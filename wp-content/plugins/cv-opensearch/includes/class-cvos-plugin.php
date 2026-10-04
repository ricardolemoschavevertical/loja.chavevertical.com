<?php
defined( 'ABSPATH' ) || exit;

final class CVOS_Plugin {
    private static ?self $instance = null;

    public CVOS_Settings $settings;
    public CVOS_Client $client;
    public CVOS_Serializer $serializer;
    public CVOS_Indexer $indexer;
    public CVOS_Search $search;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->settings   = new CVOS_Settings();
        $this->client     = new CVOS_Client( $this->settings );
        $this->serializer = new CVOS_Serializer( $this->settings );
        $this->indexer    = new CVOS_Indexer( $this->settings, $this->client, $this->serializer );
        $this->search     = new CVOS_Search( $this->settings, $this->client );

        add_action( 'admin_menu', array( $this, 'admin_menu' ), 40 );
        add_action( 'admin_post_cvos_save_settings', array( $this, 'save_settings' ) );
        add_action( 'admin_post_cvos_test_connection', array( $this, 'test_connection' ) );
        add_action( 'admin_post_cvos_start_reindex', array( $this, 'start_reindex' ) );
        add_filter(
            'plugin_action_links_' . plugin_basename( CVOS_FILE ),
            array( $this, 'action_links' )
        );
    }

    public static function activate(): void {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return;
        }

        $settings = new CVOS_Settings();

        if ( false === get_option( CVOS_Settings::OPTION, false ) ) {
            add_option( CVOS_Settings::OPTION, CVOS_Settings::defaults(), '', false );
        }

        if ( false === get_option( CVOS_Settings::SECRET_OPTION, false ) ) {
            add_option( CVOS_Settings::SECRET_OPTION, '', '', false );
        }

        self::create_analytics_table();

        $client     = new CVOS_Client( $settings );
        $serializer = new CVOS_Serializer( $settings );
        $indexer    = new CVOS_Indexer( $settings, $client, $serializer );
        $indexer->update_schedule();
    }

    public static function deactivate(): void {
        wp_clear_scheduled_hook( 'cvos_scheduled_reindex' );
        wp_clear_scheduled_hook( 'cvos_reindex_batch' );
        wp_clear_scheduled_hook( 'cvos_index_product' );
        wp_clear_scheduled_hook( 'cvos_delete_product' );
    }

    private static function create_analytics_table(): void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table   = $wpdb->prefix . 'cvos_search_analytics';
        $charset = $wpdb->get_charset_collate();

        dbDelta(
            "CREATE TABLE {$table} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                day DATE NOT NULL,
                query_hash CHAR(64) NOT NULL,
                query VARCHAR(191) NOT NULL,
                result_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
                hits BIGINT UNSIGNED NOT NULL DEFAULT 1,
                last_seen DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY day_query (day, query_hash),
                KEY hits (hits),
                KEY last_seen (last_seen)
            ) {$charset};"
        );
    }

    public function action_links( array $links ): array {
        array_unshift(
            $links,
            '<a href="' . esc_url( admin_url( 'admin.php?page=cv-opensearch' ) ) . '">' . esc_html__( 'Configurar', 'cv-opensearch' ) . '</a>'
        );
        return $links;
    }

    public function admin_menu(): void {
        add_submenu_page(
            'woocommerce',
            __( 'OpenSearch', 'cv-opensearch' ),
            __( 'OpenSearch', 'cv-opensearch' ),
            'manage_woocommerce',
            'cv-opensearch',
            array( $this, 'render_admin' )
        );
    }

    public function save_settings(): void {
        $this->guard_admin( 'cvos_save_settings' );

        $posted = isset( $_POST['settings'] ) && is_array( $_POST['settings'] )
            ? wp_unslash( $_POST['settings'] )
            : array();

        $settings = $this->settings->sanitize( $posted );
        update_option( CVOS_Settings::OPTION, $settings, false );

        $secret = isset( $_POST['cvos_secret'] )
            ? trim( (string) wp_unslash( $_POST['cvos_secret'] ) )
            : '';

        if ( '' !== $secret ) {
            update_option( CVOS_Settings::SECRET_OPTION, $secret, false );
        }

        $this->indexer->update_schedule();

        $this->redirect_admin( 'saved', 'Configuração guardada.' );
    }

    public function test_connection(): void {
        $this->guard_admin( 'cvos_test_connection' );

        $info   = $this->client->info();
        $health = $this->client->health();

        if ( is_wp_error( $info ) ) {
            $this->redirect_admin( 'error', $info->get_error_message() );
        }

        if ( is_wp_error( $health ) ) {
            $this->redirect_admin( 'error', $health->get_error_message() );
        }

        $version = sanitize_text_field( (string) ( $info['version']['number'] ?? '' ) );
        $cluster = sanitize_text_field( (string) ( $health['cluster_name'] ?? '' ) );
        $status  = sanitize_key( (string) ( $health['status'] ?? '' ) );

        $this->redirect_admin(
            'tested',
            sprintf(
                'Ligação OK — OpenSearch %1$s · cluster %2$s · estado %3$s.',
                $version ?: '?',
                $cluster ?: '?',
                $status ?: '?'
            )
        );
    }

    public function start_reindex(): void {
        $this->guard_admin( 'cvos_start_reindex' );

        if ( ! $this->settings->configured() ) {
            $this->redirect_admin( 'error', 'Configure primeiro o servidor OpenSearch.' );
        }

        $this->indexer->start_full_reindex();
        $state = get_option( 'cvos_reindex_state', array() );

        if ( 'error' === ( $state['status'] ?? '' ) ) {
            $this->redirect_admin( 'error', (string) ( $state['message'] ?? 'Erro ao iniciar a reindexação.' ) );
        }

        $this->redirect_admin( 'reindex', 'Reindexação total iniciada em segundo plano.' );
    }

    private function guard_admin( string $action ): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'Sem permissão.', 'cv-opensearch' ), 403 );
        }

        check_admin_referer( $action );
    }

    private function redirect_admin( string $status, string $message ): void {
        wp_safe_redirect(
            add_query_arg(
                array(
                    'page'         => 'cv-opensearch',
                    'cvos_status'  => sanitize_key( $status ),
                    'cvos_message' => rawurlencode( $message ),
                ),
                admin_url( 'admin.php' )
            )
        );
        exit;
    }

    public function render_admin(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        $settings = $this->settings->all();
        $state    = get_option( 'cvos_reindex_state', array() );
        $message  = isset( $_GET['cvos_message'] ) ? sanitize_text_field( rawurldecode( wp_unslash( $_GET['cvos_message'] ) ) ) : '';
        $status   = isset( $_GET['cvos_status'] ) ? sanitize_key( wp_unslash( $_GET['cvos_status'] ) ) : '';
        $astro_api = rest_url( 'cv-opensearch/v1/catalog/products' );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'CV OpenSearch', 'cv-opensearch' ); ?> <small style="font-size:13px;color:#646970">v<?php echo esc_html( CVOS_VERSION ); ?></small></h1>
            <p><?php esc_html_e( 'WooCommerce é a fonte oficial. O plugin sincroniza o catálogo para um OpenSearch local ou remoto e disponibiliza a mesma pesquisa ao WordPress e ao Astro.', 'cv-opensearch' ); ?></p>

            <?php if ( $message ) : ?>
                <div class="notice <?php echo 'error' === $status ? 'notice-error' : 'notice-success'; ?> is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
            <?php endif; ?>

            <div style="max-width:1180px;display:grid;grid-template-columns:minmax(0,2fr) minmax(280px,1fr);gap:22px;align-items:start">
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="background:#fff;border:1px solid #dcdcde;padding:22px">
                    <input type="hidden" name="action" value="cvos_save_settings">
                    <?php wp_nonce_field( 'cvos_save_settings' ); ?>

                    <h2><?php esc_html_e( 'Ligação ao servidor OpenSearch', 'cv-opensearch' ); ?></h2>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th><label for="cvos-endpoint">Endpoint</label></th>
                            <td>
                                <input id="cvos-endpoint" class="regular-text code" type="url" name="settings[endpoint]" value="<?php echo esc_attr( $settings['endpoint'] ); ?>" placeholder="https://search.exemplo.pt:9200">
                                <p class="description">Pode apontar para outro servidor. Não coloque utilizador/password no URL.</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Autenticação</th>
                            <td>
                                <select name="settings[auth_type]">
                                    <option value="basic" <?php selected( $settings['auth_type'], 'basic' ); ?>>Basic Auth</option>
                                    <option value="bearer" <?php selected( $settings['auth_type'], 'bearer' ); ?>>Bearer Token</option>
                                    <option value="none" <?php selected( $settings['auth_type'], 'none' ); ?>>Sem autenticação</option>
                                </select>
                                <input type="text" name="settings[username]" value="<?php echo esc_attr( $settings['username'] ); ?>" placeholder="Utilizador">
                                <input type="password" name="cvos_secret" value="" autocomplete="new-password" placeholder="<?php echo $this->settings->secret() ? esc_attr__( 'Segredo já guardado — vazio mantém', 'cv-opensearch' ) : esc_attr__( 'Password / token', 'cv-opensearch' ); ?>">
                            </td>
                        </tr>
                        <tr>
                            <th><label for="cvos-index">Índice</label></th>
                            <td><input id="cvos-index" class="regular-text code" type="text" name="settings[index_name]" value="<?php echo esc_attr( $settings['index_name'] ); ?>"><p class="description">Para este servidor use um nome dentro de <code>wordpress-chavevertical-*</code>, por exemplo <code>wordpress-chavevertical-products</code>.</p></td>
                        </tr>
                        <tr>
                            <th><label for="cvos-ca-file">Certificado CA</label></th>
                            <td><input id="cvos-ca-file" class="large-text code" type="text" name="settings[ca_file]" value="<?php echo esc_attr( $settings['ca_file'] ?? '' ); ?>" placeholder="/caminho/privado/opensearch-ca.pem"><p class="description">Caminho absoluto no servidor WordPress para <code>opensearch-ca.pem</code>. O ficheiro deve ficar fora da pasta pública sempre que possível.</p></td>
                        </tr>
                        <tr>
                            <th>Segurança TLS</th>
                            <td>
                                <label><input type="checkbox" name="settings[verify_ssl]" value="yes" <?php checked( $settings['verify_ssl'], 'yes' ); ?>> Verificar certificado SSL</label><br>
                                <label><input type="checkbox" name="settings[allow_insecure_http]" value="yes" <?php checked( $settings['allow_insecure_http'], 'yes' ); ?>> Permitir HTTP sem TLS apenas numa rede privada</label>
                            </td>
                        </tr>
                        <tr>
                            <th>Timeout</th>
                            <td><input type="number" min="2" max="60" name="settings[timeout]" value="<?php echo esc_attr( $settings['timeout'] ); ?>"> segundos</td>
                        </tr>
                    </table>

                    <h2><?php esc_html_e( 'WordPress, WooCommerce e Astro', 'cv-opensearch' ); ?></h2>
                    <table class="form-table" role="presentation">
                        <tr><th>Motor</th><td><label><input type="checkbox" name="settings[enabled]" value="yes" <?php checked( $settings['enabled'], 'yes' ); ?>> Ativar OpenSearch</label></td></tr>
                        <tr><th>WooCommerce</th><td><label><input type="checkbox" name="settings[replace_native_search]" value="yes" <?php checked( $settings['replace_native_search'], 'yes' ); ?>> Usar OpenSearch na pesquisa nativa de produtos</label></td></tr>
                        <tr><th>Pesquisa instantânea</th><td><label><input type="checkbox" name="settings[enable_instant_search]" value="yes" <?php checked( $settings['enable_instant_search'], 'yes' ); ?>> Sugestões enquanto escreve</label></td></tr>
                        <tr><th>Astro</th><td><label><input type="checkbox" name="settings[enable_astro_api]" value="yes" <?php checked( $settings['enable_astro_api'], 'yes' ); ?>> Disponibilizar API pública só de leitura para o Astro</label><p class="description"><code><?php echo esc_html( $astro_api ); ?></code></p></td></tr>
                        <tr><th>URL pública</th><td><input class="regular-text code" type="url" name="settings[public_base_url]" value="<?php echo esc_attr( $settings['public_base_url'] ); ?>"><p class="description">Base usada nos links públicos dos produtos; o checkout continua no WooCommerce.</p></td></tr>
                        <tr><th>Mínimo caracteres</th><td><input type="number" min="1" max="10" name="settings[min_chars]" value="<?php echo esc_attr( $settings['min_chars'] ); ?>"></td></tr>
                        <tr><th>Sugestões</th><td><input type="number" min="3" max="30" name="settings[suggestions_limit]" value="<?php echo esc_attr( $settings['suggestions_limit'] ); ?>"></td></tr>
                        <tr><th>Resultados/página</th><td><input type="number" min="6" max="100" name="settings[results_per_page]" value="<?php echo esc_attr( $settings['results_per_page'] ); ?>"></td></tr>
                        <tr><th>Stock</th><td><label><input type="checkbox" name="settings[exclude_out_of_stock]" value="yes" <?php checked( $settings['exclude_out_of_stock'], 'yes' ); ?>> Excluir esgotados</label></td></tr>
                    </table>

                    <h2><?php esc_html_e( 'Campos, relevância e sinónimos', 'cv-opensearch' ); ?></h2>
                    <table class="form-table" role="presentation">
                        <?php
                        $fields = array(
                            'search_in_sku'               => 'SKU / referência',
                            'search_in_brands'            => 'Marcas',
                            'search_in_categories'        => 'Categorias',
                            'search_in_tags'              => 'Etiquetas',
                            'search_in_attributes'        => 'Atributos',
                            'search_in_short_description' => 'Descrição breve',
                            'search_in_description'       => 'Descrição longa',
                            'search_in_custom_fields'     => 'Campos personalizados',
                        );
                        foreach ( $fields as $key => $label ) :
                            ?>
                            <tr><th><?php echo esc_html( $label ); ?></th><td><label><input type="checkbox" name="settings[<?php echo esc_attr( $key ); ?>]" value="yes" <?php checked( $settings[ $key ], 'yes' ); ?>> Pesquisar neste campo</label></td></tr>
                        <?php endforeach; ?>
                        <tr><th>Campos personalizados</th><td><input class="large-text code" type="text" name="settings[custom_fields]" value="<?php echo esc_attr( $settings['custom_fields'] ); ?>" placeholder="campo_1,campo_2"></td></tr>
                        <tr><th>Fuzzy</th><td><select name="settings[fuzzy_mode]"><option value="off" <?php selected( $settings['fuzzy_mode'], 'off' ); ?>>Desligado</option><option value="normal" <?php selected( $settings['fuzzy_mode'], 'normal' ); ?>>Normal</option><option value="aggressive" <?php selected( $settings['fuzzy_mode'], 'aggressive' ); ?>>Agressivo</option></select></td></tr>
                        <tr><th>Sinónimos</th><td><textarea class="large-text code" rows="7" name="settings[synonyms]" placeholder="rebarbadora, esmeriladora&#10;porta-paletes, porta paletes"><?php echo esc_textarea( $settings['synonyms'] ); ?></textarea><p class="description">Um grupo por linha, termos separados por vírgulas.</p></td></tr>
                    </table>

                    <h3>Pesos de relevância</h3>
                    <div style="display:grid;grid-template-columns:repeat(2,minmax(220px,1fr));gap:8px 18px;max-width:760px">
                        <?php
                        $weights = array(
                            'weight_name' => 'Nome',
                            'weight_sku' => 'SKU',
                            'weight_variation_sku' => 'SKU variação',
                            'weight_brands' => 'Marca',
                            'weight_categories' => 'Categoria',
                            'weight_tags' => 'Etiqueta',
                            'weight_attributes' => 'Atributos',
                            'weight_short_description' => 'Descrição breve',
                            'weight_description' => 'Descrição',
                            'weight_custom_fields' => 'Campos personalizados',
                        );
                        foreach ( $weights as $key => $label ) :
                            ?>
                            <label style="display:flex;justify-content:space-between;align-items:center;gap:10px"><?php echo esc_html( $label ); ?><input style="width:90px" type="number" step="0.1" min="0.1" max="50" name="settings[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $settings[ $key ] ); ?>"></label>
                        <?php endforeach; ?>
                    </div>

                    <h2><?php esc_html_e( 'Filtros permanentes', 'cv-opensearch' ); ?></h2>
                    <table class="form-table" role="presentation">
                        <tr><th>Modo</th><td><select name="settings[filter_mode]"><option value="exclude" <?php selected( $settings['filter_mode'], 'exclude' ); ?>>Excluir IDs indicados</option><option value="include" <?php selected( $settings['filter_mode'], 'include' ); ?>>Incluir apenas IDs indicados</option></select></td></tr>
                        <tr><th>Produtos</th><td><input class="large-text code" type="text" name="settings[filter_product_ids]" value="<?php echo esc_attr( $settings['filter_product_ids'] ); ?>"></td></tr>
                        <tr><th>Categorias</th><td><input class="large-text code" type="text" name="settings[filter_category_ids]" value="<?php echo esc_attr( $settings['filter_category_ids'] ); ?>"></td></tr>
                        <tr><th>Etiquetas</th><td><input class="large-text code" type="text" name="settings[filter_tag_ids]" value="<?php echo esc_attr( $settings['filter_tag_ids'] ); ?>"></td></tr>
                        <tr><th>Marcas</th><td><input class="large-text code" type="text" name="settings[filter_brand_ids]" value="<?php echo esc_attr( $settings['filter_brand_ids'] ); ?>"></td></tr>
                    </table>

                    <h2><?php esc_html_e( 'Sincronização e analytics', 'cv-opensearch' ); ?></h2>
                    <table class="form-table" role="presentation">
                        <tr><th>Reindexação programada</th><td><label><input type="checkbox" name="settings[schedule_enabled]" value="yes" <?php checked( $settings['schedule_enabled'], 'yes' ); ?>> Ativa</label> <select name="settings[schedule_interval]"><option value="weekly" <?php selected( $settings['schedule_interval'], 'weekly' ); ?>>Semanal</option><option value="daily" <?php selected( $settings['schedule_interval'], 'daily' ); ?>>Diária</option></select></td></tr>
                        <tr><th>Analytics</th><td><label><input type="checkbox" name="settings[analytics_enabled]" value="yes" <?php checked( $settings['analytics_enabled'], 'yes' ); ?>> Agregar pesquisas por dia</label> <input type="number" min="7" max="365" name="settings[analytics_retention_days]" value="<?php echo esc_attr( $settings['analytics_retention_days'] ); ?>"> dias</td></tr>
                    </table>

                    <?php submit_button( __( 'Guardar configuração', 'cv-opensearch' ) ); ?>
                </form>

                <aside>
                    <section style="background:#fff;border:1px solid #dcdcde;padding:18px;margin-bottom:18px">
                        <h2 style="margin-top:0">Estado</h2>
                        <p><strong>Servidor:</strong> <?php echo $this->settings->configured() ? esc_html( $this->settings->endpoint() ) : '—'; ?></p>
                        <p><strong>Índice:</strong> <code><?php echo esc_html( $this->settings->index_name() ); ?></code></p>
                        <p><strong>Motor:</strong> <?php echo $this->settings->is_yes( 'enabled' ) ? 'Ativo' : 'Desativado'; ?></p>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                            <input type="hidden" name="action" value="cvos_test_connection">
                            <?php wp_nonce_field( 'cvos_test_connection' ); ?>
                            <?php submit_button( 'Testar ligação', 'secondary', 'submit', false ); ?>
                        </form>
                    </section>

                    <section style="background:#fff;border:1px solid #dcdcde;padding:18px;margin-bottom:18px">
                        <h2 style="margin-top:0">Índice do catálogo</h2>
                        <?php if ( $state ) : ?>
                            <p><strong>Estado:</strong> <?php echo esc_html( $state['status'] ?? '—' ); ?></p>
                            <p><strong>Processados:</strong> <?php echo esc_html( number_format_i18n( (int) ( $state['processed'] ?? 0 ) ) ); ?> / <?php echo esc_html( number_format_i18n( (int) ( $state['total'] ?? 0 ) ) ); ?></p>
                            <?php if ( ! empty( $state['message'] ) ) : ?><p><?php echo esc_html( $state['message'] ); ?></p><?php endif; ?>
                        <?php endif; ?>
                        <p class="description">A reindexação total recria o índice e volta a carregar os produtos publicados em lotes de 50. Atualizações normais são incrementais.</p>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Recriar o índice OpenSearch e iniciar uma reindexação total?');">
                            <input type="hidden" name="action" value="cvos_start_reindex">
                            <?php wp_nonce_field( 'cvos_start_reindex' ); ?>
                            <?php submit_button( 'Reindexação total', 'secondary', 'submit', false ); ?>
                        </form>
                    </section>

                    <section style="background:#fff;border:1px solid #dcdcde;padding:18px">
                        <h2 style="margin-top:0">Segurança</h2>
                        <p>O Astro não recebe credenciais do OpenSearch. Consulta a API somente-leitura deste plugin.</p>
                        <p>Em produção pode guardar segredos em <code>wp-config.php</code> através de <code>CVOS_ENDPOINT</code>, <code>CVOS_USERNAME</code>, <code>CVOS_PASSWORD</code> ou <code>CVOS_BEARER_TOKEN</code>. O caminho da CA também pode ser definido em <code>CVOS_CA_FILE</code>.</p>
                    </section>
                </aside>
            </div>
        </div>
        <?php
    }
}
