<?php
get_header();
while ( have_posts() ) : the_post();
    $logo_id = get_post_meta( get_the_ID(), 'scpt_logo_id', true );
    $logo    = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : '';
    $contacto  = get_post_meta( get_the_ID(), 'scpt_contacto', true );
    $ubicacion = get_post_meta( get_the_ID(), 'scpt_ubicacion', true );
    $mapa      = get_post_meta( get_the_ID(), 'scpt_mapa', true );
    $insta     = get_post_meta( get_the_ID(), 'scpt_instagram', true );
    $face      = get_post_meta( get_the_ID(), 'scpt_facebook', true );
    $wa        = get_post_meta( get_the_ID(), 'scpt_whatsapp', true );
    $galeria   = get_post_meta( get_the_ID(), 'scpt_galeria', true );
    $scpt_assets = plugins_url( 'assets/', dirname(__DIR__) . '/servicios-cpt.php' );
?>

<div style="background: #2182A5; width: 100%; height: 100px; position: absolute; top: 0; left: 0; z-index: -1;"></div>

<main class="servicio-detalle">

    <div style="display: flex; flex-direction: column; align-items: center; margin-bottom: 20px;">
        
        <?php if ( $logo ) : ?>
            <p><img src="<?php echo esc_url( $logo ); ?>" alt="<?php the_title(); ?>" style="max-width:200px;"></p>
            <?php endif; ?>
            
            <h1 style="text-align: center; margin-bottom: 34px;"><?php the_title(); ?></h1>
            <div>
                <p class="scpt-descripcion-inline">
                    <strong>Descripción:</strong>
                    <span><?php echo esc_html( wp_strip_all_tags( get_the_content() ) ); ?></span>
                </p>
                <p style="margin-bottom: 10px;"><strong>Contacto: </strong><?php echo esc_html( $contacto ); ?></p>
                <p class="scpt-ubicacion"><strong>Ubicación: </strong> <?php echo esc_html( $ubicacion ); ?> <a style="color: #2182A5;" href="<?php echo esc_url( $mapa ); ?>" target="_blank" rel="noopener"> (Ver en Google Maps)</a></p>
                
                <div style="display: flex; gap: 2rem; margin-top: 10px;">
                    <p><strong>Redes sociales: </strong></p>
                    <?php if ( $insta ) : ?><a href="<?php echo esc_url( $insta ); ?>" target="_blank"><img src="<?php echo esc_url( $scpt_assets . 'instagram.svg' ); ?>" alt="Instagram" style="width:25px; height:25px; vertical-align:middle; margin-right:4px;"></a> <?php endif; ?>
                    <?php if ( $face )  : ?><a href="<?php echo esc_url( $face ); ?>" target="_blank"><img src="<?php echo esc_url( $scpt_assets . 'facebook.svg' ); ?>" alt="Facebook" style="width:25px; height:25px; vertical-align:middle; margin-right:4px;"></a> <?php endif; ?>
                    <?php if ( $wa )    : ?><a href="<?php echo esc_url( $wa ); ?>" target="_blank"><img src="<?php echo esc_url( $scpt_assets . 'whatsapp.svg' ); ?>" alt="WhatsApp" style="width:25px; height:25px; vertical-align:middle; margin-right:4px;"></a> <?php endif; ?>
                </div>
            </div>
    </div>
<p><strong>Galería: </strong></p>
    <?php
$galeria_ids = get_post_meta( get_the_ID(), 'scpt_galeria', true );
if ( $galeria_ids ) :
    $ids = explode( ',', $galeria_ids );
    ?>
    <div class="scpt-galeria-carousel swiper">
        <div class="swiper-wrapper">
            <?php foreach ( $ids as $id ) : ?>
                <div class="swiper-slide">
                    <?php echo wp_get_attachment_image( $id, 'large' ); ?>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="swiper-pagination"></div>
        <div class="swiper-button-prev"></div>
        <div class="swiper-button-next"></div>
    </div>
<?php endif; ?>

</main>
<?php
endwhile;
get_footer();
