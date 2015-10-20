<?php

/* Create tokens table on theme switch */
function create_tokenTable(){
	global $wpdb;
	return $wpdb->query(" CREATE TABLE IF NOT EXISTS _api_active_tokens (
							  id int(12) unsigned NOT NULL AUTO_INCREMENT,
							  user_id varchar(12) NOT NULL,
							  token varchar(32) NOT NULL,
							  token_status tinyint(1) NOT NULL DEFAUlT 0,
							  expiration bigint(20) unsigned NOT NULL,
							  token_salt varchar(32),
							  gen_timestamp timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
							  PRIMARY KEY (id)
							) ENGINE=InnoDB DEFAULT CHARSET=utf8;
						");
}
add_action('switch_theme', 'create_tokenTable');

/* Via POST 
 * Check login data matches, activate token and return user data
 * DISABLING TOKEN RESULTS IN DENIED PROTECTED REQUESTS BUT CAN STILL BE USED AS A PASSIVE TOKEN
 * @param String @user_login (via $_POST) The username
 * @param String @user_password (via $_POST) the password matching the user
 * @return JSON encoded user data to store locally
 * @see get User basic data
 */
function mobile_pseudo_login() {
	
	if(!isset($_POST['user_login']) && !isset($_POST['user_password'])) return wp_send_json_error();
	
	global $rest;
	extract($_POST);
	$creds = array();
	$creds['user_login'] = $user_login;
	$creds['user_password'] = $user_password;
	$creds['remember'] = true;
	$SignTry = wp_signon( $creds, false );

	if( !is_wp_error($SignTry)){
		
		$user_id 	= $SignTry->ID;
		$user_login = $SignTry->user_login;
		$role 		= $SignTry->roles[0];
		$user_name 	= $SignTry->display_name;

		/* Validate token before sending response */
		if(!$rest->check_token_valid('none', $request_token)){
			$response = $rest->update_tokenStatus($request_token, 'none', 1);
			if($user_id) $rest->settokenUser($request_token, $user_id);
			
			/* Return user info to store client side */
			if($response){
				wp_send_json_success(array(
										'user_id' 		=> $user_id,
										'user_login' 	=> $user_login,
										'user_name' 	=> $user_name,
										'role' 			=> $role
									));
				exit;
			}
			/* Error: Something went wrong */
			return wp_send_json_error();
			exit;
		}
	}
	/* There was an error processing auth request */
	wp_send_json_error("Couldn't sign in using the data provided");
}

/* Check login data matches, activate token and return user data
 * DISABLING TOKEN RESULTS IN DENIED PROTECTED REQUESTS BUT CAN STILL BE USED AS A PASSIVE TOKEN
 * @param String @user_login (via $_POST) The username
 * @param String @user_password (via $_POST) the password matching the user
 * @return JSON encoded user data to store locally
 * @see get User basic data
 */
function _mobile_pseudo_login($user_login, $user_password, $request_token) {
	
	if(!isset($user_login) && !isset($user_password)) return wp_send_json_error();
	
	global $rest;
	$creds = array();
	$creds['user_login'] = $user_login;
	$creds['user_password'] = $user_password;
	$creds['remember'] = true;
	$SignTry = wp_signon( $creds, false );

	if( !is_wp_error($SignTry)){
		
		$user_id 	= $SignTry->ID;
		$user_login = $SignTry->user_login;
		$role 		= $SignTry->roles[0];
		$user_name 	= $SignTry->display_name;

		/* Validate token before sending response */
		if(!$rest->check_token_valid('none', $request_token)){
			$response = $rest->update_tokenStatus($request_token, 'none', 1);
			if($user_id) $rest->settokenUser($request_token, $user_id);
			
			/* Return user info to store client side */
			if($response){
				wp_send_json_success(array(
										'user_id' 		=> $user_id,
										'user_login' 	=> $user_login,
										'user_name' 	=> $user_name,
										'role' 			=> $role
									));
				exit;
			}
			/* Error: Something went wrong */
			return FALSE;
			exit;
		}
	}
	/* There was an error processing auth request */
	wp_send_json_error("Couldn't sign in using the data provided");
}

/* Disable token in database for the logged user
 * DISABLING TOKEN RESULTS IN DENIED PROTECTED REQUESTS BUT CAN STILL BE USED AS A PASSIVE TOKEN
 * @param String @logged The username
 * @param String @request_token (via $_POST) the active request token for this user
 */
function mobile_pseudo_logout($logged){
	$user = get_user_by('slug', $logged);
	if(!isset($_POST['request_token']) || !$user) return wp_send_json_error();

	global $rest;
	/* Validate token before sending response */
	if($rest->check_token_valid($user->ID, $_POST['request_token'])){
		$response = $rest->update_tokenStatus($_POST['request_token'], $user->ID, 0);
		
		/* Return user info to store client side */
		if($response){
			wp_send_json_success();
			exit;
		}
		/* Error: Something went wrong */
		wp_send_json_error();
	}
	exit;
}

/* DEPRECATED */
/* DEPRECATED */
/* DEPRECATED (if used) PLEASE CHECK LOGIN USING TOKEN with checkToken endpoint */
/* DEPRECATED */
/* DEPRECATED */
function mobile_login_check($user_id, $user_token){
	wp_send_json_success();
}

// CATEGORIES
function follow_category($user_login){
	
	$user = get_user_by('login', $user_login);
	if(museografo_follow_category($user)) 
		wp_send_json_success();
	wp_send_json_error('Problem while following category');
}
add_action('wp_ajax_follow_category', 'follow_category');
add_action('wp_ajax_nopriv_follow_category', 'follow_category');

function unfollow_category($user_login){
	
	$user = get_user_by('login', $user_login);
	if(museografo_unfollow_category($user)) 
		wp_send_json_success();
	wp_send_json_error('Problem while following category');
}
add_action('wp_ajax_unfollow_category', 'unfollow_category');
add_action('wp_ajax_nopriv_unfollow_category', 'unfollow_category');

/*
 * ****** THIS IS A MARIN METHOD *******
 * Get expos feed
 * @param Int $offset
 * @param Int $limit
 * @return JSON Object 
 */
function get_expos_feed($offset = NULL, $limit = NULL){

	$events_feed = array();
	if($offset){
		$args = array(
						"post_type" 		=> "exposicion",
						"posts_per_page" 	=> $limit,
						"offset" 			=> $offset,
					);
	}else{
		$args = array(
						"post_type" 		=> "exposicion",
						"posts_per_page" 	=> -1,
					);
	}
	$expos = get_posts($args);
	$expos_complete = array("pool" => $expos, "count" => count($expos));
	foreach ($expos_complete['pool'] as $each_expo) {
		$thumb =  wp_get_attachment_image_src(get_post_thumbnail_id($each_expo->ID), 'thumbnail');
		$each_expo->thumb_url = $thumb[0];
	}
	return json_encode($expos_complete);
}
/*
 * Get single event info
 * @param Int $event_id
 * @param String $user_login
 */
function get_event_single($event_id, $user_login){
	$logged_user 	= get_user_by('slug', $user_login);
	if(!$logged_user) 
		return wp_send_json_error('Not a valid user or you don\'t have enough permissions');
	return json_encode(get_event_stdinfo($event_id, TRUE, $logged_user));
}

/*
 * Get gallery attachments for a event
 * @param Int $event_id
 * @param Int $limit
 * @param String $size
 */
function get_event_gallery($event_id, $limit = 5, $size = 'gallery_mobile'){
	$feat_id = get_post_thumbnail_id($event_id);
	$args = array(
	   'post_type' 		=> 'attachment',
	   'post_status' 	=> 'any',
	   'post_parent' 	=> $event_id,
	   'exclude'		=> $feat_id,
	   'posts_per_page' => $limit
	);

	$attachments = get_posts( $args );
	$images = array();
	if ( $attachments ) {
		foreach ( $attachments as $attachment ) {

			$img_url = museo_get_attachment_url($attachment->ID, $size);
			$this_array = array(
									'src' => $img_url[0],
									'w' => ($size == 'thumbnail') ? 150 : 350,
									'h' => ($size == 'thumbnail') ? 150 : 240,
								);
			$images['items'][]  = $this_array;
		}
	}
	return json_encode($images);
}

/*
 * Get attachments uploaded by a user
 *
 * @param String $user_login
 * @param Int $limit
 * @param String $size
 */
function get_user_gallery($user_login, $limit = 5, $size = 'gallery_mobile'){
	$user_object = get_user_by("slug", $user_login);
	if(!$user_object)
		wp_send_json_error("No such user or not enough permissions");
	$args = array(
	   'post_type' 		=> 'attachment',
	   'post_status' 	=> 'private',
	   'author' 		=> $user_object->ID,
	   'posts_per_page' => $limit
	);

	$attachments = get_posts( $args );
	$images = array();
	if ( $attachments ) {
		foreach ( $attachments as $attachment ) {

			$img_url = museo_get_attachment_url($attachment->ID, $size);
			$this_array = array(
									'src' => $img_url[0],
									'w' => ($size == 'thumbnail') ? 150 : 350,
									'h' => ($size == 'thumbnail') ? 150 : 240,
								);
			$images['items'][]  = $this_array;
		}
		$images['user_nicename'] = get_the_author_meta( 'display_name', $user_object->ID );
	}
	return json_encode($images);
}

