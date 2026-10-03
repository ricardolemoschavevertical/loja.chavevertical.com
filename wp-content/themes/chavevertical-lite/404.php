<?php
defined( 'ABSPATH' ) || exit;
get_header();
?>
<section class="cvl-shell cvl-error-page">
    <span>404</span>
    <h1><?php esc_html_e( 'Página não encontrada', 'chavevertical-lite' ); ?></h1>
    <p><?php esc_html_e( 'A página que procura foi movida, removida ou o endereço está incorreto.', 'chavevertical-lite' ); ?></p>
    <div class="cvl-hero-actions">
        <a class="cvl-button cvl-button-primary" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'PÁGINA INICIAL', 'chavevertical-lite' ); ?></a>
        <a class="cvl-button" href="<?php echo esc_url( cvl_shop_url() ); ?>"><?php esc_html_e( 'VER PRODUTOS', 'chavevertical-lite' ); ?></a>
    </div>
</section>
<?php get_footer(); ?>
