<?php

class User{

	private $wpdb, $table_prefix;
	public $attrs;

	/*
	 * @param $attrs Array
	 * params
	 */
	public function __construct($attrs = array()){

		global $wpdb, $table_prefix;
		$this->wpdb = &$wpdb;
		$this->attrs = array();
		
	}

	/*
	 * Create new user
	 * TO DO: Create user object from login and fill attributes
	 */
	public function create($nombre, $password, $email, $attrs = array()){

		$response =  wp_create_user(
			$nombre,
			$password,
			$email
		);
		
		if(isset($attrs['twitter_username']))
			$this->set_tw_username($response, $attrs['twitter_username']);
		if(isset($attrs['gplus_username']))
			$this->set_gp_username($response, $attrs['gplus_username']);
		return $response;
	}

	/*
	 * Get username
	 */
	public function _username_exists($username){
		$user = get_user_by( 'login', $username );
		if(!$user) return FALSE;
		if($user_id 	= 	username_exists( $username )) return $user_id;
	}

	
	/*
	 * Check if user exists by mail or twitter account
	 */
	public function check_if_user_exists($username, $email) {

		//Check for twitter meta, email or username
		$user = get_user_by( 'login', $username );
		if(!$user) return FALSE;
		$found_twitter 	= 	get_user_meta($user->ID, 'twitter_username', TRUE);
		$found_email 	=	get_user_by( 'email', $email );
		if($user_id 	= 	username_exists( $username )) return $user_id;
		if($found_twitter) 	return $found_twitter;
		if($found_email) 	return $found_email;
		return FALSE;
	}

	/*
	 * Create user account if the username isn't taken
	 * @param $username
	 * @param $email
	 * @param $attrs (IMPORTANT: If account is created the 'native' way the password will be sent here as an attribute named password)
	 * @param $autologin Bool
	 * @return $success JSON encoded success, if user was created response contains also an object with the user info
	 * NOTE: If no password is passed the account may become inaccessible if created outside social login methods
	 */
	public function create_if__notExists($username, $email, $attrs = array(), $autologin = TRUE){
		
		$found = $this->check_if_user_exists($username, $email);
		
		if(!$found){
			$password = isset($attrs['password']) ? $attrs['password'] : wp_generate_password();
			$user_id = $this->create($username,  $password, $email, $attrs);
			$data_created = array('username' => $username, 'user_id' => $user_id);
			if($autologin){
				$this->login_if__Exists($username);
				return;
			}
			return (!is_wp_error($user_id)) ? $data_created : FALSE;
		}
		// if($autologin){
		// 	$this->login_if__Exists($username);
		// 	return;
		// }else{
		// 	wp_send_json_success($data_created);
		// }
		wp_send_json_error(array('error_message' => 'Couldn\'t create user', 'found_id' => $found));
		exit;
	}

	public function login_if__Exists($username){
		
		$user = get_user_by('login', $username);
		$this->pseudo_login($user->ID);
	}

	/*
	 * Set the username as metadata if provider is Twitter
	 * 
	 */
	private function set_tw_username($user_id, $twitter_username){
		
		$this->attrs['twitter_username'] = $twitter_username;
		return update_user_meta($user_id, 'twitter_username', $twitter_username);
	}

	/*
	 * Set the username as metadata if provider is Google+
	 * 
	 */
	private function set_gp_username($user_id, $gp_username){

		$this->attrs['gp_username'] = $gp_username;
		return update_user_meta($user_id, 'gp_username', $gp_username);
	}

	/*
	 * Pseudo LogIn 
	 * TO DO: Send user login and token for mobile app
	 */
	public function pseudo_login($user_id = NULL){

		if( $user_id ){
			wp_set_auth_cookie( $user_id, 0, 0 );
			wp_set_current_user( $user_id );
			global $current_user;
		}
		return wp_send_json_success();
	}

}
