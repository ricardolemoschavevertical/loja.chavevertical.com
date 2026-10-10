<?php
/**
 * Pure-PHP unit tests for the Chave Vertical checkout fiscal validator.
 * No WordPress database, orders, emails, gateway requests or writes.
 */
declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/stub/' );
function add_action() {}
function add_filter() {}
function sanitize_key( $value ): string {
    return preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $value ) );
}
function sanitize_text_field( $value ): string {
    return trim( (string) $value );
}

class WP_Error {
    private array $errors = array();
    public function add( $code, $message, $data = array() ): void {
        $this->errors[ $code ] = $message;
    }
    public function has_errors(): bool {
        return (bool) $this->errors;
    }
    public function get_error_code(): string {
        return (string) array_key_first( $this->errors );
    }
}

require dirname( __DIR__ ) . '/wp-content/themes/chavevertical-lite/inc/checkout-customer-type.php';

function check( $assertion, string $label ): void {
    if ( ! $assertion ) {
        fwrite( STDERR, "FAIL: $label\n" );
        exit( 1 );
    }
    echo "PASS: $label\n";
}

function make_valid( string $eight ): string {
    check( (bool) preg_match( '/^[0-9]{8}$/D', $eight ), 'Seed has 8 digits' );
    $sum = 0;
    for ( $i = 0; $i < 8; $i++ ) {
        $sum += (int) $eight[ $i ] * ( 9 - $i );
    }
    $digit = 11 - ( $sum % 11 );
    return $eight . (string) ( $digit >= 10 ? 0 : $digit );
}

foreach ( array( '1', '2', '3', '4' ) as $prefix ) {
    $n = make_valid( $prefix . '1234567' );
    check( 'singular' === cvl_checkout_pt_tax_id_kind( $n ), "NIF particular $prefix" );
    check( cvl_checkout_tax_kind_allowed( cvl_checkout_pt_tax_id_kind( $n ), 'particular' ), 'NIF allowed for particular' );
    check( ! cvl_checkout_tax_kind_allowed( cvl_checkout_pt_tax_id_kind( $n ), 'empresa' ), 'Personal NIF not accepted as NIPC' );
}
check( 'singular' === cvl_checkout_pt_tax_id_kind( make_valid( '45123456' ) ), 'Special nonresident NIF starting 45' );

foreach ( array( '5', '6', '8', '9' ) as $prefix ) {
    $n = make_valid( $prefix . '1234567' );
    check( 'nipc' === cvl_checkout_pt_tax_id_kind( $n ), "NIPC $prefix" );
    check( cvl_checkout_tax_kind_allowed( cvl_checkout_pt_tax_id_kind( $n ), 'empresa' ), 'NIPC allowed for empresa' );
    check( ! cvl_checkout_tax_kind_allowed( cvl_checkout_pt_tax_id_kind( $n ), 'particular' ), 'NIPC rejected for particular' );
}
$entidade7 = make_valid( '71234567' );
check( 'entidade_at' === cvl_checkout_pt_tax_id_kind( $entidade7 ), 'AT entity NIF starting 7' );
check( cvl_checkout_tax_kind_allowed( cvl_checkout_pt_tax_id_kind( $entidade7 ), 'empresa' ), 'AT entity NIF accepted under company' );
check( 'NIF da entidade' === cvl_checkout_company_tax_label( $entidade7, 'PT' ), 'AT 7 labelled as NIF, not NIPC' );

check( cvl_checkout_valid_pt_tax_id( '123456789' ), 'Personal reference checksum correct' );
check( cvl_checkout_valid_pt_tax_id( 'PT 509-514-502' ), 'NIPC accepts separators and PT prefix' );
check( ! cvl_checkout_valid_pt_tax_id( '509514503' ), 'Wrong checksum rejected' );
check( ! cvl_checkout_valid_pt_tax_id( make_valid( '01234567' ) ), 'Disallowed first digit 0 rejected' );

