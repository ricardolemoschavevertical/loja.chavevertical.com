<?php
defined( 'ABSPATH' ) || exit;

define( 'CVL_VERSION', '0.16.109' );

function cvl_asset_version( $relative_path = '' ) {
    $relative_path = ltrim( (string) $relative_path, '/' );

    if ( $relative_path ) {
        $file = get_template_directory() . '/' . $relative_path;

        if ( is_file( $file ) ) {
            return CVL_VERSION . '-' . (string) filemtime( $file );
        }
    }

    return CVL_VERSION;
}

$cvl_homepage_highlights_file = get_template_directory() . '/inc/homepage-highlights.php';
if ( file_exists( $cvl_homepage_highlights_file ) ) {
    require_once $cvl_homepage_highlights_file;
}

$cvl_best_price_catalog_file = get_template_directory() . '/inc/best-price-catalog.php';
if ( file_exists( $cvl_best_price_catalog_file ) ) {
    require_once $cvl_best_price_catalog_file;
}

$cvl_promotion_catalog_file = get_template_directory() . '/inc/promotion-catalog.php';
if ( file_exists( $cvl_promotion_catalog_file ) ) {
    require_once $cvl_promotion_catalog_file;
}

$cvl_brand_archive_file = get_template_directory() . '/inc/brand-archive.php';
if ( file_exists( $cvl_brand_archive_file ) ) {
    require_once $cvl_brand_archive_file;
}

$cvl_contact_form_file = get_template_directory() . '/inc/contact-form.php';
if ( file_exists( $cvl_contact_form_file ) ) {
    require_once $cvl_contact_form_file;
}

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
        cvl_asset_version( 'assets/css/main.css' )
    );

    wp_enqueue_style(
        'cvl-v04',
        get_template_directory_uri() . '/assets/css/v04.css',
        array( 'cvl-main' ),
        cvl_asset_version( 'assets/css/v04.css' )
    );

    wp_enqueue_style(
        'cvl-v05',
        get_template_directory_uri() . '/assets/css/v05.css',
        array( 'cvl-v04' ),
        cvl_asset_version( 'assets/css/v05.css' )
    );

    wp_enqueue_style(
        'cvl-v06',
        get_template_directory_uri() . '/assets/css/v06.css',
        array( 'cvl-v05' ),
        cvl_asset_version( 'assets/css/v06.css' )
    );

    wp_enqueue_style(
        'cvl-v07',
        get_template_directory_uri() . '/assets/css/v07.css',
        array( 'cvl-v06' ),
        cvl_asset_version( 'assets/css/v07.css' )
    );

    wp_enqueue_style(
        'cvl-v08',
        get_template_directory_uri() . '/assets/css/v08.css',
        array( 'cvl-v07' ),
        cvl_asset_version( 'assets/css/v08.css' )
    );

    wp_enqueue_style(
        'cvl-v09',
        get_template_directory_uri() . '/assets/css/v09.css',
        array( 'cvl-v08' ),
        cvl_asset_version( 'assets/css/v09.css' )
    );

    if ( function_exists( 'is_product' ) && is_product() ) {
        wp_enqueue_style(
            'cvl-v10',
            get_template_directory_uri() . '/assets/css/v10.css',
            array( 'cvl-v09' ),
            cvl_asset_version( 'assets/css/v10.css' )
        );

        wp_enqueue_script(
            'cvl-product',
            get_template_directory_uri() . '/assets/js/product.js',
            array(),
            cvl_asset_version( 'assets/js/product.js' ),
            true
        );
    }

    if (
        ( function_exists( 'is_product_category' ) && is_product_category() )
        || ( taxonomy_exists( 'product_brand' ) && is_tax( 'product_brand' ) )
        || ( function_exists( 'cvl_is_promotion_catalog_request' ) && cvl_is_promotion_catalog_request() )
        || ( function_exists( 'cvl_is_best_price_catalog_request' ) && cvl_is_best_price_catalog_request() )
    ) {
        wp_enqueue_script(
            'cvl-search-results',
            get_template_directory_uri() . '/assets/js/search-results.js',
            array(),
            cvl_asset_version( 'assets/js/search-results.js' ),
            true
        );

        wp_enqueue_script(
            'cvl-category',
            get_template_directory_uri() . '/assets/js/category.js',
            array(),
            cvl_asset_version( 'assets/js/category.js' ),
            true
        );
    }

    if ( is_page_template( 'page-marcas.php' ) || is_page( 'marcas' ) ) {
        wp_enqueue_script(
            'cvl-brands',
            get_template_directory_uri() . '/assets/js/brands.js',
            array(),
            cvl_asset_version( 'assets/js/brands.js' ),
            true
        );
    }

    if ( is_page_template( 'page-contactos.php' ) || is_page( 'contactos' ) ) {
        wp_enqueue_style(
            'cvl-v12',
            get_template_directory_uri() . '/assets/css/v12.css',
            array( 'cvl-v09' ),
            cvl_asset_version( 'assets/css/v12.css' )
        );
    }

    /*
     * Cartões de produto — réplica visual da definição final do CV-Shopware.
     * Carrega depois do CSS específico de categorias para que a listagem use
     * uma única apresentação em shop, categorias, pesquisa e blocos WooCommerce.
     */
    $cvl_listing_style_dependencies = array( 'cvl-v09' );

    if ( wp_style_is( 'cvl-v11', 'enqueued' ) ) {
        $cvl_listing_style_dependencies[] = 'cvl-v11';
    }

    wp_enqueue_style(
        'cvl-v13',
        get_template_directory_uri() . '/assets/css/v13.css',
        $cvl_listing_style_dependencies,
        cvl_asset_version( 'assets/css/v13.css' )
    );

    if ( is_front_page() ) {
        wp_enqueue_script(
            'cvl-homepage-highlights',
            get_template_directory_uri() . '/assets/js/homepage-highlights.js',
            array(),
            cvl_asset_version( 'assets/js/homepage-highlights.js' ),
            true
        );
    }

    wp_enqueue_script(
        'cvl-main',
        get_template_directory_uri() . '/assets/js/main.js',
        array(),
        cvl_asset_version( 'assets/js/main.js' ),
        true
    );

    if (
        ( is_search() && 'product' === get_query_var( 'post_type' ) )
        || ( taxonomy_exists( 'product_brand' ) && is_tax( 'product_brand' ) )
        || ( function_exists( 'cvl_is_promotion_catalog_request' ) && cvl_is_promotion_catalog_request() )
        || ( function_exists( 'cvl_is_best_price_catalog_request' ) && cvl_is_best_price_catalog_request() )
    ) {
        wp_enqueue_script(
            'cvl-search-results',
            get_template_directory_uri() . '/assets/js/search-results.js',
            array(),
            cvl_asset_version( 'assets/js/search-results.js' ),
            true
        );
    }
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


/**
 * A pesquisa de produtos deve permanecer numa página de resultados, mesmo
 * quando existe apenas um produto, para manter filtros e contexto.
 */
add_filter(
    'woocommerce_redirect_single_search_result',
    static function ( $redirect ) {
        return is_search() ? false : $redirect;
    },
    20
);

/**
 * O WooCommerce tenta usar o template de arquivo para pesquisas de produto.
 * Forçamos o search.php do tema, onde os filtros são alimentados pelo
 * OpenSearch e só são renderizados produtos.
 */
add_filter(
    'template_include',
    static function ( $template ) {
        if ( is_admin() || ! is_search() || 'product' !== get_query_var( 'post_type' ) ) {
            return $template;
        }

        $search_template = get_template_directory() . '/search.php';
        return is_readable( $search_template ) ? $search_template : $template;
    },
    999
);

/**
 * Placeholder visual para produtos WooCommerce sem imagem.
 * Mantém a imagem dentro do tema para ser versionada e implantada com o site.
 */
function cvl_product_placeholder_url() {
    return get_template_directory_uri() . '/assets/images/chavevertical-placeholder-produto.webp';
}

add_filter( 'woocommerce_placeholder_img_src', function ( $src ) {
    return cvl_product_placeholder_url();
} );

function cvl_cart_url() {
    return function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' );
}

function cvl_account_url() {
    return function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/my-account/' );
}

function cvl_wishlist_url() {
    $favorites_page = get_page_by_path( 'favoritos', OBJECT, 'page' );

    if ( $favorites_page instanceof WP_Post ) {
        return get_permalink( $favorites_page->ID );
    }

    if ( function_exists( 'tinv_url_wishlist_default' ) ) {
        $url = tinv_url_wishlist_default();

        if ( ! empty( $url ) ) {
            return $url;
        }
    }

    $yith_page_id = absint( get_option( 'yith_wcwl_wishlist_page_id' ) );

    if ( $yith_page_id ) {
        $url = get_permalink( $yith_page_id );

        if ( $url ) {
            return $url;
        }
    }

    foreach ( array( 'lista-de-desejos', 'favoritos', 'wishlist' ) as $slug ) {
        $page = get_page_by_path( $slug, OBJECT, 'page' );

        if ( $page instanceof WP_Post ) {
            return get_permalink( $page->ID );
        }
    }

    return home_url( '/lista-de-desejos/' );
}

function cvl_shop_url() {
    return function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' );
}

function cvl_brands_page_url() {
    $page = get_page_by_path( 'marcas', OBJECT, 'page' );

    if ( $page instanceof WP_Post ) {
        return get_permalink( $page->ID );
    }

    return home_url( '/?pagename=marcas' );
}

function cvl_contact_page_url() {
    $page = get_page_by_path( 'contactos', OBJECT, 'page' );

    if ( $page instanceof WP_Post ) {
        return get_permalink( $page->ID );
    }

    return home_url( '/?pagename=contactos' );
}

