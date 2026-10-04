<?php
/**
 * Promotion catalogue for /shop/?on_sale=1.
 */

defined( 'ABSPATH' ) || exit;


/**
 * Canonical promotions URL used by the catalogue and the main navigation.
 */
function cvl_promotions_url(): string {
    $shop_url = function_exists( 'wc_get_page_permalink' )
        ? wc_get_page_permalink( 'shop' )
        : home_url( '/shop/' );

    return add_query_arg( 'on_sale', '1', $shop_url );
}

/**
 * True only for the dedicated promotions view in the WooCommerce shop.
 */
function cvl_is_promotion_catalog_request(): bool {
    if ( ! function_exists( 'is_shop' ) || ! is_shop() ) {
        return false;
    }

    if ( ! isset( $_GET['on_sale'] ) ) {
        return false;
    }

    return '1' === sanitize_text_field( wp_unslash( $_GET['on_sale'] ) );
}

/**
 * Published parent/simple product IDs that are currently on sale.
 */
function cvl_promotion_product_ids(): array {
    static $ids = null;

    if ( null !== $ids ) {
        return $ids;
    }

    if ( ! function_exists( 'wc_get_product_ids_on_sale' ) ) {
        $ids = array();
        return $ids;
    }

    $sale_ids = array_values(
        array_unique(
            array_filter(
                array_map( 'absint', wc_get_product_ids_on_sale() )
            )
        )
    );

    if ( ! $sale_ids ) {
        $ids = array();
        return $ids;
    }

    // wc_get_product_ids_on_sale() can include variation IDs. The catalogue
    // only renders published product posts; parents are already included by
    // WooCommerce when an active variation is on sale.
    $ids = get_posts(
        array(
            'post_type'              => 'product',
            'post_status'            => 'publish',
            'post__in'               => $sale_ids,
            'posts_per_page'         => -1,
            'fields'                 => 'ids',
            'orderby'                => 'none',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        )
    );

    $ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );

    return $ids;
}

/**
 * Brands used by promotion products after the current category/price filters.
 * The selected brand itself is intentionally ignored here so users can switch
 * or combine brands without losing the available facet list.
 */
function cvl_promotion_brand_facets(
    array $sale_product_ids,
    ?WP_Term $category = null,
    float $min_price = 0.0,
    float $max_price = 0.0
): array {
    if ( ! $sale_product_ids || ! taxonomy_exists( 'product_brand' ) ) {
        return array();
    }

    $args = array(
        'post_type'              => 'product',
        'post_status'            => 'publish',
        'post__in'               => $sale_product_ids,
        'posts_per_page'         => -1,
        'fields'                 => 'ids',
        'orderby'                => 'none',
        'no_found_rows'          => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
    );

    $tax_query = array();

    if ( $category instanceof WP_Term ) {
        $tax_query[] = array(
            'taxonomy'         => 'product_cat',
            'field'            => 'term_id',
            'terms'            => array( (int) $category->term_id ),
            'include_children' => true,
            'operator'         => 'IN',
        );
    }

    if ( $tax_query ) {
        $args['tax_query'] = $tax_query;
    }

    if ( $min_price > 0 || $max_price > 0 ) {
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

        $args['meta_query'] = array( $price_rule );
    }

    $ids = get_posts( $args );

    if ( ! $ids ) {
        return array();
    }

    $terms = wp_get_object_terms(
        $ids,
        'product_brand',
        array(
            'orderby' => 'name',
            'order'   => 'ASC',
        )
    );

    if ( is_wp_error( $terms ) || ! $terms ) {
        return array();
    }

    return array_values(
        array_filter(
            $terms,
            static fn( $term ): bool => $term instanceof WP_Term
        )
    );
}

/**
 * Categories used by current promotion products.
 */
function cvl_promotion_category_facets( array $sale_product_ids ): array {
    if ( ! $sale_product_ids || ! taxonomy_exists( 'product_cat' ) ) {
        return array();
    }

    $terms = wp_get_object_terms(
        $sale_product_ids,
        'product_cat',
        array(
            'orderby' => 'name',
            'order'   => 'ASC',
        )
    );

    if ( is_wp_error( $terms ) || ! $terms ) {
        return array();
    }

    $uncategorized = get_term_by( 'slug', 'uncategorized', 'product_cat' );
    $uncategorized_id = $uncategorized instanceof WP_Term
        ? (int) $uncategorized->term_id
        : 0;

    return array_values(
        array_filter(
            $terms,
            static fn( $term ): bool =>
                $term instanceof WP_Term
                && ( ! $uncategorized_id || (int) $term->term_id !== $uncategorized_id )
        )
    );
}

