<?php
defined( 'ABSPATH' ) || exit;

final class CVR2_Admin {
    public static function init(): void {
        add_action( 'admin_menu', array( __CLASS__, 'menu' ), 30 );
        add_action( 'admin_post_cvr2_save_settings', array( __CLASS__, 'save_settings' ) );
        add_action( 'wp_ajax_cvr2_test_source', array( __CLASS__, 'ajax_test_source' ) );
        add_action( 'wp_ajax_cvr2_sync_structure', array( __CLASS__, 'ajax_sync_structure' ) );
        add_action( 'wp_ajax_cvr2_start_import', array( __CLASS__, 'ajax_start_import' ) );
        add_action( 'wp_ajax_cvr2_import_batch', array( __CLASS__, 'ajax_import_batch' ) );
        add_action( 'wp_ajax_cvr2_reset_import', array( __CLASS__, 'ajax_reset_import' ) );
    }

    public static function menu(): void {
        $parent = function_exists( 'cv_admin_parent_slug' ) ? cv_admin_parent_slug() : 'woocommerce';

        add_submenu_page(
            $parent,
            'Importação REST + R2',
            'Importação REST + R2',
            'manage_woocommerce',
            'cv-r2-rest-import',
            array( __CLASS__, 'render' )
        );
    }

    private static function guard_ajax(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => 'Sem permissões.' ), 403 );
        }
        check_ajax_referer( 'cvr2_import', 'nonce' );
    }

    public static function save_settings(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'Sem permissões.', 'cv-r2-media-linker' ) );
        }

        check_admin_referer( 'cvr2_save_settings' );

        $old = CVR2_REST_Client::settings();
        $new = array(
            'source_url'  => untrailingslashit( esc_url_raw( wp_unslash( $_POST['source_url'] ?? '' ) ) ),
            'r2_base_url' => untrailingslashit( esc_url_raw( wp_unslash( $_POST['r2_base_url'] ?? '' ) ) ),
            'batch_size'  => 1,
            'source_ck'   => (string) ( $old['source_ck'] ?? '' ),
            'source_cs'   => (string) ( $old['source_cs'] ?? '' ),
        );

        $ck = trim( (string) wp_unslash( $_POST['source_ck'] ?? '' ) );
        $cs = trim( (string) wp_unslash( $_POST['source_cs'] ?? '' ) );

        if ( '' !== $ck ) {
            $new['source_ck'] = CVR2_REST_Client::encrypt_secret( $ck );
        }
        if ( '' !== $cs ) {
            $new['source_cs'] = CVR2_REST_Client::encrypt_secret( $cs );
        }

        update_option( CVR2_OPTION, $new, false );

        wp_safe_redirect(
            add_query_arg(
                array(
                    'page'      => 'cv-r2-rest-import',
                    'cvr2_saved'=> 1,
                ),
                admin_url( 'admin.php' )
            )
        );
        exit;
    }

    public static function ajax_test_source(): void {
        self::guard_ajax();
        $result = CVR2_REST_Client::test_connection();

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success(
            array(
                'message' => sprintf( 'Ligação REST OK. %s produtos encontrados na origem.', number_format_i18n( (int) $result['total'] ) ),
            )
        );
    }

    public static function ajax_sync_structure(): void {
        self::guard_ajax();
        @set_time_limit( 120 );

        $result = CVR2_Product_Importer::sync_structure();
        update_option( 'cvr2_structure_synced_at', time(), false );

        $message = sprintf(
            'Estrutura sincronizada: %d categorias, %d etiquetas, %d marcas e %d atributos.',
            (int) $result['categories'],
            (int) $result['tags'],
            (int) $result['brands'],
            (int) $result['attributes']
        );

        if ( ! empty( $result['warnings'] ) ) {
            $message .= ' Avisos: ' . implode( ' | ', array_map( 'sanitize_text_field', $result['warnings'] ) );
        }

        wp_send_json_success(
            array(
                'message' => $message,
                'result'  => $result,
            )
        );
    }

    public static function ajax_start_import(): void {
        self::guard_ajax();

        $state = array(
            'status'      => 'running',
            'phase'       => 'products',
            'page'        => 1,
            'total_pages' => 0,
            'total'       => 0,
            'processed'   => 0,
            'created'     => 0,
            'updated'     => 0,
            'slug_errors' => 0,
            'errors'      => array(),
            'started_at'  => time(),
            'updated_at'  => time(),
        );

        update_option( CVR2_STATE_OPTION, $state, false );
        wp_send_json_success( array( 'state' => $state, 'message' => 'Importação iniciada.' ) );
    }

    public static function ajax_reset_import(): void {
        self::guard_ajax();
        delete_option( CVR2_STATE_OPTION );
        wp_send_json_success( array( 'message' => 'Estado da importação limpo.' ) );
    }

    public static function ajax_import_batch(): void {
        self::guard_ajax();

        // O JavaScript orquestra a importação. Cada pedido PHP trata apenas um produto,
        // para que o limite total de execução do PHP não limite a migração completa.

        $state = (array) get_option( CVR2_STATE_OPTION, array() );
        if ( empty( $state ) || 'done' === ( $state['status'] ?? '' ) ) {
            wp_send_json_success(
                array(
                    'done'  => true,
                    'state' => $state,
                )
            );
        }

        if ( 'relations' === ( $state['phase'] ?? 'products' ) ) {
            $result = CVR2_Product_Importer::resolve_relations_page( max( 1, absint( $state['page'] ?? 1 ) ), 25 );
            $state['page']        = (int) $result['page'] + 1;
            $state['total_pages'] = (int) $result['total_pages'];
            $state['updated_at']  = time();

            if ( (int) $result['page'] >= (int) $result['total_pages'] ) {
                $state['status']      = 'done';
                $state['phase']       = 'done';
                $state['finished_at'] = time();
            }

            update_option( CVR2_STATE_OPTION, $state, false );

            wp_send_json_success(
                array(
                    'done'    => 'done' === $state['status'],
                    'state'   => $state,
                    'message' => 'A resolver relações entre produtos.',
                )
            );
        }

        // Modo JavaScript: exatamente 1 produto por pedido AJAX/PHP.
        $per_page = 1;
        $page     = max( 1, absint( $state['page'] ?? 1 ) );

        $result = CVR2_REST_Client::request(
            'products',
            array(
                'per_page' => $per_page,
                'page'     => $page,
                'orderby'  => 'id',
                'order'    => 'asc',
                'status'   => 'any',
            ),
            60
        );

        if ( is_wp_error( $result ) ) {
            $result = CVR2_REST_Client::request(
                'products',
                array(
                    'per_page' => $per_page,
                    'page'     => $page,
                    'orderby'  => 'id',
                    'order'    => 'asc',
                ),
                60
            );
        }

        if ( is_wp_error( $result ) ) {
            $state['status']     = 'error';
            $state['updated_at'] = time();
            $state['errors'][]   = $result->get_error_message();
            $state['errors']     = array_slice( $state['errors'], -20 );
            update_option( CVR2_STATE_OPTION, $state, false );

            wp_send_json_error(
                array(
                    'message' => $result->get_error_message(),
                    'state'   => $state,
                )
            );
        }

        $state['total']       = (int) $result['total'];
        $state['total_pages'] = max( 1, (int) $result['total_pages'] );

        foreach ( (array) $result['data'] as $source_product ) {
            $was_existing = self::target_exists_for_source( (array) $source_product );
            $imported = CVR2_Product_Importer::import_source_product( (array) $source_product );

            if ( is_wp_error( $imported ) ) {
                $source_id = absint( $source_product['id'] ?? 0 );
                if ( str_starts_with( (string) $imported->get_error_code(), 'cvr2_slug_' ) ) {
                    $state['slug_errors'] = absint( $state['slug_errors'] ?? 0 ) + 1;
                }
                $state['errors'][] = '#' . $source_id . ': ' . $imported->get_error_message();
                $state['errors']   = array_slice( $state['errors'], -20 );
            } else {
                $was_existing ? $state['updated']++ : $state['created']++;
            }

            $state['processed']++;
        }

        $state['updated_at'] = time();

        if ( $page >= (int) $state['total_pages'] || empty( $result['data'] ) ) {
            $state['phase'] = 'relations';
            $state['page']  = 1;
        } else {
            $state['page'] = $page + 1;
        }

        update_option( CVR2_STATE_OPTION, $state, false );

        wp_send_json_success(
            array(
                'done'    => false,
                'state'   => $state,
                'message' => sprintf(
                    'Produtos processados: %s / %s.',
                    number_format_i18n( (int) $state['processed'] ),
                    number_format_i18n( (int) $state['total'] )
                ),
            )
        );
    }

    private static function target_exists_for_source( array $source ): bool {
        $source_id = absint( $source['id'] ?? 0 );
        $sku       = wc_clean( (string) ( $source['sku'] ?? '' ) );
        $slug      = sanitize_title( (string) ( $source['slug'] ?? '' ) );

        if ( $source_id ) {
            $ids = get_posts(
                array(
                    'post_type'      => 'product',
                    'post_status'    => 'any',
                    'posts_per_page' => 1,
                    'fields'         => 'ids',
                    'meta_key'       => '_cvr2_source_product_id',
                    'meta_value'     => $source_id,
                    'no_found_rows'  => true,
                )
            );
            if ( $ids ) {
                return true;
            }
        }

        if ( $sku && wc_get_product_id_by_sku( $sku ) ) {
            return true;
        }

        return $slug && get_page_by_path( $slug, OBJECT, 'product' ) instanceof WP_Post;
    }

    public static function render(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'Sem permissões.', 'cv-r2-media-linker' ) );
        }

        $settings = CVR2_REST_Client::settings();
        $state    = (array) get_option( CVR2_STATE_OPTION, array() );
        $nonce    = wp_create_nonce( 'cvr2_import' );
        ?>
        <div class="wrap cvr2-admin">
            <h1>CHAVE VERTICAL — Importação REST + R2</h1>
            <p>Importa produtos do site original por WooCommerce REST API, preservando slugs, dados WooCommerce, variações, metadados Rank Math e associações de imagens.</p>

            <?php if ( isset( $_GET['cvr2_saved'] ) ) : ?>
                <div class="notice notice-success is-dismissible"><p>Configuração guardada.</p></div>
            <?php endif; ?>

            <style>
                .cvr2-grid{display:grid;grid-template-columns:minmax(0,1.15fr) minmax(320px,.85fr);gap:18px;max-width:1200px}
                .cvr2-card{padding:20px;border:1px solid #dcdcde;border-radius:10px;background:#fff}
                .cvr2-card h2{margin-top:0}.cvr2-row{display:grid;grid-template-columns:180px minmax(0,1fr);gap:14px;align-items:center;margin:12px 0}
                .cvr2-row input{width:100%}.cvr2-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:16px}
                .cvr2-log{min-height:72px;padding:12px;border:1px solid #dcdcde;background:#f6f7f7;white-space:pre-wrap}
                .cvr2-progress{height:14px;margin:12px 0;overflow:hidden;border-radius:999px;background:#e5e5e5}
                .cvr2-progress>span{height:100%;display:block;width:0;background:#00a32a;transition:width .2s}
                .cvr2-stats{display:grid;grid-template-columns:repeat(5,1fr);gap:8px}.cvr2-stat{padding:10px;background:#f6f7f7;border-radius:7px}
                .cvr2-stat strong{display:block;font-size:18px}
                @media(max-width:900px){.cvr2-grid{grid-template-columns:1fr}.cvr2-row{grid-template-columns:1fr}.cvr2-stats{grid-template-columns:1fr 1fr}}
            </style>

            <div class="cvr2-grid">
                <section class="cvr2-card">
                    <h2>Origem REST</h2>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <input type="hidden" name="action" value="cvr2_save_settings">
                        <?php wp_nonce_field( 'cvr2_save_settings' ); ?>

                        <div class="cvr2-row">
                            <label for="cvr2-source-url"><strong>Site original</strong></label>
                            <input id="cvr2-source-url" name="source_url" type="url" value="<?php echo esc_attr( (string) $settings['source_url'] ); ?>" required>
                        </div>
                        <div class="cvr2-row">
                            <label for="cvr2-r2-url"><strong>URL pública R2</strong></label>
                            <input id="cvr2-r2-url" name="r2_base_url" type="url" value="<?php echo esc_attr( (string) $settings['r2_base_url'] ); ?>" required>
                        </div>
                        <div class="cvr2-row">
                            <label for="cvr2-ck"><strong>Consumer key</strong></label>
                            <input id="cvr2-ck" name="source_ck" type="password" value="" autocomplete="new-password" placeholder="<?php echo CVR2_REST_Client::consumer_key() ? 'Configurada — deixar vazio para manter' : 'ck_…'; ?>">
                        </div>
                        <div class="cvr2-row">
                            <label for="cvr2-cs"><strong>Consumer secret</strong></label>
                            <input id="cvr2-cs" name="source_cs" type="password" value="" autocomplete="new-password" placeholder="<?php echo CVR2_REST_Client::consumer_secret() ? 'Configurada — deixar vazio para manter' : 'cs_…'; ?>">
                        </div>
                        <div class="cvr2-row">
                            <strong>Modo de execução</strong>
                            <div>
                                <strong>JavaScript — 1 produto por pedido</strong><br>
                                <small>Cada produto corre num pedido PHP independente. O processo completo pode continuar por milhares de produtos sem depender do max_execution_time de uma única execução PHP.</small>
                                <input type="hidden" name="batch_size" value="1">
                            </div>
                        </div>

                        <p><button class="button button-primary" type="submit">Guardar configuração</button></p>
                    </form>

                    <p><strong>Prioridade de imagem:</strong> R2 WEBP existente → registar na Media Library e associar → se não existir, descarregar a origem e gerar WEBP 750×750, qualidade 90, fundo branco.</p>
                    <p><strong>Slug obrigatório:</strong> o slug recebido da origem tem de ficar exatamente igual. Se o WordPress tentar criar <code>-2</code>, <code>-3</code> ou qualquer outra alteração, esse produto é marcado com erro e não é considerado importado.</p>
                    <p><strong>Identidade do produto:</strong> ID original → SKU → slug. Depois da importação o slug fica bloqueado contra alterações acidentais.</p>
                </section>

                <section class="cvr2-card">
                    <h2>Importação</h2>
                    <div class="cvr2-actions">
                        <button class="button" type="button" data-cvr2-action="test">1. Testar REST</button>
                        <button class="button" type="button" data-cvr2-action="structure">2. Sincronizar estrutura</button>
                        <button class="button button-primary" type="button" data-cvr2-action="start">3. Iniciar / recomeçar importação</button>
                        <button class="button" type="button" data-cvr2-action="resume">Retomar</button>
                        <button class="button" type="button" data-cvr2-action="pause">Pausar</button>
                        <button class="button" type="button" data-cvr2-action="reset">Limpar estado</button>
                    </div>

                    <div class="cvr2-progress" aria-hidden="true"><span data-cvr2-progress></span></div>
                    <div class="cvr2-stats">
                        <div class="cvr2-stat"><span>Processados</span><strong data-stat="processed">0</strong></div>
                        <div class="cvr2-stat"><span>Total</span><strong data-stat="total">0</strong></div>
                        <div class="cvr2-stat"><span>Criados</span><strong data-stat="created">0</strong></div>
                        <div class="cvr2-stat"><span>Atualizados</span><strong data-stat="updated">0</strong></div>
                        <div class="cvr2-stat"><span>Erros de slug</span><strong data-stat="slug_errors">0</strong></div>
                    </div>
                    <h3>Estado</h3>
                    <div class="cvr2-log" data-cvr2-log>Pronto.</div>

                    <?php if ( $state ) : ?>
                        <p><small>Estado guardado: <?php echo esc_html( wp_json_encode( $state, JSON_UNESCAPED_UNICODE ) ); ?></small></p>
                    <?php endif; ?>
                </section>
            </div>
        </div>

        <script>
        (() => {
            const nonce = <?php echo wp_json_encode( $nonce ); ?>;
            const log = document.querySelector('[data-cvr2-log]');
            const progress = document.querySelector('[data-cvr2-progress]');
            const buttons = document.querySelectorAll('[data-cvr2-action]');
            let running = false;

            const write = (message) => { if (log) log.textContent = String(message || ''); };
            const setBusy = (busy) => buttons.forEach((button) => {
                if (button.dataset.cvr2Action === 'pause') {
                    button.disabled = !running;
                    return;
                }
                button.disabled = busy;
            });
            const yieldBrowser = () => new Promise((resolve) => window.setTimeout(resolve, 75));

            function renderState(state) {
                state = state || {};
                ['processed','total','created','updated','slug_errors'].forEach((key) => {
                    const el = document.querySelector('[data-stat="' + key + '"]');
                    if (el) el.textContent = Number(state[key] || 0).toLocaleString('pt-PT');
                });

                const total = Number(state.total || 0);
                const processed = Number(state.processed || 0);
                if (progress) progress.style.width = total > 0 ? Math.min(100, (processed / total) * 100) + '%' : '0%';
            }

            async function call(action) {
                const body = new URLSearchParams({action, nonce});
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

            async function loop() {
                if (running) return;
                running = true;
                setBusy(true);

                try {
                    while (running) {
                        const data = await call('cvr2_import_batch');
                        renderState(data.state);
                        write(data.message || (data.done ? 'Importação concluída.' : 'A processar…'));

                        if (data.done) {
                            running = false;
                            write('Importação concluída. Slugs verificados, relações e dados processados.');
                            break;
                        }

                        // Entrega o controlo ao browser antes do próximo produto.
                        await yieldBrowser();
                    }
                } catch (error) {
                    running = false;
                    write(error.message || error);
                } finally {
                    setBusy(false);
                }
            }

            document.querySelector('[data-cvr2-action="test"]')?.addEventListener('click', async () => {
                setBusy(true);
                try { const data = await call('cvr2_test_source'); write(data.message); }
                catch (error) { write(error.message || error); }
                finally { setBusy(false); }
            });

            document.querySelector('[data-cvr2-action="structure"]')?.addEventListener('click', async () => {
                setBusy(true);
                write('A sincronizar categorias, etiquetas, marcas e atributos…');
                try { const data = await call('cvr2_sync_structure'); write(data.message); }
                catch (error) { write(error.message || error); }
                finally { setBusy(false); }
            });

            document.querySelector('[data-cvr2-action="start"]')?.addEventListener('click', async () => {
                setBusy(true);
                try {
                    const data = await call('cvr2_start_import');
                    renderState(data.state);
                    write(data.message);
                    setBusy(false);
                    loop();
                } catch (error) {
                    write(error.message || error);
                    setBusy(false);
                }
            });

            document.querySelector('[data-cvr2-action="resume"]')?.addEventListener('click', () => loop());

            document.querySelector('[data-cvr2-action="pause"]')?.addEventListener('click', () => {
                running = false;
                setBusy(false);
                write('Importação pausada no browser. O progresso ficou guardado e pode ser retomado.');
            });

            document.querySelector('[data-cvr2-action="reset"]')?.addEventListener('click', async () => {
                setBusy(true);
                try {
                    const data = await call('cvr2_reset_import');
                    renderState({});
                    write(data.message);
                } catch (error) {
                    write(error.message || error);
                } finally {
                    setBusy(false);
                }
            });
        })();
        </script>
        <?php
    }
}