function cvl_about_page_url() {
    foreach ( array( 'quem-somos', 'sobre-nos', 'sobrenos' ) as $slug ) {
        $page = get_page_by_path( $slug, OBJECT, 'page' );

        if ( $page instanceof WP_Post ) {
            return get_permalink( $page->ID );
        }
    }

    return home_url( '/quem-somos/' );
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

/**
 * Dados da marca do produto.
 * Usa a taxonomia product_brand quando existir e mostra o respetivo
 * thumbnail_id quando estiver disponível. Caso contrário devolve só o nome.
 */
function cvl_get_product_brand( $product_id ) {
    if ( ! taxonomy_exists( 'product_brand' ) ) {
        return null;
    }

    $terms = wp_get_post_terms( $product_id, 'product_brand' );

    if ( is_wp_error( $terms ) || empty( $terms ) ) {
        return null;
    }

    $term         = reset( $terms );
    $thumbnail_id = absint( get_term_meta( $term->term_id, 'thumbnail_id', true ) );

    return array(
        'name'         => $term->name,
        'url'          => get_term_link( $term ),
        'thumbnail_id' => $thumbnail_id,
    );
}

function cvl_get_product_primary_category( $product_id ) {
    $terms = wp_get_post_terms( $product_id, 'product_cat' );

    if ( is_wp_error( $terms ) || empty( $terms ) ) {
        return null;
    }

    foreach ( $terms as $term ) {
        if ( ! in_array( strtolower( $term->name ), array( 'uncategorized', 'predefinido' ), true ) ) {
            return $term;
        }
    }

    return reset( $terms );
}

/**
 * Card de produto: categoria clicável por cima do título.
 *
 * O WooCommerce abre o link do produto antes da imagem. Para evitar um <a>
 * dentro de outro <a>, fechamos esse link após a imagem, mostramos a categoria
 * como link próprio e reabrimos o link do produto para o restante conteúdo.
 */
function cvl_loop_product_category() {
    global $product;

    // Na página de pesquisa, a categoria pertence aos filtros laterais.
    if ( is_search() ) {
        return;
    }

    if ( ! class_exists( 'WC_Product' ) || ! $product instanceof WC_Product ) {
        return;
    }

    $category     = cvl_get_product_primary_category( $product->get_id() );
    $product_url  = $product->get_permalink();

    // Fecha o link do produto que envolve a imagem.
    echo '</a>';

    if ( $category instanceof WP_Term ) {
        $category_url = get_term_link( $category );

        if ( ! is_wp_error( $category_url ) ) {
            echo '<a class="cvl-product-category" href="' . esc_url( $category_url ) . '">' . esc_html( $category->name ) . '</a>';
        }
    }

    // Reabre o link do produto para título, marca, referência e preço.
    echo '<a class="woocommerce-LoopProduct-link woocommerce-loop-product__link cvl-product-content-link" href="' . esc_url( $product_url ) . '">';
}
add_action( 'woocommerce_before_shop_loop_item_title', 'cvl_loop_product_category', 20 );

add_filter( 'woocommerce_post_class', function ( $classes, $product ) {
    if ( ! is_search() && class_exists( 'WC_Product' ) && $product instanceof WC_Product ) {
        $classes[] = 'cvl-product-split-card';
    }

    return $classes;
}, 10, 2 );

/**
 * Card de produto: marca/logótipo centrado e referência numa linha própria,
 * seguindo a hierarquia visual do storefront Shopware.
 */
function cvl_loop_product_meta() {
    global $product;

    if ( ! class_exists( 'WC_Product' ) || ! $product instanceof WC_Product ) {
        return;
    }

    $sku   = $product->get_sku();
    $brand = cvl_get_product_brand( $product->get_id() );

    echo '<div class="cvl-product-brand-sku-row">';

    echo '<span class="cvl-product-brand"';
    if ( $brand ) {
        echo ' aria-label="' . esc_attr( $brand['name'] ) . '"';
    }
    echo '>';

    if ( $brand ) {
        if ( $brand['thumbnail_id'] ) {
            echo wp_kses_post(
                wp_get_attachment_image(
                    $brand['thumbnail_id'],
                    'medium',
                    false,
                    array(
                        'class'   => 'cvl-product-brand-logo',
                        'loading' => 'lazy',
                        'alt'     => $brand['name'],
                    )
                )
            );
        } else {
            echo '<span class="cvl-product-brand-name">' . esc_html( $brand['name'] ) . '</span>';
        }
    }

    echo '</span>';

    if ( $sku ) {
        echo '<span class="cvl-product-sku" title="' . esc_attr( $sku ) . '"><i class="cvl-product-barcode-icon" aria-hidden="true"></i><span class="cvl-product-sku-text">' . esc_html( $sku ) . '</span></span>';
    }

    echo '</div>';
}
add_action( 'woocommerce_after_shop_loop_item_title', 'cvl_loop_product_meta', 4 );

/**
 * Dados da etiqueta comercial sobre a imagem.
 *
 * Público:
 * - sem stock: sem etiqueta;
 * - 1 unidade: SÓ 1 EM STOCK;
 * - 2 unidades: ÚLTIMAS UNIDADES;
 * - destaque: MELHOR PREÇO!;
 * - por encomenda: sem etiqueta pública;
 * - restantes estados mantêm promoção / stock.
 *
 * Clientes só veem quantidade exata quando existe 1 unidade.
 * Utilizadores com gestão WooCommerce veem a quantidade real quando
 * existe stock gerido; com stock zero não aparece qualquer etiqueta.
 */
function cvl_product_image_badge_data( WC_Product $product ) {
    if ( $product->get_featured() ) {
        return array(
            'class' => 'is-featured',
            'label' => __( 'MELHOR PREÇO!', 'chavevertical-lite' ),
        );
    }

    if ( $product->is_on_sale() ) {
        return array(
            'class' => 'is-sale',
            'label' => __( 'PROMOÇÃO', 'chavevertical-lite' ),
        );
    }

    return null;
}

/**
 * Etiqueta comercial centrada sobre a imagem dos cards.
 */
function cvl_loop_product_badge() {
    global $product;

    if ( ! class_exists( 'WC_Product' ) || ! $product instanceof WC_Product ) {
        return;
    }

    $badge = cvl_product_image_badge_data( $product );

    if ( empty( $badge ) ) {
        return;
    }

    echo '<span class="cvl-product-badge ' . esc_attr( $badge['class'] ) . '">' . esc_html( $badge['label'] ) . '</span>';
}
add_action( 'woocommerce_before_shop_loop_item_title', 'cvl_loop_product_badge', 5 );

add_action( 'wp', function () {
    remove_action( 'woocommerce_before_shop_loop_item_title', 'woocommerce_show_product_loop_sale_flash', 10 );
}, 25 );

/**
 * Card de produto: disponibilidade comercial centrada.
 * A avaliação deixa de ser apresentada na listagem.
 */
function cvl_loop_product_stock() {
    global $product;

    if ( ! class_exists( 'WC_Product' ) || ! $product instanceof WC_Product ) {
        return;
    }

    $status             = $product->get_stock_status();
    $backorders_allowed = $product->backorders_allowed();
    $stock_quantity     = $product->managing_stock() ? $product->get_stock_quantity() : null;
    $stock_quantity     = null !== $stock_quantity ? max( 0, (int) $stock_quantity ) : null;
    $can_manage         = is_user_logged_in() && current_user_can( 'manage_woocommerce' );

    $class        = 'is-instock';
    $label        = __( 'Em stock', 'chavevertical-lite' );
    $contact_link = false;

    if ( null !== $stock_quantity && $stock_quantity > 0 ) {
        if ( $can_manage ) {
            $label = 1 === $stock_quantity
                ? __( '1 unidade em stock', 'chavevertical-lite' )
                : sprintf( __( '%d unidades em stock', 'chavevertical-lite' ), $stock_quantity );
        } elseif ( 1 === $stock_quantity ) {
            $label = __( 'Apenas 1 unidade em stock', 'chavevertical-lite' );
        } elseif ( 2 === $stock_quantity ) {
            $label = __( 'Últimas unidades em stock', 'chavevertical-lite' );
        } else {
            $label = __( '3 ou mais unidades em stock', 'chavevertical-lite' );
        }
    } elseif ( 'onbackorder' === $status || $backorders_allowed ) {
        $class        = 'is-backorder';
        $label        = __( 'Encomenda a fornecedor', 'chavevertical-lite' );
        $contact_link = true;
    } elseif ( 'outofstock' === $status || ( null !== $stock_quantity && 0 === $stock_quantity ) ) {
        $class        = 'is-outofstock';
        $label        = __( 'Produto sob consulta', 'chavevertical-lite' );
        $contact_link = true;
    }

    $status_html = '<span aria-hidden="true"></span>' . esc_html( $label );

    if ( $contact_link ) {
        $sku = $product->get_sku();
        $message = sprintf(
            'Olá, pretendo consultar o prazo de entrega do produto %1$s%2$s. %3$s',
            $product->get_name(),
            $sku ? ' (Ref: ' . $sku . ')' : '',
            $product->get_permalink()
        );
        $whatsapp_url = 'https://wa.me/351914580410?text=' . rawurlencode( $message );

        $status_html = '<a class="cvl-product-stock-link" href="' . esc_url( $whatsapp_url ) . '" target="_blank" rel="noopener nofollow">' . $status_html . '</a>';
    }

    echo '<div class="cvl-product-status-row">';
    echo '<div class="cvl-product-stock ' . esc_attr( $class ) . '">' . wp_kses_post( $status_html ) . '</div>';
    echo '</div>';
}
add_action( 'woocommerce_after_shop_loop_item_title', 'cvl_loop_product_stock', 7 );

/**
 * Preço do card: valor com IVA em destaque e preço sem IVA à direita.
 * Não altera qualquer preço do produto; apenas calcula a apresentação.
 */
function cvl_loop_product_price_block() {
    global $product;

    if ( ! class_exists( 'WC_Product' ) || ! $product instanceof WC_Product ) {
        return;
    }

    $is_variable = $product->is_type( 'variable' );

    if ( $is_variable && method_exists( $product, 'get_variation_price' ) ) {
        $raw_price   = (float) $product->get_variation_price( 'min', false );
        $raw_regular = (float) $product->get_variation_regular_price( 'min', false );
    } else {
        $raw_price   = (float) $product->get_price();
        $raw_regular = (float) $product->get_regular_price();
    }

    if ( $raw_price <= 0 ) {
        echo '<div class="cvl-product-price-row is-no-price"><span class="cvl-product-price-current">' . esc_html__( 'Preço sob consulta', 'chavevertical-lite' ) . '</span></div>';
        return;
    }

    $gross_price = (float) wc_get_price_including_tax(
        $product,
        array(
            'price' => $raw_price,
        )
    );
    $net_price = (float) wc_get_price_excluding_tax(
        $product,
        array(
            'price' => $raw_price,
        )
    );

    $gross_regular = 0.0;
    if ( $raw_regular > $raw_price ) {
        $gross_regular = (float) wc_get_price_including_tax(
            $product,
            array(
                'price' => $raw_regular,
            )
        );
    }

    echo '<div class="cvl-product-price-row">';
    echo '<div class="cvl-product-price-primary">';

    if ( $gross_regular > $gross_price ) {
        echo '<del class="cvl-product-price-regular">' . wp_kses_post( wc_price( $gross_regular ) ) . '</del>';
    }

    if ( $is_variable ) {
        echo '<span class="cvl-product-price-from">' . esc_html__( 'Desde', 'chavevertical-lite' ) . '</span>';
    }

    echo '<span class="cvl-product-price-current">' . wp_kses_post( wc_price( $gross_price ) ) . '</span>';
    echo '</div>';

    echo '<div class="cvl-product-price-net">';
    echo '<strong>' . wp_kses_post( wc_price( $net_price ) ) . '</strong>';
    echo '<span>' . esc_html__( 'Preço S/ IVA', 'chavevertical-lite' ) . '</span>';
    echo '</div>';
    echo '</div>';
}

add_action(
    'wp',
    static function (): void {
        remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_rating', 5 );
        remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10 );
    },
    30
);
add_action( 'woocommerce_after_shop_loop_item_title', 'cvl_loop_product_price_block', 10 );

/**
 * Ações do card: uma única ação comercial.
 * Usa ícones SVG para manter escala, alinhamento e aparência consistentes.
 * - compra direta: botão limpo ADICIONAR
 * - restantes produtos: ícone de visualizar + VER
 */
function cvl_loop_actions_open() {
    echo '<div class="cvl-product-actions">';
}
add_action( 'woocommerce_after_shop_loop_item', 'cvl_loop_actions_open', 9 );

function cvl_loop_actions_close() {
    global $product;

    if ( class_exists( 'WC_Product' ) && $product instanceof WC_Product ) {
        $eye_icon = '<svg class="cvl-card-eye-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M1.8 12s3.5-6 10.2-6 10.2 6 10.2 6-3.5 6-10.2 6S1.8 12 1.8 12Z"></path><circle cx="12" cy="12" r="3"></circle></svg>';

        echo '<a class="cvl-card-eye-button" href="' . esc_url( $product->get_permalink() ) . '" aria-label="' . esc_attr( sprintf( __( 'Ver %s', 'chavevertical-lite' ), $product->get_name() ) ) . '">' . $eye_icon . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    echo '</div>';
}
add_action( 'woocommerce_after_shop_loop_item', 'cvl_loop_actions_close', 11 );



/**
 * Botão de favoritos no canto da imagem quando existe um plugin suportado.
 * Sem plugin de wishlist ativo, não apresenta um controlo sem funcionalidade.
 */
function cvl_loop_product_wishlist_button() {
    global $product;

    if ( ! class_exists( 'WC_Product' ) || ! $product instanceof WC_Product ) {
        return;
    }

    $output = '';

    if ( shortcode_exists( 'yith_wcwl_add_to_wishlist' ) ) {
        $output = do_shortcode( '[yith_wcwl_add_to_wishlist product_id="' . absint( $product->get_id() ) . '"]' );
    } elseif ( shortcode_exists( 'ti_wishlists_addtowishlist' ) ) {
        $output = do_shortcode( '[ti_wishlists_addtowishlist product_id="' . absint( $product->get_id() ) . '"]' );
    }

    if ( '' === trim( (string) $output ) ) {
        return;
    }

    echo '<div class="cvl-card-wishlist">' . $output . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
add_action( 'woocommerce_after_shop_loop_item', 'cvl_loop_product_wishlist_button', 12 );

add_filter( 'woocommerce_product_add_to_cart_text', function ( $text, $product ) {
    if ( class_exists( 'WC_Product' ) && $product instanceof WC_Product && $product->is_type( 'simple' ) && $product->is_purchasable() && $product->is_in_stock() ) {
        return __( 'ADICIONAR', 'chavevertical-lite' );
    }

    return __( 'VER PRODUTO', 'chavevertical-lite' );
}, 10, 2 );

add_filter( 'woocommerce_loop_add_to_cart_link', function ( $html, $product ) {
    if ( ! class_exists( 'WC_Product' ) || ! $product instanceof WC_Product ) {
        return $html;
    }

    $can_add_directly = $product->is_type( 'simple' )
        && $product->is_purchasable()
        && $product->is_in_stock();

    $view_icon = '<svg class="cvl-product-action-icon cvl-view-product-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill-rule="evenodd" clip-rule="evenodd" d="M1.32 11.45C2.81 6.98 7.03 3.75 12 3.75s9.19 3.23 10.68 7.7c.12.36.12.75 0 1.1-1.49 4.48-5.71 7.7-10.68 7.7s-9.19-3.22-10.68-7.7a1.75 1.75 0 0 1 0-1.1ZM17.25 12a5.25 5.25 0 1 1-10.5 0 5.25 5.25 0 0 1 10.5 0Z"></path><circle cx="12" cy="12" r="2.45"></circle></svg>';

    if ( ! $can_add_directly ) {
        return sprintf(
            '<a href="%1$s" class="button cvl-view-product" aria-label="%2$s">%3$s<span class="cvl-view-product-label">%4$s</span></a>',
            esc_url( $product->get_permalink() ),
            esc_attr( sprintf( __( 'Ver %s', 'chavevertical-lite' ), $product->get_name() ) ),
            $view_icon,
            esc_html__( 'VER PRODUTO', 'chavevertical-lite' )
        );
    }

    $replacement = '><span class="cvl-cart-button-label">' . esc_html__( 'ADICIONAR', 'chavevertical-lite' ) . '</span></a>';
    $html = preg_replace( '/>[^<]*<\/a>$/', $replacement, $html, 1 );

    if ( is_string( $html ) ) {
        $html = str_replace( 'class="', 'class="cvl-cart-icon-button ', $html );
    }

    return $html;
}, 20, 2 );

/**
 * Ficha de produto — apresentação alinhada com o storefront Shopware.
 *
 * Mantém os hooks e a lógica comercial nativos do WooCommerce; apenas
 * reorganiza a apresentação e acrescenta informação de leitura.
 */
add_action( 'wp', function () {
    if ( ! function_exists( 'is_product' ) || ! is_product() ) {
        return;
    }

    remove_action( 'woocommerce_before_single_product_summary', 'woocommerce_show_product_sale_flash', 10 );
    remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_rating', 10 );
    remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 10 );
    remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40 );
    remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_sharing', 50 );
    remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_product_data_tabs', 10 );
}, 20 );

/**
 * Barra de serviços no topo da coluna de informação, como na referência visual.
 */
