<?php
require_once('User.class.php');
class Router{

	function __construct($token = 'NOTOKEN', $attrs = array()){
		if($token == 'NOTOKEN') return FALSE;
		add_action('slim_mapping', array( &$this,'api_mapping' ));

		/* Set custom expiration if provided, default if not */
		(!empty($attrs) && $attrs['expires'] !== "") ? $this->set_expiration($attrs['expires']) : $this->set_expiration();
		$this->attrs =  array(
							'request_token' => $token,
							'method'		=> '$method',
							'data'			=> '$data'
						);
	}

	function api_mapping($slim){

		$context = $this;
		

		/*** Those damn robots ***/
		$slim->get('/rest/v1/', function() {
			wp_send_json_error('These are not the droids you are looking for, please get a token first');
			exit();
		});

		$slim->get('/rest/v1/robots/', function() {
			wp_send_json_success('These are not the droids you are looking for, please get a token first. If you\'re testing API connection, everything seems to be going smooth ;)');
			exit();
		});

		/*
		 *   _____      _              
		 *  /__   \___ | | _____ _ __  
		 *    / /\/ _ \| |/ / _ \ '_ \ 
		 *   / / | (_) |   <  __/ | | |
		 *   \/   \___/|_|\_\___|_| |_|
		 *                             
		 */

			/* 
			 * Get a passive token
			 * Generates a token, stores it into the database and returns the token as a response
			 * Implement so that tokens are generated only once, then validated and used until valid
			 * @return 	response.success Bool true is request was executed correctly
			 * @return 	response.request_token Generated passive token
			 * @see 	Validate token
			 */
			$slim->get('/rest/v1/auth/getToken/', function () use ($context){
				
			  	if (method_exists("Router", 'generateToken')){
			  		$new_token = $context->generateToken(FALSE);
			  		wp_send_json_success(array('request_token' => $new_token));
			  	}
			  	wp_send_json_error('Couldn\'t execute method');
			  	exit;
			});
			
			/* Check token for validity */
			$slim->post('/rest/v1/auth/user/checkToken/', function () {
				
				if(!isset($_POST['request_token']) OR !isset($_POST['user_id'])) return wp_send_json_error(array("error" => "Please provide a user_id and a request token, or refer to the documentation for further support"));
			  	$device_info = (isset($_POST['device_info'])) ? $_POST['device_info'] : NULL;
				
			  	$response = $this->check_token_valid($_POST['user_id'], $_POST['request_token'], $device_info);
				if($response) wp_send_json_success($response);
				wp_send_json_error();
			});

			/*  
			 * Validate Token
			 */
			$slim->post('/rest/v1/user/validateToken/', function () {

				$token 		= (isset($_POST['token'])) 		? $_POST['token'] 	: NULL;
				$user_id 	= (isset($_POST['user_id'])) 	? $_POST['user_id'] : NULL;
				$validate_id 	= (isset($_POST['validate_id'])) ? $_POST['validate_id'] : NULL;
				$device_info 	= (isset($_POST['device_info'])) ? $_POST['device_info'] : NULL;
				
				if(!$token OR !$user_id) wp_send_json_error('Error: Not enough data provided, please check documentation');

				/* Validate token and return it as a response */
				if(!$this->check_token_valid($user_id, $token, $device_info)){
					$response = $this->update_tokenStatus($token, $user_id, 1);
					if($validate_id) $this->settokenUser($token, $validate_id, $device_info);
					if($response) wp_send_json_success(array('token' => $token, 'status' => 'valid'));
					/* Error: Something went wrong */
					wp_send_json_error('Can\'t validate token, please check your implementation. Do not send tokens directly to this endpoint');
					exit;
				}
				/* Error: Something went wrong */
				wp_send_json_error('Can\'t validate token or already valid. Please check your implementation or execute auth/user/checkToken/ endpoint to check your current token status. Do not send tokens directly to this endpoint');
				exit;
			});
				
			/*  
			 * ¡VARIATION! 
			 * Validate Token from another endpoint
			 * Differs from the '/user/validateToken/' endpoint in the way it sends a response.
			 * Instead of sending a JSON response, this one just sends a Boolean response to handle inside the other endpoint
			 */
			$slim->post('/rest/v1/auth/validateToken/', function () {

				$token 		= (isset($_POST['token'])) 		? $_POST['token'] 	: NULL;
				$user_id 	= (isset($_POST['user_id'])) 	? $_POST['user_id'] : NULL;
				$validate_id 	= (isset($_POST['validate_id'])) ? $_POST['validate_id'] : NULL;
				
				if(!$token OR !$user_id) return FALSE;

				/* Validate token and return it as a response */
				if(!$this->check_token_valid($user_id, $token)){
					$response = $this->update_tokenStatus($token, $user_id, 1);
					if($validate_id) $this->settokenUser($token, $validate_id);
					
					if($response) return TRUE;
					return FALSE;
					exit;
				}
				/* Error: Something went wrong */
				return
				exit;
			});


			/* 
			 * Create user new 
			 * @param 	$attr via $_POST {username: 'username',email: 'email',password: 'password',}
			 * @return 	response.success Bool Executed
			 * @return 	response.data User data
			 * @see 	User.class.php
			 */
			$slim->post('/rest/v1/auth/user/', function () {

				extract($_POST);
				if (!isset($username))wp_send_json_error('Please provide a username');
				
				/* Create user object */
				$User 	= new User();

				$created = $User->create_if__notExists($username, $email, $attrs, FALSE);
				if($created){
					if( isset($attrs['login_redirect']) 
						 AND (!$attrs['login_redirect'] OR $attrs['login_redirect'] == FALSE)
					  ) {
					  	mobile_pseudo_login();
						wp_send_json_success($created);
					}
						
					/* Must provide password to use this method */
					_mobile_pseudo_login($username, $attrs['password'], $attrs['request_token']);
					exit;
				}
			  	wp_send_json_error('Couldn\'t create user');
			  	exit;
			});

			/* 
			 * Check if user exists
			 * 
			 */
			$slim->get('/rest/v1/user/exists/:username', function ($username) {
				$User = new User();
				/* Create user */
				if($User->_username_exists($username)){
					$json_response = array('user_id' => $User->_username_exists($username), 'username' => $username);
					wp_send_json_success($json_response);
				}
				wp_send_json_error();
				exit;
			});



		/*
		 *   _             _       
		 *  | | ___   __ _(_)_ __  
		 *  | |/ _ \ / _` | | '_ \ 
		 *  | | (_) | (_| | | | | |
		 *  |_|\___/ \__, |_|_| |_|
		 *            |___/         
		 */
		
			/*** Get random event image for login page ***/
			$slim->get('/rest/v1/content/login/', function() {
				return get_login_content();
				exit;
			});
			
			/*** User Login ***/
			$slim->post('/rest/v1/auth/login/', function() {
				return mobile_pseudo_login();
				exit;
			});

			/* User Logout from API and invalidate token in database
			 * @param String $logged The username
			 * @param String $request_token (via $_POST) to invalidate in database
			 * @return JSON success
			 */
			$slim->post('/rest/v1/auth/:logged/logout/', function($logged) {
				return mobile_pseudo_logout($logged);
				exit;
			});

			/*
			 * DEPRECATED!!!!!
			 * DEPRECATED!!!!!
			 * DEPRECATED!!!!!
			 * CHECK USER LOGIN IMPORTANT USE BEFORE EVERY CALL TO THE API
			 * DEPRECATED!!!!! 
			 * DEPRECATED!!!!! 
			 * DEPRECATED!!!!! (use checkToken endpoint instead)
			 */
			// $slim->get('/rest/v1/login/:user_id',function($user_id) {
			// 	wp_send_json_error('deprecated');
			// 	// wp_send_json_success(mobile_login_check($user_id, $user_token));
			// 	exit;
			// });

		/*
		 *                     __ _ _           
		 *    _ __  _ __ ___  / _(_) | ___  ___ 
		 *   | '_ \| '__/ _ \| |_| | |/ _ \/ __|
		 *   | |_) | | | (_) |  _| | |  __/\__ \
		 *   | .__/|_|  \___/|_| |_|_|\___||___/
		 *   |_|                                
		 */
		
			/* Get user profile
			 * @param String $logged User requesting the profile
			 * @param String $queried_login User whose profile is requested
			 * @return JSON formatted user profile info
			 */
			$slim->get('/rest/v1/:logged/user/:queried_login/', function ($logged, $queried_login){
				echo get_user_profile($queried_login, $logged);
				exit;
			});

			/* Update user profile
			 * @param String $ulogin User whose profile is being updated
			 * @return JSON formatted user profile info
			 * TO DO: Use PUT method instead with the same endpoint
			 * DEPRECATED
			 * DEPRECATED
			 * DEPRECATED
			 * DEPRECATED, USE PUT METHOD INSTEAD
			 * DEPRECATED
			 * DEPRECATED
			 */
			$slim->post('/rest/v1/user/update/:ulogin', function($ulogin){
				wp_send_json_error('deprecated');
				// echo update_user_profile($user);
				exit;
			});

			/* Get user basic info
			 * @return JSON formatted user basic info
			 * TO DO: Check data sent by this endpoint and activate it
			 */
			$slim->get('/rest/v1/auth/user/me', function () {
			  	return (method_exists($this, 'getInfo')) ? wp_send_json_success(array("request_token" => $this->getInfo())) : wp_send_json_error('Couldn\'t execute method') ;
			});

			/*
			 * Get attachments uploaded by user
			 * @param String $user_login
			 * @param Int $limit
			 * @param String optional $Size
			 */
			$slim->get('/rest/v1/user/:user_login/gallery/:limit/(:size)/', function($user_login, $limit, $size = 'gallery_mobile') {	
				echo get_user_gallery($user_login, $limit, $size);
				exit;
			});
			
			/*
			 * Get projects uploaded by artist
			 * @param String $user_login
			 * @param Int $limit
			 * @param String optional $Size
			 */
			$slim->get('/rest/v1/user/:artist_login/projects/:limit/(:size)/', function($artist_login, $limit = 5, $size = 'gallery_mobile') {	
				echo get_artist_projects($artist_login, $limit, $size);
				exit;
			});

			/* Get user followers
			 * @param String $logged The log in name of the logged user making the call
			 * @param String $queried_login The log in name of the queried user
			 * @return JSON object containing the user follower list
			 * @see retrieve_user_followers
			 */
			$slim->get('/rest/v1/:logged/user/:queried_login/followers/:type/', function ($logged, $queried_login, $type) {
			  	return retrieve_user_followers($queried_login, $logged, $type);
			});

			/* Get user followees
			 * @param String $logged The log in name of the logged user making the call
			 * @param String $queried_login The log in name of the queried user
			 * @return JSON object containing the user followees list
			 * @see retrieve_user_followees
			 */
			$slim->get('/rest/v1/:logged/user/:queried_login/followees/:type/', function ($logged, $queried_login, $type) {
			  	return retrieve_user_followees($queried_login, $logged, $type);
			});

			/* Update user profile (PUT)
			 * @param String $ulogin User whose profile is being updated
			 * @see update_user_profile
			 * @see museografo_completar_perfil
			 * @return JSON formatted user profile info
			 */
			$slim->put('/rest/v1/user/:ulogin/', function($ulogin){
				$app = \Slim\Slim::getInstance();
				$app->add(new \Slim\Middleware\ContentTypes());
				
				$var_array = array();
				parse_str($app->request->getBody(), $var_array);
				$var_array['return'] = 	FALSE;		
				
				return update_user_profile($ulogin, $var_array);
				exit;
			});

			/* Update user password (PUT)
			 * @param String $ulogin User whose password is being updated
			 * @see PUT endpoint "/rest/v1/user/:ulogin"
			 * @return JSON formatted success response
			 * TO DO: Create a POST endpoint to set password first time.
			 */
			$slim->put('/rest/v1/user/:ulogin/password/', function($ulogin){
				$app = \Slim\Slim::getInstance();
				$app->add(new \Slim\Middleware\ContentTypes());
				$var_array = array();
				parse_str($app->request->getBody(), $var_array);
				
				$new_password = $var_array['password_nuevo'];
				return update_user_password($ulogin, $var_array['password_nuevo']);
				exit;
			});


		/*     __       _ _                   
		 *    / _| ___ | | | _____      _____ 
		 *   | |_ / _ \| | |/ _ \ \ /\ / / __|
		 *   |  _| (_) | | | (_) \ V  V /\__ \
		 *   |_|  \___/|_|_|\___/ \_/\_/ |___/
		 *                                    
		 */
		
			/* Follow User
			 * @param Int $who_follows The active logged user
			 * @param Int $who The user ID to follow
			 * @param String $type The type of user who is following
			 * @return JSON success
			 */
			$slim->post('/rest/v1/:who_follows/follow',function($who_follows) {

				$who 	= isset($_POST['user_id']) 	? $_POST['user_id'] :  NULL;
				$type 	= isset($_POST['type']) 	? $_POST['type'] 	: 'suscriptor';
				$user  	= get_user_by('login', $who_follows);
				if( siguiendo( $who, $type, $user))
					wp_send_json_success();
				wp_send_json_error();
				exit;
			});

			/* Unfollow User
			 * @param Int $who_follows The active logged user
			 * @param Int $who The user ID to unfollow
			 * @return JSON success
			 */
			$slim->post('/rest/v1/:who_follows/unfollow',function($who_follows) {
				
				$who = isset($_POST['user_id']) ? $_POST['user_id'] : NULL;
				$user = get_user_by('login', $who_follows);
				if( dejar_de_seguir($who, $user))
					wp_send_json_success();
				wp_send_json_error();
				exit;
			});

			/* Get categories feed by parent level
			 * @param String $user_login The active logged user
			 * @param Int $level The category level to fetch
			 */
			$slim->get('/rest/v1/:user_login/categories/:level/', function($user_login, $level) {
				$user = get_user_by('login', $user_login);
				echo get_categories_feed($user, $level);
				exit;
			});
			
			/* Get random categories for discovery page
			 * @param String $user_login The active logged user
			 * @param Int $level The category level to fetch
			 */
			$slim->get('/rest/v1/:user_login/categories/rand/:number/', function($user_login, $number) {
				$user = get_user_by('login', $user_login);
				echo get_rand_categories_feed($user, $number);
				exit;
			});

			/* Get categories feed by parent level
			 * @param String $user_login The active logged user
			 * @param Int $level The category level to fetch
			 */
			$slim->get('/rest/v1/:u_login/categories/:level/:exclude', function($user_login, $level, $exclude) {
				$user = get_user_by('login', $user_login);
				echo get_categories_feed($user, $level, $exclude);
				exit;
			});
			
			/* Get category details (Explore category)
			 * @param String $user_login The active logged user
			 */
			$slim->get('/rest/v1/:user_login/category/:cat_id/', function($user_login, $cat_id) {
				
				wp_send_json_success(get_category_detail($cat_id, $user_login));
				exit;
			});

			/* Follow Category
			 * @param Int $user_login The active logged user
			 * @param Int $who The category ID to follow
			 */
			$slim->post('/rest/v1/:user_login/categories/follow/', function($user_login) {
				
				return follow_category($user_login);
				exit;
			});

			/* Unfollow Cateogry
			 * @param Int $who_follows The active logged user
			 * @param Int $who The cateogory ID to unfollow
			 */
			$slim->post('/rest/v1/:user_login/categories/unfollow/',function($user_login) {
				
				return unfollow_category($user_login);
				exit();
			});

			/* Get user follower count with user role filter
			 * @param String $user_login The user whose followers are being queried
			 * @param String $filter The type of followers we are looking for DEFAULT is 'museografo'
			 * TO DO: Here we call subscribers 'museografo', later make sure all the api calls respond to this standard
			 * @return Int $follower_count or false if error
			 */
			$slim->get('/rest/v1/user/:user_login/follower_count/:filter/', function($user_login, $filter = 'museografo'){
				$user = get_user_by('slug', $user_login);
				echo json_encode(intval(checa_total_seguidores($user->ID, $filter)));
				exit;
			});

			/* Get user following count with user role filter
			 * @param String $user_login The user whose followees are being queried
			 * @param String $filter The type of followees we are looking for DEFAULT is 'museografo'
			 * TO DO: Here we call subscribers 'museografo', later make sure all the api calls respond to this standard
			 * @return Int $followee_count or false if error
			 */
			$slim->get('/rest/v1/user/:user_login/followee_count/:filter/', function($user_login, $filter = 'museografo'){
				$user = get_user_by('slug', $user_login);
				echo json_encode(intval(checa_total_siguiendo($user->ID, $filter)));
				exit;
			});

		/*                _   _  __ _           _   _                 
		 *    _ __   ___ | |_(_)/ _(_) ___ __ _| |_(_) ___  _ __  ___ 
		 *   | '_ \ / _ \| __| | |_| |/ __/ _` | __| |/ _ \| '_ \/ __|
		 *   | | | | (_) | |_| |  _| | (_| (_| | |_| | (_) | | | \__ \
		 *   |_| |_|\___/ \__|_|_| |_|\___\__,_|\__|_|\___/|_| |_|___/
		 *                                                            
		 */

			/* Get notifications count
			 * @param String $user_login The active logged user_login name
			 * @return JSON
			 */
			$slim->get('/rest/v1/:user_login/notifications/count/',function($user_login) {
				$user = get_user_by('login', $user_login);
				
				echo get_count_alertas_user($user);
				exit();
			});

			/* Get notifications
			 * @param String $user_login The active logged user_login name
			 * Retrieves 10 max notifications, to change this modify the get_notifications_pool second parameter
			 * @return JSON
			 */
			$slim->get('/rest/v1/:user_login/notifications/',function($user_login) {
				
				echo get_notifications_pool($user_login, 10);
				exit();
			});

			/* Mark notification as read
			 * @param String $user_login The active logged user_login name
			 * @param Int $notification_id The ID of the notification
			 * @return JSON success
			 */
			$slim->post('/rest/v1/:user_login/notifications/read/:n_id',function($user_login, $notification_id) {
				$user = get_user_by('login', $user_login);

				if(update_alerta_id($notification_id)) wp_send_json_success();
				exit();
			});
		

		/*     __               _ 
		 *    / _| ___  ___  __| |
		 *   | |_ / _ \/ _ \/ _` |
		 *   |  _|  __/  __/ (_| |
		 *   |_|  \___|\___|\__,_|
		 */      
		
			/*
			 * Get user's timeline feed
			 * @param String $user_login The user to retrieve timeline for
			 * @param Int $offset Number of offsetted posts pages for pagination purposes
			 * @important Timeline gets blocks of 10 activities, offset must be set according to the set of results. Ej. Page 1 is offset 0, page 2 is offset 1
			 * 
			 */
			$slim->get('/rest/v1/:u_login/timeline/:offset',function ($user_login, $offset){
				echo get_user_timeline($user_login, $offset);
				exit;
			});
			
			/*
			 * Get discover screen feed
			 * @param String $user_login The logged in user (to be deprecated soon)
			 * @param Int $offset Number of offsetted posts pages for pagination purposes
			 * @important Timeline gets blocks of 10 activities, offset must be set according to the set of results. Ej. Page 1 is offset 0, page 2 is offset 1
			 * 
			 */
			$slim->get('/rest/v1/:u_login/feeds/discover/',function ($user_login){
				// echo get_discover_feed($user_login);
				echo "Sowwy, this endpoint will be available until next version of the API";
				exit;
			});

		
			/*                         _            
			 *     _____   _____ _ __ | |_ ___  ___ 
			 *    / _ \ \ / / _ \ '_ \| __/ _ \/ __|
			 *   |  __/\ V /  __/ | | | || (_) \__ \
			 *    \___| \_/ \___|_| |_|\__\___/|___/
			 *                                      
			 */
		
			/*
			 * Get events feed for a user
			 * @param String $user_login
			 * @param Int $offset Feed offset
			 * @param Int $filter Events filter (all, most_popular, most_visited, ending)
			 * @new v2 of this method includes a filter parameter
			 */
			$slim->get('/rest/v1/:user_login/events/feed/:offset/:filter',function($user_login, $offset, $filter) {
				$user = get_user_by('login', $user_login);
				echo get_events_feed($user, $filter, $offset);
				exit;
			});

			/*
			 * Get event single data
			 * 
			 * @param Int $event_id
			 * @param String $user_login
			 */
			$slim->get('/rest/v1/:u_login/events/:e/',function($user_login, $event_id) {
				echo get_event_single($event_id, $user_login);
				exit;
			});

			/*
			 * Schedule an event
			 * @param String $user_login
			 * @param Int $evento_id (via $_POST)
			 */
			$slim->post('/rest/v1/:u_login/schedule',function($user_login) {
				$user = get_user_by('login', $user_login );
				if(museografo_agendar_evento($user)) echo wp_send_json_success();
				exit;
			});

			/*
			 * Unschedule an event
			 * @param String $user_login
			 * @param Int $evento_id (via $_POST)
			 */
			$slim->post('/rest/v1/:u_login/unschedule',function($user_login) {
				$user = get_user_by('login', $user_login );
				if(museografo_desagendar_evento($user)) echo wp_send_json_success();
				exit;
			});

			/*
			 * Mark event as attended
			 * @param String $user_login
			 * @param Int $post_ID (via $_POST)
			 * @TO DO: Include geolocation parameter to check before marking an event
			 */
			$slim->post('/rest/v1/:u_login/events/attend',function($user_login) {
				if(confirmar_asistencia($user_login)) 
					wp_send_json_success();
				wp_send_json_error('There ws an error marking the event');
				exit;
			});

			/*
			 * Get scheduled events for a user
			 * @param Int $user_login
			 * @returns Array of Integers
			 */
			$slim->get('/rest/v1/:u_login/scheduled', function($user_login) {
				$user = get_user_by('login', $user_login );
				$scheduled = museografo_eventos_agendados($user);
				$scheduled = array_map('intval', $scheduled);
				echo json_encode($scheduled);
				exit;
			});

			/*
			 * Get scheduled events feed
			 * @param Int $user_login
			 * @returns JSON encoded Array of Objects
			 */
			$slim->get('/rest/v1/:u_login/scheduled_feed/:offset/', function( $user_login, $offset) {
				$user_obj = get_user_by("slug", $user_login);
				if(!$user_obj)
					wp_send_json_error("No such user here");
				$scheduled_full = get_scheduled_feed($user_obj, $offset, 99);
				echo $scheduled_full;
				exit;
			});

			/*
			 * Get attachments for the event gallery
			 * @param Int $event_id
			 * @param Int $limit
			 */
			$slim->get('/rest/v1/events/:e_id/gallery/:limit/(:size/)', function($event_id, $limit, $size = 'gallery_mobile') {	
				echo get_event_gallery($event_id, $limit, $size);
				exit;
			});

			/*
			 * Recomend event to another user
			 * @param Int $event_id
			 */
			$slim->post('/rest/v1/:u_login/recomend/', function($user_login) {
				$user = get_user_by('login', $user_login );
				echo recomend_event_to_user($user);
				exit;
			});

			/*
			 * Create a new event
			 * @param String $logged The user that's logged in
			 * @param Array $event_data Form data to create an event (via $_POST)
			 */
			$slim->post('/rest/v1/:logged/event/', function($logged) {
				
				$result = museo_create_new_event($logged, $_POST);
				wp_send_json_success($result);
				if(isset($_POST['_redirect']) AND $result){
					wp_redirect($_POST['_redirect']);
				}elseif(!$result){
					wp_redirect(user_url('nav=crear-evento?created=false'));
				}
				exit;
			});

			/*
			 * Create a new special project
			 * @param String $logged The user that's logged in
			 * @param Array $event_data Form data to create an event (via $_POST)
			 */
			$slim->post('/rest/v1/:logged/project/', function($logged) {
				
				$result = museo_create_new_project($logged, $_POST);
				wp_send_json_success($result);
				if(isset($_POST['_redirect']) AND $result){
					wp_redirect($_POST['_redirect']);
				}elseif(!$result){
					wp_redirect(user_url('nav=create-project?created=false'));
				}
				exit;
			});

		
		/*                                    
		 *                                           _        
		 *   ___ ___  _ __ ___  _ __ ___   ___ _ __ | |_ ___  
		 *  / __/ _ \| '_ ` _ \| '_ ` _ \ / _ \ '_ \| __/ __| 
		 * | (_| (_) | | | | | | | | | | |  __/ | | | |_\__ \ 
		 *  \___\___/|_| |_| |_|_| |_| |_|\___|_| |_|\__|___/
		 *                                                   
		 */
		
			/*
			 * Post a new comment to an event
			 * @param String $logged_user The user posting the comment
			 * @param Int $event_id The event where the comment is posted (via $_POST)
			 * @param String $comment_content The content of the comment (via $_POST)
			 * @TO DO Implement replies (indented comments)
			 */
			$slim->post('/rest/v1/:logged_user/events/comments/',function($logged_user) {
				if(post_comment_to_event($logged_user)) 
					wp_send_json_success();
				wp_send_json_error('There was a problem posting your comment');
				exit;
			});
			
			/*
			 * Get comments from a certain event
			 * @param Int $event_id
			 * @TO DO Implement replies (indented comments)
			 */
			$slim->get('/rest/v1/events/comments/:event_id/:offset',function($event_id, $offset) {
				return get_event_comments($event_id, $offset);
				exit;
			});

			/*
			 * Get comments from a certain user
			 * @param Int $user_id
			 * 
			 */
			$slim->get('/rest/v1/:u_login/comments',function($user_login) {
				$user = get_user_by('login', $user_login );
				echo get_user_comments($user->ID);
				exit;
			});

			/*
			 * Upvote a comment
			 * @param String $user_login
			 * @param Int $comment_id
			 */
			$slim->post('/rest/v1/:u_login/upvote/:c_id', function( $user_login, $comment_id) {
				
				$user = get_user_by('login', $user_login );
				$comment = get_comment( $comment_id );
				
				$args = array(
							'comment_id' 		=> $comment_id,
							'parent_id' 		=> $comment->comment_post_ID,
							'comment_author_id' => $comment->user_id,
							'vote' 				=> 'up',
							'voter'				=> $user
						);
				if(vote_comment($args)) wp_send_json_success();
				exit;
			});

			/*
			 * Downvote a comment
			 * @param String $user_login
			 * @param Int $comment_id
			 */
			$slim->post('/rest/v1/:u_login/downvote/:c_id', function( $user_login, $comment_id) {
				
				$user = get_user_by('login', $user_login );
				$comment = get_comment( $comment_id );
				
				$args = array(
							'comment_id' 		=> $comment_id,
							'parent_id' 		=> $comment->comment_post_ID,
							'comment_author_id' => $comment->user_id,
							'vote' 				=> 'down',
							'voter'				=> $user
						);
				if(vote_comment($args)) wp_send_json_success();
				exit;
			});

		
		/*                                    
		 *   __   _____ _ __  _   _  ___  ___ 
		 *   \ \ / / _ \ '_ \| | | |/ _ \/ __|
		 *    \ V /  __/ | | | |_| |  __/\__ \
		 *     \_/ \___|_| |_|\__,_|\___||___/
		 *                                    
		 */
		
			/*
			 * Get venue single data
			 * @param Int $venue_id
			 * 
			 */
			$slim->get('/rest/v1/:user_login/venues/:venue_id/',function($user_login, $venue_id) {
				wp_send_json_success(get_venue_profile($venue_id, $user_login));
				exit;
			});


	   /*                          _     
		*  ___  ___  __ _ _ __ ___| |__  
		* / __|/ _ \/ _` | '__/ __| '_ \ 
		* \__ \  __/ (_| | | | (__| | | |
		* |___/\___|\__,_|_|  \___|_| |_|
		*/

			/*
			 * Search website
			 * @param String $s
			 * TO DO: Divide search by: people, tag, events and accept the parameter as a filter
			 */
			$slim->get('/rest/v1/user/:logged/search/:s/:offset/',function($logged, $s, $offset) {
				return search_museografo($s, $offset, $logged);
				exit;
			});

			/*
			 * Search usernames for autocomplete
			 * @param String $s
			 */
			$slim->get('/rest/v1/:logged/search/:s',function($logged, $s) {
				return search_usernames($logged, $s);
				exit;
			});

			$slim->put('/rest/v1/user/:logged/index/categories/', function($logged){
				$app = \Slim\Slim::getInstance();
				$values = array();
				parse_str($app->request->getBody(), $values);
				
				if( update_index_categories($logged, $values) AND isset($values['_redirect']) )
					wp_redirect($values['_redirect']);
				exit;
			});

		/*
		 *    __ _ _                    _                 _ 
		 *   / _(_) | ___   _   _ _ __ | | ___   __ _  __| |
		 *  | |_| | |/ _ \ | | | | '_ \| |/ _ \ / _` |/ _` |
		 *  |  _| | |  __/ | |_| | |_) | | (_) | (_| | (_| |
		 *  |_| |_|_|\___|  \__,_| .__/|_|\___/ \__,_|\__,_|
		 *                       |_|                        
		 */

			/*
			 * Upload event image
			 * @param String $logged
			 * @param File $file via $_POST
			 * @return JSON success
			 * TO DO: Check token validity before actually uploading file
			 * TO DO: Generate extra tokens to upload files, like a nonce
			 */
			$slim->post('/rest/v1/transfers/:logged/event_upload/:event_id/', function($logged, $event_id){
				if( isset($_FILES)){
					wp_send_json_success(save_event_upload($logged, $_FILES['file']['tmp_name'], $_FILES['file']['name'], $event_id));
					exit;
				}
				wp_send_json_error("No files detected");
				exit;
			});
	
			/*
			 * Update user profile pic
			 * @param String $logged
			 * @param File $file via $_POST
			 * @return JSON success
			 * TO DO: Check token validity before actually uploading file
			 * TO DO: Generate extra tokens to upload files, like a nonce
			 */
			$slim->put('/rest/v1/transfers/:logged/profile/', function($logged){
				$app = \Slim\Slim::getInstance();
				$values = array();
				parse_str($app->request->getBody(), $values);
				
				if( update_index_categories($logged, $values) AND isset($values['_redirect']) )
					wp_redirect($values['_redirect']);
				exit;
			});
			
			/*
			 * Update user profile pic
			 * @param String $logged
			 * @param File $file via $_POST
			 * @return JSON success
			 * TO DO: Check token validity before actually uploading file
			 * TO DO: Generate extra tokens to upload files, like a nonce
			 */
			$slim->post('/rest/v1/transfers/:logged/profile/', function($logged){
				if( isset($_FILES)){
					wp_send_json_success(save_profile_picture_upload($logged, $_FILES['file']['tmp_name'], $_FILES['file']['name']));
					exit;
				}
				wp_send_json_error("No files detected");
				exit;
			});


			/*    _                _       
			 *   /_\  ___ ___  ___| |_ ___ 
			 *  //_\\/ __/ __|/ _ \ __/ __|
			 * /  _  \__ \__ \  __/ |_\__ \
			 * \_/ \_/___/___/\___|\__|___/
			 * General data sets for ui controls or some assets, i love the word assets                           
			 */  

			/*
			 * Update user profile pic
			 * @param String $logged
			 * @param File $file via $_POST
			 * @return JSON success
			 * TO DO: Check token validity before actually uploading file
			 * TO DO: Generate extra tokens to upload files, like a nonce
			 */
			$slim->get('/rest/v1/assets/:asset_name/:args/', function($asset_name, $args){
				echo json_encode(museo_get_asset_by_name($asset_name, $args));
				exit;
			});

			/*
			 * Write to log
			 * @param String $log_name
			 * @param Object $pieces via POST
			 * @return success Object including raw log input
			 */
			$slim->post('/rest/v1/assets/logs/:log_name/', function($log_name){
				echo json_encode(museo_write_log($log_name));
				exit;
			});
	
	}
                      

