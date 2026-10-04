<?php
defined( 'ABSPATH' ) || exit;

define( 'CVL_VERSION', '0.12.3' );

$cvl_homepage_highlights_file = get_template_directory() . '/inc/homepage-highlights.php';
if ( file_exists( $cvl_homepage_highlights_file ) ) {
    require_once $cvl_homepage_highlights_file;
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
        CVL_VERSION
    );

    wp_enqueue_style(
        'cvl-v04',
        get_template_directory_uri() . '/assets/css/v04.css',
        array( 'cvl-main' ),
        CVL_VERSION
    );

    wp_enqueue_style(
        'cvl-v05',
        get_template_directory_uri() . '/assets/css/v05.css',
        array( 'cvl-v04' ),
        CVL_VERSION
    );

    wp_enqueue_style(
        'cvl-v06',
        get_template_directory_uri() . '/assets/css/v06.css',
        array( 'cvl-v05' ),
        CVL_VERSION
    );

    wp_enqueue_style(
        'cvl-v07',
        get_template_directory_uri() . '/assets/css/v07.css',
        array( 'cvl-v06' ),
        CVL_VERSION
    );

    wp_enqueue_style(
        'cvl-v08',
        get_template_directory_uri() . '/assets/css/v08.css',
        array( 'cvl-v07' ),
        CVL_VERSION
    );

    wp_enqueue_style(
        'cvl-v09',
        get_template_directory_uri() . '/assets/css/v09.css',
        array( 'cvl-v08' ),
        CVL_VERSION
    );

    if ( function_exists( 'is_product' ) && is_product() ) {
        wp_enqueue_style(
            'cvl-v10',
            get_template_directory_uri() . '/assets/css/v10.css',
            array( 'cvl-v09' ),
            CVL_VERSION
        );

        wp_enqueue_script(
            'cvl-product',
            get_template_directory_uri() . '/assets/js/product.js',
            array(),
            CVL_VERSION,
            true
        );
    }

    if ( function_exists( 'is_product_category' ) && is_product_category() ) {
        wp_enqueue_style(
            'cvl-v11',
            get_template_directory_uri() . '/assets/css/v11.css',
            array( 'cvl-v09' ),
            CVL_VERSION
        );

        wp_enqueue_script(
            'cvl-category',
            get_template_directory_uri() . '/assets/js/category.js',
            array(),
            CVL_VERSION,
            true
        );
    }

    if ( is_page_template( 'page-contactos.php' ) || is_page( 'contactos' ) ) {
        wp_enqueue_style(
            'cvl-v12',
            get_template_directory_uri() . '/assets/css/v12.css',
            array( 'cvl-v09' ),
            CVL_VERSION
        );
    }

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
    foreach ( array( 'sobre-nos', 'sobrenos' ) as $slug ) {
        $page = get_page_by_path( $slug, OBJECT, 'page' );

        if ( $page instanceof WP_Post ) {
            return get_permalink( $page->ID );
        }
    }

    return 'https://chavevertical.com/sobrenos/';
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
 * Card de produto: categoria por cima do título.
 */
function cvl_loop_product_category() {
    global $product;

    if ( ! class_exists( 'WC_Product' ) || ! $product instanceof WC_Product ) {
        return;
    }

    $category = cvl_get_product_primary_category( $product->get_id() );

    if ( ! $category ) {
        return;
    }

    echo '<span class="cvl-product-category">' . esc_html( $category->name ) . '</span>';
}
add_action( 'woocommerce_before_shop_loop_item_title', 'cvl_loop_product_category', 20 );

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

    echo '<div class="cvl-product-brand-row">';

    if ( $brand ) {
        echo '<span class="cvl-product-brand" aria-label="' . esc_attr( $brand['name'] ) . '">';

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

        echo '</span>';
    }

    echo '</div>';

    echo '<div class="cvl-product-sku-row">';

    if ( $sku ) {
        echo '<span class="cvl-product-sku" title="' . esc_attr( $sku ) . '"><i aria-hidden="true"></i>' . esc_html( $sku ) . '</span>';
    }

    echo '</div>';
}
add_action( 'woocommerce_after_shop_loop_item_title', 'cvl_loop_product_meta', 4 );

/**
 * Estado comercial no canto superior da imagem, como no layout Shopware.
 */
function cvl_loop_product_badge() {
    global $product;

    if ( ! class_exists( 'WC_Product' ) || ! $product instanceof WC_Product ) {
        return;
    }

    $status = $product->get_stock_status();
    $class  = '';
    $label  = __( 'POR ENCOMENDA', 'chavevertical-lite' );

    if ( $product->is_on_sale() ) {
        $class = 'is-sale';
        $label = __( 'PROMOÇÃO', 'chavevertical-lite' );
    } elseif ( 'instock' === $status ) {
        $label = __( 'EM STOCK', 'chavevertical-lite' );
    } elseif ( 'outofstock' === $status ) {
        $class = 'is-unavailable';
        $label = __( 'SOB CONSULTA', 'chavevertical-lite' );
    }

    echo '<span class="cvl-product-badge ' . esc_attr( $class ) . '">' . esc_html( $label ) . '</span>';
}
add_action( 'woocommerce_before_shop_loop_item_title', 'cvl_loop_product_badge', 5 );

