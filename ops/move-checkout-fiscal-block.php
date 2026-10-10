<?php
/**
 * Move WooCommerce Blocks "Additional information" (fiscal fields) directly
 * below "Billing Address", instead of after the payment section.
 *
 * This is a ONE-TIME guarded WordPress content edit: it never replaces the
 * checkout, deletes a block, changes gateways, or touches any order/customer.
 * Default is dry-run. Pass --apply for the single approved placement.
 */
declare(strict_types=1);

if ( PHP_SAPI !== 'cli' ) {
    exit( 1 );
}
$root = '/home/chavevertical-loja/htdocs/loja.chavevertical.com';
if ( ! is_readable( $root . '/wp-load.php' ) ) {
    fwrite( STDERR, "Cannot access expected store.\n" );
    exit( 1 );
}
require_once $root . '/wp-load.php';

$host = wp_parse_url( home_url(), PHP_URL_HOST );
if ( 'loja.chavevertical.com' !== $host ) {
    fwrite( STDERR, "Wrong WooCommerce store origin.\n" );
    exit( 1 );
}

$checkout_id = (int) wc_get_page_id( 'checkout' );
$post = $checkout_id ? get_post( $checkout_id ) : null;
if ( ! $post || 'page' !== $post->post_type || 'publish' !== $post->post_status ) {
    fwrite( STDERR, "Checkout page missing or not published.\n" );
    exit( 1 );
}

$original = (string) $post->post_content;
$names = array(
    'billing' => 'woocommerce/checkout-billing-address-block',
    'fiscal'  => 'woocommerce/checkout-additional-information-block',
    'payment' => 'woocommerce/checkout-payment-block',
);
$open = '<!-- wp:' . $names['fiscal'] . ' -->';
$close = '<!-- /wp:' . $names['fiscal'] . ' -->';
$billing_close = '<!-- /wp:' . $names['billing'] . ' -->';
$payment_open = '<!-- wp:' . $names['payment'] . ' -->';

foreach ( array( $open, $close, $billing_close, $payment_open ) as $needle ) {
    if ( 1 !== substr_count( $original, $needle ) ) {
        fwrite( STDERR, "Unexpected or duplicate checkout block markup: $needle\n" );
        exit( 1 );
    }
}

$start = strpos( $original, $open );
$end = strpos( $original, $close, $start ) + strlen( $close );
$bill_end = strpos( $original, $billing_close ) + strlen( $billing_close );
$payment_start = strpos( $original, $payment_open );

if ( $start < $bill_end ) {
    fwrite( STDERR, "Fiscal block was moved outside the expected position; aborting.\n" );
    exit( 1 );
}

$already_after_billing = trim( substr( $original, $bill_end, $start - $bill_end ) ) === '';
if ( $already_after_billing || get_post_meta( $checkout_id, '_cvl_checkout_fiscal_moved_once', true ) ) {
    echo "fiscal_block_position=already_prepared\n";
    exit( 0 );
}
if ( $payment_start <= $bill_end || $start <= $payment_start ) {
    fwrite( STDERR, "Checkout structure differs from the approved original; aborting.\n" );
    exit( 1 );
}

// Extract exactly one complete Gutenberg block, preserving its inner HTML.
$fiscal_markup = substr( $original, $start, $end - $start );
$without = substr( $original, 0, $start ) . substr( $original, $end );
$insert_after = strpos( $without, $billing_close ) + strlen( $billing_close );
$new_markup = substr( $without, 0, $insert_after )
    . "\n" . $fiscal_markup
    . substr( $without, $insert_after );

// Strictly assert no block disappeared, no additional block was duplicated.
foreach ( $names as $name ) {
    if ( substr_count( $original, '<!-- wp:' . $name . ' -->' )
        !== substr_count( $new_markup, '<!-- wp:' . $name . ' -->' ) ) {
        fwrite( STDERR, "Unexpected checkout block count changed.\n" );
        exit( 1 );
    }
}
if (
    ! str_contains( $new_markup, $billing_close . "\n" . $open )
    || strpos( $new_markup, $open ) > strpos( $new_markup, $payment_open )
    || strlen( $new_markup ) !== strlen( $original ) + 1
) {
    fwrite( STDERR, "New Gutenberg checkout block position did not validate.\n" );
    exit( 1 );
}

$parse = parse_blocks( $new_markup );
if ( count( $parse ) < 1 || 'woocommerce/checkout' !== ( $parse[0]['blockName'] ?? '' ) ) {
    fwrite( STDERR, "New checkout page cannot be parsed as WooCommerce Blocks.\n" );
    exit( 1 );
}

echo 'checkout_page_id=' . $checkout_id . "\n";
echo 'checkout_original_bytes=' . strlen( $original ) . "\n";
echo 'checkout_new_bytes=' . strlen( $new_markup ) . "\n";
echo "fiscal_block_target=immediately_after_billing_address\n";

if ( ! in_array( '--apply', $argv, true ) ) {
    echo "checkout_fiscal_placement=dry_run_ok\n";
    exit( 0 );
}

// One-time backup in custom meta, plus the standard WP page revision.
if ( '' === (string) get_post_meta( $checkout_id, '_cvl_checkout_fiscal_original_markup', true ) ) {
    update_post_meta( $checkout_id, '_cvl_checkout_fiscal_original_markup', $original );
}

$result = wp_update_post(
    array(
        'ID' => $checkout_id,
        'post_content' => $new_markup,
    ),
    true
);
if ( is_wp_error( $result ) || (int) $result !== $checkout_id ) {
    fwrite( STDERR, "Could not move checkout fiscal block.\n" );
    exit( 1 );
}

update_post_meta( $checkout_id, '_cvl_checkout_fiscal_moved_once', 'yes' );
clean_post_cache( $checkout_id );

$confirmed = (string) get_post_field( 'post_content', $checkout_id );
if ( $confirmed !== $new_markup ) {
    fwrite( STDERR, "Checkout page was not saved exactly as intended.\n" );
    exit( 1 );
}
echo "checkout_fiscal_placement=applied\n";