function cvl_single_product_service_strip() {
    global $product;

    if ( ! class_exists( 'WC_Product' ) || ! $product instanceof WC_Product ) {
        return;
    }

    echo '<div class="cvl-single-service-strip">';

    echo '<span>';
    echo '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 13v-2a8 8 0 0 1 16 0v2"></path><path d="M4 13h3v6H5a1 1 0 0 1-1-1zM20 13h-3v6h2a1 1 0 0 0 1-1z"></path></svg>';
    echo '<span class="cvl-single-service-copy"><strong>' . esc_html__( 'Apoio técnico especializado', 'chavevertical-lite' ) . '</strong><small>' . esc_html__( 'Fale com a nossa equipa', 'chavevertical-lite' ) . '</small></span>';
    echo '</span>';

    echo '<span>';
    echo '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 7h11v9H3zM14 10h4l3 3v3h-7z"></path><circle cx="7" cy="18" r="2"></circle><circle cx="18" cy="18" r="2"></circle></svg>';
    echo '<span class="cvl-single-service-copy"><strong>' . esc_html__( 'Envio para todo o país', 'chavevertical-lite' ) . '</strong><small>' . esc_html__( 'Consulte as condições de entrega', 'chavevertical-lite' ) . '</small></span>';
    echo '</span>';

    echo '<span>';
    echo '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 4.5 6v5c0 4.9 3 8.2 7.5 10 4.5-1.8 7.5-5.1 7.5-10V6z"></path><path d="m9 12 2 2 4-4"></path></svg>';
    echo '<span class="cvl-single-service-copy"><strong>' . esc_html__( 'Compra online', 'chavevertical-lite' ) . '</strong><small>' . esc_html__( 'Processo de compra integrado', 'chavevertical-lite' ) . '</small></span>';
    echo '</span>';

    echo '<span>';
    echo '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="M3 10h18M7 15h4"></path></svg>';
    echo '<span class="cvl-single-service-copy"><strong>' . esc_html__( 'Pagamento', 'chavevertical-lite' ) . '</strong><small>' . esc_html__( 'Opções disponíveis no checkout', 'chavevertical-lite' ) . '</small></span>';
    echo '</span>';
    echo '</div>';
}
add_action( 'woocommerce_single_product_summary', 'cvl_single_product_service_strip', 4 );

/**
 * Etiqueta comercial centrada sobre a imagem principal.
 */
function cvl_single_product_badge() {
    global $product;

    if ( ! class_exists( 'WC_Product' ) || ! $product instanceof WC_Product ) {
        return;
    }

    $badge = cvl_product_image_badge_data( $product );

    if ( empty( $badge ) ) {
        return;
    }

    echo '<span class="cvl-single-image-badge ' . esc_attr( $badge['class'] ) . '">' . esc_html( $badge['label'] ) . '</span>';
}
add_action( 'woocommerce_before_single_product_summary', 'cvl_single_product_badge', 5 );

/**
 * Referência imediatamente abaixo do título, com ação de copiar.
 */
function cvl_single_product_meta_top() {
    global $product;

    if ( ! class_exists( 'WC_Product' ) || ! $product instanceof WC_Product ) {
        return;
    }

    $sku = $product->get_sku();

    if ( ! $sku ) {
        return;
    }

    echo '<div class="cvl-single-reference">';
    echo '<span class="cvl-single-barcode" aria-hidden="true"></span>';
    echo '<span class="cvl-single-reference-code">' . esc_html( $sku ) . '</span>';
    echo '<button class="cvl-single-copy-sku" type="button" data-cvl-copy-sku="' . esc_attr( $sku ) . '" aria-label="' . esc_attr__( 'Copiar referência', 'chavevertical-lite' ) . '" title="' . esc_attr__( 'Copiar referência', 'chavevertical-lite' ) . '">';
    echo '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 8h10v10H8z"></path><path d="M5 5h10v2H7v8H5z"></path></svg>';
    echo '</button>';
    echo '</div>';
}
add_action( 'woocommerce_single_product_summary', 'cvl_single_product_meta_top', 7 );

/**
 * Rating no mesmo formato visual da referência Shopware.
 */
function cvl_single_product_rating_row() {
    global $product;

    if ( ! class_exists( 'WC_Product' ) || ! $product instanceof WC_Product ) {
        return;
    }

    $average = (float) $product->get_average_rating();
    $count   = (int) $product->get_review_count();
    $filled  = (int) round( $average );

    echo '<div class="cvl-single-rating-row" aria-label="' . esc_attr( sprintf( __( 'Avaliação média: %s em 5', 'chavevertical-lite' ), wc_format_decimal( $average, 1 ) ) ) . '">';
    echo '<span class="cvl-single-stars" aria-hidden="true">';

    for ( $i = 1; $i <= 5; $i++ ) {
        echo $i <= $filled ? '★' : '☆';
    }

    echo '</span>';

    if ( $count > 0 ) {
        echo '<span>(' . esc_html( sprintf( _n( '%d avaliação', '%d avaliações', $count, 'chavevertical-lite' ), $count ) ) . ')</span>';
    } else {
        echo '<span>(' . esc_html__( 'Ainda não existem avaliações.', 'chavevertical-lite' ) . ')</span>';
    }

    echo '</div>';
}


/**
 * Marca / logótipo numa linha própria.
 */
function cvl_single_product_brand_block() {
    global $product;

    if ( ! class_exists( 'WC_Product' ) || ! $product instanceof WC_Product ) {
        return;
    }

    $brand = cvl_get_product_brand( $product->get_id() );

    if ( ! $brand ) {
        return;
    }

    echo '<div class="cvl-single-brand-block">';

    if ( $brand['thumbnail_id'] ) {
        echo wp_kses_post(
            wp_get_attachment_image(
                $brand['thumbnail_id'],
                'medium',
                false,
                array(
                    'class' => 'cvl-single-brand-logo',
                    'alt'   => $brand['name'],
                )
            )
        );
    } else {
        echo '<strong>' . esc_html( $brand['name'] ) . '</strong>';
    }

    echo '</div>';
}

/**
 * Avaliação e marca numa linha estrutural real, como no mockup aprovado.
 */
function cvl_single_product_rating_brand_row() {
    echo '<div class="cvl-single-rating-brand-row">';
    echo '<div class="cvl-single-rating-slot">';
    cvl_single_product_rating_row();
    echo '</div>';
    echo '<div class="cvl-single-brand-slot">';
    cvl_single_product_brand_block();
    echo '</div>';
    echo '</div>';
}
add_action( 'woocommerce_single_product_summary', 'cvl_single_product_rating_brand_row', 10 );


/**
 * Devolve a primeira taxa de imposto aplicável ao produto.
 */
function cvl_single_product_tax_rate( WC_Product $product ) {
    if ( ! class_exists( 'WC_Tax' ) || ! function_exists( 'wc_tax_enabled' ) || ! wc_tax_enabled() ) {
        return 0.0;
    }

    $rates = WC_Tax::get_rates( $product->get_tax_class() );

    if ( empty( $rates ) ) {
        return 0.0;
    }

    $first = reset( $rates );

    return isset( $first['rate'] ) ? (float) $first['rate'] : 0.0;
}

/**
 * Caixa de preço: preço WooCommerce + IVA + referência líquida.
 */
function cvl_single_product_price_box() {
    global $product;

    if ( ! class_exists( 'WC_Product' ) || ! $product instanceof WC_Product ) {
        return;
    }

    $price_html = $product->get_price_html();

    if ( '' === trim( wp_strip_all_tags( $price_html ) ) ) {
        return;
    }

    $tax_rate    = cvl_single_product_tax_rate( $product );
    $tax_display = get_option( 'woocommerce_tax_display_shop', 'incl' );
    $vat_label   = '';

    if ( $tax_rate > 0 ) {
        $vat_label = 'incl' === $tax_display
            ? sprintf( __( 'INCLUI IVA %s%%', 'chavevertical-lite' ), wc_format_decimal( $tax_rate, 0 ) )
            : sprintf( __( '+ IVA %s%%', 'chavevertical-lite' ), wc_format_decimal( $tax_rate, 0 ) );
    }

    $raw_min = $product->is_type( 'variable' )
        ? (float) $product->get_variation_price( 'min', false )
        : (float) $product->get_price();

    $raw_max = $product->is_type( 'variable' )
        ? (float) $product->get_variation_price( 'max', false )
        : $raw_min;

    $net_html = '';

    if ( $raw_min > 0 && function_exists( 'wc_get_price_excluding_tax' ) ) {
        $net_min = wc_get_price_excluding_tax(
            $product,
            array(
                'qty'   => 1,
                'price' => $raw_min,
            )
        );
        $net_max = wc_get_price_excluding_tax(
            $product,
            array(
                'qty'   => 1,
                'price' => $raw_max,
            )
        );

        $net_html = wc_price( $net_min );

        if ( $net_max > $net_min ) {
            $net_html .= ' – ' . wc_price( $net_max );
        }
    }

    echo '<div class="cvl-single-price-box">';
    echo '<div class="cvl-single-price-row">';
    echo '<span class="cvl-single-price">' . wp_kses_post( $price_html ) . '</span>';

    if ( $vat_label ) {
        echo '<span class="cvl-single-vat">' . esc_html( $vat_label ) . '</span>';
    }

    echo '</div>';

    if ( $net_html ) {
        echo '<div class="cvl-single-price-net">' . esc_html__( 'Preço sem IVA:', 'chavevertical-lite' ) . ' <strong>' . wp_kses_post( $net_html ) . '</strong> <span>(+ IVA)</span></div>';
    }

    echo '</div>';
}
add_action( 'woocommerce_single_product_summary', 'cvl_single_product_price_box', 15 );

/**
 * Painel de disponibilidade e dados comerciais.
 */
function cvl_single_product_info_panel() {
    global $product;

    if ( ! class_exists( 'WC_Product' ) || ! $product instanceof WC_Product ) {
        return;
    }

    $status         = $product->get_stock_status();
    $class          = 'is-onbackorder';
    $label          = __( 'Disponível por encomenda', 'chavevertical-lite' );
    $delivery_label = __( 'Prazo sujeito a confirmação', 'chavevertical-lite' );
    $availability_icon = '<svg class="cvl-whatsapp-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M20.5 11.7a8.3 8.3 0 0 1-12.3 7.2L3.5 20l1.2-4.5A8.3 8.3 0 1 1 20.5 11.7Z"></path><path d="M8.4 7.8c.2-.4.4-.4.7-.4h.5c.2 0 .4.1.5.4l.8 1.8c.1.3.1.5-.1.7l-.7.9c-.2.2-.1.4 0 .6.5.9 1.2 1.7 2.1 2.2.2.1.4.2.6 0l.9-1.1c.2-.2.4-.3.7-.2l1.9.9c.3.1.4.3.4.5 0 .3-.1 1.5-.8 2.1-.7.6-1.5.8-2.4.6-1.3-.3-2.9-1-4.4-2.4-1.2-1.1-2.2-2.5-2.6-3.8-.4-1.1-.1-2.1.4-2.8.4-.5.9-.7 1.5-.7Z"></path></svg>';

    if ( 'instock' === $status ) {
        $class          = 'is-instock';
        $label          = __( 'Em stock', 'chavevertical-lite' );
        $delivery_label = __( 'Entrega prevista: 4 a 5 dias úteis', 'chavevertical-lite' );
    } elseif ( 'outofstock' === $status ) {
        $class          = 'is-outofstock';
        $label          = __( 'Sob consulta', 'chavevertical-lite' );
        $delivery_label = __( 'Consulte-nos para confirmar o prazo', 'chavevertical-lite' );
    }

    $sku      = $product->get_sku();
    $category = cvl_get_product_primary_category( $product->get_id() );
    $tags     = wp_get_post_terms( $product->get_id(), 'product_tag', array( 'fields' => 'names' ) );
    $tag_text = ! is_wp_error( $tags ) && ! empty( $tags ) ? implode( ', ', $tags ) : '—';

    // Informação detalhada de disponibilidade com quantidade real em stock.
    $availability_detail = $label;

    if ( 'instock' === $status && $product->managing_stock() ) {
        $stock_quantity = $product->get_stock_quantity();

        if ( null !== $stock_quantity && $stock_quantity > 0 ) {
            if ( 1 === (int) $stock_quantity ) {
                $availability_detail = __( 'Só 1 em stock', 'chavevertical-lite' );
            } else {
                $availability_detail = sprintf(
                    __( '%d em stock', 'chavevertical-lite' ),
                    (int) $stock_quantity
                );
            }

        }
    }

    $message = sprintf(
        'Olá, pretendo consultar o prazo de entrega do produto %1$s%2$s. %3$s',
        $product->get_name(),
        $sku ? ' (Ref: ' . $sku . ')' : '',
        $product->get_permalink()
    );
    $whatsapp_url = 'https://wa.me/351914580410?text=' . rawurlencode( $message );

    echo '<section class="cvl-single-info-panel">';
    echo '<div class="cvl-single-availability-row">';
    echo '<a class="cvl-single-availability ' . esc_attr( $class ) . '" href="' . esc_url( $whatsapp_url ) . '" target="_blank" rel="noopener nofollow">';
    echo '<span class="cvl-single-availability-icon" aria-hidden="true">' . $availability_icon . '</span>';
    echo '<span class="cvl-single-availability-copy"><strong>' . esc_html( $label ) . '</strong><small>' . esc_html( $delivery_label ) . '</small></span>';
    echo '</a>';
    echo '</div>';

    echo '<div class="cvl-single-facts"><table><tbody>';
    echo '<tr><th scope="row">' . esc_html__( 'Disponibilidade:', 'chavevertical-lite' ) . '</th><td><span class="cvl-single-stock-state ' . esc_attr( $class ) . '"><i aria-hidden="true"></i>' . esc_html( $availability_detail ) . '</span></td></tr>';
    echo '<tr><th scope="row">' . esc_html__( 'Prazo de entrega:', 'chavevertical-lite' ) . '</th><td>';

    if ( 'instock' === $status ) {
        echo esc_html( $delivery_label );
    } else {
        echo '<a href="' . esc_url( $whatsapp_url ) . '" target="_blank" rel="noopener nofollow">' . esc_html( $delivery_label ) . '</a>';
    }

    echo '</td></tr>';

    if ( $sku ) {
        echo '<tr><th scope="row">' . esc_html__( 'Referência:', 'chavevertical-lite' ) . '</th><td><span class="cvl-single-fact-reference-value">' . esc_html( $sku ) . '</span></td></tr>';
    }

    if ( $category ) {
        echo '<tr class="cvl-single-fact-category"><th scope="row">' . esc_html__( 'Categorias:', 'chavevertical-lite' ) . '</th><td><span class="cvl-single-fact-category-value">' . esc_html( $category->name ) . '</span></td></tr>';
    }

    echo '<tr class="cvl-single-fact-tags"><th scope="row">' . esc_html__( 'Etiquetas:', 'chavevertical-lite' ) . '</th><td>' . esc_html( $tag_text ) . '</td></tr>';
    echo '</tbody></table></div>';
    echo '</section>';
}
add_action( 'woocommerce_single_product_summary', 'cvl_single_product_info_panel', 25 );

