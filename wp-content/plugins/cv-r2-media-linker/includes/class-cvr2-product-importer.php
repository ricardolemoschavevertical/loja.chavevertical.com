<?php
defined( 'ABSPATH' ) || exit;

final class CVR2_Product_Importer {
    private const SOURCE_META = '_cvr2_source_product_id';

    public static function sync_structure(): array {
        $result = array(
            'categories' => 0,
            'tags'       => 0,
            'brands'     => 0,
            'attributes' => 0,
            'warnings'   => array(),
        );

        $categories = CVR2_REST_Client::all_pages( 'products/categories', array( 'hide_empty' => 'false' ) );
        if ( is_wp_error( $categories ) ) {
            $result['warnings'][] = 'Categorias: ' . $categories->get_error_message();
        } else {
            $result['categories'] = self::sync_hierarchical_terms( 'product_cat', $categories );
        }

        $tags = CVR2_REST_Client::all_pages( 'products/tags', array( 'hide_empty' => 'false' ) );
        if ( is_wp_error( $tags ) ) {
            $result['warnings'][] = 'Etiquetas: ' . $tags->get_error_message();
        } else {
            foreach ( $tags as $item ) {
                if ( self::ensure_term( 'product_tag', $item ) ) {
                    $result['tags']++;
                }
            }
        }

        $brands = CVR2_REST_Client::all_pages( 'products/brands', array( 'hide_empty' => 'false' ) );
        if ( is_wp_error( $brands ) ) {
            $result['warnings'][] = 'Marcas: endpoint não disponível ou erro — ' . $brands->get_error_message();
        } elseif ( taxonomy_exists( 'product_brand' ) ) {
            foreach ( $brands as $item ) {
                if ( self::ensure_term( 'product_brand', $item ) ) {
                    $result['brands']++;
                }
            }
        }

        $attributes = CVR2_REST_Client::all_pages( 'products/attributes' );
        if ( is_wp_error( $attributes ) ) {
            $result['warnings'][] = 'Atributos: ' . $attributes->get_error_message();
        } else {
            foreach ( $attributes as $attribute ) {
                $target_id = self::ensure_global_attribute( $attribute );
                if ( ! $target_id ) {
                    continue;
                }

                $result['attributes']++;
                $source_id = absint( $attribute['id'] ?? 0 );
                $taxonomy  = wc_attribute_taxonomy_name_by_id( $target_id );

                if ( $source_id && $taxonomy ) {
                    self::ensure_attribute_taxonomy_registered( $taxonomy, (string) ( $attribute['name'] ?? $taxonomy ) );
                    $terms = CVR2_REST_Client::all_pages( 'products/attributes/' . $source_id . '/terms' );
                    if ( ! is_wp_error( $terms ) ) {
                        foreach ( $terms as $term ) {
                            self::ensure_term( $taxonomy, $term );
                        }
                    }
                }
            }
        }

        return $result;
    }

    private static function sync_hierarchical_terms( string $taxonomy, array $items ): int {
        $done      = array();
        $remaining = array_values( $items );
        $count     = 0;
        $guard     = 0;

        while ( $remaining && $guard < 20 ) {
            $guard++;
            $next = array();
            $made_progress = false;

            foreach ( $remaining as $item ) {
                $source_id = absint( $item['id'] ?? 0 );
                $parent_id = absint( $item['parent'] ?? 0 );

                if ( $parent_id && ! isset( $done[ $parent_id ] ) ) {
                    $existing_parent = self::find_term_by_source_id( $taxonomy, $parent_id );
                    if ( ! $existing_parent ) {
                        $next[] = $item;
                        continue;
                    }
                    $done[ $parent_id ] = $existing_parent;
                }

                $parent_target = $parent_id ? absint( $done[ $parent_id ] ?? 0 ) : 0;
                $term_id = self::ensure_term( $taxonomy, $item, $parent_target );

                if ( $term_id ) {
                    $done[ $source_id ] = $term_id;
                    $count++;
                    $made_progress = true;
                }
            }

            if ( ! $made_progress && $next ) {
                foreach ( $next as $item ) {
                    $term_id = self::ensure_term( $taxonomy, $item, 0 );
                    if ( $term_id ) {
                        $done[ absint( $item['id'] ?? 0 ) ] = $term_id;
                        $count++;
                    }
                }
                break;
            }

            $remaining = $next;
        }

        return $count;
    }

