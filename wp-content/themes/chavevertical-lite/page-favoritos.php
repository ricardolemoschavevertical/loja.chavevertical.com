<?php
defined( 'ABSPATH' ) || exit;

$embedded = isset( $_GET['cv_embed'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['cv_embed'] ) );

if ( ! $embedded ) {
    get_header();
}

$wishlist_shortcode = '';
if ( shortcode_exists( 'ti_wishlistsview' ) ) {
    $wishlist_shortcode = '[ti_wishlistsview]';
} elseif ( shortcode_exists( 'yith_wcwl_wishlist' ) ) {
    $wishlist_shortcode = '[yith_wcwl_wishlist]';
}
?>
<main class="cvl-favorites-page<?php echo $embedded ? ' is-embedded' : ''; ?>">
    <div class="cvl-shell cvl-favorites-shell">
        <?php if ( ! $embedded ) : ?>
            <nav class="cvl-favorites-breadcrumbs" aria-label="<?php esc_attr_e( 'Breadcrumbs', 'chavevertical-lite' ); ?>">
                <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Início</a>
                <span aria-hidden="true">/</span>
                <span>Favoritos</span>
            </nav>
            <header class="cvl-favorites-heading">
                <span class="cvl-favorites-eyebrow">A SUA SELEÇÃO</span>
                <h1>Favoritos</h1>
                <p>Guarde produtos para consultar, comparar e adicionar ao carrinho mais tarde.</p>
            </header>
        <?php endif; ?>

        <section class="cvl-favorites-content" aria-label="<?php esc_attr_e( 'Produtos favoritos', 'chavevertical-lite' ); ?>">
            <?php if ( $wishlist_shortcode ) : ?>
                <?php echo do_shortcode( $wishlist_shortcode ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php else : ?>
                <div class="cvl-favorites-empty">
                    <span class="cvl-favorites-heart" aria-hidden="true">♡</span>
                    <h2>Os favoritos estão temporariamente indisponíveis</h2>
                    <p>A funcionalidade de lista de desejos não está ativa neste momento.</p>
                    <a class="button" href="<?php echo esc_url( cvl_shop_url() ); ?>">Ver produtos</a>
                </div>
            <?php endif; ?>
        </section>
    </div>
</main>

<style>
.cvl-favorites-page{padding:28px 0 56px;background:#f7f9f9;min-height:52vh}
.cvl-favorites-shell{max-width:none}
.cvl-favorites-breadcrumbs{display:flex;gap:8px;align-items:center;margin:0 0 22px;color:#6b747b;font-size:13px}
.cvl-favorites-breadcrumbs a{color:#007267}
.cvl-favorites-heading{margin-bottom:24px;padding:28px 30px;background:#fff;border:1px solid #e2e7ea;border-radius:4px}
.cvl-favorites-eyebrow{display:block;margin-bottom:7px;color:#007267;font-size:11px;font-weight:800;letter-spacing:.1em}
.cvl-favorites-heading h1{margin:0 0 8px;font-size:clamp(28px,3vw,42px);line-height:1.05}
.cvl-favorites-heading p{margin:0;color:#66727c}
.cvl-favorites-content{padding:24px 30px;background:#fff;border:1px solid #e2e7ea;border-radius:4px}
.cvl-favorites-content table{width:100%}
.cvl-favorites-content img{max-width:110px;height:auto}
.cvl-favorites-content .button,.cvl-favorites-content button,.cvl-favorites-content input[type=submit]{border-radius:3px}
.cvl-favorites-empty{text-align:center;padding:52px 20px}
.cvl-favorites-heart{display:block;margin-bottom:12px;color:#007267;font-size:48px;line-height:1}
.cvl-favorites-empty h2{margin:0 0 8px}
.cvl-favorites-empty p{margin:0 0 20px;color:#66727c}
.cvl-favorites-page.is-embedded{padding:0;background:#fff;min-height:300px}
.cvl-favorites-page.is-embedded .cvl-shell{width:100%;max-width:none}
.cvl-favorites-page.is-embedded .cvl-favorites-content{padding:8px 0;border:0}
@media(max-width:700px){
  .cvl-favorites-page{padding:18px 0 36px}
  .cvl-favorites-heading,.cvl-favorites-content{padding:20px 16px}
  .cvl-favorites-content{overflow-x:auto}
}
</style>
<?php
if ( ! $embedded ) {
    get_footer();
}