/**
 * Ação comercial secundária alinhada com o storefront Shopware.
 *
 * Mantém o formulário de compra nativo do WooCommerce intocado. A ação
 * secundária abre o pedido comercial com produto e referência já preenchidos.
 */
function cvl_single_product_request_url( WC_Product $product, $request_type = 'quote' ) {
    return add_query_arg(
        array(
            'produto' => $product->get_name(),
            'sku'     => $product->get_sku(),
            'tipo'    => sanitize_key( $request_type ),
        ),
        'https://chavevertical.com/contacto-pedido-de-cotacao/'
    );
}


add_filter( 'woocommerce_product_single_add_to_cart_text', function () {
    return __( 'Adicionar ao carrinho', 'chavevertical-lite' );
} );

function cvl_single_product_quote_action() {
    global $product;

    if ( ! class_exists( 'WC_Product' ) || ! $product instanceof WC_Product ) {
        return;
    }

    $url = cvl_single_product_request_url( $product, 'orcamento' );

    echo '<a class="cvl-single-proforma-button cvl-single-quote-button" href="' . esc_url( $url ) . '">';
    echo '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h12v18H6z"></path><path d="M9 7h6M9 11h6M9 15h4"></path></svg>';
    echo '<span>' . esc_html__( 'SOLICITAR ORÇAMENTO', 'chavevertical-lite' ) . '</span>';
    echo '</a>';
}
add_action( 'woocommerce_after_add_to_cart_button', 'cvl_single_product_quote_action', 20 );

function cvl_single_product_quote_only_action() {
    global $product;

    if ( ! class_exists( 'WC_Product' ) || ! $product instanceof WC_Product ) {
        return;
    }

    if ( $product->is_purchasable() && $product->is_in_stock() ) {
        return;
    }

    $url = cvl_single_product_request_url( $product, 'orcamento' );

    echo '<div class="cvl-single-quote-only">';
    echo '<a class="cvl-single-proforma-button cvl-single-quote-button" href="' . esc_url( $url ) . '">';
    echo '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h12v18H6z"></path><path d="M9 7h6M9 11h6M9 15h4"></path></svg>';
    echo '<span>' . esc_html__( 'SOLICITAR ORÇAMENTO', 'chavevertical-lite' ) . '</span>';
    echo '</a>';
    echo '</div>';
}
add_action( 'woocommerce_single_product_summary', 'cvl_single_product_quote_only_action', 31 );

/**
 * Faixa de confiança sob as ações, visualmente equivalente ao mockup final.
 */
function cvl_single_product_trust_strip() {
    echo '<div class="cvl-single-trust-strip" aria-label="' . esc_attr__( 'Informações de compra', 'chavevertical-lite' ) . '">';

    $items = array(
        array(
            'icon'  => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 4.5 6v5c0 4.9 3 8.2 7.5 10 4.5-1.8 7.5-5.1 7.5-10V6z"></path><path d="m9 12 2 2 4-4"></path></svg>',
            'title' => __( 'Compra online', 'chavevertical-lite' ),
            'text'  => __( 'Processo de compra integrado', 'chavevertical-lite' ),
        ),
        array(
            'icon'  => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 7h11v9H3zM14 10h4l3 3v3h-7z"></path><circle cx="7" cy="18" r="2"></circle><circle cx="18" cy="18" r="2"></circle></svg>',
            'title' => __( 'Envio nacional', 'chavevertical-lite' ),
            'text'  => __( 'Envio para todo o país', 'chavevertical-lite' ),
        ),
        array(
            'icon'  => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="M3 10h18M7 15h4"></path></svg>',
            'title' => __( 'Pagamento', 'chavevertical-lite' ),
            'text'  => __( 'Opções disponíveis no checkout', 'chavevertical-lite' ),
        ),
        array(
            'icon'  => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 13v-2a8 8 0 0 1 16 0v2"></path><path d="M4 13h3v6H5a1 1 0 0 1-1-1zM20 13h-3v6h2a1 1 0 0 0 1-1z"></path></svg>',
            'title' => __( 'Apoio técnico', 'chavevertical-lite' ),
            'text'  => __( 'Especialistas no setor', 'chavevertical-lite' ),
        ),
    );

    foreach ( $items as $item ) {
        echo '<div class="cvl-single-trust-item">';
        echo '<span class="cvl-single-trust-icon">' . wp_kses( $item['icon'], array(
            'svg' => array( 'viewbox' => true, 'aria-hidden' => true ),
            'path' => array( 'd' => true ),
            'circle' => array( 'cx' => true, 'cy' => true, 'r' => true ),
            'rect' => array( 'x' => true, 'y' => true, 'width' => true, 'height' => true, 'rx' => true ),
        ) ) . '</span>';
        echo '<span class="cvl-single-trust-copy"><strong>' . esc_html( $item['title'] ) . '</strong><small>' . esc_html( $item['text'] ) . '</small></span>';
        echo '</div>';
    }

    echo '</div>';
}
// Faixa inferior de confiança removida: informação consolidada nos cartões superiores.

/**
 * Partilha da ficha, posicionada pelo template junto à galeria.
 */
function cvl_single_product_share() {
    global $product;

    if ( ! class_exists( 'WC_Product' ) || ! $product instanceof WC_Product ) {
        return;
    }

    $url   = $product->get_permalink();
    $title = $product->get_name();

    echo '<div class="cvl-single-share">';
    echo '<span class="cvl-single-share-label">' . esc_html__( 'Partilhar:', 'chavevertical-lite' ) . '</span>';

    echo '<a class="is-facebook" href="' . esc_url( 'https://www.facebook.com/sharer.php?u=' . rawurlencode( $url ) ) . '" target="_blank" rel="noopener" aria-label="' . esc_attr__( 'Partilhar no Facebook', 'chavevertical-lite' ) . '">';
    echo '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M13.7 21v-8.2h2.8l.4-3.2h-3.2V7.5c0-.9.3-1.6 1.6-1.6H17V3.1c-.3 0-1.4-.1-2.5-.1-2.5 0-4.2 1.5-4.2 4.3v2.4H7.5v3.2h2.8V21h3.4z"></path></svg><span>Facebook</span></a>';

    echo '<a class="is-whatsapp" href="' . esc_url( 'https://wa.me/?text=' . rawurlencode( $title . ' ' . $url ) ) . '" target="_blank" rel="noopener" aria-label="' . esc_attr__( 'Partilhar no WhatsApp', 'chavevertical-lite' ) . '">';
    echo '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12.04 2a9.91 9.91 0 0 0-8.59 14.86L2.05 22l5.25-1.38A9.9 9.9 0 1 0 12.04 2Zm5.79 14.1c-.24.68-1.4 1.25-1.92 1.32-.5.07-1.14.1-3.32-.8-2.79-1.15-4.58-4.01-4.72-4.2-.14-.19-1.13-1.5-1.13-2.86 0-1.36.71-2.03.96-2.31.25-.28.56-.35.75-.35.19 0 .37 0 .53.01.17.01.4.06.61.57.24.57.81 1.98.88 2.12.07.14.12.31.02.5-.09.19-.14.31-.28.47-.14.17-.3.37-.43.5-.14.14-.29.3-.12.59.16.28.73 1.2 1.56 1.94 1.07.95 1.97 1.25 2.25 1.39.28.14.45.12.61-.07.17-.19.72-.84.91-1.13.19-.28.38-.24.64-.14.26.09 1.66.78 1.95.92.28.14.47.21.54.33.07.12.07.7-.17 1.38Z"></path></svg><span>WhatsApp</span></a>';

    $mailto = 'mailto:?subject=' . rawurlencode( $title ) . '&body=' . rawurlencode( $url );
    echo '<a class="is-email" href="' . esc_attr( $mailto ) . '" aria-label="' . esc_attr__( 'Partilhar por email', 'chavevertical-lite' ) . '">';
    echo '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 5.5h18v13H3z"></path><path d="m4.5 7 7.5 6 7.5-6"></path></svg><span>Email</span></a>';

    echo '</div>';
}

/**
 * Os nomes dos separadores já identificam o conteúdo; evita títulos repetidos
 * dentro dos painéis Descrição e Informação adicional.
 */
add_filter( 'woocommerce_product_description_heading', '__return_empty_string' );
add_filter( 'woocommerce_product_additional_information_heading', '__return_empty_string' );

/**
 * Tab comercial equivalente ao layout de referência.
 */
add_filter( 'woocommerce_product_tabs', function ( $tabs ) {
    $tabs['cvl_contact'] = array(
        'title'    => __( 'Solicitar Contacto / Orçamento', 'chavevertical-lite' ),
        'priority' => 25,
        'callback' => 'cvl_single_product_contact_tab_content',
    );

    return $tabs;
}, 20 );

function cvl_single_product_contact_tab_content() {
    global $product;

    if ( ! class_exists( 'WC_Product' ) || ! $product instanceof WC_Product ) {
        return;
    }

    $url = cvl_single_product_request_url( $product, 'orcamento' );

    echo '<div class="cvl-single-contact-tab">';
    echo '<p>' . esc_html__( 'Tem dúvidas sobre as características técnicas ou pretende encomendar em quantidade? A equipa comercial prepara uma proposta adequada ao seu pedido.', 'chavevertical-lite' ) . '</p>';
    echo '<a class="cvl-single-contact-cta" href="' . esc_url( $url ) . '">' . esc_html__( 'SOLICITAR ORÇAMENTO', 'chavevertical-lite' ) . '</a>';
    echo '</div>';
}

/**
 * Produto individual: mostrar 8 produtos relacionados.
 */
add_filter( 'woocommerce_output_related_products_args', function ( $args ) {
    $args['posts_per_page'] = 8;
    $args['columns']        = 8;

    return $args;
} );

/**
 * O painel comercial já apresenta "Sob consulta"; evita o "Esgotado"
 * nativo do WooCommerce na mesma ficha.
 */
add_filter( 'woocommerce_get_stock_html', function ( $html, $product ) {
    if (
        function_exists( 'is_product' )
        && is_product()
        && $product instanceof WC_Product
        && 'outofstock' === $product->get_stock_status()
    ) {
        return '';
    }

    return $html;
}, 20, 2 );


add_filter( 'loop_shop_columns', function () {
    return 6;
} );

add_filter( 'loop_shop_per_page', function () {
    return 30;
} );


/**
 * Shop root: visual category grid matching the Shopware reference.
 */
function cvl_shop_root_category_grid() {
    if ( ! function_exists( 'is_shop' ) || ! is_shop() || ! taxonomy_exists( 'product_cat' ) ) {
        return;
    }

    $exclude = array();
    $uncategorized = get_term_by( 'slug', 'uncategorized', 'product_cat' );

    if ( $uncategorized && ! is_wp_error( $uncategorized ) ) {
        $exclude[] = (int) $uncategorized->term_id;
    }

    $terms = get_terms( array(
        'taxonomy'   => 'product_cat',
        'parent'     => 0,
        'hide_empty' => false,
        'exclude'    => $exclude,
        'number'     => 0,
        'orderby'    => 'name',
        'order'      => 'ASC',
    ) );

    if ( is_wp_error( $terms ) || empty( $terms ) ) {
        return;
    }

    echo '<section class="cvl-shop-category-section">';
    echo '<header class="cvl-shop-catalog-heading"><h1>' . esc_html__( 'CATÁLOGO', 'chavevertical-lite' ) . '</h1></header>';
    echo '<div class="cvl-category-grid cvl-category-grid-premium cvl-category-grid-page">';

    foreach ( $terms as $term ) {
        $url = get_term_link( $term );
        if ( is_wp_error( $url ) ) {
            continue;
        }

        $thumbnail_id = absint( get_term_meta( $term->term_id, 'thumbnail_id', true ) );

        echo '<a class="cvl-category-card" href="' . esc_url( $url ) . '">';
        echo '<span class="cvl-category-image">';

        if ( $thumbnail_id ) {
            echo wp_kses_post(
                wp_get_attachment_image(
                    $thumbnail_id,
                    'medium',
                    false,
                    array(
                        'loading' => 'lazy',
                        'alt'     => $term->name,
                    )
                )
            );
        } else {
            echo '<span class="cvl-category-placeholder" aria-hidden="true">⚙</span>';
        }

        echo '</span>';
        echo '<span class="cvl-category-copy"><strong>' . esc_html( $term->name ) . '</strong></span>';
        echo '</a>';
    }

    echo '</div></section>';
}
// Renderizado diretamente em woocommerce.php para evitar a grelha nativa duplicada.