    private static function find_term_by_source_id( string $taxonomy, int $source_id ): int {
        $terms = get_terms(
            array(
                'taxonomy'   => $taxonomy,
                'hide_empty' => false,
                'number'     => 1,
                'fields'     => 'ids',
                'meta_query' => array(
                    array(
                        'key'   => '_cvr2_source_term_id',
                        'value' => $source_id,
                    ),
                ),
            )
        );

        return is_wp_error( $terms ) || ! $terms ? 0 : absint( $terms[0] );
    }

    private static function ensure_term( string $taxonomy, array $item, int $parent = 0 ): int {
        if ( ! taxonomy_exists( $taxonomy ) ) {
            return 0;
        }

        $source_id = absint( $item['id'] ?? 0 );
        $name      = sanitize_text_field( (string) ( $item['name'] ?? '' ) );
        $slug      = sanitize_title( (string) ( $item['slug'] ?? $name ) );

        if ( ! $name || ! $slug ) {
            return 0;
        }

        $term_id = $source_id ? self::find_term_by_source_id( $taxonomy, $source_id ) : 0;
        if ( ! $term_id ) {
            $existing = get_term_by( 'slug', $slug, $taxonomy );
            $term_id  = $existing instanceof WP_Term ? (int) $existing->term_id : 0;
        }

        $args = array(
            'slug'   => $slug,
            'parent' => max( 0, $parent ),
        );

        if ( isset( $item['description'] ) ) {
            $args['description'] = wp_kses_post( (string) $item['description'] );
        }

        if ( $term_id ) {
            wp_update_term( $term_id, $taxonomy, array_merge( $args, array( 'name' => $name ) ) );
        } else {
            $created = wp_insert_term( $name, $taxonomy, $args );
            if ( is_wp_error( $created ) ) {
                return 0;
            }
            $term_id = absint( $created['term_id'] );
        }

        if ( $source_id ) {
            update_term_meta( $term_id, '_cvr2_source_term_id', $source_id );
        }

        if ( ! empty( $item['image']['src'] ) || ! empty( $item['image']['id'] ) ) {
            $image = is_array( $item['image'] ) ? $item['image'] : array();
            $attachment_id = CVR2_Media::attachment_for_source_image( $image );
            if ( ! is_wp_error( $attachment_id ) && $attachment_id ) {
                update_term_meta( $term_id, 'thumbnail_id', absint( $attachment_id ) );
            }
        }

        return $term_id;
    }

    private static function ensure_global_attribute( array $source ): int {
        $source_id = absint( $source['id'] ?? 0 );
        $name      = sanitize_text_field( (string) ( $source['name'] ?? '' ) );
        $slug      = wc_sanitize_taxonomy_name( (string) ( $source['slug'] ?? $name ) );

        if ( ! $name || ! $slug ) {
            return 0;
        }

        $map = (array) get_option( 'cvr2_attribute_map', array() );
        if ( $source_id && ! empty( $map[ $source_id ] ) ) {
            return absint( $map[ $source_id ] );
        }

        foreach ( wc_get_attribute_taxonomies() as $attribute ) {
            if ( $attribute->attribute_name === $slug || 0 === strcasecmp( $attribute->attribute_label, $name ) ) {
                $target_id = absint( $attribute->attribute_id );
                if ( $source_id ) {
                    $map[ $source_id ] = $target_id;
                    update_option( 'cvr2_attribute_map', $map, false );
                }
                return $target_id;
            }
        }

        $target_id = wc_create_attribute(
            array(
                'name'         => $name,
                'slug'         => $slug,
                'type'         => sanitize_key( (string) ( $source['type'] ?? 'select' ) ),
                'order_by'     => sanitize_key( (string) ( $source['order_by'] ?? 'menu_order' ) ),
                'has_archives' => ! empty( $source['has_archives'] ),
            )
        );

        if ( is_wp_error( $target_id ) ) {
            return 0;
        }

        delete_transient( 'wc_attribute_taxonomies' );
        $target_id = absint( $target_id );

        if ( $source_id ) {
            $map[ $source_id ] = $target_id;
            update_option( 'cvr2_attribute_map', $map, false );
        }

        return $target_id;
    }

