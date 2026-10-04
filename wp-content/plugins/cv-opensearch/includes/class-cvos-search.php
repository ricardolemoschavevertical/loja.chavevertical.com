<?php
defined( 'ABSPATH' ) || exit;

final class CVOS_Search {
    private CVOS_Settings $settings;
    private CVOS_Client $client;
    private bool $assets_localized = false;

    public function __construct( CVOS_Settings $settings, CVOS_Client $client ) {
        $this->settings = $settings;
        $this->client   = $client;

        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
        add_action( 'pre_get_posts', array( $this, 'intercept_native_search' ), 20 );
        add_filter( 'posts_search', array( $this, 'remove_mysql_search_clause' ), 20, 2 );
        add_filter( 'get_search_form', array( $this, 'replace_search_form' ), 20 );
        add_shortcode( 'cv_opensearch', array( $this, 'shortcode' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
    }

    public function register_assets(): void {
        wp_register_style(
            'cv-opensearch',
            CVOS_URL . 'assets/search.css',
            array(),
            CVOS_VERSION
        );
        wp_register_script(
            'cv-opensearch',
            CVOS_URL . 'assets/search.js',
            array(),
            CVOS_VERSION,
            true
        );

        /*
         * The Chave Vertical theme has its own hard-coded header search form and
         * therefore does not pass through get_search_form(). Enqueue the instant
         * search client globally when the engine is active so that the existing
         * header field can be enhanced without replacing its design.
         */
        if ( $this->settings->is_yes( 'enabled' ) && $this->settings->is_yes( 'enable_instant_search' ) ) {
            $this->enqueue_assets();
        }
    }

    private function enqueue_assets(): void {
        if (
            ! $this->settings->is_yes( 'enabled' )
            || ! $this->settings->is_yes( 'enable_instant_search' )
        ) {
            return;
        }

        wp_enqueue_style( 'cv-opensearch' );
        wp_enqueue_script( 'cv-opensearch' );

        if ( $this->assets_localized ) {
            return;
        }

        wp_localize_script(
            'cv-opensearch',
            'CVOpenSearch',
            array(
                'endpoint'  => esc_url_raw( rest_url( 'cv-opensearch/v1/suggest' ) ),
                'minChars'  => absint( $this->settings->get( 'min_chars', 2 ) ),
                'limit'     => absint( $this->settings->get( 'suggestions_limit', 10 ) ),
                'searchUrl' => home_url( '/' ),
            )
        );

        $this->assets_localized = true;
    }

    public function shortcode(): string {
        return $this->render_search_form( get_search_query() );
    }

    public function replace_search_form( string $form ): string {
        if ( is_admin() || ! $this->settings->is_yes( 'enabled' ) || ! $this->settings->is_yes( 'replace_native_search' ) ) {
            return $form;
        }

        return $this->render_search_form( get_search_query() );
    }

    private function render_search_form( string $value = '' ): string {
        $this->enqueue_assets();
        $id = wp_unique_id( 'cvos-search-' );

        ob_start();
        ?>
        <div class="cvos-search" data-cvos-search>
            <form class="cvos-search-form" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>" autocomplete="off">
                <label class="screen-reader-text" for="<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Pesquisar produtos', 'cv-opensearch' ); ?></label>
                <input id="<?php echo esc_attr( $id ); ?>" data-cvos-input type="search" name="s" value="<?php echo esc_attr( $value ); ?>" placeholder="<?php esc_attr_e( 'Pesquisar produtos, marcas ou referências…', 'cv-opensearch' ); ?>">
                <input type="hidden" name="post_type" value="product">
                <button type="submit" aria-label="<?php esc_attr_e( 'Pesquisar', 'cv-opensearch' ); ?>">⌕</button>
            </form>
            <div class="cvos-suggestions" data-cvos-results hidden></div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    public function register_rest_routes(): void {
        register_rest_route(
            'cv-opensearch/v1',
            '/health',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'rest_health' ),
                'permission_callback' => '__return_true',
            )
        );

        register_rest_route(
            'cv-opensearch/v1',
            '/suggest',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'rest_suggest' ),
                'permission_callback' => '__return_true',
                'args'                => array(
                    'q'     => array( 'sanitize_callback' => 'sanitize_text_field' ),
                    'limit' => array( 'sanitize_callback' => 'absint' ),
                ),
            )
        );

