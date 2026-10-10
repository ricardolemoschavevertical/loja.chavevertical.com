<?php
/**
 * Move fiscal customer-type and NIF/NIPC section before Billing Address.
 *
 * WP CLI only; guard current checkout structure, backup once, and preserve
 * checkout shipping/payment blocks exactly. Dry run unless --apply is given.
 *
 * Expected now: Contact -> Billing -> Fiscal -> Shipping method -> Shipping
 * Expected after: Contact -> Fiscal -> Billing -> Shipping method -> Shipping
 */
declare(strict_types=1);

if ( PHP_SAPI !== 'cli' ) {
    exit( 1 );
}
$root = '/home/chavevertical-loja/htdocs/loja.chavevertical.com';
if ( ! is_readable( $root . '/wp-load.php' ) ) {
    fwrite( STDERR, "WordPress origin not found.\n" );
    exit( 1 );
}
require_once $root . '/wp-load.php';
if ( 'loja.chavevertical.com' !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
    fwrite( STDERR, "Wrong WordPress origin.\n" );
    exit( 1 );
}
$page_id = (int) wc_get_page_id( 'checkout' );
$post = $page_id ? get_post( $page_id ) : null;
if ( ! $post || 'page' !== $post->post_type || 'publish' !== $post->post_status ) {
    fwrite( STDERR, "Checkout page missing or unpublished.\n" );
    exit( 1 );
}
$original = (string) $post->post_content;
$names = array(
    'contact' => 'woocommerce/checkout-contact-information-block',
    'billing' => 'woocommerce/checkout-billing-address-block',
    'fiscal' => 'woocommerce/checkout-additional-information-block',
    'shipping_method' => 'woocommerce/checkout-shipping-method-block',
    'shipping' => 'woocommerce/checkout-shipping-address-block',
    'payment' => 'woocommerce/checkout-payment-block',
);
$start = array();
$end = array();
foreach ( $names as $name => $block_name ) {
    $open = '<!-- wp:' . $block_name . ' -->';
    $close = '<!-- /wp:' . $block_name . ' -->';
    if ( 1 !== substr_count( $original, $open ) || 1 !== substr_count( $original, $close ) ) {
        fwrite( STDERR, "Ambiguous checkout block: $block_name\n" );
        exit( 1 );
    }
    $start[ $name ] = strpos( $original, $open );
    $end[ $name ] = strpos( $original, $close, $start[ $name ] ) + strlen( $close );
}

if (
    $start['contact'] < $start['fiscal']
    && $start['fiscal'] < $start['billing']
    && $start['billing'] < $start['shipping_method']
    && $start['shipping_method'] < $start['shipping']
    && $start['shipping'] < $start['payment']
) {
    echo "checkout_customer_type_before_billing=already_applied\n";
    exit( 0 );
}
if ( ! (
    $start['contact'] < $start['billing']
    && $start['billing'] < $start['fiscal']
    && $start['fiscal'] < $start['shipping_method']
    && $start['shipping_method'] < $start['shipping']
    && $start['shipping'] < $start['payment']
    && '' === trim( substr( $original, $end['billing'], $start['fiscal'] - $end['billing'] ) )
) ) {
    fwrite( STDERR, "Checkout changed since the last approved layout; no write.\n" );
    exit( 1 );
}

$fiscal_markup = substr( $original, $start['fiscal'], $end['fiscal'] - $start['fiscal'] );
$without = substr( $original, 0, $start['fiscal'] ) . substr( $original, $end['fiscal'] );
$billing_start = strpos( $without, '<!-- wp:' . $names['billing'] . ' -->' );
if ( false === $billing_start ) {
    fwrite( STDERR, "Billing marker missing after extracting fiscal block.\n" );
    exit( 1 );
}
$new = substr( $without, 0, $billing_start )
    . $fiscal_markup . "\n"
    . substr( $without, $billing_start );

$positions = array();
foreach ( $names as $name => $block_name ) {
    $open = '<!-- wp:' . $block_name . ' -->';
    $close = '<!-- /wp:' . $block_name . ' -->';
    if ( 1 !== substr_count( $new, $open ) || 1 !== substr_count( $new, $close ) ) {
        fwrite( STDERR, "Checkout lost or duplicated blocks: $block_name\n" );
        exit( 1 );
    }
    $positions[ $name ] = strpos( $new, $open );
}
if ( ! (
    $positions['contact'] < $positions['fiscal']
    && $positions['fiscal'] < $positions['billing']
    && $positions['billing'] < $positions['shipping_method']
    && $positions['shipping_method'] < $positions['shipping']
    && $positions['shipping'] < $positions['payment']
) ) {
    fwrite( STDERR, "Proposed block order invalid.\n" );
    exit( 1 );
}
$parsed = parse_blocks( $new );
if ( ! isset( $parsed[0]['blockName'] ) || 'woocommerce/checkout' !== $parsed[0]['blockName'] ) {
    fwrite( STDERR, "Proposed page is no longer a WooCommerce Checkout Block.\n" );
    exit( 1 );
}

echo "checkout_fiscal_layout_target=contact,fiscal,billing,shipping_method,shipping,payment\n";
if ( ! in_array( '--apply', $argv, true ) ) {
    echo "checkout_customer_type_before_billing=dry_run_ok\n";
    exit( 0 );
}

$backup_key = '_cvl_checkout_fiscal_before_billing_backup';
if ( '' === (string) get_post_meta( $page_id, $backup_key, true ) ) {
    update_post_meta( $page_id, $backup_key, $original );
}
$result = wp_update_post( array( 'ID' => $page_id, 'post_content' => $new ), true );
if ( is_wp_error( $result ) || (int) $result !== $page_id ) {
    fwrite( STDERR, "Failed to save checkout layout; previous content backed up.\n" );
    exit( 1 );
}
clean_post_cache( $page_id );
if ( (string) get_post_field( 'post_content', $page_id ) !== $new ) {
    fwrite( STDERR, "Checkout page did not persist exact approved markup.\n" );
    exit( 1 );
}
echo "checkout_customer_type_before_billing=applied\n";