	/*
	 * Set expiration for the Request token
	 * @param $exp Int (Expiration time in miliseconds)
	 * Default value is 86,400,000 (24 hours)
	 * Use 0 for no expiration token
	 * TO DO: Invalidate token after expiration
	 */
	private function set_expiration($exp = 86400000){
		$this->attrs['timestamp'] 	= microtime(FALSE);
		$this->attrs['expires'] 	= $exp;
		return $this;
	}

	/*
	 * Generate token
	 * @param $active Bool FALSE is default for passive tokens
	 * PLEASE DO NOT ACTIVATE TOKENS DIRECTLY, USE AUTH-ACTIVATE TOKEN INSTEAD
	 */
	private function generateToken($active = FALSE){
		$token = strtoupper(md5(uniqid(rand(), true)));
		$this->setToken($token, $active);
		$this->set_expiration();
		$this->saveToken_toDB();
		return $this->getToken();
	}

	/*
	 * Save Token to DB
	 * @param $user_id String Default is "none"
	 */
	private function saveToken_toDB($user_id = 'none'){
		global $wpdb;
		$result = $wpdb->query( $wpdb->prepare(" INSERT INTO _api_active_tokens
												  (user_id, token, token_status, expiration)
												  VALUES(%s, %s, %d, %d);
											   "
											 	 ,$user_id
											 	 ,$this->attrs['request_token']
											 	 ,0
											 	 ,$this->attrs['expires'] ));
		return ($result == 1) ? TRUE : FALSE; 
	}

