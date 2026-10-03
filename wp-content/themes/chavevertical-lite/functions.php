<?php
defined( 'ABSPATH' ) || exit;

define( 'CVL_VERSION', '0.6.0' );

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
 * Ficha de produto: referência e marca imediatamente abaixo do título.
 */
function cvl_single_product_meta_top() {
    global $product;

    if ( ! class_exists( 'WC_Product' ) || ! $product instanceof WC_Product ) {
        return;
    }

    $sku   = $product->get_sku();
    $brand = cvl_get_product_brand( $product->get_id() );

    if ( ! $sku && ! $brand ) {
        return;
    }

    echo '<div class="cvl-single-meta-top">';

    if ( $sku ) {
        echo '<div class="cvl-single-sku"><small>' . esc_html__( 'Referência', 'chavevertical-lite' ) . '</small><strong>' . esc_html( $sku ) . '</strong></div>';
    }

    if ( $brand ) {
        echo '<div class="cvl-single-brand">';

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

    echo '</div>';
}
add_action( 'woocommerce_single_product_summary', 'cvl_single_product_meta_top', 7 );

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