    private static function ensure_attribute_taxonomy_registered( string $taxonomy, string $label ): void {
        if ( taxonomy_exists( $taxonomy ) ) {
            return;
        }

        register_taxonomy(
            $taxonomy,
            array( 'product' ),
            array(
                'hierarchical' => false,
                'label'        => $label,
                'public'       => false,
                'show_ui'      => false,
                'query_var'    => true,
                'rewrite'      => false,
            )
        );
    }

    public static function import_source_product( array $source ) {
        $source_id = absint( $source['id'] ?? 0 );
        $type      = sanitize_key( (string) ( $source['type'] ?? 'simple' ) );
        $sku       = wc_clean( (string) ( $source['sku'] ?? '' ) );
        $slug      = sanitize_title( (string) ( $source['slug'] ?? '' ) );
        $target_id = self::find_product( $source_id, $sku, $slug );

        if ( $target_id && $slug ) {
            $old_slug = (string) get_post_field( 'post_name', $target_id );
            if ( $old_slug && $old_slug !== $slug ) {
                add_post_meta( $target_id, '_wp_old_slug', $old_slug );
            }
        }

        wp_set_object_terms( $target_id, $type, 'product_type' );
        $product = self::product_instance( $type, $target_id );

        if ( ! $product ) {
            return new WP_Error( 'cvr2_product_type', 'Tipo de produto não suportado: ' . $type );
        }

        self::apply_common_fields( $product, $source );

        $category_ids = array();
        foreach ( (array) ( $source['categories'] ?? array() ) as $term ) {
            $id = self::ensure_term( 'product_cat', (array) $term );
            if ( $id ) {
                $category_ids[] = $id;
            }
        }
        $product->set_category_ids( array_values( array_unique( $category_ids ) ) );

        $tag_ids = array();
        foreach ( (array) ( $source['tags'] ?? array() ) as $term ) {
            $id = self::ensure_term( 'product_tag', (array) $term );
            if ( $id ) {
                $tag_ids[] = $id;
            }
        }
        $product->set_tag_ids( array_values( array_unique( $tag_ids ) ) );

        self::apply_attributes( $product, (array) ( $source['attributes'] ?? array() ) );
        self::apply_default_attributes( $product, (array) ( $source['default_attributes'] ?? array() ) );
        self::apply_images( $product, (array) ( $source['images'] ?? array() ) );

        $target_id = $product->save();

        if ( ! $target_id ) {
            return new WP_Error( 'cvr2_product_save', 'WooCommerce não guardou o produto.' );
        }

        if ( $source_id ) {
            update_post_meta( $target_id, self::SOURCE_META, $source_id );
        }
        update_post_meta( $target_id, '_cvr2_source_slug', $slug );
        update_post_meta( $target_id, '_cvr2_imported_at', gmdate( 'c' ) );

        self::apply_brand_terms( $target_id, (array) ( $source['brands'] ?? array() ) );
        self::copy_meta_data( $target_id, (array) ( $source['meta_data'] ?? array() ) );
        self::store_pending_relations( $target_id, $source );

        if ( 'variable' === $type && $source_id ) {
            $variations = CVR2_REST_Client::all_pages( 'products/' . $source_id . '/variations', array( 'status' => 'any' ) );
            if ( is_wp_error( $variations ) ) {
                update_post_meta( $target_id, '_cvr2_variation_import_error', $variations->get_error_message() );
            } else {
                foreach ( $variations as $variation ) {
                    self::import_variation( $target_id, (array) $variation );
                }
            }
        }

        return array(
            'id'        => $target_id,
            'source_id' => $source_id,
            'sku'       => $sku,
            'slug'      => $slug,
        );
    }

    private static function find_product( int $source_id, string $sku, string $slug ): int {
        if ( $source_id ) {
            $ids = get_posts(
                array(
                    'post_type'      => 'product',
                    'post_status'    => 'any',
                    'posts_per_page' => 1,
                    'fields'         => 'ids',
                    'meta_key'       => self::SOURCE_META,
                    'meta_value'     => $source_id,
                    'no_found_rows'  => true,
                )
            );
            if ( $ids ) {
                return absint( $ids[0] );
            }
        }

        if ( $sku ) {
            $id = wc_get_product_id_by_sku( $sku );
            if ( $id ) {
                return absint( $id );
            }
        }

        if ( $slug ) {
            $post = get_page_by_path( $slug, OBJECT, 'product' );
            if ( $post instanceof WP_Post ) {
                return (int) $post->ID;
            }
        }

        return 0;
    }

