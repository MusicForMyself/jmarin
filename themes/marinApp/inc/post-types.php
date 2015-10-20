<?php


// CUSTOM POST TYPES /////////////////////////////////////////////////////////////////


	add_action('init', function(){


		// Expos
		$labels = array(
			'name'          => 'Exposiciones',
			'singular_name' => 'Exposición',
			'add_new'       => 'Nueva Exposición',
			'add_new_item'  => 'Nueva Exposición',
			'edit_item'     => 'Editar Exposición',
			'new_item'      => 'Nueva Exposición',
			'all_items'     => 'Todas',
			'view_item'     => 'Ver Exposición',
			'search_items'  => 'Buscar Exposición',
			'not_found'     => 'No se encontro',
			'menu_name'     => 'Exposiciones'
		);

		$args = array(
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'query_var'          => true,
			'rewrite'            => array( 'slug' => 'exposicion' ),
			'capability_type'    => 'post',
			'has_archive'        => true,
			'hierarchical'       => false,
			'menu_position'      => 6,
			'taxonomies'         => array( 'category' ),
			'supports'           => array( 'title', 'editor', 'thumbnail' )
		);
		register_post_type( 'exposicion', $args );

		// User uploads
		$labels = array(
			'name'          => 'Subidas usuarios',
			'singular_name' => 'Subida',
			'add_new'       => 'Nueva Subida',
			'add_new_item'  => 'Nueva Subida',
			'edit_item'     => 'Editar Subida',
			'new_item'      => 'Nueva Subida',
			'all_items'     => 'Todas',
			'view_item'     => 'Ver Subida',
			'search_items'  => 'Buscar Subida',
			'not_found'     => 'No se encontro',
			'menu_name'     => 'Subidas usuarios'
		);

		$args = array(
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'query_var'          => true,
			'rewrite'            => array( 'slug' => 'user-upload' ),
			'capability_type'    => 'post',
			'has_archive'        => true,
			'hierarchical'       => false,
			'menu_position'      => 6,
			'taxonomies'         => array(),
			'supports'           => array( 'title', 'editor', 'thumbnail' )
		);
		register_post_type( 'user-upload', $args );

	});