$personal = array( 'billing_country' => 'PT', 'billing_customer_type' => 'particular', 'billing_nif' => '123456789', 'billing_nipc' => '', 'billing_company' => '' );
$business = array( 'billing_country' => 'PT', 'billing_customer_type' => 'empresa', 'billing_nif' => '', 'billing_nipc' => 'PT 509-514-502', 'billing_company' => 'Chave Vertical Lda' );

foreach ( array( 'particular' => $personal, 'empresa' => $business ) as $name => $data ) {
    $clean = cvl_checkout_customer_clean_data( $data );
    $errors = new WP_Error();
    cvl_checkout_customer_validate( $clean, $errors );
    check( ! $errors->has_errors(), "$name accepted when correct" );
}

$wrong_personal = $personal;
$wrong_personal['billing_nif'] = '509514502';
$errors = new WP_Error();
cvl_checkout_customer_validate( $wrong_personal, $errors );
check( 'billing_nif_wrong_kind' === $errors->get_error_code(), 'Company NIPC rejected for individual' );

$wrong_company = $business;
$wrong_company['billing_nipc'] = '123456789';
$errors = new WP_Error();
cvl_checkout_customer_validate( $wrong_company, $errors );
check( 'billing_nipc_wrong_kind' === $errors->get_error_code(), 'Personal NIF rejected for company' );

$missing_company = $business;
$missing_company['billing_company'] = '';
$errors = new WP_Error();
cvl_checkout_customer_validate( $missing_company, $errors );
check( $errors->has_errors(), 'Company name required' );

$foreign_company = $business;
$foreign_company['billing_country'] = 'ES';
$foreign_company['billing_nipc'] = 'ESB12345678';
$errors = new WP_Error();
cvl_checkout_customer_validate( $foreign_company, $errors );
check( ! $errors->has_errors(), 'Non-PT VAT not checked with Portuguese algorithm' );

$pt_id_from_es = $business;
$pt_id_from_es['billing_country'] = 'ES';
$pt_id_from_es['billing_nipc'] = 'PT509514503';
$errors = new WP_Error();
cvl_checkout_customer_validate( $pt_id_from_es, $errors );
check( $errors->has_errors(), 'Incorrect PT VAT still rejected for foreign billing' );

$malformed_pt = $business;
$malformed_pt['billing_country'] = 'ES';
$malformed_pt['billing_nipc'] = 'PTX12345678';
$errors = new WP_Error();
cvl_checkout_customer_validate( $malformed_pt, $errors );
check( $errors->has_errors(), 'Malformed Portuguese prefix not treated as foreign VAT' );

$cross_border_pt_invalid = $business;
$cross_border_pt_invalid['billing_country'] = 'ES';
$cross_border_pt_invalid['billing_nipc'] = 'PT 509 514 503';
$cross_border_pt_invalid = cvl_checkout_customer_clean_data( $cross_border_pt_invalid );
check( $cross_border_pt_invalid['billing_nipc'] === 'PT509514503', 'Cross-border PT VAT prefix retained during sanitization' );
$errors = new WP_Error();
cvl_checkout_customer_validate( $cross_border_pt_invalid, $errors );
check( $errors->has_errors(), 'Invalid cross-border Portuguese VAT blocked after sanitization' );

$cross_border_pt_valid = $business;
$cross_border_pt_valid['billing_country'] = 'FR';
$cross_border_pt_valid['billing_nipc'] = 'PT 509 514 502';
$cross_border_pt_valid = cvl_checkout_customer_clean_data( $cross_border_pt_valid );
check( $cross_border_pt_valid['billing_nipc'] === 'PT509514502', 'Valid cross-border PT VAT prefix preserved' );
$errors = new WP_Error();
cvl_checkout_customer_validate( $cross_border_pt_valid, $errors );
check( ! $errors->has_errors(), 'Valid cross-border Portuguese VAT accepted' );

echo "checkout_tax_unit_status=ok\n";