    private static function product_instance( string $type, int $id ) {
        return match ( $type ) {
            'variable' => new WC_Product_Variable( $id ),
            'grouped'  => new WC_Product_Grouped( $id ),
            'external' => new WC_Product_External( $id ),
            default    => new WC_Product_Simple( $id ),
        };
    }

    private static function apply_common_fields( WC_Product $product, array $source ): void {
        $product->set_name( wp_strip_all_tags( (string) ( $source['name'] ?? '' ) ) );
        $product->set_slug( sanitize_title( (string) ( $source['slug'] ?? '' ) ) );
        $product->set_status( sanitize_key( (string) ( $source['status'] ?? 'draft' ) ) );
        $product->set_featured( ! empty( $source['featured'] ) );
        $product->set_catalog_visibility( sanitize_key( (string) ( $source['catalog_visibility'] ?? 'visible' ) ) );
        $product->set_description( wp_kses_post( (string) ( $source['description'] ?? '' ) ) );
        $product->set_short_description( wp_kses_post( (string) ( $source['short_description'] ?? '' ) ) );

        $sku = wc_clean( (string) ( $source['sku'] ?? '' ) );
        if ( $sku ) {
            try {
                $product->set_sku( $sku );
            } catch ( WC_Data_Exception $e ) {
                update_post_meta( $product->get_id(), '_cvr2_sku_warning', $e->getMessage() );
            }
        }

        $product->set_regular_price( wc_format_decimal( (string) ( $source['regular_price'] ?? '' ) ) );
        $product->set_sale_price( wc_format_decimal( (string) ( $source['sale_price'] ?? '' ) ) );

        if ( ! empty( $source['date_on_sale_from_gmt'] ) ) {
            $product->set_date_on_sale_from( (string) $source['date_on_sale_from_gmt'] );
        }
        if ( ! empty( $source['date_on_sale_to_gmt'] ) ) {
            $product->set_date_on_sale_to( (string) $source['date_on_sale_to_gmt'] );
        }

        $product->set_virtual( ! empty( $source['virtual'] ) );
        $product->set_downloadable( ! empty( $source['downloadable'] ) );
        $product->set_download_limit( (int) ( $source['download_limit'] ?? -1 ) );
        $product->set_download_expiry( (int) ( $source['download_expiry'] ?? -1 ) );
        $product->set_tax_status( sanitize_key( (string) ( $source['tax_status'] ?? 'taxable' ) ) );
        $product->set_tax_class( sanitize_title( (string) ( $source['tax_class'] ?? '' ) ) );
        $product->set_manage_stock( ! empty( $source['manage_stock'] ) );

        if ( array_key_exists( 'stock_quantity', $source ) && null !== $source['stock_quantity'] ) {
            $product->set_stock_quantity( (int) $source['stock_quantity'] );
        }

        $product->set_stock_status( sanitize_key( (string) ( $source['stock_status'] ?? 'instock' ) ) );
        $product->set_backorders( sanitize_key( (string) ( $source['backorders'] ?? 'no' ) ) );
        $product->set_sold_individually( ! empty( $source['sold_individually'] ) );
        $product->set_weight( wc_format_decimal( (string) ( $source['weight'] ?? '' ) ) );

        $dimensions = (array) ( $source['dimensions'] ?? array() );
        $product->set_length( wc_format_decimal( (string) ( $dimensions['length'] ?? '' ) ) );
        $product->set_width( wc_format_decimal( (string) ( $dimensions['width'] ?? '' ) ) );
        $product->set_height( wc_format_decimal( (string) ( $dimensions['height'] ?? '' ) ) );
        $product->set_reviews_allowed( ! empty( $source['reviews_allowed'] ) );
        $product->set_purchase_note( wp_kses_post( (string) ( $source['purchase_note'] ?? '' ) ) );
        $product->set_menu_order( (int) ( $source['menu_order'] ?? 0 ) );

        if ( $product instanceof WC_Product_External ) {
            $product->set_product_url( esc_url_raw( (string) ( $source['external_url'] ?? '' ) ) );
            $product->set_button_text( sanitize_text_field( (string) ( $source['button_text'] ?? '' ) ) );
        }

        $shipping = (array) ( $source['shipping_class'] ?? array() );
        $shipping_slug = sanitize_title( is_string( $source['shipping_class'] ?? null ) ? (string) $source['shipping_class'] : '' );
        if ( $shipping_slug ) {
            $term = get_term_by( 'slug', $shipping_slug, 'product_shipping_class' );
            if ( ! $term ) {
                $created = wp_insert_term( $shipping_slug, 'product_shipping_class', array( 'slug' => $shipping_slug ) );
                if ( ! is_wp_error( $created ) ) {
                    $product->set_shipping_class_id( absint( $created['term_id'] ) );
                }
            } elseif ( $term instanceof WP_Term ) {
                $product->set_shipping_class_id( (int) $term->term_id );
            }
        }

        if ( ! empty( $source['downloads'] ) ) {
            $downloads = array();
            foreach ( (array) $source['downloads'] as $item ) {
                if ( empty( $item['file'] ) ) {
                    continue;
                }
                $download = new WC_Product_Download();
                $download->set_id( (string) ( $item['id'] ?? md5( (string) $item['file'] ) ) );
                $download->set_name( sanitize_text_field( (string) ( $item['name'] ?? 'Download' ) ) );
                $download->set_file( esc_url_raw( (string) $item['file'] ) );
                $downloads[] = $download;
            }
            $product->set_downloads( $downloads );
        }
    }