	/*
	 * Update Token status
	 * @param $token String
	 * @param $user_id Int
	 * @param $status String
	 */
	public function update_tokenStatus( $token, $user_id = 'none', $status = 0){
		global $wpdb;
		$result = $wpdb->query( $wpdb->prepare(" UPDATE _api_active_tokens
												  SET token_status = %d
												  WHERE user_id = %s
												  AND token = %s;
											   "
											 	 ,$status
											 	 ,$user_id
											 	 ,$token));
		return ($result == 1) ? $token : FALSE; 
	}

	/*
	 * Update Token expiration time 
	 * @param $token String
	 * @param $user_id Int
	 * @param $new_expiration Int (milliseconds) Default is 86400000
	 */
	private function update_tokenExp( $token, $user_id = 'none', $new_expiration = 86400000){
		global $wpdb;
		$result = $wpdb->query( $wpdb->prepare(" UPDATE _api_active_tokens
												  SET expiration = %d
												  WHERE user_id = %s
												  AND token = %s;
											   "
											 	 ,$status
											 	 ,$user_id
											 	 ,$token));
		return ($result == 1) ? $token : FALSE; 
	}

	/*
	 * Update Token expiration time 
	 * @param $token String
	 * @param $user_id Int
	 * @param $new_timestamp Unix timestamp
	 */
	private function update_tokenTimestamp( $token, $user_id = 'none', $new_timestamp){
		global $wpdb;
		$result = $wpdb->query( $wpdb->prepare(" UPDATE _api_active_tokens
												  SET ge_timestamp = FROM_UNIXTIME(%d)
												  WHERE user_id = %s
												  AND token = %s;
											   "
											 	 ,$status
											 	 ,$user_id
											 	 ,$new_timestamp));
		return ($result == 1) ? $token : FALSE; 
	}

