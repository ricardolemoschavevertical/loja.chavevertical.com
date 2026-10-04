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


/* Product page visual-layout probe: verifies the live HTML actually contains
 * the selected mockup structure and that the expected stylesheet version loads.
 */
$result['product_layout_check'] = array();

if ( function_exists( 'wc_get_product_id_by_sku' ) ) {
    $probe_product_id = (int) 3562;

    if ( $probe_product_id > 0 ) {
        $probe_product_url = get_permalink( $probe_product_id );
        $probe_product_response = wp_remote_get(
            add_query_arg( 'cvl_product_probe', (string) time(), $probe_product_url ),
            array(
                'timeout' => 25,
                'redirection' => 5,
                'headers' => array(
                    'Cache-Control' => 'no-cache',
                    'Pragma' => 'no-cache',
                ),
            )
        );

        $result['product_layout_check']['product_id'] = $probe_product_id;
        $result['product_layout_check']['url'] = $probe_product_url;

        if ( is_wp_error( $probe_product_response ) ) {
            $result['product_layout_check']['error'] = $probe_product_response->get_error_message();
        } else {
            $probe_product_body = (string) wp_remote_retrieve_body( $probe_product_response );
            $result['product_layout_check']['status'] = (int) wp_remote_retrieve_response_code( $probe_product_response );
            $result['product_layout_check']['theme_version'] = defined( 'CVL_VERSION' ) ? CVL_VERSION : null;
            $result['product_layout_check']['v10_loaded'] = false !== strpos( $probe_product_body, '/assets/css/v10.css' );
            $result['product_layout_check']['version_loaded'] = defined( 'CVL_VERSION' )
                ? false !== strpos( $probe_product_body, 'ver=' . rawurlencode( CVL_VERSION ) )
                : null;
            $result['product_layout_check']['grid'] = substr_count( $probe_product_body, 'cvl-product-detail-grid' );
            $result['product_layout_check']['gallery'] = substr_count( $probe_product_body, 'cvl-product-gallery-wrap' );
            $result['product_layout_check']['summary'] = substr_count( $probe_product_body, 'cvl-product-summary' );
            $result['product_layout_check']['service_strip'] = substr_count( $probe_product_body, 'cvl-single-service-strip' );
            $result['product_layout_check']['brand_block'] = substr_count( $probe_product_body, 'cvl-single-brand-block' );
            $result['product_layout_check']['price_box'] = substr_count( $probe_product_body, 'cvl-single-price-box' );
            $result['product_layout_check']['short_description'] = substr_count( $probe_product_body, 'woocommerce-product-details__short-description' );
            $result['product_layout_check']['info_panel'] = substr_count( $probe_product_body, 'cvl-single-info-panel' );
            $result['product_layout_check']['quote_button'] = substr_count( $probe_product_body, 'cvl-single-quote-button' );
            $result['product_layout_check']['trust_strip'] = substr_count( $probe_product_body, 'cvl-single-trust-strip' );
            $result['product_layout_check']['summary_tabs'] = substr_count( $probe_product_body, 'cvl-product-summary-tabs' );
            $result['product_layout_check']['stock_badge_one'] = false !== strpos( $probe_product_body, 'SÓ 1 EM STOCK' );
            $result['product_layout_check']['body_length'] = strlen( $probe_product_body );

            if ( preg_match( '/<link[^>]+href=["\']([^"\']*v10\.css[^"\']*)["\']/i', $probe_product_body, $m ) ) {
                $result['product_layout_check']['v10_href'] = html_entity_decode( $m[1], ENT_QUOTES );
            }

            if ( preg_match( '/<div id="product-' . preg_quote( (string) $probe_product_id, '/' ) . '"[\s\S]{0,25000}/i', $probe_product_body, $m ) ) {
                $result['product_layout_check']['product_html_sample'] = substr(
                    preg_replace( '/\s+/', ' ', $m[0] ),
                    0,
                    5000
                );
            }
        }
    } else {
        $result['product_layout_check']['error'] = 'Probe product 3562 not found.';
    }
}


/* Exact live product-width probe for the reported product page. */
$result['exact_product_width_probe'] = array();
$exact_product_url = add_query_arg(
    'cvl_width_probe',
    (string) time(),
    home_url( '/produto/serra-de-mesa-circular-deslizante-holzmann-kf315vf2600/' )
);
$exact_product_response = wp_remote_get(
    $exact_product_url,
    array(
        'timeout' => 25,
        'redirection' => 5,
        'headers' => array(
            'Cache-Control' => 'no-cache',
            'Pragma' => 'no-cache',
        ),
    )
);