/**
 * Card de produto: disponibilidade perto do rating/preço.
 */
function cvl_loop_product_stock() {
    global $product;

    if ( ! class_exists( 'WC_Product' ) || ! $product instanceof WC_Product ) {
        return;
    }

    $status = $product->get_stock_status();
    $class  = 'is-backorder';
    $label  = __( 'Disponível por encomenda', 'chavevertical-lite' );

    if ( 'instock' === $status ) {
        $class = 'is-instock';
        $label = __( 'Em stock', 'chavevertical-lite' );
    } elseif ( 'outofstock' === $status ) {
        $class = 'is-outofstock';
        $label = __( 'Sob consulta', 'chavevertical-lite' );
    }

    echo '<div class="cvl-product-stock ' . esc_attr( $class ) . '"><span aria-hidden="true"></span>' . esc_html( $label ) . '</div>';
}
add_action( 'woocommerce_after_shop_loop_item_title', 'cvl_loop_product_stock', 7 );

/**
 * Ações do card: botão principal WooCommerce + botão Ver.
 */
function cvl_loop_actions_open() {
    echo '<div class="cvl-product-actions">';
}
add_action( 'woocommerce_after_shop_loop_item', 'cvl_loop_actions_open', 9 );

function cvl_loop_view_button() {
    global $product;

    if ( ! class_exists( 'WC_Product' ) || ! $product instanceof WC_Product ) {
        return;
    }

    echo '<a class="button cvl-view-product" href="' . esc_url( $product->get_permalink() ) . '">' . esc_html__( 'Ver', 'chavevertical-lite' ) . '</a>';
}
add_action( 'woocommerce_after_shop_loop_item', 'cvl_loop_view_button', 15 );

function cvl_loop_actions_close() {
    echo '</div>';
}
add_action( 'woocommerce_after_shop_loop_item', 'cvl_loop_actions_close', 20 );

add_filter( 'woocommerce_product_add_to_cart_text', function ( $text, $product ) {
    if ( class_exists( 'WC_Product' ) && $product instanceof WC_Product && $product->is_type( 'simple' ) && $product->is_purchasable() && $product->is_in_stock() ) {
        return __( 'Adicionar ao carrinho', 'chavevertical-lite' );
    }

    return __( 'Ver produto', 'chavevertical-lite' );
}, 10, 2 );

add_filter( 'woocommerce_loop_add_to_cart_link', function ( $html, $product ) {
    if ( ! class_exists( 'WC_Product' ) || ! $product instanceof WC_Product || ! $product->is_type( 'simple' ) || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
        return $html;
    }

    $icon = '<svg class="cvl-cart-button-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h2l2.1 10.2a2 2 0 0 0 2 1.6h7.8a2 2 0 0 0 2-1.6L20 8H7"></path><circle cx="10" cy="20" r="1.2"></circle><circle cx="18" cy="20" r="1.2"></circle></svg>';
    $label = esc_html__( 'Adicionar ao carrinho', 'chavevertical-lite' );
    $replacement = '>' . $icon . '<span class="screen-reader-text">' . $label . '</span></a>';

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
}, 20 );

/**
 * Badge comercial por cima da galeria.
 */
