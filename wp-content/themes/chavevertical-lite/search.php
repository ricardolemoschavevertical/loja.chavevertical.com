<?php
defined( 'ABSPATH' ) || exit;
get_header();
?>
<div class="cvl-shell cvl-content">
    <header class="cvl-search-heading">
        <span><?php esc_html_e( 'PESQUISA', 'chavevertical-lite' ); ?></span>
        <h1><?php printf( esc_html__( 'Resultados para: %s', 'chavevertical-lite' ), esc_html( get_search_query() ) ); ?></h1>
    </header>

    <?php if ( have_posts() ) : ?>
        <?php if ( 'product' === get_query_var( 'post_type' ) && function_exists( 'woocommerce_product_loop_start' ) ) : ?>
            <?php woocommerce_product_loop_start(); ?>
            <?php while ( have_posts() ) : the_post(); ?>
                <?php wc_get_template_part( 'content', 'product' ); ?>
            <?php endwhile; ?>
            <?php woocommerce_product_loop_end(); ?>
            <?php woocommerce_pagination(); ?>
        <?php else : ?>
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
    <?php else : ?>
        <div class="cvl-empty-state">
            <h2><?php esc_html_e( 'Não encontrámos resultados.', 'chavevertical-lite' ); ?></h2>
            <p><?php esc_html_e( 'Experimente outra referência, marca ou descrição.', 'chavevertical-lite' ); ?></p>
            <a class="cvl-button cvl-button-primary" href="<?php echo esc_url( cvl_shop_url() ); ?>"><?php esc_html_e( 'VER CATÁLOGO', 'chavevertical-lite' ); ?></a>
        </div>
    <?php endif; ?>
</div>
<?php get_footer(); ?>
