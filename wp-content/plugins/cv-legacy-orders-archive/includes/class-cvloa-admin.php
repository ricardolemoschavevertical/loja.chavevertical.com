<?php
defined( 'ABSPATH' ) || exit;

final class CVLOA_Admin {
    public static function init(): void {
        add_action( 'admin_menu', array( __CLASS__, 'menu' ), 99 );
        add_action( 'admin_post_cvloa_save_settings', array( __CLASS__, 'save_settings' ) );

        add_action( 'wp_ajax_cvloa_test_source', array( __CLASS__, 'ajax_test_source' ) );
        add_action( 'wp_ajax_cvloa_pull_statuses', array( __CLASS__, 'ajax_pull_statuses' ) );
        add_action( 'wp_ajax_cvloa_start_import', array( __CLASS__, 'ajax_start_import' ) );
        add_action( 'wp_ajax_cvloa_import_batch', array( __CLASS__, 'ajax_import_batch' ) );
        add_action( 'wp_ajax_cvloa_import_status', array( __CLASS__, 'ajax_import_status' ) );
        add_action( 'wp_ajax_cvloa_reset_import', array( __CLASS__, 'ajax_reset_import' ) );
    }

    public static function menu(): void {
        add_submenu_page(
            'woocommerce',
            'Encomendas antigas',
            'Encomendas antigas',
            'manage_woocommerce',
            'cv-legacy-orders',
            array( __CLASS__, 'render' )
        );
    }