/**
 * Carrega a árvore completa de categorias numa única query.
 * Mantém uma ordem comercial estável para as categorias principais.
 */
function cvl_get_product_category_tree() {
    static $tree = null;

    if ( null !== $tree ) {
        return $tree;
    }

    $tree = array();

    if ( ! taxonomy_exists( 'product_cat' ) ) {
        return $tree;
    }

    $terms = get_terms( array(
        'taxonomy'   => 'product_cat',
        'hide_empty' => false,
        'number'     => 0,
    ) );

    if ( is_wp_error( $terms ) || empty( $terms ) ) {
        return $tree;
    }

    $uncategorized = get_term_by( 'slug', 'uncategorized', 'product_cat' );
    $uncategorized_id = $uncategorized && ! is_wp_error( $uncategorized )
        ? (int) $uncategorized->term_id
        : 0;

    foreach ( $terms as $term ) {
        if ( $uncategorized_id && (int) $term->term_id === $uncategorized_id ) {
            continue;
        }

        $parent = (int) $term->parent;

        if ( ! isset( $tree[ $parent ] ) ) {
            $tree[ $parent ] = array();
        }

        $tree[ $parent ][] = $term;
    }

    $root_priority = array(
        'ferramentas-manuais',
        'ferramentas-electricas',
        'ferramentas-eletricas',
        'ferramentas-pneumaticas',
        'oficina-automovel',
        'maquinas-p-industria-metal',
        'carpintaria-de-madeiras',
        'construcao-civil',
        'canalizacao-e-desentupimentos',
        'equipamentos-de-soldadura',
        'electricidade-e-electronica',
        'eletricidade-e-eletronica',
        'equip-p-agricultura',
        'elevacao-e-carga',
        'medicao-e-nivelamento',
        'lavagem-a-alta-pressao',
        'aspiracao-e-lavagem-estofos',
        'ar-comprimido',
        'geradores',
        'proteccao-e-seguranca',
        'protecao-e-seguranca',
        'limpeza',
        'estantaria-e-arrumacao',
        'floresta-e-jardim',
        'iluminacao',
    );
    $rank = array_flip( $root_priority );

    foreach ( $tree as $parent => &$siblings ) {
        usort(
            $siblings,
            static function ( $a, $b ) use ( $parent, $rank ) {
                if ( 0 === (int) $parent ) {
                    $ra = $rank[ $a->slug ] ?? 999;
                    $rb = $rank[ $b->slug ] ?? 999;

                    if ( $ra !== $rb ) {
                        return $ra <=> $rb;
                    }
                }

                return strcasecmp( $a->name, $b->name );
            }
        );
    }
    unset( $siblings );

    return $tree;
}

/**
 * Renderiza a navegação off-canvas de categorias sem queries adicionais.
 */
function cvl_render_category_drawer_items( array $tree, int $parent = 0, int $depth = 0 ) {
    if ( empty( $tree[ $parent ] ) || $depth > 5 ) {
        return;
    }

    $list_class = 0 === $depth
        ? 'cvl-category-drawer-list'
        : 'cvl-category-drawer-sublist';

    echo '<ul class="' . esc_attr( $list_class ) . '">';

    foreach ( $tree[ $parent ] as $term ) {
        $term_id      = (int) $term->term_id;
        $term_url     = get_term_link( $term );
        $has_children = ! empty( $tree[ $term_id ] );

        if ( is_wp_error( $term_url ) ) {
            continue;
        }

        echo '<li class="cvl-category-drawer-item' . ( $has_children ? ' has-children' : '' ) . '" data-depth="' . esc_attr( (string) $depth ) . '">';
        echo '<div class="cvl-category-drawer-row">';
        echo '<a href="' . esc_url( $term_url ) . '"><span>' . esc_html( $term->name ) . '</span></a>';

        if ( $has_children ) {
            echo '<button class="cvl-category-expand" type="button" aria-expanded="false" aria-label="' . esc_attr( sprintf( __( 'Mostrar subcategorias de %s', 'chavevertical-lite' ), $term->name ) ) . '">';
            echo '<span aria-hidden="true">›</span>';
            echo '</button>';
        } else {
            echo '<span class="cvl-category-row-arrow" aria-hidden="true">›</span>';
        }

        echo '</div>';

        if ( $has_children ) {
            echo '<div class="cvl-category-drawer-children" hidden>';
            cvl_render_category_drawer_items( $tree, $term_id, $depth + 1 );
            echo '</div>';
        }

        echo '</li>';
    }

    echo '</ul>';
}


/**
 * Categorias WooCommerce — layout de três colunas inspirado no storefront
 * de backup.chavevertical.com: categorias à esquerda, catálogo ao centro e
 * apoio comercial à direita.
 *
 * É deliberadamente limitado a taxonomias product_cat; shop, produto,
 * carrinho e checkout mantêm o layout existente.
 */
add_filter( 'woocommerce_show_page_title', function ( $show ) {
    if ( function_exists( 'is_product_category' ) && is_product_category() ) {
        return false;
    }

    return $show;
} );

/**
 * Abre o shell da categoria e mantém o H1 semanticamente disponível.
 */
function cvl_backup_category_layout_open() {
    $term  = get_queried_object();
    $title = $term instanceof WP_Term ? $term->name : single_term_title( '', false );

    echo '<section class="cvl-backup-category-page">';
    echo '<div class="cvl-backup-category-breadcrumbs">';

    if ( function_exists( 'woocommerce_breadcrumb' ) ) {
        woocommerce_breadcrumb(
            array(
                'delimiter'   => '<span class="cvl-breadcrumb-delimiter" aria-hidden="true">›</span>',
                'wrap_before' => '<nav class="woocommerce-breadcrumb" aria-label="' . esc_attr__( 'Navegação estrutural', 'chavevertical-lite' ) . '">',
                'wrap_after'  => '</nav>',
                'before'      => '<span class="cvl-breadcrumb-current">',
                'after'       => '</span>',
                'home'        => __( 'Home', 'chavevertical-lite' ),
            )
        );
    }

    echo '</div>';

    if ( $title ) {
        echo '<h1 class="screen-reader-text">' . esc_html( $title ) . '</h1>';
    }

    echo '<div class="cvl-backup-category-layout">';
    echo '<main class="cvl-backup-category-main" aria-label="' . esc_attr__( 'Produtos e subcategorias', 'chavevertical-lite' ) . '">';
}

/**
 * Fecha o catálogo central e injeta as duas barras laterais.
 */
function cvl_backup_category_layout_close() {
    echo '</main>';

    cvl_backup_category_left_sidebar();
    cvl_backup_category_right_sidebar();

    echo '</div>';
    echo '</section>';
}

/**
 * Resolve a raiz comercial da categoria atual para manter uma navegação
 * consistente dentro da mesma família.
 */
function cvl_backup_category_root_id( WP_Term $term ) {
    $ancestors = get_ancestors( $term->term_id, 'product_cat', 'taxonomy' );

    if ( empty( $ancestors ) ) {
        return (int) $term->term_id;
    }

    return (int) end( $ancestors );
}

/**
 * Renderiza a árvore de categorias no formato do sidebar do backup.
 */
function cvl_backup_category_sidebar_nodes( array $tree, int $parent_id, int $current_id, int $depth = 0 ) {
    if ( empty( $tree[ $parent_id ] ) || $depth > 2 ) {
        return;
    }

    echo '<ul class="cv-cat-list">';

    foreach ( $tree[ $parent_id ] as $term ) {
        $url = get_term_link( $term );

        if ( is_wp_error( $url ) ) {
            continue;
        }

        $is_current = (int) $term->term_id === $current_id;

        echo '<li style="--cv-cat-level:' . esc_attr( (string) $depth ) . ';">';
        echo '<a class="cv-cat-link' . ( $is_current ? ' is-current' : '' ) . '" href="' . esc_url( $url ) . '"' . ( $is_current ? ' aria-current="page"' : '' ) . '>';
        echo '<span class="cv-bullet" aria-hidden="true"></span>';
        echo '<span class="cv-cat-text">' . esc_html( $term->name ) . '</span>';
        echo '</a>';

        if ( ! empty( $tree[ (int) $term->term_id ] ) ) {
            cvl_backup_category_sidebar_nodes(
                $tree,
                (int) $term->term_id,
                $current_id,
                $depth + 1
            );
        }

        echo '</li>';
    }

    echo '</ul>';
}

/**
 * Grelha central de subcategorias, equivalente aos cartões que o Porto
 * apresenta nas categorias com filhos. Quando existem subcategorias,
 * a categoria atua como landing page e não mostra a mensagem
 * "nenhum produto encontrado".
 *
 * @return bool True quando a grelha foi renderizada.
 */
function cvl_backup_category_subcategory_grid() {
    $term = get_queried_object();

    if ( ! $term instanceof WP_Term || 'product_cat' !== $term->taxonomy ) {
        return false;
    }

    $children = get_terms(
        array(
            'taxonomy'   => 'product_cat',
            'parent'     => (int) $term->term_id,
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
            'number'     => 0,
        )
    );

    if ( is_wp_error( $children ) || empty( $children ) ) {
        return false;
    }

    echo '<div class="archive-products cvl-backup-subcategories">';
    echo '<ul class="products cvl-backup-subcategory-products" role="list">';

    foreach ( $children as $child ) {
        $url = get_term_link( $child );

        if ( is_wp_error( $url ) ) {
            continue;
        }

        $thumbnail_id = absint( get_term_meta( $child->term_id, 'thumbnail_id', true ) );

        echo '<li class="product-category product-col" role="listitem">';
        echo '<a href="' . esc_url( $url ) . '" aria-label="' . esc_attr( sprintf( __( 'Abrir categoria %s', 'chavevertical-lite' ), $child->name ) ) . '">';

        if ( $thumbnail_id ) {
            echo wp_kses_post(
                wp_get_attachment_image(
                    $thumbnail_id,
                    'woocommerce_thumbnail',
                    false,
                    array(
                        'loading' => 'lazy',
                        'alt'     => $child->name,
                    )
                )
            );
        } else {
            echo '<img src="' . esc_url( cvl_product_placeholder_url() ) . '" alt="' . esc_attr( $child->name ) . '" loading="lazy">';
        }

        echo '<h2 class="woocommerce-loop-category__title">' . esc_html( $child->name ) . '</h2>';
        echo '</a>';
        echo '</li>';
    }

    echo '</ul>';
    echo '</div>';

    return true;
}

/**
 * Sidebar esquerda: categorias da família atual, com colapso em mobile.
 */
function cvl_backup_category_left_sidebar() {
    $term = get_queried_object();

    if ( ! $term instanceof WP_Term || 'product_cat' !== $term->taxonomy ) {
        return;
    }

    $tree       = cvl_get_product_category_tree();
    $root_id    = cvl_backup_category_root_id( $term );
    $list_root  = $root_id;

    if ( empty( $tree[ $list_root ] ) && $term->parent ) {
        $list_root = (int) $term->parent;
    }

    if ( empty( $tree[ $list_root ] ) ) {
        $list_root = 0;
    }

    echo '<aside class="cvl-backup-category-sidebar cvl-backup-category-sidebar--left" aria-label="' . esc_attr__( 'Categorias', 'chavevertical-lite' ) . '">';
    echo '<div class="cv-sidebar-inner">';
    echo '<h2 class="cv-sidebar-title">' . esc_html__( 'Categorias', 'chavevertical-lite' ) . '</h2>';
    echo '<button type="button" class="cv-cat-toggle" aria-expanded="true" aria-controls="cvl-category-sidebar-groups">';
    echo '<span class="cv-cat-toggle-inner">';
    echo '<span class="cv-cat-toggle-text">' . esc_html__( 'Ocultar categorias', 'chavevertical-lite' ) . '</span>';
    echo '<span class="cv-cat-toggle-icon" aria-hidden="true">−</span>';
    echo '</span>';
    echo '</button>';
    echo '<div id="cvl-category-sidebar-groups" class="cv-category-groups">';

    cvl_backup_category_sidebar_nodes(
        $tree,
        $list_root,
        (int) $term->term_id
    );

    echo '</div>';
    echo '</div>';
    echo '</aside>';
}

/**
 * SVGs locais para os cartões de contacto, evitando dependência externa
 * de Font Awesome.
 */
