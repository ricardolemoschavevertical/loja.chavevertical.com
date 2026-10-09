<?php
/**
 * Standalone regression test for the persistent legacy importer pause.
 * No real WordPress database or products are touched.
 */
declare(strict_types=1);

define( 'ABSPATH', '/test/' );
define( 'CVR2_STATE_OPTION', 'cvr2_import_state' );

$GLOBALS['cv_test_options'] = array();
$GLOBALS['cv_test_transients'] = array();
function get_option( $key, $default = false ) {
    return $GLOBALS['cv_test_options'][ $key ] ?? $default;
}
function update_option( $key, $value, $autoload = null ): bool {
    $GLOBALS['cv_test_options'][ $key ] = $value;
    return true;
}
function delete_option( $key ): bool {
    unset( $GLOBALS['cv_test_options'][ $key ] );
    return true;
}
function get_transient( $key ) {
    return $GLOBALS['cv_test_transients'][ $key ] ?? false;
}
function absint( $value ): int {
    return abs( (int) $value );
}
function check( bool $assertion, string $message ): void {
    if ( ! $assertion ) {
        throw new RuntimeException( 'FAIL: ' . $message );
    }
}

require_once dirname( __DIR__ ) . '/includes/class-cvr2-admin.php';

$state = array(
    'status' => 'running',
    'run_id' => 'prior-run',
    'page' => 45,
    'batch_index' => 7,
    'processed' => 1234,
    'total' => 35000,
    'updated_at' => time() - 3600,
);
update_option( CVR2_STATE_OPTION, $state );
check( CVR2_Admin::legacy_blocks_local(), 'A stale running checkpoint must initially block local import.' );
$result = CVR2_Admin::pause_legacy_safely();
check( ! $result['pending'], 'A stale REST checkpoint with no live batch must pause immediately.' );
$paused = get_option( CVR2_STATE_OPTION );
check( $paused['status'] === 'paused', 'REST checkpoint must be paused on the server.' );
check( $paused['page'] === 45, 'REST page must be preserved.' );
check( $paused['batch_index'] === 7, 'Within-page index must be preserved.' );
check( $paused['processed'] === 1234, 'Progress must be preserved.' );
check( ! CVR2_Admin::legacy_blocks_local(), 'Local import must be unlocked after pause.' );

$state['run_id'] = 'active-run';
update_option( CVR2_STATE_OPTION, $state );
$lock_key = 'cvr2_batch_lock_' . md5( 'active-run' );
$GLOBALS['cv_test_transients'][$lock_key] = 'in-flight';
$result = CVR2_Admin::pause_legacy_safely();
check( $result['pending'], 'An active Woo batch cannot be paused mid-product.' );
check( CVR2_Admin::legacy_blocks_local(), 'Local import remains locked while a batch is in flight.' );
check( get_option( CVR2_STATE_OPTION )['status'] === 'running', 'Active batch state is not clobbered.' );
check( get_option( 'cvr2_legacy_pause_requested' ) === 'active-run', 'Pause request must survive between PHP requests.' );

$checkpoint = get_option( CVR2_STATE_OPTION );
$reflection = new ReflectionMethod( CVR2_Admin::class, 'apply_pending_pause' );
$reflection->setAccessible( true );
$args = array( &$checkpoint );
$applied = $reflection->invokeArgs( null, $args );
check( $applied === true, 'The batch must honour the persistent pause request.' );
check( $checkpoint['status'] === 'paused', 'Batch final checkpoint must be paused.' );
check( $checkpoint['processed'] === 1234 && $checkpoint['batch_index'] === 7, 'Batch pause must preserve progress.' );
check( get_option( 'cvr2_legacy_pause_requested', '' ) === '', 'The fulfilled pause request is cleared.' );
check( CVR2_Admin::legacy_blocks_local(), 'The live transient still protects against concurrent local imports.' );
unset( $GLOBALS['cv_test_transients'][$lock_key] );
check( ! CVR2_Admin::legacy_blocks_local(), 'Local import becomes available after batch shutdown.' );

echo "OK: legacy checkpoint pause, in-flight protection and safe release.\n";