/*
 * Get projects uploaded by an artist
 *
 * @param String $user_login
 * @param Int $limit
 * @param String $size
 */
function get_artist_projects($user_login, $limit = 5, $size = 'gallery_mobile'){
	$user_object = get_user_by("slug", $user_login);
	if(!$user_object)
		wp_send_json_error("No such user or not enough permissions");
	$id_array = get_gallery_array($user_object->ID);
	$project_array = array('items' => array());

	if($id_array){
		foreach ($id_array as $index => $attachment_id) {
		 	$url_full 	 = museo_get_attachment_url( $attachment_id, $size);
		 	$att_post 	 = get_post($attachment_id);
		 	$title 		 = $att_post->post_title;
		 	$description_rough = $att_post->post_content;
		 	$description = wpautop($description_rough);
		 	$project_array['items'][] = array(
		 							'src' 		=> $url_full[0],
		 							'w' 		=> ($size == 'thumbnail') ? 150 : 350,
		 							'h' 		=> ($size == 'thumbnail') ? 150 : 240,
		 							'title' 	=> $title,
		 							'description' => $description,
		 							'caption' => $description_rough
		 						);		 	
		}
		return json_encode($project_array);
	}
		

}

/*
 * Get special projects uploaded by an artist
 *
 * @param String $user_login
 * @param String $size
 * @param Int $limit
 */
function get_artist_specialprojects($user_login, $limit = -1){
	$user_object = get_user_by("slug", $user_login);
	if(!$user_object)
		wp_send_json_error("No such user or not enough permissions");
	$args = array(
				"post_type" 	=> "proyectos-especiales",
				"author" 		=> $user_object->ID,
				"posts_per_page" => $limit
			);
	return get_posts($args);
}

function get_venue_event_count($venue_id){
	$venue_object = get_user_by("id", $venue_id);
	return count(museografo_eventos_creados($venue_object));
}

function get_venue_events($venue_id, $logged_user, $offset = 0, $limit = 10){

	$events_feed = array();
	$venue_object = get_user_by("id", $venue_id);
	$user_object = get_user_by("slug", $logged_user);
	if(!$user_object)
		wp_send_json_error("No such user or not enough permissions");
	$created_events = museografo_eventos_creados($venue_object);
	
	if(!empty($created_events))
		foreach ($created_events as $event) {
			
			if(is_event_ontime($event->ID)){
				$events_feed['results']['ontime'][] = get_event_stdinfo($event->ID, FALSE, $user_object);
			}else{
				$events_feed['results']['history'][] = get_event_stdinfo($event->ID, FALSE, $user_object);
			}
			
		}	
	return json_encode($events_feed);
}

function get_user_events($user_id, $logged_user, $offset = 0, $limit = 10){

	$events_feed = array();
	$user_object = get_user_by("id", $user_id);
	$scheduled_feed = json_decode(get_scheduled_feed($user_object, 0, 20, TRUE));
	if(!empty($scheduled_feed->results))
	foreach ($scheduled_feed->results as $event) {
		$type = array_values(get_the_terms( $event->ID, 'tipo-de-evento' ));
		$ID_venue = get_post_meta($event->ID ,'mg_venue_id',true);
		$thumb_url = museo_get_attachment_url( get_post_thumbnail_id($event->ID), 'eventos-feed' );

		if(is_future_event($event->ID)){
			$events_feed['results']['scheduled'][] = array(
									'ID' 				=> $event->ID,
									'event_title' 		=> $event->event_title,
									'event_description' => wp_trim_words($event->event_description, 18, '...'),
									'event_thumbnail' 	=> $thumb_url[0],
									'event_type'	 	=> (!empty($type)) ? $type[0]->name : null,
									'venue_id' 			=> $ID_venue,
									'venue' 			=> (get_the_author_meta( 'display_name', $ID_venue ) !== '') ? get_the_author_meta( 'display_name', $ID_venue ) : NULL,
									'venue_avatar' 		=> (museo_get_profilepic_url($ID_venue) !== '') ? museo_get_profilepic_url($ID_venue) : NULL,
									'date_start' 		=> (fecha_inicio_evento($event->ID) !== '') ? fecha_inicio_evento($event->ID) : NULL,
									'date_end' 			=> (fecha_fin_evento($event->ID) !== '') ? fecha_fin_evento($event->ID) : NULL,
									'latlong' 			=> (get_post_meta($event->ID,'mg_evento_latlong', true) !== '') ? get_post_meta($event->ID,'mg_evento_latlong', true) : NULL,
									'address' 			=> (get_post_meta($event->ID,'mg_evento_direccion', true) !== '') ? get_post_meta($event->ID,'mg_evento_direccion', true) : NULL,
									'scheduled' 		=> ( in_array( $event->ID, museografo_eventos_agendados($user_object, TRUE) ) ) ? true : false,
									'attended' 			=> ( in_array( $event->ID, get_attended_events($user_object) ) ) ? true : false
								);
		}else{
			$events_feed['results']['history'][] = array(
									'ID' 				=> $event->ID,
									'event_title' 		=> $event->event_title,
									'event_description' => wp_trim_words($event->event_description, 18, '...'),
									'event_thumbnail' 	=> $thumb_url[0],
									'event_type'	 	=> (!empty($type)) ? $type[0]->name : null,
									'venue_id' 			=> $ID_venue,
									'venue' 			=> get_the_author_meta( 'display_name', $ID_venue ),
									'venue_avatar' 		=> museo_get_profilepic_url($ID_venue),
									'date_start' 		=> fecha_inicio_evento($event->ID),
									'date_end' 			=> fecha_fin_evento($event->ID),
									'latlong' 			=> get_post_meta($event->ID,'mg_evento_latlong', true),
									'address' 			=> get_post_meta($event->ID,'mg_evento_direccion', true),
									'scheduled' 		=> ( in_array( $event->ID, museografo_eventos_agendados($user_object, TRUE) ) ) ? true : false,
									'attended' 			=> ( in_array( $event->ID, get_attended_events($user_object) ) ) ? true : false
								);
		}
		
	}	
	return json_encode($events_feed);
}


/*
 * Get agenda for a user
 *
 * @param String $logged_user user login part of the endpoint
 * @param Int $offset
 * @param Object $logged_user
 * @param Boolean $flag_all Set TRUE if you don't want to filter events through ontime method
 * @return Object
 */
function get_scheduled_feed($logged_user, $offset, $limit = 10, $flag_all = FALSE){
	if(!$logged_user)
		return NULL;
	
	$scheduled = array_map('intval', museografo_eventos_agendados($logged_user, $flag_all));
	
	$scheduled_full = array();
	$scheduled_full['event_count'] = 0;
	if(!$scheduled) return json_encode($scheduled_full);
	foreach ($scheduled as $each_event) {
		$event = get_post($each_event);
		if(!$event)
			continue;
		//TO DO: Pagination using offset
		if(count($scheduled_full) >= $limit) 
			return $scheduled_full;
		
		$ID_venue = get_post_meta($event->ID ,'mg_venue_id',true);
		$thumb_url = museo_get_attachment_url( get_post_thumbnail_id($event->ID), 'eventos-feed' );
		$scheduled_full['event_count']++;
		$latlong = (get_post_meta($event->ID,'mg_evento_latlong', true) !== '') ? get_post_meta($event->ID,'mg_evento_latlong', true) : NULL;
		$scheduled_full['results'][] = array(
								'ID' 				=> $event->ID,
								'event_title' 		=> $event->post_title,
								'event_description' => $event->post_content,
								'event_thumbnail' 	=> $thumb_url[0],
								'venue' 			=> get_the_author_meta( 'display_name', $ID_venue ),
								'venue_avatar' 		=> museo_get_profilepic_url($ID_venue),
								'date_start' 		=> fecha_inicio_evento($event->ID),
								'date_end' 			=> fecha_fin_evento($event->ID),
								'date_end_unformatted' 	=> fecha_fin_evento($event->ID, TRUE),
								'venue_latlong' 	=> $latlong
							);
	}
	if(empty($scheduled_full['results']))
		return json_encode(array('empty_set' => TRUE, 'event_count' => 0, 'results' => array()));
	$date_end_array = array();
	foreach ($scheduled_full['results'] as $key => $row)
	    $date_end_array[$key] = $row['date_end_unformatted'];
	/* Order events by closest ending date */
	array_multisort($date_end_array, SORT_ASC, $scheduled_full['results']);
	return json_encode($scheduled_full);
}