    public static function save_settings(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'Sem permissões.', 'cv-legacy-orders-archive' ) );
        }

        check_admin_referer( 'cvloa_save_settings' );

        $old = CVLOA_REST_Client::settings();

        $settings = array(
            'source_url' => untrailingslashit(
                esc_url_raw( (string) wp_unslash( $_POST['source_url'] ?? 'https://chavevertical.com' ) )
            ),
            'reuse_cvr2' => ! empty( $_POST['reuse_cvr2'] ) ? 1 : 0,
            'source_ck'  => (string) ( $old['source_ck'] ?? '' ),
            'source_cs'  => (string) ( $old['source_cs'] ?? '' ),
        );

        $ck = trim( (string) wp_unslash( $_POST['source_ck'] ?? '' ) );
        $cs = trim( (string) wp_unslash( $_POST['source_cs'] ?? '' ) );

        if ( '' !== $ck ) {
            $settings['source_ck'] = CVLOA_REST_Client::encrypt_secret( $ck );
        }

        if ( '' !== $cs ) {
            $settings['source_cs'] = CVLOA_REST_Client::encrypt_secret( $cs );
        }

        update_option( CVLOA_OPTION, $settings, false );

        wp_safe_redirect(
            add_query_arg(
                array(
                    'page'        => 'cv-legacy-orders',
                    'tab'         => 'settings',
                    'cvloa_saved' => 1,
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

        check_ajax_referer( 'cvloa_import', 'nonce' );
    }

    public static function ajax_test_source(): void {
        self::guard_ajax();

        $result = CVLOA_REST_Client::test_connection();

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success(
            array(
                'message' => sprintf(
                    'Ligação REST válida. A origem reporta %s encomenda(s).',
                    number_format_i18n( absint( $result['total'] ?? 0 ) )
                ),
            )
        );
    }

    private static function refresh_source_statuses() {
        $statuses = CVLOA_REST_Client::order_statuses();

        if ( is_wp_error( $statuses ) ) {
            return $statuses;
        }

        $cache = array(
            'source_url' => CVLOA_REST_Client::source_url(),
            'fetched_at' => time(),
            'statuses'   => $statuses,
        );

        update_option( CVLOA_STATUSES_OPTION, $cache, false );

        // Persistir as definições no Chave Vertical Core. Este option/runtime
        // não depende deste plugin temporário e continua ativo depois de o removermos.
        if ( class_exists( 'CV_Core_Order_History' ) ) {
            CV_Core_Order_History::merge_permanent_statuses( $statuses );
        }

        // Depois de guardar as definições da origem, disponibilizá-las já
        // no WooCommerce atual para encomendas novas e futuras.
        $cache['woo_sync'] = CVLOA_Order_Statuses::sync_now();
        update_option( CVLOA_STATUSES_OPTION, $cache, false );

        return $cache;
    }

    private static function cached_source_statuses(): array {
        $cache = (array) get_option( CVLOA_STATUSES_OPTION, array() );

        return isset( $cache['statuses'] ) && is_array( $cache['statuses'] )
            ? $cache['statuses']
            : array();
    }

    public static function ajax_pull_statuses(): void {
        self::guard_ajax();

        $cache = self::refresh_source_statuses();

        if ( is_wp_error( $cache ) ) {
            wp_send_json_error( array( 'message' => $cache->get_error_message() ) );
        }

        $statuses = (array) ( $cache['statuses'] ?? array() );
        $woo_sync = (array) ( $cache['woo_sync'] ?? array() );

        wp_send_json_success(
            array(
                'statuses'   => $statuses,
                'woo_sync'   => $woo_sync,
                'fetched_at' => wp_date( 'd/m/Y H:i:s', absint( $cache['fetched_at'] ?? time() ) ),
                'message'    => sprintf(
                    'Foram puxados %1$d estado(s) da origem. %2$d estão disponíveis no WooCommerce atual; %3$d estado(s) personalizado(s) foram registados agora.',
                    count( $statuses ),
                    absint( $woo_sync['available'] ?? 0 ),
                    absint( $woo_sync['registered'] ?? 0 )
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

        $batch_size = absint( wp_unslash( $_POST['batch_size'] ?? 20 ) );
        $batch_size = max( 1, min( 100, $batch_size ) );

        $status_cache = (array) get_option( CVLOA_STATUSES_OPTION, array() );
        if (
            empty( $status_cache['statuses'] )
            || (string) ( $status_cache['source_url'] ?? '' ) !== CVLOA_REST_Client::source_url()
        ) {
            self::refresh_source_statuses();
        } else {
            CVLOA_Order_Statuses::sync_now();
        }

        $state = array(
            'status'        => 'running',
            'page'          => 1,
            'total_pages'   => 0,
            'total'         => 0,
            'processed'     => 0,
            'archived'      => 0,
            'updated'       => 0,
            'ignored'       => 0,
            'errors_count'  => 0,
            'errors'        => array(),
            'batch_size'    => $batch_size,
            'import_mode'   => $mode,
            'include_notes' => ! empty( $_POST['include_notes'] ),
            'run_id'        => wp_generate_uuid4(),
            'started_at'    => time(),
            'updated_at'    => time(),
        );

        update_option( CVLOA_STATE_OPTION, $state, false );

        wp_send_json_success(
            array(
                'state'   => $state,
                'message' => sprintf(
                    'Importação iniciada — lote de %d encomenda(s).',
                    $batch_size
                ),
            )
        );
    }

    public static function ajax_import_status(): void {
        self::guard_ajax();

        $state          = (array) get_option( CVLOA_STATE_OPTION, array() );
        $request_run_id = sanitize_text_field( (string) wp_unslash( $_POST['run_id'] ?? '' ) );

        if (
            $request_run_id
            && ! empty( $state['run_id'] )
            && ! hash_equals( (string) $state['run_id'], $request_run_id )
        ) {
            wp_send_json_error(
                array(
                    'message' => 'Esta execução foi substituída por uma importação mais recente.',
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
        delete_option( CVLOA_STATE_OPTION );
        wp_send_json_success( array( 'message' => 'Estado da importação limpo.' ) );
    }

    public static function ajax_import_batch(): void {
        self::guard_ajax();

        $state = (array) get_option( CVLOA_STATE_OPTION, array() );

        if ( empty( $state ) ) {
            wp_send_json_error( array( 'message' => 'Não existe uma importação ativa.' ) );
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
                    'message' => 'Execução antiga bloqueada. Retome a importação ativa.',
                    'state'   => $state,
                ),
                409
            );
        }

        $lock_key   = 'cvloa_batch_lock_' . md5( $state_run_id );
        $lock_token = wp_generate_uuid4();

        if ( get_transient( $lock_key ) ) {
            wp_send_json_success(
                array(
                    'done'    => false,
                    'busy'    => true,
                    'state'   => $state,
                    'message' => 'Já existe um lote de encomendas em processamento.',
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
        $batch_size = max( 1, min( 100, absint( $state['batch_size'] ?? 20 ) ) );

        $result = CVLOA_REST_Client::request(
            'orders',
            array(
                'per_page' => $batch_size,
                'page'     => $page,
                'orderby'  => 'id',
                'order'    => 'asc',
                'status'   => 'any',
            ),
            75
        );

        if ( is_wp_error( $result ) ) {
            $state['status']       = 'error';
            $state['errors_count'] = absint( $state['errors_count'] ?? 0 ) + 1;
            $state['errors'][]     = $result->get_error_message();
            $state['errors']       = array_slice( $state['errors'], -20 );
            $state['updated_at']   = time();
            update_option( CVLOA_STATE_OPTION, $state, false );

            wp_send_json_error(
                array(
                    'message' => $result->get_error_message(),
                    'state'   => $state,
                )
            );
        }

        $orders      = array_values( (array) $result['data'] );
        $source_url  = CVLOA_REST_Client::source_url();
        $source_hash = substr( sha1( strtolower( untrailingslashit( $source_url ) ) ), 0, 10 );

        foreach ( $orders as &$order ) {
            $order = (array) $order;
            $id    = absint( $order['id'] ?? 0 );

            if ( ! $id ) {
                continue;
            }

            $order['_cvloa_origin']      = 'remote';
            $order['_cvloa_source_url']  = $source_url;
            $order['_cvloa_archive_key'] = 'remote-' . $source_hash . '-' . $id;
        }
        unset( $order );

        if ( ! empty( $state['include_notes'] ) ) {
            foreach ( $orders as &$order ) {
                $order = (array) $order;
                $id    = absint( $order['id'] ?? 0 );

                if ( ! $id ) {
                    continue;
                }

                $notes = CVLOA_REST_Client::request(
                    'orders/' . $id . '/notes',
                    array( 'per_page' => 100 ),
                    45
                );

                if ( is_wp_error( $notes ) ) {
                    $order['_cvloa_notes_error'] = $notes->get_error_message();
                } else {
                    $order['_cvloa_notes'] = array_values( (array) $notes['data'] );
                }
            }
            unset( $order );
        }

        $archive = CVLOA_Archive::archive_batch(
            $orders,
            'update_existing' === ( $state['import_mode'] ?? 'ignore_existing' )
        );

        if ( is_wp_error( $archive ) ) {
            $state['status']       = 'error';
            $state['errors_count'] = absint( $state['errors_count'] ?? 0 ) + 1;
            $state['errors'][]     = $archive->get_error_message();
            $state['errors']       = array_slice( $state['errors'], -20 );
            $state['updated_at']   = time();
            update_option( CVLOA_STATE_OPTION, $state, false );

            wp_send_json_error(
                array(
                    'message' => $archive->get_error_message(),
                    'state'   => $state,
                )
            );
        }

        $state['total']        = (int) $result['total'];
        $state['total_pages']  = max( 1, (int) $result['total_pages'] );
        $state['processed']    = absint( $state['processed'] ?? 0 ) + count( $orders );
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

        if ( $page >= (int) $state['total_pages'] || empty( $orders ) ) {
            $state['status']      = 'done';
            $state['finished_at'] = time();
        } else {
            $state['page'] = $page + 1;
        }

        update_option( CVLOA_STATE_OPTION, $state, false );

        wp_send_json_success(
            array(
                'done'  => 'done' === ( $state['status'] ?? '' ),
                'state' => $state,
                'message' => sprintf(
                    'Verificadas %1$s / %2$s — arquivadas: %3$s; atualizadas: %4$s; ignoradas: %5$s.',
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

        $tab = sanitize_key( (string) wp_unslash( $_GET['tab'] ?? 'archive' ) );
        if ( ! in_array( $tab, array( 'archive', 'import', 'settings' ), true ) ) {
            $tab = 'archive';
        }

        $base_url = add_query_arg( 'page', 'cv-legacy-orders', admin_url( 'admin.php' ) );
        ?>
        <div class="wrap cvloa-admin">
            <h1>Encomendas antigas</h1>
            <p>Arquivo histórico local em modo só leitura. Estas encomendas não são criadas nas tabelas de encomendas atuais do WooCommerce.</p>

            <nav class="nav-tab-wrapper">
                <a class="nav-tab <?php echo 'archive' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'tab', 'archive', $base_url ) ); ?>">Encomendas antigas</a>
                <a class="nav-tab <?php echo 'import' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'tab', 'import', $base_url ) ); ?>">Importar</a>
                <a class="nav-tab <?php echo 'settings' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'tab', 'settings', $base_url ) ); ?>">Configuração</a>
            </nav>

            <style>
                .cvloa-admin{max-width:1600px}.cvloa-admin .nav-tab-wrapper{margin-bottom:18px}
                .cvloa-card{background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:18px;margin:16px 0}
                .cvloa-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px}
                .cvloa-stat{padding:14px;border-radius:8px;background:#f6f7f7}.cvloa-stat span{display:block;color:#646970;font-size:12px}.cvloa-stat strong{display:block;margin-top:4px;font-size:20px}
                .cvloa-actions{display:flex;gap:8px;flex-wrap:wrap;margin:14px 0}.cvloa-progress{height:14px;border-radius:999px;background:#e5e5e5;overflow:hidden}.cvloa-progress span{display:block;height:100%;width:0;background:#2271b1;transition:width .2s}
                .cvloa-log{padding:10px 12px;background:#f6f7f7;border:1px solid #dcdcde;min-height:42px;white-space:pre-wrap}
                .cvloa-mode label{display:block;margin:9px 0}.cvloa-row{display:grid;grid-template-columns:210px minmax(0,1fr);gap:14px;align-items:center;margin:12px 0}.cvloa-row input[type=text],.cvloa-row input[type=url],.cvloa-row input[type=password],.cvloa-row input[type=number]{width:100%;max-width:720px}
                .cvloa-table{width:100%;border-collapse:collapse;background:#fff}.cvloa-table th,.cvloa-table td{padding:10px;border-bottom:1px solid #eee;text-align:left;vertical-align:top}.cvloa-table th{background:#f6f7f7}
                .cvloa-status{display:inline-block;padding:4px 8px;border-radius:999px;background:#f0f0f1;font-size:11px;font-weight:700}
                .cvloa-status.cvloa-status-completed{background:#dff3e4;color:#145a27;border:1px solid #a8d5b3}
                .cvloa-status.cvloa-status-failed{background:#fbe3e3;color:#8a1f1f;border:1px solid #e5aaaa}
                .cvloa-status.cvloa-status-other{background:#fff0db;color:#8a4b08;border:1px solid #efc27f}
                .cvloa-table tr.cvloa-order-completed>td{background:#f1faf3}
                .cvloa-table tr.cvloa-order-failed>td{background:#fff1f1}
                .cvloa-table tr.cvloa-order-other>td{background:#fff8ee}
                .cvloa-readonly{display:inline-block;padding:5px 9px;border-radius:999px;background:#fff7e6;color:#744b00;font-size:11px;font-weight:800}
                .cvloa-detail-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.cvloa-detail-card{padding:16px;border:1px solid #dcdcde;border-radius:9px;background:#fff}
                .cvloa-toolbar{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin:14px 0}.cvloa-toolbar input[type=search]{min-width:320px}
                .cvloa-muted{color:#646970}.cvloa-error{color:#b32d2e}.cvloa-path{word-break:break-all;font-family:monospace}
                @media(max-width:800px){.cvloa-row,.cvloa-detail-grid{grid-template-columns:1fr}.cvloa-toolbar input[type=search]{min-width:0;width:100%}}
            </style>

            <?php
            if ( 'settings' === $tab ) {
                self::render_settings();
            } elseif ( 'import' === $tab ) {
                self::render_import();
            } else {
                self::render_archive();
            }
            ?>
        </div>
        <?php
    }

    private static function render_settings(): void {
        $settings = CVLOA_REST_Client::settings();

        if ( isset( $_GET['cvloa_saved'] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>Configuração guardada.</p></div>';
        }
        ?>
        <div class="cvloa-card">
            <h2>Origem REST WooCommerce</h2>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="cvloa_save_settings">
                <?php wp_nonce_field( 'cvloa_save_settings' ); ?>

                <div class="cvloa-row">
                    <label for="cvloa-source-url"><strong>URL da loja de origem</strong></label>
                    <input id="cvloa-source-url" type="url" name="source_url" value="<?php echo esc_attr( (string) $settings['source_url'] ); ?>" required>
                </div>

                <div class="cvloa-row">
                    <span><strong>Credenciais existentes</strong></span>
                    <label><input type="checkbox" name="reuse_cvr2" value="1" <?php checked( ! empty( $settings['reuse_cvr2'] ) ); ?>> Reutilizar as credenciais REST já guardadas no CV R2 Media Linker quando não forem definidas credenciais próprias.</label>
                </div>

                <div class="cvloa-row">
                    <label for="cvloa-ck"><strong>Consumer Key própria</strong></label>
                    <input id="cvloa-ck" type="password" name="source_ck" value="" autocomplete="new-password" placeholder="Deixar vazio para manter/reutilizar">
                </div>

                <div class="cvloa-row">
                    <label for="cvloa-cs"><strong>Consumer Secret próprio</strong></label>
                    <input id="cvloa-cs" type="password" name="source_cs" value="" autocomplete="new-password" placeholder="Deixar vazio para manter/reutilizar">
                </div>

                <?php submit_button( 'Guardar configuração' ); ?>
            </form>
        </div>
        <?php
    }

    private static function render_import(): void {
        $stats        = CVLOA_Archive::stats();
        $state        = (array) get_option( CVLOA_STATE_OPTION, array() );
        $status_cache = (array) get_option( CVLOA_STATUSES_OPTION, array() );
        $statuses     = isset( $status_cache['statuses'] ) && is_array( $status_cache['statuses'] )
            ? $status_cache['statuses']
            : array();
        $nonce        = wp_create_nonce( 'cvloa_import' );
        ?>
        <div class="cvloa-grid">
            <div class="cvloa-stat"><span>Encomendas no arquivo</span><strong><?php echo esc_html( number_format_i18n( $stats['count'] ) ); ?></strong></div>
            <div class="cvloa-stat"><span>Ficheiro de dados</span><strong><?php echo esc_html( size_format( $stats['data_bytes'], 1 ) ); ?></strong></div>
            <div class="cvloa-stat"><span>Índice local</span><strong><?php echo esc_html( size_format( $stats['index_bytes'], 1 ) ); ?></strong></div>
        </div>

        <div class="cvloa-card">
            <h2>Ficheiros do arquivo</h2>
            <table class="cvloa-table">
                <thead>
                    <tr>
                        <th>Ficheiro</th>
                        <th>Tamanho</th>
                        <th>Localização</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code><?php echo esc_html( basename( CVLOA_Archive::data_path() ) ); ?></code></td>
                        <td><?php echo esc_html( size_format( $stats['data_bytes'], 1 ) ); ?></td>
                        <td><span class="cvloa-path"><?php echo esc_html( CVLOA_Archive::data_path() ); ?></span></td>
                    </tr>
                    <tr>
                        <td><code><?php echo esc_html( basename( CVLOA_Archive::index_path() ) ); ?></code></td>
                        <td><?php echo esc_html( size_format( $stats['index_bytes'], 1 ) ); ?></td>
                        <td><span class="cvloa-path"><?php echo esc_html( CVLOA_Archive::index_path() ); ?></span></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="cvloa-card">
            <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap">
                <div>
                    <h2 style="margin:0 0 5px">Estados das encomendas na origem</h2>
                    <p style="margin:0">Puxa os estados reais do WooCommerce de origem, incluindo estados personalizados, e regista no WooCommerce atual os estados que estiverem em falta para poderem ser usados nas próximas encomendas. O estado de cada encomenda antiga continua também guardado no arquivo.</p>
                </div>
                <button class="button" type="button" data-cvloa-action="pull-statuses">Puxar estados da origem</button>
            </div>

            <div data-cvloa-source-statuses style="margin-top:14px">
                <?php if ( $statuses ) : ?>
                    <table class="cvloa-table">
                        <thead><tr><th>Estado</th><th>Slug</th><th>Encomendas na origem</th></tr></thead>
                        <tbody>
                        <?php foreach ( $statuses as $status_row ) : $status_row = (array) $status_row; ?>
                            <tr>
                                <td><span class="cvloa-status"><?php echo esc_html( (string) ( $status_row['name'] ?? '' ) ); ?></span></td>
                                <td><code><?php echo esc_html( (string) ( $status_row['slug'] ?? '' ) ); ?></code></td>
                                <td><?php echo esc_html( number_format_i18n( absint( $status_row['total'] ?? 0 ) ) ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p class="description">Última sincronização: <?php echo esc_html( ! empty( $status_cache['fetched_at'] ) ? wp_date( 'd/m/Y H:i:s', absint( $status_cache['fetched_at'] ) ) : '—' ); ?></p>
                <?php else : ?>
                    <p class="cvloa-muted">Ainda não foram puxados os estados da loja de origem.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="cvloa-card">
            <h2>Importar encomendas históricas</h2>
            <p>A importação grava uma cópia completa da resposta REST no arquivo local. Não cria encomendas WooCommerce, não altera stock e não dispara emails.</p>

            <div class="cvloa-mode">
                <label><input type="radio" name="cvloa_import_mode" value="ignore_existing" checked> <strong>Ignorar encomendas já arquivadas</strong> — recomendado para a primeira migração e retomas.</label>
                <label><input type="radio" name="cvloa_import_mode" value="update_existing"> <strong>Atualizar encomendas já arquivadas</strong> — grava uma nova versão local e atualiza o índice para a versão mais recente.</label>
            </div>

            <div class="cvloa-row">
                <label for="cvloa-batch-size"><strong>Quantidade por lote</strong></label>
                <input id="cvloa-batch-size" type="number" min="1" max="100" step="1" value="<?php echo esc_attr( (string) max( 1, min( 100, absint( $state['batch_size'] ?? 20 ) ) ) ); ?>">
            </div>

            <div class="cvloa-row">
                <span><strong>Notas da encomenda</strong></span>
                <label><input id="cvloa-include-notes" type="checkbox" value="1"> Incluir notas da encomenda. Torna a importação mais lenta porque exige um pedido REST adicional por encomenda.</label>
            </div>

            <div class="cvloa-actions">
                <button class="button" type="button" data-cvloa-action="test">Testar origem</button>
                <button class="button button-primary" type="button" data-cvloa-action="start">Iniciar / recomeçar</button>
                <button class="button" type="button" data-cvloa-action="resume">Retomar</button>
                <button class="button" type="button" data-cvloa-action="pause">Pausar</button>
                <button class="button" type="button" data-cvloa-action="reset">Limpar estado</button>
            </div>

            <p><strong data-cvloa-progress-text>0 / 0 encomendas</strong> <span class="cvloa-muted" data-cvloa-progress-percent>0%</span></p>
            <div class="cvloa-progress"><span data-cvloa-progress></span></div>

            <div class="cvloa-grid" style="margin-top:12px">
                <div class="cvloa-stat"><span>Verificadas</span><strong data-stat="processed">0</strong></div>
                <div class="cvloa-stat"><span>Total</span><strong data-stat="total">0</strong></div>
                <div class="cvloa-stat"><span>Arquivadas</span><strong data-stat="archived">0</strong></div>
                <div class="cvloa-stat"><span>Atualizadas</span><strong data-stat="updated">0</strong></div>
                <div class="cvloa-stat"><span>Ignoradas</span><strong data-stat="ignored">0</strong></div>
                <div class="cvloa-stat"><span>Erros</span><strong data-stat="errors_count">0</strong></div>
            </div>

            <h3>Estado</h3>
            <div class="cvloa-log" data-cvloa-log>Pronto.</div>

            <?php if ( ! empty( $stats['error'] ) ) : ?>
                <p class="cvloa-error"><?php echo esc_html( $stats['error'] ); ?></p>
            <?php endif; ?>
            <p class="description">Pasta base do arquivo: <span class="cvloa-path"><?php echo esc_html( $stats['storage_path'] ); ?></span></p>
        </div>

        <script>
        (() => {
            const nonce = <?php echo wp_json_encode( $nonce ); ?>;
            const buttons = document.querySelectorAll('[data-cvloa-action]');
            const modeInputs = document.querySelectorAll('input[name="cvloa_import_mode"]');
            const batchInput = document.querySelector('#cvloa-batch-size');
            const notesInput = document.querySelector('#cvloa-include-notes');
            const log = document.querySelector('[data-cvloa-log]');
            const progress = document.querySelector('[data-cvloa-progress]');
            const progressText = document.querySelector('[data-cvloa-progress-text]');
            const progressPercent = document.querySelector('[data-cvloa-progress-percent]');
            const sourceStatuses = document.querySelector('[data-cvloa-source-statuses]');
            let running = false;
            let activeRunId = '';
            let statusTimer = null;

            const write = (message) => { if (log) log.textContent = String(message || ''); };

            const setBusy = (busy) => {
                buttons.forEach((button) => {
                    if (button.dataset.cvloaAction === 'pause') {
                        button.disabled = !running;
                    } else {
                        button.disabled = busy;
                    }
                });
                modeInputs.forEach((input) => input.disabled = busy);
                if (batchInput) batchInput.disabled = busy;
                if (notesInput) notesInput.disabled = busy;
            };

            function renderState(state) {
                state = state || {};
                if (state.run_id) activeRunId = String(state.run_id);

                ['processed','total','archived','updated','ignored','errors_count'].forEach((key) => {
                    const el = document.querySelector('[data-stat="' + key + '"]');
                    if (el) el.textContent = Number(state[key] || 0).toLocaleString('pt-PT');
                });

                const total = Number(state.total || 0);
                const processed = Number(state.processed || 0);
                const percent = total > 0 ? Math.min(100, (processed / total) * 100) : 0;
                if (progress) progress.style.width = percent + '%';
                if (progressText) progressText.textContent = processed.toLocaleString('pt-PT') + ' / ' + total.toLocaleString('pt-PT') + ' encomendas';
                if (progressPercent) progressPercent.textContent = percent.toLocaleString('pt-PT', {maximumFractionDigits:1}) + '%';

                if (state.import_mode) {
                    modeInputs.forEach((input) => input.checked = input.value === state.import_mode);
                }
                if (batchInput && state.batch_size) batchInput.value = String(state.batch_size);
                if (notesInput) notesInput.checked = Boolean(state.include_notes);
            }

            function renderSourceStatuses(statuses, fetchedAt = '') {
                if (!sourceStatuses) return;

                const rows = statuses && typeof statuses === 'object'
                    ? Object.values(statuses)
                    : [];

                sourceStatuses.replaceChildren();

                if (!rows.length) {
                    const p = document.createElement('p');
                    p.className = 'cvloa-muted';
                    p.textContent = 'A origem não devolveu estados de encomenda.';
                    sourceStatuses.appendChild(p);
                    return;
                }

                const table = document.createElement('table');
                table.className = 'cvloa-table';

                const thead = document.createElement('thead');
                const headRow = document.createElement('tr');
                ['Estado','Slug','Encomendas na origem'].forEach((label) => {
                    const th = document.createElement('th');
                    th.textContent = label;
                    headRow.appendChild(th);
                });
                thead.appendChild(headRow);
                table.appendChild(thead);

                const tbody = document.createElement('tbody');

                rows.forEach((row) => {
                    const tr = document.createElement('tr');

                    const nameTd = document.createElement('td');
                    const badge = document.createElement('span');
                    badge.className = 'cvloa-status';
                    badge.textContent = String(row.name || row.slug || '');
                    nameTd.appendChild(badge);
                    tr.appendChild(nameTd);

                    const slugTd = document.createElement('td');
                    const code = document.createElement('code');
                    code.textContent = String(row.slug || '');
                    slugTd.appendChild(code);
                    tr.appendChild(slugTd);

                    const totalTd = document.createElement('td');
                    totalTd.textContent = Number(row.total || 0).toLocaleString('pt-PT');
                    tr.appendChild(totalTd);

                    tbody.appendChild(tr);
                });

                table.appendChild(tbody);
                sourceStatuses.appendChild(table);

                if (fetchedAt) {
                    const p = document.createElement('p');
                    p.className = 'description';
                    p.textContent = 'Última sincronização: ' + fetchedAt;
                    sourceStatuses.appendChild(p);
                }
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
                    const data = await call('cvloa_import_status', activeRunId ? {run_id: activeRunId} : {});
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
                        const data = await call('cvloa_import_batch', {run_id: activeRunId});
                        renderState(data.state);
                        write(data.message || 'A processar…');

                        if (data.busy) {
                            await new Promise((resolve) => window.setTimeout(resolve, 700));
                            continue;
                        }

                        if (data.done) {
                            running = false;
                            write(data.message || 'Importação concluída.');
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

            document.querySelector('[data-cvloa-action="test"]')?.addEventListener('click', async () => {
                setBusy(true);
                try {
                    const data = await call('cvloa_test_source');
                    write(data.message);
                } catch (error) {
                    write(error.message || error);
                } finally {
                    setBusy(false);
                }
            });

            document.querySelector('[data-cvloa-action="pull-statuses"]')?.addEventListener('click', async () => {
                setBusy(true);
                try {
                    const data = await call('cvloa_pull_statuses');
                    renderSourceStatuses(data.statuses, data.fetched_at || '');
                    write(data.message);
                } catch (error) {
                    write(error.message || error);
                } finally {
                    setBusy(false);
                }
            });

            document.querySelector('[data-cvloa-action="start"]')?.addEventListener('click', async () => {
                setBusy(true);
                try {
                    const mode = document.querySelector('input[name="cvloa_import_mode"]:checked')?.value || 'ignore_existing';
                    const batchSize = Math.max(1, Math.min(100, Number(batchInput?.value || 20)));
                    const data = await call('cvloa_start_import', {
                        import_mode: mode,
                        batch_size: batchSize,
                        include_notes: notesInput?.checked ? '1' : ''
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

            document.querySelector('[data-cvloa-action="resume"]')?.addEventListener('click', async () => {
                setBusy(true);
                try {
                    const data = await call('cvloa_import_status');
                    activeRunId = String(data.state?.run_id || '');
                    renderState(data.state);
                    if (!activeRunId || data.done) {
                        write(data.done ? 'A importação guardada já está concluída.' : 'Não existe uma importação para retomar.');
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

            document.querySelector('[data-cvloa-action="pause"]')?.addEventListener('click', () => {
                running = false;
                stopPolling();
                setBusy(false);
                write('Importação pausada no browser. O estado ficou guardado para retoma.');
            });

            document.querySelector('[data-cvloa-action="reset"]')?.addEventListener('click', async () => {
                running = false;
                stopPolling();
                setBusy(true);
                try {
                    const data = await call('cvloa_reset_import');
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
        <?php
    }

    private static function render_archive(): void {
        $view_key = sanitize_key( (string) wp_unslash( $_GET['view_order'] ?? '' ) );

        if ( $view_key ) {
            self::render_order_detail( $view_key );
            return;
        }

        $index = CVLOA_Archive::load_index();
        if ( ! empty( $index['_error'] ) ) {
            echo '<div class="notice notice-error"><p>' . esc_html( (string) $index['_error'] ) . '</p></div>';
            return;
        }

        $orders = array_values( (array) ( $index['orders'] ?? array() ) );
        $search = trim( (string) wp_unslash( $_GET['s'] ?? '' ) );
        $status = sanitize_key( (string) wp_unslash( $_GET['status'] ?? '' ) );
        $needle = strtolower( remove_accents( $search ) );

        if ( '' !== $needle ) {
            $orders = array_values(
                array_filter(
                    $orders,
                    static fn( array $row ): bool => str_contains( (string) ( $row['search'] ?? '' ), $needle )
                )
            );
        }

        if ( '' !== $status ) {
            $orders = array_values(
                array_filter(
                    $orders,
                    static fn( array $row ): bool => $status === (string) ( $row['status'] ?? '' )
                )
            );
        }

        usort(
            $orders,
            static function ( array $a, array $b ): int {
                $ad = strtotime( (string) ( $a['date_created'] ?? '' ) ) ?: 0;
                $bd = strtotime( (string) ( $b['date_created'] ?? '' ) ) ?: 0;
                if ( $ad === $bd ) {
                    return absint( $b['id'] ?? 0 ) <=> absint( $a['id'] ?? 0 );
                }
                return $bd <=> $ad;
            }
        );

        $all_statuses = array_fill_keys( array_keys( self::cached_source_statuses() ), true );
        foreach ( (array) ( $index['orders'] ?? array() ) as $row ) {
            $row_status = sanitize_key( preg_replace( '/^wc-/', '', (string) ( $row['status'] ?? '' ) ) );
            if ( $row_status ) {
                $all_statuses[ $row_status ] = true;
            }
        }
        ksort( $all_statuses );

        $per_page = 50;
        $paged    = max( 1, absint( $_GET['paged'] ?? 1 ) );
        $total    = count( $orders );
        $pages    = max( 1, (int) ceil( $total / $per_page ) );
        $paged    = min( $paged, $pages );
        $slice    = array_slice( $orders, ( $paged - 1 ) * $per_page, $per_page );

        $stats = CVLOA_Archive::stats();
        ?>
        <div class="cvloa-grid">
            <div class="cvloa-stat"><span>Encomendas arquivadas</span><strong><?php echo esc_html( number_format_i18n( $stats['count'] ) ); ?></strong></div>
            <div class="cvloa-stat"><span>Resultados atuais</span><strong><?php echo esc_html( number_format_i18n( $total ) ); ?></strong></div>
            <div class="cvloa-stat"><span>Última atualização do índice</span><strong style="font-size:14px"><?php echo esc_html( self::format_date( $stats['updated_at'] ) ); ?></strong></div>
        </div>

        <form class="cvloa-toolbar" method="get">
            <input type="hidden" name="page" value="cv-legacy-orders">
            <input type="hidden" name="tab" value="archive">
            <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Nº encomenda, cliente, empresa, email, telefone, produto ou SKU">
            <select name="status">
                <option value="">Todos os estados</option>
                <?php foreach ( array_keys( $all_statuses ) as $order_status ) : ?>
                    <option value="<?php echo esc_attr( $order_status ); ?>" <?php selected( $status, $order_status ); ?>><?php echo esc_html( self::status_label( $order_status ) ); ?></option>
                <?php endforeach; ?>
            </select>
            <button class="button">Pesquisar</button>
            <a class="button" href="<?php echo esc_url( add_query_arg( array( 'page' => 'cv-legacy-orders', 'tab' => 'archive' ), admin_url( 'admin.php' ) ) ); ?>">Limpar</a>
        </form>

        <div class="cvloa-card" style="padding:0;overflow:auto">
            <table class="cvloa-table">
                <thead>
                    <tr>
                        <th>Encomenda</th>
                        <th>Data</th>
                        <th>Cliente / Empresa</th>
                        <th>Estado</th>
                        <th>Total</th>
                        <th>Itens</th>
                        <th>Pagamento</th>
                        <th>Envio</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ( ! $slice ) : ?>
                    <tr><td colspan="8">Não foram encontradas encomendas no arquivo.</td></tr>
                <?php else : ?>
                    <?php foreach ( $slice as $row ) : ?>
                        <?php
                        $detail_url = add_query_arg(
                            array(
                                'page'       => 'cv-legacy-orders',
                                'tab'        => 'archive',
                                'view_order' => sanitize_key( (string) ( $row['archive_key'] ?? $row['id'] ?? '' ) ),
                            ),
                            admin_url( 'admin.php' )
                        );
                        ?>
                        <?php $status_color_class = self::status_color_class( (string) ( $row['status'] ?? '' ) ); ?>
                        <tr class="<?php echo esc_attr( 'cvloa-order-' . $status_color_class ); ?>">
                            <td><a href="<?php echo esc_url( $detail_url ); ?>"><strong>#<?php echo esc_html( (string) ( $row['number'] ?: $row['id'] ) ); ?></strong></a><br><span class="cvloa-muted">ID origem <?php echo esc_html( (string) $row['id'] ); ?></span></td>
                            <td><?php echo esc_html( self::format_date( (string) ( $row['date_created'] ?? '' ) ) ); ?></td>
                            <td><strong><?php echo esc_html( (string) ( $row['billing_name'] ?? '' ) ); ?></strong><?php if ( ! empty( $row['billing_company'] ) ) : ?><br><?php echo esc_html( (string) $row['billing_company'] ); ?><?php endif; ?><?php if ( ! empty( $row['billing_email'] ) ) : ?><br><span class="cvloa-muted"><?php echo esc_html( (string) $row['billing_email'] ); ?></span><?php endif; ?></td>
                            <td><span class="<?php echo esc_attr( 'cvloa-status cvloa-status-' . $status_color_class ); ?>"><?php echo esc_html( self::status_label( (string) ( $row['status'] ?? '' ) ) ); ?></span></td>
                            <td><?php echo wp_kses_post( wc_price( (float) ( $row['total'] ?? 0 ), array( 'currency' => (string) ( $row['currency'] ?? get_woocommerce_currency() ) ) ) ); ?></td>
                            <td><?php echo esc_html( number_format_i18n( absint( $row['item_count'] ?? 0 ) ) ); ?></td>
                            <td><?php echo esc_html( (string) ( $row['payment_method_title'] ?? '' ) ); ?></td>
                            <td><?php echo esc_html( implode( ', ', (array) ( $row['shipping_methods'] ?? array() ) ) ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php
        if ( $pages > 1 ) {
            $pagination = paginate_links(
                array(
                    'base'      => add_query_arg(
                        array(
                            'page'   => 'cv-legacy-orders',
                            'tab'    => 'archive',
                            's'      => $search,
                            'status' => $status,
                            'paged'  => '%#%',
                        ),
                        admin_url( 'admin.php' )
                    ),
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
    }

    private static function render_order_detail( string $order_key ): void {
        $order = CVLOA_Archive::read_order( $order_key );

        if ( ! $order ) {
            echo '<div class="notice notice-error"><p>Não foi possível encontrar esta encomenda no arquivo local.</p></div>';
            return;
        }

        $order_id = absint( $order['id'] ?? 0 );
        $billing  = (array) ( $order['billing'] ?? array() );
        $shipping = (array) ( $order['shipping'] ?? array() );
        $currency = (string) ( $order['currency'] ?? get_woocommerce_currency() );
        $back_url = add_query_arg( array( 'page' => 'cv-legacy-orders', 'tab' => 'archive' ), admin_url( 'admin.php' ) );
        ?>
        <p><a class="button" href="<?php echo esc_url( $back_url ); ?>">← Voltar às encomendas antigas</a></p>
        <div class="cvloa-card">
            <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap">
                <div>
                    <h2 style="margin-top:0">Encomenda #<?php echo esc_html( (string) ( $order['number'] ?? $order_id ) ); ?></h2>
                    <p class="cvloa-muted">ID de origem: <?php echo esc_html( (string) $order_id ); ?> · <?php echo esc_html( self::format_date( (string) ( $order['date_created'] ?? '' ) ) ); ?></p>
                </div>
                <?php $status_color_class = self::status_color_class( (string) ( $order['status'] ?? '' ) ); ?>
                <div><span class="cvloa-readonly">ARQUIVO — SÓ LEITURA</span> <span class="<?php echo esc_attr( 'cvloa-status cvloa-status-' . $status_color_class ); ?>"><?php echo esc_html( self::status_label( (string) ( $order['status'] ?? '' ) ) ); ?></span></div>
            </div>
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
                <?php foreach ( (array) ( $order['shipping_lines'] ?? array() ) as $line ) : $line = (array) $line; ?>
                    <p><strong>Método:</strong> <?php echo esc_html( (string) ( $line['method_title'] ?? '' ) ); ?></p>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="cvloa-card" style="overflow:auto">
            <h3>Produtos</h3>
            <table class="cvloa-table">
                <thead><tr><th>Produto</th><th>SKU</th><th>Quantidade</th><th>Subtotal</th><th>Total</th></tr></thead>
                <tbody>
                <?php foreach ( (array) ( $order['line_items'] ?? array() ) as $item ) : $item = (array) $item; ?>
                    <tr>
                        <td><strong><?php echo esc_html( (string) ( $item['name'] ?? '' ) ); ?></strong><?php if ( ! empty( $item['variation_id'] ) ) : ?><br><span class="cvloa-muted">Variação #<?php echo esc_html( (string) absint( $item['variation_id'] ) ); ?></span><?php endif; ?></td>
                        <td><?php echo esc_html( (string) ( $item['sku'] ?? '' ) ); ?></td>
                        <td><?php echo esc_html( (string) ( $item['quantity'] ?? 0 ) ); ?></td>
                        <td><?php echo wp_kses_post( wc_price( (float) ( $item['subtotal'] ?? 0 ), array( 'currency' => $currency ) ) ); ?></td>
                        <td><?php echo wp_kses_post( wc_price( (float) ( $item['total'] ?? 0 ), array( 'currency' => $currency ) ) ); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="cvloa-detail-grid">
            <div class="cvloa-detail-card">
                <h3>Pagamento</h3>
                <p><strong>Método:</strong> <?php echo esc_html( (string) ( $order['payment_method_title'] ?? '' ) ); ?></p>
                <?php if ( ! empty( $order['transaction_id'] ) ) : ?><p><strong>Transação:</strong> <?php echo esc_html( (string) $order['transaction_id'] ); ?></p><?php endif; ?>
                <?php if ( ! empty( $order['date_paid'] ) ) : ?><p><strong>Pago em:</strong> <?php echo esc_html( self::format_date( (string) $order['date_paid'] ) ); ?></p><?php endif; ?>
            </div>
            <div class="cvloa-detail-card">
                <h3>Totais</h3>
                <p><strong>Desconto:</strong> <?php echo wp_kses_post( wc_price( (float) ( $order['discount_total'] ?? 0 ), array( 'currency' => $currency ) ) ); ?></p>
                <p><strong>Transporte:</strong> <?php echo wp_kses_post( wc_price( (float) ( $order['shipping_total'] ?? 0 ), array( 'currency' => $currency ) ) ); ?></p>
                <p><strong>IVA:</strong> <?php echo wp_kses_post( wc_price( (float) ( $order['total_tax'] ?? 0 ), array( 'currency' => $currency ) ) ); ?></p>
                <p style="font-size:16px"><strong>Total:</strong> <?php echo wp_kses_post( wc_price( (float) ( $order['total'] ?? 0 ), array( 'currency' => $currency ) ) ); ?></p>
            </div>
        </div>

        <?php if ( ! empty( $order['customer_note'] ) ) : ?>
            <div class="cvloa-card"><h3>Nota do cliente</h3><p><?php echo nl2br( esc_html( (string) $order['customer_note'] ) ); ?></p></div>
        <?php endif; ?>

        <?php if ( ! empty( $order['_cvloa_notes'] ) ) : ?>
            <div class="cvloa-card">
                <h3>Notas da encomenda arquivadas</h3>
                <?php foreach ( (array) $order['_cvloa_notes'] as $note ) : $note = (array) $note; ?>
                    <p><strong><?php echo esc_html( self::format_date( (string) ( $note['date_created'] ?? '' ) ) ); ?></strong> — <?php echo wp_kses_post( (string) ( $note['note'] ?? '' ) ); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ( ! empty( $order['refunds'] ) ) : ?>
            <div class="cvloa-card">
                <h3>Reembolsos</h3>
                <table class="cvloa-table"><thead><tr><th>ID</th><th>Motivo</th><th>Total</th></tr></thead><tbody>
                <?php foreach ( (array) $order['refunds'] as $refund ) : $refund = (array) $refund; ?>
                    <tr><td>#<?php echo esc_html( (string) absint( $refund['id'] ?? 0 ) ); ?></td><td><?php echo esc_html( (string) ( $refund['reason'] ?? '' ) ); ?></td><td><?php echo wp_kses_post( wc_price( abs( (float) ( $refund['total'] ?? 0 ) ), array( 'currency' => $currency ) ) ); ?></td></tr>
                <?php endforeach; ?>
                </tbody></table>
            </div>
        <?php endif; ?>

        <?php if ( ! empty( $order['meta_data'] ) ) : ?>
            <div class="cvloa-card">
                <details>
                    <summary><strong>Metadados arquivados</strong></summary>
                    <table class="cvloa-table" style="margin-top:12px"><thead><tr><th>Chave</th><th>Valor</th></tr></thead><tbody>
                    <?php foreach ( (array) $order['meta_data'] as $meta ) : $meta = (array) $meta; ?>
                        <tr>
                            <td><?php echo esc_html( (string) ( $meta['key'] ?? '' ) ); ?></td>
                            <td><?php echo esc_html( self::stringify_value( $meta['value'] ?? '' ) ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody></table>
                </details>
            </div>
        <?php endif; ?>
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

    private static function status_color_class( string $status ): string {
        $status = sanitize_key( preg_replace( '/^wc-/', '', $status ) );

        if ( 'completed' === $status ) {
            return 'completed';
        }

        if ( 'failed' === $status ) {
            return 'failed';
        }

        return 'other';
    }

    private static function status_label( string $status ): string {
        $status = sanitize_key( preg_replace( '/^wc-/', '', $status ) );

        if ( '' === $status ) {
            return '—';
        }

        $source_statuses = self::cached_source_statuses();

        if ( ! empty( $source_statuses[ $status ]['name'] ) ) {
            return (string) $source_statuses[ $status ]['name'];
        }

        $label = wc_get_order_status_name( $status );
        return $label ?: ucfirst( str_replace( '-', ' ', $status ) );
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
