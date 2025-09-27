<?php
/**
 * Plugin Name: Novedades CPT
 * Description: Crea el CPT "Novedades" con campos personalizados, shortcode para carrusel y navegación entre novedades.
 * Version:     1.0
 * Author:      Drexler Jesús Guzmán Cruz
 * Text Domain: novedades-cpt
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Registrar Custom Post Type
 */
function ncpt_register_post_type() {
    $labels = array(
        'name'               => 'Novedades',
        'singular_name'      => 'Novedad',
        'add_new'            => 'Añadir Novedad',
        'add_new_item'       => 'Añadir Nueva Novedad',
        'edit_item'          => 'Editar Novedad',
        'new_item'           => 'Nueva Novedad',
        'all_items'          => 'Todas las Novedades',
        'view_item'          => 'Ver Novedad',
        'search_items'       => 'Buscar Novedades',
        'not_found'          => 'No se encontraron novedades',
        'not_found_in_trash' => 'No hay novedades en la papelera',
        'menu_name'          => 'Novedades'
    );

    $args = array(
        'labels'             => $labels,
        'public'             => true,
        'has_archive'        => true,
        'rewrite'            => array( 'slug' => 'novedades' ),
        'supports'           => array( 'title', 'editor', 'thumbnail' ),
        'menu_icon'          => 'dashicons-megaphone',
        'show_in_rest'       => true, // para Gutenberg/Elementor
    );

    register_post_type( 'novedad', $args );
}
add_action( 'init', 'ncpt_register_post_type' );

/**
 * Metabox: Lugar y Fecha del evento
 */
function ncpt_add_meta_boxes() {
    add_meta_box(
        'ncpt_event_details',
        'Detalles del Evento',
        'ncpt_render_meta_box',
        'novedad',
        'normal',
        'default'
    );
}
add_action( 'add_meta_boxes', 'ncpt_add_meta_boxes' );

function ncpt_render_meta_box( $post ) {
    $lugar = get_post_meta( $post->ID, '_ncpt_lugar', true );
    $fecha = get_post_meta( $post->ID, '_ncpt_fecha', true );
    wp_nonce_field( 'ncpt_save_meta', 'ncpt_meta_nonce' );
    ?>
    <p>
        <label for="ncpt_lugar"><strong>Lugar del evento:</strong></label><br>
        <input type="text" name="ncpt_lugar" id="ncpt_lugar" value="<?php echo esc_attr( $lugar ); ?>" style="width:100%;">
    </p>
    <p>
        <label for="ncpt_fecha"><strong>Fecha del evento:</strong></label><br>
        <input type="date" name="ncpt_fecha" id="ncpt_fecha" value="<?php echo esc_attr( $fecha ); ?>">
    </p>
    <?php
}

function ncpt_save_meta( $post_id ) {
    if ( ! isset( $_POST['ncpt_meta_nonce'] ) || ! wp_verify_nonce( $_POST['ncpt_meta_nonce'], 'ncpt_save_meta' ) ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;

    if ( isset( $_POST['ncpt_lugar'] ) ) {
        update_post_meta( $post_id, '_ncpt_lugar', sanitize_text_field( $_POST['ncpt_lugar'] ) );
    }
    if ( isset( $_POST['ncpt_fecha'] ) ) {
        update_post_meta( $post_id, '_ncpt_fecha', sanitize_text_field( $_POST['ncpt_fecha'] ) );
    }
}
add_action( 'save_post', 'ncpt_save_meta' );

/**
 * Shortcode [novedades_carrusel]
 * Muestra las novedades en un carrusel básico (compatible con Elementor)
 */
function ncpt_shortcode_carrusel( $atts ) {
    ob_start();
    $query = new WP_Query( array(
        'post_type'      => 'novedad',
        'posts_per_page' => -1,
        'orderby'        => 'date',
        'order'          => 'DESC'
    ));
    if ( $query->have_posts() ) : ?>
        <div class="ncpt-carrusel swiper-container">
            <div class="swiper-wrapper">
                <?php while ( $query->have_posts() ) : $query->the_post();
                    $lugar = get_post_meta( get_the_ID(), '_ncpt_lugar', true );
                    $fecha = get_post_meta( get_the_ID(), '_ncpt_fecha', true );
                ?>
                <div class="swiper-slide ncpt-slide" style="background-image: url('<?php echo get_the_post_thumbnail_url(get_the_ID(),'full'); ?>');">
                    <a href="<?php the_permalink(); ?>">
                        <div class="ncpt-overlay">
                            <h3><?php the_title(); ?></h3>
                            <p><?php echo esc_html( $fecha ); ?></p>
                            <p><?php echo wp_trim_words( get_the_content(), 20 ); ?></p>
                        </div>
                    </a>
                </div>
                <?php endwhile; ?>
            </div>
            <!-- Navegación del carrusel -->
            <div class="swiper-button-next"></div>
            <div class="swiper-button-prev"></div>
            <div class="swiper-pagination"></div>
        </div>
        <?php
    else:
        echo '<p>No hay novedades disponibles.</p>';
    endif;
    wp_reset_postdata();
    return ob_get_clean();
}
add_shortcode( 'novedades_carrusel', 'ncpt_shortcode_carrusel' );

/**
 * Cargar SwiperJS (o tu librería de carrusel preferida)
 */
function ncpt_enqueue_assets() {
    // Swiper desde CDN
    wp_enqueue_style( 'swiper-css', 'https://cdn.jsdelivr.net/npm/swiper@9/swiper-bundle.min.css' );
    wp_enqueue_script( 'swiper-js', 'https://cdn.jsdelivr.net/npm/swiper@9/swiper-bundle.min.js', array(), null, true );
    wp_add_inline_script( 'swiper-js', "
        document.addEventListener('DOMContentLoaded', function(){
            new Swiper('.ncpt-carrusel', {
                loop: true,
                slidesPerView: 2,
                spaceBetween: 40,
                pagination: { el: '.swiper-pagination', clickable: true },
                navigation: { nextEl: '.swiper-button-next', prevEl: '.swiper-button-prev' },
                breakpoints: {
                    0: { slidesPerView: 1 },
                    768: { slidesPerView: 2 }
                }
            });
        });
    " );
}
add_action( 'wp_enqueue_scripts', 'ncpt_enqueue_assets' );
