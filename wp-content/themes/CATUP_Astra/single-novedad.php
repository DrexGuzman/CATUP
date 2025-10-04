    <?php
    /**
     * Plantilla de detalle de una Novedad
     * Ubicación: CATUP-astra/single-novedad.php
     */
    get_header();
    ?>
<div style="background: #2182A5; width: 100%; height: 100px; position: absolute; top: 0; left: 0; z-index: -1;"></div>

<main id="primary" class="site-main novedad-single" style="margin: 10rem auto; max-width: 800px; background: #fff; padding: 20px; border-radius: 8px;">
    <?php while ( have_posts() ) : the_post(); ?>
        <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>

            <header class="novedad-header">
                <h1 class="novedad-title"><?php the_title(); ?></h1>
                <?php
                    $lugar = get_post_meta( get_the_ID(), '_ncpt_lugar', true );
                    $fecha = get_post_meta( get_the_ID(), '_ncpt_fecha', true );
                ?>
                <p class="novedad-meta">
                    <?php if ( $fecha ) echo '<strong>Fecha:</strong> ' . esc_html( $fecha ) . ' &nbsp;|&nbsp; '; ?>
                    <?php if ( $lugar ) echo '<strong>Lugar:</strong> ' . esc_html( $lugar ); ?>
                </p>
            </header>

            <div class="novedad-content">
                <?php if ( has_post_thumbnail() ) : ?>
                    <div style="border-radius: 12px; overflow: hidden;" class="novedad-image">
                        <?php the_post_thumbnail( 'large' ); ?>
                    </div>
                <?php endif; ?>

                <div style="margin-top: 20px;" class="novedad-text">
                    <?php the_content(); ?>
                </div>
            </div>

            <footer class="novedad-footer">
                <nav class="novedad-navigation">
                    <?php
                    $next_post = get_adjacent_post(false, '', false);
                    $next_link = get_next_post_link('%link', '← Novedad siguiente');
                    ?>
                    <div class="nav-next <?php echo !$next_post ? 'disabled' : ''; ?>">
                        <?php echo $next_link ? $next_link : '← Novedad siguiente'; ?>
                    </div>
                    <?php
                    $prev_post = get_adjacent_post(false, '', true);
                    $prev_link = get_previous_post_link('%link', 'Novedad anterior →');
                    ?>
                    <div class="nav-previous <?php echo !$prev_post ? 'disabled' : ''; ?>">
                        <?php echo $prev_link ? $prev_link : 'Novedad anterior →'; ?>
                    </div>
                </nav>
            </footer>

        </article>
    <?php endwhile; ?>
</main>

<?php get_footer(); ?>
