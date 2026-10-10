<?php
/**
 * Chave Vertical — move billing + fiscal info BEFORE shipping in Woo Blocks.
 *
 * Guarded one-time WordPress page edit, optional --apply; saves a backup in
 * postmeta and ordinary WordPress revisions. Does not change field values,
 * payment blocks, shipping methods, checkout settings, customer or order data.
 *
 * Expected old top-level checkout fields:
 * contact -> shipping selection -> shipping address -> billing -> fiscal ->
 * shipping methods -> payment.
 *
 * New ordering:
 * contact -> billing -> fiscal & same-for-shipping -> shipping selection ->
 * shipping address -> shipping methods -> payment.
 */
declare(strict_types=1);

if ( 'cli' !== PHP_SAPI ) {
    exit( 1 );
}
$root = '/home/chavevertical-loja/htdocs/loja.chavevertical.com';
if ( ! is_readable( $root . '/wp-load.php' ) ) {
    fwrite( STDERR, "WordPress checkout origin not available.\n" );
    exit( 1 );
}
require_once $root . '/wp-load.php';
if ( 'loja.chavevertical.com' !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
    fwrite( STDERR, "Wrong WordPress host.\n" );
    exit( 1 );
}
if ( ! function_exists( 'wc_get_page_id' ) ) {
    fwrite( STDERR, "WooCommerce not active.\n" );
    exit( 1 );
}

$id = (int) wc_get_page_id( 'checkout' );
$post = $id ? get_post( $id ) : null;
if ( ! $post || 'page' !== $post->post_type || 'publish' !== $post->post_status ) {
    fwrite( STDERR, "Checkout page missing or unpublished.\n" );
    exit( 1 );
}
$original = (string) $post->post_content;

$blocks = array(
    'contact' => 'woocommerce/checkout-contact-information-block',
    'billing' => 'woocommerce/checkout-billing-address-block',
    'fiscal' => 'woocommerce/checkout-additional-information-block',
    'shipping' => 'woocommerce/checkout-shipping-address-block',
    'shipping_pick' => 'woocommerce/checkout-shipping-method-block',
    'payment' => 'woocommerce/checkout-payment-block',
);

$opening = array();
$closing = array();
foreach ( $blocks as $name => $block ) {
    $opening[ $name ] = '<!-- wp:' . $block . ' -->';
    $closing[ $name ] = '<!-- /wp:' . $block . ' -->';
    if ( substr_count( $original, $opening[ $name ] ) !== 1
         || substr_count( $original, $closing[ $name ] ) !== 1 ) {
        fwrite( STDERR, "Checkout block markup unexpected: $block\n" );
        exit( 1 );
    }
}

$pos = array();
foreach ( $opening as $name => $tag ) {
    $pos[$name] = strpos( $original, $tag );
}

// Already configured (idempotency), including manually positioned pages.
if (
    $pos['contact'] < $pos['billing']
    && $pos['billing'] < $pos['fiscal']
    && $pos['fiscal'] < $pos['shipping_pick']
    && $pos['shipping_pick'] < $pos['shipping']
    && $pos['shipping'] < $pos['payment']
) {
    echo "checkout_billing_first=already_applied\n";
    exit( 0 );
}

if (
    !( $pos['contact'] < $pos['shipping_pick'] )
    || !( $pos['shipping_pick'] < $pos['shipping'] )
    || !( $pos['shipping'] < $pos['billing'] )
    || !( $pos['billing'] < $pos['fiscal'] )
    || !( $pos['fiscal'] < $pos['payment'] )
) {
    fwrite( STDERR, "Checkout block positions are not the approved original; aborting.\n" );
    exit( 1 );
}

$billing_start = $pos['billing'];
$fiscal_start = $pos['fiscal'];
$billing_end = strpos( $original, $closing['billing'], $billing_start ) + strlen( $closing['billing'] );
$fiscal_end = strpos( $original, $closing['fiscal'], $fiscal_start ) + strlen( $closing['fiscal'] );
$contact_end = strpos( $original, $closing['contact'], $pos['contact'] ) + strlen( $closing['contact'] );

if ( $billing_start >= $billing_end
    || $billing_end >= $fiscal_start
    || $fiscal_start >= $fiscal_end
    || '' !== trim( substr( $original, $billing_end, $fiscal_start - $billing_end ) )
) {
    fwrite( STDERR, "Billing and fiscal checkout sections are not contiguous.\n" );
    exit( 1 );
}

$move = substr( $original, $billing_start, $fiscal_end - $billing_start );
$without = substr( $original, 0, $billing_start ) . substr( $original, $fiscal_end );
if ( substr_count( $without, $opening['billing'] ) || substr_count( $without, $opening['fiscal'] ) ) {
    fwrite( STDERR, "Duplicate billing/fiscal blocks in checkout.\n" );
    exit( 1 );
}
$insert_pos = strpos( $without, $closing['contact'] ) + strlen( $closing['contact'] );
$new = substr( $without, 0, $insert_pos ) . "\n" . $move . "\n" . substr( $without, $insert_pos );

foreach ( $blocks as $name => $block ) {
    if ( substr_count( $new, $opening[$name] ) !== 1 || substr_count( $new, $closing[$name] ) !== 1 ) {
        fwrite( STDERR, "Unexpected number of checkout blocks in proposed page.\n" );
        exit( 1 );
    }
}
$new_positions = array();
foreach ( $opening as $name => $tag ) {
    $new_positions[$name] = strpos( $new, $tag );
}
if ( ! (
    $new_positions['contact'] < $new_positions['billing']
    && $new_positions['billing'] < $new_positions['fiscal']
    && $new_positions['fiscal'] < $new_positions['shipping_pick']
    && $new_positions['shipping_pick'] < $new_positions['shipping']
    && $new_positions['shipping'] < $new_positions['payment']
) ) {
    fwrite( STDERR, "Checkout layout changed beyond the approved order.\n" );
    exit( 1 );
}

$parsed = parse_blocks( $new );
if ( count( $parsed ) < 1 || 'woocommerce/checkout' !== ( $parsed[0]['blockName'] ?? '' ) ) {
    fwrite( STDERR, "New checkout blocks are not valid.\n" );
    exit( 1 );
}
echo 'checkout_billing_first_target=' . implode( ',', array_keys( $new_positions ) ) . "\n";
echo 'checkout_page_id=' . $id . "\n";

if ( ! in_array( '--apply', $argv, true ) ) {
    echo "checkout_billing_first=dry_run_ok\n";
    exit( 0 );
}
$backup_key = '_cvl_checkout_billing_first_original_markup';
if ( '' === (string) get_post_meta( $id, $backup_key, true ) ) {
    update_post_meta( $id, $backup_key, $original );
}
$saved = wp_update_post( array( 'ID' => $id, 'post_content' => $new ), true );
if ( is_wp_error( $saved ) || (int) $saved !== $id ) {
    fwrite( STDERR, "Unable to apply checkout order. Backup preserved.\n" );
    exit( 1 );
}
clean_post_cache( $id );
if ( (string) get_post_field( 'post_content', $id ) !== $new ) {
    fwrite( STDERR, "Checkout page markup differs from approved value.\n" );
    exit( 1 );
}
update_post_meta( $id, '_cvl_checkout_billing_first_applied', current_time( 'mysql' ) );
echo "checkout_billing_first=applied\n";