function cvl_backup_category_contact_icon( $type ) {
    switch ( $type ) {
        case 'whatsapp':
            return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12.04 2a9.91 9.91 0 0 0-8.59 14.86L2.05 22l5.25-1.38A9.9 9.9 0 1 0 12.04 2Zm5.79 14.1c-.24.68-1.4 1.25-1.92 1.32-.5.07-1.14.1-3.32-.8-2.79-1.15-4.58-4.01-4.72-4.2-.14-.19-1.13-1.5-1.13-2.86 0-1.36.71-2.03.96-2.31.25-.28.56-.35.75-.35.19 0 .37 0 .53.01.17.01.4.06.61.57.24.57.81 1.98.88 2.12.07.14.12.31.02.5-.09.19-.14.31-.28.47-.14.17-.3.37-.43.5-.14.14-.29.3-.12.59.16.28.73 1.2 1.56 1.94 1.07.95 1.97 1.25 2.25 1.39.28.14.45.12.61-.07.17-.19.72-.84.91-1.13.19-.28.38-.24.64-.14.26.09 1.66.78 1.95.92.28.14.47.21.54.33.07.12.07.7-.17 1.38Z"></path></svg>';

        case 'phone':
            return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6.8 3.6 9.1 7.5c.3.5.2 1.1-.2 1.5l-1.5 1.2a13.8 13.8 0 0 0 6.4 6.4l1.2-1.5c.4-.4 1-.5 1.5-.2l3.9 2.3c.5.3.8.9.6 1.5l-.6 1.8c-.2.6-.8 1-1.5 1C9.7 21.5 2.5 14.3 2.5 5.1c0-.7.4-1.3 1-1.5l1.8-.6c.6-.2 1.2.1 1.5.6Z"></path></svg>';

        case 'email':
            return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 5.5h18v13H3z"></path><path d="m4.5 7 7.5 6 7.5-6"></path></svg>';

        default:
            return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 3h10l4 4v14H5V3Z"></path><path d="M14 3v5h5M8 12h8M8 16h6"></path></svg>';
    }
}

/**
 * Escolhe um produto real da loja para o bloco "Produto em destaque".
 */
function cvl_backup_category_featured_product() {
    if ( ! function_exists( 'wc_get_products' ) ) {
        return null;
    }

    $featured_ids = function_exists( 'wc_get_featured_product_ids' )
        ? array_values( array_filter( array_map( 'absint', wc_get_featured_product_ids() ) ) )
        : array();

    if ( ! empty( $featured_ids ) ) {
        foreach ( $featured_ids as $product_id ) {
            $product = wc_get_product( $product_id );

            if ( $product instanceof WC_Product && 'publish' === $product->get_status() && $product->is_visible() ) {
                return $product;
            }
        }
    }

    $products = wc_get_products(
        array(
            'limit'   => 1,
            'status'  => 'publish',
            'orderby' => 'date',
            'order'   => 'DESC',
        )
    );

    return ! empty( $products ) && $products[0] instanceof WC_Product
        ? $products[0]
        : null;
}

/**
 * Sidebar direita: cartões de contacto do backup e produto em destaque.
 */
function cvl_backup_category_right_sidebar() {
    $featured = cvl_backup_category_featured_product();

    echo '<aside class="cvl-backup-category-sidebar cvl-backup-category-sidebar--right" aria-label="' . esc_attr__( 'Apoio comercial', 'chavevertical-lite' ) . '">';
    echo '<div class="cv-quote-widget">';
    echo '<div class="cv-quote-title">' . esc_html__( 'Solicitar Cotação / Orçamento', 'chavevertical-lite' ) . '</div>';

    echo '<a class="cv-contact-card cv-whatsapp-card" href="https://wa.me/351914580410?text=' . rawurlencode( 'Olá, pretendo solicitar uma cotação / orçamento.' ) . '" target="_blank" rel="noopener noreferrer">';
    echo '<span class="cv-contact-icon is-fill">' . cvl_backup_category_contact_icon( 'whatsapp' ) . '</span>';
    echo '<span class="cv-contact-text"><span class="cv-help">' . esc_html__( 'Como podemos ajudar?', 'chavevertical-lite' ) . ' <span class="cv-status">' . esc_html__( 'Online', 'chavevertical-lite' ) . '</span></span><span class="cv-contact">' . esc_html__( 'Fale Connosco pelo WhatsApp', 'chavevertical-lite' ) . '</span></span>';
    echo '</a>';

    echo '<div class="cv-contact-card cv-phone-card">';
    echo '<span class="cv-contact-icon">' . cvl_backup_category_contact_icon( 'phone' ) . '</span>';
    echo '<span class="cv-contact-text"><span class="cv-help">' . esc_html__( 'Prefere falar connosco?', 'chavevertical-lite' ) . ' <span class="cv-status">' . esc_html__( 'Telefone', 'chavevertical-lite' ) . '</span></span>';
    echo '<span class="cv-contact cv-phone-numbers"><a href="tel:+351234020500">234 020 500</a><span class="cv-phone-separator">|</span><a href="tel:+351914938100">914 938 100</a></span></span>';
    echo '</div>';

    $mail_subject = rawurlencode( 'Pedido de Cotação / Orçamento' );
    echo '<a class="cv-contact-card cv-email-card" href="mailto:geral@chavevertical.pt?subject=' . esc_attr( $mail_subject ) . '">';
    echo '<span class="cv-contact-icon">' . cvl_backup_category_contact_icon( 'email' ) . '</span>';
    echo '<span class="cv-contact-text"><span class="cv-help">' . esc_html__( 'Também pode contactar por email', 'chavevertical-lite' ) . ' <span class="cv-status">' . esc_html__( 'Email', 'chavevertical-lite' ) . '</span></span><span class="cv-contact">geral@chavevertical.pt</span></span>';
    echo '</a>';

    echo '<a class="cv-contact-card cv-form-card" href="https://chavevertical.com/contacto-pedido-de-cotacao/">';
    echo '<span class="cv-contact-icon">' . cvl_backup_category_contact_icon( 'form' ) . '</span>';
    echo '<span class="cv-contact-text"><span class="cv-help">' . esc_html__( 'Prefere enviar os dados?', 'chavevertical-lite' ) . ' <span class="cv-status">' . esc_html__( 'Formulário', 'chavevertical-lite' ) . '</span></span><span class="cv-contact">' . esc_html__( 'Pedir Cotação por Formulário', 'chavevertical-lite' ) . '</span></span>';
    echo '</a>';

    echo '</div>';

    if ( $featured instanceof WC_Product ) {
        echo '<section class="cvl-category-featured-product">';
        echo '<h2 class="cvl-category-widget-title">' . esc_html__( 'PRODUTO EM DESTAQUE', 'chavevertical-lite' ) . '</h2>';
        echo '<a class="cvl-category-featured-link" href="' . esc_url( $featured->get_permalink() ) . '" aria-label="' . esc_attr( $featured->get_name() ) . '">';
        echo wp_kses_post(
            $featured->get_image(
                'woocommerce_single',
                array(
                    'loading' => 'lazy',
                    'alt'     => $featured->get_name(),
                )
            )
        );
        echo '</a>';
        echo '</section>';
    }

    echo '</aside>';
}


/**
 * Category archives — search-style catalogue with category, brand and price filters.
 */
function cvl_category_archive_branch_ids( WP_Term $term ): array {
    $children = get_term_children( $term->term_id, 'product_cat' );
    $children = is_wp_error( $children ) ? array() : array_map( 'absint', $children );

    return array_values( array_unique( array_merge( array( (int) $term->term_id ), $children ) ) );
}


/**
 * Devolve todos os produtos publicados associados a qualquer categoria do ramo.
 * É independente da query nativa do arquivo e inclui produtos atribuídos apenas
 * às categorias filhas/netas.
 */
function cvl_category_archive_product_ids( array $category_ids ): array {
    global $wpdb;

    $category_ids = array_values( array_filter( array_map( 'absint', $category_ids ) ) );
    if ( ! $category_ids ) {
        return array();
    }

    $placeholders = implode( ',', array_fill( 0, count( $category_ids ), '%d' ) );

    $sql = "
        SELECT DISTINCT p.ID
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
        INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
        WHERE p.post_type = 'product'
          AND p.post_status = 'publish'
          AND tt.taxonomy = 'product_cat'
          AND tt.term_id IN ({$placeholders})
    ";

    $ids = $wpdb->get_col( $wpdb->prepare( $sql, $category_ids ) );

    return array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
}

function cvl_category_archive_selected_category( WP_Term $base_term ): ?WP_Term {
    $slug = isset( $_GET['categoria'] ) ? sanitize_title( wp_unslash( $_GET['categoria'] ) ) : '';

    if ( ! $slug || $slug === $base_term->slug ) {
        return null;
    }

    $term = get_term_by( 'slug', $slug, 'product_cat' );
    if ( ! $term instanceof WP_Term ) {
        return null;
    }

    return in_array( (int) $term->term_id, cvl_category_archive_branch_ids( $base_term ), true )
        ? $term
        : null;
}

function cvl_category_archive_brand_facets( array $category_ids, float $min_price = 0.0, float $max_price = 0.0 ): array {
    if ( ! taxonomy_exists( 'product_brand' ) || ! $category_ids ) {
        return array();
    }

    global $wpdb;

    $category_ids = array_values( array_filter( array_map( 'absint', $category_ids ) ) );
    if ( ! $category_ids ) {
        return array();
    }

    $placeholders = implode( ',', array_fill( 0, count( $category_ids ), '%d' ) );
    $lookup_table = $wpdb->prefix . 'wc_product_meta_lookup';
    $sql = "
        SELECT t.term_id, t.slug, t.name, COUNT(DISTINCT p.ID) AS product_count
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->term_relationships} trc ON trc.object_id = p.ID
        INNER JOIN {$wpdb->term_taxonomy} ttc
            ON ttc.term_taxonomy_id = trc.term_taxonomy_id
            AND ttc.taxonomy = 'product_cat'
        INNER JOIN {$wpdb->term_relationships} trb ON trb.object_id = p.ID
        INNER JOIN {$wpdb->term_taxonomy} ttb
            ON ttb.term_taxonomy_id = trb.term_taxonomy_id
            AND ttb.taxonomy = 'product_brand'
        INNER JOIN {$wpdb->terms} t ON t.term_id = ttb.term_id
        INNER JOIN {$lookup_table} l ON l.product_id = p.ID
        WHERE p.post_type = 'product'
          AND p.post_status = 'publish'
          AND ttc.term_id IN ({$placeholders})
    ";

    $args = $category_ids;

    if ( $min_price > 0 ) {
        $sql .= ' AND l.max_price >= %f';
        $args[] = $min_price;
    }

    if ( $max_price > 0 ) {
        $sql .= ' AND l.min_price <= %f';
        $args[] = $max_price;
    }

    $sql .= ' GROUP BY t.term_id, t.slug, t.name ORDER BY product_count DESC, t.name ASC';

    $prepared = $wpdb->prepare( $sql, $args );
    $rows = $wpdb->get_results( $prepared, ARRAY_A );

    return is_array( $rows ) ? $rows : array();
}

function cvl_category_archive_price_bounds( array $category_ids, $brand_slugs = array() ): array {
    if ( ! $category_ids ) {
        return array( 0.0, 0.0 );
    }

    global $wpdb;

    $category_ids = array_values( array_filter( array_map( 'absint', $category_ids ) ) );
    if ( ! $category_ids ) {
        return array( 0.0, 0.0 );
    }

    $brand_slugs = is_array( $brand_slugs ) ? $brand_slugs : array( $brand_slugs );
    $brand_slugs = array_values(
        array_unique(
            array_filter(
                array_map( 'sanitize_title', $brand_slugs )
            )
        )
    );

    $placeholders = implode( ',', array_fill( 0, count( $category_ids ), '%d' ) );
    $lookup_table = $wpdb->prefix . 'wc_product_meta_lookup';

    $sql = "
        SELECT MIN(l.min_price) AS min_price, MAX(l.max_price) AS max_price
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->term_relationships} trc ON trc.object_id = p.ID
        INNER JOIN {$wpdb->term_taxonomy} ttc
            ON ttc.term_taxonomy_id = trc.term_taxonomy_id
            AND ttc.taxonomy = 'product_cat'
        INNER JOIN {$lookup_table} l ON l.product_id = p.ID
    ";

    $args = $category_ids;

    if ( $brand_slugs && taxonomy_exists( 'product_brand' ) ) {
        $sql .= "
            INNER JOIN {$wpdb->term_relationships} trb ON trb.object_id = p.ID
            INNER JOIN {$wpdb->term_taxonomy} ttb
                ON ttb.term_taxonomy_id = trb.term_taxonomy_id
                AND ttb.taxonomy = 'product_brand'
            INNER JOIN {$wpdb->terms} tb ON tb.term_id = ttb.term_id
        ";
    }

    $sql .= "
        WHERE p.post_type = 'product'
          AND p.post_status = 'publish'
          AND ttc.term_id IN ({$placeholders})
    ";

    if ( $brand_slugs && taxonomy_exists( 'product_brand' ) ) {
        $brand_placeholders = implode( ',', array_fill( 0, count( $brand_slugs ), '%s' ) );
        $sql .= " AND tb.slug IN ({$brand_placeholders})";
        $args = array_merge( $args, $brand_slugs );
    }

    $row = $wpdb->get_row( $wpdb->prepare( $sql, $args ), ARRAY_A );

    return array(
        isset( $row['min_price'] ) ? (float) $row['min_price'] : 0.0,
        isset( $row['max_price'] ) ? (float) $row['max_price'] : 0.0,
    );
}

/**
 * Remove de uma tax_query qualquer cláusula de uma determinada taxonomia,
 * preservando as restantes (visibilidade, marcas, etc.).
 */
function cvl_category_archive_without_taxonomy( array $tax_query, string $taxonomy ): array {
    $filtered = array();

    foreach ( $tax_query as $key => $clause ) {
        if ( 'relation' === $key ) {
            $filtered['relation'] = $clause;
            continue;
        }

        if ( ! is_array( $clause ) ) {
            continue;
        }

        if ( isset( $clause['taxonomy'] ) ) {
            if ( $taxonomy !== (string) $clause['taxonomy'] ) {
                $filtered[] = $clause;
            }
            continue;
        }

        $nested = cvl_category_archive_without_taxonomy( $clause, $taxonomy );
        $nested_clauses = array_filter(
            $nested,
            static fn( $nested_key ): bool => 'relation' !== $nested_key,
            ARRAY_FILTER_USE_KEY
        );

        if ( $nested_clauses ) {
            $filtered[] = $nested;
        }
    }

    return $filtered;
}

