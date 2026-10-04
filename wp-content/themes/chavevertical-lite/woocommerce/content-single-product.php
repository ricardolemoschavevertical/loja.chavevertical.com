<?php
/**
 * Single product content.
 *
 * Reorganiza apenas o markup visual da ficha, mantendo todos os hooks
 * comerciais nativos do WooCommerce.
 *
 * @version 3.6.0
 */

defined( 'ABSPATH' ) || exit;

global $product;

do_action( 'woocommerce_before_single_product' );

if ( post_password_required() ) {
    echo get_the_password_form(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    return;
}
?>
<div id="product-<?php the_ID(); ?>" <?php wc_product_class( 'cvl-product-detail', $product ); ?>>
    <div class="cvl-product-detail-grid">
        <aside class="cvl-product-gallery-wrap">
            <?php do_action( 'woocommerce_before_single_product_summary' ); ?>

            <?php if ( function_exists( 'cvl_single_product_share' ) ) : ?>
                <?php cvl_single_product_share(); ?>
            <?php endif; ?>
        </aside>

        <section class="summary entry-summary cvl-product-summary">
            <?php do_action( 'woocommerce_single_product_summary' ); ?>
        </section>
    </div>

    <div class="cvl-product-after">
        <?php do_action( 'woocommerce_after_single_product_summary' ); ?>
    </div>
</div>

<?php do_action( 'woocommerce_after_single_product' ); ?>
