<?php
defined( 'ABSPATH' ) || exit;

final class CVLOA_Customer_Admin {
    public static function init(): void {
        add_action( 'admin_menu', array( __CLASS__, 'menu' ), 100 );
        add_action( 'admin_post_cvloa_export_local_customers', array( __CLASS__, 'export_local_customers' ) );
        add_action( 'admin_post_cvloa_import_local_customers', array( __CLASS__, 'import_local_customers' ) );

        add_action( 'wp_ajax_cvloa_customer_test_source', array( __CLASS__, 'ajax_test_source' ) );
        add_action( 'wp_ajax_cvloa_customer_start_import', array( __CLASS__, 'ajax_start_import' ) );
        add_action( 'wp_ajax_cvloa_customer_import_batch', array( __CLASS__, 'ajax_import_batch' ) );
        add_action( 'wp_ajax_cvloa_customer_import_status', array( __CLASS__, 'ajax_import_status' ) );
        add_action( 'wp_ajax_cvloa_customer_reset_import', array( __CLASS__, 'ajax_reset_import' ) );
        add_action( 'wp_ajax_cvloa_rebuild_customer_identities', array( __CLASS__, 'ajax_rebuild_identities' ) );
    }

    public static function menu(): void {
        add_submenu_page(
            'woocommerce',
            'Clientes antigos',
            'Clientes antigos',
            'manage_woocommerce',
            'cv-legacy-customers',
            array( __CLASS__, 'render' )
        );
    }



