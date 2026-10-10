<?php
/**
 * Read-only postcode unit tests: no WordPress runtime, HTTP requests,
 * orders, email messages, database writes or checkout submissions.
 */
declare(strict_types=1);

defined( 'ABSPATH' ) || define( 'ABSPATH', __DIR__ . '/stub/' );

$GLOBALS['cvl_postcode_test_hooks'] = array();
$GLOBALS['cvl_postcode_test_checkout'] = true;

class WP_Error {
    private array $messages = array();
    public function add( string $code, string $message, array $data = array() ): void {
        $this->messages[ $code ] = $message;
    }
    public function get_error_message( string $code ): string {
        return $this->messages[ $code ] ?? '';
    }
    public function has_errors(): bool {
        return ! empty( $this->messages );
    }
}

function add_filter( string $name, $callback, int $priority = 10, int $accepted_args = 1 ): void {
    $GLOBALS['cvl_postcode_test_hooks'][ $name ][] = $callback;
}
function add_action( string $name, $callback, int $priority = 10, int $accepted_args = 1 ): void {
    $GLOBALS['cvl_postcode_test_hooks'][ $name ][] = $callback;
}
function is_checkout(): bool {
    return $GLOBALS['cvl_postcode_test_checkout'];
}
function check( bool $result, string $title ): void {
    if ( ! $result ) {
        fwrite( STDERR, "FAIL: $title\n" );
        exit( 1 );
    }
    echo "PASS: $title\n";
}

require dirname( __DIR__ ) . '/wp-content/themes/chavevertical-lite/inc/checkout-postcodes.php';

foreach ( array( '6160152', '6160 152', '6160-152', '6160 - 152' ) as $value ) {
    check( '6160-152' === cvl_checkout_normalize_pt_postcode( $value ), "Normalize pasted PT $value" );
}
check( cvl_checkout_valid_pt_postcode( '2500-663' ), 'Official example has valid PT format' );
check( cvl_checkout_valid_pt_postcode( '6160-152' ), 'CTT PT format accepted' );
check( ! cvl_checkout_valid_pt_postcode( '6160152' ), 'Missing hyphen is not canonical' );
check( ! cvl_checkout_valid_pt_postcode( '6160-15' ), 'Short 6-digit postcode rejected' );
check( ! cvl_checkout_valid_pt_postcode( '6160-1522' ), 'Long 8-digit postcode rejected' );
check( ! cvl_checkout_valid_pt_postcode( '6160-15A' ), 'Letters rejected for PT' );
check( ! cvl_checkout_valid_pt_postcode( '6160-152\n' ), 'Trailing junk rejected' );
check( cvl_checkout_normalize_pt_postcode( '6160-A52' ) === '6160-A52', 'Malformed text is not silently altered' );

$dual = array(
    'billing_country' => 'PT',
    'billing_postcode' => '1000001',
    'shipping_country' => 'PT',
    'shipping_postcode' => '2500 663',
    'ship_to_different_address' => 1,
);
$normalized = cvl_checkout_postcodes_normalize_data( $dual );
check( $normalized['billing_postcode'] === '1000-001', 'Billing PT normalizes independently' );
check( $normalized['shipping_postcode'] === '2500-663', 'Delivery PT normalizes independently' );

$foreign = array(
    'billing_country' => 'GB',
    'billing_postcode' => 'SW1A 1AA',
    'shipping_country' => 'FR',
    'shipping_postcode' => '75001',
    'ship_to_different_address' => 1,
);
check( cvl_checkout_postcodes_normalize_data( $foreign ) === $foreign, 'Foreign formats remain unchanged' );

$mixed = $dual;
$mixed['billing_country'] = 'FR';
$mixed['billing_postcode'] = '75001';
$normalized = cvl_checkout_postcodes_normalize_data( $mixed );
check( $normalized['billing_postcode'] === '75001' && $normalized['shipping_postcode'] === '2500-663', 'Billing country does not affect delivery postcode' );