        register_rest_route(
            'cv-opensearch/v1',
            '/catalog/products',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'rest_catalog' ),
                'permission_callback' => '__return_true',
            )
        );
    }

    public function rest_health(): WP_REST_Response {
        if ( ! $this->settings->configured() ) {
            return new WP_REST_Response(
                array(
                    'ok'         => false,
                    'configured' => false,
                    'enabled'    => $this->settings->is_yes( 'enabled' ),
                ),
                503
            );
        }

        $health = $this->client->health();
        if ( is_wp_error( $health ) ) {
            return new WP_REST_Response(
                array(
                    'ok'         => false,
                    'configured' => true,
                    'enabled'    => $this->settings->is_yes( 'enabled' ),
                    'error'      => $health->get_error_message(),
                ),
                503
            );
        }

        return new WP_REST_Response(
            array(
                'ok'          => true,
                'configured'  => true,
                'enabled'     => $this->settings->is_yes( 'enabled' ),
                'cluster'     => sanitize_text_field( (string) ( $health['cluster_name'] ?? '' ) ),
                'status'      => sanitize_key( (string) ( $health['status'] ?? '' ) ),
                'index'       => $this->settings->index_name(),
                'plugin'      => CVOS_VERSION,
            ),
            200
        );
    }

    public function rest_suggest( WP_REST_Request $request ) {
        if ( ! $this->settings->is_yes( 'enabled' ) ) {
            return new WP_Error( 'cvos_disabled', 'Pesquisa OpenSearch desativada.', array( 'status' => 503 ) );
        }

        $query = trim( sanitize_text_field( (string) $request->get_param( 'q' ) ) );
        $min   = max( 1, absint( $this->settings->get( 'min_chars', 2 ) ) );

        if ( $this->text_length( $query ) < $min ) {
            return rest_ensure_response(
                array(
                    'ok'    => true,
                    'items' => array(),
                    'total' => 0,
                )
            );
        }

        $limit  = min( 30, max( 3, absint( $request->get_param( 'limit' ) ?: $this->settings->get( 'suggestions_limit', 10 ) ) ) );
        $result = $this->search(
            array(
                'q'        => $query,
                'page'     => 1,
                'per_page' => $limit,
                'facets'   => false,
            )
        );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return rest_ensure_response(
            array(
                'ok'    => true,
                'items' => $result['items'],
                'total' => $result['total'],
            )
        );
    }

    public function rest_catalog( WP_REST_Request $request ) {
        if ( ! $this->settings->is_yes( 'enabled' ) || ! $this->settings->is_yes( 'enable_astro_api' ) ) {
            return new WP_Error( 'cvos_astro_disabled', 'API OpenSearch para Astro desativada.', array( 'status' => 503 ) );
        }

        $params = array(
            'q'         => sanitize_text_field( (string) ( $request->get_param( 's' ) ?: $request->get_param( 'q' ) ) ),
            'page'      => absint( $request->get_param( 'page' ) ?: 1 ),
            'per_page'  => absint( $request->get_param( 'per_page' ) ?: $this->settings->get( 'results_per_page', 24 ) ),
            'sort'      => sanitize_key( (string) ( $request->get_param( 'sort' ) ?: 'relevance' ) ),
            'brand'     => sanitize_text_field( (string) ( $request->get_param( 'marca' ) ?: $request->get_param( 'brand' ) ) ),
            'category'  => sanitize_text_field( (string) ( $request->get_param( 'categoria' ) ?: $request->get_param( 'category' ) ) ),
            'stock'     => sanitize_key( (string) $request->get_param( 'stock' ) ),
            'min_price' => (float) $request->get_param( 'min_price' ),
            'max_price' => (float) $request->get_param( 'max_price' ),
            'facets'    => true,
        );

        $result = $this->search( $params );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return rest_ensure_response(
            array(
                'ok'       => true,
                'source'   => 'opensearch',
                'total'    => $result['total'],
                'page'     => $result['page'],
                'per_page' => $result['per_page'],
                'items'    => $result['items'],
                'facets'   => $result['facets'],
            )
        );
    }

    public function search( array $args ) {
        if ( ! $this->settings->configured() ) {
            return new WP_Error( 'cvos_not_configured', 'OpenSearch não configurado.' );
        }

        $query    = trim( sanitize_text_field( (string) ( $args['q'] ?? '' ) ) );
        $page     = max( 1, absint( $args['page'] ?? 1 ) );
        $per_page = min( 100, max( 1, absint( $args['per_page'] ?? $this->settings->get( 'results_per_page', 24 ) ) ) );
        $filters  = array(
            array( 'term' => array( 'status' => 'publish' ) ),
        );
        $must_not = array();

        if ( $this->settings->is_yes( 'exclude_out_of_stock' ) ) {
            $must_not[] = array( 'term' => array( 'stock_status' => 'outofstock' ) );
        }

        $brands = $this->csv( $args['brand'] ?? '' );
        if ( $brands ) {
            $filters[] = array( 'terms' => array( 'brand_slugs' => $brands ) );
        }

        $category = trim( (string) ( $args['category'] ?? '' ) );
        if ( '' !== $category ) {
            if ( ctype_digit( $category ) ) {
                $filters[] = array( 'term' => array( 'category_ids' => (int) $category ) );
            } else {
                $filters[] = array( 'term' => array( 'category_slugs' => sanitize_title( $category ) ) );
            }
        }

        $stock = sanitize_key( (string) ( $args['stock'] ?? '' ) );
        if ( 'outofstock' === $stock ) {
            $filters[] = array( 'term' => array( 'stock_status' => 'outofstock' ) );
        } elseif ( 'available' === $stock ) {
            $must_not[] = array( 'term' => array( 'stock_status' => 'outofstock' ) );
        }

        $min_price = (float) ( $args['min_price'] ?? 0 );
        $max_price = (float) ( $args['max_price'] ?? 0 );
        if ( $min_price > 0 || $max_price > 0 ) {
            $range = array();
            if ( $min_price > 0 ) {
                $range['gte'] = $min_price;
            }
            if ( $max_price > 0 ) {
                $range['lte'] = $max_price;
            }
            $filters[] = array( 'range' => array( 'price' => $range ) );
        }

        $this->add_static_filters( $filters, $must_not );

        $bool = array( 'filter' => $filters );
        if ( $must_not ) {
            $bool['must_not'] = $must_not;
        }

        if ( '' !== $query ) {
            $bool['must'] = array( $this->text_query( $query ) );
        }

        $body = array(
            'from'             => ( $page - 1 ) * $per_page,
            'size'             => $per_page,
            'track_total_hits' => true,
            'query'            => array(
                'bool' => $bool,
            ),
            'sort'             => $this->sort( sanitize_key( (string) ( $args['sort'] ?? 'relevance' ) ), '' !== $query ),
        );

        if ( ! empty( $args['facets'] ) ) {
            $body['aggs'] = array(
                'brands' => array(
                    'terms' => array(
                        'field' => 'brand_facets',
                        'size'  => 250,
                    ),
                ),
                'price' => array(
                    'stats' => array( 'field' => 'price' ),
                ),
            );
        }

        $response = $this->client->search( $body );
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $hits  = $response['hits']['hits'] ?? array();
        $total = (int) ( $response['hits']['total']['value'] ?? 0 );
        $items = array();

        foreach ( $hits as $hit ) {
            if ( isset( $hit['_source'] ) && is_array( $hit['_source'] ) ) {
                $items[] = $hit['_source'];
            }
        }

        $facets = array(
            'brands' => array(),
            'price'  => null,
        );

        foreach ( $response['aggregations']['brands']['buckets'] ?? array() as $bucket ) {
            $parts = explode( '|', (string) ( $bucket['key'] ?? '' ), 2 );
            $slug  = sanitize_title( $parts[0] ?? '' );
            if ( $slug ) {
                $facets['brands'][] = array(
                    'slug'  => $slug,
                    'name'  => sanitize_text_field( $parts[1] ?? $parts[0] ),
                    'count' => (int) ( $bucket['doc_count'] ?? 0 ),
                );
            }
        }

        if ( isset( $response['aggregations']['price'] ) ) {
            $stats = $response['aggregations']['price'];
            $facets['price'] = array(
                'min' => isset( $stats['min'] ) && is_finite( (float) $stats['min'] ) ? (float) $stats['min'] : 0,
                'max' => isset( $stats['max'] ) && is_finite( (float) $stats['max'] ) ? (float) $stats['max'] : 0,
            );
        }

        if ( '' !== $query ) {
            $this->record_analytics( $query, $total );
        }

        return array(
            'items'    => $items,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $per_page,
            'facets'   => $facets,
        );
    }

    private function text_query( string $query ): array {
        $fields = array(
            'name^' . (float) $this->settings->get( 'weight_name', 14 ),
        );

        if ( $this->settings->is_yes( 'search_in_sku' ) ) {
            $fields[] = 'sku.text^' . (float) $this->settings->get( 'weight_sku', 16 );
            $fields[] = 'variation_skus^' . (float) $this->settings->get( 'weight_variation_sku', 12 );
            $fields[] = 'guid^' . (float) $this->settings->get( 'weight_sku', 16 );
        }
        if ( $this->settings->is_yes( 'search_in_brands' ) ) {
            $fields[] = 'brand_names^' . (float) $this->settings->get( 'weight_brands', 7 );
        }
        if ( $this->settings->is_yes( 'search_in_categories' ) ) {
            $fields[] = 'category_names^' . (float) $this->settings->get( 'weight_categories', 5 );
        }
        if ( $this->settings->is_yes( 'search_in_tags' ) ) {
            $fields[] = 'tag_names^' . (float) $this->settings->get( 'weight_tags', 3 );
        }
        if ( $this->settings->is_yes( 'search_in_attributes' ) ) {
            $fields[] = 'attributes_text^' . (float) $this->settings->get( 'weight_attributes', 4 );
        }
        if ( $this->settings->is_yes( 'search_in_short_description' ) ) {
            $fields[] = 'short_description^' . (float) $this->settings->get( 'weight_short_description', 2 );
        }
        if ( $this->settings->is_yes( 'search_in_description' ) ) {
            $fields[] = 'description^' . (float) $this->settings->get( 'weight_description', 1 );
        }
        if ( $this->settings->is_yes( 'search_in_custom_fields' ) ) {
            $fields[] = 'custom_fields_text^' . (float) $this->settings->get( 'weight_custom_fields', 3 );
        }

        $fuzzy_mode = (string) $this->settings->get( 'fuzzy_mode', 'normal' );
        $fuzziness  = 'off' === $fuzzy_mode ? 0 : ( 'aggressive' === $fuzzy_mode ? 2 : 'AUTO' );

        $should = array(
            array(
                'multi_match' => array(
                    'query'     => $query,
                    'fields'    => $fields,
                    'type'      => 'best_fields',
                    'operator'  => 'and',
                    'fuzziness' => $fuzziness,
                ),
            ),
            array(
                'term' => array(
                    'sku' => array(
                        'value' => $query,
                        'boost' => 25,
                    ),
                ),
            ),
            array(
                'term' => array(
                    'variation_skus' => array(
                        'value' => $query,
                        'boost' => 20,
                    ),
                ),
            ),
        );

        foreach ( $this->expand_synonyms( $query ) as $synonym ) {
            if ( 0 === strcasecmp( $synonym, $query ) ) {
                continue;
            }
            $should[] = array(
                'multi_match' => array(
                    'query'  => $synonym,
                    'fields' => $fields,
                    'type'   => 'best_fields',
                    'boost'  => 0.65,
                ),
            );
        }

        return array(
            'bool' => array(
                'should'               => $should,
                'minimum_should_match' => 1,
            ),
        );
    }

    private function expand_synonyms( string $query ): array {
        $normalized = $this->normalize( $query );
        $expanded   = array( $query );

        foreach ( $this->settings->synonyms() as $group ) {
            $normalized_group = array_map( array( $this, 'normalize' ), $group );
            if ( in_array( $normalized, $normalized_group, true ) ) {
                $expanded = array_merge( $expanded, $group );
            }
        }

        return array_values( array_unique( array_filter( $expanded ) ) );
    }

    private function add_static_filters( array &$filters, array &$must_not ): void {
        $mode = (string) $this->settings->get( 'filter_mode', 'exclude' );
        $sets = array(
            'id'           => $this->settings->id_list( 'filter_product_ids' ),
            'category_ids' => $this->settings->id_list( 'filter_category_ids' ),
            'tag_ids'      => $this->settings->id_list( 'filter_tag_ids' ),
            'brand_ids'    => $this->settings->id_list( 'filter_brand_ids' ),
        );

        foreach ( $sets as $field => $values ) {
            if ( ! $values ) {
                continue;
            }
            $clause = array( 'terms' => array( $field => $values ) );
            if ( 'include' === $mode ) {
                $filters[] = $clause;
            } else {
                $must_not[] = $clause;
            }
        }
    }

    private function sort( string $sort, bool $has_query ): array {
        if ( 'name' === $sort ) {
            return array( array( 'name_sort' => 'asc' ) );
        }
        if ( 'price-asc' === $sort ) {
            return array( array( 'price' => array( 'order' => 'asc', 'missing' => '_last' ) ) );
        }
        if ( 'price-desc' === $sort ) {
            return array( array( 'price' => array( 'order' => 'desc', 'missing' => '_last' ) ) );
        }
        if ( 'date' === $sort ) {
            return array( array( 'modified_gmt' => 'desc' ) );
        }

        return $has_query
            ? array( '_score', array( 'featured' => 'desc' ), array( 'name_sort' => 'asc' ) )
            : array( array( 'featured' => 'desc' ), array( 'modified_gmt' => 'desc' ) );
    }

    public function intercept_native_search( WP_Query $query ): void {
        if (
            is_admin()
            || ! $query->is_main_query()
            || ! $query->is_search()
            || ! $this->settings->is_yes( 'enabled' )
            || ! $this->settings->is_yes( 'replace_native_search' )
        ) {
            return;
        }

        $post_type = $query->get( 'post_type' );
        if ( $post_type && 'product' !== $post_type && ! ( is_array( $post_type ) && in_array( 'product', $post_type, true ) ) ) {
            return;
        }

        $term = trim( (string) $query->get( 's' ) );
        if ( '' === $term ) {
            return;
        }

        $ids = $this->search_ids( $term, 2000 );
        if ( is_wp_error( $ids ) ) {
            return;
        }

        $query->set( 'post_type', 'product' );
        $query->set( 'post__in', $ids ?: array( 0 ) );
        $query->set( 'orderby', 'post__in' );
        $query->set( 'cvos_active', 1 );
    }

    public function remove_mysql_search_clause( string $search, WP_Query $query ): string {
        return $query->get( 'cvos_active' ) ? '' : $search;
    }

    private function search_ids( string $term, int $limit ) {
        $body = array(
            'size'             => min( 5000, max( 1, $limit ) ),
            '_source'          => false,
            'track_total_hits' => false,
            'query'            => array(
                'bool' => array(
                    'filter' => array(
                        array( 'term' => array( 'status' => 'publish' ) ),
                    ),
                    'must' => array( $this->text_query( $term ) ),
                ),
            ),
        );

        $response = $this->client->search( $body );
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        return array_values(
            array_filter(
                array_map(
                    static fn( array $hit ): int => absint( $hit['_id'] ?? 0 ),
                    $response['hits']['hits'] ?? array()
                )
            )
        );
    }

    private function record_analytics( string $query, int $result_count ): void {
        if ( ! $this->settings->is_yes( 'analytics_enabled' ) || $this->text_length( $query ) < 2 ) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'cvos_search_analytics';
        $day   = gmdate( 'Y-m-d' );
        $clean = mb_substr( trim( preg_replace( '/\s+/u', ' ', $query ) ), 0, 160 );
        $hash  = hash( 'sha256', $this->normalize( $clean ) );

        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$table} (day,query_hash,query,result_count,hits,last_seen)
                 VALUES (%s,%s,%s,%d,1,%s)
                 ON DUPLICATE KEY UPDATE result_count=VALUES(result_count), hits=hits+1, last_seen=VALUES(last_seen)",
                $day,
                $hash,
                $clean,
                $result_count,
                current_time( 'mysql', true )
            )
        );
    }

    private function csv( $value ): array {
        return array_values(
            array_unique(
                array_filter(
                    array_map(
                        'sanitize_title',
                        preg_split( '/[\s,]+/', (string) $value )
                    )
                )
            )
        );
    }

    private function normalize( string $value ): string {
        $value = remove_accents( strtolower( trim( $value ) ) );
        return preg_replace( '/\s+/u', ' ', $value );
    }

    private function text_length( string $value ): int {
        return function_exists( 'mb_strlen' ) ? mb_strlen( $value ) : strlen( $value );
    }
}