    public static function export_local_customers(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'Sem permissões.', 'cv-legacy-orders-archive' ) );
        }

        check_admin_referer( 'cvloa_export_local_customers' );

        $ready = CVLOA_Customer_Archive::ensure_storage();
        if ( is_wp_error( $ready ) ) {
            wp_die( esc_html( $ready->get_error_message() ) );
        }

        $path = CVLOA_Customer_Archive::data_path();
        if ( ! is_readable( $path ) ) {
            wp_die( esc_html__( 'O ficheiro local dos clientes não está disponível para leitura.', 'cv-legacy-orders-archive' ) );
        }

        $handle = @fopen( $path, 'rb' );
        if ( false === $handle ) {
            wp_die( esc_html__( 'Não foi possível abrir o ficheiro local dos clientes.', 'cv-legacy-orders-archive' ) );
        }

        $meta = wp_json_encode(
            array(
                '_cvloa_export' => array(
                    'format'         => 'cvloa-ndjson',
                    'version'        => 1,
                    'type'           => 'customers',
                    'exported_at'    => gmdate( 'c' ),
                    'plugin_version' => defined( 'CVLOA_VERSION' ) ? CVLOA_VERSION : '',
                ),
            ),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );

        if ( false === $meta ) {
            fclose( $handle );
            wp_die( esc_html__( 'Não foi possível preparar a exportação dos clientes.', 'cv-legacy-orders-archive' ) );
        }

        while ( ob_get_level() > 0 ) {
            @ob_end_clean();
        }

        nocache_headers();
        header( 'Content-Type: application/x-ndjson; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="cv-clientes-antigos-' . gmdate( 'Ymd-His' ) . '.ndjson"' );
        header( 'X-Content-Type-Options: nosniff' );

        echo $meta . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

        $first_line = true;
        while ( ! feof( $handle ) ) {
            $line = fgets( $handle );
            if ( false === $line ) {
                break;
            }

            if ( $first_line ) {
                $first_line = false;
                if ( str_starts_with( ltrim( $line ), '<?php' ) ) {
                    continue;
                }
            }

            if ( '' === trim( $line ) ) {
                continue;
            }

            echo $line; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }

        fclose( $handle );
        exit;
    }

    public static function import_local_customers(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'Sem permissões.', 'cv-legacy-orders-archive' ) );
        }

        check_admin_referer( 'cvloa_import_local_customers' );

        $file = isset( $_FILES['cvloa_customers_file'] ) && is_array( $_FILES['cvloa_customers_file'] )
            ? $_FILES['cvloa_customers_file']
            : array();

        $upload_error = absint( $file['error'] ?? UPLOAD_ERR_NO_FILE );
        if ( UPLOAD_ERR_OK !== $upload_error ) {
            self::redirect_file_notice( 'error', 'Selecione um ficheiro de clientes válido para importar.' );
        }

        $tmp_path = (string) ( $file['tmp_name'] ?? '' );
        if ( '' === $tmp_path || ! is_readable( $tmp_path ) ) {
            self::redirect_file_notice( 'error', 'Não foi possível ler o ficheiro carregado.' );
        }

        $max_size = (int) wp_max_upload_size();
        $file_size = (int) ( $file['size'] ?? 0 );
        if ( $max_size > 0 && $file_size > $max_size ) {
            self::redirect_file_notice( 'error', 'O ficheiro excede o limite de upload permitido pelo WordPress.' );
        }

        $result = self::import_customers_file( $tmp_path );
        if ( is_wp_error( $result ) ) {
            self::redirect_file_notice( 'error', $result->get_error_message() );
        }

        $message = sprintf(
            'Ficheiro importado: %1$d registo(s) lido(s), %2$d novo(s), %3$d atualizado(s).',
            absint( $result['records'] ?? 0 ),
            absint( $result['archived'] ?? 0 ),
            absint( $result['updated'] ?? 0 )
        );

        $errors = absint( $result['errors_count'] ?? 0 );
        if ( $errors ) {
            $message .= sprintf( ' %d linha(s) foram ignoradas por erro.', $errors );
        }

        self::redirect_file_notice( $errors ? 'warning' : 'success', $message );
    }

    private static function import_customers_file( string $path ) {
        $handle = @fopen( $path, 'rb' );
        if ( false === $handle ) {
            return new WP_Error( 'cvloa_customers_import_open_failed', 'Não foi possível abrir o ficheiro carregado.' );
        }

        $summary = array(
            'records'      => 0,
            'archived'     => 0,
            'updated'      => 0,
            'ignored'      => 0,
            'errors_count' => 0,
            'errors'       => array(),
        );
        $batch   = array();
        $line_no = 0;

        try {
            while ( ! feof( $handle ) ) {
                $line = fgets( $handle );
                if ( false === $line ) {
                    break;
                }

                $line_no++;
                $line = trim( $line );

                if ( '' === $line || str_starts_with( $line, '<?php' ) ) {
                    continue;
                }

                $decoded = json_decode( $line, true );
                if ( ! is_array( $decoded ) ) {
                    $summary['errors_count']++;
                    if ( count( $summary['errors'] ) < 20 ) {
                        $summary['errors'][] = 'Linha ' . $line_no . ': JSON inválido.';
                    }
                    continue;
                }

                if ( isset( $decoded['_cvloa_export'] ) && is_array( $decoded['_cvloa_export'] ) ) {
                    $type = sanitize_key( (string) ( $decoded['_cvloa_export']['type'] ?? '' ) );
                    if ( '' !== $type && 'customers' !== $type ) {
                        return new WP_Error( 'cvloa_customers_import_wrong_type', 'O ficheiro selecionado não é uma exportação de clientes.' );
                    }
                    continue;
                }

                if (
                    array_key_exists( 'line_items', $decoded )
                    || (
                        ! array_key_exists( 'email', $decoded )
                        && ! array_key_exists( 'orders_count', $decoded )
                        && ! array_key_exists( 'billing', $decoded )
                    )
                ) {
                    $summary['errors_count']++;
                    if ( count( $summary['errors'] ) < 20 ) {
                        $summary['errors'][] = 'Linha ' . $line_no . ': o registo não parece ser um cliente válido.';
                    }
                    continue;
                }

                $batch[] = $decoded;
                $summary['records']++;

                if ( count( $batch ) >= 100 ) {
                    $saved = CVLOA_Customer_Archive::archive_batch( $batch, true );
                    if ( is_wp_error( $saved ) ) {
                        return $saved;
                    }

                    foreach ( array( 'archived', 'updated', 'ignored' ) as $key ) {
                        $summary[ $key ] += absint( $saved[ $key ] ?? 0 );
                    }
                    foreach ( (array) ( $saved['errors'] ?? array() ) as $error ) {
                        $summary['errors_count']++;
                        if ( count( $summary['errors'] ) < 20 ) {
                            $summary['errors'][] = sanitize_text_field( (string) $error );
                        }
                    }
                    $batch = array();
                }
            }

            if ( $batch ) {
                $saved = CVLOA_Customer_Archive::archive_batch( $batch, true );
                if ( is_wp_error( $saved ) ) {
                    return $saved;
                }

                foreach ( array( 'archived', 'updated', 'ignored' ) as $key ) {
                    $summary[ $key ] += absint( $saved[ $key ] ?? 0 );
                }
                foreach ( (array) ( $saved['errors'] ?? array() ) as $error ) {
                    $summary['errors_count']++;
                    if ( count( $summary['errors'] ) < 20 ) {
                        $summary['errors'][] = sanitize_text_field( (string) $error );
                    }
                }
            }
        } finally {
            fclose( $handle );
        }

        if ( 0 === $summary['records'] ) {
            return new WP_Error( 'cvloa_customers_import_empty', 'O ficheiro não contém clientes válidos para importar.' );
        }

        return $summary;
    }

    private static function redirect_file_notice( string $type, string $message ): void {
        $type = in_array( $type, array( 'success', 'warning', 'error' ), true ) ? $type : 'warning';

        wp_safe_redirect(
            add_query_arg(
                array(
                    'page'                        => 'cv-legacy-customers',
                    'cvloa_customer_file_notice'  => $type,
                    'cvloa_customer_file_message' => $message,
                ),
                admin_url( 'admin.php' )
            )
        );
        exit;
    }

    private static function guard_ajax(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => 'Sem permissões.' ), 403 );
        }

        check_ajax_referer( 'cvloa_customer_import', 'nonce' );
    }

    public static function ajax_test_source(): void {
        self::guard_ajax();

        $result = CVLOA_REST_Client::request(
            'customers',
            array(
                'per_page' => 1,
                'page'     => 1,
                'orderby'  => 'id',
                'order'    => 'asc',
                'role'     => 'customer',
            ),
            30
        );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success(
            array(
                'message' => sprintf(
                    'Ligação REST válida. A origem reporta %s cliente(s).',
                    number_format_i18n( absint( $result['total'] ?? 0 ) )
                ),
            )
        );
    }

    public static function ajax_start_import(): void {
        self::guard_ajax();

        $mode = sanitize_key( (string) wp_unslash( $_POST['import_mode'] ?? 'ignore_existing' ) );
        if ( ! in_array( $mode, array( 'ignore_existing', 'update_existing' ), true ) ) {
            $mode = 'ignore_existing';
        }

        $batch_size = absint( wp_unslash( $_POST['batch_size'] ?? 50 ) );
        $batch_size = max( 1, min( 100, $batch_size ) );

        $state = array(
            'status'       => 'running',
            'page'         => 1,
            'total_pages'  => 0,
            'total'        => 0,
            'processed'    => 0,
            'archived'     => 0,
            'updated'      => 0,
            'ignored'      => 0,
            'errors_count' => 0,
            'errors'       => array(),
            'batch_size'   => $batch_size,
            'import_mode'  => $mode,
            'run_id'       => wp_generate_uuid4(),
            'started_at'   => time(),
            'updated_at'   => time(),
        );

        update_option( CVLOA_CUSTOMER_STATE_OPTION, $state, false );

        wp_send_json_success(
            array(
                'state'   => $state,
                'message' => sprintf(
                    'Importação de clientes iniciada — lote de %d cliente(s).',
                    $batch_size
                ),
            )
        );
    }

    public static function ajax_import_status(): void {
        self::guard_ajax();

        $state          = (array) get_option( CVLOA_CUSTOMER_STATE_OPTION, array() );
        $request_run_id = sanitize_text_field( (string) wp_unslash( $_POST['run_id'] ?? '' ) );

        if (
            $request_run_id
            && ! empty( $state['run_id'] )
            && ! hash_equals( (string) $state['run_id'], $request_run_id )
        ) {
            wp_send_json_error(
                array(
                    'message' => 'Esta execução foi substituída por uma importação de clientes mais recente.',
                    'state'   => $state,
                ),
                409
            );
        }

        wp_send_json_success(
            array(
                'state' => $state,
                'done'  => ! empty( $state ) && 'done' === ( $state['status'] ?? '' ),
            )
        );
    }

    public static function ajax_reset_import(): void {
        self::guard_ajax();
        delete_option( CVLOA_CUSTOMER_STATE_OPTION );
        wp_send_json_success( array( 'message' => 'Estado da importação de clientes limpo.' ) );
    }

    public static function ajax_rebuild_identities(): void {
        self::guard_ajax();
        @set_time_limit( 300 );

        $result = CVLOA_Customer_Identities::rebuild();

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        $stats      = (array) ( $result['stats'] ?? array() );
        $guest_sync = (array) ( $result['guest_sync'] ?? array() );

        wp_send_json_success(
            array(
                'stats' => CVLOA_Customer_Identities::stats(),
                'message' => sprintf(
                    'Identidades reconstruídas: %1$s perfis; %2$s perfis com encomendas de convidado; %3$s encomendas analisadas. Clientes convidados no arquivo: %4$s.',
                    number_format_i18n( absint( $stats['profiles'] ?? 0 ) ),
                    number_format_i18n( absint( $stats['guest_profiles'] ?? 0 ) ),
                    number_format_i18n( absint( $stats['orders_scanned'] ?? 0 ) ),
                    number_format_i18n( absint( $guest_sync['archived'] ?? 0 ) + absint( $guest_sync['updated'] ?? 0 ) )
                ),
            )
        );
    }

    public static function ajax_import_batch(): void {
        self::guard_ajax();

        $state = (array) get_option( CVLOA_CUSTOMER_STATE_OPTION, array() );

        if ( empty( $state ) ) {
            wp_send_json_error( array( 'message' => 'Não existe uma importação de clientes ativa.' ) );
        }

        if ( 'done' === ( $state['status'] ?? '' ) ) {
            wp_send_json_success(
                array(
                    'done'  => true,
                    'state' => $state,
                )
            );
        }

        $request_run_id = sanitize_text_field( (string) wp_unslash( $_POST['run_id'] ?? '' ) );
        $state_run_id   = sanitize_text_field( (string) ( $state['run_id'] ?? '' ) );

        if ( ! $request_run_id || ! hash_equals( $state_run_id, $request_run_id ) ) {
            wp_send_json_error(
                array(
                    'message' => 'Execução antiga bloqueada. Retome a importação de clientes ativa.',
                    'state'   => $state,
                ),
                409
            );
        }

        $lock_key   = 'cvloa_customer_batch_lock_' . md5( $state_run_id );
        $lock_token = wp_generate_uuid4();

        if ( get_transient( $lock_key ) ) {
            wp_send_json_success(
                array(
                    'done'    => false,
                    'busy'    => true,
                    'state'   => $state,
                    'message' => 'Já existe um lote de clientes em processamento.',
                )
            );
        }

        set_transient( $lock_key, $lock_token, 180 );
        register_shutdown_function(
            static function () use ( $lock_key, $lock_token ): void {
                if ( get_transient( $lock_key ) === $lock_token ) {
                    delete_transient( $lock_key );
                }
            }
        );

        @set_time_limit( 180 );

        $page       = max( 1, absint( $state['page'] ?? 1 ) );
        $batch_size = max( 1, min( 100, absint( $state['batch_size'] ?? 50 ) ) );

        $result = CVLOA_REST_Client::request(
            'customers',
            array(
                'per_page' => $batch_size,
                'page'     => $page,
                'orderby'  => 'id',
                'order'    => 'asc',
                'role'     => 'customer',
            ),
            75
        );

        if ( is_wp_error( $result ) ) {
            $state['status']       = 'error';
            $state['errors_count'] = absint( $state['errors_count'] ?? 0 ) + 1;
            $state['errors'][]     = $result->get_error_message();
            $state['errors']       = array_slice( $state['errors'], -20 );
            $state['updated_at']   = time();
            update_option( CVLOA_CUSTOMER_STATE_OPTION, $state, false );

            wp_send_json_error(
                array(
                    'message' => $result->get_error_message(),
                    'state'   => $state,
                )
            );
        }

        $customers   = array_values( (array) $result['data'] );
        $source_url  = CVLOA_REST_Client::source_url();
        $source_hash = substr( sha1( strtolower( untrailingslashit( $source_url ) ) ), 0, 10 );

        foreach ( $customers as &$customer ) {
            $customer = (array) $customer;
            $id       = absint( $customer['id'] ?? 0 );

            if ( ! $id ) {
                continue;
            }

            $customer['_cvloa_origin']      = 'remote';
            $customer['_cvloa_source_url']  = $source_url;
            $customer['_cvloa_archive_key'] = 'remote-' . $source_hash . '-' . $id;
        }
        unset( $customer );

        $archive = CVLOA_Customer_Archive::archive_batch(
            $customers,
            'update_existing' === ( $state['import_mode'] ?? 'ignore_existing' )
        );

        if ( is_wp_error( $archive ) ) {
            $state['status']       = 'error';
            $state['errors_count'] = absint( $state['errors_count'] ?? 0 ) + 1;
            $state['errors'][]     = $archive->get_error_message();
            $state['errors']       = array_slice( $state['errors'], -20 );
            $state['updated_at']   = time();
            update_option( CVLOA_CUSTOMER_STATE_OPTION, $state, false );

            wp_send_json_error(
                array(
                    'message' => $archive->get_error_message(),
                    'state'   => $state,
                )
            );
        }

        $state['total']        = (int) $result['total'];
        $state['total_pages']  = max( 1, (int) $result['total_pages'] );
        $state['processed']    = absint( $state['processed'] ?? 0 ) + count( $customers );
        $state['archived']     = absint( $state['archived'] ?? 0 ) + absint( $archive['archived'] ?? 0 );
        $state['updated']      = absint( $state['updated'] ?? 0 ) + absint( $archive['updated'] ?? 0 );
        $state['ignored']      = absint( $state['ignored'] ?? 0 ) + absint( $archive['ignored'] ?? 0 );
        $batch_errors          = (array) ( $archive['errors'] ?? array() );
        $state['errors_count'] = absint( $state['errors_count'] ?? 0 ) + count( $batch_errors );
        $state['errors']       = array_slice(
            array_merge( (array) ( $state['errors'] ?? array() ), $batch_errors ),
            -20
        );
        $state['updated_at'] = time();

        if ( $page >= (int) $state['total_pages'] || empty( $customers ) ) {
            $state['status']      = 'done';
            $state['finished_at'] = time();
        } else {
            $state['page'] = $page + 1;
        }

        update_option( CVLOA_CUSTOMER_STATE_OPTION, $state, false );

        wp_send_json_success(
            array(
                'done'    => 'done' === ( $state['status'] ?? '' ),
                'state'   => $state,
                'message' => sprintf(
                    'Verificados %1$s / %2$s clientes — arquivados: %3$s; atualizados: %4$s; ignorados: %5$s.',
                    number_format_i18n( (int) $state['processed'] ),
                    number_format_i18n( (int) $state['total'] ),
                    number_format_i18n( (int) $state['archived'] ),
                    number_format_i18n( (int) $state['updated'] ),
                    number_format_i18n( (int) $state['ignored'] )
                ),
            )
        );
    }

    public static function render(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'Sem permissões.', 'cv-legacy-orders-archive' ) );
        }

        $view_key = sanitize_key( (string) wp_unslash( $_GET['view_customer'] ?? '' ) );
        if ( $view_key ) {
            self::render_customer_detail( $view_key );
            return;
        }

        $index = CVLOA_Customer_Archive::load_index();
        if ( ! empty( $index['_error'] ) ) {
            echo '<div class="wrap"><h1>Clientes antigos</h1><div class="notice notice-error"><p>' . esc_html( (string) $index['_error'] ) . '</p></div></div>';
            return;
        }

        $customers = array_values( (array) ( $index['customers'] ?? array() ) );
        $search    = trim( (string) wp_unslash( $_GET['s'] ?? '' ) );
        $needle    = strtolower( remove_accents( $search ) );

        if ( '' !== $needle ) {
            $customers = array_values(
                array_filter(
                    $customers,
                    static fn( array $row ): bool => str_contains( (string) ( $row['search'] ?? '' ), $needle )
                )
            );
        }

        usort(
            $customers,
            static function ( array $a, array $b ): int {
                $an = (string) ( $a['name'] ?? '' );
                $bn = (string) ( $b['name'] ?? '' );
                $cmp = strnatcasecmp( $an, $bn );
                return 0 !== $cmp ? $cmp : ( absint( $a['id'] ?? 0 ) <=> absint( $b['id'] ?? 0 ) );
            }
        );

        $per_page = 50;
        $paged    = max( 1, absint( $_GET['paged'] ?? 1 ) );
        $total    = count( $customers );
        $pages    = max( 1, (int) ceil( $total / $per_page ) );
        $paged    = min( $paged, $pages );
        $slice    = array_slice( $customers, ( $paged - 1 ) * $per_page, $per_page );
        $archived_order_counts = CVLOA_Archive::email_order_counts();

        $stats          = CVLOA_Customer_Archive::stats();
        $identity_stats = CVLOA_Customer_Identities::stats();
        $state          = (array) get_option( CVLOA_CUSTOMER_STATE_OPTION, array() );
        $nonce = wp_create_nonce( 'cvloa_customer_import' );
        $file_notice = sanitize_key( (string) wp_unslash( $_GET['cvloa_customer_file_notice'] ?? '' ) );
        $file_message = sanitize_text_field( (string) wp_unslash( $_GET['cvloa_customer_file_message'] ?? '' ) );
        ?>
        <div class="wrap cvloa-customer-admin">
            <h1>Clientes antigos</h1>
            <p>Arquivo privado dos clientes da loja de origem. Não copia palavras-passe. Um cliente histórico sem conta local pode ter a conta WordPress preparada na primeira tentativa de login por email e depois definir uma nova palavra-passe pelo fluxo normal de recuperação.</p>

            <?php if ( $file_message && in_array( $file_notice, array( 'success', 'warning', 'error' ), true ) ) : ?>
                <div class="notice notice-<?php echo esc_attr( $file_notice ); ?> is-dismissible"><p><?php echo esc_html( $file_message ); ?></p></div>
            <?php endif; ?>

            <style>
                .cvloa-customer-admin{max-width:1600px}.cvloa-card{background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:18px;margin:16px 0}
                .cvloa-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px}.cvloa-stat{padding:14px;border-radius:8px;background:#f6f7f7}.cvloa-stat span{display:block;color:#646970;font-size:12px}.cvloa-stat strong{display:block;margin-top:4px;font-size:20px}
                .cvloa-actions{display:flex;gap:8px;flex-wrap:wrap;margin:14px 0}.cvloa-progress{height:14px;border-radius:999px;background:#e5e5e5;overflow:hidden}.cvloa-progress span{display:block;height:100%;width:0;background:#2271b1;transition:width .2s}
                .cvloa-table{width:100%;border-collapse:collapse;background:#fff}.cvloa-table th,.cvloa-table td{padding:10px;border-bottom:1px solid #eee;text-align:left;vertical-align:top}.cvloa-table th{background:#f6f7f7}
                .cvloa-toolbar{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin:14px 0}.cvloa-toolbar input[type=search]{min-width:340px}.cvloa-muted{color:#646970}.cvloa-log{padding:10px 12px;background:#f6f7f7;border:1px solid #dcdcde;min-height:42px;white-space:pre-wrap}
                .cvloa-path{word-break:break-all;font-family:monospace}.cvloa-detail-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.cvloa-detail-card{padding:16px;border:1px solid #dcdcde;border-radius:9px;background:#fff}
                @media(max-width:800px){.cvloa-detail-grid{grid-template-columns:1fr}.cvloa-toolbar input[type=search]{min-width:0;width:100%}}
            </style>

            <p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=cv-legacy-orders' ) ); ?>">← Encomendas antigas</a></p>

            <div class="cvloa-grid">
                <div class="cvloa-stat"><span>Clientes arquivados</span><strong><?php echo esc_html( number_format_i18n( $stats['count'] ) ); ?></strong></div>
                <div class="cvloa-stat"><span>Ficheiro de dados</span><strong><?php echo esc_html( size_format( $stats['data_bytes'], 1 ) ); ?></strong></div>
                <div class="cvloa-stat"><span>Índice local</span><strong><?php echo esc_html( size_format( $stats['index_bytes'], 1 ) ); ?></strong></div>
            </div>

            <div class="cvloa-card">
                <h2>Ficheiro local de clientes</h2>
                <p>Exporte uma cópia portátil do arquivo local ou importe uma cópia anterior. O índice é reconstruído automaticamente a partir dos registos importados.</p>

                <div class="cvloa-actions">
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <input type="hidden" name="action" value="cvloa_export_local_customers">
                        <?php wp_nonce_field( 'cvloa_export_local_customers' ); ?>
                        <button class="button" type="submit">Exportar clientes</button>
                    </form>

                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                        <input type="hidden" name="action" value="cvloa_import_local_customers">
                        <?php wp_nonce_field( 'cvloa_import_local_customers' ); ?>
                        <input type="file" name="cvloa_customers_file" accept=".ndjson,.json,.php,application/json,application/x-ndjson,text/plain" required>
                        <button class="button button-primary" type="submit">Importar ficheiro</button>
                    </form>
                </div>

                <p class="description">A importação mescla o ficheiro com o arquivo atual: clientes com a mesma chave são atualizados e os restantes são mantidos.</p>
                <p class="description">Dados: <span class="cvloa-path"><?php echo esc_html( CVLOA_Customer_Archive::data_path() ); ?></span> — <?php echo esc_html( size_format( $stats['data_bytes'], 1 ) ); ?></p>
                <p class="description">Índice: <span class="cvloa-path"><?php echo esc_html( CVLOA_Customer_Archive::index_path() ); ?></span> — <?php echo esc_html( size_format( $stats['index_bytes'], 1 ) ); ?></p>
            </div>

            <div class="cvloa-card">
                <h2>Importar dados de clientes</h2>
                <p>Importa os clientes registados da origem através de <code>wc/v3/customers</code>. Guarda faturação, envio, email, telefone, empresa, metadados e restantes campos devolvidos pela API.</p>

                <p>
                    <label><input type="radio" name="cvloa_customer_mode" value="ignore_existing" checked> <strong>Ignorar clientes já arquivados</strong></label><br>
                    <label><input type="radio" name="cvloa_customer_mode" value="update_existing"> <strong>Atualizar clientes já arquivados</strong></label>
                </p>

                <p>
                    <label for="cvloa-customer-batch"><strong>Quantidade por lote:</strong></label>
                    <input id="cvloa-customer-batch" type="number" min="1" max="100" step="1" value="<?php echo esc_attr( (string) max( 1, min( 100, absint( $state['batch_size'] ?? 50 ) ) ) ); ?>">
                </p>

                <div class="cvloa-actions">
                    <button class="button" type="button" data-customer-action="test">Testar origem</button>
                    <button class="button button-primary" type="button" data-customer-action="start">Iniciar / recomeçar</button>
                    <button class="button" type="button" data-customer-action="resume">Retomar</button>
                    <button class="button" type="button" data-customer-action="pause">Pausar</button>
                    <button class="button" type="button" data-customer-action="reset">Limpar estado</button>
                </div>

                <p><strong data-customer-progress-text>0 / 0 clientes</strong> <span class="cvloa-muted" data-customer-progress-percent>0%</span></p>
                <div class="cvloa-progress"><span data-customer-progress></span></div>

                <div class="cvloa-grid" style="margin-top:12px">
                    <div class="cvloa-stat"><span>Verificados</span><strong data-customer-stat="processed">0</strong></div>
                    <div class="cvloa-stat"><span>Total</span><strong data-customer-stat="total">0</strong></div>
                    <div class="cvloa-stat"><span>Arquivados</span><strong data-customer-stat="archived">0</strong></div>
                    <div class="cvloa-stat"><span>Atualizados</span><strong data-customer-stat="updated">0</strong></div>
                    <div class="cvloa-stat"><span>Ignorados</span><strong data-customer-stat="ignored">0</strong></div>
                    <div class="cvloa-stat"><span>Erros</span><strong data-customer-stat="errors_count">0</strong></div>
                </div>

                <h3>Estado</h3>
                <div class="cvloa-log" data-customer-log>Pronto.</div>

                <p class="description">Dados: <span class="cvloa-path"><?php echo esc_html( CVLOA_Customer_Archive::data_path() ); ?></span> — <?php echo esc_html( size_format( $stats['data_bytes'], 1 ) ); ?></p>
                <p class="description">Índice: <span class="cvloa-path"><?php echo esc_html( CVLOA_Customer_Archive::index_path() ); ?></span> — <?php echo esc_html( size_format( $stats['index_bytes'], 1 ) ); ?></p>
            </div>

            <div class="cvloa-card">
                <h2>Clientes convidados e deduplicação</h2>
                <p>Analisa as encomendas arquivadas e agrupa identidades por <strong>email</strong>, <strong>NIF/NIPC</strong> e <strong>telefone</strong>. Os compradores convidados deduplicados passam também a aparecer em “Clientes antigos”.</p>

                <div class="cvloa-grid">
                    <div class="cvloa-stat"><span>Perfis deduplicados</span><strong data-identity-stat="profiles"><?php echo esc_html( number_format_i18n( $identity_stats['profiles'] ) ); ?></strong></div>
                    <div class="cvloa-stat"><span>Perfis com compras de convidado</span><strong data-identity-stat="guest_profiles"><?php echo esc_html( number_format_i18n( $identity_stats['guest_profiles'] ) ); ?></strong></div>
                    <div class="cvloa-stat"><span>Encomendas convidado</span><strong data-identity-stat="guest_orders"><?php echo esc_html( number_format_i18n( $identity_stats['guest_orders'] ) ); ?></strong></div>
                    <div class="cvloa-stat"><span>Encomendas analisadas</span><strong data-identity-stat="orders_scanned"><?php echo esc_html( number_format_i18n( $identity_stats['orders_scanned'] ) ); ?></strong></div>
                </div>

                <div class="cvloa-actions">
                    <button class="button button-primary" type="button" data-customer-action="identities">Reconstruir clientes convidados</button>
                </div>

                <p>
                    <strong>Estado:</strong>
                    <span data-identity-dirty><?php echo ! empty( $identity_stats['dirty'] ) ? 'Precisa de reconstrução' : 'Atualizado'; ?></span>
                </p>
                <div class="cvloa-log" data-identity-log><?php echo ! empty( $identity_stats['dirty'] ) ? 'Foram alterados clientes ou encomendas desde a última reconstrução.' : 'Índice de identidades pronto.'; ?></div>
                <p class="description">Índice deduplicado: <span class="cvloa-path"><?php echo esc_html( CVLOA_Customer_Identities::path() ); ?></span> — <?php echo esc_html( size_format( $identity_stats['bytes'], 1 ) ); ?></p>
            </div>

            <form class="cvloa-toolbar" method="get">
                <input type="hidden" name="page" value="cv-legacy-customers">
                <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Nome, empresa, email, telefone, ID, cidade ou código postal">
                <button class="button">Pesquisar</button>
                <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=cv-legacy-customers' ) ); ?>">Limpar</a>
                <span class="cvloa-muted"><?php echo esc_html( number_format_i18n( $total ) ); ?> resultado(s)</span>
            </form>

            <div class="cvloa-card" style="padding:0;overflow:auto">
                <table class="cvloa-table">
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>Email</th>
                            <th>Telefone</th>
                            <th>Empresa</th>
                            <th>Localidade</th>
                            <th>Encomendas</th>
                            <th>Total gasto</th>
                            <th>Registo</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ( ! $slice ) : ?>
                        <tr><td colspan="8">Não foram encontrados clientes no arquivo.</td></tr>
                    <?php else : ?>
                        <?php foreach ( $slice as $row ) : ?>
                            <?php
                            $detail_url = add_query_arg(
                                array(
                                    'page'          => 'cv-legacy-customers',
                                    'view_customer' => sanitize_key( (string) ( $row['archive_key'] ?? '' ) ),
                                ),
                                admin_url( 'admin.php' )
                            );
                            ?>
                            <tr>
                                <td><a href="<?php echo esc_url( $detail_url ); ?>"><strong><?php echo esc_html( (string) ( $row['name'] ?: '#' . $row['id'] ) ); ?></strong></a><br><span class="cvloa-muted">ID origem <?php echo esc_html( (string) $row['id'] ); ?></span></td>
                                <td><?php echo esc_html( (string) ( $row['email'] ?? '' ) ); ?></td>
                                <td><?php echo esc_html( (string) ( $row['phone'] ?? '' ) ); ?></td>
                                <td><?php echo esc_html( (string) ( $row['company'] ?? '' ) ); ?></td>
                                <td><?php echo esc_html( trim( (string) ( $row['billing_postcode'] ?? '' ) . ' ' . (string) ( $row['billing_city'] ?? '' ) ) ); ?></td>
                                <?php $row_email = strtolower( sanitize_email( (string) ( $row['email'] ?? '' ) ) ); ?>
                                <td>
                                    <strong><?php echo esc_html( number_format_i18n( absint( $archived_order_counts[ $row_email ] ?? 0 ) ) ); ?></strong>
                                    <br><span class="cvloa-muted"><?php echo esc_html( number_format_i18n( absint( $row['orders_count'] ?? 0 ) ) ); ?> na origem</span>
                                </td>
                                <td><?php echo wp_kses_post( wc_price( (float) ( $row['total_spent'] ?? 0 ) ) ); ?></td>
                                <td><?php echo esc_html( self::format_date( (string) ( $row['date_created'] ?? '' ) ) ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php
            if ( $pages > 1 ) {
                $pagination_base = add_query_arg(
                    array(
                        'page'  => 'cv-legacy-customers',
                        's'     => $search,
                        'paged' => '%#%',
                    ),
                    admin_url( 'admin.php' )
                );
                $pagination_base = str_replace( rawurlencode( '%#%' ), '%#%', $pagination_base );

                $pagination = paginate_links(
                    array(
                        'base'      => $pagination_base,
                        'format'    => '',
                        'current'   => $paged,
                        'total'     => $pages,
                        'type'      => 'list',
                        'prev_text' => '‹',
                        'next_text' => '›',
                    )
                );

                if ( $pagination ) {
                    echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post( $pagination ) . '</div></div>';
                }
            }
            ?>

            <script>
            (() => {
                const nonce = <?php echo wp_json_encode( $nonce ); ?>;
                const buttons = document.querySelectorAll('[data-customer-action]');
                const modeInputs = document.querySelectorAll('input[name="cvloa_customer_mode"]');
                const batchInput = document.querySelector('#cvloa-customer-batch');
                const log = document.querySelector('[data-customer-log]');
                const progress = document.querySelector('[data-customer-progress]');
                const progressText = document.querySelector('[data-customer-progress-text]');
                const progressPercent = document.querySelector('[data-customer-progress-percent]');
                const identityLog = document.querySelector('[data-identity-log]');
                const identityDirty = document.querySelector('[data-identity-dirty]');
                let running = false;
                let activeRunId = '';
                let statusTimer = null;

                const write = (message) => { if (log) log.textContent = String(message || ''); };

                const setBusy = (busy) => {
                    buttons.forEach((button) => {
                        if (button.dataset.customerAction === 'pause') {
                            button.disabled = !running;
                        } else {
                            button.disabled = busy;
                        }
                    });
                    modeInputs.forEach((input) => input.disabled = busy);
                    if (batchInput) batchInput.disabled = busy;
                };

                function renderState(state) {
                    state = state || {};
                    if (state.run_id) activeRunId = String(state.run_id);

                    ['processed','total','archived','updated','ignored','errors_count'].forEach((key) => {
                        const el = document.querySelector('[data-customer-stat="' + key + '"]');
                        if (el) el.textContent = Number(state[key] || 0).toLocaleString('pt-PT');
                    });

                    const total = Number(state.total || 0);
                    const processed = Number(state.processed || 0);
                    const percent = total > 0 ? Math.min(100, (processed / total) * 100) : 0;
                    if (progress) progress.style.width = percent + '%';
                    if (progressText) progressText.textContent = processed.toLocaleString('pt-PT') + ' / ' + total.toLocaleString('pt-PT') + ' clientes';
                    if (progressPercent) progressPercent.textContent = percent.toLocaleString('pt-PT', {maximumFractionDigits:1}) + '%';

                    if (state.import_mode) {
                        modeInputs.forEach((input) => input.checked = input.value === state.import_mode);
                    }
                    if (batchInput && state.batch_size) batchInput.value = String(state.batch_size);
                }

                async function call(action, params = {}) {
                    const body = new URLSearchParams({action, nonce, ...params});
                    const response = await fetch(ajaxurl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},
                        body: body.toString()
                    });
                    const json = await response.json();
                    if (!json.success) throw new Error(json.data?.message || 'Erro no pedido.');
                    return json.data || {};
                }

                async function refreshStatus() {
                    if (!running) return;
                    try {
                        const data = await call('cvloa_customer_import_status', activeRunId ? {run_id: activeRunId} : {});
                        renderState(data.state);
                    } catch (error) {}
                }

                function startPolling() {
                    stopPolling();
                    statusTimer = window.setInterval(refreshStatus, 1000);
                }

                function stopPolling() {
                    if (statusTimer) window.clearInterval(statusTimer);
                    statusTimer = null;
                }

                async function loop() {
                    if (running || !activeRunId) return;
                    running = true;
                    setBusy(true);
                    startPolling();

                    try {
                        while (running) {
                            const data = await call('cvloa_customer_import_batch', {run_id: activeRunId});
                            renderState(data.state);
                            write(data.message || 'A processar…');

                            if (data.busy) {
                                await new Promise((resolve) => window.setTimeout(resolve, 700));
                                continue;
                            }

                            if (data.done) {
                                running = false;
                                write(data.message || 'Importação de clientes concluída.');
                                break;
                            }

                            await new Promise((resolve) => window.setTimeout(resolve, 100));
                        }
                    } catch (error) {
                        running = false;
                        write(error.message || error);
                    } finally {
                        stopPolling();
                        setBusy(false);
                    }
                }

                document.querySelector('[data-customer-action="test"]')?.addEventListener('click', async () => {
                    setBusy(true);
                    try {
                        const data = await call('cvloa_customer_test_source');
                        write(data.message);
                    } catch (error) {
                        write(error.message || error);
                    } finally {
                        setBusy(false);
                    }
                });

                document.querySelector('[data-customer-action="start"]')?.addEventListener('click', async () => {
                    setBusy(true);
                    try {
                        const mode = document.querySelector('input[name="cvloa_customer_mode"]:checked')?.value || 'ignore_existing';
                        const batchSize = Math.max(1, Math.min(100, Number(batchInput?.value || 50)));
                        const data = await call('cvloa_customer_start_import', {
                            import_mode: mode,
                            batch_size: batchSize
                        });
                        activeRunId = String(data.state?.run_id || '');
                        renderState(data.state);
                        write(data.message);
                        setBusy(false);
                        loop();
                    } catch (error) {
                        write(error.message || error);
                        setBusy(false);
                    }
                });

                document.querySelector('[data-customer-action="resume"]')?.addEventListener('click', async () => {
                    setBusy(true);
                    try {
                        const data = await call('cvloa_customer_import_status');
                        activeRunId = String(data.state?.run_id || '');
                        renderState(data.state);
                        if (!activeRunId || data.done) {
                            write(data.done ? 'A importação guardada já está concluída.' : 'Não existe uma importação de clientes para retomar.');
                            setBusy(false);
                            return;
                        }
                        setBusy(false);
                        loop();
                    } catch (error) {
                        write(error.message || error);
                        setBusy(false);
                    }
                });

                document.querySelector('[data-customer-action="pause"]')?.addEventListener('click', () => {
                    running = false;
                    stopPolling();
                    setBusy(false);
                    write('Importação de clientes pausada no browser. O estado ficou guardado para retoma.');
                });

                document.querySelector('[data-customer-action="identities"]')?.addEventListener('click', async () => {
                    setBusy(true);
                    if (identityLog) identityLog.textContent = 'A reconstruir perfis de clientes convidados…';

                    try {
                        const data = await call('cvloa_rebuild_customer_identities');
                        const stats = data.stats || {};

                        ['profiles','guest_profiles','guest_orders','orders_scanned'].forEach((key) => {
                            const el = document.querySelector('[data-identity-stat="' + key + '"]');
                            if (el) el.textContent = Number(stats[key] || 0).toLocaleString('pt-PT');
                        });

                        if (identityDirty) identityDirty.textContent = 'Atualizado';
                        if (identityLog) identityLog.textContent = data.message || 'Índice de identidades reconstruído.';
                    } catch (error) {
                        if (identityLog) identityLog.textContent = error.message || error;
                    } finally {
                        setBusy(false);
                    }
                });

                document.querySelector('[data-customer-action="reset"]')?.addEventListener('click', async () => {
                    running = false;
                    stopPolling();
                    setBusy(true);
                    try {
                        const data = await call('cvloa_customer_reset_import');
                        activeRunId = '';
                        renderState({});
                        write(data.message);
                    } catch (error) {
                        write(error.message || error);
                    } finally {
                        setBusy(false);
                    }
                });

                renderState(<?php echo wp_json_encode( $state, JSON_UNESCAPED_UNICODE ); ?>);
            })();
            </script>
        </div>
        <?php
    }

    private static function render_customer_detail( string $customer_key ): void {
        $customer = CVLOA_Customer_Archive::read_customer( $customer_key );

        if ( ! $customer ) {
            echo '<div class="wrap"><h1>Clientes antigos</h1><div class="notice notice-error"><p>Não foi possível encontrar este cliente no arquivo local.</p></div></div>';
            return;
        }

        $billing      = (array) ( $customer['billing'] ?? array() );
        $shipping     = (array) ( $customer['shipping'] ?? array() );
        $name         = trim( (string) ( $customer['first_name'] ?? '' ) . ' ' . (string) ( $customer['last_name'] ?? '' ) );
        $email        = sanitize_email( (string) ( $customer['email'] ?? $billing['email'] ?? '' ) );
        $linked_orders = CVLOA_Archive::find_by_billing_email( $email );
        $local_user   = '' !== $email ? get_user_by( 'email', $email ) : false;
        ?>
        <div class="wrap cvloa-customer-admin">
            <style>
                .cvloa-card{background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:18px;margin:16px 0}.cvloa-detail-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.cvloa-detail-card{padding:16px;border:1px solid #dcdcde;border-radius:9px;background:#fff}.cvloa-table{width:100%;border-collapse:collapse;background:#fff}.cvloa-table th,.cvloa-table td{padding:10px;border-bottom:1px solid #eee;text-align:left;vertical-align:top}.cvloa-table th{background:#f6f7f7}.cvloa-muted{color:#646970}@media(max-width:800px){.cvloa-detail-grid{grid-template-columns:1fr}}
            </style>
            <h1>Cliente antigo</h1>
            <p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=cv-legacy-customers' ) ); ?>">← Voltar aos clientes</a></p>

            <div class="cvloa-card">
                <h2><?php echo esc_html( $name ?: (string) ( $customer['email'] ?? 'Cliente #' . absint( $customer['id'] ?? 0 ) ) ); ?></h2>
                <p class="cvloa-muted">ID de origem: <?php echo esc_html( (string) absint( $customer['id'] ?? 0 ) ); ?> · Utilizador: <?php echo esc_html( (string) ( $customer['username'] ?? '' ) ); ?></p>
                <p><strong>Email:</strong> <?php echo esc_html( $email ); ?><br><strong>Perfil:</strong> <?php echo esc_html( (string) ( $customer['role'] ?? '' ) ); ?><br><strong>Registado:</strong> <?php echo esc_html( self::format_date( (string) ( $customer['date_created'] ?? '' ) ) ); ?></p>
                <p><strong>Acesso nesta loja:</strong>
                    <?php if ( $local_user instanceof WP_User ) : ?>
                        <span style="color:#008a20;font-weight:700">Conta local existente</span>
                        — <a href="<?php echo esc_url( get_edit_user_link( $local_user->ID ) ); ?>">utilizador #<?php echo esc_html( (string) $local_user->ID ); ?></a>
                    <?php else : ?>
                        <span style="color:#996800;font-weight:700">Apenas arquivado — sem conta local de login</span>
                    <?php endif; ?>
                </p>
            </div>

            <div class="cvloa-detail-grid">
                <div class="cvloa-detail-card">
                    <h3>Faturação</h3>
                    <?php self::render_address( $billing ); ?>
                    <?php if ( ! empty( $billing['email'] ) ) : ?><p><strong>Email:</strong> <?php echo esc_html( (string) $billing['email'] ); ?></p><?php endif; ?>
                    <?php if ( ! empty( $billing['phone'] ) ) : ?><p><strong>Telefone:</strong> <?php echo esc_html( (string) $billing['phone'] ); ?></p><?php endif; ?>
                </div>
                <div class="cvloa-detail-card">
                    <h3>Envio</h3>
                    <?php self::render_address( $shipping ); ?>
                </div>
            </div>

            <div class="cvloa-card">
                <h3>Dados comerciais</h3>
                <p><strong>N.º de encomendas:</strong> <?php echo esc_html( number_format_i18n( absint( $customer['orders_count'] ?? 0 ) ) ); ?><br>
                <strong>Total gasto:</strong> <?php echo wp_kses_post( wc_price( (float) ( $customer['total_spent'] ?? 0 ) ) ); ?><br>
                <strong>Cliente pagante:</strong> <?php echo ! empty( $customer['is_paying_customer'] ) ? 'Sim' : 'Não'; ?></p>
            </div>

            <div class="cvloa-card">
                <h3>Encomendas associadas pelo email</h3>
                <p class="cvloa-muted">Correspondência pelo email de faturação <strong><?php echo esc_html( $email ?: '—' ); ?></strong>.</p>
                <?php if ( ! $linked_orders ) : ?>
                    <p>Não foram encontradas encomendas arquivadas com este email.</p>
                <?php else : ?>
                    <table class="cvloa-table">
                        <thead><tr><th>Encomenda</th><th>Data</th><th>Estado</th><th>Total</th></tr></thead>
                        <tbody>
                        <?php foreach ( $linked_orders as $order_row ) : ?>
                            <?php
                            $order_url = add_query_arg(
                                array(
                                    'page'       => 'cv-legacy-orders',
                                    'tab'        => 'archive',
                                    'view_order' => sanitize_key( (string) ( $order_row['archive_key'] ?? $order_row['id'] ?? '' ) ),
                                ),
                                admin_url( 'admin.php' )
                            );
                            $order_status = sanitize_key( preg_replace( '/^wc-/', '', (string) ( $order_row['status'] ?? '' ) ) );
                            $order_status_label = wc_get_order_status_name( $order_status );
                            if ( ! $order_status_label ) {
                                $order_status_label = ucfirst( str_replace( '-', ' ', $order_status ) );
                            }
                            ?>
                            <tr>
                                <td><a href="<?php echo esc_url( $order_url ); ?>"><strong>#<?php echo esc_html( (string) ( $order_row['number'] ?: $order_row['id'] ) ); ?></strong></a></td>
                                <td><?php echo esc_html( self::format_date( (string) ( $order_row['date_created'] ?? '' ) ) ); ?></td>
                                <td><?php echo esc_html( $order_status_label ); ?></td>
                                <td><?php echo wp_kses_post( wc_price( (float) ( $order_row['total'] ?? 0 ), array( 'currency' => (string) ( $order_row['currency'] ?? get_woocommerce_currency() ) ) ) ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <?php if ( ! empty( $customer['meta_data'] ) ) : ?>
                <div class="cvloa-card">
                    <details>
                        <summary><strong>Metadados arquivados</strong></summary>
                        <table class="cvloa-table" style="margin-top:12px">
                            <thead><tr><th>Chave</th><th>Valor</th></tr></thead>
                            <tbody>
                            <?php foreach ( (array) $customer['meta_data'] as $meta ) : $meta = (array) $meta; ?>
                                <tr>
                                    <td><?php echo esc_html( (string) ( $meta['key'] ?? '' ) ); ?></td>
                                    <td><?php echo esc_html( self::stringify_value( $meta['value'] ?? '' ) ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </details>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function render_address( array $address ): void {
        $name    = trim( (string) ( $address['first_name'] ?? '' ) . ' ' . (string) ( $address['last_name'] ?? '' ) );
        $company = (string) ( $address['company'] ?? '' );
        $lines   = array_filter(
            array(
                $name,
                $company,
                (string) ( $address['address_1'] ?? '' ),
                (string) ( $address['address_2'] ?? '' ),
                trim( (string) ( $address['postcode'] ?? '' ) . ' ' . (string) ( $address['city'] ?? '' ) ),
                (string) ( $address['state'] ?? '' ),
                (string) ( $address['country'] ?? '' ),
            )
        );

        if ( ! $lines ) {
            echo '<p class="cvloa-muted">Sem morada arquivada.</p>';
            return;
        }

        echo '<p>' . implode( '<br>', array_map( 'esc_html', $lines ) ) . '</p>';
    }

    private static function format_date( string $value ): string {
        if ( '' === $value ) {
            return '—';
        }

        $time = strtotime( $value );
        return $time ? wp_date( 'd/m/Y H:i', $time ) : $value;
    }

    private static function stringify_value( $value ): string {
        if ( is_scalar( $value ) || null === $value ) {
            return (string) $value;
        }

        $json = wp_json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        return false === $json ? '' : $json;
    }
}
