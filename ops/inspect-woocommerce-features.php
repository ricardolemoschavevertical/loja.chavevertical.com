<?php
declare(strict_types=1);

$root = '/home/chavevertical-loja/htdocs/loja.chavevertical.com';
require_once $root . '/wp-load.php';

echo 'woocommerce_version=' . ( defined( 'WC_VERSION' ) ? WC_VERSION : 'unknown' ) . PHP_EOL;

$class = 'Automattic\\WooCommerce\\Internal\\Features\\FeaturesController';
if ( class_exists( $class ) && function_exists( 'wc_get_container' ) ) {
    try {
        $controller = wc_get_container()->get( $class );
        echo 'features_controller=' . get_class( $controller ) . PHP_EOL;
        echo 'methods=' . implode( ',', get_class_methods( $controller ) ) . PHP_EOL;

        foreach ( array( 'get_features', 'get_feature_definitions' ) as $method ) {
            if ( method_exists( $controller, $method ) ) {
                $data = $controller->{$method}();
                echo $method . '=' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES ) . PHP_EOL;
            }
        }
    } catch ( Throwable $e ) {
        echo 'features_controller_error=' . $e->getMessage() . PHP_EOL;
    }
}

global $wpdb;
$patterns = array(
    'woocommerce%feature%',
    '%custom_orders_table%',
    '%analytics%',
    '%attribution%',
    '%rate_limit%',
    '%product%cache%',
);

$where = array();
$args  = array();

foreach ( $patterns as $pattern ) {
    $where[] = 'option_name LIKE %s';
    $args[]  = $pattern;
}

$sql = 'SELECT option_name, option_value FROM ' . $wpdb->options . ' WHERE ' . implode( ' OR ', $where ) . ' ORDER BY option_name';
$rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$args ), ARRAY_A );

echo 'matching_options=' . wp_json_encode( $rows, JSON_UNESCAPED_SLASHES ) . PHP_EOL;