// USERS
function get_user_profile($queried_user = NULL, $logged_user = NULL){
	if(!$queried_user) 
		return json_encode(array("success" => FALSE, "error" => "No user queried"));
	if(!$logged_user){
		global $current_user;
	}else{
		$current_user = get_user_by('slug', $logged_user);
	}
	$user_object 	= get_user_by( 'slug', $queried_user );
	if(!$user_object)
		wp_send_json_error("No such user in the database, check data and try again");
	$user_firstname = get_user_meta( $user_object->ID, 'first_name', true);
	$user_lastname 	= get_user_meta( $user_object->ID, 'last_name', true);
	$user_meta_bio_rough = get_user_meta( $user_object->ID, 'description', true );
	$user_meta_bio 	= wpautop(addslashes($user_meta_bio_rough));
	$user_meta_country = get_user_meta($user_object->ID, 'profile_country', true);
	$user_meta_city = get_user_meta( $user_object->ID, 'profile_city', true);
	$user_meta_gender = (get_user_meta( $user_object->ID, 'sexo', true) !== '') ? strtolower(get_user_meta( $user_object->ID, 'sexo', true)) : NULL;
	$user_avatar 	= museo_get_profilepic_url($user_object->ID);
	$is_private 	=  get_user_meta( $user_object->ID, 'private_profile', true);
	$role_prefix 	= $user_object->roles[0];
	if($role_prefix !== 'venue' AND $role_prefix !== 'artista')
		$role_prefix = 'suscriptor';
	if($role_prefix == 'suscriptor'){
		$user_events = json_decode(get_user_events($user_object->ID, $current_user, 0));
	}else{
		$user_events = json_decode(get_venue_events($user_object->ID, get_clean_userlogin($current_user->ID), 0));
	}
	
	$user_data = array(
						'ID' 			=> $user_object->ID,
						'user_display' 	=> $user_object->display_name,
						'user_login' 	=> get_clean_userlogin($user_object->ID),
						'user_nicename' => $user_object->user_nicename,
						'first_name' 	=> ($user_firstname) ? $user_firstname : NULL,
						'last_name' 	=> ($user_lastname) ? $user_lastname : NULL,
						'nickname' 		=> $user_object->nickname,
						'gender' 		=> get_user_meta($user_object->ID, 'sexo', true),
						'user_role' 	=> $user_object->roles[0],
						'user_bio_rough' => ($user_meta_bio_rough !== '') ? $user_meta_bio_rough : null,
						'user_bio' 		=> ($user_meta_bio !== '') ? $user_meta_bio : null,
						'user_avatar' 	=> ( isset($user_avatar) AND $user_avatar !== '') ? $user_avatar : null,
						'user_email' 	=> $user_object->user_email,
						'country' 		=> ($user_meta_country) ? get_nice_term($user_meta_country) : NULL,
						'city' 			=> ($user_meta_city) ? get_nice_term($user_meta_city) : NULL,
						'birthday' 		=> get_user_meta($user_object->ID, 'fecha-nacimiento', true),
						'is_gender_'.$user_meta_gender 	=> true,
						'follows' 		=> array(
													'venues' 	=> checa_total_siguiendo($user_object->ID, 'venue'),
													'artists' 	=> checa_total_siguiendo($user_object->ID, 'artista'),
													'users' 	=> checa_total_siguiendo($user_object->ID, 'suscriptor'),
													'all' 		=> checa_total_siguiendo($user_object->ID, 'any')
												),
						'followers' 	=> array(
													'artists' 	=> checa_total_seguidores($user_object->ID, 'artista'),
													'users' 	=> checa_total_seguidores($user_object->ID, 'suscriptor'),
													'all' 		=> checa_total_seguidores($user_object->ID, 'any')
												),
						'upload_count'	=> get_total_attachment_user_id( $user_object->ID ),
						'comment_count'	=> checa_total_comments_echos( $user_object->ID ),
						'event_count'	=> array(
												'scheduled' => get_eventos_agendados($user_object->ID),
												'attended'  => (get_eventos_asistidos($user_object->ID))
											),
						'events'		=> $user_events,
						'is_following'	=> ($current_user) ? intval(checa_si_sigue_el_usuario($current_user->ID ,$user_object->ID)) : 'undefined',
						'is_private'	=> ($is_private === 'true') ? TRUE : FALSE,
						'role_prefix'	=> $role_prefix,
						'is_'.$role_prefix		=> TRUE
					);
	
	return json_encode($user_data);
}

// VENUES
function get_venue_profile($venue_id, $user = NULL){
	if(!$user){
		global $current_user;
	}else{
		$current_user = get_user_by('slug', $user);
	}
	$venue_object = get_user_by('id', $venue_id);
	$venue_meta = get_user_meta($venue_id);
	
	return array(
						'ID' 			=> $venue_id,
						'venue_name' 	=> $venue_object->display_name,
						'venue_slug' 	=> get_clean_userlogin($venue_object->ID),
						'venue_bio' 	=> !empty($venue_meta['description']) ? wpautop($venue_meta['description'][0]) : NULL,
						'venue_city' 	=> !empty($venue_meta['ciudad'][0]) ? $venue_meta['ciudad'][0] : NULL,
						'venue_phone' 	=> !empty($venue_meta['telefono'][0]) ? $venue_meta['telefono'][0] : NULL,
						'venue_address' => !empty($venue_meta['direccion'][0]) ? $venue_meta['direccion'][0] : NULL,
						'venue_hours'   => (get_user_meta($venue_id, 'horario', true) !== '' ) ? get_user_meta($venue_id, 'horario', true)  : NULL,
						'latlong'  	 	=> !empty($venue_meta['latlong'][0]) ? $venue_meta['latlong'][0] : NULL,
						'venue_avatar' 	=> (museo_get_profilepic_url($venue_id) !== '') ? museo_get_profilepic_url($venue_id) : NULL,
						'follows' 		=> array(
													'venues' 	=> checa_total_siguiendo($venue_id, 'venue'),
													'artists' 	=> checa_total_siguiendo($venue_id, 'artista'),
													'users' 	=> checa_total_siguiendo($venue_id, 'suscriptor'),
													'all' 		=> checa_total_siguiendo($venue_id, 'any')
												),
						'followers' 	=> array(
													'artists' 	=> checa_total_seguidores($venue_id, 'artista'),
													'users' 	=> checa_total_seguidores($venue_id, 'suscriptor'),
													'all' 		=> checa_total_seguidores($venue_id, 'any')
												),
						'is_following'	=> ($current_user) ? intval(checa_si_sigue_el_usuario($current_user->ID ,$venue_id)) : 'undefined',
						'events'		=> json_decode(get_venue_events($venue_object->ID, get_clean_userlogin($current_user->ID), 0)),
						'event_count'   => get_venue_event_count($venue_object->ID)
					);
}

// TIMELINE
function get_user_timeline($user, $offset){

	$user = get_user_by( 'login', $user );
	$activities = get_actividades($offset*10, $user);
	
	/* TO DO: Do all this in separate functions */
	$full_timeline = array();
	foreach ($activities as $key => $activity) {
		
		if($activity->type == 'evento'){
			$event = get_post($activity->post_id);
			$thumb_url = museo_get_attachment_url( get_post_thumbnail_id($activity->post_id), 'eventos-feed' );
			$ID_venue = get_post_meta($activity->post_id ,'mg_venue_id',true);
			$full_timeline[] = array(
										'ID' 				=> $activity->post_id,
										'type' 				=> $activity->type,
										'event_title' 		=> $event->post_title,
										'event_author'  	=> $event->post_author,
										'event_slug' 		=> $event->post_name,
										'event_description' => wp_trim_words($event->post_content, 16, "..."),
										'event_thumbnail' 	=> $thumb_url[0],
										'venue' 			=> get_the_author_meta( 'display_name', $ID_venue ),
										'venue_id' 			=> $ID_venue,
										$activity->type 	=> true,
										'venue_avatar' 		=> museo_get_profilepic_url($ID_venue),
										'venue_latlong'		=> get_user_meta($ID_venue, 'latlong', true),
										'date_start' 		=> fecha_inicio_evento($event->ID),
										'date_end' 			=> fecha_fin_evento($event->ID),
										'costo' 			=> get_post_meta($event->ID,'mg_costo',true),
										'address' 			=> get_post_meta($event->ID,'mg_evento_direccion', true),
										'scheduled' 		=> ( in_array( $activity->post_id, museografo_eventos_agendados($user) ) ) ? true : false,
									);
		}elseif($activity->type == 'resena'){
			$data_excerpt = get_comment($activity->data_id);
			$data_excerpt = wp_trim_words($data_excerpt->comment_content, 10);
			$event = get_post($activity->post_id);
			$act_user_meta = get_the_author_meta( 'display_name', $activity->user_id );
			$act_userlogin_meta = get_the_author_meta( 'user_login', $activity->user_id );
			
			$activity_user = get_user_by('slug', $act_userlogin_meta);
			$full_timeline[] = array(
										'ID' 			=> $activity->actividad_id,
										'user_id' 		=> $activity->user_id,
										'user_nicename' => $act_user_meta,
										'user_login' 	=> $activity_user->user_login,
										'user_avatar' 	=> museo_get_profilepic_url($activity->user_id),
										'event_id' 		=> $activity->post_id,
										'event_title' 	=> $event->post_title,
										$activity->type => true,
										'data_excerpt' 	=> (isset($data_excerpt)) ? $data_excerpt :'',
										'type' 			=> $activity->type,
									);
		}elseif($activity->type == 'media'){
			$event = get_post($activity->post_id);
			$media_thumbnail = museo_get_attachment_url( $activity->data_id, 'agenda-feed' );
			$media_thumbnail = ($media_thumbnail) ? $media_thumbnail[0]: "images/event_placeholder.png";
			$act_user_meta = get_the_author_meta( 'display_name', $activity->user_id );
			$act_userlogin_meta = get_the_author_meta( 'user_login', $activity->user_id );
			
			$activity_user = get_user_by('slug', $act_userlogin_meta);
			$full_timeline[] = array(
										'ID' 			=> $activity->actividad_id,
										'user_id' 		=> $activity->user_id,
										'user_nicename' => $act_user_meta,
										'user_login' 	=> $activity_user->user_login,
										'user_avatar' 	=> museo_get_profilepic_url($activity->user_id),
										'event_id' 		=> $activity->post_id,
										'event_title' 	=> $event->post_title,
										$activity->type => true,
										'media_thumbnail' 	=> $media_thumbnail,
										'type' 			=> $activity->type,
									);
		}elseif($activity->type == 'agendo'){
			$user = get_user_by('id', $activity->user_id);
			$event = get_post($activity->post_id);
			
			$full_timeline[] = array(
										'ID' 				=> $activity->actividad_id,
										'user_id' 			=> $activity->user_id,
										'user_login' 		=> $user->user_login,
										'user_nicename' 	=> get_the_author_meta( 'display_name', $activity->user_id ),
										'user_avatar' 		=> museo_get_profilepic_url($activity->user_id),
										'event_id' 			=> $event->ID,
										'event_title' 		=> $event->post_title,
										$activity->type 	=> true,
										'type' 				=> $activity->type
									);
		}else{
			$user = get_user_by('id', $activity->user_id);
			$_role = (!empty($user->roles) AND $user->roles[0] != 'subscriber' ) ? $user->roles[0] : 'user';
			$post_user = get_user_by('id', $activity->post_id);
			$role = (!empty($post_user->roles) AND $post_user->roles[0] != 'subscriber' ) ? $post_user->roles[0] : 'user';
			
			$full_timeline[] = array(
										'ID' 				=> $activity->actividad_id,
										'type' 				=> $activity->type,
										$activity->type 	=> true,
										'user_id' 			=> $activity->user_id,
										'user_login' 		=> get_clean_userlogin($user->ID),
										'user_nicename' 	=> get_the_author_meta( 'display_name', $activity->user_id ),
										'is_'.$_role 		=> TRUE,
										'user_avatar' 		=> museo_get_profilepic_url($activity->user_id),
										'followed'			=> array(
																	'user_id' 		=> $post_user->ID,
																	'user_login' 	=> get_clean_userlogin($post_user->ID),
																	'user_nicename' => $post_user->display_name,
																	'is_'.$role 	=> TRUE,
																)
									);
		}
	}
	return json_encode($full_timeline);
}


