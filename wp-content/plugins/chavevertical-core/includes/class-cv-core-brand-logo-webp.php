<?php
defined( 'ABSPATH' ) || exit;

/**
 * Normaliza logótipos da taxonomia product_brand para WebP.
 *
 * - Mantém o AVIF original para rollback.
 * - Cria/reutiliza um novo attachment WebP.
 * - Atualiza apenas o thumbnail_id da marca.
 * - Converte automaticamente novos thumbnails AVIF atribuídos a marcas.
 */
final class CV_Core_Brand_Logo_WebP {
    private const SOURCE_META = '_cv_brand_logo_source_attachment_id';
    private const PREVIOUS_TERM_META = '_cv_brand_logo_previous_thumbnail_id';
    private const CONVERTED_AT_META = '_cv_brand_logo_webp_converted_at';

    /** @var array<int,bool> */
    private static array $running = array();

    public static function init(): void {
        add_action( 'added_term_meta', array( __CLASS__, 'maybe_convert_thumbnail_meta' ), 100, 4 );
        add_action( 'updated_term_meta', array( __CLASS__, 'maybe_convert_thumbnail_meta' ), 100, 4 );
    }

    /**
     * Converte automaticamente quando o thumbnail_id de uma marca é gravado.
     *
     * @param int    $meta_id    ID do registo de meta.
     * @param int    $term_id    ID do termo.
     * @param string $meta_key   Chave do meta.
     * @param mixed  $meta_value Valor do meta.
     */
    public static function maybe_convert_thumbnail_meta( $meta_id, $term_id, $meta_key, $meta_value ): void {
        unset( $meta_id, $meta_value );

        if ( 'thumbnail_id' !== $meta_key ) {
            return;
        }

        $term = get_term( (int) $term_id );

        if ( ! $term instanceof WP_Term || 'product_brand' !== $term->taxonomy ) {
            return;
        }

        self::convert_term( (int) $term_id );
    }

    /**
     * Converte todos os logótipos AVIF atualmente associados a marcas.
     *
     * @return array{converted:int,reused:int,skipped:int,failed:int,errors:array<int,string>}
     */
    public static function convert_existing(): array {
        $summary = array(
            'converted' => 0,
            'reused'    => 0,
            'skipped'   => 0,
            'failed'    => 0,
            'errors'    => array(),
        );

        if ( ! taxonomy_exists( 'product_brand' ) ) {
            $summary['failed']++;
            $summary['errors'][] = 'A taxonomia product_brand não está disponível.';
            return $summary;
        }

        $term_ids = get_terms(
            array(
                'taxonomy'   => 'product_brand',
                'hide_empty' => false,
                'fields'     => 'ids',
            )
        );

        if ( is_wp_error( $term_ids ) ) {
            $summary['failed']++;
            $summary['errors'][] = $term_ids->get_error_message();
            return $summary;
        }

        foreach ( $term_ids as $term_id ) {
            $result = self::convert_term( (int) $term_id );

            if ( is_wp_error( $result ) ) {
                $summary['failed']++;
                $summary['errors'][] = sprintf(
                    'Marca %d: %s',
                    (int) $term_id,
                    $result->get_error_message()
                );
                continue;
            }

            $status = isset( $result['status'] ) ? (string) $result['status'] : 'skipped';

            if ( isset( $summary[ $status ] ) && is_int( $summary[ $status ] ) ) {
                $summary[ $status ]++;
            } else {
                $summary['skipped']++;
            }
        }

        return $summary;
    }

    /**
     * Converte o thumbnail de uma marca específica.
     *
     * @return array{status:string,term_id:int,old_attachment_id?:int,new_attachment_id?:int}|WP_Error
     */
    public static function convert_term( int $term_id ) {
        if ( isset( self::$running[ $term_id ] ) ) {
            return array(
                'status'  => 'skipped',
                'term_id' => $term_id,
            );
        }

        $term = get_term( $term_id, 'product_brand' );

        if ( ! $term instanceof WP_Term ) {
            return new WP_Error( 'cv_brand_logo_term', 'Marca inválida.' );
        }

        $attachment_id = absint( get_term_meta( $term_id, 'thumbnail_id', true ) );

        if ( ! $attachment_id ) {
            return array(
                'status'  => 'skipped',
                'term_id' => $term_id,
            );
        }

        if ( ! self::attachment_is_avif( $attachment_id ) ) {
            return array(
                'status'  => 'skipped',
                'term_id' => $term_id,
            );
        }

        self::$running[ $term_id ] = true;

        try {
            $existing_id = self::find_existing_webp( $attachment_id );

            if ( $existing_id ) {
                self::update_term_thumbnail( $term_id, $attachment_id, $existing_id );

                return array(
                    'status'            => 'reused',
                    'term_id'           => $term_id,
                    'old_attachment_id' => $attachment_id,
                    'new_attachment_id' => $existing_id,
                );
            }

            $new_attachment_id = self::convert_attachment_to_webp( $attachment_id, $term );

            if ( is_wp_error( $new_attachment_id ) ) {
                return $new_attachment_id;
            }

            self::update_term_thumbnail( $term_id, $attachment_id, $new_attachment_id );

            return array(
                'status'            => 'converted',
                'term_id'           => $term_id,
                'old_attachment_id' => $attachment_id,
                'new_attachment_id' => $new_attachment_id,
            );
        } finally {
            unset( self::$running[ $term_id ] );
        }
    }

