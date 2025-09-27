<?php
/**
 * Plantilla de archivo para el CPT Novedades
 * Ubicación: tu-tema-hijo/archive-novedad.php
 */
get_header(); ?>

<main id="primary" class="site-main novedades-archivo">
    <header class="page-header">
        <h1 class="page-title"><?php post_type_archive_title(); ?></h1>
    </header>

    <?php if ( have_posts() ) : ?>
        <div class="novedades-grid">
            <?php while ( have_posts() ) : the_post(); ?>
                <article id="post-<?php the_ID(); ?>" <?php post_class('novedad-item'); ?>>
                    <a href="<?php the_permalink(); ?>" class="novedad-thumb">
                        <?php if ( has_post_thumbnail() ) {
                            the_post_thumbnail( 'medium' );
                        } else {
                            echo '<img src="' . esc_url( get_template_directory_uri() . '/images/placeholder.png' ) . '" alt="">';
                        } ?>
                    </a>
                    <div class="novedad-info">
                        <h2 class="novedad-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                        <?php
                            $lugar = get_post_meta( get_the_ID(), '_ncpt_lugar', true );
                            $fecha = get_post_meta( get_the_ID(), '_ncpt_fecha', true );
                        ?>
                        <p class="novedad-meta">
                            <?php if ( $fecha ) echo 'Fecha: ' . esc_html( $fecha ) . ' &nbsp;|&nbsp; '; ?>
                            <?php if ( $lugar ) echo 'Lugar: ' . esc_html( $lugar ); ?>
                        </p>
                        <p class="novedad-excerpt"><?php echo wp_trim_words( get_the_excerpt(), 25 ); ?></p>
                        <a class="novedad-link" href="<?php the_permalink(); ?>">Ver más</a>
                    </div>
                </article>
            <?php endwhile; ?>
        </div>

        <div class="novedades-pagination">
            <?php the_posts_pagination(); ?>
        </div>

    <?php else : ?>
        <p>No hay novedades disponibles en este momento.</p>
    <?php endif; ?>
</main>

<?php get_footer(); ?>
