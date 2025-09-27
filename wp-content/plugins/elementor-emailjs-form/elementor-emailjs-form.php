<?php
/**
 * Plugin Name: Elementor EmailJS Form
 * Description: Widget de Elementor con un formulario que envía correos mediante EmailJS.
 * Version: 1.0.0
 * Author: Drexler Jesús Guzmán Cruz
 * Text Domain: elementor-emailjs-form
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Registrar el widget
function eef_register_widget() {
    require_once __DIR__ . '/widget-emailjs-form.php';
    \Elementor\Plugin::instance()->widgets_manager->register( new \Elementor_EmailJS_Form_Widget() );
}
add_action( 'elementor/widgets/register', 'eef_register_widget' );

// Cargar scripts JS y EmailJS SDK
function eef_enqueue_scripts() {
    wp_enqueue_script( 'emailjs-sdk', 'https://cdn.jsdelivr.net/npm/emailjs-com@3/dist/email.min.js', [], null, true );
    wp_enqueue_script( 'eef-emailjs-form', plugin_dir_url( __FILE__ ) . 'assets/js/emailjs-form.js', [ 'jquery', 'emailjs-sdk' ], '1.0.0', true );

    // Pasar las opciones al JS (reemplaza con tus IDs si quieres valores por defecto)
    wp_localize_script( 'eef-emailjs-form', 'eef_vars', [
        'service_id' => '',   // se definirá en el widget
        'template_id' => '',
        'public_key' => '',
    ]);
}
add_action( 'wp_enqueue_scripts', 'eef_enqueue_scripts' );