    private static function attachment_is_avif( int $attachment_id ): bool {
        $mime = strtolower( (string) get_post_mime_type( $attachment_id ) );

        if ( 'image/avif' === $mime ) {
            return true;
        }

        $url  = (string) wp_get_attachment_url( $attachment_id );
        $path = (string) wp_parse_url( $url, PHP_URL_PATH );

        return (bool) preg_match( '/\.avif$/i', $path );
    }

    private static function find_existing_webp( int $source_attachment_id ): int {
        $ids = get_posts(
            array(
                'post_type'      => 'attachment',
                'post_status'    => 'inherit',
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'meta_key'       => self::SOURCE_META,
                'meta_value'     => (string) $source_attachment_id,
                'orderby'        => 'ID',
                'order'          => 'DESC',
                'no_found_rows'  => true,
            )
        );

        if ( empty( $ids ) ) {
            return 0;
        }

        $candidate = absint( $ids[0] );

        return 'image/webp' === strtolower( (string) get_post_mime_type( $candidate ) )
            ? $candidate
            : 0;
    }

    /**
     * @return int|WP_Error
     */
    private static function convert_attachment_to_webp( int $attachment_id, WP_Term $term ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $source_url = (string) wp_get_attachment_url( $attachment_id );

        if ( '' === $source_url ) {
            return new WP_Error( 'cv_brand_logo_url', 'O attachment AVIF não tem URL.' );
        }

        $local_file = (string) get_attached_file( $attachment_id, true );
        $downloaded = false;

        if ( '' === $local_file || ! is_readable( $local_file ) ) {
            $local_file = download_url( $source_url, 30 );

            if ( is_wp_error( $local_file ) ) {
                return new WP_Error(
                    'cv_brand_logo_download',
                    'Não foi possível descarregar o AVIF: ' . $local_file->get_error_message()
                );
            }

            $downloaded = true;
        }

        $source_path = (string) wp_parse_url( $source_url, PHP_URL_PATH );
        $basename    = pathinfo( $source_path, PATHINFO_FILENAME );

        if ( '' === $basename ) {
            $basename = sanitize_title( $term->slug ?: $term->name );
        }

        $basename = sanitize_file_name( $basename );

        if ( '' === $basename ) {
            $basename = 'marca-' . $term->term_id;
        }

        $temp_dir  = get_temp_dir();
        $webp_name = wp_unique_filename( $temp_dir, $basename . '.webp' );
        $webp_path = trailingslashit( $temp_dir ) . $webp_name;

        $editor = wp_get_image_editor( $local_file );

        if ( is_wp_error( $editor ) ) {
            if ( $downloaded && is_file( $local_file ) ) {
                wp_delete_file( $local_file );
            }

            return new WP_Error(
                'cv_brand_logo_editor',
                'O servidor não conseguiu abrir o AVIF: ' . $editor->get_error_message()
            );
        }

        $editor->set_quality( 90 );
        $saved = $editor->save( $webp_path, 'image/webp' );

        if ( $downloaded && is_file( $local_file ) ) {
            wp_delete_file( $local_file );
        }

        if ( is_wp_error( $saved ) ) {
            if ( is_file( $webp_path ) ) {
                wp_delete_file( $webp_path );
            }

            return new WP_Error(
                'cv_brand_logo_save',
                'Não foi possível criar o WEBP: ' . $saved->get_error_message()
            );
        }

        $saved_path = isset( $saved['path'] ) ? (string) $saved['path'] : $webp_path;

        if ( ! is_file( $saved_path ) ) {
            return new WP_Error( 'cv_brand_logo_missing_webp', 'O ficheiro WEBP não foi criado.' );
        }

        $file_array = array(
            'name'     => $basename . '.webp',
            'tmp_name' => $saved_path,
        );

        $old_post = get_post( $attachment_id );
        $title    = $old_post instanceof WP_Post && '' !== trim( (string) $old_post->post_title )
            ? (string) $old_post->post_title
            : $term->name;

        $new_attachment_id = media_handle_sideload( $file_array, 0, $title );

        if ( is_wp_error( $new_attachment_id ) ) {
            if ( is_file( $saved_path ) ) {
                wp_delete_file( $saved_path );
            }

            return new WP_Error(
                'cv_brand_logo_media',
                'O WEBP foi criado mas não pôde ser registado na Biblioteca: ' . $new_attachment_id->get_error_message()
            );
        }

        $new_attachment_id = absint( $new_attachment_id );

        update_post_meta( $new_attachment_id, self::SOURCE_META, $attachment_id );

        $old_alt = (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
        update_post_meta(
            $new_attachment_id,
            '_wp_attachment_image_alt',
            '' !== trim( $old_alt ) ? $old_alt : $term->name
        );

        if ( $old_post instanceof WP_Post ) {
            wp_update_post(
                array(
                    'ID'           => $new_attachment_id,
                    'post_title'   => $title,
                    'post_excerpt' => (string) $old_post->post_excerpt,
                    'post_content' => (string) $old_post->post_content,
                )
            );
        }

        return $new_attachment_id;
    }

    private static function update_term_thumbnail( int $term_id, int $old_attachment_id, int $new_attachment_id ): void {
        if ( ! get_term_meta( $term_id, self::PREVIOUS_TERM_META, true ) ) {
            update_term_meta( $term_id, self::PREVIOUS_TERM_META, $old_attachment_id );
        }

        update_term_meta( $term_id, 'thumbnail_id', $new_attachment_id );
        update_term_meta( $term_id, self::CONVERTED_AT_META, gmdate( 'c' ) );

        clean_term_cache( $term_id, 'product_brand' );
    }
}
