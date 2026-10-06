<?php
defined( 'ABSPATH' ) || exit;

final class CVR2_R2_Tools {
    private const OPTION = 'cv_r2_media_linker_settings';
    private const NONCE = 'cvr2_r2_tools';
    private const STATE_OPTION = 'cv_r2_media_linker_bulk_state';
    private const DB_VERSION_OPTION = 'cv_r2_media_linker_db_version';

    private static string $table = '';

    public static function init(): void {
        global $wpdb;
        self::$table = $wpdb->prefix . 'cv_r2_media_map';

        add_action( 'admin_init', array( __CLASS__, 'maybe_install' ) );
        add_action( 'admin_post_cvr2_r2_save_settings', array( __CLASS__, 'save_settings' ) );
        add_action( 'wp_ajax_cvr2_r2_test_connection', array( __CLASS__, 'ajax_test_connection' ) );
        add_action( 'wp_ajax_cvr2_r2_bulk_start', array( __CLASS__, 'ajax_bulk_start' ) );
        add_action( 'wp_ajax_cvr2_r2_bulk_process', array( __CLASS__, 'ajax_bulk_process' ) );
        add_action( 'wp_ajax_cvr2_r2_bulk_reset', array( __CLASS__, 'ajax_bulk_reset' ) );
        add_action( 'wp_ajax_cvr2_r2_diagnose', array( __CLASS__, 'ajax_diagnose' ) );
        add_filter( 'woocommerce_product_csv_importer_class', array( __CLASS__, 'filter_csv_importer_class' ), 20 );
    }

    public static function maybe_install(): void {
        if ( '2' === (string) get_option( self::DB_VERSION_OPTION, '' ) ) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $table = self::$table ?: $wpdb->prefix . 'cv_r2_media_map';

        dbDelta(
            "CREATE TABLE {$table} (
                object_hash char(64) NOT NULL,
                object_key text NOT NULL,
                basename varchar(255) NOT NULL,
                attachment_id bigint(20) unsigned NOT NULL,
                r2_url text NOT NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY (object_hash),
                KEY basename (basename),
                KEY attachment_id (attachment_id)
            ) {$charset};"
        );

