<?php
/** Cart presentation only: keep the native saved list before promotions. */
defined( 'ABSPATH' ) || exit;

/**
 * Move the theme-owned promotions section outside the Cart block, before
 * React / Interactivity API hydration. Preserve all other markup byte-for-byte;
 * never move or clone customer items in the browser.
 *
 * The opening tag belongs to cvl_empty_cart_promotions_markup(). Count nested
 * sections rather than stopping at the first closing tag in a product card.
 * If the section is incomplete, fail open and leave the content untouched.
 */
function cvl_cart_promotions_last( $content ) {
    $start = strpos( $content, '<section class="cvl-empty-cart-promotions"' );

    if ( false === $start ) {
        return $content;
    }

    $matched = preg_match_all(
        '~<!--.*?-->|<(script|style|template)\b[^>]*>.*?</\1\s*>|</?section\b(?:"[^"]*"|\'[^\']*\'|[^\'">])*>~is',
        $content,
        $tags,
        PREG_OFFSET_CAPTURE,
        $start
    );

    if ( ! $matched ) {
        return $content;
    }

    $depth = 0;
    foreach ( $tags[0] as $tag ) {
        if ( ! preg_match( '~^</?section\b~i', $tag[0] ) ) {
            continue;
        }

        $depth += 0 === strpos( $tag[0], '</' ) ? -1 : 1;
        if ( 0 !== $depth ) {
            continue;
        }

        $end   = $tag[1] + strlen( $tag[0] );
        $after = substr( $content, $end );
        if ( '' === trim( $after ) ) {
            return $content;
        }

        $promotions = substr( $content, $start, $end - $start );
        return substr( $content, 0, $start ) . $after . "\n" . $promotions;
    }

    return $content;
}

add_filter( 'the_content', static function ( $content ) {
    if ( ! is_cart() || ! in_the_loop() || ! is_main_query() ) {
        return $content;
    }

    return cvl_cart_promotions_last( $content );
}, 99 );

add_action( 'wp_enqueue_scripts', static function () {
    wp_enqueue_style(
        'cvl-cart-sections',
        get_template_directory_uri() . '/assets/css/cart-sections.css',
        array( 'cvl-cart' ),
        cvl_asset_version( 'assets/css/cart-sections.css' )
    );
}, 20 );
