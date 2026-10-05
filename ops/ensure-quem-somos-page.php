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

$page = get_page_by_path( 'quem-somos', OBJECT, 'page' );

if ( $page instanceof WP_Post ) {
    $pageId = (int) $page->ID;

    $update = array(
        'ID'          => $pageId,
        'post_title'  => 'Quem Somos',
        'post_name'   => 'quem-somos',
        'post_status' => 'publish',
    );

    $updated = wp_update_post( $update, true );

    if ( is_wp_error( $updated ) ) {
        fwrite( STDERR, 'Could not update existing Quem Somos page: ' . $updated->get_error_message() . "\n" );
        exit( 1 );
    }

    update_post_meta( $pageId, '_wp_page_template', 'page-quem-somos.php' );
    clean_post_cache( $pageId );

    echo "Quem Somos page already exists and is configured: {$pageId}\n";
    echo get_permalink( $pageId ) . "\n";
    exit( 0 );
}

$pageId = wp_insert_post(
    array(
        'post_type'    => 'page',
        'post_title'   => 'Quem Somos',
        'post_name'    => 'quem-somos',
        'post_status'  => 'publish',
        'post_content' => '',
    ),
    true
);

if ( is_wp_error( $pageId ) ) {
    fwrite( STDERR, 'Could not create Quem Somos page: ' . $pageId->get_error_message() . "\n" );
    exit( 1 );
}

update_post_meta( (int) $pageId, '_wp_page_template', 'page-quem-somos.php' );
clean_post_cache( (int) $pageId );

echo "Quem Somos page created: {$pageId}\n";
echo get_permalink( (int) $pageId ) . "\n";
