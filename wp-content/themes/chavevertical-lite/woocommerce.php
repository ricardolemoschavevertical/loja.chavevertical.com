<?php
defined( 'ABSPATH' ) || exit;

get_header();

if ( function_exists( 'is_product_category' ) && is_product_category() && function_exists( 'cvl_product_category_search_layout' ) ) {
    cvl_product_category_search_layout();
    get_footer();
    return;
}
?>
<div class="cvl-shell cvl-woocommerce-shell">
    <?php if ( function_exists( 'woocommerce_content' ) ) : ?>
        <?php if ( function_exists( 'is_shop' ) && is_shop() && function_exists( 'cvl_shop_root_category_grid' ) ) : ?>
            <?php
            // A raiz /shop/ é o diretório visual das categorias principais.
            cvl_shop_root_category_grid();
            ?>
        <?php else : ?>
            <?php woocommerce_content(); ?>
        <?php endif; ?>
    <?php else : ?>
        <section class="cvl-empty-state">
            <h1><?php esc_html_e( 'Loja em preparação', 'chavevertical-lite' ); ?></h1>
            <p><?php esc_html_e( 'O WooCommerce ainda não está instalado ou ativo. O tema continua funcional e esta área ficará disponível assim que a loja for ativada.', 'chavevertical-lite' ); ?></p>
        </section>
    <?php endif; ?>
</div>
<?php
get_footer();
