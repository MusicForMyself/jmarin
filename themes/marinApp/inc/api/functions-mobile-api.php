<?php

require_once(get_template_directory().'/vendor/src/Google/autoload.php');

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

/*
 * Get content for the login screen
 * @return Array
 */
function get_login_content(){
	$homepage = get_page_by_path('home');
	$media = get_attached_media('image', $homepage->ID);
	$return_array = array();
	foreach ($media as $each_file) {
		$image = wp_get_attachment_image_src( $each_file->ID, "full");
		$return_array['gallery'][] =  $image[0];
	}
	echo json_encode($return_array);
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
function save_event_upload($image_temp, $image_name, $comment) {
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

	$args = 	array(
						"post_type" => "user-upload",
						"post_title" => $comment,
						"post_content" => "",
						"post_status" => "publish",
					);
	$inserted = wp_insert_post($args);

	$attachment = array(
		'post_status'    => 'private',
		'post_mime_type' => "image/{$extension}",
		'post_type'      => 'attachment',
		'post_parent'    => $inserted,
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
		set_post_thumbnail( $inserted, $attach_id );
		// registra_actividad($event_id, $current_user->ID, 'media', $attach_id);
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


/**
 * Get artist bio info from page
 * @return Array
 */
function jf_get_semblanza($lang){
	$semblanza_page = get_page_by_path('semblanza');
	$thumb =  wp_get_attachment_image_src(get_post_thumbnail_id($semblanza_page->ID), 'medium');
	return  array(
					"artist_name" 	=> $semblanza_page->post_title,
					"artist_origin" => "Uruapan, Michoacán 1963",
					"artist_bio" 	=> wpautop(qtranxf_use($lang, $semblanza_page->post_content)),
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
	$meta_moreinfo = get_post_meta($expo_id, "moreinfo", TRUE);
	$post_object->event_moreinfo	 = ($meta_moreinfo !== '') ?  $meta_moreinfo: NULL;

	/*** Get gallery contents ***/
	$args = array(
					'post_type'   => 'attachment',
					'numberposts' => -1,
					'post_status' => 'any',
					'post_parent' => $expo_id,
					'exclude'     => get_post_thumbnail_id(),
				);

	$attachments = get_posts( $args );
	$pool  = array();
	if ( $attachments ) {
		foreach ( $attachments as $index => $attachment ) {
			$thumbnail = wp_get_attachment_image_src( $attachment->ID, 'medium_cut' );
			$pool[] = array(	
										"title" 		=>  apply_filters( 'the_title', $attachment->post_title ),
										"description" 	=>  apply_filters( 'the_title', $attachment->post_excerpt ),
										"url" 			=>  $thumbnail[0],
									);
		}
		$gallery = array();
		foreach ($pool as $index => $each_pool_element) {
			
			$gallery['pool'][] = $each_pool_element;
		}
		$post_object->gallery  = $gallery;
	}
	return json_encode($post_object, true);
}

/**
 * Get content for hashtag page
 * @param Int $offset
 * @return Array
 */
function get_marin_hashtag($offset = NULL){
	$hashtag_final = array();
	$args = array(
					"post_type" 	 => "user-upload",
					"posts_per_page" => -1,
					"orderby" 		 => "date",
					"order" 		 => "DESC",
				 );
	$hashtag_content = get_posts( $args );
	foreach ($hashtag_content as $key => $each_image) {
		$hashtag_url = wp_get_attachment_image_src( get_post_thumbnail_id($each_image->ID), 'medium_cut' );
		$hashtag_final[$key]['comment'] = $each_image->post_title;
		$hashtag_final[$key]['img_url'] = $hashtag_url[0];
	}
	return json_encode($hashtag_final);
}

/**
 * Sync event to Google Calendar
 * @param Int $event_id
 * @return Array
 */
function sync_googleCal( $event_id = NULL ){

	$client = new Google_Client();
	$client->setAuthConfigFile(THEMEPATH.'/vendor/src/client_secret.json');

	$client_email 	= 'account-1@jorge-marin-1147.iam.gserviceaccount.com';
	$private_key 	= file_get_contents(THEMEPATH.'/vendor/src/client_secret.json');
	$scopes 		= array('https://www.googleapis.com/auth/calendar');
	
	$credentials = new Google_Auth_AssertionCredentials(
	    $client_email,
	    $scopes,
	    $private_key
	);
	$client->setApplicationName("Jorge_Marin_App");
	$client->setAssertionCredentials($credentials);
	$client->setClientId('116535447197535022331');

	if( $client->getAuth()->isAccessTokenExpired() ) {
		$client->getAuth()->refreshTokenWithAssertion();
	}

	$cal = new Google_Service_Calendar($client);
	try {  
		$event = new Google_Service_Calendar_Event();  
		$event->setSummary('Halloween');
		$event->setLocation('The Neighbourhood');
		$start = new Google_Service_Calendar_EventDateTime();
		$start->setDateTime('2015-12-10T10:00:00.000-05:00');
		$event->setStart($start);
		$end = new Google_Service_Calendar_EventDateTime();
		$end->setDateTime('2015-12-10T10:25:00.000-05:00');
		$event->setEnd($end);

		$createdEvent = $cal->events->insert('primary', $event);   
	 	echo $createdEvent->getId()."\n\n"; 
	} catch (Exception $ex) {
	  
	  die($ex->getMessage());
	}

}

/**
 * Connect to Google Server to Server
 * @param Int $event_id
 * @return Array
 */
function connect_googleStoS(){

	$client = new Google_Client();
	$client_email = 'account-1@jorge-marin-1147.iam.gserviceaccount.com';
	$private_key = file_get_contents(THEMEPATH.'/vendor/src/APIServerKey.p12');
	$scopes = array('https://www.googleapis.com/auth/calendar');
	
	$credentials = new Google_Auth_AssertionCredentials(
	    $client_email,
	    $scopes,
	    $private_key
	);
	$client->setApplicationName("Jorge_Marin_App");
	$client->setAssertionCredentials($credentials);
	$client->setClientId('116535447197535022331');

	if( $client->getAuth()->isAccessTokenExpired() ) {
		$client->getAuth()->refreshTokenWithAssertion();
	}

	$cal = new Google_Service_Calendar($client);
	try {  
		$event = new Google_Service_Calendar_Event();  
		$event->setSummary('Halloween');
		$event->setLocation('The Neighbourhood');
		$start = new Google_Service_Calendar_EventDateTime();
		$start->setDateTime('2015-12-10T10:00:00.000-05:00');
		$event->setStart($start);
		$end = new Google_Service_Calendar_EventDateTime();
		$end->setDateTime('2015-12-10T10:25:00.000-05:00');
		$event->setEnd($end);

		$createdEvent = $cal->events->insert('primary', $event);
	 	echo $createdEvent->getId()."\n\n"; 
	} catch (Exception $ex) {
	  
	  die( $ex->getMessage() );

	}

}

