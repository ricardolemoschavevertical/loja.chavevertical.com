<?php
/**
 * Product brand archive.
 */

defined( 'ABSPATH' ) || exit;

get_header();

if ( function_exists( 'cvl_product_brand_search_layout' ) ) {
    cvl_product_brand_search_layout();
} elseif ( function_exists( 'woocommerce_content' ) ) {
    echo '<div class="cvl-shell cvl-woocommerce-shell">';
    woocommerce_content();
    echo '</div>';
}

get_footer();