/**
 * Hierarchical tree: only root categories are visible initially.
 * Missing ancestors are injected so every used sale category remains reachable.
 */
function cvl_promotion_category_tree( array $terms ): array {
    $nodes = array();

    foreach ( $terms as $term ) {
        if ( ! $term instanceof WP_Term ) {
            continue;
        }

        $nodes[ (int) $term->term_id ] = array(
            'term_id'  => (int) $term->term_id,
            'slug'     => $term->slug,
            'name'     => $term->name,
            'parent'   => (int) $term->parent,
            'children' => array(),
        );
    }

    if ( ! $nodes ) {
        return array();
    }

    $seed_ids = array_keys( $nodes );

    foreach ( $seed_ids as $seed_id ) {
        $parent_id = absint( $nodes[ $seed_id ]['parent'] ?? 0 );

        while ( $parent_id > 0 ) {
            if ( ! isset( $nodes[ $parent_id ] ) ) {
                $parent_term = get_term( $parent_id, 'product_cat' );

                if ( ! $parent_term instanceof WP_Term ) {
                    break;
                }

                $nodes[ $parent_id ] = array(
                    'term_id'  => (int) $parent_term->term_id,
                    'slug'     => $parent_term->slug,
                    'name'     => $parent_term->name,
                    'parent'   => (int) $parent_term->parent,
                    'children' => array(),
                );
            }

            $parent_id = absint( $nodes[ $parent_id ]['parent'] ?? 0 );
        }
    }

    uasort(
        $nodes,
        static fn( array $a, array $b ): int => strnatcasecmp( $a['name'], $b['name'] )
    );

    $roots = array();

    foreach ( array_keys( $nodes ) as $term_id ) {
        $parent_id = absint( $nodes[ $term_id ]['parent'] ?? 0 );

        if ( $parent_id > 0 && isset( $nodes[ $parent_id ] ) ) {
            $nodes[ $parent_id ]['children'][] = &$nodes[ $term_id ];
        } else {
            $roots[] = &$nodes[ $term_id ];
        }
    }

    $sort_children = static function ( array &$items ) use ( &$sort_children ): void {
        usort(
            $items,
            static fn( array $a, array $b ): int => strnatcasecmp( $a['name'], $b['name'] )
        );

        foreach ( $items as &$item ) {
            if ( ! empty( $item['children'] ) ) {
                $sort_children( $item['children'] );
            }
        }
        unset( $item );
    };

    $sort_children( $roots );

    return $roots;
}

/**
 * Min/max active catalogue price for promotion IDs and an optional category.
 */
function cvl_promotion_price_bounds( array $sale_product_ids, ?WP_Term $category = null ): array {
    if ( ! $sale_product_ids ) {
        return array( 0.0, 0.0 );
    }

    $args = array(
        'post_type'              => 'product',
        'post_status'            => 'publish',
        'post__in'               => $sale_product_ids,
        'posts_per_page'         => -1,
        'fields'                 => 'ids',
        'orderby'                => 'none',
        'no_found_rows'          => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
    );

    if ( $category instanceof WP_Term ) {
        $args['tax_query'] = array(
            array(
                'taxonomy'         => 'product_cat',
                'field'            => 'term_id',
                'terms'            => array( (int) $category->term_id ),
                'include_children' => true,
                'operator'         => 'IN',
            ),
        );
    }

    $ids = get_posts( $args );

    if ( ! $ids ) {
        return array( 0.0, 0.0 );
    }

    global $wpdb;

    $ids          = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
    $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
    $lookup_table = $wpdb->prefix . 'wc_product_meta_lookup';

    $sql = "
        SELECT
            MIN(NULLIF(min_price, 0)) AS min_price,
            MAX(max_price) AS max_price
        FROM {$lookup_table}
        WHERE product_id IN ({$placeholders})
    ";

    $row = $wpdb->get_row( $wpdb->prepare( $sql, $ids ), ARRAY_A );

    return array(
        max( 0.0, (float) ( $row['min_price'] ?? 0 ) ),
        max( 0.0, (float) ( $row['max_price'] ?? 0 ) ),
    );
}

/**
 * Search-style catalogue for products currently on promotion.
 */