	/*
	 * Update Token user
	 * @param $token String
	 * @param $user_id Int The user that will be associated with the token
	 */
	public function settokenUser( $token, $user_id = 'none', $device_info = NULL){
		global $wpdb;
		$result = $wpdb->query( $wpdb->prepare(" UPDATE _api_active_tokens
												  SET user_id = %d
												  WHERE token = %s;
											   "
											 	 ,$user_id
											 	 ,$token));
		$pieces = array();
		if($result == 1 AND $device_info)
			$pieces = array(
							'data' => $device_info,
							'message' => "Token {$token} successfully assigned to the user {$user_id} connected from mobile device."
						);
		$pieces = array(
						'user_id' => $user_id,
						'error' => "Couldn't get device info"
					);
		// museo_write_log('connections', $pieces);
		return ($result == 1) ? TRUE : FALSE; 
	}

	/*  
	 * Check token validity
	 * @param user_id String (string for internal purposes, int id's only)
	 * @param token String 
	 * @param Array $device_info contains device info to write in the log 
	 */
	public function check_token_valid($user_id, $token, $device_info = NULL){
		global $wpdb;
		$result = $wpdb->get_var( $wpdb->prepare(" SELECT token_status
													FROM _api_active_tokens
													  WHERE user_id = %s
													  AND token = %s;
												   "
											 	 ,$user_id
											 	 ,$token));
		$pieces = array();
		if($result == 1 AND $device_info){
			$pieces = array(
							'request_token' => $token,
							'user_id' => $user_id,
							'data' => $device_info,
							'message' => "Token {$token} checked for validation connected from mobile device."
						);
			// museo_write_log('connections', $pieces);
			return ($result == 1) ? TRUE : FALSE; 
		}
		$pieces = array(
						'user_id' => $user_id,
						'error' => "Couldn't get device info"
					);
		// museo_write_log('connections', $pieces);
		return ($result == 1) ? TRUE : FALSE; 
	}

	/*
	 * Token setter
	 * @param $token String
	 * @param $active Bool Default is FALSE
	 * Please DO NOT activate tokens directly, 
	 *  follow authentication process to do so
	 */
	private function setToken($token, $active = FALSE){
		$this->attrs['request_token'] = $token;
		return $this;
	}
	
	/*
	 * Token getter
	 * @return (String) Object token 
	 */
	private function getToken(){
		return $this->attrs['request_token'];
	}

}