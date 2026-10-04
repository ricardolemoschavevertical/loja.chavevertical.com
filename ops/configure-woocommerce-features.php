<?php
declare(strict_types=1);

$root = '/home/chavevertical-loja/htdocs/loja.chavevertical.com';
require_once $root . '/wp-load.php';

if ( ! defined( 'WC_VERSION' ) ) {
    fwrite( STDERR, "WooCommerce is not active.\n" );
    exit( 1 );
}

$expected = array(
    'woocommerce_custom_orders_table_enabled'                 => 'yes',
    'woocommerce_analytics_enabled'                           => 'yes',
    'woocommerce_feature_order_attribution_enabled'           => 'yes',
    'woocommerce_feature_product_instance_caching_enabled'    => 'yes',
    'woocommerce_feature_rate_limit_checkout_enabled'         => 'yes',
    'woocommerce_custom_orders_table_data_sync_enabled'       => 'no',
);

$before = array();
foreach ( $expected as $key => $value ) {
    $before[ $key ] = get_option( $key, null );
}

foreach ( $expected as $key => $value ) {
    update_option( $key, $value, false );
}

wp_cache_flush();

$failed = array();
foreach ( $expected as $key => $value ) {
    $actual = (string) get_option( $key, '' );
    echo $key . ': ' . ( is_scalar( $before[ $key ] ) ? (string) $before[ $key ] : 'unset' ) . ' -> ' . $actual . PHP_EOL;

    if ( $actual !== $value ) {
        $failed[ $key ] = $actual;
    }
}

if ( $failed ) {
    fwrite( STDERR, 'Feature verification failed: ' . wp_json_encode( $failed ) . PHP_EOL );
    exit( 1 );
}

echo 'WooCommerce recommended feature configuration verified.' . PHP_EOL;
