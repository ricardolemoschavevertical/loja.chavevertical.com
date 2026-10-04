<?php
/**
 * Product brand archive catalogue.
 *
 * Reuses the search-results visual language: filters on the left, product grid
 * on the right, and progressive "load more" pagination.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Category facets available inside one brand archive.
 */
function cvl_brand_archive_category_facets( int $brand_term_id, float $min_price = 0.0, float $max_price = 0.0 ): array {
    if ( $brand_term_id <= 0 || ! taxonomy_exists( 'product_cat' ) ) {
        return array();
    }

    global $wpdb;

    $lookup_table = $wpdb->prefix . 'wc_product_meta_lookup';
    $params       = array( $brand_term_id );

    $price_sql = '';

    if ( $min_price > 0 ) {
        $price_sql .= ' AND lookup.max_price >= %f';
        $params[] = $min_price;
    }

    if ( $max_price > 0 ) {
        $price_sql .= ' AND lookup.min_price <= %f';
        $params[] = $max_price;
    }

    $sql = "
        SELECT
            terms.term_id,
            terms.slug,
            terms.name,
            COUNT(DISTINCT products.ID) AS product_count
        FROM {$wpdb->posts} products
        INNER JOIN {$wpdb->term_relationships} brand_rel
            ON brand_rel.object_id = products.ID
        INNER JOIN {$wpdb->term_taxonomy} brand_tax
            ON brand_tax.term_taxonomy_id = brand_rel.term_taxonomy_id
            AND brand_tax.taxonomy = 'product_brand'
            AND brand_tax.term_id = %d
        INNER JOIN {$wpdb->term_relationships} category_rel
            ON category_rel.object_id = products.ID
        INNER JOIN {$wpdb->term_taxonomy} category_tax
            ON category_tax.term_taxonomy_id = category_rel.term_taxonomy_id
            AND category_tax.taxonomy = 'product_cat'
        INNER JOIN {$wpdb->terms} terms
            ON terms.term_id = category_tax.term_id
        INNER JOIN {$lookup_table} lookup
            ON lookup.product_id = products.ID
        WHERE products.post_type = 'product'
          AND products.post_status = 'publish'
          {$price_sql}
        GROUP BY terms.term_id, terms.slug, terms.name
        ORDER BY product_count DESC, terms.name ASC
    ";

    $rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );

    return is_array( $rows ) ? $rows : array();
}

/**
 * Price bounds for the current brand and optional selected category.
 */
function cvl_brand_archive_price_bounds( int $brand_term_id, ?WP_Term $category = null ): array {
    if ( $brand_term_id <= 0 ) {
        return array( 0.0, 0.0 );
    }

    global $wpdb;

    $lookup_table = $wpdb->prefix . 'wc_product_meta_lookup';
    $params       = array( $brand_term_id );
    $category_sql = '';

    if ( $category instanceof WP_Term ) {
        $category_sql = "
            INNER JOIN {$wpdb->term_relationships} category_rel
                ON category_rel.object_id = products.ID
            INNER JOIN {$wpdb->term_taxonomy} category_tax
                ON category_tax.term_taxonomy_id = category_rel.term_taxonomy_id
                AND category_tax.taxonomy = 'product_cat'
                AND category_tax.term_id = %d
        ";
        $params[] = (int) $category->term_id;
    }

    $sql = "
        SELECT
            MIN(NULLIF(lookup.min_price, 0)) AS min_price,
            MAX(lookup.max_price) AS max_price
        FROM {$wpdb->posts} products
        INNER JOIN {$wpdb->term_relationships} brand_rel
            ON brand_rel.object_id = products.ID
        INNER JOIN {$wpdb->term_taxonomy} brand_tax
            ON brand_tax.term_taxonomy_id = brand_rel.term_taxonomy_id
            AND brand_tax.taxonomy = 'product_brand'
            AND brand_tax.term_id = %d
        {$category_sql}
        INNER JOIN {$lookup_table} lookup
            ON lookup.product_id = products.ID
        WHERE products.post_type = 'product'
          AND products.post_status = 'publish'
    ";

    $row = $wpdb->get_row( $wpdb->prepare( $sql, $params ), ARRAY_A );

    return array(
        max( 0.0, (float) ( $row['min_price'] ?? 0 ) ),
        max( 0.0, (float) ( $row['max_price'] ?? 0 ) ),
    );
}

/**
 * Search-style brand archive.
 */
