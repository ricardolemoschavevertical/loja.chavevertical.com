<?php
/**
 * Isolated WooCommerce Checkout Blocks billing fields tests.
 *
 * No network, orders, plugins, DB, Woo live sessions or payment requests.
 */
declare(strict_types=1);

defined( 'ABSPATH' ) || define( 'ABSPATH', '/tmp/cv-test/' );

$GLOBALS['cvl_hooks'] = array();
$GLOBALS['cvl_additional_fields'] = array();
$GLOBALS['cvl_wc_country'] = 'PT';

class WP_Error {
    private array $errors = array();
    public function add( $code, $message, $context = array() ): void {
        $this->errors[ $code ] = $message;
    }
    public function has_errors(): bool {
        return (bool) $this->errors;
    }
    public function get_error_codes(): array {
        return array_keys( $this->errors );
    }
    public function get_error_messages(): array {
        return array_values( $this->errors );
    }
}
class WP_REST_Request {
    public function __construct( private array $params ) {}
    public function get_param( string $name ) {
        return $this->params[ $name ] ?? null;
    }
}
class WC_Order {
    private array $metadata = array();
    private string $company = '';
    public array $shipping = array( 'postcode' => '2500-663', 'city' => 'Salir do Porto' );
    public function update_meta_data( string $key, $value ): void {
        $this->metadata[$key] = $value;
    }
    public function get_meta( string $key ) {
        return $this->metadata[$key] ?? null;
    }
    public function set_billing_company( string $name ): void {
        $this->company = $name;
    }
    public function get_billing_company(): string {
        return $this->company;
    }
    public function get_billing_country(): string {
        return $GLOBALS['cvl_wc_country'];
    }
}
function WC() {
    return (object) array(
        'customer' => new class {
            public function get_billing_country(): string {
                return $GLOBALS['cvl_wc_country'];
            }
        }
    );
}
function add_filter( $name, $callback, $priority = 10, $accepted_args = 1 ): void {
    $GLOBALS['cvl_hooks'][$name][] = $callback;
}
function add_action( $name, $callback, $priority = 10, $accepted_args = 1 ): void {
    $GLOBALS['cvl_hooks'][$name][] = $callback;
}
function woocommerce_register_additional_checkout_field( array $field ): bool {
    $GLOBALS['cvl_additional_fields'][] = $field;
    return true;
}
function sanitize_key( $value ): string {
    return preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $value ) );
}
function sanitize_text_field( $value ): string {
    return trim( strip_tags( (string) $value ) );
}
function check( bool $okay, string $label ): void {
    if ( ! $okay ) {
        fwrite( STDERR, "FAIL: $label\n" );
        exit( 1 );
    }
    echo "PASS: $label\n";
}
require dirname( __DIR__ ) . '/wp-content/themes/chavevertical-lite/inc/checkout-customer-type.php';
require dirname( __DIR__ ) . '/wp-content/themes/chavevertical-lite/inc/checkout-blocks-fiscal.php';

foreach ( $GLOBALS['cvl_hooks']['woocommerce_init'] ?? array() as $register ) {
    $register();
}
$fields = array();
foreach ( $GLOBALS['cvl_additional_fields'] as $field ) {
    $fields[$field['id']] = $field;
    check( $field['location'] === 'order', 'Fiscal field applies to order only, not shipping' );
}
check( count( $fields ) === 4, 'Exactly four fiscal fields registered' );
check( $fields['cvl-fiscal/customer-type']['type'] === 'select', 'Customer type is native select' );
check( $fields['cvl-fiscal/customer-type']['required'] === true, 'Customer type required' );
check( count( $fields['cvl-fiscal/customer-type']['options'] ) === 2, 'Exactly Particular and Empresa options' );

foreach ( array(
    'cvl-fiscal/nif' => 'particular',
    'cvl-fiscal/company-name' => 'empresa',
    'cvl-fiscal/nipc' => 'empresa',
) as $id => $kind ) {
    check(
        $fields[$id]['required']['checkout']['properties']['additional_fields']['properties']['cvl-fiscal/customer-type']['const'] === $kind,
        "$id becomes required for correct customer type"
    );
    check(
        $fields[$id]['hidden']['checkout']['properties']['additional_fields']['properties']['cvl-fiscal/customer-type']['not']['const'] === $kind,
        "$id hidden for other customer type"
    );
    check( !isset($fields[$id]['attributes']['disabled']), "$id does not disable React field" );
}

