<?php
/**
 * Template Name: Checkout Personalizado
 * Template Post Type: page
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<div style="background: #2182A5; width: 100%; height: 100px; position: absolute; top: 0; left: 0; z-index: -1;"></div>

<main id="checkout-personalizado" class="site-main" style="margin: 10rem auto; max-width: 800px; background: #fff; padding: 20px; border-radius: 8px;">
    <div >
        <h1><?php the_title(); ?></h1>
        <div class="checkout-block-area">
            <?php
            if ( function_exists( 'the_content' ) ) {
                while ( have_posts() ) : the_post();
                    the_content();
                endwhile;
            }
            ?>
        </div>
    </div>
</main>

<?php get_footer(); ?>
