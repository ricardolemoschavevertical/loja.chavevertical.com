<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WC_Product_CSV_Importer', false ) || class_exists( 'CVR2_Product_CSV_Importer', false ) ) {
    return;
}

class CVR2_Product_CSV_Importer extends WC_Product_CSV_Importer {
    protected function set_image_data( &$product, $data ) {
        $featured = 0;
        $gallery = array();
        $previous = $product->get_gallery_image_ids( 'edit' );

        if ( ! empty( $data['raw_image_id'] ) ) {
            $result = $this->cvr2_resolve_image( $data['raw_image_id'], $product );
            if ( is_wp_error( $result ) || ! $result || ! wp_attachment_is_image( $result ) ) {
                $message = is_wp_error( $result ) ? $result->get_error_message() : 'A imagem principal não é um attachment válido.';
                throw new Exception( '[R2 / imagem principal] ' . $message, 400 );
            }
            $featured = absint( $result );
            $product->set_image_id( $featured );
        }

        if ( isset( $data['raw_gallery_image_ids'] ) ) {
            $had_failure = false;

            foreach ( (array) $data['raw_gallery_image_ids'] as $value ) {
                if ( '' === trim( (string) $value ) ) {
                    continue;
                }

                $result = $this->cvr2_resolve_image( $value, $product );
                if ( is_wp_error( $result ) || ! $result || ! wp_attachment_is_image( $result ) ) {
                    $had_failure = true;
                    continue;
                }

                $gallery[] = absint( $result );
            }

            if ( $had_failure ) {
                $gallery = array_merge( $previous, $gallery );
            }

            $main = $featured ?: (int) $product->get_image_id( 'edit' );
            $gallery = array_values(
                array_unique(
                    array_filter(
                        array_map( 'absint', $gallery ),
                        static fn( int $id ): bool => $id > 0 && $id !== $main
                    )
                )
            );

            $product->set_gallery_image_ids( $gallery );
        }
    }

    private function cvr2_resolve_image( $value, WC_Product $product ) {
        $value = trim( (string) $value );

        if ( '' === $value ) {
            return new WP_Error( 'cvr2_csv_empty_image', 'Referência de imagem vazia.' );
        }

        if ( ctype_digit( $value ) ) {
            $attachment_id = absint( $value );
            if ( $attachment_id && wp_attachment_is_image( $attachment_id ) ) {
                return $attachment_id;
            }
        }

        $existing = attachment_url_to_postid( $value );
        if ( $existing && wp_attachment_is_image( $existing ) ) {
            return $existing;
        }

        return CVR2_Media::attachment_for_source_image(
            array(
                'src'  => $value,
                'name' => $product->get_name(),
                'alt'  => $product->get_name(),
            )
        );
    }

    public function get_attachment_id_from_url( $url, $product_id ) {
        $product = $product_id ? wc_get_product( $product_id ) : false;

        $result = $this->cvr2_resolve_image(
            $url,
            $product instanceof WC_Product ? $product : new WC_Product_Simple()
        );

        if ( is_wp_error( $result ) || ! $result ) {
            throw new Exception(
                is_wp_error( $result ) ? $result->get_error_message() : 'Não foi possível resolver a imagem.',
                400
            );
        }

        return absint( $result );
    }
}
