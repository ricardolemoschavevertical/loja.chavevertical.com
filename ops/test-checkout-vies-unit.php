<?php
/**
 * VIES integration tests, hermetic: no HTTP, WooCommerce DB, orders or emails.
 * Run in package build before any theme deployment.
 */
declare(strict_types=1);

defined( 'ABSPATH' ) || define( 'ABSPATH', __DIR__ . '/stub/' );
defined( 'MINUTE_IN_SECONDS' ) || define( 'MINUTE_IN_SECONDS', 60 );

$GLOBALS['cvl_test_hooks'] = array();
$GLOBALS['cvl_test_http'] = array();
$GLOBALS['cvl_test_http_calls'] = array();
$GLOBALS['cvl_test_transients'] = array();
$GLOBALS['cvl_test_nonce_ok'] = true;
$GLOBALS['cvl_test_is_checkout'] = true;
$_SERVER['REMOTE_ADDR'] = '192.0.2.10';

class WP_Error {
    private string $code;
    private string $message;
    public function __construct( string $code = '', string $message = '' ) {
        $this->code = $code;
        $this->message = $message;
    }
    public function get_error_code(): string { return $this->code; }
    public function get_error_message(): string { return $this->message; }
}
class CvlViesMockResponse extends RuntimeException {
    public mixed $payload;
    public int $status;
    public bool $success;
    public function __construct( bool $success, mixed $payload, int $status ) {
        parent::__construct( 'mock response' );
        $this->success = $success;
        $this->payload = $payload;
        $this->status = $status;
    }
}
function add_filter( $name, $callback, $priority = 10, $accepted_args = 1 ): void {
    $GLOBALS['cvl_test_hooks'][$name][] = $callback;
}
function add_action( $name, $callback, $priority = 10, $accepted_args = 1 ): void {
    $GLOBALS['cvl_test_hooks'][$name][] = $callback;
}
function is_checkout(): bool { return $GLOBALS['cvl_test_is_checkout']; }
function sanitize_text_field( $text ): string { return trim( strip_tags( (string) $text ) ); }
function wp_unslash( $v ) { return $v; }
function wc_get_page_id( $v ): int { return 100; }
function check_ajax_referer( $action, $nonce, $die ): int|false {
    return $GLOBALS['cvl_test_nonce_ok'] ? 1 : false;
}
function get_current_user_id(): int { return 0; }
function wp_salt( $v = '' ): string { return 'unit-test-salt'; }
function get_transient( $key ) { return $GLOBALS['cvl_test_transients'][$key] ?? false; }
function set_transient( $key, $value, $expiration ): bool {
    $GLOBALS['cvl_test_transients'][$key] = $value;
    return true;
}
function wp_json_encode( $data ): string { return (string) json_encode( $data ); }
function is_wp_error( $v ): bool { return $v instanceof WP_Error; }
function wp_remote_post( $url, $args ) {
    $GLOBALS['cvl_test_http_calls'][] = array( 'url' => $url, 'args' => $args );
    return $GLOBALS['cvl_test_http'];
}
function wp_remote_retrieve_response_code( $response ): int {
    return (int) ( $response['response']['code'] ?? 0 );
}
function wp_remote_retrieve_body( $response ): string {
    return (string) ( $response['body'] ?? '' );
}
function wp_send_json_success( $payload, $status = 200 ): never {
    throw new CvlViesMockResponse( true, $payload, (int) $status );
}
function wp_send_json_error( $payload, $status = 200 ): never {
    throw new CvlViesMockResponse( false, $payload, (int) $status );
}
function cvl_checkout_pt_tax_id_kind( $vat ): string {
    if ( '509514502' === $vat ) { return 'nipc'; }
    if ( '712345677' === $vat ) { return 'entidade_at'; }
    return '';
}

require dirname( __DIR__ ) . '/wp-content/themes/chavevertical-lite/inc/checkout-vies.php';

function check( bool $expected, string $name ): void {
    if ( ! $expected ) {
        fwrite( STDERR, "FAIL: $name\n" );
        exit( 1 );
    }
    echo "PASS: $name\n";
}

function capture_lookup( string $country, string $vat, bool $nonce = true ): CvlViesMockResponse {
    $GLOBALS['cvl_test_nonce_ok'] = $nonce;
    $_POST = array( 'country' => $country, 'vat' => $vat, 'nonce' => 'valid-test' );
    try {
        cvl_vies_checkout_lookup_ajax();
    } catch ( CvlViesMockResponse $r ) {
        return $r;
    }
    throw new RuntimeException( 'Expected mock JSON response.' );
}

// Normalização e validação local, sem chamadas externas.
$pt = cvl_vies_normalize_request( 'PT', 'PT 509 514 502' );
check( is_array( $pt ) && $pt['countryCode'] === 'PT' && $pt['vatNumber'] === '509514502', 'Portuguese NIPC normalized' );
$gr = cvl_vies_normalize_request( 'GR', 'EL123456789' );
check( is_array( $gr ) && $gr['countryCode'] === 'EL', 'Greece uses EL VIES code' );
$ni = cvl_vies_normalize_request( 'GB', 'XI123456789' );
check( is_array( $ni ) && $ni['countryCode'] === 'XI', 'Northern Ireland XI recognized' );
$nl = cvl_vies_normalize_request( 'NL', 'NL123456789B01' );
check( is_array( $nl ) && $nl['vatNumber'] === '123456789B01', 'VAT suffix letters preserved' );
check( cvl_vies_normalize_request( 'US', '12345678' ) instanceof WP_Error, 'Non-EU destination rejects VIES lookup, not checkout' );
check( cvl_vies_normalize_request( 'ES', 'PT509514502' ) instanceof WP_Error, 'Mismatching VAT prefix cannot query another country' );
check( cvl_vies_normalize_request( 'PT', '509514503' ) instanceof WP_Error, 'Incorrect Portuguese checksum never sent to VIES' );

