<?php
declare(strict_types=1);

$root = '/home/chavevertical-loja/htdocs/loja.chavevertical.com';
require_once $root . '/wp-load.php';

if ( ! taxonomy_exists( 'product_brand' ) ) {
    fwrite( STDERR, "product_brand taxonomy unavailable\n" );
    exit( 2 );
}

$term_ids = get_terms(
    array(
        'taxonomy'   => 'product_brand',
        'hide_empty' => false,
        'fields'     => 'ids',
    )
);

if ( is_wp_error( $term_ids ) ) {
    fwrite( STDERR, $term_ids->get_error_message() . "\n" );
    exit( 2 );
}

$remaining = array();

foreach ( $term_ids as $term_id ) {
    $attachment_id = absint( get_term_meta( (int) $term_id, 'thumbnail_id', true ) );

    if ( ! $attachment_id ) {
        continue;
    }

    $mime = strtolower( (string) get_post_mime_type( $attachment_id ) );
    $url  = (string) wp_get_attachment_url( $attachment_id );
    $path = (string) wp_parse_url( $url, PHP_URL_PATH );

    if ( 'image/avif' === $mime || preg_match( '/\.avif$/i', $path ) ) {
        $term = get_term( (int) $term_id, 'product_brand' );
        $remaining[] = sprintf(
            '%d:%s:%s',
            (int) $term_id,
            $term instanceof WP_Term ? $term->name : 'unknown',
            $url
        );
    }
}

printf( "brand-logo-avif-remaining=%d\n", count( $remaining ) );

foreach ( $remaining as $item ) {
    fwrite( STDERR, "brand-logo-avif remaining: {$item}\n" );
}

exit( empty( $remaining ) ? 0 : 1 );
