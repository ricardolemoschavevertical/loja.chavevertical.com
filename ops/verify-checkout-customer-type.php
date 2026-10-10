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
cvl_checkout_test_assert( $uses_shortcode || $uses_blocks, 'Known WooCommerce checkout type is configured' );

if ( $uses_blocks ) {
    echo "NOTICE: checkout is WooCommerce Blocks. Classic NIF/NIPC/VIES fields are not rendered by the Blocks checkout.\n";
    echo "checkout_fiscal_choice_ui=classic_only_not_available_in_blocks\n";
}

cvl_checkout_test_assert( function_exists( 'cvl_checkout_valid_pt_postcode' ), 'CTT postcode helper loaded in live theme' );
cvl_checkout_test_assert( '6160-152' === wc_format_postcode( '6160152', 'PT' ), 'WooCommerce Store API-compatible PT normalization' );
cvl_checkout_test_assert( WC_Validation::is_postcode( '6160-152', 'PT' ), 'WooCommerce PT postcode format accepted' );
cvl_checkout_test_assert( ! WC_Validation::is_postcode( '6160-15A', 'PT' ), 'WooCommerce PT invalid postcode rejected' );
cvl_checkout_test_assert(
    cvl_checkout_valid_pt_postcode( '2500-663' ) && ! cvl_checkout_valid_pt_postcode( '2500-66' ),
    'CTT seven-digit postcode format correctly checked'
);

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

// Official Portuguese ranges: persons 1–4; RNPC NIPC 5, 6, 8, 9.
// Portuguese fiscal IDs starting with 7 are assigned by AT to other entities.
function cvl_checkout_test_make_valid_id( string $first_eight ): string {
    cvl_checkout_test_assert( (bool) preg_match( '/^[0-9]{8}$/D', $first_eight ), 'Test ID seed has 8 digits' );
    $sum = 0;
    for ( $i = 0; $i < 8; $i++ ) {
        $sum += (int) $first_eight[$i] * ( 9 - $i );
    }
    $check = 11 - ( $sum % 11 );
    return $first_eight . (string) ( $check >= 10 ? 0 : $check );
}

foreach ( array( '1', '2', '3', '4' ) as $prefix ) {
    $tax = cvl_checkout_test_make_valid_id( $prefix . '1234567' );
    cvl_checkout_test_assert( 'singular' === cvl_checkout_pt_tax_id_kind( $tax ), "Personal prefix $prefix classified correctly" );
    cvl_checkout_test_assert( cvl_checkout_tax_kind_allowed( cvl_checkout_pt_tax_id_kind( $tax ), 'particular' ), "Personal prefix $prefix accepted in Particular" );
    cvl_checkout_test_assert( ! cvl_checkout_tax_kind_allowed( cvl_checkout_pt_tax_id_kind( $tax ), 'empresa' ), "Personal prefix $prefix rejected as NIPC" );
}
$nonresident_singular = cvl_checkout_test_make_valid_id( '45123456' );
cvl_checkout_test_assert( 'singular' === cvl_checkout_pt_tax_id_kind( $nonresident_singular ), 'Personal 45 range accepted' );

foreach ( array( '5', '6', '8', '9' ) as $prefix ) {
    $tax = cvl_checkout_test_make_valid_id( $prefix . '1234567' );
    cvl_checkout_test_assert( 'nipc' === cvl_checkout_pt_tax_id_kind( $tax ), "RNPC NIPC prefix $prefix classified" );
    cvl_checkout_test_assert( cvl_checkout_tax_kind_allowed( cvl_checkout_pt_tax_id_kind( $tax ), 'empresa' ), "NIPC prefix $prefix accepted in Empresa" );
    cvl_checkout_test_assert( ! cvl_checkout_tax_kind_allowed( cvl_checkout_pt_tax_id_kind( $tax ), 'particular' ), "NIPC prefix $prefix rejected for Particular" );
}

$entity_seven = cvl_checkout_test_make_valid_id( '71234567' );
cvl_checkout_test_assert( 'entidade_at' === cvl_checkout_pt_tax_id_kind( $entity_seven ), 'AT entity prefix 7 recognised separately from NIPC' );
cvl_checkout_test_assert( cvl_checkout_tax_kind_allowed( cvl_checkout_pt_tax_id_kind( $entity_seven ), 'empresa' ), 'AT entity NIF accepted for Empresa' );
cvl_checkout_test_assert( 'NIF da entidade' === cvl_checkout_company_tax_label( $entity_seven, 'PT' ), 'AT entity NIF correctly labelled on documents' );
cvl_checkout_test_assert( 'N.º fiscal / VAT' === cvl_checkout_company_tax_label( 'ESB12345678', 'ES' ), 'Foreign identifier correctly labelled on documents' );
cvl_checkout_test_assert( ! cvl_checkout_valid_pt_tax_id( cvl_checkout_test_make_valid_id( '01234567' ) ), 'Prefix 0 rejected' );
cvl_checkout_test_assert( '' === cvl_checkout_pt_tax_id_kind( '509514503' ), 'Bad check digit rejected' );


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


$wrong_personal_prefix = $personal_clean;
$wrong_personal_prefix['billing_nif'] = '509514502';
$errors = new WP_Error();
cvl_checkout_customer_validate( $wrong_personal_prefix, $errors );
cvl_checkout_test_assert( $errors->get_error_code() === 'billing_nif_wrong_kind', 'Company NIPC blocked in Particular' );

$wrong_business_prefix = $business_clean;
$wrong_business_prefix['billing_nipc'] = '123456789';
$errors = new WP_Error();
cvl_checkout_customer_validate( $wrong_business_prefix, $errors );
cvl_checkout_test_assert( $errors->get_error_code() === 'billing_nipc_wrong_kind', 'Personal NIF blocked as company NIPC' );

$special_entity = $business_clean;
$special_entity['billing_nipc'] = $entity_seven;
$errors = new WP_Error();
cvl_checkout_customer_validate( $special_entity, $errors );
cvl_checkout_test_assert( ! $errors->has_errors(), 'AT entity NIF 7 accepted in Empresa' );

$foreign_business = $business_clean;
$foreign_business['billing_country'] = 'ES';
$foreign_business['billing_nipc'] = 'ESB12345678';
$errors = new WP_Error();
cvl_checkout_customer_validate( $foreign_business, $errors );
cvl_checkout_test_assert( ! $errors->has_errors(), 'Foreign VAT number does not use Portuguese checksum' );

$foreign_pt_invalid = $business_clean;
$foreign_pt_invalid['billing_country'] = 'ES';
$foreign_pt_invalid['billing_nipc'] = 'PT 509 514 503';
$foreign_pt_invalid = cvl_checkout_customer_clean_data( $foreign_pt_invalid );
$errors = new WP_Error();
cvl_checkout_customer_validate( $foreign_pt_invalid, $errors );
cvl_checkout_test_assert( $errors->has_errors(), 'Portuguese VAT with foreign billing still checked as Portuguese' );

$pt_with_country_abroad = $business_clean;
$pt_with_country_abroad['billing_country'] = 'FR';
$pt_with_country_abroad = cvl_checkout_customer_clean_data( $pt_with_country_abroad );
cvl_checkout_test_assert( 'PT509514502' === $pt_with_country_abroad['billing_nipc'], 'Portuguese VAT prefix retained for foreign billing address so checksum cannot be bypassed' );

echo "checkout_fiscal_test_status=ok\n";
