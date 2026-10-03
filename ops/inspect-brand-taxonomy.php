<?php
declare(strict_types=1);
require_once '/home/chavevertical-loja/htdocs/loja.chavevertical.com/wp-load.php';

$taxonomies = get_taxonomies( array(), 'objects' );
$brands = array();

foreach ( $taxonomies as $taxonomy ) {
    if (
        stripos( $taxonomy->name, 'brand' ) !== false
        || stripos( (string) $taxonomy->label, 'marca' ) !== false
        || stripos( (string) $taxonomy->label, 'brand' ) !== false
    ) {
        $terms = get_terms( array(
            'taxonomy'   => $taxonomy->name,
            'hide_empty' => false,
        ) );

        $brands[] = array(
            'taxonomy' => $taxonomy->name,
            'label'    => $taxonomy->label,
            'public'   => (bool) $taxonomy->public,
            'terms'    => is_wp_error( $terms ) ? -1 : count( $terms ),
        );
    }
}

echo wp_json_encode( array(
    'woocommerce_active' => class_exists( 'WooCommerce' ),
    'brand_taxonomies'    => $brands,
), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) . PHP_EOL;
