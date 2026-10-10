<?php
/**
 * Offline tests: no WordPress boot, database connection or email sending.
 * Run: php ops/test-woocommerce-email-ptpt.php
 */
declare(strict_types=1);
define( 'ABSPATH', __DIR__ );
$GLOBALS['cvl_test_filters'] = array();
$GLOBALS['cvl_test_actions'] = array();
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
    $GLOBALS['cvl_test_filters'][ $hook ][] = $callback;
}
function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
    $GLOBALS['cvl_test_actions'][ $hook ][] = $callback;
}
class WC_Email { public $id = 'customer_processing_order'; }
require __DIR__ . '/../wp-content/themes/chavevertical-lite/inc/woocommerce-email-ptpt.php';

function cvl_email_check( bool $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, 'FAILED: ' . $message . "\n" );
        exit( 1 );
    }
}

$email = new WC_Email();
cvl_email_check( ! cvl_email_ptpt_active(), 'Email scope must start inactive' );
cvl_email_check(
    'Order summary' === cvl_email_ptpt_gettext( 'Order summary', 'Order summary', 'woocommerce' ),
    'Non-email output must not be touched'
);
foreach ( $GLOBALS['cvl_test_actions']['woocommerce_email_header'] as $action ) {
    $action( 'Encomenda', $email );
}
cvl_email_check( cvl_email_ptpt_active(), 'Header activates the email scope' );
cvl_email_check(
    'Resumo da encomenda' === cvl_email_ptpt_gettext( 'Order summary', 'Order summary', 'woocommerce' ),
    'Untranslated WooCommerce structural labels must fall back to PT-PT'
);
cvl_email_check(
    'Resumo oficial' === cvl_email_ptpt_gettext( 'Resumo oficial', 'Order summary', 'woocommerce' ),
    'Existing official translations must win'
);
cvl_email_check(
    'Order summary' === cvl_email_ptpt_gettext( 'Order summary', 'Order summary', 'other-plugin' ),
    'Other text domains must not be altered'
);
foreach ( $GLOBALS['cvl_test_actions']['woocommerce_email_footer'] as $action ) {
    $action( $email );
}
cvl_email_check( ! cvl_email_ptpt_active(), 'Footer restores the original scope' );
$subjects = cvl_email_ptpt_titles();
cvl_email_check(
    '[Chave Vertical]: Nova encomenda #1201' === cvl_email_ptpt_match_title(
        '[Chave Vertical]: New order #1201',
        $subjects['new_order']['subject']
    ),
    'Order number and store title must remain intact'
);
cvl_email_check(
    'Assunto escrito pelo gestor' === cvl_email_ptpt_match_title(
        'Assunto escrito pelo gestor',
        $subjects['new_order']['subject']
    ),
    'Administrator-authored subjects must be preserved'
);
cvl_email_check(
    'A sua encomenda #1201 na Chave Vertical foi reembolsada' === cvl_email_ptpt_match_title(
        'Your Chave Vertical order #1201 has been refunded',
        $subjects['customer_refunded_order']['subject']
    ),
    'Refund subjects must retain the correct order number'
);
echo "WooCommerce email PT-PT fallback: OK\n";