// COMMENTS
function get_event_comments($post_id, $offset = 0){

	$args = array(
					'post_id' => $post_id,
					'number'  => 5,
					'parent'  => 0,
					'offset'  => $offset
				);
	$comments = get_comments($args);
	
	if(!empty($comments)){
		$comments_data = array();
		foreach ($comments as $comment) {
			$comments_data[] = array(
										'ID' 			=> $comment->comment_ID,
										'author_name' 	=> $comment->comment_author,
										'author_thumbnail' 	=> museo_get_profilepic_url($comment->user_id),
										'content' 		=> $comment->comment_content,
										'upvotes' 		=> total_votos($comment->comment_ID, 'up'),
										'downvotes' 	=> total_votos($comment->comment_ID, 'down')
									); 
		}
		$array_return = array(
						'pool' 			=> $comments_data,
						'comment_count' => count($comments_data)
					);
		wp_send_json_success($array_return);
	}else{
		wp_send_json_success(array(
						'pool' 			=> NULL,
						'comment_count' => 0
					));
	}
	wp_send_json_error();
}

function get_child_comments($post_id, $parent_comment_id = 0, $limit = 0){
	$args = array(
					'post_id' => $post_id,
					'number'  => $limit,
					'parent'  => $parent_comment_id
				);
	$comments = get_comments($args);
	if(!empty($comments)){
		$comments_data = array();
		foreach ($comments as $comment) {
			$comments_data[] = array(
										'ID' 			=> $comment->comment_ID,
										'author_name' 	=> $comment->comment_author,
										'author_thumbnail' 	=> museo_get_profilepic_url($comment->user_id),
										'content' 		=> $comment->comment_content,
										'upvotes' 		=> total_votos($comment->comment_ID, 'up'),
										'downvotes' 	=> total_votos($comment->comment_ID, 'down')
									); 
		}
		$array_return = array(
						'pool' 			=> $comments_data,
						'comment_count' => count($comments_data)
					);
		wp_send_json_success($array_return);
	}
	wp_send_json_error();
}

function vote_comment($args){
	extract($args);
	if(agregar_voto($voter, $args)) return TRUE;
}

function get_user_comments($user_id){
	$user_comments = get_comments_echos($user_id);
	if($user_comments) return json_encode($user_comments);
}

function post_comment_to_event($logged_user){
	$current_user 	 = get_user_by('slug', $logged_user);
	$event_id 		 = isset($_POST['event_id']) ? $_POST['event_id'] : NULL;
	$comment_content = isset($_POST['comment_content']) ? $_POST['comment_content'] : NULL;
	if(!$event_id OR !$comment_content) return FALSE;
	$data = array(
				    'comment_post_ID' => $event_id ,
				    'comment_author' => $current_user->display_name,
				    'comment_content' => $comment_content,
				    'user_id' => $current_user->ID,
				    'comment_agent' => 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.0.10) Gecko/2009042316 Firefox/3.0.10 (.NET CLR 3.5.30729)',
				    'comment_approved' => 1,
				);
	if(wp_insert_comment($data))
		return TRUE;
}

function get_notifications_pool($user_login, $limit = 10){

	$user = get_user_by('login', $user_login);
	$data = array();
	$data['count'] = get_count_alertas_user($user);
	$data['pool'] = get_alertas_user($limit, $user);
	foreach ($data['pool'] as $key => $notification) {
		$notification->estatus = intval($notification->estatus);
		$user_object = get_user_by('id',  $notification->user_id );
		if(!$user_object) continue;
		$notification->user_login = $user_object->user_login;
		$notification->event_title = get_the_title($notification->objeto_id);
		if($notification->tipo == 'recomendo') 	$notification->recommend = true;
		if($notification->tipo == 'voto') 		$notification->vote = true;
		if($notification->tipo == 'siguiendo') 	$notification->follow = true;
		if($notification->tipo == 'friend_fb') 	$notification->friend_fb = true;
	}
	$data['user_login'] = $user_login;
	$user_avatar 	= museo_get_profilepic_url($user->ID);
	$data['profile_pic'] = ( isset($user_avatar) AND $user_avatar !== '') ? $user_avatar : null;
	return json_encode( $data );
}

function recomend_event_to_user($user){
	//TO DO: Return falsie value if not done
	ajax_museografo_recomendar($user);
}

function update_user_profile($user_login, $args){
	if(museografo_completar_perfil($user_login, $args)) 
		wp_send_json_success();
	wp_send_json_error();
}

/* 
 * Get a random categories feed
 * @param Object $user
 * @param Int $number = 4
 * @return JSON success plus data array
 */
function get_rand_categories_feed($user, $number = 4){

	global $wpdb;

	$query_ids = $wpdb->get_results(
			$wpdb->prepare( 
				"SELECT tt.term_id FROM wp_term_taxonomy AS tt
				  INNER JOIN wp_terms AS wpt
				   	ON tt.term_id = wpt.term_id
						WHERE taxonomy = 'category'
						AND tt.term_id != '1'
						ORDER BY rand() LIMIT %d
					;"
				, $number
			), ARRAY_N
		);
	
	$concat = "";
	foreach ($query_ids as $id) 
		$concat .= $id[0].",";
	$args = array(
		'type'				=> 'post',
		'hide_empty' 		=> 0,
		'include'			=> $concat,
		'exclude'			=> '1'

	);
	$cat_feed = array();
	$categories = get_categories($args);
	$seguidas = museografo_categorias_seguidas($user);
	$parent_name = NULL;
	
	foreach ($categories as $key => $category) {
		$thumb = get_tax_meta($category->term_id, 'mg_imagen_categoria');
		$thumb = ($thumb !== '') ? $thumb : array('id' =>'', 'src' =>'');
		$cat_object = get_term_by('id', $category->term_id, 'category');

		$cat_feed['pool'][] = array(
						'ID' 				=> $category->term_id,
						'title' 			=> $category->name,
						'slug' 				=> $category->slug,
						'thumbnail' 		=> $thumb['src'],
						'has_description' 	=> ($cat_object->description) ? TRUE : FALSE,
						'is_following'		=> false
					);
		if(in_array($category->term_id, $seguidas)){
			$cat_feed['pool'][$key]['is_following'] = true;
		}
	}
	if(!empty($cat_feed)) wp_send_json_success($cat_feed);
	wp_send_json_error();
}

/* 
 * Get the categories feed by level
 * @param Object $user
 * @param Int $level The parent level of categories to retrieve
 * @param Mixed $exclude Id(s) to exclude from query
 * @return JSON success plus data array
 */