    private static function apply_attributes( WC_Product $product, array $source_attributes ): void {
        $attributes = array();
        $map        = (array) get_option( 'cvr2_attribute_map', array() );

        foreach ( $source_attributes as $source ) {
            $source = (array) $source;
            $attr   = new WC_Product_Attribute();
            $src_id = absint( $source['id'] ?? 0 );

            if ( $src_id && ! empty( $map[ $src_id ] ) ) {
                $target_id = absint( $map[ $src_id ] );
                $taxonomy  = wc_attribute_taxonomy_name_by_id( $target_id );
                if ( $taxonomy ) {
                    self::ensure_attribute_taxonomy_registered( $taxonomy, (string) ( $source['name'] ?? $taxonomy ) );
                    $option_ids = array();
                    foreach ( (array) ( $source['options'] ?? array() ) as $option ) {
                        $term = term_exists( (string) $option, $taxonomy );
                        if ( ! $term ) {
                            $term = wp_insert_term( (string) $option, $taxonomy );
                        }
                        if ( ! is_wp_error( $term ) ) {
                            $option_ids[] = absint( is_array( $term ) ? $term['term_id'] : $term );
                        }
                    }
                    $attr->set_id( $target_id );
                    $attr->set_name( $taxonomy );
                    $attr->set_options( $option_ids );
                }
            } else {
                $attr->set_id( 0 );
                $attr->set_name( sanitize_text_field( (string) ( $source['name'] ?? '' ) ) );
                $attr->set_options( array_map( 'sanitize_text_field', (array) ( $source['options'] ?? array() ) ) );
            }

            $attr->set_position( (int) ( $source['position'] ?? 0 ) );
            $attr->set_visible( ! empty( $source['visible'] ) );
            $attr->set_variation( ! empty( $source['variation'] ) );
            $attributes[] = $attr;
        }

        $product->set_attributes( $attributes );
    }

    private static function apply_default_attributes( WC_Product $product, array $defaults ): void {
        $out = array();
        $map = (array) get_option( 'cvr2_attribute_map', array() );

        foreach ( $defaults as $default ) {
            $default = (array) $default;
            $src_id  = absint( $default['id'] ?? 0 );
            $option  = (string) ( $default['option'] ?? '' );

            if ( $src_id && ! empty( $map[ $src_id ] ) ) {
                $taxonomy = wc_attribute_taxonomy_name_by_id( absint( $map[ $src_id ] ) );
                if ( $taxonomy ) {
                    $term = get_term_by( 'name', $option, $taxonomy );
                    $out[ $taxonomy ] = $term instanceof WP_Term ? $term->slug : sanitize_title( $option );
                }
            } elseif ( ! empty( $default['name'] ) ) {
                $out[ sanitize_title( (string) $default['name'] ) ] = $option;
            }
        }

        $product->set_default_attributes( $out );
    }

