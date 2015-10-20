<?php


// CUSTOM METABOXES //////////////////////////////////////////////////////////////////



	add_action('add_meta_boxes', function(){

		add_meta_box( 'info_event_meta', "Información de la exposición", "info_event_callback", "exposicion", "side", "high" );

	});



// CUSTOM METABOXES CALLBACK FUNCTIONS ///////////////////////////////////////////////



	function info_event_callback($post){

		$date_start = get_post_meta($post->ID, 'date_start', true);
		$date_end = get_post_meta($post->ID, 'date_end', true);
		$location = get_post_meta($post->ID, 'location', true);
		$latlong = get_post_meta($post->ID, 'latlong', true);
		$moreinfo = get_post_meta($post->ID, 'moreinfo', true);

		wp_nonce_field(__FILE__, 'info_event_nonce');
		
		echo "<label for='date_start'>Fecha de inauguración:</label>";
		echo "<input type='date' class='widefat' id='date_start' name='date_start' value='$date_start'/>";
		echo "<label for='date_end'>Fecha de clausura:</label>";
		echo "<input type='date' class='widefat' id='date_end' name='date_end' value='$date_end'/>";
		echo "<label for='location'>Lugar:</label>";
		echo "<input type='text' class='widefat' id='location' name='location' value='$location'/>";
		echo "<label for='latlong'>Latlong:</label>";
		echo "<input type='text' class='widefat' id='latlong' name='latlong' value='$latlong'/>";
		echo "<label for='moreinfo'>Más información (url):</label>";
		echo "<input type='text' class='widefat' id='moreinfo' name='moreinfo' value='$moreinfo'/>";
	}



// SAVE METABOXES DATA ///////////////////////////////////////////////////////////////



	add_action('save_post', function($post_id){


		if ( ! current_user_can('edit_page', $post_id)) 
			return $post_id;


		if ( defined('DOING_AUTOSAVE') and DOING_AUTOSAVE ) 
			return $post_id;
		
		
		if ( wp_is_post_revision($post_id) OR wp_is_post_autosave($post_id) ) 
			return $post_id;


		if ( isset($_POST['date_start']) and check_admin_referer(__FILE__, 'info_event_nonce') ){
			update_post_meta($post_id, 'date_start', $_POST['date_start']);
		}
		if ( isset($_POST['date_end']) and check_admin_referer(__FILE__, 'info_event_nonce') ){
			update_post_meta($post_id, 'date_end', $_POST['date_end']);
		}
		if ( isset($_POST['location']) and check_admin_referer(__FILE__, 'info_event_nonce') ){
			update_post_meta($post_id, 'location', $_POST['location']);
		}
		if ( isset($_POST['latlong']) and check_admin_referer(__FILE__, 'info_event_nonce') ){
			update_post_meta($post_id, 'latlong', $_POST['latlong']);
		}
		if ( isset($_POST['moreinfo_link']) and check_admin_referer(__FILE__, 'info_event_nonce') ){
			update_post_meta($post_id, 'moreinfo_link', $_POST['moreinfo_link']);
		}


		// Guardar correctamente los checkboxes
		/*if ( isset($_POST['_checkbox_meta']) and check_admin_referer(__FILE__, '_checkbox_nonce') ){
			update_post_meta($post_id, '_checkbox_meta', $_POST['_checkbox_meta']);
		} else if ( ! defined('DOING_AJAX') ){
			delete_post_meta($post_id, '_checkbox_meta');
		}*/


	});



// OTHER METABOXES ELEMENTS //////////////////////////////////////////////////////////
