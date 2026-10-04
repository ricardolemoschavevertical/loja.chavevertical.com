<?php
defined( 'ABSPATH' ) || exit;

get_header();
?>
<div class="cvl-shell cvl-woocommerce-shell">
    <?php if ( function_exists( 'woocommerce_content' ) ) : ?>
        <?php if ( function_exists( 'is_product_category' ) && is_product_category() && function_exists( 'cvl_backup_category_layout_open' ) && function_exists( 'cvl_backup_category_layout_close' ) ) : ?>
            <?php cvl_backup_category_layout_open(); ?>
            <?php
            $cvl_has_subcategories = function_exists( 'cvl_backup_category_subcategory_grid' )
                ? cvl_backup_category_subcategory_grid()
                : false;
            ?>
            <?php if ( ! $cvl_has_subcategories ) : ?>
                <?php woocommerce_content(); ?>
            <?php endif; ?>
            <?php cvl_backup_category_layout_close(); ?>
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
