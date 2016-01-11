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
function get_expos_feed($offset = NULL, $limit = NULL, $lang = "es"){

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
function jf_get_semblanza($lang = 'es'){
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
function get_expo($expo_id, $lang = "es"){
	$post_object = get_post($expo_id);
	$thumb = wp_get_attachment_image_src(get_post_thumbnail_id($post_object->ID), 'medium');
	$post_object->thumb_url = $thumb[0];
	$post_object->post_title = qtranxf_use($lang, $post_object->post_title);
	$post_object->post_content = wpautop( qtranxf_use($lang, $post_object->post_content) );
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

