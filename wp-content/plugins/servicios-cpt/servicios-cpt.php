<?php
/**
 * Plugin Name: Servicios CPT
 * Description: CPT de Servicios con categorías, campos personalizados, galería y shortcode de cards.
 * Version: 1.1
 * Author: Drexler Jesús Guzmán Cruz
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/*--------------------------------------------------------------
# 1. Registrar CPT y Taxonomía
--------------------------------------------------------------*/
function scpt_register_servicios() {

    register_post_type( 'servicio', array(
        'labels' => array(
            'name'          => 'Servicios',
            'singular_name' => 'Servicio',
            'add_new'       => 'Añadir Servicio',
            'add_new_item'  => 'Añadir Nuevo Servicio',
            'edit_item'     => 'Editar Servicio',
            'view_item'     => 'Ver Servicio',
        ),
        'public'       => true,
        'has_archive'  => true,
        'menu_icon'    => 'dashicons-store',
        'supports'     => array( 'title', 'editor', 'thumbnail' ),
        'rewrite'      => array( 'slug' => 'servicios' ),
        'show_in_rest' => true,
    ));

    register_taxonomy( 'servicio_cat', 'servicio', array(
        'labels' => array(
            'name'          => 'Categorías de Servicios',
            'singular_name' => 'Categoría de Servicio'
        ),
        'hierarchical' => true,
        'show_in_rest' => true,
        'rewrite'      => array( 'slug' => 'categoria-servicio' ),
    ));
}
add_action( 'init', 'scpt_register_servicios' );

/*--------------------------------------------------------------
# 2. Campos personalizados
--------------------------------------------------------------*/
function scpt_add_metaboxes() {
    add_meta_box( 'scpt_datos', 'Datos del Servicio', 'scpt_metabox_html', 'servicio', 'normal', 'default' );
}
add_action( 'add_meta_boxes', 'scpt_add_metaboxes' );

function scpt_metabox_html( $post ) {

    wp_nonce_field( 'scpt_datos_nonce', 'scpt_datos_nonce' );

    // ✅ LOGO con selector
    $logo_id  = get_post_meta( $post->ID, 'scpt_logo_id', true );
    $logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'thumbnail' ) : '';
    echo '<p><label><strong>Logo</strong></label></p>';
    echo '<div id="scpt-logo-preview">';
    if ( $logo_url ) {
        echo '<img src="'.esc_url($logo_url).'" style="max-width:120px; display:block; margin-bottom:8px;">';
    }
    echo '</div>';
    echo '<input type="hidden" id="scpt_logo_id" name="scpt_logo_id" value="'.esc_attr($logo_id).'">';
    echo '<button type="button" class="button" id="scpt-logo-btn">Seleccionar/Editar Logo</button>';

    // ✅ Campos de texto restantes
    $fields = array(
        'contacto'  => 'Contacto (teléfono / email)',
        'ubicacion' => 'Ubicación escrita',
        'mapa'      => 'Enlace de Google Maps',
        'instagram' => 'URL Instagram',
        'facebook'  => 'URL Facebook',
        'whatsapp'  => 'URL WhatsApp',
    );

    foreach ( $fields as $key => $label ) {
        $value = get_post_meta( $post->ID, 'scpt_'.$key, true );
        echo '<p><label>'.$label.'</label><br>';
        echo '<input type="text" name="scpt_'.$key.'" value="'.esc_attr($value).'" style="width:100%"></p>';
    }

    // ✅ Galería (con botones de eliminar)
    $galeria_ids = get_post_meta( $post->ID, 'scpt_galeria', true );
    echo '<p><label><strong>Galería de Imágenes</strong></label></p>';
    echo '<div id="scpt-galeria-preview">';
    if ( $galeria_ids ) {
        $ids = explode( ',', $galeria_ids );
        foreach ( $ids as $id ) {
            $id = (int) $id;
            echo '<div class="scpt-thumb" data-id="'.$id.'" style="position:relative;display:inline-block;margin:5px;">';
            echo '<button type="button" class="scpt-remove" title="Eliminar" style="position:absolute;top:-8px;right:-8px;background:#dc3232;color:#fff;border-radius:50%;width:22px;height:22px;line-height:20px;text-align:center;border:0;cursor:pointer;">×</button>';
            echo wp_get_attachment_image( $id, 'thumbnail', false, array( 'style' => 'display:block;max-width:120px;height:auto;border:1px solid #ddd;border-radius:4px;' ) );
            echo '</div>';
        }
    }
    echo '</div>';
    echo '<input type="hidden" id="scpt_galeria" name="scpt_galeria" value="'.esc_attr($galeria_ids).'">';
    echo '<button type="button" class="button" id="scpt-galeria-btn">Añadir imágenes</button>';
}

