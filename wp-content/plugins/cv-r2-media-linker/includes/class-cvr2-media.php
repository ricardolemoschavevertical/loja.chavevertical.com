<?php
defined( 'ABSPATH' ) || exit;

final class CVR2_Media {
    public static function init(): void {
        add_filter( 'wp_get_attachment_url', array( __CLASS__, 'filter_attachment_url' ), 20, 2 );
        add_filter( 'image_downsize', array( __CLASS__, 'filter_image_downsize' ), 20, 3 );
        add_filter( 'wp_prepare_attachment_for_js', array( __CLASS__, 'filter_attachment_js' ), 20, 3 );
        add_filter( 'wp_update_attachment_metadata', array( __CLASS__, 'capture_r2_after_metadata' ), 999, 2 );
        add_filter( 'wp_get_attachment_thumb_url', array( __CLASS__, 'filter_attachment_thumb_url' ), 99, 2 );
        add_filter( 'wp_calculate_image_srcset', array( __CLASS__, 'filter_remote_srcset' ), 99, 5 );
        add_filter( 'rest_prepare_attachment', array( __CLASS__, 'filter_rest_attachment' ), 99, 3 );
        add_filter( 'attachment_fields_to_edit', array( __CLASS__, 'remote_attachment_fields' ), 20, 2 );
        add_action( 'wp_ajax_image-editor', array( __CLASS__, 'guard_remote_pixel_editor' ), 0 );

        // Filtro R2 na Biblioteca Multimédia em modo lista.
        add_action( 'restrict_manage_posts', array( __CLASS__, 'render_r2_list_filter' ) );
        add_action( 'pre_get_posts', array( __CLASS__, 'apply_r2_list_filter' ) );

        // Filtro R2 no modo grelha e nos seletores de imagem/galeria dos produtos.
        add_filter( 'ajax_query_attachments_args', array( __CLASS__, 'apply_r2_ajax_filter' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_r2_media_filter' ) );
    }

    public static function attachment_for_source_image( array $image ) {
        $source_id  = absint( $image['id'] ?? 0 );
        $source_url = esc_url_raw( (string) ( $image['src'] ?? '' ) );
        $alt        = sanitize_text_field( (string) ( $image['alt'] ?? '' ) );
        $title      = sanitize_text_field( (string) ( $image['name'] ?? $alt ) );

        if ( $source_id ) {
            $existing = self::find_by_meta( '_cvr2_source_attachment_id', (string) $source_id );
            if ( ! $existing ) {
                $existing = self::find_by_meta( '_cv_source_attachment_id', (string) $source_id );
            }
            if ( $existing ) {
                self::refresh_attachment_text( $existing, $title, $alt );
                return $existing;
            }
        }

        if ( ! $source_url ) {
            return new WP_Error( 'cvr2_image_missing_url', 'Imagem de origem sem URL.' );
        }

        foreach ( self::r2_candidates( $source_url ) as $candidate ) {
            $existing = self::find_existing_r2_attachment( $candidate['key'], $candidate['url'] );

            if ( $existing ) {
                if ( $source_id ) {
                    update_post_meta( $existing, '_cvr2_source_attachment_id', $source_id );
                }
                self::refresh_attachment_text( $existing, $title, $alt );
                return $existing;
            }

            if ( self::remote_exists( $candidate['url'] ) ) {
                return self::register_virtual_attachment(
                    $candidate['key'],
                    $candidate['url'],
                    $source_id,
                    $title,
                    $alt
                );
            }
        }

        return self::import_source_original( $source_url, $source_id, $title, $alt );
    }

    private static function find_by_meta( string $key, string $value ): int {
        $ids = get_posts(
            array(
                'post_type'      => 'attachment',
                'post_status'    => 'inherit',
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'meta_key'       => $key,
                'meta_value'     => $value,
                'no_found_rows'  => true,
            )
        );

        return $ids ? absint( $ids[0] ) : 0;
    }

    private static function find_existing_r2_attachment( string $key, string $url ): int {
        foreach ( array( '_cvr2_r2_key', '_cv_r2_key', '_wp_attached_file' ) as $meta_key ) {
            $id = self::find_by_meta( $meta_key, $key );
            if ( $id ) {
                update_post_meta( $id, '_cvr2_r2_key', $key );
                update_post_meta( $id, '_cvr2_r2_url', $url );
                return $id;
            }
        }

        foreach ( array( '_cvr2_r2_url', '_cv_r2_url' ) as $meta_key ) {
            $id = self::find_by_meta( $meta_key, $url );
            if ( $id ) {
                update_post_meta( $id, '_cvr2_r2_key', $key );
                update_post_meta( $id, '_cvr2_r2_url', $url );
                return $id;
            }
        }

        global $wpdb;
        $id = absint(
            $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND guid = %s ORDER BY ID ASC LIMIT 1",
                    $url
                )
            )
        );

        if ( $id ) {
            update_post_meta( $id, '_cvr2_r2_key', $key );
            update_post_meta( $id, '_cvr2_r2_url', $url );
        }

        return $id;
    }