$profile = cvl_vies_extract_billing_profile( array(
    'name' => 'Chave Vertical Lda',
    'address' => "RUA DO COMÉRCIO\n3800-000 AVEIRO",
    'traderCity' => 'Aveiro',
) );
check( $profile['company'] === 'Chave Vertical Lda', 'Company name returned' );
check( $profile['street'] === 'RUA DO COMÉRCIO', 'Only well separated street inferred' );
check( $profile['city'] === 'Aveiro' && $profile['postcode'] === '', 'No postcode guessed without structured field' );
check( ! array_key_exists( 'shipping', $profile ), 'Shipping fields never returned' );
$hidden = cvl_vies_extract_billing_profile( array( 'name' => '---', 'address' => '----' ) );
check( $hidden['company'] === '' && $hidden['street'] === '', 'VIES undisclosed details not inserted into billing' );

$valid = cvl_vies_parse_service_reply( 200, '{"valid":true,"name":"Example Lda","address":"Rua Teste"}' );
check( $valid['status'] === 'valid' && $valid['profile']['company'] === 'Example Lda', 'REST valid response parsed' );
$invalid = cvl_vies_parse_service_reply( 200, '{"valid":false}' );
check( $invalid['status'] === 'not_found', 'Not found in VIES does not block checkout' );
$unavailable = cvl_vies_parse_service_reply( 503, '{"errorWrappers":[{"error":"MS_UNAVAILABLE"}]}' );
check( $unavailable['status'] === 'unavailable', 'VIES outage allows manual fallback' );
$bad = cvl_vies_parse_service_reply( 200, '{"userError":"MS_MAX_CONCURRENT_REQ"}' );
check( $bad['status'] === 'unavailable', 'VIES service error allows manual fallback' );

// Form button exists only for the billing NIPC field on checkout.
check( isset( $GLOBALS['cvl_test_hooks']['woocommerce_form_field'][0] ), 'Field decoration hook registered' );
$fieldHtml = '<p class="form-row" id="billing_nipc_field"><label>NIPC</label><span class="woocommerce-input-wrapper"><input></span></p>';
$rendered = $GLOBALS['cvl_test_hooks']['woocommerce_form_field'][0]( $fieldHtml, 'billing_nipc' );
check( str_contains( $rendered, 'id="cvl-vies-check"' ), 'VIES button is inserted next to billing NIPC' );
check( $GLOBALS['cvl_test_hooks']['woocommerce_form_field'][0]( $fieldHtml, 'shipping_company' ) === $fieldHtml, 'No button added to shipping form' );
$GLOBALS['cvl_test_is_checkout'] = false;
check( $GLOBALS['cvl_test_hooks']['woocommerce_form_field'][0]( $fieldHtml, 'billing_nipc' ) === $fieldHtml, 'No VIES button outside checkout' );
$GLOBALS['cvl_test_is_checkout'] = true;

// Test AJAX with mocked official VIES, without real HTTP requests.
$GLOBALS['cvl_test_http'] = array(
    'response' => array( 'code' => 200 ),
    'body' => '{"countryCode":"PT","vatNumber":"509514502","valid":true,"name":"Chave Vertical Lda","address":"Rua industrial"}',
);
$ok = capture_lookup( 'PT', '509514502' );
check( $ok->success && $ok->payload['status'] === 'valid', 'Guest checkout lookup succeeds through server proxy' );
$last = end( $GLOBALS['cvl_test_http_calls'] );
check( $last['url'] === 'https://ec.europa.eu/taxation_customs/vies/rest-api/check-vat-number', 'Only official fixed HTTPS endpoint called' );
check( $last['args']['redirection'] === 0 && $last['args']['sslverify'] === true, 'Server VIES request uses TLS and rejects redirects' );
check( json_decode( $last['args']['body'], true ) === $pt, 'POST body has only country and VAT number' );

$GLOBALS['cvl_test_http']['body'] = '{"valid":false}';
$notfound = capture_lookup( 'PT', '509514502' );
check( $notfound->success && $notfound->payload['status'] === 'not_found', 'AJAX not-found is a non-blocking result' );
$before = count( $GLOBALS['cvl_test_http_calls'] );
$bad = capture_lookup( 'PT', '509514503' );
check( $bad->success && $bad->payload['status'] === 'invalid_input', 'Incorrect tax ID is handled without HTTP call' );
check( count( $GLOBALS['cvl_test_http_calls'] ) === $before, 'Invalid VAT inputs do not reach VIES' );
$unauthorized = capture_lookup( 'PT', '509514502', false );
check( ! $unauthorized->success && $unauthorized->status === 403, 'Expired checkout nonce rejected' );

echo "checkout_vies_unit_status=ok\n";
