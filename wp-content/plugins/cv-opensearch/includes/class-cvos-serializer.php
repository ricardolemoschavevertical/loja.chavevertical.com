<?php
defined( 'ABSPATH' ) || exit;

final class CVOS_Serializer {
    private CVOS_Settings $settings;

    public function __construct( CVOS_Settings $settings ) {
        $this->settings = $settings;
    }

    public function product( int $product_id ): ?array {
        $product = wc_get_product( $product_id );
        if ( ! $product instanceof WC_Product ) {
            return null;
        }

        if ( $product->is_type( 'variation' ) ) {
            $parent_id = $product->get_parent_id();
            return $parent_id ? $this->product( $parent_id ) : null;
        }

        if ( 'publish' !== $product->get_status() ) {
            return null;
        }

        $id         = $product->get_id();
        $categories = $this->terms( $id, 'product_cat' );
        $tags       = $this->terms( $id, 'product_tag' );
        $brands     = $this->brands( $id );
        $attributes = $this->attributes( $product );
        $variations = $this->variations( $product );

        $image_id    = $product->get_image_id();
        $gallery_ids = array_values(
            array_unique(
                array_filter(
                    array_merge(
                        array( $image_id ),
                        $product->get_gallery_image_ids()
                    )
                )
            )
        );

        $gallery = array();
        foreach ( array_slice( $gallery_ids, 0, 12 ) as $attachment_id ) {
            $url = wp_get_attachment_image_url( $attachment_id, 'full' );
            if ( $url ) {
                $gallery[] = array(
                    'src' => esc_url_raw( $url ),
                    'alt' => sanitize_text_field( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ),
                );
            }
        }

        $custom_values = array();
        foreach ( preg_split( '/[\s,;]+/', (string) $this->settings->get( 'custom_fields', '' ) ) as $meta_key ) {
            $meta_key = trim( $meta_key );
            if ( '' === $meta_key || 0 === strpos( $meta_key, '_' ) ) {
                continue;
            }

            $value = get_post_meta( $id, $meta_key, true );
            if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
                $custom_values[] = wp_strip_all_tags( (string) $value );
            }
        }

        $public_base = untrailingslashit( (string) $this->settings->get( 'public_base_url', '' ) );
        $woo_url     = (string) get_permalink( $id );
        $public_url  = $woo_url;

        if ( $public_base && $woo_url ) {
            $path = (string) wp_parse_url( $woo_url, PHP_URL_PATH );
            $public_url = $public_base . '/' . ltrim( $path, '/' );
        }

        $variation_skus = array_values(
            array_unique(
                array_filter(
                    array_column( $variations, 'sku' )
                )
            )
        );

        $currency  = get_woocommerce_currency();
        $stock_qty = $product->get_stock_quantity();