    private static function refresh_attachment_text( int $attachment_id, string $title, string $alt ): void {
        if ( $title ) {
            wp_update_post(
                array(
                    'ID'         => $attachment_id,
                    'post_title' => $title,
                )
            );
        }

        if ( $alt ) {
            update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt );
        }
    }

    private static function r2_candidates( string $source_url ): array {
        $path = (string) wp_parse_url( $source_url, PHP_URL_PATH );
        $path = rawurldecode( $path );
        $path = preg_replace( '#^.*?/wp-content/uploads/#i', '', $path );
        $path = ltrim( (string) $path, '/' );

        if ( '' === $path ) {
            return array();
        }

        $info      = pathinfo( $path );
        $directory = ! empty( $info['dirname'] ) && '.' !== $info['dirname'] ? trailingslashit( $info['dirname'] ) : '';
        $filename  = (string) ( $info['filename'] ?? '' );
        $extension = strtolower( (string) ( $info['extension'] ?? '' ) );

        if ( '' === $filename ) {
            return array();
        }

        $keys = array();

        // Se já existir uma versão WEBP convertida no R2, continua a ter prioridade.
        $keys[] = $directory . $filename . '.webp';

        // Caso contrário, aceita o formato original: JPG/JPEG/PNG/GIF/AVIF/etc.
        if ( $extension ) {
            $keys[] = $directory . $filename . '.' . $extension;
        }

        $keys = array_values( array_unique( $keys ) );
        $out  = array();

        foreach ( $keys as $key ) {
            $mime = self::mime_for_key( $key );

            if ( ! $mime ) {
                continue;
            }

            $out[] = array(
                'key'  => $key,
                'url'  => self::build_r2_url( $key ),
                'mime' => $mime,
            );
        }

        return $out;
    }

    private static function mime_for_key( string $key ): string {
        $filetype = wp_check_filetype( basename( $key ), null );
        $mime     = (string) ( $filetype['type'] ?? '' );

        if ( $mime && str_starts_with( $mime, 'image/' ) ) {
            return $mime;
        }

        $extension = strtolower( (string) pathinfo( $key, PATHINFO_EXTENSION ) );
        $fallbacks = array(
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'gif'  => 'image/gif',
            'webp' => 'image/webp',
            'avif' => 'image/avif',
            'bmp'  => 'image/bmp',
            'tif'  => 'image/tiff',
            'tiff' => 'image/tiff',
        );

        return (string) ( $fallbacks[ $extension ] ?? '' );
    }

    private static function build_r2_url( string $key ): string {
        $parts = array_filter( explode( '/', str_replace( '\\', '/', $key ) ), 'strlen' );
        return CVR2_REST_Client::r2_base_url() . '/' . implode( '/', array_map( 'rawurlencode', $parts ) );
    }

    private static function remote_exists( string $url ): bool {
        if ( ! $url ) {
            return false;
        }

        $response = wp_safe_remote_request(
            $url,
            array(
                'method'      => 'HEAD',
                'timeout'     => 8,
                'redirection' => 2,
                'headers'     => array( 'User-Agent' => 'CV-R2-Linker/' . CVR2_VERSION ),
            )
        );

        if ( ! is_wp_error( $response ) ) {
            $code = (int) wp_remote_retrieve_response_code( $response );
            if ( $code >= 200 && $code < 400 ) {
                return true;
            }
            if ( ! in_array( $code, array( 403, 405 ), true ) ) {
                return false;
            }
        }

        $fallback = wp_safe_remote_get(
            $url,
            array(
                'timeout'     => 8,
                'redirection' => 2,
                'headers'     => array(
                    'Range'      => 'bytes=0-0',
                    'User-Agent' => 'CV-R2-Linker/' . CVR2_VERSION,
                ),
            )
        );

        if ( is_wp_error( $fallback ) ) {
            return false;
        }

        $code = (int) wp_remote_retrieve_response_code( $fallback );
        return in_array( $code, array( 200, 206 ), true );
    }

    private static function register_virtual_attachment(
        string $key,
        string $url,
        int $source_id,
        string $title,
        string $alt
    ) {
        $mime = self::mime_for_key( $key );

        if ( ! $mime ) {
            return new WP_Error( 'cvr2_image_type', 'Formato de imagem não reconhecido: ' . basename( $key ) );
        }

        $attachment_id = wp_insert_attachment(
            array(
                'post_mime_type' => $mime,
                'post_title'     => $title ?: pathinfo( $key, PATHINFO_FILENAME ),
                'post_status'    => 'inherit',
                'guid'           => $url,
            ),
            false,
            0,
            true
        );

        if ( is_wp_error( $attachment_id ) ) {
            return $attachment_id;
        }

        update_post_meta( $attachment_id, '_wp_attached_file', $key );
        update_post_meta( $attachment_id, '_cvr2_r2_key', $key );
        update_post_meta( $attachment_id, '_cvr2_r2_url', $url );
        update_post_meta( $attachment_id, '_cvr2_virtual', 1 );

        if ( $source_id ) {
            update_post_meta( $attachment_id, '_cvr2_source_attachment_id', $source_id );
        }

        if ( $alt ) {
            update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt );
        }

        wp_update_attachment_metadata(
            $attachment_id,
            array(
                'width'  => 750,
                'height' => 750,
                'file'   => $key,
                'sizes'  => array(),
            )
        );

        return (int) $attachment_id;
    }

    private static function import_source_original(
        string $source_url,
        int $source_id,
        string $title,
        string $alt
    ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $tmp = download_url( $source_url, 30 );

        if ( is_wp_error( $tmp ) ) {
            return $tmp;
        }

        $path     = (string) wp_parse_url( $source_url, PHP_URL_PATH );
        $basename = sanitize_file_name( wp_basename( rawurldecode( $path ) ) );

        if ( '' === $basename || '.' === $basename ) {
            $basename = 'cvr2-image';
        }

        $extension = strtolower( (string) pathinfo( $basename, PATHINFO_EXTENSION ) );

        if ( ! $extension ) {
            $detected_mime = function_exists( 'wp_get_image_mime' ) ? (string) wp_get_image_mime( $tmp ) : '';
            $detected_ext  = self::extension_for_mime( $detected_mime );

            if ( $detected_ext ) {
                $basename .= '.' . $detected_ext;
            }
        }

        $file_array = array(
            'name'     => $basename,
            'tmp_name' => $tmp,
        );

        /*
         * IMPORTANTE:
         * Usar media_handle_sideload() faz a imagem passar pelo pipeline normal
         * da Media Library: wp_handle_sideload(), criação do attachment,
         * geração de metadata e todos os hooks do WordPress/plugins de offload.
         *
         * Assim, se a origem estiver no disco local do site antigo, S3, CDN ou
         * outro URL, o sistema R2 instalado no WordPress pode enviá-la para R2
         * exatamente pelo mesmo método usado num upload normal do administrador.
         */
        $attachment_id = media_handle_sideload(
            $file_array,
            0,
            $title ?: pathinfo( $basename, PATHINFO_FILENAME ),
            array(
                'post_status' => 'inherit',
            )
        );

        if ( is_wp_error( $attachment_id ) ) {
            @unlink( $tmp );
            return $attachment_id;
        }

        $attachment_id = absint( $attachment_id );

        update_post_meta( $attachment_id, '_cvr2_source_url', $source_url );
        update_post_meta( $attachment_id, '_cvr2_native_sideload', 1 );

        if ( $source_id ) {
            update_post_meta( $attachment_id, '_cvr2_source_attachment_id', $source_id );
        }

        if ( $alt ) {
            update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt );
        }

        self::capture_native_r2_location( $attachment_id );

        return $attachment_id;
    }

    private static function extension_for_mime( string $mime ): string {
        return match ( strtolower( $mime ) ) {
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
            'image/avif' => 'avif',
            default      => '',
        };
    }

    private static function capture_native_r2_location( int $attachment_id ): void {
        $url = wp_get_attachment_url( $attachment_id );

        if ( ! $url ) {
            update_post_meta( $attachment_id, '_cvr2_needs_r2_check', 1 );
            return;
        }

        $r2_base = CVR2_REST_Client::r2_base_url();
        $r2_host = strtolower( (string) wp_parse_url( $r2_base, PHP_URL_HOST ) );
        $url_host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );

        if ( $r2_host && $url_host && $r2_host === $url_host ) {
            $base_path = trim( (string) wp_parse_url( $r2_base, PHP_URL_PATH ), '/' );
            $url_path  = ltrim( rawurldecode( (string) wp_parse_url( $url, PHP_URL_PATH ) ), '/' );

            if ( $base_path && str_starts_with( $url_path, $base_path . '/' ) ) {
                $url_path = substr( $url_path, strlen( $base_path ) + 1 );
            }

            update_post_meta( $attachment_id, '_cvr2_r2_url', esc_url_raw( $url ) );
            update_post_meta( $attachment_id, '_cvr2_r2_key', $url_path );
            delete_post_meta( $attachment_id, '_cvr2_needs_r2_check' );
            return;
        }

        /*
         * Alguns plugins de offload executam de forma assíncrona ou apenas
         * alteram a URL depois de gerar os metadados. Neste caso mantemos o
         * attachment normal e marcamo-lo para verificação, sem copiar o ficheiro
         * diretamente para R2 por fora do WordPress.
         */
        update_post_meta( $attachment_id, '_cvr2_needs_r2_check', 1 );
    }

    private static function convert_to_webp( string $source, string $destination ) {
        if ( class_exists( 'Imagick' ) ) {
            try {
                $image = new Imagick( $source );
                if ( $image->getNumberImages() > 1 ) {
                    $image->setIteratorIndex( 0 );
                }

                $image->setImageBackgroundColor( 'white' );
                $image = $image->mergeImageLayers( Imagick::LAYERMETHOD_FLATTEN );
                $image->setImageColorspace( Imagick::COLORSPACE_SRGB );
                $image->thumbnailImage( 750, 750, true, true );

                $canvas = new Imagick();
                $canvas->newImage( 750, 750, new ImagickPixel( 'white' ), 'webp' );
                $x = (int) floor( ( 750 - $image->getImageWidth() ) / 2 );
                $y = (int) floor( ( 750 - $image->getImageHeight() ) / 2 );
                $canvas->compositeImage( $image, Imagick::COMPOSITE_OVER, $x, $y );
                $canvas->setImageFormat( 'webp' );
                $canvas->setImageCompressionQuality( 90 );
                $canvas->stripImage();
                $ok = $canvas->writeImage( $destination );

                $image->clear();
                $canvas->clear();

                return $ok ? true : new WP_Error( 'cvr2_webp_write', 'Falha ao gravar WEBP.' );
            } catch ( Throwable $e ) {
                return new WP_Error( 'cvr2_imagick', $e->getMessage() );
            }
        }

        if ( ! function_exists( 'imagecreatefromstring' ) || ! function_exists( 'imagewebp' ) ) {
            return new WP_Error( 'cvr2_no_image_engine', 'Servidor sem Imagick/GD com suporte WEBP.' );
        }

        $bytes = file_get_contents( $source );
        $src   = false !== $bytes ? @imagecreatefromstring( $bytes ) : false;

        if ( ! $src ) {
            return new WP_Error( 'cvr2_gd_open', 'GD não conseguiu abrir a imagem.' );
        }

        $src_w = imagesx( $src );
        $src_h = imagesy( $src );
        $scale = min( 750 / max( 1, $src_w ), 750 / max( 1, $src_h ) );
        $dst_w = max( 1, (int) round( $src_w * $scale ) );
        $dst_h = max( 1, (int) round( $src_h * $scale ) );
        $dst   = imagecreatetruecolor( 750, 750 );
        $white = imagecolorallocate( $dst, 255, 255, 255 );
        imagefill( $dst, 0, 0, $white );

        imagecopyresampled(
            $dst,
            $src,
            (int) floor( ( 750 - $dst_w ) / 2 ),
            (int) floor( ( 750 - $dst_h ) / 2 ),
            0,
            0,
            $dst_w,
            $dst_h,
            $src_w,
            $src_h
        );

        $ok = imagewebp( $dst, $destination, 90 );
        imagedestroy( $src );
        imagedestroy( $dst );

        return $ok ? true : new WP_Error( 'cvr2_gd_write', 'GD não conseguiu gravar WEBP.' );
    }

    private static function r2_meta_query( string $mode ): array {
        $r2_keys = array( '_cvr2_r2_url', '_cv_r2_url' );

        if ( 'r2' === $mode ) {
            return array(
                'relation' => 'OR',
                array(
                    'key'     => $r2_keys[0],
                    'value'   => '',
                    'compare' => '!=',
                ),
                array(
                    'key'     => $r2_keys[1],
                    'value'   => '',
                    'compare' => '!=',
                ),
            );
        }

        if ( 'local' === $mode ) {
            return array(
                'relation' => 'AND',
                array(
                    'relation' => 'OR',
                    array(
                        'key'     => $r2_keys[0],
                        'compare' => 'NOT EXISTS',
                    ),
                    array(
                        'key'     => $r2_keys[0],
                        'value'   => '',
                        'compare' => '=',
                    ),
                ),
                array(
                    'relation' => 'OR',
                    array(
                        'key'     => $r2_keys[1],
                        'compare' => 'NOT EXISTS',
                    ),
                    array(
                        'key'     => $r2_keys[1],
                        'value'   => '',
                        'compare' => '=',
                    ),
                ),
            );
        }

        return array();
    }

    private static function merge_meta_query( array $existing, array $r2_query ): array {
        if ( ! $r2_query ) {
            return $existing;
        }

        if ( ! $existing ) {
            return $r2_query;
        }

        return array(
            'relation' => 'AND',
            $existing,
            $r2_query,
        );
    }

    public static function render_r2_list_filter( string $post_type ): void {
        global $pagenow;

        if ( 'upload.php' !== $pagenow || 'attachment' !== $post_type ) {
            return;
        }

        $selected = isset( $_GET['cvr2_r2_filter'] )
            ? sanitize_key( wp_unslash( $_GET['cvr2_r2_filter'] ) )
            : '';

        ?>
        <label class="screen-reader-text" for="cvr2-r2-filter-list">Filtrar imagens por armazenamento</label>
        <select name="cvr2_r2_filter" id="cvr2-r2-filter-list">
            <option value="" <?php selected( $selected, '' ); ?>>Todas as imagens</option>
            <option value="r2" <?php selected( $selected, 'r2' ); ?>>R2</option>
            <option value="local" <?php selected( $selected, 'local' ); ?>>Não R2</option>
        </select>
        <?php
    }

    public static function apply_r2_list_filter( WP_Query $query ): void {
        global $pagenow;

        if (
            ! is_admin()
            || 'upload.php' !== $pagenow
            || ! $query->is_main_query()
            || 'attachment' !== $query->get( 'post_type' )
        ) {
            return;
        }

        $mode = isset( $_GET['cvr2_r2_filter'] )
            ? sanitize_key( wp_unslash( $_GET['cvr2_r2_filter'] ) )
            : '';

        if ( ! in_array( $mode, array( 'r2', 'local' ), true ) ) {
            return;
        }

        $existing = (array) $query->get( 'meta_query' );
        $query->set( 'meta_query', self::merge_meta_query( $existing, self::r2_meta_query( $mode ) ) );
    }

    public static function apply_r2_ajax_filter( array $query ): array {
        $mode = sanitize_key( (string) ( $query['cvr2_r2_filter'] ?? '' ) );
        unset( $query['cvr2_r2_filter'] );

        if ( ! in_array( $mode, array( 'r2', 'local' ), true ) ) {
            return $query;
        }

        $existing = isset( $query['meta_query'] ) && is_array( $query['meta_query'] )
            ? $query['meta_query']
            : array();

        $query['meta_query'] = self::merge_meta_query( $existing, self::r2_meta_query( $mode ) );

        return $query;
    }

    public static function enqueue_r2_media_filter( string $hook_suffix ): void {
        if ( ! is_admin() ) {
            return;
        }

        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

        $allowed = 'upload.php' === $hook_suffix
            || in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true )
            || ( $screen && in_array( (string) $screen->post_type, array( 'product', 'attachment' ), true ) );

        if ( ! $allowed ) {
            return;
        }

        wp_enqueue_media();

        $script = <<<'JS'
