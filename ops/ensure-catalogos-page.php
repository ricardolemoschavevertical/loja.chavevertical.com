<?php
declare(strict_types=1);

$wordpressRoot = '/home/chavevertical-loja/htdocs/loja.chavevertical.com';
$wpLoad        = $wordpressRoot . '/wp-load.php';

if ( ! is_file( $wpLoad ) ) {
    fwrite( STDERR, "WordPress bootstrap not found: {$wpLoad}\n" );
    exit( 1 );
}

define( 'WP_USE_THEMES', false );
require_once $wpLoad;

$host = (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST );

if ( 'loja.chavevertical.com' !== strtolower( $host ) ) {
    fwrite( STDERR, "Refusing to modify unexpected WordPress host: {$host}\n" );
    exit( 1 );
}

if ( ! shortcode_exists( 'cv_pdf_catalogos_auto' ) ) {
    fwrite( STDERR, "Required shortcode cv_pdf_catalogos_auto is not registered.\n" );
    exit( 1 );
}

$content = '[cv_pdf_catalogos_auto]';
$page = get_page_by_path( 'catalogos', OBJECT, 'page' );

if ( $page instanceof WP_Post ) {
    $pageId = (int) $page->ID;
    $updated = wp_update_post(
        array(
            'ID'           => $pageId,
            'post_title'   => 'Catálogos',
            'post_name'    => 'catalogos',
            'post_status'  => 'publish',
            'post_content' => $content,
        ),
        true
    );

    if ( is_wp_error( $updated ) ) {
        fwrite( STDERR, 'Could not update existing Catalogos page: ' . $updated->get_error_message() . "\n" );
        exit( 1 );
    }

    clean_post_cache( $pageId );
    echo "Catalogos page already exists and was updated: {$pageId}\n";
    echo get_permalink( $pageId ) . "\n";
    exit( 0 );
}

$pageId = wp_insert_post(
    array(
        'post_type'    => 'page',
        'post_title'   => 'Catálogos',
        'post_name'    => 'catalogos',
        'post_status'  => 'publish',
        'post_content' => $content,
    ),
    true
);

if ( is_wp_error( $pageId ) ) {
    fwrite( STDERR, 'Could not create Catalogos page: ' . $pageId->get_error_message() . "\n" );
    exit( 1 );
}

clean_post_cache( (int) $pageId );

echo "Catalogos page created: {$pageId}\n";
echo get_permalink( (int) $pageId ) . "\n";