        return array(
            'id'                 => $id,
            'parent_id'          => 0,
            'name'               => wp_strip_all_tags( $product->get_name() ),
            'name_sort'          => wp_strip_all_tags( $product->get_name() ),
            'slug'               => $product->get_slug(),
            'url'                => esc_url_raw( $public_url ),
            'purchase_url'       => esc_url_raw( $woo_url ),
            'sku'                => (string) $product->get_sku(),
            'variation_skus'     => $variation_skus,
            'guid'               => method_exists( $product, 'get_global_unique_id' ) ? (string) $product->get_global_unique_id() : '',
            'short_description'  => wp_strip_all_tags( (string) $product->get_short_description() ),
            'description'        => wp_strip_all_tags( (string) $product->get_description() ),
            'brand_names'        => array_column( $brands, 'name' ),
            'brand_slugs'        => array_column( $brands, 'slug' ),
            'brand_ids'          => array_map( 'intval', array_column( $brands, 'id' ) ),
            'brand_facets'       => array_map(
                static fn( array $term ): string => $term['slug'] . '|' . $term['name'],
                $brands
            ),
            'category_names'     => array_column( $categories, 'name' ),
            'category_slugs'     => array_column( $categories, 'slug' ),
            'category_ids'       => array_map( 'intval', array_column( $categories, 'id' ) ),
            'tag_names'          => array_column( $tags, 'name' ),
            'tag_ids'            => array_map( 'intval', array_column( $tags, 'id' ) ),
            'attributes_text'    => implode(
                ' ',
                array_map(
                    static fn( array $attribute ): string => $attribute['name'] . ' ' . implode( ' ', $attribute['options'] ),
                    $attributes
                )
            ),
            'custom_fields_text' => implode( ' ', $custom_values ),
            'price'              => (float) $product->get_price(),
            'regular_price'      => (float) $product->get_regular_price(),
            'sale_price'         => (float) $product->get_sale_price(),
            'on_sale'            => $product->is_on_sale(),
            'currency'           => $currency,
            'stock_status'       => sanitize_key( $product->get_stock_status() ),
            'stock_quantity'     => null === $stock_qty ? null : (float) $stock_qty,
            'featured'           => $product->is_featured(),
            'average_rating'     => (float) $product->get_average_rating(),
            'rating_count'       => (int) $product->get_rating_count(),
            'type'               => sanitize_key( $product->get_type() ),
            'status'             => 'publish',
            'modified_gmt'       => $product->get_date_modified()
                ? $product->get_date_modified()->setTimezone( new DateTimeZone( 'UTC' ) )->format( DATE_ATOM )
                : gmdate( DATE_ATOM ),
            'categories'         => $categories,
            'brands'             => $brands,
            'tags'               => $tags,
            'attributes'         => $attributes,
            'gallery'            => $gallery,
            'variations'         => $variations,
            'image_url'          => $gallery[0]['src'] ?? '',
            'image_alt'          => $gallery[0]['alt'] ?? $product->get_name(),
        );
    }

    private function terms( int $product_id, string $taxonomy ): array {
        $terms = get_the_terms( $product_id, $taxonomy );

        if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
            return array();
        }

        return array_values(
            array_map(
                static fn( WP_Term $term ): array => array(
                    'id'   => (int) $term->term_id,
                    'name' => $term->name,
                    'slug' => $term->slug,
                ),
                $terms
            )
        );
    }

    private function brands( int $product_id ): array {
        foreach ( array( 'product_brand', 'pwb-brand', 'yith_product_brand' ) as $taxonomy ) {
            if ( ! taxonomy_exists( $taxonomy ) ) {
                continue;
            }

            $terms = $this->terms( $product_id, $taxonomy );
            if ( ! $terms ) {
                continue;
            }

            foreach ( $terms as &$term ) {
                $thumbnail_id = absint( get_term_meta( $term['id'], 'thumbnail_id', true ) );
                $term['logo'] = $thumbnail_id ? (string) wp_get_attachment_image_url( $thumbnail_id, 'full' ) : '';
            }
            unset( $term );

            return $terms;
        }

        return array();
    }

    private function attributes( WC_Product $product ): array {
        $output = array();

        foreach ( $product->get_attributes() as $attribute ) {
            if ( ! $attribute instanceof WC_Product_Attribute ) {
                continue;
            }

            $name = wc_attribute_label( $attribute->get_name() );

            if ( $attribute->is_taxonomy() ) {
                $terms = wc_get_product_terms(
                    $product->get_id(),
                    $attribute->get_name(),
                    array( 'fields' => 'names' )
                );
                $options = is_wp_error( $terms ) ? array() : $terms;
            } else {
                $options = $attribute->get_options();
            }

            $output[] = array(
                'name'    => wp_strip_all_tags( $name ),
                'slug'    => sanitize_title( $attribute->get_name() ),
                'options' => array_values( array_map( 'strval', $options ) ),
            );
        }

        return $output;
    }

    private function variations( WC_Product $product ): array {
        if ( ! $product->is_type( 'variable' ) ) {
            return array();
        }

        $output = array();

        foreach ( $product->get_children() as $variation_id ) {
            $variation = wc_get_product( $variation_id );

            if ( ! $variation instanceof WC_Product_Variation || 'publish' !== $variation->get_status() ) {
                continue;
            }

            $image_id = $variation->get_image_id();

            $output[] = array(
                'id'             => $variation->get_id(),
                'sku'            => (string) $variation->get_sku(),
                'price'          => (float) $variation->get_price(),
                'regular_price'  => (float) $variation->get_regular_price(),
                'sale_price'     => (float) $variation->get_sale_price(),
                'stock_status'   => sanitize_key( $variation->get_stock_status() ),
                'stock_quantity' => null === $variation->get_stock_quantity() ? null : (float) $variation->get_stock_quantity(),
                'attributes'     => array_map( 'strval', $variation->get_attributes() ),
                'image'          => $image_id ? (string) wp_get_attachment_image_url( $image_id, 'full' ) : '',
            );
        }

        return $output;
    }
}
