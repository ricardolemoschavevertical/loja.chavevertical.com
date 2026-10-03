<?php
defined( 'ABSPATH' ) || exit;

define( 'CVL_VERSION', '0.1.0' );

add_action( 'after_setup_theme', function () {
    load_theme_textdomain( 'chavevertical-lite', get_template_directory() . '/languages' );

    add_theme_support( 'title-tag' );
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );
    add_theme_support( 'custom-logo', array(
        'height'      => 60,
        'width'       => 240,
        'flex-height' => true,
        'flex-width'  => true,
    ) );

    add_theme_support( 'woocommerce' );
    add_theme_support( 'wc-product-gallery-zoom' );
    add_theme_support( 'wc-product-gallery-lightbox' );
    add_theme_support( 'wc-product-gallery-slider' );

    register_nav_menus( array(
        'primary' => __( 'Menu principal', 'chavevertical-lite' ),
        'footer'  => __( 'Menu rodapé', 'chavevertical-lite' ),
    ) );
} );

add_action( 'wp_enqueue_scripts', function () {
    wp_enqueue_style(
        'cvl-main',
        get_template_directory_uri() . '/assets/css/main.css',
        array(),
        CVL_VERSION
    );

    wp_enqueue_script(
        'cvl-main',
        get_template_directory_uri() . '/assets/js/main.js',
        array(),
        CVL_VERSION,
        true
    );
} );

add_filter( 'woocommerce_enqueue_styles', function ( $styles ) {
    unset( $styles['woocommerce-general'] );
    unset( $styles['woocommerce-layout'] );
    unset( $styles['woocommerce-smallscreen'] );
    return $styles;
} );

add_filter( 'body_class', function ( $classes ) {
    $classes[] = 'cvl-site';
    return $classes;
} );

function cvl_cart_url() {
    return function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' );
}

function cvl_account_url() {
    return function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/my-account/' );
}

function cvl_shop_url() {
    return function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' );
}

function cvl_cart_count() {
    if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
        return 0;
    }
    return WC()->cart->get_cart_contents_count();
}

add_filter( 'woocommerce_add_to_cart_fragments', function ( $fragments ) {
    ob_start();
    ?>
    <span class="cvl-cart-count"><?php echo esc_html( cvl_cart_count() ); ?></span>
    <?php
    $fragments['span.cvl-cart-count'] = ob_get_clean();
    return $fragments;
} );

add_action( 'widgets_init', function () {
    register_sidebar( array(
        'name'          => __( 'Rodapé', 'chavevertical-lite' ),
        'id'            => 'footer-widgets',
        'before_widget' => '<section class="cvl-footer-widget">',
        'after_widget'  => '</section>',
        'before_title'  => '<h3>',
        'after_title'   => '</h3>',
    ) );
} );
