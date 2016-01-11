<?php


// CUSTOM PAGES //////////////////////////////////////////////////////////////////////


	add_action('init', function(){


		// Semblanza
		if( ! get_page_by_path('semblanza') ){
			$page = array(
				'post_author' => 1,
				'post_status' => 'publish',
				'post_title'  => 'Jorge Marín',
				'post_name'   => 'semblanza',
				'post_type'   => 'page'
			);
			wp_insert_post( $page, true );
		}

		// Home
		if( ! get_page_by_path('home') ){
			$page = array(
				'post_author' => 1,
				'post_status' => 'publish',
				'post_title'  => 'Home gallery',
				'post_name'   => 'home',
				'post_type'   => 'page'
			);
			wp_insert_post( $page, true );
		}


	});