function cvl_promotion_catalog_layout(): void {
    $sale_product_ids = cvl_promotion_product_ids();

    $selected_category = null;
    $selected_cat_slug = isset( $_GET['categoria'] )
        ? sanitize_title( wp_unslash( $_GET['categoria'] ) )
        : '';

    if ( $selected_cat_slug ) {
        $candidate = get_term_by( 'slug', $selected_cat_slug, 'product_cat' );

        if ( $candidate instanceof WP_Term ) {
            $selected_category = $candidate;
        }
    }

    $selected_brands = isset( $_GET['marca'] )
        ? (array) wc_clean( wp_unslash( $_GET['marca'] ) )
        : array();
    $selected_brands = array_values(
        array_unique(
            array_filter(
                array_map( 'sanitize_title', $selected_brands )
            )
        )
    );

    $min_price = isset( $_GET['min_price'] )
        ? max( 0, (float) wc_format_decimal( wp_unslash( $_GET['min_price'] ) ) )
        : 0.0;
    $max_price = isset( $_GET['max_price'] )
        ? max( 0, (float) wc_format_decimal( wp_unslash( $_GET['max_price'] ) ) )
        : 0.0;

    $brand_terms = cvl_promotion_brand_facets(
        $sale_product_ids,
        $selected_category,
        $min_price,
        $max_price
    );
    $category_terms = cvl_promotion_category_facets( $sale_product_ids );
    $category_tree  = cvl_promotion_category_tree( $category_terms );

    list( $price_floor_raw, $price_ceil_raw ) = cvl_promotion_price_bounds(
        $sale_product_ids,
        $selected_category
    );

    $shop_url = function_exists( 'wc_get_page_permalink' )
        ? wc_get_page_permalink( 'shop' )
        : home_url( '/shop/' );

    $base_url = cvl_promotions_url();

    $filter_url = static function ( array $overrides = array() ) use (
        $base_url,
        $selected_category,
        $selected_brands,
        $min_price,
        $max_price
    ): string {
        $state = array(
            'on_sale'   => '1',
            'marca'     => $selected_brands,
            'categoria' => $selected_category instanceof WP_Term ? $selected_category->slug : '',
            'min_price' => $min_price > 0 ? $min_price : '',
            'max_price' => $max_price > 0 ? $max_price : '',
        );

        foreach ( $overrides as $key => $value ) {
            $state[ $key ] = $value;
        }

        unset( $state['cvl_page'] );

        return add_query_arg(
            array_filter(
                $state,
                static fn( $value ): bool => '' !== $value && null !== $value
            ),
            $shop_url
        );
    };

    $catalog_page = isset( $_GET['cvl_page'] )
        ? max( 1, absint( wp_unslash( $_GET['cvl_page'] ) ) )
        : 1;

    $tax_query = array();

    if ( $selected_category instanceof WP_Term ) {
        $tax_query[] = array(
            'taxonomy'         => 'product_cat',
            'field'            => 'term_id',
            'terms'            => array( (int) $selected_category->term_id ),
            'include_children' => true,
            'operator'         => 'IN',
        );
    }

    if ( $selected_brands && taxonomy_exists( 'product_brand' ) ) {
        $tax_query[] = array(
            'taxonomy' => 'product_brand',
            'field'    => 'slug',
            'terms'    => $selected_brands,
            'operator' => 'IN',
        );
    }

    if ( function_exists( 'wc_get_product_visibility_term_ids' ) ) {
        $visibility_ids = wc_get_product_visibility_term_ids();
        $excluded       = array();

        if ( ! empty( $visibility_ids['exclude-from-catalog'] ) ) {
            $excluded[] = (int) $visibility_ids['exclude-from-catalog'];
        }

        if (
            'yes' === get_option( 'woocommerce_hide_out_of_stock_items' )
            && ! empty( $visibility_ids['outofstock'] )
        ) {
            $excluded[] = (int) $visibility_ids['outofstock'];
        }

        if ( $excluded ) {
            $tax_query[] = array(
                'taxonomy' => 'product_visibility',
                'field'    => 'term_id',
                'terms'    => $excluded,
                'operator' => 'NOT IN',
            );
        }
    }

    $meta_query = array();

    if ( $min_price > 0 || $max_price > 0 ) {
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

        $meta_query[] = $price_rule;
    }

    $catalog_query = new WP_Query(
        array(
            'post_type'           => 'product',
            'post_status'         => 'publish',
            'post__in'            => $sale_product_ids ? $sale_product_ids : array( 0 ),
            'posts_per_page'      => 24,
            'paged'               => $catalog_page,
            'ignore_sticky_posts' => true,
            'no_found_rows'       => false,
            'tax_query'           => $tax_query,
            'meta_query'          => $meta_query,
            'orderby'             => array(
                'menu_order' => 'ASC',
                'date'       => 'DESC',
            ),
        )
    );

    $found = (int) $catalog_query->found_posts;
    ?>
    <div class="cvl-shell cvl-content cvl-search-page cvl-promotion-catalog-page">
        <header class="cvl-search-heading cvl-search-heading-compact">
            <span><?php esc_html_e( 'PROMOÇÕES', 'chavevertical-lite' ); ?></span>
            <h1><?php esc_html_e( 'Produtos em promoção', 'chavevertical-lite' ); ?></h1>
            <p class="cvl-search-count">
                <?php
                printf(
                    esc_html( _n( '%s produto em promoção', '%s produtos em promoção', $found, 'chavevertical-lite' ) ),
                    esc_html( number_format_i18n( $found ) )
                );
                ?>
            </p>
        </header>

        <div class="cvl-search-layout">
            <aside class="cvl-search-filters" aria-label="<?php esc_attr_e( 'Filtros das promoções', 'chavevertical-lite' ); ?>">
                <div class="cvl-search-filters-head">
                    <strong><?php esc_html_e( 'FILTRAR PRODUTOS', 'chavevertical-lite' ); ?></strong>
                    <?php if ( $selected_brands || $selected_category || $min_price > 0 || $max_price > 0 ) : ?>
                        <a href="<?php echo esc_url( $base_url ); ?>"><?php esc_html_e( 'Limpar', 'chavevertical-lite' ); ?></a>
                    <?php endif; ?>
                </div>

                <?php if ( $brand_terms ) : ?>
                    <section class="cvl-search-filter-group cvl-promotion-brand-filter" aria-label="<?php esc_attr_e( 'Filtrar por marcas', 'chavevertical-lite' ); ?>">
                        <h2><?php esc_html_e( 'MARCAS', 'chavevertical-lite' ); ?></h2>

                        <form class="cvl-final-brand-filter-form" method="get" action="<?php echo esc_url( $shop_url ); ?>" data-cvl-brand-auto-filter>
                            <input type="hidden" name="on_sale" value="1">
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
                                <?php foreach ( $brand_terms as $brand_term ) : ?>
                                    <?php
                                    if ( ! $brand_term instanceof WP_Term ) {
                                        continue;
                                    }

                                    $brand_slug = sanitize_title( $brand_term->slug );
                                    $active     = in_array( $brand_slug, $selected_brands, true );
                                    $input_id   = 'cvl-promotion-brand-' . sanitize_html_class( $brand_slug );
                                    ?>
                                    <label class="cvl-final-brand-chip<?php echo $active ? ' is-active' : ''; ?>" for="<?php echo esc_attr( $input_id ); ?>">
                                        <input
                                            id="<?php echo esc_attr( $input_id ); ?>"
                                            type="checkbox"
                                            name="marca[]"
                                            value="<?php echo esc_attr( $brand_slug ); ?>"
                                            <?php checked( $active ); ?>
                                        >
                                        <span><?php echo esc_html( $brand_term->name ); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </form>
                    </section>
                <?php endif; ?>

                <?php if ( $category_tree ) : ?>
                    <?php
                    $selected_category_id = $selected_category instanceof WP_Term
                        ? (int) $selected_category->term_id
                        : 0;
                    $selected_ancestor_ids = $selected_category_id
                        ? array_map( 'absint', get_ancestors( $selected_category_id, 'product_cat', 'taxonomy' ) )
                        : array();

                    $render_promotion_category_nodes = static function ( array $items, int $depth = 0 ) use (
                        &$render_promotion_category_nodes,
                        $filter_url,
                        $selected_category_id,
                        $selected_ancestor_ids
                    ): void {
                        foreach ( $items as $item ) {
                            $term_id = absint( $item['term_id'] ?? 0 );
                            $slug    = sanitize_title( (string) ( $item['slug'] ?? '' ) );
                            $name    = (string) ( $item['name'] ?? '' );

                            if ( ! $term_id || ! $slug || '' === $name ) {
                                continue;
                            }

                            $children = is_array( $item['children'] ?? null )
                                ? $item['children']
                                : array();
                            $active   = $selected_category_id === $term_id;
                            $expanded = $active || in_array( $term_id, $selected_ancestor_ids, true );
                            $panel_id = 'cvl-promotion-category-children-' . $term_id;
                            ?>
                            <div class="cvl-category-filter-item<?php echo $expanded ? ' is-expanded' : ''; ?>" style="--cvl-cat-depth:<?php echo esc_attr( $depth ); ?>">
                                <div class="cvl-category-filter-row">
                                    <a
                                        class="cvl-category-filter-link<?php echo $active ? ' is-active' : ''; ?>"
                                        href="<?php echo esc_url( $filter_url( array( 'categoria' => $active ? '' : $slug ) ) ); ?>"
                                    >
                                        <span><?php echo esc_html( $name ); ?></span>
                                    </a>

                                    <?php if ( $children ) : ?>
                                        <button
                                            type="button"
                                            class="cvl-category-filter-toggle"
                                            data-cvl-category-tree-toggle
                                            aria-expanded="<?php echo $expanded ? 'true' : 'false'; ?>"
                                            aria-controls="<?php echo esc_attr( $panel_id ); ?>"
                                            aria-label="<?php echo esc_attr( sprintf( __( 'Expandir %s', 'chavevertical-lite' ), $name ) ); ?>"
                                        ><span aria-hidden="true">›</span></button>
                                    <?php endif; ?>
                                </div>

                                <?php if ( $children ) : ?>
                                    <div
                                        id="<?php echo esc_attr( $panel_id ); ?>"
                                        class="cvl-category-filter-children"
                                        data-cvl-category-tree-children
                                        <?php echo $expanded ? '' : 'hidden'; ?>
                                    >
                                        <?php $render_promotion_category_nodes( $children, $depth + 1 ); ?>
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
                            <?php $render_promotion_category_nodes( $category_tree ); ?>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if ( $price_ceil_raw > 0 ) : ?>
                    <section class="cvl-search-filter-group">
                        <h2><?php esc_html_e( 'PREÇO', 'chavevertical-lite' ); ?></h2>
                        <form class="cvl-search-price-filter" method="get" action="<?php echo esc_url( $shop_url ); ?>">
                            <input type="hidden" name="on_sale" value="1">
                            <?php if ( $selected_category instanceof WP_Term ) : ?>
                                <input type="hidden" name="categoria" value="<?php echo esc_attr( $selected_category->slug ); ?>">
                            <?php endif; ?>
                            <?php foreach ( $selected_brands as $selected_brand_slug ) : ?>
                                <input type="hidden" name="marca[]" value="<?php echo esc_attr( $selected_brand_slug ); ?>">
                            <?php endforeach; ?>

                            <div class="cvl-search-price-inputs">
                                <label>
                                    <span><?php esc_html_e( 'Mín.', 'chavevertical-lite' ); ?></span>
                                    <input
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        name="min_price"
                                        value="<?php echo $min_price > 0 ? esc_attr( wc_format_decimal( $min_price, 2 ) ) : ''; ?>"
                                        placeholder="<?php echo esc_attr( number_format_i18n( floor( $price_floor_raw ), 0 ) ); ?>"
                                    >
                                </label>
                                <label>
                                    <span><?php esc_html_e( 'Máx.', 'chavevertical-lite' ); ?></span>
                                    <input
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        name="max_price"
                                        value="<?php echo $max_price > 0 ? esc_attr( wc_format_decimal( $max_price, 2 ) ) : ''; ?>"
                                        placeholder="<?php echo esc_attr( number_format_i18n( ceil( $price_ceil_raw ), 0 ) ); ?>"
                                    >
                                </label>
                            </div>

                            <div class="cvl-search-price-range">
                                <?php echo wp_kses_post( wc_price( floor( $price_floor_raw ) ) ); ?>
                                –
                                <?php echo wp_kses_post( wc_price( ceil( $price_ceil_raw ) ) ); ?>
                            </div>

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
                    $next_url  = $catalog_page < $max_pages
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
                            <span
                                class="cvl-search-load-more-status screen-reader-text"
                                data-cvl-search-load-more-status
                                aria-live="polite"
                            ></span>
                        </div>
                    <?php endif; ?>
                <?php else : ?>
                    <div class="cvl-empty-state">
                        <h2><?php esc_html_e( 'Não existem promoções com estes filtros.', 'chavevertical-lite' ); ?></h2>
                        <p><?php esc_html_e( 'Experimente remover ou alterar os filtros selecionados.', 'chavevertical-lite' ); ?></p>
                        <a class="cvl-button cvl-button-primary" href="<?php echo esc_url( $base_url ); ?>">
                            <?php esc_html_e( 'LIMPAR FILTROS', 'chavevertical-lite' ); ?>
                        </a>
                    </div>
                <?php endif; ?>

                <?php wc_reset_loop(); ?>
            </section>
        </div>
    </div>
    <?php
}