        update_option( self::DB_VERSION_OPTION, '2', false );
    }

    private static function can_manage(): bool {
        return current_user_can( 'manage_woocommerce' ) || current_user_can( 'manage_options' );
    }

    private static function defaults(): array {
        return array(
            'account_id' => '',
            'access_key_id' => '',
            'secret_access_key' => '',
            'bucket' => '',
            'public_base_url' => CVR2_REST_Client::r2_base_url(),
            'prefix' => '',
        );
    }

    public static function settings(): array {
        $settings = wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );

        foreach (
            array(
                'account_id' => 'CV_R2_ACCOUNT_ID',
                'access_key_id' => 'CV_R2_ACCESS_KEY_ID',
                'secret_access_key' => 'CV_R2_SECRET_ACCESS_KEY',
                'bucket' => 'CV_R2_BUCKET',
                'public_base_url' => 'CV_R2_PUBLIC_BASE_URL',
                'prefix' => 'CV_R2_PREFIX',
            ) as $key => $constant
        ) {
            if ( defined( $constant ) && '' !== (string) constant( $constant ) ) {
                $settings[ $key ] = (string) constant( $constant );
            }
        }

        $settings['prefix'] = trim( str_replace( '\\', '/', (string) $settings['prefix'] ), '/' );
        if ( $settings['prefix'] ) {
            $settings['prefix'] .= '/';
        }

        return $settings;
    }

    public static function save_settings(): void {
        if ( ! self::can_manage() ) {
            wp_die( 'Sem permissões.' );
        }
        check_admin_referer( self::NONCE );

        $old = self::settings();
        $secret = trim( (string) wp_unslash( $_POST['secret_access_key'] ?? '' ) );

        $new = array(
            'account_id' => sanitize_text_field( (string) wp_unslash( $_POST['account_id'] ?? '' ) ),
            'access_key_id' => sanitize_text_field( (string) wp_unslash( $_POST['access_key_id'] ?? '' ) ),
            'secret_access_key' => $secret ? sanitize_text_field( $secret ) : (string) $old['secret_access_key'],
            'bucket' => sanitize_text_field( (string) wp_unslash( $_POST['bucket'] ?? '' ) ),
            'public_base_url' => untrailingslashit( esc_url_raw( (string) wp_unslash( $_POST['public_base_url'] ?? '' ) ) ),
            'prefix' => trim( sanitize_text_field( (string) wp_unslash( $_POST['prefix'] ?? '' ) ), '/' ),
        );

        update_option( self::OPTION, $new, false );

        $current = CVR2_REST_Client::settings();
        if ( $new['public_base_url'] ) {
            $current['r2_base_url'] = $new['public_base_url'];
            update_option( CVR2_OPTION, $current, false );
        }

        wp_safe_redirect(
            add_query_arg(
                array(
                    'page' => 'cv-r2-rest-import',
                    'tab' => 'r2',
                    'r2_saved' => 1,
                ),
                admin_url( 'admin.php' )
            )
        );
        exit;
    }

    private static function verify_ajax(): void {
        if ( ! self::can_manage() ) {
            wp_send_json_error( array( 'message' => 'Sem permissões.' ), 403 );
        }
        check_ajax_referer( self::NONCE, 'nonce' );
    }

    private static function validate_settings( array $settings ) {
        foreach (
            array(
                'account_id' => 'Cloudflare Account ID',
                'access_key_id' => 'R2 Access Key ID',
                'secret_access_key' => 'R2 Secret Access Key',
                'bucket' => 'Bucket',
                'public_base_url' => 'URL pública',
            ) as $key => $label
        ) {
            if ( empty( $settings[ $key ] ) ) {
                return new WP_Error( 'cvr2_r2_missing', 'Falta configurar: ' . $label );
            }
        }

        if ( ! preg_match( '/^[a-f0-9]{32}$/i', (string) $settings['account_id'] ) ) {
            return new WP_Error( 'cvr2_r2_account', 'Cloudflare Account ID inválido.' );
        }

        if ( ! wp_http_validate_url( (string) $settings['public_base_url'] ) ) {
            return new WP_Error( 'cvr2_r2_url', 'URL pública R2 inválida.' );
        }

        return true;
    }

    private static function signature_key( string $secret, string $date, string $region, string $service ): string {
        $k_date = hash_hmac( 'sha256', $date, 'AWS4' . $secret, true );
        $k_region = hash_hmac( 'sha256', $region, $k_date, true );
        $k_service = hash_hmac( 'sha256', $service, $k_region, true );
        return hash_hmac( 'sha256', 'aws4_request', $k_service, true );
    }

    private static function encode_path( string $path ): string {
        return '/' . implode( '/', array_map( 'rawurlencode', explode( '/', ltrim( $path, '/' ) ) ) );
    }

    private static function request( string $method, string $key = '', array $query = array(), int $timeout = 30 ) {
        $settings = self::settings();
        $valid = self::validate_settings( $settings );
        if ( is_wp_error( $valid ) ) {
            return $valid;
        }

        $method = strtoupper( $method );
        if ( ! in_array( $method, array( 'GET', 'HEAD' ), true ) ) {
            return new WP_Error( 'cvr2_r2_read_only', 'As ferramentas R2 operam o bucket apenas em leitura.' );
        }

        ksort( $query );
        $canonical_query = http_build_query( $query, '', '&', PHP_QUERY_RFC3986 );
        $host = $settings['account_id'] . '.r2.cloudflarestorage.com';
        $canonical_uri = self::encode_path( (string) $settings['bucket'] );
        if ( $key ) {
            $canonical_uri .= self::encode_path( $key );
        }

        $url = 'https://' . $host . $canonical_uri . ( $canonical_query ? '?' . $canonical_query : '' );
        $amz_date = gmdate( 'Ymd\THis\Z' );
        $date_stamp = gmdate( 'Ymd' );
        $payload_hash = hash( 'sha256', '' );
        $canonical_headers = 'host:' . $host . "\n" . 'x-amz-content-sha256:' . $payload_hash . "\n" . 'x-amz-date:' . $amz_date . "\n";
        $signed_headers = 'host;x-amz-content-sha256;x-amz-date';
        $canonical_request = $method . "\n" . $canonical_uri . "\n" . $canonical_query . "\n" . $canonical_headers . "\n" . $signed_headers . "\n" . $payload_hash;
        $scope = $date_stamp . '/auto/s3/aws4_request';
        $string_to_sign = "AWS4-HMAC-SHA256\n{$amz_date}\n{$scope}\n" . hash( 'sha256', $canonical_request );
        $signature = hash_hmac( 'sha256', $string_to_sign, self::signature_key( (string) $settings['secret_access_key'], $date_stamp, 'auto', 's3' ) );

        return wp_safe_remote_request(
            $url,
            array(
                'method' => $method,
                'timeout' => $timeout,
                'redirection' => 0,
                'headers' => array(
                    'Authorization' => 'AWS4-HMAC-SHA256 Credential=' . $settings['access_key_id'] . '/' . $scope . ', SignedHeaders=' . $signed_headers . ', Signature=' . $signature,
                    'x-amz-content-sha256' => $payload_hash,
                    'x-amz-date' => $amz_date,
                ),
            )
        );
    }

    private static function xml_value( string $xml, string $tag ): string {
        $tag = preg_quote( $tag, '/' );
        return preg_match( '/<' . $tag . '>(.*?)<\/' . $tag . '>/s', $xml, $m )
            ? html_entity_decode( trim( $m[1] ), ENT_QUOTES | ENT_XML1, 'UTF-8' )
            : '';
    }

    private static function list_objects( string $token = '', int $max_keys = 100 ): array|WP_Error {
        $settings = self::settings();
        $query = array(
            'list-type' => '2',
            'max-keys' => (string) max( 1, min( 1000, $max_keys ) ),
        );
        if ( $settings['prefix'] ) {
            $query['prefix'] = $settings['prefix'];
        }
        if ( $token ) {
            $query['continuation-token'] = $token;
        }

        $response = self::request( 'GET', '', $query, 30 );
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        $body = (string) wp_remote_retrieve_body( $response );
        if ( 200 !== $code ) {
            return new WP_Error( 'cvr2_r2_http', 'R2 respondeu HTTP ' . $code . '.' );
        }

        $objects = array();
        if ( preg_match_all( '/<Contents>(.*?)<\/Contents>/s', $body, $matches ) ) {
            foreach ( $matches[1] as $chunk ) {
                $key = self::xml_value( $chunk, 'Key' );
                if ( ! $key ) {
                    continue;
                }
                $objects[] = array(
                    'key' => $key,
                    'size' => (int) self::xml_value( $chunk, 'Size' ),
                    'etag' => trim( self::xml_value( $chunk, 'ETag' ), '"' ),
                );
            }
        }

        return array(
            'objects' => $objects,
            'is_truncated' => 'true' === strtolower( self::xml_value( $body, 'IsTruncated' ) ),
            'next_token' => self::xml_value( $body, 'NextContinuationToken' ),
        );
    }

    private static function is_image_key( string $key ): bool {
        return in_array( strtolower( pathinfo( wp_basename( $key ), PATHINFO_EXTENSION ) ), array( 'jpg','jpeg','png','gif','webp','avif','bmp','tif','tiff' ), true );
    }

    private static function is_generated_thumbnail( string $key ): bool {
        return (bool) preg_match( '/-\d+x\d+(?:-\d+)?\.(?:jpe?g|png|gif|webp|avif)$/i', wp_basename( $key ) );
    }

    private static function mime_from_key( string $key ): string {
        $ext = strtolower( pathinfo( wp_basename( $key ), PATHINFO_EXTENSION ) );
        return array(
            'jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif',
            'webp'=>'image/webp','avif'=>'image/avif','bmp'=>'image/bmp','tif'=>'image/tiff','tiff'=>'image/tiff',
        )[ $ext ] ?? '';
    }

    private static function public_url( string $key ): string {
        $settings = self::settings();
        return untrailingslashit( (string) $settings['public_base_url'] ) . '/' . implode( '/', array_map( 'rawurlencode', explode( '/', ltrim( $key, '/' ) ) ) );
    }

    private static function find_attachment( string $key ): int {
        foreach ( array( '_cvr2_r2_key', '_cv_r2_key', '_wp_attached_file' ) as $meta_key ) {
            $ids = get_posts(
                array(
                    'post_type'=>'attachment','post_status'=>'inherit','posts_per_page'=>1,'fields'=>'ids',
                    'meta_key'=>$meta_key,'meta_value'=>$key,'no_found_rows'=>true,
                )
            );
            if ( $ids ) {
                return absint( $ids[0] );
            }
        }
        return 0;
    }

    private static function upsert_map( string $key, int $attachment_id, string $url ): void {
        global $wpdb;
        $table = self::$table ?: $wpdb->prefix . 'cv_r2_media_map';
        $wpdb->replace(
            $table,
            array(
                'object_hash'=>hash( 'sha256', $key ),
                'object_key'=>$key,
                'basename'=>wp_basename( $key ),
                'attachment_id'=>$attachment_id,
                'r2_url'=>$url,
                'created_at'=>current_time( 'mysql', true ),
            ),
            array( '%s','%s','%s','%d','%s','%s' )
        );
    }

    private static function register_attachment( array $object ) {
        $key = (string) ( $object['key'] ?? '' );
        if ( ! $key || ! self::is_image_key( $key ) ) {
            return new WP_Error( 'cvr2_r2_not_image', 'O objeto não é uma imagem suportada.' );
        }

        $url = self::public_url( $key );
        $existing = self::find_attachment( $key );
        if ( $existing ) {
            update_post_meta( $existing, '_cvr2_r2_key', $key );
            update_post_meta( $existing, '_cvr2_r2_url', $url );
            update_post_meta( $existing, '_cv_r2_key', $key );
            update_post_meta( $existing, '_cv_r2_url', $url );
            self::upsert_map( $key, $existing, $url );
            return $existing;
        }

        $mime = self::mime_from_key( $key );
        if ( ! $mime ) {
            return new WP_Error( 'cvr2_r2_mime', 'Formato de imagem não suportado.' );
        }

        $attachment_id = wp_insert_attachment(
            array(
                'guid'=>$url,
                'post_mime_type'=>$mime,
                'post_title'=>sanitize_text_field( pathinfo( wp_basename( $key ), PATHINFO_FILENAME ) ),
                'post_status'=>'inherit',
            ),
            false,
            0,
            true
        );

        if ( is_wp_error( $attachment_id ) ) {
            return $attachment_id;
        }

        $attachment_id = absint( $attachment_id );
        update_post_meta( $attachment_id, '_wp_attached_file', $key );
        update_post_meta( $attachment_id, '_cvr2_r2_key', $key );
        update_post_meta( $attachment_id, '_cvr2_r2_url', $url );
        update_post_meta( $attachment_id, '_cv_r2_key', $key );
        update_post_meta( $attachment_id, '_cv_r2_url', $url );
        update_post_meta( $attachment_id, '_cvr2_virtual', 1 );
        update_post_meta( $attachment_id, '_cv_r2_filesize', absint( $object['size'] ?? 0 ) );
        update_post_meta(
            $attachment_id,
            '_wp_attachment_metadata',
            array(
                'file'=>$key,
                'sizes'=>array(),
                'filesize'=>absint( $object['size'] ?? 0 ),
            )
        );

        self::upsert_map( $key, $attachment_id, $url );
        return $attachment_id;
    }

    public static function ajax_test_connection(): void {
        self::verify_ajax();
        $result = self::list_objects( '', 1 );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message'=>$result->get_error_message() ), 400 );
        }
        $example = (string) ( $result['objects'][0]['key'] ?? '' );
        wp_send_json_success( array( 'message'=>'Ligação R2 OK.' . ( $example ? ' Exemplo: ' . $example : '' ) ) );
    }

    public static function ajax_bulk_start(): void {
        self::verify_ajax();
        $batch = max( 10, min( 500, absint( $_POST['batch_size'] ?? 100 ) ) );
        $state = array(
            'continuation_token'=>'','batch_size'=>$batch,'processed'=>0,'created'=>0,
            'existing'=>0,'skipped'=>0,'errors'=>0,'last_key'=>'','last_error'=>'','done'=>false,
        );
        update_option( self::STATE_OPTION, $state, false );
        wp_send_json_success( array( 'state'=>$state ) );
    }

    public static function ajax_bulk_reset(): void {
        self::verify_ajax();
        delete_option( self::STATE_OPTION );
        wp_send_json_success( array( 'message'=>'Estado limpo; attachments já criados foram mantidos.' ) );
    }

    public static function ajax_bulk_process(): void {
        self::verify_ajax();
        $state = (array) get_option( self::STATE_OPTION, array() );
        if ( ! $state ) {
            wp_send_json_error( array( 'message'=>'Inicie primeiro a importação do bucket.' ), 400 );
        }
        if ( ! empty( $state['done'] ) ) {
            wp_send_json_success( array( 'state'=>$state ) );
        }

        $page = self::list_objects(
            (string) ( $state['continuation_token'] ?? '' ),
            max( 10, min( 500, absint( $state['batch_size'] ?? 100 ) ) )
        );
        if ( is_wp_error( $page ) ) {
            wp_send_json_error( array( 'message'=>$page->get_error_message(), 'state'=>$state ), 502 );
        }

        foreach ( $page['objects'] as $object ) {
            $key = (string) ( $object['key'] ?? '' );
            $state['processed']++;
            $state['last_key'] = $key;

            if ( ! $key || ! self::is_image_key( $key ) || self::is_generated_thumbnail( $key ) ) {
                $state['skipped']++;
                continue;
            }

            if ( self::find_attachment( $key ) ) {
                $state['existing']++;
                continue;
            }

            $id = self::register_attachment( $object );
            if ( is_wp_error( $id ) ) {
                $state['errors']++;
                $state['last_error'] = $id->get_error_message();
            } else {
                $state['created']++;
            }
        }

        $state['continuation_token'] = ! empty( $page['is_truncated'] ) ? (string) $page['next_token'] : '';
        $state['done'] = empty( $page['is_truncated'] );
        update_option( self::STATE_OPTION, $state, false );
        wp_send_json_success( array( 'state'=>$state ) );
    }

    public static function ajax_diagnose(): void {
        self::verify_ajax();
        $sku = wc_clean( (string) wp_unslash( $_POST['sku'] ?? '' ) );
        if ( ! $sku ) {
            wp_send_json_error( array( 'message'=>'Indique um SKU.' ), 400 );
        }

        $product_id = wc_get_product_id_by_sku( $sku );
        $product = $product_id ? wc_get_product( $product_id ) : false;
        if ( ! $product ) {
            wp_send_json_error( array( 'message'=>'Produto não encontrado.' ), 404 );
        }

        $image_id = absint( $product->get_image_id( 'edit' ) );
        $gallery = array_values( array_filter( array_map( 'absint', $product->get_gallery_image_ids( 'edit' ) ) ) );
        $url = $image_id ? wp_get_attachment_url( $image_id ) : '';
        $r2 = $image_id ? (string) get_post_meta( $image_id, '_cvr2_r2_url', true ) : '';
        if ( ! $r2 && $image_id ) {
            $r2 = (string) get_post_meta( $image_id, '_cv_r2_url', true );
        }

        wp_send_json_success(
            array(
                'message'=>'Diagnóstico concluído.',
                'product_id'=>$product_id,
                'sku'=>$sku,
                'image_id'=>$image_id,
                'image_url'=>$url,
                'r2'=>boolval( $r2 ),
                'r2_url'=>$r2,
                'gallery_ids'=>$gallery,
                'gallery_count'=>count( $gallery ),
            )
        );
    }

    public static function filter_csv_importer_class( string $class ): string {
        if ( 'WC_Product_CSV_Importer' !== $class ) {
            return $class;
        }

        if ( ! class_exists( 'WC_Product_CSV_Importer', false ) && defined( 'WC_ABSPATH' ) ) {
            $core = WC_ABSPATH . 'includes/import/class-wc-product-csv-importer.php';
            if ( file_exists( $core ) ) {
                include_once $core;
            }
        }

        if ( class_exists( 'WC_Product_CSV_Importer', false ) && ! class_exists( 'CVR2_Product_CSV_Importer', false ) ) {
            require_once CVR2_DIR . 'includes/class-cvr2-product-csv-importer.php';
        }

        return class_exists( 'CVR2_Product_CSV_Importer', false ) ? 'CVR2_Product_CSV_Importer' : $class;
    }

    public static function render(): void {
        if ( ! self::can_manage() ) {
            wp_die( 'Sem permissões.' );
        }

        self::maybe_install();
        $settings = self::settings();
        $state = (array) get_option( self::STATE_OPTION, array() );
        $nonce = wp_create_nonce( self::NONCE );
        ?>
        <div class="cvr2-card">
            <h2>Ferramentas R2 recuperadas</h2>
            <p>Estas funções pertenciam ao plugin R2 anterior e foram integradas novamente sem repor a antiga limitação de apenas WEBP.</p>

            <?php if ( isset( $_GET['r2_saved'] ) ) : ?>
                <div class="notice notice-success inline"><p>Configuração R2 guardada.</p></div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="cvr2_r2_save_settings">
                <?php wp_nonce_field( self::NONCE ); ?>
                <div class="cvr2-row"><label><strong>Cloudflare Account ID</strong></label><input name="account_id" value="<?php echo esc_attr( (string) $settings['account_id'] ); ?>"></div>
                <div class="cvr2-row"><label><strong>R2 Access Key ID</strong></label><input name="access_key_id" value="<?php echo esc_attr( (string) $settings['access_key_id'] ); ?>"></div>
                <div class="cvr2-row"><label><strong>R2 Secret Access Key</strong></label><input type="password" name="secret_access_key" value="" autocomplete="new-password" placeholder="<?php echo ! empty( $settings['secret_access_key'] ) ? 'Configurada — deixar vazio para manter' : ''; ?>"></div>
                <div class="cvr2-row"><label><strong>Bucket</strong></label><input name="bucket" value="<?php echo esc_attr( (string) $settings['bucket'] ); ?>"></div>
                <div class="cvr2-row"><label><strong>URL pública R2</strong></label><input type="url" name="public_base_url" value="<?php echo esc_attr( (string) $settings['public_base_url'] ); ?>"></div>
                <div class="cvr2-row"><label><strong>Prefixo</strong></label><input name="prefix" value="<?php echo esc_attr( (string) $settings['prefix'] ); ?>" placeholder="wp-content/uploads/ ou vazio"></div>
                <p><button type="submit" class="button button-primary">Guardar configuração R2</button></p>
            </form>

            <div class="cvr2-actions">
                <button type="button" class="button" data-r2-action="test">Testar ligação R2</button>
            </div>
            <div class="cvr2-log" data-r2-log>Pronto.</div>
        </div>

        <div class="cvr2-card" style="margin-top:18px">
            <h2>Importar registos do R2 para a Biblioteca Multimédia</h2>
            <p>Cria apenas os attachments; não descarrega os objetos. Agora aceita <strong>WEBP, JPG, JPEG, PNG, GIF, AVIF, BMP e TIFF</strong>. Miniaturas antigas com sufixos de tamanho são ignoradas.</p>
            <div class="cvr2-actions">
                <label>Imagens por lote <input data-r2-batch type="number" min="10" max="500" value="<?php echo esc_attr( (string) max( 10, min( 500, absint( $state['batch_size'] ?? 100 ) ) ) ); ?>" style="width:90px"></label>
                <button type="button" class="button button-primary" data-r2-action="start">Iniciar / recomeçar</button>
                <button type="button" class="button" data-r2-action="resume">Retomar</button>
                <button type="button" class="button" data-r2-action="pause">Pausar</button>
                <button type="button" class="button" data-r2-action="reset">Limpar estado</button>
            </div>
            <div class="cvr2-log" data-r2-bulk-log>Sem importação ativa.</div>
        </div>

        <div class="cvr2-card" style="margin-top:18px">
            <h2>Diagnóstico de imagem por SKU</h2>
            <div class="cvr2-actions">
                <input type="text" data-r2-sku placeholder="SKU exato" style="min-width:260px">
                <button type="button" class="button" data-r2-action="diagnose">Ver imagem associada</button>
            </div>
            <div class="cvr2-log" data-r2-diagnose-log>Pronto.</div>
            <p><img data-r2-preview hidden alt="" style="max-width:100%;max-height:420px;object-fit:contain"></p>
        </div>

        <script>
        (() => {
            const nonce=<?php echo wp_json_encode( $nonce ); ?>;
            const log=document.querySelector('[data-r2-log]');
            const bulkLog=document.querySelector('[data-r2-bulk-log]');
            const diagLog=document.querySelector('[data-r2-diagnose-log]');
            const preview=document.querySelector('[data-r2-preview]');
            let running=false;

            async function call(action,params={}){
                const body=new URLSearchParams({action,nonce,...params});
                const response=await fetch(ajaxurl,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body:body.toString()});
                const json=await response.json();
                if(!json.success) throw new Error(json.data?.message||'Erro.');
                return json.data||{};
            }

            const renderState=(s)=>{
                if(!bulkLog)return;
                if(!s||!Object.keys(s).length){bulkLog.textContent='Sem importação ativa.';return;}
                bulkLog.textContent=[
                    'Estado: '+(s.done?'CONCLUÍDO':(running?'A IMPORTAR':'PAUSADO / PRONTO A RETOMAR')),
                    'Processados: '+Number(s.processed||0).toLocaleString('pt-PT'),
                    'Criados: '+Number(s.created||0).toLocaleString('pt-PT'),
                    'Já existentes: '+Number(s.existing||0).toLocaleString('pt-PT'),
                    'Ignorados: '+Number(s.skipped||0).toLocaleString('pt-PT'),
                    'Erros: '+Number(s.errors||0).toLocaleString('pt-PT'),
                    'Último: '+String(s.last_key||''),
                    s.last_error?'Último erro: '+s.last_error:''
                ].filter(Boolean).join('\n');
            };

            async function loop(){
                if(running)return;
                running=true;
                try{
                    while(running){
                        const data=await call('cvr2_r2_bulk_process');
                        renderState(data.state);
                        if(data.state?.done){running=false;break;}
                        await new Promise(r=>setTimeout(r,75));
                    }
                }catch(error){running=false;if(bulkLog)bulkLog.textContent=error.message||error;}
            }

            document.querySelector('[data-r2-action="test"]')?.addEventListener('click',async()=>{
                try{const d=await call('cvr2_r2_test_connection');if(log)log.textContent=d.message;}catch(e){if(log)log.textContent=e.message||e;}
            });
            document.querySelector('[data-r2-action="start"]')?.addEventListener('click',async()=>{
                try{const batch=document.querySelector('[data-r2-batch]')?.value||100;const d=await call('cvr2_r2_bulk_start',{batch_size:batch});renderState(d.state);loop();}catch(e){if(bulkLog)bulkLog.textContent=e.message||e;}
            });
            document.querySelector('[data-r2-action="resume"]')?.addEventListener('click',()=>loop());
            document.querySelector('[data-r2-action="pause"]')?.addEventListener('click',()=>{running=false;if(bulkLog)bulkLog.textContent+='\nPausado.';});
            document.querySelector('[data-r2-action="reset"]')?.addEventListener('click',async()=>{
                running=false;try{const d=await call('cvr2_r2_bulk_reset');if(bulkLog)bulkLog.textContent=d.message;}catch(e){if(bulkLog)bulkLog.textContent=e.message||e;}
            });
            document.querySelector('[data-r2-action="diagnose"]')?.addEventListener('click',async()=>{
                const sku=document.querySelector('[data-r2-sku]')?.value||'';
                try{
                    const d=await call('cvr2_r2_diagnose',{sku});
                    if(diagLog)diagLog.textContent=[
                        'Produto #'+d.product_id,
                        'SKU: '+d.sku,
                        'Imagem principal: '+(d.image_id||'sem imagem'),
                        'R2: '+(d.r2?'SIM':'NÃO'),
                        'Galeria: '+d.gallery_count,
                        'URL: '+(d.image_url||'')
                    ].join('\n');
                    if(preview&&d.image_url){preview.src=d.image_url;preview.hidden=false;}
                }catch(e){if(diagLog)diagLog.textContent=e.message||e;if(preview)preview.hidden=true;}
            });

            renderState(<?php echo wp_json_encode( $state ); ?>);
        })();
        </script>
        <?php
    }
}
