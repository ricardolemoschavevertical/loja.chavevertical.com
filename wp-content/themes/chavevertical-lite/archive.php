<?php
defined( 'ABSPATH' ) || exit;
get_header();
?>
<div class="cvl-shell cvl-content">
    <header class="cvl-search-heading">
        <span><?php esc_html_e( 'ARQUIVO', 'chavevertical-lite' ); ?></span>
        <?php the_archive_title( '<h1>', '</h1>' ); ?>
        <?php the_archive_description( '<div class="cvl-archive-description">', '</div>' ); ?>
    </header>

    <?php if ( have_posts() ) : ?>
        <div class="cvl-search-results">
            <?php while ( have_posts() ) : the_post(); ?>
                <article <?php post_class( 'cvl-search-result' ); ?>>
                    <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                    <?php the_excerpt(); ?>
                </article>
            <?php endwhile; ?>
        </div>
        <?php the_posts_pagination(); ?>
    <?php endif; ?>
</div>
<?php get_footer(); ?>
