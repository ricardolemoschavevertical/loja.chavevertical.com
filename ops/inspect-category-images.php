<?php
declare(strict_types=1);

require_once '/home/chavevertical-loja/htdocs/loja.chavevertical.com/wp-load.php';

$uncategorized = get_term_by( 'slug', 'uncategorized', 'product_cat' );
$exclude = $uncategorized && ! is_wp_error( $uncategorized ) ? array( (int) $uncategorized->term_id ) : array();

$terms = get_terms( array(
    'taxonomy'   => 'product_cat',
    'hide_empty' => false,
    'exclude'    => $exclude,
    'number'     => 0,
) );

if ( is_wp_error( $terms ) ) {
    fwrite( STDERR, $terms->get_error_message() . PHP_EOL );
    exit( 1 );
}

$missing = array();
$roots   = array();

foreach ( $terms as $term ) {
    $thumb_id = absint( get_term_meta( $term->term_id, 'thumbnail_id', true ) );
    $entry = array(
        'id'       => (int) $term->term_id,
        'parent'   => (int) $term->parent,
        'slug'     => $term->slug,
        'name'     => html_entity_decode( $term->name, ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
        'thumb_id' => $thumb_id,
        'url'      => $thumb_id ? (string) wp_get_attachment_url( $thumb_id ) : '',
    );

    if ( ! $thumb_id ) {
        $missing[] = $entry;
    }

    if ( 0 === (int) $term->parent ) {
        $metadata = $thumb_id ? wp_get_attachment_metadata( $thumb_id ) : array();
        $entry['width']  = isset( $metadata['width'] ) ? (int) $metadata['width'] : 0;
        $entry['height'] = isset( $metadata['height'] ) ? (int) $metadata['height'] : 0;
        $roots[] = $entry;
    }
}

echo wp_json_encode( array(
    'total'         => count( $terms ),
    'missing_count' => count( $missing ),
    'missing'       => $missing,
    'roots'         => $roots,
), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . PHP_EOL;