if ( is_wp_error( $exact_product_response ) ) {
    $result['exact_product_width_probe']['error'] = $exact_product_response->get_error_message();
} else {
    $exact_body = (string) wp_remote_retrieve_body( $exact_product_response );
    $result['exact_product_width_probe']['url'] = $exact_product_url;
    $result['exact_product_width_probe']['status'] = (int) wp_remote_retrieve_response_code( $exact_product_response );
    $result['exact_product_width_probe']['theme_version'] = defined( 'CVL_VERSION' ) ? CVL_VERSION : null;

    foreach ( array( 'v05.css', 'v10.css' ) as $probe_css_file ) {
        if ( preg_match( "~<link[^>]+href=[\\\"']([^\\\"']*" . preg_quote( $probe_css_file, "~" ) . "[^\\\"']*)[\\\"']~i", $exact_body, $m ) ) {
            $css_href = html_entity_decode( $m[1], ENT_QUOTES );
            $result['exact_product_width_probe'][ str_replace( '.', '_', $probe_css_file ) . '_href' ] = $css_href;
            $css_response = wp_remote_get(
                $css_href,
                array(
                    'timeout' => 20,
                    'redirection' => 3,
                    'headers' => array(
                        'Cache-Control' => 'no-cache',
                        'Pragma' => 'no-cache',
                    ),
                )
            );

            if ( ! is_wp_error( $css_response ) ) {
                $css_body = (string) wp_remote_retrieve_body( $css_response );
                $key = str_replace( '.', '_', $probe_css_file );
                $result['exact_product_width_probe'][ $key . '_status' ] = (int) wp_remote_retrieve_response_code( $css_response );
                $result['exact_product_width_probe'][ $key . '_bytes' ] = strlen( $css_body );
                if ( 'v05.css' === $probe_css_file ) {
                    $result['exact_product_width_probe']['v05_global_shell_unlimited'] =
                        false !== strpos( $css_body, '.cvl-shell{width:var(--cvl-shell);max-width:none;margin-inline:auto}' );
                }
                if ( 'v10.css' === $probe_css_file ) {
                    $result['exact_product_width_probe']['v10_product_shell_shared'] =
                        false !== strpos( $css_body, 'width:var(--cvl-shell)!important' )
                        && false !== strpos( $css_body, 'max-width:var(--cvl-ref-site)!important' );
                    $result['exact_product_width_probe']['v10_grid_full_width'] =
                        false !== strpos( $css_body, '.single-product .cvl-product-detail-grid{' )
                        && false !== strpos( $css_body, 'width:100%!important' );
                }
            }
        }
    }

    if ( class_exists( 'DOMDocument' ) ) {
        $dom = new DOMDocument();
        libxml_use_internal_errors( true );
        $dom->loadHTML( $exact_body );
        libxml_clear_errors();
        $xpath = new DOMXPath( $dom );

        $product_nodes = $xpath->query( '//*[starts-with(@id,"product-") and contains(concat(" ", normalize-space(@class), " "), " cvl-product-detail ")]' );
        $related_nodes = $xpath->query( '//*[contains(concat(" ", normalize-space(@class), " "), " related ") and contains(concat(" ", normalize-space(@class), " "), " products ")]' );
        $shell_nodes = $xpath->query( '//*[contains(concat(" ", normalize-space(@class), " "), " cvl-woocommerce-shell ")]' );
        $grid_nodes = $xpath->query( '//*[contains(concat(" ", normalize-space(@class), " "), " cvl-product-detail-grid ")]' );

        $result['exact_product_width_probe']['product_nodes'] = $product_nodes ? $product_nodes->length : 0;
        $result['exact_product_width_probe']['related_nodes'] = $related_nodes ? $related_nodes->length : 0;
        $result['exact_product_width_probe']['shell_nodes'] = $shell_nodes ? $shell_nodes->length : 0;
        $result['exact_product_width_probe']['grid_nodes'] = $grid_nodes ? $grid_nodes->length : 0;

        if ( $product_nodes && $product_nodes->length ) {
            $node = $product_nodes->item( 0 );
            $ancestors = array();
            $parent = $node->parentNode;
            $depth = 0;
            while ( $parent && $depth < 8 ) {
                if ( XML_ELEMENT_NODE === $parent->nodeType ) {
                    $ancestors[] = array(
                        'tag' => strtolower( $parent->nodeName ),
                        'id' => $parent->attributes && $parent->attributes->getNamedItem( 'id' )
                            ? $parent->attributes->getNamedItem( 'id' )->nodeValue
                            : '',
                        'class' => $parent->attributes && $parent->attributes->getNamedItem( 'class' )
                            ? $parent->attributes->getNamedItem( 'class' )->nodeValue
                            : '',
                    );
                    $depth++;
                }
                $parent = $parent->parentNode;
            }
            $result['exact_product_width_probe']['product_ancestors'] = $ancestors;

            if ( $related_nodes && $related_nodes->length ) {
                $related = $related_nodes->item( 0 );
                $result['exact_product_width_probe']['related_inside_product'] = $node->contains( $related );
                $result['exact_product_width_probe']['related_parent_class'] =
                    $related->parentNode && $related->parentNode->attributes && $related->parentNode->attributes->getNamedItem( 'class' )
                    ? $related->parentNode->attributes->getNamedItem( 'class' )->nodeValue
                    : '';
            }
        }
    }
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
