<?php
declare(strict_types=1);

$root = '/home/chavevertical-loja/htdocs/loja.chavevertical.com';
require_once $root . '/wp-load.php';

$result = array(
    'home_url' => home_url('/'),
    'stylesheet' => get_stylesheet(),
    'taxonomy_exists' => taxonomy_exists('product_brand'),
    'brand_count' => taxonomy_exists('product_brand') ? wp_count_terms(array('taxonomy'=>'product_brand','hide_empty'=>false)) : null,
);

$page = get_page_by_path('marcas', OBJECT, 'page');

if ($page instanceof WP_Post) {
    $result['page'] = array(
        'id' => (int) $page->ID,
        'status' => $page->post_status,
        'slug' => $page->post_name,
        'permalink' => get_permalink($page->ID),
        'template' => get_post_meta($page->ID, '_wp_page_template', true),
    );
} else {
    $result['page'] = null;
}

$result['template_file_exists'] = is_file(get_stylesheet_directory() . '/page-marcas.php');

$result['products'] = array(
    'published' => 0,
    'with_brand' => 0,
    'without_brand' => 0,
    'samples_with_brand' => array(),
    'samples_without_brand' => array(),
);

if ( post_type_exists( 'product' ) ) {
    $published_ids = get_posts( array(
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
    ) );

    $result['products']['published'] = count( $published_ids );

    foreach ( $published_ids as $product_id ) {
        $terms = taxonomy_exists( 'product_brand' )
            ? wp_get_post_terms( $product_id, 'product_brand' )
            : array();

        $has_brand = ! is_wp_error( $terms ) && ! empty( $terms );

        if ( $has_brand ) {
            $result['products']['with_brand']++;

            if ( count( $result['products']['samples_with_brand'] ) < 10 ) {
                $result['products']['samples_with_brand'][] = array(
                    'id' => (int) $product_id,
                    'sku' => (string) get_post_meta( $product_id, '_sku', true ),
                    'title' => get_the_title( $product_id ),
                    'brands' => wp_list_pluck( $terms, 'name' ),
                );
            }
        } else {
            $result['products']['without_brand']++;

            if ( count( $result['products']['samples_without_brand'] ) < 10 ) {
                $meta = get_post_meta( $product_id );
                $brandish = array();

                foreach ( $meta as $key => $values ) {
                    if (
                        stripos( (string) $key, 'brand' ) !== false
                        || stripos( (string) $key, 'manufacturer' ) !== false
                        || stripos( (string) $key, 'marca' ) !== false
                    ) {
                        $brandish[ $key ] = array_slice( array_map( 'strval', (array) $values ), 0, 3 );
                    }
                }

                $result['products']['samples_without_brand'][] = array(
                    'id' => (int) $product_id,
                    'sku' => (string) get_post_meta( $product_id, '_sku', true ),
                    'title' => get_the_title( $product_id ),
                    'brandish_meta' => $brandish,
                );
            }
        }
    }
}


$target = isset($result['page']['permalink']) ? $result['page']['permalink'] : home_url('/?pagename=marcas');

$frontend_urls = array(
    'home' => home_url( '/' ),
    'shop' => function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' ),
);

$result['frontend_brand_render'] = array();

foreach ( $frontend_urls as $frontend_key => $frontend_url ) {
    $frontend_response = wp_remote_get( $frontend_url, array(
        'timeout' => 20,
        'redirection' => 3,
        'headers' => array(
            'Cache-Control' => 'no-cache',
        ),
    ) );

    if ( is_wp_error( $frontend_response ) ) {
        $result['frontend_brand_render'][ $frontend_key ] = array(
            'url' => $frontend_url,
            'error' => $frontend_response->get_error_message(),
        );
        continue;
    }

    $frontend_body = (string) wp_remote_retrieve_body( $frontend_response );
    $result['frontend_brand_render'][ $frontend_key ] = array(
        'url' => $frontend_url,
        'status' => (int) wp_remote_retrieve_response_code( $frontend_response ),
        'product_cards' => substr_count( $frontend_body, 'class="product ' ),
        'brand_rows' => substr_count( $frontend_body, 'cvl-product-brand-row' ),
        'brand_logos' => substr_count( $frontend_body, 'cvl-product-brand-logo' ),
        'brand_names' => substr_count( $frontend_body, 'cvl-product-brand-name' ),
        'v13_loaded' => false !== strpos( $frontend_body, '/assets/css/v13.css' ),
        'theme_version_loaded' => false !== strpos( $frontend_body, 'ver=0.15.3' ),
    );
}


$front_page_file = get_stylesheet_directory() . '/front-page.php';
$front_page_source = is_file( $front_page_file ) ? (string) file_get_contents( $front_page_file ) : '';

$result['home_layout_check'] = array(
    'front_page_file' => $front_page_file,
    'front_page_file_exists' => is_file( $front_page_file ),
    'template_has_promo_block' => false !== strpos( $front_page_source, 'cvl-home-promo-products' ),
    'template_has_recent_after_brands' => false !== strpos( $front_page_source, 'cvl-home-recent-products' ),
    'template_has_limit_16' => false !== strpos( $front_page_source, "'limit'   => 16" ),
    'template_has_limit_8' => false !== strpos( $front_page_source, "'limit'   => 8" ),
);

$home_probe_urls = array(
    'public' => home_url( '/' ),
    'cache_bust' => add_query_arg( 'cvl_home_probe', (string) time(), home_url( '/' ) ),
);

foreach ( $home_probe_urls as $probe_key => $probe_url ) {
    $probe_response = wp_remote_get( $probe_url, array(
        'timeout' => 20,
        'redirection' => 3,
        'headers' => array(
            'Cache-Control' => 'no-cache',
            'Pragma' => 'no-cache',
        ),
    ) );

    if ( is_wp_error( $probe_response ) ) {
        $result['home_layout_check'][ $probe_key ] = array(
            'url' => $probe_url,
            'error' => $probe_response->get_error_message(),
        );
        continue;
    }

    $probe_body = (string) wp_remote_retrieve_body( $probe_response );
    $result['home_layout_check'][ $probe_key ] = array(
        'url' => $probe_url,
        'status' => (int) wp_remote_retrieve_response_code( $probe_response ),
        'promo_section' => substr_count( $probe_body, 'cvl-home-promo-products' ),
        'recent_section' => substr_count( $probe_body, 'cvl-home-recent-products' ),
        'promo_heading' => substr_count( $probe_body, 'Oportunidades em destaque' ),
        'recent_heading' => substr_count( $probe_body, 'Produtos recentes' ),
        'brand_section' => substr_count( $probe_body, 'cvl-brand-carousel-section' ),
        'theme_0155' => false !== strpos( $probe_body, 'ver=0.15.5' ),
        'cache_status_header' => wp_remote_retrieve_header( $probe_response, 'cf-cache-status' ),
        'age_header' => wp_remote_retrieve_header( $probe_response, 'age' ),
    );
}

$response = wp_remote_get($target, array('timeout' => 20, 'redirection' => 3));

if (is_wp_error($response)) {
    $result['http_error'] = $response->get_error_message();
} else {
    $body = (string) wp_remote_retrieve_body($response);
    $result['http_status'] = (int) wp_remote_retrieve_response_code($response);
    $result['renders_brands_template'] = false !== strpos($body, 'cvl-brands-page');
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
