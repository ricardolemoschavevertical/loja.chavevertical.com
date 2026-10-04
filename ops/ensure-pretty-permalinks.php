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

$targetStructure = '/%postname%/';
$currentStructure = (string) get_option( 'permalink_structure', '' );

if ( $currentStructure !== $targetStructure ) {
    update_option( 'permalink_structure', $targetStructure, true );
    echo "Permalink structure updated: {$currentStructure} -> {$targetStructure}\n";
} else {
    echo "Permalink structure already correct: {$targetStructure}\n";
}

/*
 * Imported pages can occasionally retain empty, numeric or page-ID style slugs.
 * Only repair those clearly invalid slugs; existing meaningful slugs are preserved.
 */
$pages = get_posts(
    array(
        'post_type'      => 'page',
        'post_status'    => array( 'publish', 'private', 'draft', 'pending', 'future' ),
        'posts_per_page' => -1,
        'orderby'        => 'ID',
        'order'          => 'ASC',
    )
);

$repaired = 0;

foreach ( $pages as $page ) {
    if ( ! $page instanceof WP_Post ) {
        continue;
    }

    $slug = (string) $page->post_name;

    if ( '' !== $slug && ! preg_match( '/^(?:page-)?\d+$/', $slug ) ) {
        continue;
    }

    $candidate = sanitize_title( $page->post_title );

    if ( '' === $candidate ) {
        $candidate = 'pagina-' . (int) $page->ID;
    }

    $candidate = wp_unique_post_slug(
        $candidate,
        (int) $page->ID,
        (string) $page->post_status,
        'page',
        (int) $page->post_parent
    );

    $result = wp_update_post(
        array(
            'ID'        => (int) $page->ID,
            'post_name' => $candidate,
        ),
        true
    );

    if ( is_wp_error( $result ) ) {
        fwrite(
            STDERR,
            sprintf(
                "Could not repair page slug for ID %d: %s\n",
                (int) $page->ID,
                $result->get_error_message()
            )
        );
        exit( 1 );
    }

    clean_post_cache( (int) $page->ID );
    $repaired++;

    echo sprintf(
        "Page slug repaired: #%d -> /%s/\n",
        (int) $page->ID,
        $candidate
    );
}

flush_rewrite_rules( true );

echo "Rewrite rules flushed. Repaired page slugs: {$repaired}\n";

if ( function_exists( 'wc_get_page_id' ) ) {
    $wooPages = array(
        'Loja'              => 'shop',
        'Carrinho'          => 'cart',
        'Finalizar compras' => 'checkout',
        'A minha conta'     => 'myaccount',
    );

    foreach ( $wooPages as $label => $key ) {
        $pageId = (int) wc_get_page_id( $key );

        if ( $pageId > 0 ) {
            echo sprintf(
                "%s: %s\n",
                $label,
                get_permalink( $pageId )
            );
        }
    }
}

exit( 0 );
