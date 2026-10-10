<?php
/**
 * Read-only checkout fiscal fields smoke test for self-hosted theme deploy.
 *
 * Exercises WooCommerce checkout field registration, conditional validation and
 * in-memory order meta. Does NOT create an order, send email or update options.
 */
declare(strict_types=1);

$root = '/home/chavevertical-loja/htdocs/loja.chavevertical.com';
if ( PHP_SAPI !== 'cli' || ! is_readable( $root . '/wp-load.php' ) ) {
    fwrite( STDERR, "Unexpected environment.\n" );
    exit( 1 );
}

defined( 'WP_ADMIN' ) || define( 'WP_ADMIN', true );
require_once $root . '/wp-load.php';

$host = wp_parse_url( home_url(), PHP_URL_HOST );
$site_host = wp_parse_url( site_url(), PHP_URL_HOST );
if ( 'loja.chavevertical.com' !== $host || 'loja.chavevertical.com' !== $site_host ) {
    fwrite( STDERR, "Wrong WordPress origin.\n" );
    exit( 1 );
}

function cvl_checkout_test_assert( $condition, $label ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: $label\n" );
        exit( 1 );
    }
    echo "PASS: $label\n";
}

cvl_checkout_test_assert( function_exists( 'cvl_checkout_customer_validate' ), 'Custom checkout logic loaded' );

$checkout_page_id = (int) wc_get_page_id( 'checkout' );
$page_content     = $checkout_page_id > 0 ? (string) get_post_field( 'post_content', $checkout_page_id ) : '';
$uses_blocks      = str_contains( $page_content, 'wp:woocommerce/checkout' );
$uses_shortcode   = has_shortcode( $page_content, 'woocommerce_checkout' );

echo 'checkout_page_id=' . $checkout_page_id . "\n";
echo 'checkout_mode=' . ( $uses_blocks ? 'blocks' : ( $uses_shortcode ? 'classic' : 'unknown' ) ) . "\n";
cvl_checkout_test_assert( $uses_shortcode && ! $uses_blocks, 'Classic shortcode checkout is active' );

$fields = apply_filters(
    'woocommerce_checkout_fields',
    array( 'billing' => array( 'billing_company' => array( 'type' => 'text' ) ) )
);
foreach ( array( 'billing_customer_type', 'billing_company', 'billing_nif', 'billing_nipc' ) as $field ) {
    cvl_checkout_test_assert( isset( $fields['billing'][ $field ] ), "Field $field registered" );
}
cvl_checkout_test_assert( 'radio' === $fields['billing']['billing_customer_type']['type'], 'Particular / Empresa selector is radio' );
cvl_checkout_test_assert( 'Nome da empresa' === $fields['billing']['billing_company']['label'], 'Company field uses correct label' );

$personal = array(
    'billing_country'       => 'PT',
    'billing_customer_type' => 'particular',
    'billing_nif'           => '123456789',
    'billing_nipc'          => '509514502',
    'billing_company'       => 'Old Company',
);
$business = array(
    'billing_country'       => 'PT',
    'billing_customer_type' => 'empresa',
    'billing_nif'           => '123456789',
    'billing_nipc'          => 'PT 509 514 502',
    'billing_company'       => 'Chave Vertical Lda',
);

$personal_clean = cvl_checkout_customer_clean_data( $personal );
$business_clean = cvl_checkout_customer_clean_data( $business );
cvl_checkout_test_assert( '' === $personal_clean['billing_nipc'] && '' === $personal_clean['billing_company'], 'Particular clears company data' );
cvl_checkout_test_assert( '' === $business_clean['billing_nif'] && '509514502' === $business_clean['billing_nipc'], 'Empresa clears NIF, normalizes NIPC' );

cvl_checkout_test_assert( cvl_checkout_valid_pt_tax_id( '123456789' ), 'Valid Portuguese personal NIF accepted' );
cvl_checkout_test_assert( cvl_checkout_valid_pt_tax_id( '509514502' ), 'Valid Portuguese company NIPC accepted' );
cvl_checkout_test_assert( ! cvl_checkout_valid_pt_tax_id( '123456780' ), 'Invalid Portuguese NIF rejected' );

foreach ( array( 'particular' => $personal_clean, 'empresa' => $business_clean ) as $label => $data ) {
    $errors = new WP_Error();
    cvl_checkout_customer_validate( $data, $errors );
    cvl_checkout_test_assert( ! $errors->has_errors(), "$label accepted with valid fields" );

    $order = new WC_Order();
    $order->set_billing_company( 'Old Company' );
    cvl_checkout_customer_save_order( $order, $data );
    cvl_checkout_test_assert( $label === $order->get_meta( '_billing_customer_type' ), "$label order type saved" );
    cvl_checkout_test_assert(
        ( 'empresa' === $label ? '509514502' : '123456789' ) === $order->get_meta( 'empresa' === $label ? '_billing_nipc' : '_billing_nif' ),
        "$label order tax ID saved"
    );
    if ( 'particular' === $label ) {
        cvl_checkout_test_assert( '' === $order->get_billing_company(), 'Particular does not inherit company' );
    }
}

$missing_tax = $personal_clean;
$missing_tax['billing_nif'] = '';
$errors = new WP_Error();
cvl_checkout_customer_validate( $missing_tax, $errors );
cvl_checkout_test_assert( $errors->has_errors(), 'Missing NIF blocks checkout' );

$missing_company = $business_clean;
$missing_company['billing_company'] = '';
$errors = new WP_Error();
cvl_checkout_customer_validate( $missing_company, $errors );
cvl_checkout_test_assert( $errors->has_errors(), 'Missing company name blocks checkout' );

$invalid_nipc = $business_clean;
$invalid_nipc['billing_nipc'] = '509514503';
$errors = new WP_Error();
cvl_checkout_customer_validate( $invalid_nipc, $errors );
cvl_checkout_test_assert( $errors->has_errors(), 'Invalid NIPC blocks checkout' );

echo "checkout_fiscal_test_status=ok\n";
