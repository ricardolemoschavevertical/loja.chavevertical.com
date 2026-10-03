<?php
defined( 'ABSPATH' ) || exit;

get_header();
?>
<div class="cvl-shell cvl-woocommerce-shell">
    <?php if ( function_exists( 'woocommerce_content' ) ) : ?>
        <?php woocommerce_content(); ?>
    <?php else : ?>
        <section class="cvl-empty-state">
            <h1><?php esc_html_e( 'Loja em preparação', 'chavevertical-lite' ); ?></h1>
            <p><?php esc_html_e( 'O WooCommerce ainda não está instalado ou ativo. O tema continua funcional e esta área ficará disponível assim que a loja for ativada.', 'chavevertical-lite' ); ?></p>
        </section>
    <?php endif; ?>
</div>
<?php
get_footer();