(function(wp){
    if (!wp || !wp.media || !wp.media.view || !wp.media.view.AttachmentsBrowser) {
        return;
    }

    var AttachmentFilters = wp.media.view.AttachmentFilters;

    var CVR2StorageFilter = AttachmentFilters.extend({
        id: 'cvr2-media-storage-filter',
        className: 'attachment-filters cvr2-media-storage-filter',

        createFilters: function() {
            this.filters = {
                all: {
                    text: 'Todas as imagens',
                    props: { cvr2_r2_filter: '' },
                    priority: 10
                },
                r2: {
                    text: 'R2',
                    props: { cvr2_r2_filter: 'r2' },
                    priority: 20
                },
                local: {
                    text: 'Não R2',
                    props: { cvr2_r2_filter: 'local' },
                    priority: 30
                }
            };
        }
    });

    var originalCreateToolbar = wp.media.view.AttachmentsBrowser.prototype.createToolbar;

    wp.media.view.AttachmentsBrowser.prototype.createToolbar = function() {
        originalCreateToolbar.apply(this, arguments);

        if (!this.collection || !this.collection.props || !this.toolbar) {
            return;
        }

        this.toolbar.set(
            'cvr2StorageFilter',
            new CVR2StorageFilter({
                controller: this.controller,
                model: this.collection.props,
                priority: -76
            }).render()
        );
    };
})(window.wp);
JS;

        wp_add_inline_script( 'media-views', $script, 'after' );
    }

    public static function capture_r2_after_metadata( array $data, int $attachment_id ): array {
        if ( $attachment_id && get_post_meta( $attachment_id, '_cvr2_native_sideload', true ) ) {
            self::capture_native_r2_location( $attachment_id );
        }

        return $data;
    }

    private static function attachment_r2_url( int $post_id ): string {
        $r2 = (string) get_post_meta( $post_id, '_cvr2_r2_url', true );
        if ( ! $r2 ) {
            $r2 = (string) get_post_meta( $post_id, '_cv_r2_url', true );
            if ( $r2 ) {
                update_post_meta( $post_id, '_cvr2_r2_url', $r2 );
                $legacy_key = (string) get_post_meta( $post_id, '_cv_r2_key', true );
                if ( $legacy_key && ! get_post_meta( $post_id, '_cvr2_r2_key', true ) ) {
                    update_post_meta( $post_id, '_cvr2_r2_key', $legacy_key );
                }
            }
        }
        return esc_url_raw( $r2 );
    }

    /**
     * WordPress filters can receive non-numeric placeholder image IDs (for example,
     * WooCommerce's sample order in the email preview). Never query R2 metadata
     * or raise a TypeError for these IDs; let WordPress handle them normally.
     */
    private static function normalize_attachment_id( $value ): int {
        if ( is_int( $value ) ) {
            return $value > 0 ? $value : 0;
        }

        if ( is_string( $value ) && preg_match( '/\A[0-9]+\z/', $value ) ) {
            return absint( $value );
        }

        return 0;
    }

    public static function filter_attachment_thumb_url( $url, $post_id ) {
        $attachment_id = self::normalize_attachment_id( $post_id );
        if ( ! $attachment_id ) {
            return $url;
        }

        $r2 = self::attachment_r2_url( $attachment_id );
        return $r2 ?: $url;
    }

    public static function filter_remote_srcset( $sources, $size_array, $image_src, $image_meta, $attachment_id ) {
        $id = self::normalize_attachment_id( $attachment_id );
        return $id && self::attachment_r2_url( $id ) ? false : $sources;
    }

    public static function filter_rest_attachment( $response, WP_Post $attachment, $request ) {
        $r2 = self::attachment_r2_url( (int) $attachment->ID );
        if ( ! $r2 || ! is_object( $response ) || ! method_exists( $response, 'get_data' ) ) {
            return $response;
        }

        $data = $response->get_data();
        if ( ! is_array( $data ) ) {
            return $response;
        }

        if ( array_key_exists( 'source_url', $data ) ) {
            $data['source_url'] = $r2;
        }

        if ( isset( $data['media_details'] ) && is_array( $data['media_details'] ) ) {
            $data['media_details']['sizes']['full']['source_url'] = $r2;
        }

        $response->set_data( $data );
        return $response;
    }

    public static function remote_attachment_fields( array $fields, WP_Post $post ): array {
        $r2 = self::attachment_r2_url( (int) $post->ID );
        if ( ! $r2 ) {
            return $fields;
        }

        $fields['cvr2_remote_info'] = array(
            'label' => 'Imagem R2',
            'input' => 'html',
            'html'  => '<p>Ficheiro remoto no R2. ALT, título, legenda e descrição continuam editáveis.</p><p><a target="_blank" rel="noopener noreferrer" href="' . esc_url( $r2 ) . '">Abrir imagem original no R2</a></p>',
        );

        return $fields;
    }

    public static function guard_remote_pixel_editor(): void {
        $id = isset( $_POST['postid'] ) ? absint( $_POST['postid'] ) : 0;
        if ( ! $id || ! self::attachment_r2_url( $id ) ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $id ) ) {
            wp_send_json_error( array( 'message' => 'Sem permissões para editar esta imagem.' ), 403 );
        }

        check_ajax_referer( 'image_editor-' . $id );
        wp_send_json_error(
            array(
                'message' => array(
                    'error' => 'Imagem R2 remota. A edição de píxeis/corte/rotação exige primeiro uma cópia local pelo fluxo normal da Media Library.',
                ),
            )
        );
    }

    public static function filter_attachment_url( $url, $post_id ) {
        $attachment_id = self::normalize_attachment_id( $post_id );
        if ( ! $attachment_id ) {
            return $url;
        }

        $r2 = self::attachment_r2_url( $attachment_id );
        return $r2 ?: $url;
    }

    public static function filter_image_downsize( $downsize, $id, $size ) {
        $attachment_id = self::normalize_attachment_id( $id );
        if ( ! $attachment_id ) {
            return $downsize;
        }

        $r2 = self::attachment_r2_url( $attachment_id );
        if ( ! $r2 ) {
            return $downsize;
        }

        $meta   = wp_get_attachment_metadata( $attachment_id );
        $width  = is_array( $meta ) ? absint( $meta['width'] ?? 750 ) : 750;
        $height = is_array( $meta ) ? absint( $meta['height'] ?? 750 ) : 750;

        return array( $r2, $width ?: 750, $height ?: 750, false );
    }

    public static function filter_attachment_js( array $response, WP_Post $attachment, array $meta ): array {
        $r2 = self::attachment_r2_url( (int) $attachment->ID );

        if ( ! $r2 ) {
            return $response;
        }

        $width  = absint( $meta['width'] ?? 750 ) ?: 750;
        $height = absint( $meta['height'] ?? 750 ) ?: 750;

        $response['url'] = $r2;
        $response['sizes']['full'] = array(
            'url'         => $r2,
            'width'       => $width,
            'height'      => $height,
            'orientation' => $height > $width ? 'portrait' : 'landscape',
        );

        return $response;
    }
}