function get_categories_feed($user, $level, $exclude = NULL){
	$args = array(
		'parent' 		=> $level,
		'type' 			=> 'post',
		'hide_empty' 	=> 0,
		'exclude' 		=> array(1, $exclude)
	);
	$cat_feed = array();
	$categories = get_categories($args);
	$seguidas = museografo_categorias_seguidas($user);
	$parent_name = NULL;
	if($level != 0) {
		$cat_object = get_term_by('id', $level, 'category');
		if(!$cat_object) wp_send_json_error("No such category id");
		$parent_name = $cat_object->name;
		$parent_thumb = get_tax_meta($cat_object->term_id, 'mg_imagen_categoria');
		$parent_thumb = ($parent_thumb !== '') ? $parent_thumb : array('id' =>'', 'src' =>'');

		$cat_feed['parent'] = $level;
		$cat_feed['parent_name'] = $parent_name;
		$cat_feed['parent_thumbnail'] = $parent_thumb['src'];
		$cat_feed['parent_is_following'] = FALSE;
		if(in_array($cat_object->term_id, $seguidas) AND $level != 0){
			$cat_feed['parent_is_following'] = true;
		}
	}
	foreach ($categories as $key => $category) {
		$thumb = get_tax_meta($category->term_id, 'mg_imagen_categoria');
		$thumb = ($thumb !== '') ? $thumb : array('id' =>'', 'src' =>'');
		$cat_object = get_term_by('id', $category->term_id, 'category');

		$cat_feed['pool'][] = array(
						'ID' 				=> $category->term_id,
						'title' 			=> $category->name,
						'slug' 				=> $category->slug,
						'thumbnail' 		=> $thumb['src'],
						'has_description' 	=> ($cat_object->description) ? TRUE : FALSE,
						'is_following'		=> false
					);
		if(in_array($category->term_id, $seguidas)){
			$cat_feed['pool'][$key]['is_following'] = true;
		}
	}
	if(!empty($cat_feed)) wp_send_json_success($cat_feed);
	wp_send_json_error();
}

/*
 * Get explore json content for a category
 * TO DO: Analyze data and explore "campaigns" 
 * @param Int Category ID
 * @param String $logged_user
 * @see EXPLORE methods
 * TO DO: Make Explore stuff a class on it's own
 * TO DO: Keep getting children recursively if has_description is FALSE
 */
function get_category_detail($category_id, $logged_user){
	$user = get_user_by('slug', $logged_user);
	if(!$user)
		wp_send_json_error("Not a valid user or not enough permissions");
	if(!$category_id)
		wp_send_json_error("Not a valid category identifier");
	$seguidas = museografo_categorias_seguidas($user);
	$inarray = in_array($category_id, $seguidas) ? TRUE : FALSE;
	$cat_object = get_term_by('id', $category_id, 'category');
	$thumb = get_tax_meta($category_id, 'mg_imagen_categoria');
	$thumb = ($thumb !== '') ? $thumb : array('id' =>NULL, 'src' =>NULL);

	return array(
					'ID' 				=> $category_id,
					'title' 			=> $cat_object->name,
					'slug' 				=> $cat_object->slug,
					'thumbnail' 		=> $thumb['src'],
					'is_following' 		=> $inarray,
					'description' 		=> $cat_object->description,
					'has_description' 	=> ($cat_object->description) ? TRUE : FALSE,
					'explore_venues' 	=>  explore_get_venues($category_id, $logged_user, 4),
					'explore_artists' 	=>  explore_get_artists($category_id, $logged_user, 4),
					'explore_events' 	=>  explore_get_events($category_id, $logged_user, 2)
				);
}


/*
 * EXPLORE VENUES
 * Get recommended venues based on a category
 * @param Int $category_id
 * @param Obj $logged_user
 * @param Int $number The number of posts to be retrieved
 */
function explore_get_venues($category_id, $logged_user, $limit = 4){
	$args = array(
			'number' => $limit,
			'meta_query' => array(
					'relation' => 'AND',
					array(
						'key' => 'venue_categories',
						'value' => $category_id,
						'compare' => 'LIKE'
					),
					array(
						'key' => 'wp_capabilities',
						'value' => 'venue',
						'compare' => 'LIKE'	
					)
				)
		);

	$venues = get_users($args);
	$return_array = array();
	foreach ($venues as $each_venue){
		$return_array[] = array(
									'ID' => $each_venue->ID,
									'slug' => get_clean_userlogin($each_venue->ID),
									'name' => $each_venue->display_name,
									'thumbnail' => museo_get_profilepic_url($each_venue->ID),
								);
	}
	return $return_array;
}

/*
 * EXPLORE ARTISTS
 * Get recommended artists based on a category
 * @param Int $category_id
 * @param Obj $logged_user
 */
function explore_get_artists($category_id, $logged_user, $limit = 4){
	$args = array(
			'number' => $limit,
			'meta_query' => array(
					'relation' => 'AND',
					array(
						'key' => 'venue_categories',
						'value' => $category_id,
						'compare' => 'LIKE'
					),
					array(
						'key' => 'wp_capabilities',
						'value' => 'artista',
						'compare' => 'LIKE'	
					)
				)
		);

	$artists = get_users($args);
	$return_array = array();
	foreach ($artists as $each_artist){
		$return_array[] = array(
									'ID' 	=> $each_artist->ID,
									'slug' 	=> get_clean_userlogin($each_artist->ID),
									'name' 	=> $each_artist->display_name,
									'thumbnail' => museo_get_profilepic_url($each_artist->ID),
								);
	}
	return $return_array;
}

/*
 * EXPLORE EVENTS
 * Get recommended events based on a category
 * @param Int $category_id
 * @param Obj $logged_user
 * @param Int $numberposts Default is 2
 */
function explore_get_events($category_id, $logged_user, $limit = 2){
	$args = array(
					'post_type' 	=> 'eventos',
					'post_status' 	=> 'publish',
					'posts_per_page' => $limit,
					'category__in' 	=> $category_id,
				);
	$queried_events = get_posts($args);
	
	$event_array = array();
	foreach ($queried_events as $event) {
	
		$event_array[] = get_event_stdinfo($event->ID, FALSE, $logged_user);
	}
	return $event_array;
}

/*
 * Get the standarized JSON for a single event
 * get_event_stdinfo()
 * 
 * @param Int $event_id
 * @param Boolean $complete If set to false only an excerpt of the event is retrieved
 * @param Object $logged_user Logged user object
 * @return Array $event_info
 */
function get_event_stdinfo($event_id, $complete = TRUE, $logged_user){
	$event_object 	= get_post($event_id);
	if(!$event_object OR $event_object->post_type !== 'eventos') 
		return wp_send_json_error('Event doesn\'t exist, check ID');
	$user_obj 	= get_user_by('slug', get_clean_userlogin($logged_user->ID));
	if(!$user_obj) 
		return wp_send_json_error('Not a valid user or you don\'t have enough permissions');
	$info_array = array();
	$ID_venue 		= get_post_meta($event_object->ID ,'mg_venue_id', TRUE);
	$thumb_url 		= museo_get_attachment_url( get_post_thumbnail_id($event_object->ID), 'gallery_mobile' );
	$type 			= array_values(wp_get_post_terms( $event_object->ID, 'tipo-de-evento' ));
	$event_address  = (get_post_meta($event_object->ID,'mg_evento_direccion', true) !== '') 
							? get_post_meta($event_object->ID,'mg_evento_direccion', true) 
							: get_user_meta($ID_venue, 'direccion', TRUE);
	$event_address = ($event_address == '') ? NULL : $event_address;
	$event_latlong  = (get_post_meta($event_object->ID,'mg_evento_latlong', true) !== '') 
							? get_post_meta($event_object->ID,'mg_evento_latlong', true) 
							: get_user_meta($ID_venue, 'latlong', TRUE);
	$event_latlong = ($event_latlong == '') ? NULL : $event_latlong;
	$info_array = array(
							'ID' 				=> $event_object->ID,
							'event_title' 		=> $event_object->post_title,
							'event_slug' 		=> $event_object->post_name,
							'event_description' => wpautop( wp_trim_words($event_object->post_content), $num_words = 15, $more = '...' ),
							'event_thumbnail' 	=> $thumb_url[0],
							'event_type'	 	=> (!empty($type)) ? $type[0]->name : null,
							'venue_id' 			=> $ID_venue,
							'venue' 			=> get_the_author_meta( 'display_name', $ID_venue ),
							'venue_avatar' 		=> museo_get_profilepic_url($ID_venue),
							'date_start' 		=> fecha_inicio_evento($event_object->ID),
							'date_end' 			=> fecha_fin_evento($event_object->ID),
							'latlong' 			=> $event_latlong,
							'address' 			=> $event_address,
							'scheduled' 		=> ( in_array( $event_object->ID, museografo_eventos_agendados($logged_user) ) ) ? true : false,
							'scheduled_count' 	=> event_get_scheduled_count($event_object->ID),
							'attended' 			=> ( in_array( $event_object->ID, get_attended_events($logged_user) ) ) ? true : false
						);
	if(!$complete) return $info_array;
	
	
	$category_array = wp_get_post_terms( $event_object->ID, 'category' );
	$return_categories = array();
	if(!empty($category_array))
		foreach ($category_array as $each_category)
			$return_categories[] = array(
										'ID'   => $each_category->term_id,
										'name' => $each_category->name,
										'slug' => $each_category->slug,
										'is_last_of_branch' => (!empty($each_category->description)) ? TRUE : FALSE 
									);

	$artists_array  = get_event_artists( $event_object->ID);
	$return_artists = array();

	if(!empty($artists_array))
		foreach ($artists_array as $each_artist)
			$return_artists[] = array(
										'name' => $each_artist->name,
										'slug' => $each_artist->slug
									);
	unset($info_array['event_description']);
	$complementary_array = array(
							'event_uri' 		=> get_permalink($event_object->ID),
							'event_author' 		=> $event_object->post_author,
							'event_description' => wpautop($event_object->post_content),
							'admision' 			=> (get_post_meta($event_object->ID,'mg_costo',true)) ? get_post_meta($event_object->ID,'mg_costo',true) : NULL,
							'artists' 			=> (!empty($return_artists)) ? $return_artists : NULL,
							'categories' 		=> (!empty($return_categories)) ? $return_categories : NULL
					);
	return array_merge($info_array, $complementary_array);
}