function scpt_save_metabox( $post_id ) {
    // Seguridad básica
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! isset( $_POST['scpt_datos_nonce'] ) || ! wp_verify_nonce( $_POST['scpt_datos_nonce'], 'scpt_datos_nonce' ) ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    $keys = array( 'logo_id','contacto','ubicacion','mapa','instagram','facebook','whatsapp','galeria' );
    foreach ( $keys as $key ) {
        if ( isset( $_POST['scpt_'.$key] ) ) {
            $val = $_POST['scpt_'.$key];

            // Sanitiza por tipo
            if ( $key === 'logo_id' ) {
                $val = (int) $val;
            } elseif ( $key === 'galeria' ) {
                $val = implode( ',', array_filter( array_map( 'intval', array_filter( array_map( 'trim', explode( ',', $val ) ) ) ) ) );
            } elseif ( in_array( $key, array( 'mapa','instagram','facebook','whatsapp' ), true ) ) {
                $val = esc_url_raw( $val );
            } else {
                $val = sanitize_text_field( $val );
            }

            update_post_meta( $post_id, 'scpt_'.$key, $val );
        }
    }
}
add_action( 'save_post_servicio', 'scpt_save_metabox' );

/*--------------------------------------------------------------
# 3. Shortcode para mostrar Cards
   Uso: [servicios_cards categoria="slug" cantidad="4"]
--------------------------------------------------------------*/
function scpt_servicios_cards_shortcode( $atts ) {
    $atts = shortcode_atts( array(
        'categoria' => '',
        'cantidad'  => -1,
    ), $atts, 'servicios_cards' );

    $args = array(
        'post_type'      => 'servicio',
        'posts_per_page' => $atts['cantidad'],
        'orderby'        => 'date',
        'order'          => 'DESC',
    );

    if ( $atts['categoria'] ) {
        $args['tax_query'] = array(
            array(
                'taxonomy' => 'servicio_cat',
                'field'    => 'slug',
                'terms'    => $atts['categoria'],
            ),
        );
    }

    $q = new WP_Query( $args );
    if ( ! $q->have_posts() ) return '<p>No hay servicios.</p>';

    ob_start();
    echo '<div class="scpt-servicios-grid">';
    while ( $q->have_posts() ) : $q->the_post();
        // ✅ Obtener URL del logo a partir del ID
        $logo_id = get_post_meta( get_the_ID(), 'scpt_logo_id', true );
        $logo    = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : '';
        $desc    = wp_trim_words( get_the_excerpt() ?: get_the_content(), 20, '…' );
        $link    = get_the_permalink();
        ?>
        <div class="scpt-card">
            <?php if ( $logo ) : ?>
                <div class="scpt-card-logo">
                    <img src="<?php echo esc_url( $logo ); ?>" alt="<?php the_title_attribute(); ?>">
                </div>
            <?php endif; ?>
            <h3><?php the_title(); ?></h3>
            <p><?php echo esc_html( $desc ); ?></p>
            <a class="scpt-btn" href="<?php echo esc_url( $link ); ?>">Ver más</a>
        </div>
        <?php
    endwhile;
    echo '</div>';
    wp_reset_postdata();
    return ob_get_clean();
}
add_shortcode( 'servicios_cards', 'scpt_servicios_cards_shortcode' );