function cvl_product_brand_search_layout(): void {
    $brand = get_queried_object();

    if ( ! $brand instanceof WP_Term || 'product_brand' !== $brand->taxonomy ) {
        return;
    }

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

    $min_price = isset( $_GET['min_price'] )
        ? max( 0, (float) wc_format_decimal( wp_unslash( $_GET['min_price'] ) ) )
        : 0.0;
    $max_price = isset( $_GET['max_price'] )
        ? max( 0, (float) wc_format_decimal( wp_unslash( $_GET['max_price'] ) ) )
        : 0.0;

    $category_facets = cvl_brand_archive_category_facets(
        (int) $brand->term_id,
        $min_price,
        $max_price
    );

    list( $price_floor_raw, $price_ceil_raw ) = cvl_brand_archive_price_bounds(
        (int) $brand->term_id,
        $selected_category
    );

    $base_url = get_term_link( $brand );

    if ( is_wp_error( $base_url ) ) {
        $base_url = home_url( '/' );
    }

    $filter_url = static function ( array $overrides = array() ) use (
        $base_url,
        $selected_category,
        $min_price,
        $max_price
    ): string {
        $state = array(
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
            $base_url
        );
    };

    $catalog_page = isset( $_GET['cvl_page'] )
        ? max( 1, absint( wp_unslash( $_GET['cvl_page'] ) ) )
        : 1;

    $tax_query = array(
        array(
            'taxonomy' => 'product_brand',
            'field'    => 'term_id',
            'terms'    => array( (int) $brand->term_id ),
            'operator' => 'IN',
        ),
    );

    if ( $selected_category instanceof WP_Term ) {
        $tax_query[] = array(
            'taxonomy'         => 'product_cat',
            'field'            => 'term_id',
            'terms'            => array( (int) $selected_category->term_id ),
            'include_children' => true,
            'operator'         => 'IN',
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
    <div class="cvl-shell cvl-content cvl-search-page cvl-brand-catalog-page">
        <header class="cvl-search-heading cvl-search-heading-compact">
            <span><?php esc_html_e( 'MARCA', 'chavevertical-lite' ); ?></span>
            <h1><?php echo esc_html( $brand->name ); ?></h1>
            <p class="cvl-search-count">
                <?php
                printf(
                    esc_html( _n( '%s produto encontrado', '%s produtos encontrados', $found, 'chavevertical-lite' ) ),
                    esc_html( number_format_i18n( $found ) )
                );
                ?>
            </p>
        </header>

        <div class="cvl-search-layout">
            <aside class="cvl-search-filters" aria-label="<?php esc_attr_e( 'Filtros da marca', 'chavevertical-lite' ); ?>">
                <div class="cvl-search-filters-head">
                    <strong><?php esc_html_e( 'FILTRAR PRODUTOS', 'chavevertical-lite' ); ?></strong>
                    <?php if ( $selected_category || $min_price > 0 || $max_price > 0 ) : ?>
                        <a href="<?php echo esc_url( $base_url ); ?>"><?php esc_html_e( 'Limpar', 'chavevertical-lite' ); ?></a>
                    <?php endif; ?>
                </div>

                <?php if ( $category_facets ) : ?>
                    <section class="cvl-search-filter-group">
                        <h2><?php esc_html_e( 'CATEGORIAS', 'chavevertical-lite' ); ?></h2>
                        <div class="cvl-search-filter-list">
                            <?php foreach ( $category_facets as $facet ) : ?>
                                <?php
                                $slug = sanitize_title( (string) ( $facet['slug'] ?? '' ) );

                                if ( ! $slug ) {
                                    continue;
                                }

                                $selected = $selected_category instanceof WP_Term
                                    && $slug === $selected_category->slug;
                                ?>
                                <a
                                    class="<?php echo $selected ? 'is-active' : ''; ?>"
                                    href="<?php echo esc_url( $filter_url( array( 'categoria' => $selected ? '' : $slug ) ) ); ?>"
                                >
                                    <span><?php echo esc_html( $facet['name'] ?? $slug ); ?></span>
                                    <small><?php echo esc_html( number_format_i18n( (int) ( $facet['product_count'] ?? 0 ) ) ); ?></small>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if ( $price_ceil_raw > 0 ) : ?>
                    <section class="cvl-search-filter-group">
                        <h2><?php esc_html_e( 'PREÇO', 'chavevertical-lite' ); ?></h2>
                        <form class="cvl-search-price-filter" method="get" action="<?php echo esc_url( $base_url ); ?>">
                            <?php if ( $selected_category instanceof WP_Term ) : ?>
                                <input type="hidden" name="categoria" value="<?php echo esc_attr( $selected_category->slug ); ?>">
                            <?php endif; ?>

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
                        <h2><?php esc_html_e( 'Não encontrámos produtos.', 'chavevertical-lite' ); ?></h2>
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