/*
 * Search website wrapper function for the API
 * @param String @search_term
 * @param Int @offset
 * @return associative Array of the results by type
 */
function search_museografo($search_term, $offset, $user = NULL){
	if(!$user){
		global $current_user;
	}else{
		$current_user = get_user_by('slug', $user);
	}
	$results_array = array();
	if($search_term === '') wp_send_json_error();
	$resultados = get_search_museo($search_term, $offset*10);
	$results_categories = search_museografo_categories($search_term);
	$new_full_array = array_merge($results_categories, $resultados);
	
	foreach ($new_full_array as $index => $each_result) {
		if($each_result->tipo == 'user'){
			
			/* If result is a User, an artist or a venue */
			$user_object = get_user_by('id', $each_result->user_id);
			$user_thumb = museo_get_profilepic_url($each_result->user_id);
			$user_role = ($user_object->roles[0] == 'suscriptor' 
							OR $user_object->roles[0] == 'subscriber'
							OR $user_object->roles[0] == 'administrador'
							OR $user_object->roles[0] == 'administrator') ? 'usuario' : $user_object->roles[0];
			$role_prefix = $user_object->roles[0];
			if($role_prefix !== 'venue' AND $role_prefix !== 'artista')
				$role_prefix = 'suscriptor';
			$results_array['results'][] = array(
											'display_user' 		=> TRUE,
											'ID' 				=> $each_result->user_id,
											'user_login' 		=> get_clean_userlogin($each_result->user_id),
											'user_nicename' 	=> $user_object->user_nicename,
											'user_display_name' => $user_object->display_name,
											'user_role' 		=> $user_role,
											"is_".$user_role 	=> TRUE,
											'user_thumbnail' 	=> ( isset($user_thumb) AND $user_thumb !== '') ? $user_thumb : NULL,
											'is_following'		=> ($current_user) ? intval(checa_si_sigue_el_usuario($current_user->ID , $each_result->user_id)) : 'undefined',
											'user_followers'	=> checa_total_followers($each_result->user_id),
											'role_prefix'		=> $role_prefix
										);
		}elseif($each_result->tipo == 'post'){
			
			/* If result is an Event */
			$event 	= get_post($each_result->post_id);
			$event_thumbnail = museo_get_attachment_url( get_post_thumbnail_id($each_result->post_id), 'gallery_mobile' );
			$venue_object 	= get_user_by('id', $each_result->user_id);
			$venue_name 	= $venue_object->display_name;
			$venue_slug 	= $venue_object->user_login;
			$venue_thumb 	= museo_get_profilepic_url($each_result->user_id);

			$results_array['results'][] = array(
											'display_event' 	=> TRUE,
											'event_title' 		=> $event->post_title,
											'event_author'  	=> $event->post_author,
											'event_slug' 		=> $event->post_name,
											'event_content' 	=> $event->post_content,
											'event_thumbnail' 	=> !empty($event_thumbnail) ? $event_thumbnail[0] : NULL,
											'venue' 			=> get_the_author_meta( 'display_name', $venue_object->ID ),
											'venue_avatar' 		=> museo_get_profilepic_url($venue_object->ID),
											'venue_latlong'		=> get_user_meta($venue_object->ID, 'latlong', true),
											'date_start' 		=> fecha_inicio_evento($event->ID),
											'date_end' 			=> fecha_fin_evento($event->ID),
											'costo' 			=> get_post_meta($event->ID,'mg_costo',true),
											'address' 			=> get_post_meta($event->ID,'mg_evento_direccion', true),
											'scheduled' 		=> ( in_array( $each_result->post_id, museografo_eventos_agendados($current_user) ) ) ? true : false
										);
		}elseif($each_result->tipo == 'category'){
			$results_array['results'][] = array(
											'display_category' 	=> TRUE,
											'ID' 				=> $each_result->ID,
											'cat_title' 		=> $each_result->name,
											'cat_slug' 			=> $each_result->slug,
											'has_description' 	=> $each_result->has_description,
											'description' 		=> $each_result->description,
											'thumbnail' 		=> $each_result->thumbnail
										);
		}

	}
	wp_send_json_success($results_array);

}


function retrieve_user_followers($queried_login, $logged_name = NULL, $type = 'suscriptor'){
	if(!$logged_name){
		global $current_user;
	}else{
		$current_user = get_user_by('slug', $logged_name);
	}
	$queried = get_user_by('slug', $queried_login);
	$follower_ids = get_user_followers($queried->ID, $type);

	$type_label = ($type == 'suscriptor' 
						OR $type == 'subscriber'
						OR $type == 'administrador'
						OR $type == 'administrator') ? 'museografo' : $type;

	$user_list = array();
	foreach ($follower_ids as $each_id) {
		$temp_user = get_user_by('id', $each_id);
		$user_thumb = museo_get_profilepic_url($temp_user->ID);
		$user_role = ($temp_user->roles[0] == 'suscriptor' 
						OR $temp_user->roles[0] == 'subscriber'
						OR $temp_user->roles[0] == 'administrador'
						OR $temp_user->roles[0] == 'administrator') ? 'usuario' : $temp_user->roles[0];
		$role_prefix = $temp_user->roles[0];
		if($role_prefix !== 'venue' AND $role_prefix !== 'artista')
			$role_prefix = 'suscriptor';

		$user_list[] = array(
							'ID' 				=> $temp_user->ID,
							'user_login' 		=> $temp_user->user_login,
							'user_display_name' => $temp_user->display_name,
							'user_role' 		=> $user_role,
							'user_thumbnail' 	=> ( isset($user_thumb) AND $user_thumb !== '') ? $user_thumb : NULL,
							'is_following'		=> ($current_user) ? intval(checa_si_sigue_el_usuario($current_user->ID , $temp_user->ID)) : 'undefined',
							'user_followers'	=> checa_total_followers($temp_user->ID),
							'role_prefix'		=> $role_prefix,
							'is_'.$user_role    => TRUE
						);
	}
	$array_return = array(
							'label' => $type_label.'s',
							'queried_user_display_name' => $queried->display_name,
							'pool'	=> $user_list
						);
	wp_send_json_success($array_return);
}

function retrieve_user_followees($queried_login, $logged_name = NULL, $type = 'suscriptor'){
	if(!$logged_name){
		global $current_user;
	}else{
		$current_user = get_user_by('slug', $logged_name);
	}
	$queried = get_user_by('slug', $queried_login);
	$followees_ids = get_user_followees($queried->ID, $type);
	$type_label = ($type == 'suscriptor' 
						OR $type == 'subscriber'
						OR $type == 'administrador'
						OR $type == 'administrator') ? 'user' : $type;

	$user_list = array();
	foreach ($followees_ids as $each_id) {
		$temp_user = get_user_by('id', $each_id);

		$user_thumb = museo_get_profilepic_url($temp_user->ID);
		$user_role = ($temp_user->roles[0] == 'suscriptor' 
						OR $temp_user->roles[0] == 'subscriber'
						OR $temp_user->roles[0] == 'administrador'
						OR $temp_user->roles[0] == 'administrator') ? 'user' : $temp_user->roles[0];
		$role_prefix = $temp_user->roles[0];
		if($role_prefix !== 'venue' AND $role_prefix !== 'artista')
			$role_prefix = 'suscriptor';
		$user_list[] = array(
							'ID' 				=> $temp_user->ID,
							'user_login' 		=> $temp_user->user_login,
							'user_display_name' => $temp_user->display_name,
							'user_role' 		=> $user_role,
							'user_thumbnail' 	=> ( isset($user_thumb) AND $user_thumb !== '') ? $user_thumb : NULL,
							'is_following'		=> ($current_user) ? intval(checa_si_sigue_el_usuario($current_user->ID , $temp_user->ID)) : 'undefined',
							'user_followers'	=> checa_total_followers($temp_user->ID),
							'role_prefix'		=> $role_prefix,
							'is_'.$user_role    => TRUE
						);
	}
	$array_return = array(
							'label' => ucfirst($type_label).'s',
							'queried_user_display_name' => $queried->display_name,
							'pool'	=> $user_list
						);
	wp_send_json_success($array_return);
}

