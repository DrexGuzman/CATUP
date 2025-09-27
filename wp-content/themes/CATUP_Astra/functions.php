<?php
/**
 * Cargar los estilos del tema padre y del tema hijo
 */
function astra_child_enqueue_styles() {
    // Cargar estilo del tema padre
    wp_enqueue_style(
        'astra-parent-style',
        get_template_directory_uri() . '/style.css'
    );
    
    // Cargar estilo del tema hijo
    wp_enqueue_style(
        'astra-child-style',
        get_stylesheet_directory_uri() . '/style.css',
        array('astra-parent-style'),
        wp_get_theme()->get('Version')
    );

    // CPT: Servicios
function dg_register_servicios_cpt() {
    $labels = array(
        'name'               => 'Servicios',
        'singular_name'      => 'Servicio',
        'menu_name'          => 'Servicios',
        'name_admin_bar'     => 'Servicio',
        'add_new'            => 'Añadir nuevo',
        'add_new_item'       => 'Añadir nuevo servicio',
        'new_item'           => 'Nuevo servicio',
        'edit_item'          => 'Editar servicio',
        'view_item'          => 'Ver servicio',
        'all_items'          => 'Todos los servicios',
        'search_items'       => 'Buscar servicios',
    );

    $args = array(
        'labels'             => $labels,
        'public'             => true,
        'has_archive'        => true,
        'rewrite'            => array('slug' => 'servicios'),
        'menu_icon'          => 'dashicons-store',
        'supports'           => array('title','editor','thumbnail','comments'), // comments para reseñas
        'taxonomies'         => array('category'), // usarás categorías como "Hoteles", "Restaurantes", etc.
        'show_in_rest'       => true, // Para que sea compatible con Gutenberg/Elementor
    );

    register_post_type('servicio', $args);
}
add_action('init', 'dg_register_servicios_cpt');
}

function child_enqueue_styles() {
    wp_enqueue_style( 'child-style', get_stylesheet_uri() );
}
add_action( 'wp_enqueue_scripts', 'child_enqueue_styles', 20 );

function eef_enqueue_lato_font() {
    wp_enqueue_style(
        'lato-font',
        'https://fonts.googleapis.com/css2?family=Lato:wght@400;700&display=swap',
        [],
        null
    );
}
add_action( 'wp_enqueue_scripts', 'eef_enqueue_lato_font' );