/*--------------------------------------------------------------
# 4. Estilos
--------------------------------------------------------------*/
function scpt_enqueue_styles() {
    wp_add_inline_style( 'wp-block-library', "
        .scpt-servicios-grid{display:flex;flex-wrap:wrap;gap:20px;justify-content:center}
        .scpt-card{width:260px;border:1px solid #ddd;border-radius:8px;padding:15px;text-align:center;box-shadow:0 2px 4px rgba(0,0,0,.1)}
        .scpt-card-logo img{max-width:120px;max-height:120px;object-fit:contain;margin-bottom:10px}
        .scpt-card h3{font-size:1.2em;margin-bottom:8px}
        .scpt-card p{overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical}
        .scpt-btn{display:inline-block;margin-top:10px;padding:8px 16px;background:#0073aa;color:#fff;text-decoration:none;border-radius:4px}
        .scpt-btn:hover{background:#005177}
    " );
}
add_action( 'wp_enqueue_scripts', 'scpt_enqueue_styles' );

/*--------------------------------------------------------------
# 5. Plantilla individual
--------------------------------------------------------------*/
function scpt_load_single_template( $template ) {
    if ( is_singular( 'servicio' ) ) {
        $custom = plugin_dir_path( __FILE__ ) . 'templates/single-servicio.php';
        if ( file_exists( $custom ) ) return $custom;
    }
    return $template;
}
add_filter( 'single_template', 'scpt_load_single_template' );

/*--------------------------------------------------------------
# 6. Scripts admin (logo + galería)
--------------------------------------------------------------*/
function scpt_admin_enqueue( $hook ) {
    // Cargar en el editor del CPT (nuevo y edición)
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if ( $screen && $screen->post_type === 'servicio' && in_array( $hook, array( 'post-new.php','post.php' ), true ) ) {
        wp_enqueue_media();
        wp_enqueue_script(
            'scpt-galeria-script',
            plugin_dir_url( __FILE__ ) . 'scpt-galeria.js',
            array( 'jquery' ),
            '1.1',
            true
        );
    }
}
add_action( 'admin_enqueue_scripts', 'scpt_admin_enqueue' );

/*--------------------------------------------------------------
# 7. Scripts front (Swiper para galería)
--------------------------------------------------------------*/
function scpt_enqueue_front_assets() {
    if ( is_singular( 'servicio' ) ) {
        // Swiper CSS & JS
        wp_enqueue_style( 'swiper', 'https://cdn.jsdelivr.net/npm/swiper@9/swiper-bundle.min.css' );
        wp_enqueue_script( 'swiper', 'https://cdn.jsdelivr.net/npm/swiper@9/swiper-bundle.min.js', array(), null, true );

        // Inicialización del carrusel
        wp_add_inline_script( 'swiper', "
            document.addEventListener('DOMContentLoaded',function(){
                new Swiper('.scpt-galeria-carousel', {
                    slidesPerView: 4,
                    spaceBetween: 20,
                    pagination: { el: '.swiper-pagination', clickable: true },
                    navigation: { nextEl: '.swiper-button-next', prevEl: '.swiper-button-prev' },
                    breakpoints: {
                        0:   { slidesPerView: 1 },
                        768: { slidesPerView: 2 },
                        1024:{ slidesPerView: 4 }
                    }
                });
            });
        " );
        // Estilos básicos
        wp_add_inline_style( 'swiper', "
            .scpt-galeria-carousel{margin-top:30px}
            .scpt-galeria-carousel img{width:100%; height:auto; display:block; border-radius:8px}
        " );
    }
}
add_action( 'wp_enqueue_scripts', 'scpt_enqueue_front_assets' );