/*
 * Update user password
 * @param String $user_login The login name of the user
 * @param String $new_password The new password 
 * @return Bool $success
 * TO DO: Encode (client-side) and decode (server-side) password for security
 */
function update_user_password($user_login, $new_password){
	$user = get_user_by('slug', $user_login);
	if(wp_set_password($new_password, $user->ID));
		wp_send_json_success();
	wp_send_json_error();
	exit;
}

/*  
 * Update the categories a user is indexed in
 *
 */
function update_index_categories($logged_name, $values){
	$user = get_user_by('slug', $logged_name);
	$user_id = $user->ID;
	
	if(update_user_meta( $user_id, 'venue_categories', $values['categories'] ))
		return TRUE;
	return FALSE;
}

/**
 * Create new event from artist profile
 * @param String $logged The user that's creating the post
 * @param Array $event_data (via $_POST)
 */
function museo_create_new_event($logged, $event_data){
	
	$author = get_user_by('login', $logged);
	$revision = (!empty($author->roles) AND  $author->roles[0] == 'artista') ? "draft" : "publish" ;
	
	$post = array(
	  'post_title'     => $event_data['event_title'],
	  'post_content'   => $event_data['event_description'],
	  'post_status'    => $revision,
	  'post_type'      => 'eventos',
	  'post_author'    => $author->ID,
	);  
	
	$post_id = wp_insert_post($post);
	
	if(!is_wp_error($post_id)){
		wp_set_object_terms($post_id, array_values(array_map('intval', explode( ',' ,$event_data['cat_array']))), "category", FALSE);
		if(isset($event_data['event_date_start']))	update_post_meta($post_id, 'mg_evento_fecha_inicio', $event_data['event_date_start']);
		if(isset($event_data['event_hour_start'])) 	update_post_meta($post_id, 'mg_evento_hora_inicio', $event_data['event_hour_start']);
		if(isset($event_data['event_date_end'])) 	update_post_meta($post_id, 'mg_evento_fecha_fin', $event_data['event_date_end']);
		if(isset($event_data['event_hour_end'])) 	update_post_meta($post_id, 'mg_evento_hora_fin', $event_data['event_hour_end']);
		if(isset($event_data['event_venue'])) 		update_post_meta($post_id, 'mg_venue_id', $event_data['event_venue']);
		if(isset($event_data['other_venue'])) 		update_post_meta($post_id, 'mg_other_venue', $event_data['other_venue']);
		if(isset($event_data['event_price'])) 		update_post_meta($post_id, 'mg_costo', $event_data['event_price']);
		if(isset($event_data['mg_artist_ids']) AND intval($event_data['mg_artist_ids'])) 	update_post_meta($post_id, 'mg_artist_ids', $event_data['mg_artist_ids']);
		if(isset($event_data['event_address'])) 	update_post_meta($post_id, 'mg_evento_direccion', $event_data['event_address']);
		
		if  (
			 isset( $_POST['event_image_upload_nonce']) 
			 && wp_verify_nonce( $_POST['event_image_upload_nonce'], 'event_image_upload' )
			 && !empty( $_FILES )
			) 
		{
			require_once( ABSPATH . 'wp-admin/includes/image.php' );
			require_once( ABSPATH . 'wp-admin/includes/file.php' );
			require_once( ABSPATH . 'wp-admin/includes/media.php' );
			// Let WordPress handle the upload
			$attachment_id = media_handle_upload('event_image_upload', $user_id );
			if ( is_wp_error( $attachment_id ) ) {
				wp_send_json_error();
			}else {
				add_post_meta($post_id, '_thumbnail_id', $attachment_id);
				$metadata = wp_get_attachment_metadata( $attachment_id );
				$new_imagemeta = array(
									"aperture"=> 0,
									"credit"=> "",
									"camera"=> "",
									"caption"=> "",
									"created_timestamp"=> 0,
									"copyright"=> "",
									"focal_length"=> 0,
									"iso"=> 0,
									"shutter_speed"=> 0,
									"title"=> $event_data['event_title'],
									"orientation"=> 0
								);
				$metadata['image_meta'] = $new_imagemeta;
				wp_update_attachment_metadata($attachment_id, $metadata);
				$message = "El usuario {$author->user_login} ha creado un nuevo evento: \"{$event_data['event_title']}\" que necesita ser revisado y aprobado";
				$headers[] = 'From: Museógrafo Admin<noreply@museografo.com>';
				if($revision == "draft")
					wp_mail( "ivan@marat.mx", "Nuevo evento esperando revisión", $message, $headers );
				wp_send_json_success();
			}

		} else {
			wp_send_json_error();
		}
	}
	wp_send_json_error();
}

/**  
 * Create new project from artist profile
 * @param String $logged The user that's creating the post
 * @param Array $project_data (via $_POST)
 */
function museo_create_new_project($logged, $project_data){
	$author = get_user_by('login', $logged);
	
	$post = array(
	  'post_title'     => $project_data['project_title'],
	  'post_content'   => $project_data['project_description'],
	  'post_status'    => "publish",
	  'post_type'      => 'proyectos-especiales',
	  'post_author'    => $author->ID,
	);  
	
	$post_id = wp_insert_post($post);
	if(!is_wp_error($post_id)){
		wp_set_object_terms($post_id, array_values(array_map('intval', explode( ',' ,$project_data['cat_array']))), "category", FALSE);
		if(isset($project_data['project_date_started']))	update_post_meta($post_id, 'project_date_started', $project_data['project_date_started']);
		if(isset($project_data['project_date_finished'])) update_post_meta($post_id, 'project_date_finished', $project_data['project_date_finished']);
		if(isset($project_data['technique'])) update_post_meta($post_id, 'project_technique', $project_data['technique']);
		if(isset($project_data['collection'])) update_post_meta($post_id, 'project_collection', $project_data['collection']);
		if(isset($project_data['project_links'])) update_post_meta($post_id, 'project_links', $project_data['project_links']);
		if(isset($project_data['url_video'])) update_post_meta($post_id, 'project_video_url', $project_data['url_video']);
		if(isset($project_data['type_of_project'])) update_post_meta($post_id, 'project_type', $project_data['type_of_project']);
		
		if  (
			 isset( $_POST['artist_image_upload_nonce']) 
			 && wp_verify_nonce( $_POST['artist_image_upload_nonce'], 'artist_image_upload' )
			 && !empty( $_FILES )
			) 
		{
			require_once( ABSPATH . 'wp-admin/includes/image.php' );
			require_once( ABSPATH . 'wp-admin/includes/file.php' );
			require_once( ABSPATH . 'wp-admin/includes/media.php' );
			// Let WordPress handle the upload
			$attachment_id = media_handle_upload('artist_image_upload', $user_id );
			if ( is_wp_error( $attachment_id ) ) {
				wp_send_json_error();
			}else {
				add_post_meta($post_id, '_thumbnail_id', $attachment_id);
				$metadata = wp_get_attachment_metadata( $attachment_id );
				$new_imagemeta = array(
									"aperture"=> 0,
									"credit"=> "",
									"camera"=> "",
									"caption"=> "",
									"created_timestamp"=> 0,
									"copyright"=> "",
									"focal_length"=> 0,
									"iso"=> 0,
									"shutter_speed"=> 0,
									"title"=> $project_data['event_title'],
									"orientation"=> 0
								);
				$metadata['image_meta'] = $new_imagemeta;
				wp_update_attachment_metadata($attachment_id, $metadata);
		
				wp_send_json_success();
			}

		} else {
			wp_send_json_error();
		}
	}
	wp_send_json_error();
}

/* Search usernames to populate autocomplete */
function search_usernames($logged, $s){
	$user = get_user_by('slug', $logged);
	$values = get_user_followees($user->ID);
	
	$stringPlaceholders = array_fill(0, count($values), '%d');
	$in_here = implode(', ', $stringPlaceholders);

	$values[] = "%".$s."%";
	$values[] = "%".$s."%";

	global $wpdb;
	$query = "SELECT user_login
			  FROM wp_users
				WHERE ID IN ($in_here)
				AND ( user_login LIKE %s
					OR display_name LIKE %s)
				LIMIT 5;
			";
			
	$results = $wpdb->get_col( $wpdb->prepare($query , 
													$values));

	wp_send_json_success($results);
}

/*
 * Get content for the login screen
 * @return Array
 */
function get_login_content(){
	$args = array(
					'post_type' => 'eventos',
					'posts_per_page' => 1,
					'orderby' => 'rand'
				);
	$random_event = get_posts($args);
	$random_event = $random_event[0];
	$thumb = museo_get_attachment_url(get_post_thumbnail_id($random_event->ID), 'gallery_mobile');
	wp_send_json_success( array(
					'login_image' => $thumb[0]
				));
}

/*
 * Get extension from MIME type
 * @param String $mimetype
 */