    private static function apply_images( WC_Product $product, array $images ): void {
        $ids = array();

        foreach ( $images as $image ) {
            $id = CVR2_Media::attachment_for_source_image( (array) $image );
            if ( ! is_wp_error( $id ) && $id ) {
                $ids[] = absint( $id );
            }
        }

        $ids = array_values( array_unique( array_filter( $ids ) ) );
        $product->set_image_id( $ids ? array_shift( $ids ) : 0 );
        $product->set_gallery_image_ids( $ids );
    }

    private static function apply_brand_terms( int $product_id, array $brands ): void {
        if ( ! taxonomy_exists( 'product_brand' ) ) {
            return;
        }

        $ids = array();
        foreach ( $brands as $brand ) {
            $id = self::ensure_term( 'product_brand', (array) $brand );
            if ( $id ) {
                $ids[] = $id;
            }
        }

        wp_set_object_terms( $product_id, array_values( array_unique( $ids ) ), 'product_brand', false );
    }

    private static function copy_meta_data( int $target_id, array $meta_data ): void {
        $blocked = array(
            '_edit_lock',
            '_edit_last',
            '_thumbnail_id',
            '_product_image_gallery',
            '_sku',
            '_price',
            '_regular_price',
            '_sale_price',
            '_stock',
            '_stock_status',
            '_manage_stock',
            '_product_version',
            '_wp_old_slug',
        );

        foreach ( $meta_data as $meta ) {
            $meta = (array) $meta;
            $key  = sanitize_key( (string) ( $meta['key'] ?? '' ) );

            if ( ! $key || in_array( $key, $blocked, true ) ) {
                continue;
            }

            update_post_meta( $target_id, $key, $meta['value'] ?? '' );
        }
    }

    private static function store_pending_relations( int $target_id, array $source ): void {
        foreach ( array(
            'upsell_ids'        => '_cvr2_source_upsell_ids',
            'cross_sell_ids'    => '_cvr2_source_cross_sell_ids',
            'grouped_products'  => '_cvr2_source_grouped_ids',
        ) as $source_key => $meta_key ) {
            update_post_meta(
                $target_id,
                $meta_key,
                array_values( array_filter( array_map( 'absint', (array) ( $source[ $source_key ] ?? array() ) ) ) )
            );
        }
    }

    private static function find_target_by_source_id( int $source_id ): int {
        if ( ! $source_id ) {
            return 0;
        }

        $ids = get_posts(
            array(
                'post_type'      => 'product',
                'post_status'    => 'any',
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'meta_key'       => self::SOURCE_META,
                'meta_value'     => $source_id,
                'no_found_rows'  => true,
            )
        );

        return $ids ? absint( $ids[0] ) : 0;
    }

    public static function resolve_relations_page( int $page = 1, int $per_page = 100 ): array {
        $query = new WP_Query(
            array(
                'post_type'      => 'product',
                'post_status'    => 'any',
                'posts_per_page' => max( 1, min( 500, $per_page ) ),
                'paged'          => max( 1, $page ),
                'fields'         => 'ids',
                'meta_query'     => array(
                    array(
                        'key'     => self::SOURCE_META,
                        'compare' => 'EXISTS',
                    ),
                ),
                'no_found_rows'  => false,
            )
        );

        foreach ( $query->posts as $product_id ) {
            $product = wc_get_product( $product_id );
            if ( ! $product ) {
                continue;
            }

            $upsells = array_map( array( __CLASS__, 'find_target_by_source_id' ), (array) get_post_meta( $product_id, '_cvr2_source_upsell_ids', true ) );
            $cross   = array_map( array( __CLASS__, 'find_target_by_source_id' ), (array) get_post_meta( $product_id, '_cvr2_source_cross_sell_ids', true ) );

            $product->set_upsell_ids( array_values( array_filter( $upsells ) ) );
            $product->set_cross_sell_ids( array_values( array_filter( $cross ) ) );

            if ( $product instanceof WC_Product_Grouped ) {
                $children = array_map( array( __CLASS__, 'find_target_by_source_id' ), (array) get_post_meta( $product_id, '_cvr2_source_grouped_ids', true ) );
                $product->set_children( array_values( array_filter( $children ) ) );
            }

            $product->save();
        }

        return array(
            'page'        => max( 1, $page ),
            'total_pages' => max( 1, (int) $query->max_num_pages ),
            'processed'   => count( $query->posts ),
        );
    }

