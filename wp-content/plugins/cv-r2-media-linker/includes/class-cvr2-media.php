<?php
defined( 'ABSPATH' ) || exit;

final class CVR2_Media {
    public static function init(): void {
        add_filter( 'wp_get_attachment_url', array( __CLASS__, 'filter_attachment_url' ), 20, 2 );
        add_filter( 'image_downsize', array( __CLASS__, 'filter_image_downsize' ), 20, 3 );
        add_filter( 'wp_prepare_attachment_for_js', array( __CLASS__, 'filter_attachment_js' ), 20, 3 );

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
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $tmp = download_url( $source_url, 30 );

        if ( is_wp_error( $tmp ) ) {
            return $tmp;
        }

        $path = (string) wp_parse_url( $source_url, PHP_URL_PATH );
        $path = rawurldecode( $path );
        $path = preg_replace( '#^.*?/wp-content/uploads/#i', '', $path );
        $path = ltrim( (string) $path, '/' );

        $info      = pathinfo( $path );
        $directory = ! empty( $info['dirname'] ) && '.' !== $info['dirname'] ? trailingslashit( $info['dirname'] ) : '';
        $filename  = sanitize_file_name( (string) ( $info['filename'] ?? 'cvr2-image' ) );
        $extension = strtolower( (string) ( $info['extension'] ?? '' ) );

        if ( ! $extension ) {
            $detected_mime = function_exists( 'wp_get_image_mime' ) ? (string) wp_get_image_mime( $tmp ) : '';
            $extension = match ( $detected_mime ) {
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/gif'  => 'gif',
                'image/webp' => 'webp',
                'image/avif' => 'avif',
                default      => '',
            };
        }

        $key  = $directory . $filename . ( $extension ? '.' . $extension : '' );
        $mime = self::mime_for_key( $key );

        if ( ! $mime ) {
            @unlink( $tmp );
            return new WP_Error( 'cvr2_image_type', 'A imagem de origem não tem um formato suportado pela Media Library.' );
        }

        $uploads = wp_upload_dir();

        if ( ! empty( $uploads['error'] ) ) {
            @unlink( $tmp );
            return new WP_Error( 'cvr2_upload_dir', (string) $uploads['error'] );
        }

        $destination = trailingslashit( $uploads['basedir'] ) . ltrim( $key, '/' );

        if ( ! wp_mkdir_p( dirname( $destination ) ) ) {
            @unlink( $tmp );
            return new WP_Error( 'cvr2_mkdir', 'Não foi possível criar a pasta de destino da imagem.' );
        }

        if ( ! @rename( $tmp, $destination ) ) {
            if ( ! @copy( $tmp, $destination ) ) {
                @unlink( $tmp );
                return new WP_Error( 'cvr2_image_copy', 'Não foi possível guardar a imagem original.' );
            }
            @unlink( $tmp );
        }

        $attachment_id = wp_insert_attachment(
            array(
                'post_mime_type' => $mime,
                'post_title'     => $title ?: $filename,
                'post_status'    => 'inherit',
            ),
            $destination,
            0,
            true
        );

        if ( is_wp_error( $attachment_id ) ) {
            return $attachment_id;
        }

        update_post_meta( $attachment_id, '_wp_attached_file', $key );
        update_post_meta( $attachment_id, '_cvr2_source_url', $source_url );
        update_post_meta( $attachment_id, '_cvr2_r2_key', $key );
        update_post_meta( $attachment_id, '_cvr2_needs_r2_check', 1 );

        if ( $source_id ) {
            update_post_meta( $attachment_id, '_cvr2_source_attachment_id', $source_id );
        }

        if ( $alt ) {
            update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt );
        }

        $metadata = wp_generate_attachment_metadata( $attachment_id, $destination );

        if ( is_array( $metadata ) ) {
            wp_update_attachment_metadata( $attachment_id, $metadata );
        }

        return (int) $attachment_id;
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

    public static function filter_attachment_url( $url, int $post_id ) {
        $r2 = (string) get_post_meta( $post_id, '_cvr2_r2_url', true );
        return $r2 ?: $url;
    }

    public static function filter_image_downsize( $downsize, int $id, $size ) {
        $r2 = (string) get_post_meta( $id, '_cvr2_r2_url', true );

        if ( ! $r2 ) {
            return $downsize;
        }

        $meta   = wp_get_attachment_metadata( $id );
        $width  = is_array( $meta ) ? absint( $meta['width'] ?? 750 ) : 750;
        $height = is_array( $meta ) ? absint( $meta['height'] ?? 750 ) : 750;

        return array( $r2, $width ?: 750, $height ?: 750, false );
    }

    public static function filter_attachment_js( array $response, WP_Post $attachment, array $meta ): array {
        $r2 = (string) get_post_meta( $attachment->ID, '_cvr2_r2_url', true );

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
