<?php


// CUSTOM METABOXES //////////////////////////////////////////////////////////////////



	add_action('add_meta_boxes', function(){

		add_meta_box( 'info_event_meta', "Información de la exposición", "info_event_callback", "exposicion", "side", "high" );

	});



// CUSTOM METABOXES CALLBACK FUNCTIONS ///////////////////////////////////////////////



	function info_event_callback($post){

		$date_start = get_post_meta($post->ID, 'date_start', true);
		$date_end = get_post_meta($post->ID, 'date_end', true);
		wp_nonce_field(__FILE__, 'info_event_nonce');
		echo "<label for='date_start'>Fecha de inauguración:</label>";
		echo "<input type='date' class='widefat' id='date_start' name='date_start' value='$date_start'/>";
		echo "<label for='date_end'>Fecha de clausura:</label>";
		echo "<input type='date' class='widefat' id='date_end' name='date_end' value='$date_end'/>";
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


		// Guardar correctamente los checkboxes
		/*if ( isset($_POST['_checkbox_meta']) and check_admin_referer(__FILE__, '_checkbox_nonce') ){
			update_post_meta($post_id, '_checkbox_meta', $_POST['_checkbox_meta']);
		} else if ( ! defined('DOING_AJAX') ){
			delete_post_meta($post_id, '_checkbox_meta');
		}*/


	});



// OTHER METABOXES ELEMENTS //////////////////////////////////////////////////////////