// WordPress checkout filters keep existing required flags and enable WC postcode validation.
$registered = $GLOBALS['cvl_postcode_test_hooks']['woocommerce_checkout_fields'][0] ?? null;
check( is_callable( $registered ), 'Woo checkout fields filter registered' );
$fields = array(
    'billing' => array( 'billing_postcode' => array( 'required' => true, 'validate' => array( 'postcode' ) ) ),
    'shipping' => array( 'shipping_postcode' => array( 'required' => false ) ),
);
$fields = $registered( $fields );
check( $fields['billing']['billing_postcode']['required'] === true, 'Billing required status unchanged' );
check( $fields['shipping']['shipping_postcode']['required'] === false, 'Delivery required status unchanged' );
check( in_array( 'postcode', $fields['shipping']['shipping_postcode']['validate'], true ), 'Shipping validated by WC' );
check( count( $fields['billing']['billing_postcode']['validate'] ) === 1, 'No duplicate WC postcode validators' );

// Native WC_Validation postcode checks, enhanced only for PT.
$validity = $GLOBALS['cvl_postcode_test_hooks']['woocommerce_validate_postcode'][0];
check( $validity( true, '6160-152', 'PT' ), 'Portuguese format allowed' );
check( ! $validity( true, '6160-15A', 'PT' ), 'Portuguese format rejected server-side' );
check( $validity( true, 'SW1A 1AA', 'GB' ), 'UK WC validation result preserved' );
check( ! $validity( false, '75001', 'FR' ), 'Foreign WC rejection preserved' );

$notices = $GLOBALS['cvl_postcode_test_hooks']['woocommerce_checkout_postcode_validation_notice'][0];
check( str_contains( $notices( 'Error', 'PT' ), '1234-567' ), 'Clear error for PT' );
check( $notices( 'Foreign error', 'GB' ) === 'Foreign error', 'Non-PT errors unchanged' );

$validation = 'cvl_checkout_postcodes_validate_data';
$billing_invalid = $dual;
$billing_invalid['billing_postcode'] = '1000-01';
$errors = new WP_Error();
$validation( $billing_invalid, $errors );
check( $errors->get_error_message( 'cvl_billing_postcode_invalid' ) !== '', 'Invalid billing PT postcode blocks checkout' );
check( $errors->get_error_message( 'cvl_shipping_postcode_invalid' ) === '', 'Valid delivery postcode remains independent' );

$shipping_invalid = $dual;
$shipping_invalid['shipping_postcode'] = 'ABC';
$errors = new WP_Error();
$validation( $shipping_invalid, $errors );
check( $errors->get_error_message( 'cvl_shipping_postcode_invalid' ) !== '', 'Invalid separate delivery postcode blocks checkout' );
check( $errors->get_error_message( 'cvl_billing_postcode_invalid' ) === '', 'Valid billing postcode remains independent' );

$shipping_inactive = $shipping_invalid;
$shipping_inactive['ship_to_different_address'] = 0;
$errors = new WP_Error();
$validation( $shipping_inactive, $errors );
check( ! $errors->has_errors(), 'Inactive separate delivery fields never block checkout' );

$shipping_foreign = $shipping_invalid;
$shipping_foreign['shipping_country'] = 'ES';
$errors = new WP_Error();
$validation( $shipping_foreign, $errors );
check( ! $errors->has_errors(), 'Spanish delivery postcode is not rejected by PT validator' );

$native_already_rejected = $billing_invalid;
$errors = new WP_Error();
$errors->add( 'billing_postcode_validation', 'Invalid native postcode' );
$validation( $native_already_rejected, $errors );
check( $errors->get_error_message( 'cvl_billing_postcode_invalid' ) === '', 'No duplicate native postcode notice' );

// External CTT lookup is a link, not a claim of address verification.
$fieldFilter = $GLOBALS['cvl_postcode_test_hooks']['woocommerce_form_field'][0] ?? null;
check( is_callable( $fieldFilter ), 'Woo field helper registered' );
$markup = '<p class="form-row"><label>Postcode</label><span><input></span></p>';
check( str_contains( $fieldFilter( $markup, 'billing_postcode' ), 'ctt.pt' ), 'Billing displays official CTT verification link' );
check( str_contains( $fieldFilter( $markup, 'shipping_postcode' ), 'ctt.pt' ), 'Delivery displays official CTT verification link' );
check( $fieldFilter( $markup, 'billing_city' ) === $markup, 'No helper in unrelated billing fields' );
$GLOBALS['cvl_postcode_test_checkout'] = false;
check( $fieldFilter( $markup, 'billing_postcode' ) === $markup, 'No checkout helper on account address editing' );

echo "checkout_postcode_unit_status=ok\n";
