<?php
/**
 * Pure PHP tests of automatic PT postcode -> locality lookup.
 * Hermetic: no WordPress DB, no network calls, no real orders or customers.
 */
declare(strict_types=1);

defined( 'ABSPATH' ) || define( 'ABSPATH', __DIR__ . '/stub/' );
defined( 'DAY_IN_SECONDS' ) || define( 'DAY_IN_SECONDS', 86400 );
defined( 'HOUR_IN_SECONDS' ) || define( 'HOUR_IN_SECONDS', 3600 );
defined( 'MINUTE_IN_SECONDS' ) || define( 'MINUTE_IN_SECONDS', 60 );

$GLOBALS['locality_hooks'] = array();
$GLOBALS['locality_transients'] = array();
$GLOBALS['locality_ttl'] = array();
$GLOBALS['locality_remote_calls'] = array();
$GLOBALS['locality_response'] = array();
$GLOBALS['locality_nonce_ok'] = true;
$_SERVER['REMOTE_ADDR'] = '192.0.2.123';

class WP_Error {
    public function __construct( public string $code = '' ) {}
}
class LocalityTestAjaxReply extends RuntimeException {
    public function __construct( public bool $success, public mixed $payload, public int $status ) {
        parent::__construct( 'mock JSON response' );
    }
}
function add_action( $tag, $callback, $priority = 10, $argc = 1 ): void {
    $GLOBALS['locality_hooks'][ $tag ][] = $callback;
}
function sanitize_text_field( $value ): string {
    return trim( strip_tags( (string) $value ) );
}
function wp_unslash( $value ) { return $value; }
function check_ajax_referer( $action, $query_arg = false, $die = true ): int|false {
    return $GLOBALS['locality_nonce_ok'] ? 1 : false;
}
function get_current_user_id(): int { return 0; }
function wp_salt( string $context ): string { return 'unit-test-only'; }
function get_transient( $key ) {
    return $GLOBALS['locality_transients'][ $key ] ?? false;
}
function set_transient( $key, $value, $ttl ): bool {
    $GLOBALS['locality_transients'][ $key ] = $value;
    $GLOBALS['locality_ttl'][ $key ] = $ttl;
    return true;
}
function wp_remote_get( $url, $args ) {
    $GLOBALS['locality_remote_calls'][] = compact( 'url', 'args' );
    return $GLOBALS['locality_response'];
}
function wp_remote_retrieve_response_code( $response ): int {
    return (int) ( $response['response']['code'] ?? 0 );
}
function wp_remote_retrieve_body( $response ): string {
    return (string) ( $response['body'] ?? '' );
}
function is_wp_error( $value ): bool { return $value instanceof WP_Error; }
function wp_send_json_success( $value, $status = 200 ): never {
    throw new LocalityTestAjaxReply( true, $value, (int) $status );
}
function wp_send_json_error( $value, $status = 200 ): never {
    throw new LocalityTestAjaxReply( false, $value, (int) $status );
}
function check( bool $assertion, string $title ): void {
    if ( ! $assertion ) {
        fwrite( STDERR, "FAIL: $title\n" );
        exit( 1 );
    }
    echo "PASS: $title\n";
}
function post_lookup( string $country, string $postcode, bool $nonce = true ): LocalityTestAjaxReply {
    $GLOBALS['locality_nonce_ok'] = $nonce;
    $_POST = array( 'country' => $country, 'postcode' => $postcode );
    try {
        cvl_postcode_locality_ajax();
    } catch ( LocalityTestAjaxReply $reply ) {
        return $reply;
    }
    throw new RuntimeException( 'Expected JSON mock reply.' );
}

require dirname( __DIR__ ) . '/wp-content/themes/chavevertical-lite/inc/checkout-postcode-locality.php';

check( cvl_postcode_locality_normalize( '6160152' ) === '6160-152', 'Numeric postcode formatted automatically' );
check( cvl_postcode_locality_normalize( '6160 152' ) === '6160-152', 'Pasted postcode with space normalized' );
check( cvl_postcode_locality_normalize( '6160-152' ) === '6160-152', 'Canonical PT postcode preserved' );
check( cvl_postcode_locality_normalize( '6160-15A' ) === '', 'Invalid PT postcode rejected' );
check( cvl_postcode_locality_normalize( '75001' ) === '', 'French postcode never treated as PT' );
check( cvl_postcode_locality_clean_name( '<script>evil</script> Coimbra' ) === 'evil Coimbra', 'Locality name sanitized' );
check( cvl_postcode_locality_clean_name( '---' ) === '', 'Undisclosed locality rejected' );
check( cvl_postcode_locality_clean_name( 'Não disponível' ) === 'Não disponível', 'Text sanitizer does not invent locality' );