function cvl_single_product_badge() {
    global $product;

    if ( ! class_exists( 'WC_Product' ) || ! $product instanceof WC_Product ) {
        return;
    }

    $status = $product->get_stock_status();
    $class  = 'is-backorder';
    $label  = __( 'SOB ENCOMENDA', 'chavevertical-lite' );

    if ( $product->is_on_sale() ) {
        $class = 'is-sale';
        $label = __( 'PROMOÇÃO', 'chavevertical-lite' );
    } elseif ( 'instock' === $status ) {
        $class = 'is-stock';
        $label = __( 'EM STOCK', 'chavevertical-lite' );
    } elseif ( 'outofstock' === $status ) {
        $class = 'is-danger';
        $label = __( 'SOB CONSULTA', 'chavevertical-lite' );
    }

    echo '<span class="cvl-single-image-badge ' . esc_attr( $class ) . '">' . esc_html( $label ) . '</span>';
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
add_action( 'woocommerce_single_product_summary', 'cvl_single_product_rating_row', 10 );

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
add_action( 'woocommerce_single_product_summary', 'cvl_single_product_brand_block', 12 );

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

    $status = $product->get_stock_status();
    $class  = 'is-onbackorder';
    $label  = __( 'Disponível por encomenda', 'chavevertical-lite' );

    if ( 'instock' === $status ) {
        $class = 'is-instock';
        $label = __( 'Disponível para entrega imediata', 'chavevertical-lite' );
    } elseif ( 'outofstock' === $status ) {
        $class = 'is-outofstock';
        $label = __( 'Sob consulta', 'chavevertical-lite' );
    }

    $sku      = $product->get_sku();
    $category = cvl_get_product_primary_category( $product->get_id() );
    $tags     = wp_get_post_terms( $product->get_id(), 'product_tag', array( 'fields' => 'names' ) );
    $tag_text = ! is_wp_error( $tags ) && ! empty( $tags ) ? implode( ', ', $tags ) : '—';

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
    echo '<span class="cvl-single-wa" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38a9.91 9.91 0 1 0 4.74-18.62Zm5.79 14.1c-.24.68-1.4 1.25-1.92 1.32-.5.07-1.14.1-3.32-.8-2.79-1.15-4.58-4.01-4.72-4.2-.14-.19-1.13-1.5-1.13-2.86 0-1.36.71-2.03.96-2.31.25-.28.56-.35.75-.35.19 0 .37 0 .53.01.17.01.4.06.61.57.24.57.81 1.98.88 2.12.07.14.12.31.02.5-.09.19-.14.31-.28.47-.14.17-.3.37-.43.5-.14.14-.29.3-.12.59.16.28.73 1.2 1.56 1.94 1.07.95 1.97 1.25 2.25 1.39.28.14.45.12.61-.07.17-.19.72-.84.91-1.13.19-.28.38-.24.64-.14.26.09 1.66.78 1.95.92.28.14.47.21.54.33.07.12.07.7-.17 1.38Z"></path></svg></span>';
    echo '<span class="cvl-single-availability-copy"><strong>' . esc_html( $label ) . '</strong><small>' . esc_html__( 'Consulte aqui o prazo de entrega', 'chavevertical-lite' ) . '</small></span>';
    echo '</a>';
    echo '</div>';

    echo '<div class="cvl-single-facts"><table><tbody>';
    echo '<tr><th scope="row">' . esc_html__( 'Disponibilidade:', 'chavevertical-lite' ) . '</th><td><span class="cvl-single-stock-state ' . esc_attr( $class ) . '"><i aria-hidden="true"></i>' . esc_html( $label ) . '</span></td></tr>';

    if ( 'onbackorder' === $status ) {
        echo '<tr><th scope="row">' . esc_html__( 'Prazo de entrega:', 'chavevertical-lite' ) . '</th><td><a href="' . esc_url( $whatsapp_url ) . '" target="_blank" rel="noopener nofollow">' . esc_html__( 'Sujeito a confirmação do fornecedor', 'chavevertical-lite' ) . '</a></td></tr>';
    }

    if ( $sku ) {
        echo '<tr><th scope="row">' . esc_html__( 'Referência:', 'chavevertical-lite' ) . '</th><td>' . esc_html( $sku ) . '</td></tr>';
    }

    if ( $category ) {
        echo '<tr><th scope="row">' . esc_html__( 'Categorias:', 'chavevertical-lite' ) . '</th><td>' . esc_html( $category->name ) . '</td></tr>';
    }

    echo '<tr><th scope="row">' . esc_html__( 'Etiquetas:', 'chavevertical-lite' ) . '</th><td>' . esc_html( $tag_text ) . '</td></tr>';
    echo '</tbody></table></div>';
    echo '</section>';
}
add_action( 'woocommerce_single_product_summary', 'cvl_single_product_info_panel', 25 );

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

    $url = add_query_arg(
        array(
            'produto' => $product->get_name(),
            'sku'     => $product->get_sku(),
        ),
        'https://chavevertical.com/contacto-pedido-de-cotacao/'
    );

    echo '<div class="cvl-single-contact-tab">';
    echo '<h3>' . esc_html__( 'Solicitar Orçamento ou Informação Adicional', 'chavevertical-lite' ) . '</h3>';
    echo '<p>' . esc_html__( 'Tem dúvidas sobre as características técnicas ou pretende encomendar em quantidade? A equipa comercial prepara uma proposta adequada ao seu pedido.', 'chavevertical-lite' ) . '</p>';
    echo '<a class="cvl-single-contact-cta" href="' . esc_url( $url ) . '">' . esc_html__( 'SOLICITAR ORÇAMENTO', 'chavevertical-lite' ) . '</a>';
    echo '</div>';
}

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
    echo '<header class="cvl-section-heading"><span>' . esc_html__( 'CATÁLOGO POR ÁREA', 'chavevertical-lite' ) . '</span><h2>' . esc_html__( 'Categorias', 'chavevertical-lite' ) . '</h2></header>';
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
add_action( 'woocommerce_archive_description', 'cvl_shop_root_category_grid', 20 );


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