function cvl_product_category_search_layout(): void {
    $base_term = get_queried_object();

    if ( ! $base_term instanceof WP_Term || 'product_cat' !== $base_term->taxonomy ) {
        return;
    }

    $selected_category = cvl_category_archive_selected_category( $base_term );

    /*
     * O nível atualmente navegado define se estamos numa mãe/intermédia ou numa
     * categoria final. Mães/intermédias mostram apenas CATEGORIAS; finais mostram
     * apenas MARCAS + PREÇO.
     */
    $navigation_parent = $selected_category instanceof WP_Term ? $selected_category : $base_term;

    $category_terms = get_terms(
        array(
            'taxonomy'   => 'product_cat',
            /*
             * Não usar hide_empty=true aqui: uma categoria filha pode não ter
             * produtos atribuídos diretamente e ainda assim ter produtos nas
             * suas descendentes. Ela continua a fazer parte da navegação.
             */
            'hide_empty' => false,
            'pad_counts' => true,
            'parent'     => (int) $navigation_parent->term_id,
            'orderby'    => 'name',
            'order'      => 'ASC',
        )
    );
    $category_terms = is_wp_error( $category_terms ) ? array() : $category_terms;

    $is_final = empty( $category_terms );

    $catalog_view = isset( $_GET['vista'] )
        ? sanitize_key( wp_unslash( $_GET['vista'] ) )
        : 'produtos';

    if ( ! in_array( $catalog_view, array( 'produtos', 'categorias' ), true ) ) {
        $catalog_view = 'produtos';
    }

    // Uma categoria final não tem mais níveis para navegar em cards.
    if ( $is_final && 'categorias' === $catalog_view ) {
        $catalog_view = 'produtos';
    }

    $selected_brands = array();

    if ( $is_final && isset( $_GET['marca'] ) ) {
        $raw_brands = (array) wp_unslash( $_GET['marca'] );
        $selected_brands = array_values(
            array_unique(
                array_filter(
                    array_map( 'sanitize_title', $raw_brands )
                )
            )
        );
    }

    $min_price = $is_final && isset( $_GET['min_price'] )
        ? max( 0, (float) wc_format_decimal( wp_unslash( $_GET['min_price'] ) ) )
        : 0.0;
    $max_price = $is_final && isset( $_GET['max_price'] )
        ? max( 0, (float) wc_format_decimal( wp_unslash( $_GET['max_price'] ) ) )
        : 0.0;

    $facet_category_ids = cvl_category_archive_branch_ids( $navigation_parent );
    $brand_facets = $is_final
        ? cvl_category_archive_brand_facets( $facet_category_ids, $min_price, $max_price )
        : array();

    if ( $is_final ) {
        list( $price_floor_raw, $price_ceil_raw ) = cvl_category_archive_price_bounds( $facet_category_ids, $selected_brands );
    } else {
        $price_floor_raw = 0.0;
        $price_ceil_raw  = 0.0;
    }

    // O carrossel usa exatamente o mesmo nível hierárquico dos filtros.
    $carousel_parent = $navigation_parent;
    $carousel_terms  = $category_terms;

    $base_url = get_term_link( $base_term );
    if ( is_wp_error( $base_url ) ) {
        $base_url = home_url( '/' );
    }

    $navigation_url = get_term_link( $navigation_parent );
    if ( is_wp_error( $navigation_url ) ) {
        $navigation_url = $base_url;
    }

    $products_tab_url   = $navigation_url;
    $categories_tab_url = add_query_arg( 'vista', 'categorias', $navigation_url );

    $filter_url = static function ( array $overrides = array() ) use (
        $base_url,
        $selected_category,
        $selected_brands,
        $min_price,
        $max_price
    ): string {
        $state = array(
            'categoria' => $selected_category instanceof WP_Term ? $selected_category->slug : '',
            'marca'     => $selected_brands,
            'min_price' => $min_price > 0 ? $min_price : '',
            'max_price' => $max_price > 0 ? $max_price : '',
        );

        foreach ( $overrides as $key => $value ) {
            $state[ $key ] = $value;
        }

        return add_query_arg(
            array_filter(
                $state,
                static function ( $value ): bool {
                    return is_array( $value )
                        ? ! empty( $value )
                        : '' !== $value && null !== $value;
                }
            ),
            $base_url
        );
    };

    if ( 'categorias' === $catalog_view ) {
        $category_tree = function_exists( 'cvl_get_product_category_tree' )
            ? cvl_get_product_category_tree()
            : array();

        $ancestor_ids = array_reverse(
            get_ancestors( (int) $navigation_parent->term_id, 'product_cat', 'taxonomy' )
        );
        $breadcrumb_terms = array();

        foreach ( $ancestor_ids as $ancestor_id ) {
            $ancestor_term = get_term( (int) $ancestor_id, 'product_cat' );
            if ( $ancestor_term instanceof WP_Term ) {
                $breadcrumb_terms[] = $ancestor_term;
            }
        }

        $breadcrumb_terms[] = $navigation_parent;
        ?>
        <div class="cvl-shell cvl-content cvl-search-page cvl-category-catalog-page is-parent-category is-categories-view">
            <header class="cvl-search-heading cvl-search-heading-compact">
                <span><?php esc_html_e( 'CATEGORIA', 'chavevertical-lite' ); ?></span>
                <h1><?php echo esc_html( $navigation_parent->name ); ?></h1>
                <p class="cvl-search-count"><?php esc_html_e( 'Escolha uma subcategoria para continuar.', 'chavevertical-lite' ); ?></p>
            </header>

            <nav class="cvl-category-view-tabs" aria-label="<?php esc_attr_e( 'Vista da categoria', 'chavevertical-lite' ); ?>">
                <a class="cvl-category-view-tab" href="<?php echo esc_url( $products_tab_url ); ?>"><?php esc_html_e( 'Produtos', 'chavevertical-lite' ); ?></a>
                <a class="cvl-category-view-tab is-active" href="<?php echo esc_url( $categories_tab_url ); ?>" aria-current="page"><?php esc_html_e( 'Categorias', 'chavevertical-lite' ); ?></a>
            </nav>

            <nav class="cvl-category-browser-breadcrumb" aria-label="<?php esc_attr_e( 'Percurso de categorias', 'chavevertical-lite' ); ?>">
                <?php
                $shop_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
                ?>
                <a href="<?php echo esc_url( $shop_url ); ?>"><?php esc_html_e( 'Catálogo', 'chavevertical-lite' ); ?></a>
                <?php foreach ( $breadcrumb_terms as $index => $crumb_term ) : ?>
                    <span aria-hidden="true">›</span>
                    <?php if ( $index === array_key_last( $breadcrumb_terms ) ) : ?>
                        <strong aria-current="page"><?php echo esc_html( $crumb_term->name ); ?></strong>
                    <?php else : ?>
                        <?php
                        $crumb_url = get_term_link( $crumb_term );
                        if ( ! is_wp_error( $crumb_url ) ) {
                            $crumb_url = add_query_arg( 'vista', 'categorias', $crumb_url );
                        }
                        ?>
                        <?php if ( ! is_wp_error( $crumb_url ) ) : ?>
                            <a href="<?php echo esc_url( $crumb_url ); ?>"><?php echo esc_html( $crumb_term->name ); ?></a>
                        <?php else : ?>
                            <span><?php echo esc_html( $crumb_term->name ); ?></span>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php endforeach; ?>
            </nav>

            <section class="cvl-category-browser" aria-label="<?php esc_attr_e( 'Subcategorias', 'chavevertical-lite' ); ?>">
                <div class="cvl-category-browser-grid">
                    <?php foreach ( $category_terms as $term ) : ?>
                        <?php
                        if ( ! $term instanceof WP_Term ) {
                            continue;
                        }

                        $term_id      = (int) $term->term_id;
                        $has_children = ! empty( $category_tree[ $term_id ] );
                        $term_url      = get_term_link( $term );

                        if ( is_wp_error( $term_url ) ) {
                            continue;
                        }

                        if ( $has_children ) {
                            $term_url = add_query_arg( 'vista', 'categorias', $term_url );
                        }

                        $thumbnail_id = absint( get_term_meta( $term_id, 'thumbnail_id', true ) );
                        $image_url    = $thumbnail_id
                            ? wp_get_attachment_image_url( $thumbnail_id, 'woocommerce_thumbnail' )
                            : '';

                        if ( ! $image_url && function_exists( 'wc_placeholder_img_src' ) ) {
                            $image_url = wc_placeholder_img_src( 'woocommerce_thumbnail' );
                        }
                        ?>
                        <a class="cvl-category-browser-card" href="<?php echo esc_url( $term_url ); ?>">
                            <span class="cvl-category-browser-image">
                                <?php if ( $image_url ) : ?>
                                    <img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $term->name ); ?>" loading="lazy" decoding="async">
                                <?php endif; ?>
                            </span>
                            <span class="cvl-category-browser-copy">
                                <strong><?php echo esc_html( $term->name ); ?></strong>
                                <small><?php echo esc_html( $has_children ? __( 'Explorar subcategorias', 'chavevertical-lite' ) : __( 'Ver produtos', 'chavevertical-lite' ) ); ?></small>
                            </span>
                            <span class="cvl-category-browser-arrow" aria-hidden="true">›</span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>
        <?php
        return;
    }

    /*
     * Consulta própria do catálogo.
     *
     * Não usa a query principal do WooCommerce. Assim a categoria mãe e as
     * intermédias incluem sempre produtos atribuídos apenas às filhas/netas.
     */
    $catalog_page = isset( $_GET['cvl_page'] )
        ? max( 1, absint( wp_unslash( $_GET['cvl_page'] ) ) )
        : 1;

    $catalog_tax_query = array(
        array(
            'taxonomy'         => 'product_cat',
            'field'            => 'term_id',
            'terms'            => array( (int) $navigation_parent->term_id ),
            'include_children' => true,
            'operator'         => 'IN',
        ),
    );

    if ( $is_final && $selected_brands && taxonomy_exists( 'product_brand' ) ) {
        $catalog_tax_query[] = array(
            'taxonomy' => 'product_brand',
            'field'    => 'slug',
            'terms'    => $selected_brands,
            'operator' => 'IN',
        );
    }

    if ( function_exists( 'wc_get_product_visibility_term_ids' ) ) {
        $visibility_ids = wc_get_product_visibility_term_ids();
        $excluded_visibility = array();

        if ( ! empty( $visibility_ids['exclude-from-catalog'] ) ) {
            $excluded_visibility[] = (int) $visibility_ids['exclude-from-catalog'];
        }

        if (
            'yes' === get_option( 'woocommerce_hide_out_of_stock_items' )
            && ! empty( $visibility_ids['outofstock'] )
        ) {
            $excluded_visibility[] = (int) $visibility_ids['outofstock'];
        }

        if ( $excluded_visibility ) {
            $catalog_tax_query[] = array(
                'taxonomy' => 'product_visibility',
                'field'    => 'term_id',
                'terms'    => $excluded_visibility,
                'operator' => 'NOT IN',
            );
        }
    }

    $catalog_meta_query = array();

    if ( $is_final && ( $min_price > 0 || $max_price > 0 ) ) {
        $price_rule = array(
            'key'  => '_price',
            'type' => 'NUMERIC',
        );

        if ( $min_price > 0 && $max_price > 0 ) {
            $price_rule['value']   = array( $min_price, $max_price );
            $price_rule['compare'] = 'BETWEEN';
        } elseif ( $min_price > 0 ) {
            $price_rule['value']   = $min_price;
            $price_rule['compare'] = '>=';
        } else {
            $price_rule['value']   = $max_price;
            $price_rule['compare'] = '<=';
        }

        $catalog_meta_query[] = $price_rule;
    }

    $catalog_args = array(
        'post_type'              => 'product',
        'post_status'            => 'publish',
        'posts_per_page'         => 24,
        'paged'                  => $catalog_page,
        'ignore_sticky_posts'    => true,
        'no_found_rows'          => false,
        'tax_query'              => $catalog_tax_query,
        'meta_query'             => $catalog_meta_query,
        'orderby'                => array(
            'menu_order' => 'ASC',
            'date'       => 'DESC',
        ),
    );

    $catalog_query = new WP_Query( $catalog_args );
    $found         = (int) $catalog_query->found_posts;
    ?>
    <div class="cvl-shell cvl-content cvl-search-page cvl-category-catalog-page<?php echo $is_final ? ' is-final-category' : ' is-parent-category'; ?>">
        <header class="cvl-search-heading cvl-search-heading-compact">
            <span><?php esc_html_e( 'CATEGORIA', 'chavevertical-lite' ); ?></span>
            <h1><?php echo esc_html( $navigation_parent->name ); ?></h1>
            <p class="cvl-search-count">
                <?php
                printf(
                    esc_html( _n( '%s produto encontrado', '%s produtos encontrados', $found, 'chavevertical-lite' ) ),
                    esc_html( number_format_i18n( $found ) )
                );
                ?>
            </p>
        </header>

        <nav class="cvl-category-view-tabs" aria-label="<?php esc_attr_e( 'Vista da categoria', 'chavevertical-lite' ); ?>">
            <a class="cvl-category-view-tab is-active" href="<?php echo esc_url( $products_tab_url ); ?>" aria-current="page"><?php esc_html_e( 'Produtos', 'chavevertical-lite' ); ?></a>
            <?php if ( $is_final ) : ?>
                <span class="cvl-category-view-tab is-disabled" aria-disabled="true"><?php esc_html_e( 'Categorias', 'chavevertical-lite' ); ?></span>
            <?php else : ?>
                <a class="cvl-category-view-tab" href="<?php echo esc_url( $categories_tab_url ); ?>"><?php esc_html_e( 'Categorias', 'chavevertical-lite' ); ?></a>
            <?php endif; ?>
        </nav>

        <?php if ( $carousel_terms ) : ?>
            <section class="cvl-category-carousel" data-cvl-category-carousel aria-label="<?php esc_attr_e( 'Categorias', 'chavevertical-lite' ); ?>">
                <div class="cvl-category-carousel-head">
                    <h2><?php esc_html_e( 'CATEGORIAS', 'chavevertical-lite' ); ?></h2>
                    <div class="cvl-category-carousel-controls" aria-hidden="false">
                        <button type="button" class="cvl-category-carousel-arrow is-prev" data-cvl-category-prev aria-label="<?php esc_attr_e( 'Categorias anteriores', 'chavevertical-lite' ); ?>">‹</button>
                        <button type="button" class="cvl-category-carousel-arrow is-next" data-cvl-category-next aria-label="<?php esc_attr_e( 'Categorias seguintes', 'chavevertical-lite' ); ?>">›</button>
                    </div>
                </div>

                <div class="cvl-category-carousel-viewport" data-cvl-category-viewport tabindex="0">
                    <div class="cvl-category-carousel-track">
                        <?php foreach ( $carousel_terms as $term ) : ?>
                            <?php
                            if ( ! $term instanceof WP_Term ) {
                                continue;
                            }

                            $thumbnail_id = absint( get_term_meta( $term->term_id, 'thumbnail_id', true ) );
                            $image_url = $thumbnail_id ? wp_get_attachment_image_url( $thumbnail_id, 'woocommerce_thumbnail' ) : '';
                            if ( ! $image_url && function_exists( 'wc_placeholder_img_src' ) ) {
                                $image_url = wc_placeholder_img_src( 'woocommerce_thumbnail' );
                            }

                            $active = $selected_category instanceof WP_Term && (int) $selected_category->term_id === (int) $term->term_id;
                            $url = $filter_url( array( 'categoria' => $term->slug ) );
                            ?>
                            <a class="cvl-category-carousel-card<?php echo $active ? ' is-active' : ''; ?>" href="<?php echo esc_url( $url ); ?>">
                                <span class="cvl-category-carousel-image">
                                    <?php if ( $image_url ) : ?>
                                        <img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $term->name ); ?>" loading="lazy" decoding="async">
                                    <?php endif; ?>
                                </span>
                                <span class="cvl-category-carousel-name"><?php echo esc_html( $term->name ); ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <div class="cvl-search-layout">
            <aside class="cvl-search-filters" aria-label="<?php esc_attr_e( 'Filtros da categoria', 'chavevertical-lite' ); ?>">
                <div class="cvl-search-filters-head">
                    <strong><?php esc_html_e( 'FILTRAR PRODUTOS', 'chavevertical-lite' ); ?></strong>
                    <?php if ( $selected_category || $selected_brands || $min_price > 0 || $max_price > 0 ) : ?>
                        <a href="<?php echo esc_url( $base_url ); ?>"><?php esc_html_e( 'Limpar', 'chavevertical-lite' ); ?></a>
                    <?php endif; ?>
                </div>

                <?php if ( $category_terms ) : ?>
                    <?php
                    $render_category_nodes = static function ( array $terms, int $depth = 0 ) use ( &$render_category_nodes, $filter_url, $selected_category ): void {
                        foreach ( $terms as $term ) {
                            if ( ! $term instanceof WP_Term ) {
                                continue;
                            }

                            $children = get_terms(
                                array(
                                    'taxonomy'   => 'product_cat',
                                    'hide_empty' => false,
                                    'pad_counts' => true,
                                    'parent'     => (int) $term->term_id,
                                    'orderby'    => 'name',
                                    'order'      => 'ASC',
                                )
                            );
                            $children = is_wp_error( $children ) ? array() : $children;

                            $active = $selected_category instanceof WP_Term
                                && (int) $selected_category->term_id === (int) $term->term_id;
                            $panel_id = 'cvl-category-children-' . (int) $term->term_id;
                            ?>
                            <div class="cvl-category-filter-item" style="--cvl-cat-depth:<?php echo esc_attr( $depth ); ?>">
                                <div class="cvl-category-filter-row">
                                    <a
                                        class="cvl-category-filter-link<?php echo $active ? ' is-active' : ''; ?>"
                                        href="<?php echo esc_url( $filter_url( array( 'categoria' => $term->slug ) ) ); ?>"
                                    >
                                        <span><?php echo esc_html( $term->name ); ?></span>
                                        <small><?php echo esc_html( number_format_i18n( (int) $term->count ) ); ?></small>
                                    </a>

                                    <?php if ( $children ) : ?>
                                        <button
                                            type="button"
                                            class="cvl-category-filter-toggle"
                                            data-cvl-category-tree-toggle
                                            aria-expanded="false"
                                            aria-controls="<?php echo esc_attr( $panel_id ); ?>"
                                            aria-label="<?php echo esc_attr( sprintf( __( 'Expandir %s', 'chavevertical-lite' ), $term->name ) ); ?>"
                                        ><span aria-hidden="true">›</span></button>
                                    <?php endif; ?>
                                </div>

                                <?php if ( $children ) : ?>
                                    <div
                                        id="<?php echo esc_attr( $panel_id ); ?>"
                                        class="cvl-category-filter-children"
                                        data-cvl-category-tree-children
                                        hidden
                                    >
                                        <?php $render_category_nodes( $children, $depth + 1 ); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php
                        }
                    };
                    ?>
                    <section class="cvl-search-filter-group cvl-category-filter-group">
                        <h2><?php esc_html_e( 'CATEGORIAS', 'chavevertical-lite' ); ?></h2>
                        <div class="cvl-category-filter-tree" data-cvl-category-filter-tree>
                            <?php $render_category_nodes( $category_terms ); ?>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if ( $is_final && $brand_facets ) : ?>
                    <section class="cvl-final-brand-filter" aria-label="<?php esc_attr_e( 'Filtrar por marcas', 'chavevertical-lite' ); ?>">
                        <h2><?php esc_html_e( 'FILTRAR POR MARCAS', 'chavevertical-lite' ); ?></h2>

                        <form class="cvl-final-brand-filter-form" method="get" action="<?php echo esc_url( $base_url ); ?>" data-cvl-brand-auto-filter>
                            <?php if ( $selected_category instanceof WP_Term ) : ?>
                                <input type="hidden" name="categoria" value="<?php echo esc_attr( $selected_category->slug ); ?>">
                            <?php endif; ?>
                            <?php if ( $min_price > 0 ) : ?>
                                <input type="hidden" name="min_price" value="<?php echo esc_attr( wc_format_decimal( $min_price, 2 ) ); ?>">
                            <?php endif; ?>
                            <?php if ( $max_price > 0 ) : ?>
                                <input type="hidden" name="max_price" value="<?php echo esc_attr( wc_format_decimal( $max_price, 2 ) ); ?>">
                            <?php endif; ?>

                            <div class="cvl-final-brand-search">
                                <input
                                    type="search"
                                    class="cvl-final-brand-search-input"
                                    data-cvl-brand-search
                                    placeholder="<?php esc_attr_e( 'Pesquisar marca…', 'chavevertical-lite' ); ?>"
                                    aria-label="<?php esc_attr_e( 'Pesquisar marca', 'chavevertical-lite' ); ?>"
                                    autocomplete="off"
                                    spellcheck="false"
                                >
                            </div>
                            <p class="cvl-final-brand-search-empty" data-cvl-brand-search-empty hidden><?php esc_html_e( 'Nenhuma marca encontrada.', 'chavevertical-lite' ); ?></p>

                            <div class="cvl-final-brand-chips">
                                <?php foreach ( $brand_facets as $facet ) : ?>
                                    <?php
                                    $slug = sanitize_title( (string) ( $facet['slug'] ?? '' ) );
                                    if ( ! $slug ) {
                                        continue;
                                    }

                                    $active   = in_array( $slug, $selected_brands, true );
                                    $input_id = 'cvl-brand-' . sanitize_html_class( $slug );
                                    ?>
                                    <label class="cvl-final-brand-chip<?php echo $active ? ' is-active' : ''; ?>" for="<?php echo esc_attr( $input_id ); ?>">
                                        <input
                                            id="<?php echo esc_attr( $input_id ); ?>"
                                            type="checkbox"
                                            name="marca[]"
                                            value="<?php echo esc_attr( $slug ); ?>"
                                            <?php checked( $active ); ?>
                                        >
                                        <span><?php echo esc_html( $facet['name'] ?? $slug ); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>

                        </form>
                    </section>
                <?php endif; ?>

                <?php if ( $is_final ) : ?>
                <section class="cvl-search-filter-group">
                    <h2><?php esc_html_e( 'PREÇO', 'chavevertical-lite' ); ?></h2>
                    <form class="cvl-search-price-filter" method="get" action="<?php echo esc_url( $base_url ); ?>">
                        <?php if ( $selected_category instanceof WP_Term ) : ?><input type="hidden" name="categoria" value="<?php echo esc_attr( $selected_category->slug ); ?>"><?php endif; ?>
                        <?php foreach ( $selected_brands as $selected_brand_slug ) : ?>
                            <input type="hidden" name="marca[]" value="<?php echo esc_attr( $selected_brand_slug ); ?>">
                        <?php endforeach; ?>

                        <div class="cvl-search-price-inputs">
                            <label>
                                <span><?php esc_html_e( 'Mín.', 'chavevertical-lite' ); ?></span>
                                <input type="number" min="0" step="0.01" name="min_price" value="<?php echo $min_price > 0 ? esc_attr( wc_format_decimal( $min_price, 2 ) ) : ''; ?>" placeholder="<?php echo esc_attr( number_format_i18n( floor( $price_floor_raw ), 0 ) ); ?>">
                            </label>
                            <label>
                                <span><?php esc_html_e( 'Máx.', 'chavevertical-lite' ); ?></span>
                                <input type="number" min="0" step="0.01" name="max_price" value="<?php echo $max_price > 0 ? esc_attr( wc_format_decimal( $max_price, 2 ) ) : ''; ?>" placeholder="<?php echo esc_attr( number_format_i18n( ceil( $price_ceil_raw ), 0 ) ); ?>">
                            </label>
                        </div>

                        <?php if ( $price_ceil_raw > 0 ) : ?>
                            <div class="cvl-search-price-range"><?php echo wp_kses_post( wc_price( floor( $price_floor_raw ) ) ); ?> – <?php echo wp_kses_post( wc_price( ceil( $price_ceil_raw ) ) ); ?></div>
                        <?php endif; ?>

                        <button type="submit"><?php esc_html_e( 'APLICAR PREÇO', 'chavevertical-lite' ); ?></button>
                    </form>
                </section>
                <?php endif; ?>
            </aside>

            <section class="cvl-search-products woocommerce">
                <?php if ( $catalog_query->have_posts() ) : ?>
                    <?php
                    wc_set_loop_prop( 'total', $catalog_query->found_posts );
                    wc_set_loop_prop( 'total_pages', $catalog_query->max_num_pages );
                    wc_set_loop_prop( 'current_page', $catalog_page );
                    wc_set_loop_prop( 'per_page', 24 );
                    wc_set_loop_prop( 'is_paginated', true );
                    ?>
                    <?php woocommerce_product_loop_start(); ?>
                    <?php while ( $catalog_query->have_posts() ) : $catalog_query->the_post(); ?>
                        <?php wc_get_template_part( 'content', 'product' ); ?>
                    <?php endwhile; ?>
                    <?php woocommerce_product_loop_end(); ?>
                    <?php wp_reset_postdata(); ?>

                    <?php
                    $max_pages = max( 1, (int) $catalog_query->max_num_pages );
                    $next_url = $catalog_page < $max_pages
                        ? add_query_arg( 'cvl_page', $catalog_page + 1, $filter_url() )
                        : '';
                    ?>
                    <?php if ( $next_url ) : ?>
                        <div class="cvl-search-load-more-wrap">
                            <a
                                class="cvl-search-load-more"
                                href="<?php echo esc_url( $next_url ); ?>"
                                data-cvl-search-load-more
                                data-loading-label="<?php echo esc_attr__( 'A CARREGAR…', 'chavevertical-lite' ); ?>"
                            ><?php esc_html_e( 'CARREGAR MAIS', 'chavevertical-lite' ); ?></a>
                            <span class="cvl-search-load-more-status screen-reader-text" data-cvl-search-load-more-status aria-live="polite"></span>
                        </div>
                    <?php endif; ?>
                <?php else : ?>
                    <div class="cvl-empty-state">
                        <h2><?php esc_html_e( 'Não encontrámos produtos.', 'chavevertical-lite' ); ?></h2>
                        <p><?php esc_html_e( 'Experimente remover ou alterar os filtros selecionados.', 'chavevertical-lite' ); ?></p>
                        <a class="cvl-button cvl-button-primary" href="<?php echo esc_url( $base_url ); ?>"><?php esc_html_e( 'LIMPAR FILTROS', 'chavevertical-lite' ); ?></a>
                    </div>
                <?php endif; ?>
                <?php wc_reset_loop(); ?>
            </section>
        </div>
    </div>
    <?php
}