    private static function import_variation( int $parent_id, array $source ) {
        $source_id = absint( $source['id'] ?? 0 );
        $sku       = wc_clean( (string) ( $source['sku'] ?? '' ) );
        $target_id = 0;

        if ( $source_id ) {
            $ids = get_posts(
                array(
                    'post_type'      => 'product_variation',
                    'post_status'    => 'any',
                    'posts_per_page' => 1,
                    'fields'         => 'ids',
                    'meta_key'       => '_cvr2_source_variation_id',
                    'meta_value'     => $source_id,
                    'no_found_rows'  => true,
                )
            );
            if ( $ids ) {
                $target_id = absint( $ids[0] );
            }
        }

        if ( ! $target_id && $sku ) {
            $candidate = wc_get_product_id_by_sku( $sku );
            if ( $candidate && 'product_variation' === get_post_type( $candidate ) ) {
                $target_id = absint( $candidate );
            }
        }

        $variation = new WC_Product_Variation( $target_id );
        $variation->set_parent_id( $parent_id );
        $variation->set_status( sanitize_key( (string) ( $source['status'] ?? 'publish' ) ) );

        if ( $sku ) {
            try {
                $variation->set_sku( $sku );
            } catch ( WC_Data_Exception $e ) {
                // Mantém a variação sem alterar SKU se já estiver atribuído noutro registo.
            }
        }

        $variation->set_regular_price( wc_format_decimal( (string) ( $source['regular_price'] ?? '' ) ) );
        $variation->set_sale_price( wc_format_decimal( (string) ( $source['sale_price'] ?? '' ) ) );
        $variation->set_manage_stock( ! empty( $source['manage_stock'] ) );

        if ( array_key_exists( 'stock_quantity', $source ) && null !== $source['stock_quantity'] ) {
            $variation->set_stock_quantity( (int) $source['stock_quantity'] );
        }

        $variation->set_stock_status( sanitize_key( (string) ( $source['stock_status'] ?? 'instock' ) ) );
        $variation->set_backorders( sanitize_key( (string) ( $source['backorders'] ?? 'no' ) ) );
        $variation->set_weight( wc_format_decimal( (string) ( $source['weight'] ?? '' ) ) );

        $dimensions = (array) ( $source['dimensions'] ?? array() );
        $variation->set_length( wc_format_decimal( (string) ( $dimensions['length'] ?? '' ) ) );
        $variation->set_width( wc_format_decimal( (string) ( $dimensions['width'] ?? '' ) ) );
        $variation->set_height( wc_format_decimal( (string) ( $dimensions['height'] ?? '' ) ) );
        $variation->set_menu_order( (int) ( $source['menu_order'] ?? 0 ) );

        $attribute_map = (array) get_option( 'cvr2_attribute_map', array() );
        $variation_attributes = array();

        foreach ( (array) ( $source['attributes'] ?? array() ) as $attribute ) {
            $attribute = (array) $attribute;
            $src_id    = absint( $attribute['id'] ?? 0 );
            $option    = (string) ( $attribute['option'] ?? '' );

            if ( $src_id && ! empty( $attribute_map[ $src_id ] ) ) {
                $taxonomy = wc_attribute_taxonomy_name_by_id( absint( $attribute_map[ $src_id ] ) );
                $variation_attributes[ $taxonomy ] = sanitize_title( $option );
            } elseif ( ! empty( $attribute['name'] ) ) {
                $variation_attributes[ sanitize_title( (string) $attribute['name'] ) ] = $option;
            }
        }

        $variation->set_attributes( $variation_attributes );

        if ( ! empty( $source['image'] ) && is_array( $source['image'] ) ) {
            $image_id = CVR2_Media::attachment_for_source_image( $source['image'] );
            if ( ! is_wp_error( $image_id ) && $image_id ) {
                $variation->set_image_id( absint( $image_id ) );
            }
        }

        $target_id = $variation->save();
        if ( $target_id && $source_id ) {
            update_post_meta( $target_id, '_cvr2_source_variation_id', $source_id );
            self::copy_meta_data( $target_id, (array) ( $source['meta_data'] ?? array() ) );
        }

        return $target_id;
    }
}
