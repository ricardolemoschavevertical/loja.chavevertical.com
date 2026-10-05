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

$page = get_page_by_path( 'favoritos', OBJECT, 'page' );
$data = array(
    'post_type'    => 'page',
    'post_title'   => 'Favoritos',
    'post_name'    => 'favoritos',
    'post_status'  => 'publish',
    'post_content' => '',
);

if ( $page instanceof WP_Post ) {
    $data['ID'] = (int) $page->ID;
    $result = wp_update_post( $data, true );
    $action = 'updated';
} else {
    $result = wp_insert_post( $data, true );
    $action = 'created';
}

if ( is_wp_error( $result ) ) {
    fwrite( STDERR, 'Could not create/update Favoritos page: ' . $result->get_error_message() . "\n" );
    exit( 1 );
}

$pageId = (int) $result;
clean_post_cache( $pageId );

echo "Favoritos page {$action}: {$pageId}\n";
echo get_permalink( $pageId ) . "\n";