function get_extension_fromMIMEtype($mimetype){
	switch ($mimetype) {
		case 'image/jpeg':
			$extension = 'jpg';
				break;
		case 'image/png':
			$extension = 'png';
				break;
		case 'image/gif':
			$extension = 'gif';
				break;
		default:
			$extension = 'jpg';
				break;
	}
	return $extension;
}

/*
 * Save event uploads
 * @param $user The user uploading the object
 * @param $ajax Wheather to use as API call (FALSE) or an ajax call
 * @return JSON success plus the image url
 */
function save_event_upload($user_login, $image_temp, $image_name, $event_id) {
	if(!$user_login){
		global $current_user;
	}else{
		$current_user = get_user_by('slug', $user_login);
	}
	global $wpdb;

	$finfo = new finfo(FILEINFO_MIME_TYPE);
	    if (false === $ext = array_search(
	        $finfo->file($image_temp),
	        array(
	            'jpg' => 'image/jpeg',
	            'png' => 'image/png',
	            'gif' => 'image/gif',
	        ),
	        true
	    )) {
	        throw new RuntimeException('Invalid file format.');
	   		wp_send_json_error('Invalid file format.');
	    }
	
	$wp_upload_dir = wp_upload_dir();
	$extension = get_extension_fromMIMEtype($_FILES['file']['type']);
	$img = $wp_upload_dir['path']."/".md5($image_name).".".$extension;
	if(!move_uploaded_file($image_temp, $img) )
	{
		throw new RuntimeException('Failed to move uploaded file.');
		exit;
	}	

	$attachment = array(
		'post_status'    => 'private',
		'post_mime_type' => "image/{$extension}",
		'post_type'      => 'attachment',
		'post_parent'    => $event_id,
		'post_title'     => $image_name,

	);

	$dir = substr($wp_upload_dir['subdir'], 1);
	
	$attach_id = wp_insert_attachment( $attachment, $img);
	if($attach_id){
		// you must first include the image.php file for the function wp_generate_attachment_metadata() to work
		require_once(ABSPATH . 'wp-admin/includes/image.php');
		$attach_data = wp_generate_attachment_metadata( $attach_id, $img );
		$_POST['attach_id'] = $attach_id;
		wp_update_attachment_metadata( $attach_id, $attach_data );
		set_post_thumbnail( '', $attach_id );
		registra_actividad($event_id, $current_user->ID, 'media', $attach_id);
		$img_url2 = museo_get_attachment_url($attach_id, 'agenda-feed');

	}
	return $img_url2[0];
	exit;
}

/*
 * Save profile picture from API
 * @param $user_id via $_POST
 * @param $user_name via $_POST
 *
 */
function save_profile_picture_upload($user_login, $image_temp, $image_name) {
	if(!$user_login){
		global $current_user;
	}else{
		$current_user = get_user_by('slug', $user_login);
	}
	global $wpdb;

	$finfo = new finfo(FILEINFO_MIME_TYPE);
	    if (false === $ext = array_search(
	        $finfo->file($image_temp),
	        array(
	            'jpg' => 'image/jpeg',
	            'png' => 'image/png',
	            'gif' => 'image/gif',
	        ),
	        true
	    )) {
	        throw new RuntimeException('Invalid file format.');
	   		wp_send_json_error('Invalid file format.');
	    }
	
	$wp_upload_dir = wp_upload_dir();
	$extension = get_extension_fromMIMEtype($_FILES['file']['type']);
	$img = $wp_upload_dir['path']."/".md5($image_name).".".$extension;
	if(!move_uploaded_file($image_temp, $img) )
	{
		throw new RuntimeException('Failed to move uploaded file.');
		exit;
	}	

	$attachment = array(
		'post_status'    => 'inherit',
		'post_mime_type' => "image/{$extension}",
		'post_type'      => 'attachment',
		'post_title'     => $image_name,

	);
	$attach_id = wp_insert_attachment( $attachment, $img);

	if($attach_id){
		// you must first include the image.php file for the function wp_generate_attachment_metadata() to work
		require_once(ABSPATH . 'wp-admin/includes/image.php');
		$attach_data = wp_generate_attachment_metadata( $attach_id, $img );
		$_POST['attach_id'] = $attach_id;
		wp_update_attachment_metadata( $attach_id, $attach_data );
		set_post_thumbnail( '', $attach_id );
		$dir = substr($wp_upload_dir['subdir'], 1);
		 $img_url2 = museo_get_attachment_url($attach_id, 'thumbnail');
		 $pat_img    = pathinfo($img_url2[0]);
		 $img2 = $dir .'/'. $pat_img['basename'];
		 save_image_user($img2, $current_user->ID);
	}
	echo $img_url2[0];
	exit;
}

/*
 * Get asset by name or identifier
 * @param String $asset_name
 * @param json encoded array of arguments
 * @see assets documentation
 *
 * @return json encoded success plus data pool if exists
 */
function jf_get_asset_by_name($asset_name = NULL, $args = array()){
	$args = json_decode(stripslashes($args), JSON_FORCE_OBJECT);
	
	if(!$asset_name) 
		return json_encode(array('success' => FALSE, 'error' =>"No asset name provideed"));
	
	if($asset_name == "provinces"){
		if(!empty($args) AND isset($args['country'])){
			$provinces = get_cities_bycountry($args['country']);
			$provinces_slug = array_map("clean_term_name", $provinces);
			$result_array = array(
								'success' => TRUE, 
								'pool' => array()
							);	
			
			foreach ($provinces as $index => $each_city) {
				$result_array['pool'][] = array("slug" => $provinces_slug[$index], "name" =>$each_city);
			}
			return $result_array;
		}
	return json_encode(array('success' => FALSE, 'error' =>"Not enough arguments"));
	}

	/* If everything fails this guy sends not found */
	return json_encode(array('success' => FALSE, 'error' =>"404 No asset found by that name or identifier"));
}

/*
 * Override for the  museo_get_attachment_url method
 * This one always gets an absolute path
 *
 * @param Int $attachment_id
 * @param String $size
 * @param Boolean $icon
 * @return Array $image or FALSE if an error ocurred
 */
function museo_get_attachment_url($attachment_id, $size='thumbnail', $icon = false) {
 
    // get a thumbnail or intermediate image if there is one
    if ( $image = image_downsize($attachment_id, $size) ){
    	$broken = explode('/', $image[0]);
    	$is_museo_dev = (array_search("museografo.dev", $broken) !== FALSE) ? TRUE : FALSE;
    	$relative_start_index = array_search("wp-content", $broken);
    	$broken = array_splice($broken, $relative_start_index, count($broken)-1);
    	if( (array_search("museografo.com", $broken) == FALSE AND !$is_museo_dev) 
    		OR (array_search("museografo.dev", $broken) == FALSE AND $is_museo_dev) ){
    		$joint = implode("/", $broken);
    		$image[0] = site_url( $joint);
    		return $image;
    	}
        return $image;
    }
 
    $src = false;
    if ( $icon && $src = wp_mime_type_icon($attachment_id) ) {
    
        /** This filter is documented in wp-includes/post.php */
        $icon_dir = apply_filters( 'icon_dir', ABSPATH . WPINC . '/images/media' );
        $src_file = $icon_dir . '/' . wp_basename($src);
        @list($width, $height) = getimagesize($src_file);
    }
    if ( $src && $width && $height )
        return array( $src, $width, $height );
    return false;
}


/**
 * Get artist bio info from page
 * @return Array
 */
function jf_get_semblanza(){
	$semblanza_page = get_page_by_path('semblanza');
	$thumb =  wp_get_attachment_image_src(get_post_thumbnail_id($semblanza_page->ID), 'medium');
	return  array(
					"artist_name" 	=> $semblanza_page->post_title,
					"artist_origin" => "Uruapan, Michoacán 1963",
					"artist_bio" 	=> wpautop($semblanza_page->post_content),
					"artist_bio_photo" 	=> $thumb[0]
				);

}

/**
 * Get artist bio info from page
 * @param Int $expo_id
 * @return Array
 */
function get_expo($expo_id){
	$post_object = get_post($expo_id);
	$thumb = wp_get_attachment_image_src(get_post_thumbnail_id($post_object->ID), 'medium');
	$post_object->thumb_url = $thumb[0];
	$post_object->post_content = wpautop($post_object->post_content);
	$meta_datestart = get_post_meta($expo_id, "date_start", TRUE);
	$post_object->event_date_start = ($meta_datestart !== '') ?  date("d.m.Y", strtotime($meta_datestart)): NULL;
	$meta_dateend = get_post_meta($expo_id, "date_end", TRUE);
	$post_object->event_date_end= ($meta_dateend !== '') ?  date("d.m.Y", strtotime($meta_dateend)): NULL;
	$meta_location = get_post_meta($expo_id, "location", TRUE);
	$post_object->event_location = ($meta_location !== '') ?  $meta_location: NULL;
	$meta_latlong = get_post_meta($expo_id, "latlong", TRUE);
	$post_object->event_latlong	 = ($meta_latlong !== '') ?  $meta_latlong: NULL;

	return json_encode($post_object);
}

/**
 * Get content for hashtag page
 * @param Int $offset
 * @return Array
 */
function get_marin_hashtag($offset = NULL){
	$hashtag_content = array();
	return json_encode($hashtag_content);
}