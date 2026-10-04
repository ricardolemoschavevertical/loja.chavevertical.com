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

$page = get_page_by_path( 'marcas', OBJECT, 'page' );

if ( $page instanceof WP_Post ) {
    $pageId = (int) $page->ID;

    if ( 'publish' !== $page->post_status ) {
        $updated = wp_update_post(
            array(
                'ID'          => $pageId,
                'post_status' => 'publish',
            ),
            true
        );

        if ( is_wp_error( $updated ) ) {
            fwrite( STDERR, 'Could not publish existing Marcas page: ' . $updated->get_error_message() . "\n" );
            exit( 1 );
        }
    }

    update_post_meta( $pageId, '_wp_page_template', 'page-marcas.php' );
    clean_post_cache( $pageId );

    echo "Marcas page already exists and is configured: {$pageId}\n";
    echo get_permalink( $pageId ) . "\n";
    exit( 0 );
}

$pageId = wp_insert_post(
    array(
        'post_type'    => 'page',
        'post_title'   => 'Marcas',
        'post_name'    => 'marcas',
        'post_status'  => 'publish',
        'post_content' => '',
    ),
    true
);

if ( is_wp_error( $pageId ) ) {
    fwrite( STDERR, 'Could not create Marcas page: ' . $pageId->get_error_message() . "\n" );
    exit( 1 );
}

update_post_meta( (int) $pageId, '_wp_page_template', 'page-marcas.php' );
clean_post_cache( (int) $pageId );

echo "Marcas page created: {$pageId}\n";
echo get_permalink( (int) $pageId ) . "\n";