$hooks = $GLOBALS['cvl_hooks']['woocommerce_blocks_validate_location_other_fields'] ?? array();
check( count($hooks) === 1, 'Official Woo Blocks server validation registered' );
$validate = $hooks[0];

$personal = array(
    'cvl-fiscal/customer-type' => 'particular',
    'cvl-fiscal/nif' => '123456789',
);
$errors = new WP_Error();
$validate( $errors, $personal, 'other' );
check( !$errors->has_errors(), 'Portuguese personal NIF accepted' );

$business = array(
    'cvl-fiscal/customer-type' => 'empresa',
    'cvl-fiscal/company-name' => 'Chave Vertical Lda',
    'cvl-fiscal/nipc' => '509514502',
);
$errors = new WP_Error();
$validate( $errors, $business, 'other' );
check( !$errors->has_errors(), 'Portuguese company NIPC accepted' );

$errors = new WP_Error();
$validate( $errors, array( 'cvl-fiscal/customer-type' => 'particular', 'cvl-fiscal/nif' => '509514502' ), 'other' );
check( $errors->has_errors(), 'NIPC rejected in Particular mode' );

$errors = new WP_Error();
$validate( $errors, array( 'cvl-fiscal/customer-type' => 'empresa', 'cvl-fiscal/nipc' => '123456789' ), 'other' );
check( $errors->has_errors(), 'Personal NIF rejected as NIPC' );

$errors = new WP_Error();
$validate( $errors, array( 'cvl-fiscal/customer-type' => 'empresa', 'cvl-fiscal/nipc' => '509514502' ), 'other' );
check( $errors->has_errors(), 'Company name required in Empresa mode' );

$errors = new WP_Error();
$validate( $errors, array( 'cvl-fiscal/customer-type' => 'particular' ), 'other' );
check( $errors->has_errors(), 'NIF required in Particular mode' );

$errors = new WP_Error();
$validate( $errors, $business, 'shipping' );
check( !$errors->has_errors(), 'Shipping fields not fiscal-validated' );

$GLOBALS['cvl_wc_country'] = 'FR';
$errors = new WP_Error();
$validate( $errors, array(
    'cvl-fiscal/customer-type' => 'empresa',
    'cvl-fiscal/company-name' => 'French Company',
    'cvl-fiscal/nipc' => 'FRXX1234567',
), 'other' );
check( !$errors->has_errors(), 'Foreign identifier does not use PT checksum' );
$GLOBALS['cvl_wc_country'] = 'PT';

$order = new WC_Order();
$request = new WP_REST_Request( array(
    'additional_fields' => $business,
    'billing_address' => array( 'country' => 'PT' ),
) );
cvl_blocks_fiscal_save_order( $order, $request );
check( $order->get_meta('_billing_customer_type') === 'empresa', 'Company type stored on order' );
check( $order->get_meta('_billing_nipc') === '509514502', 'Company NIPC stored on order' );
check( $order->get_billing_company() === 'Chave Vertical Lda', 'Company name copied into native billing company' );
check( $order->shipping['city'] === 'Salir do Porto', 'Shipping address untouched by billing data' );

$person_order = new WC_Order();
cvl_blocks_fiscal_save_order(
    $person_order,
    new WP_REST_Request( array(
        'additional_fields' => $personal,
        'billing_address' => array( 'country' => 'PT' ),
    ) )
);
check( $person_order->get_meta('_billing_nif') === '123456789', 'Personal NIF saved on order' );
check( $person_order->get_billing_company() === '', 'Individual has no company name on billing' );
check( $person_order->get_meta('_billing_nipc') === '', 'No company NIPC leaked to individual order' );

$untouched = new WC_Order();
cvl_blocks_fiscal_save_order( $untouched, new WP_REST_Request(array( 'billing_address' => array( 'country' => 'PT' ) )) );
check( $untouched->get_meta('_billing_customer_type') === null, 'Missing fields never overwrite older orders' );

echo "checkout_blocks_fiscal_unit_status=ok\n";