// Responses always contain only a locality string, not addresses/customer data.
$body = json_encode( array(
    'cp7' => '6160-152',
    'localidade' => 'Isna',
    'concelho' => 'Oleiros',
    'distrito' => 'Castelo Branco',
    'ruas' => array( 'Rua Pessoal' ),
    'cliente' => 'Confidential',
) );
$parsed = cvl_postcode_locality_parse_reply( 200, $body, '6160-152' );
check( $parsed === array( 'status' => 'found', 'localidade' => 'Isna' ), 'Only exact postcode locality returned' );
check( cvl_postcode_locality_parse_reply( 200, $body, '2500-663' )['status'] === 'not_found', 'Wrong provider postcode cannot autofill' );
check( cvl_postcode_locality_parse_reply( 404, '', '6160-152' )['status'] === 'not_found', 'No provider match -> manual entry' );
check( cvl_postcode_locality_parse_reply( 503, '', '6160-152' )['status'] === 'unavailable', 'Provider offline -> manual entry' );
check( cvl_postcode_locality_parse_reply( 200, 'not-json', '6160-152' )['status'] === 'unavailable', 'Invalid JSON -> manual entry' );
check( cvl_postcode_locality_parse_reply( 200, '{"concelho":"Coimbra"}', '6160-152' )['status'] === 'not_found', 'Concelho is never guessed as locality' );
check( cvl_postcode_locality_parse_reply( 200, '{"localidades":["Isna","Sernache"]}', '6160-152' )['status'] === 'ambiguous', 'Multiple localities never guessed' );
check( cvl_postcode_locality_parse_reply( 200, '{"data":{"cp7":"2500-663","localidade":"Salir do Porto"}}', '2500-663' )['localidade'] === 'Salir do Porto', 'Nested provider payload supported' );

$GLOBALS['locality_response'] = array( 'response' => array( 'code' => 200 ), 'body' => $body );
$ok = post_lookup( 'PT', '6160152' );
check( $ok->success && $ok->payload['localidade'] === 'Isna', 'Guest AJAX lookup returned locality' );
check( count( $GLOBALS['locality_remote_calls'] ) === 1, 'Provider contacted only once for uncached CP' );
$remote = $GLOBALS['locality_remote_calls'][0];
check( $remote['url'] === 'https://moradas.dev/cp/6160-152', 'Only fixed HTTPS URL with validated CP7' );
check( $remote['args']['sslverify'] === true && $remote['args']['redirection'] === 0, 'TLS validation and no redirects' );
check( $remote['args']['timeout'] <= 5, 'Remote lookup bounded to 5 seconds' );
check( $remote['args']['limit_response_size'] <= 65536, 'Response size bounded' );
check( $GLOBALS['locality_ttl']['cvl_cp_loc_' . md5( '6160-152' )] === 30 * DAY_IN_SECONDS, 'Successful lookup is cached for 30 days' );

$cached = post_lookup( 'PT', '6160-152' );
check( $cached->success && $cached->payload['localidade'] === 'Isna', 'Cache hit returns correct result' );
check( count( $GLOBALS['locality_remote_calls'] ) === 1, 'Cached request skips provider' );

$before = count( $GLOBALS['locality_remote_calls'] );
$foreign = post_lookup( 'FR', '6160-152' );
check( $foreign->success && $foreign->payload['status'] === 'unsupported', 'Foreign country is never looked up' );
$bad = post_lookup( 'PT', 'invalid?url=https://evil.test' );
check( $bad->success && $bad->payload['status'] === 'invalid', 'Untrusted input cannot cause SSRF' );
check( count( $GLOBALS['locality_remote_calls'] ) === $before, 'Invalid and foreign requests do not hit provider' );

$expired = post_lookup( 'PT', '6160-152', false );
check( ! $expired->success && $expired->status === 403, 'Invalid checkout nonce denied' );

// Provider errors must not be cached as found.
$GLOBALS['locality_response'] = new WP_Error( 'timeout' );
$notavailable = post_lookup( 'PT', '2500-663' );
check( $notavailable->success && $notavailable->payload['status'] === 'unavailable', 'Provider timeout falls back to manual city' );

check(
    isset( $GLOBALS['locality_hooks']['wp_ajax_cvl_postcode_locality'] )
    && isset( $GLOBALS['locality_hooks']['wp_ajax_nopriv_cvl_postcode_locality'] ),
    'Lookup available for logged-in and guest checkout'
);

echo "checkout_postcode_locality_unit_status=ok\n";
