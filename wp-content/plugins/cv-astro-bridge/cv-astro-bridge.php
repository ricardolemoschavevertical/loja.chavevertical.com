<?php
/**
 * Plugin Name: CV Astro Bridge
 * Description: Ponte entre WooCommerce, Astro e Cloudflare Worker da Chave Vertical.
 * Version: 0.3.4
 * Author: Chave Vertical
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * WC requires at least: 8.5
 * Text Domain: cv-astro-bridge
 */

defined( 'ABSPATH' ) || exit;

define( 'CVAB_VERSION', '0.3.4' );
define( 'CVAB_EXPECTED_WORKER_RELEASE', '2026.10.05.07' );
define( 'CVAB_STATUS_OPTION', 'cvab_worker_status' );
define( 'CVAB_FILE', __FILE__ );
define( 'CVAB_OPTION', 'cvab_settings' );

final class CV_Astro_Bridge {
    private static ?self $instance = null;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', array( $this, 'admin_menu' ), 30 );
        add_filter( 'cv_admin_modules', array( $this, 'register_cv_module' ) );

        add_action( 'admin_post_cvab_save_settings', array( $this, 'save_settings' ) );
        add_action( 'admin_post_cvab_test_worker', array( $this, 'test_worker' ) );
        add_action( 'admin_post_cvab_purge_product', array( $this, 'purge_product_action' ) );

        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

        add_action( 'add_meta_boxes_product', array( $this, 'add_product_metabox' ) );

        add_action( 'woocommerce_update_product', array( $this, 'product_changed' ), 30, 1 );
        add_action( 'woocommerce_new_product', array( $this, 'product_changed' ), 30, 1 );
        add_action( 'woocommerce_update_product_variation', array( $this, 'variation_changed' ), 30, 1 );
    }

    public static function activate(): void {
        if ( false === get_option( CVAB_OPTION, false ) ) {
            add_option(
                CVAB_OPTION,
                array(
                    'worker_url'  => 'https://astro.chavevertical.com',
                    'secret'      => wp_generate_password( 48, true, true ),
                    'auto_purge'  => 'yes',
                    'auto_warm'   => 'yes',
                ),
                '',
                false
            );
        }
    }

    private function settings(): array {
        return wp_parse_args(
            (array) get_option( CVAB_OPTION, array() ),
            array(
                'worker_url' => 'https://astro.chavevertical.com',
                'secret'     => '',
                'auto_purge' => 'yes',
                'auto_warm'  => 'yes',
            )
        );
    }

    public function register_cv_module( array $modules ): array {
        $modules[] = array(
            'title'       => 'Astro / Worker',
            'description' => 'Ligação WooCommerce ↔ Astro, diagnóstico, cache e API consolidada.',
            'url'         => admin_url( 'admin.php?page=cv-astro-worker' ),
            'active'      => true,
        );
        return $modules;
    }

    public function admin_menu(): void {
        $parent = function_exists( 'cv_admin_parent_slug' ) ? cv_admin_parent_slug() : 'woocommerce';

        add_submenu_page(
            $parent,
            __( 'Astro / Worker', 'cv-astro-bridge' ),
            __( 'Astro / Worker', 'cv-astro-bridge' ),
            'manage_woocommerce',
            'cv-astro-worker',
            array( $this, 'render_admin' )
        );
    }

    public function render_admin(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'Sem permissões.', 'cv-astro-bridge' ) );
        }

        $settings      = $this->settings();
        $worker_status = $this->get_worker_status();
        $status        = isset( $_GET['cvab_status'] ) ? sanitize_key( wp_unslash( $_GET['cvab_status'] ) ) : '';
        $message       = isset( $_GET['cvab_message'] ) ? sanitize_text_field( rawurldecode( wp_unslash( $_GET['cvab_message'] ) ) ) : '';

        $release_expected  = CVAB_EXPECTED_WORKER_RELEASE;
        $release_published = (string) ( $worker_status['release'] ?? '' );
        $worker_online     = ! empty( $worker_status['online'] );
        $is_published      = $worker_online && '' !== $release_published && hash_equals( $release_expected, $release_published );
        $deploy_label      = ! $worker_online ? 'INDISPONÍVEL' : ( $is_published ? 'PUBLICADO' : 'NÃO PUBLICADO' );
        $deploy_class      = ! $worker_online ? 'is-error' : ( $is_published ? 'is-ok' : 'is-warning' );
        $deploy_reason     = $this->worker_status_reason( $worker_status, $release_expected );
        ?>
        <div class="wrap">
            <h1>CHAVE VERTICAL — Astro / Worker <small style="font-size:13px;color:#646970">v<?php echo esc_html( CVAB_VERSION ); ?></small></h1>
            <p>Centro de controlo da ligação entre <code>loja.chavevertical.com</code> e <code>astro.chavevertical.com</code>.</p>

            <?php if ( $message ) : ?>
                <div class="notice notice-<?php echo 'ok' === $status ? 'success' : 'error'; ?> is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
            <?php endif; ?>

            <style>
                .cvab-grid{display:grid;grid-template-columns:minmax(0,1.3fr) minmax(320px,.7fr);gap:18px;max-width:1200px}
                .cvab-card{background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:18px;margin-top:16px}
                .cvab-card h2{margin-top:0}.cvab-actions{display:flex;gap:8px;flex-wrap:wrap}
                .cvab-status-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
                .cvab-stat{padding:12px;background:#f6f7f7;border-radius:8px}.cvab-stat strong{display:block;margin-bottom:3px}
                .cvab-deploy-status{display:flex;align-items:center;gap:8px;margin:0 0 16px;font-size:15px;font-weight:800}
                .cvab-deploy-dot{width:11px;height:11px;border-radius:50%;background:#8c8f94}
                .cvab-deploy-status.is-ok{color:#008a20}.cvab-deploy-status.is-ok .cvab-deploy-dot{background:#00a32a}
                .cvab-deploy-status.is-warning{color:#996800}.cvab-deploy-status.is-warning .cvab-deploy-dot{background:#dba617}
                .cvab-deploy-status.is-error{color:#b32d2e}.cvab-deploy-status.is-error .cvab-deploy-dot{background:#d63638}
                .cvab-release-code{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:11px}
                @media(max-width:900px){.cvab-grid{grid-template-columns:1fr}}
            </style>

            <div class="cvab-grid">
                <div>
                    <div class="cvab-card">
                        <h2>Configuração</h2>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                            <input type="hidden" name="action" value="cvab_save_settings">
                            <?php wp_nonce_field( 'cvab_save_settings' ); ?>
                            <table class="form-table" role="presentation">
                                <tr>
                                    <th><label for="cvab-worker-url">Worker / Astro</label></th>
                                    <td><input id="cvab-worker-url" type="url" class="regular-text code" name="worker_url" value="<?php echo esc_attr( $settings['worker_url'] ); ?>"></td>
                                </tr>
                                <tr>
                                    <th>Atualização automática</th>
                                    <td>
                                        <label><input type="checkbox" name="auto_purge" value="yes" <?php checked( $settings['auto_purge'], 'yes' ); ?>> Limpar cache quando o produto muda</label><br>
                                        <label><input type="checkbox" name="auto_warm" value="yes" <?php checked( $settings['auto_warm'], 'yes' ); ?>> Reaquecer a ficha depois de limpar</label>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Segredo da ponte</th>
                                    <td>
                                        <input type="password" class="regular-text code" name="secret" value="" autocomplete="new-password" placeholder="Vazio mantém o segredo atual">
                                        <p class="description">Usado apenas para assinar comandos administrativos enviados ao Worker. Nunca é enviado ao browser.</p>
                                    </td>
                                </tr>
                            </table>
                            <?php submit_button( 'Guardar configuração' ); ?>
                        </form>
                    </div>

                    <div class="cvab-card">
                        <h2>Ferramentas</h2>
                        <div class="cvab-actions">
                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                                <input type="hidden" name="action" value="cvab_test_worker">
                                <?php wp_nonce_field( 'cvab_test_worker' ); ?>
                                <?php submit_button( 'Testar Worker', 'secondary', 'submit', false ); ?>
                            </form>
                        </div>
                        <p class="description">O teste verifica o Worker, a origem WooCommerce configurada e a latência da ponte.</p>
                    </div>

                    <div class="cvab-card">
                        <h2>API Astro consolidada</h2>
                        <p><code><?php echo esc_html( rest_url( 'cv-astro/v1/product/123' ) ); ?></code></p>
                        <p><code><?php echo esc_html( rest_url( 'cv-astro/v1/product/by-slug/slug-do-produto' ) ); ?></code></p>
                        <p>Devolve produto, preço, stock, imagens, categorias, marca, atributos, upsells, cross-sells e metadados SEO necessários ao Astro.</p>
                    </div>
                </div>

                <div>
                    <div class="cvab-card">
                        <h2>Publicação Cloudflare</h2>
                        <p class="cvab-deploy-status <?php echo esc_attr( $deploy_class ); ?>">
                            <span class="cvab-deploy-dot" aria-hidden="true"></span>
                            <?php echo esc_html( $deploy_label ); ?>
                        </p>

                        <div class="cvab-status-grid">
                            <div class="cvab-stat"><strong>Worker</strong><span><?php echo esc_html( $worker_online ? 'ONLINE' : 'OFFLINE' ); ?></span></div>
                            <div class="cvab-stat"><strong>Latência</strong><span><?php echo isset( $worker_status['latency_ms'] ) ? esc_html( (string) $worker_status['latency_ms'] . ' ms' ) : '—'; ?></span></div>
                            <div class="cvab-stat"><strong>Versão esperada</strong><span class="cvab-release-code"><?php echo esc_html( $release_expected ); ?></span></div>
                            <div class="cvab-stat"><strong>Versão publicada</strong><span class="cvab-release-code"><?php echo esc_html( $release_published ?: '—' ); ?></span></div>
                            <div class="cvab-stat"><strong>Backend</strong><span><?php echo esc_html( (string) ( $worker_status['origin'] ?? 'loja.chavevertical.com' ) ); ?></span></div>
                            <div class="cvab-stat"><strong>Fonte</strong><span><?php echo esc_html( (string) ( $worker_status['source'] ?? 'WooCommerce' ) ); ?></span></div>
                            <div class="cvab-stat"><strong>D1</strong><span><?php echo ! empty( $worker_status['d1'] ) ? 'Ativo' : 'Desativado'; ?></span></div>
                            <div class="cvab-stat"><strong>Última verificação</strong><span><?php echo ! empty( $worker_status['checked_at'] ) ? esc_html( wp_date( 'd/m/Y H:i:s', (int) $worker_status['checked_at'] ) ) : 'Nunca'; ?></span></div>
                        </div>

                        <div style="margin-top:14px;padding:12px 14px;border-left:4px solid <?php echo $is_published ? '#00a32a' : '#dba617'; ?>;background:#f6f7f7">
                            <strong>Motivo:</strong>
                            <span><?php echo esc_html( $deploy_reason ); ?></span>
                        </div>
                    </div>

                    <div class="cvab-card">
                        <h2>Segurança</h2>
                        <p>Os comandos administrativos são assinados com HMAC-SHA256 e expiram rapidamente. O segredo não é incluído em HTML nem na API pública.</p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function save_settings(): void {
        $this->guard_admin( 'cvab_save_settings' );

        $old = $this->settings();
        $url = isset( $_POST['worker_url'] ) ? esc_url_raw( wp_unslash( $_POST['worker_url'] ) ) : '';

        if ( ! $url || 'https' !== wp_parse_url( $url, PHP_URL_SCHEME ) ) {
            $this->redirect_admin( 'error', 'O endereço do Worker tem de usar HTTPS.' );
        }

        $secret = isset( $_POST['secret'] ) ? trim( (string) wp_unslash( $_POST['secret'] ) ) : '';

        update_option(
            CVAB_OPTION,
            array(
                'worker_url' => untrailingslashit( $url ),
                'secret'     => '' !== $secret ? $secret : (string) $old['secret'],
                'auto_purge' => ! empty( $_POST['auto_purge'] ) ? 'yes' : 'no',
                'auto_warm'  => ! empty( $_POST['auto_warm'] ) ? 'yes' : 'no',
            ),
            false
        );

        $this->redirect_admin( 'ok', 'Configuração guardada.' );
    }

    public function test_worker(): void {
        $this->guard_admin( 'cvab_test_worker' );

        $status = $this->get_worker_status( true );

        if ( empty( $status['online'] ) ) {
            $message = ! empty( $status['error'] )
                ? 'Worker indisponível: ' . (string) $status['error']
                : 'Worker indisponível.';
            $this->redirect_admin( 'error', $message );
        }

        $published = (string) ( $status['release'] ?? '' );
        $ms        = (int) ( $status['latency_ms'] ?? 0 );

        if ( '' === $published || ! hash_equals( CVAB_EXPECTED_WORKER_RELEASE, $published ) ) {
            $reason = $this->worker_status_reason( $status, CVAB_EXPECTED_WORKER_RELEASE );
            $this->redirect_admin(
                'error',
                sprintf(
                    'Worker online em %d ms. %s',
                    $ms,
                    $reason
                )
            );
        }

        $this->redirect_admin(
            'ok',
            sprintf(
                'PUBLICADO. Worker %s online em %d ms e ligado ao WooCommerce.',
                $published,
                $ms
            )
        );
    }

    private function worker_status_reason( array $status, string $expected_release ): string {
        if ( empty( $status['online'] ) ) {
            if ( ! empty( $status['error'] ) ) {
                return 'O Worker não está acessível: ' . (string) $status['error'];
            }

            if ( ! empty( $status['http_status'] ) ) {
                return 'O endpoint de saúde respondeu HTTP ' . (int) $status['http_status'] . '.';
            }

            return 'O Worker não respondeu ao teste de saúde.';
        }

        $published = (string) ( $status['release'] ?? '' );

        if ( '' === $published ) {
            return 'O Worker responde, mas não informa a versão ativa. Isto normalmente significa que ainda está publicada uma versão anterior ao sistema de releases.';
        }

        if ( ! hash_equals( $expected_release, $published ) ) {
            return sprintf(
                'A Cloudflare ainda está a servir a versão %s. A versão esperada é %s, por isso o deploy novo ainda não foi aplicado ou falhou antes de ficar ativo.',
                $published,
                $expected_release
            );
        }

        $origin_status = (int) ( $status['origin_status'] ?? 0 );
        if ( $origin_status && 200 !== $origin_status ) {
            return 'A versão correta está publicada, mas o backend WooCommerce respondeu HTTP ' . $origin_status . '.';
        }

        return 'A versão esperada está publicada e o Worker está a comunicar corretamente com o WooCommerce.';
    }

    private function get_worker_status( bool $force = false ): array {
        $cached = (array) get_option( CVAB_STATUS_OPTION, array() );

        if (
            ! $force
            && ! empty( $cached['checked_at'] )
            && ( time() - (int) $cached['checked_at'] ) < 60
        ) {
            return $cached;
        }

        $started = microtime( true );
        $url     = untrailingslashit( (string) $this->settings()['worker_url'] ) . '/api/cv-admin/health';

        $response = wp_remote_get(
            $url,
            array(
                'timeout'     => 8,
                'redirection' => 2,
                'headers'     => array(
                    'Accept'     => 'application/json',
                    'Cache-Control' => 'no-cache',
                ),
            )
        );

        $result = array(
            'online'     => false,
            'checked_at' => time(),
            'latency_ms' => (int) round( ( microtime( true ) - $started ) * 1000 ),
        );

        if ( is_wp_error( $response ) ) {
            $result['error'] = $response->get_error_message();
            update_option( CVAB_STATUS_OPTION, $result, false );
            return $result;
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        $result['http_status'] = $code;

        if ( 200 === $code && is_array( $body ) && ! empty( $body['ok'] ) ) {
            $result['online']        = true;
            $result['release']       = sanitize_text_field( (string) ( $body['release'] ?? '' ) );
            $result['source']        = sanitize_text_field( (string) ( $body['source'] ?? '' ) );
            $result['origin']        = esc_url_raw( (string) ( $body['origin'] ?? '' ) );
            $result['origin_status'] = absint( $body['origin_status'] ?? 0 );
            $result['d1']            = ! empty( $body['d1'] );
            $result['features']      = is_array( $body['features'] ?? null ) ? $body['features'] : array();
        } else {
            $result['error'] = 'HTTP ' . $code;
        }

        update_option( CVAB_STATUS_OPTION, $result, false );

        return $result;
    }

    public function purge_product_action(): void {
        $this->guard_admin( 'cvab_purge_product' );
        $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;

        if ( ! $product_id ) {
            $this->redirect_admin( 'error', 'Produto inválido.' );
        }

        $result = $this->notify_worker_product( $product_id, true );

        if ( is_wp_error( $result ) ) {
            $this->redirect_admin( 'error', $result->get_error_message() );
        }

        $this->redirect_admin( 'ok', 'Cache do produto limpo e atualização solicitada.' );
    }

    public function product_changed( int $product_id ): void {
        $settings = $this->settings();
        if ( 'yes' !== $settings['auto_purge'] ) {
            return;
        }

        if ( wp_is_post_revision( $product_id ) || wp_is_post_autosave( $product_id ) ) {
            return;
        }

        $this->notify_worker_product( $product_id, 'yes' === $settings['auto_warm'] );
    }

    public function variation_changed( int $variation_id ): void {
        $variation = wc_get_product( $variation_id );
        if ( $variation && $variation->get_parent_id() ) {
            $this->product_changed( $variation->get_parent_id() );
        }
    }

    private function notify_worker_product( int $product_id, bool $warm ) {
        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            return new WP_Error( 'cvab_product_not_found', 'Produto não encontrado.' );
        }

        $body = wp_json_encode(
            array(
                'product_id' => $product_id,
                'slug'       => $product->get_slug(),
                'warm'       => $warm,
            )
        );

        return $this->signed_worker_request( '/api/cv-admin/cache/product', $body );
    }

    private function signed_worker_request( string $path, string $body ) {
        $settings  = $this->settings();
        $secret    = (string) $settings['secret'];
        $timestamp = (string) time();

        if ( '' === $secret ) {
            return new WP_Error( 'cvab_missing_secret', 'Falta configurar o segredo da ponte.' );
        }

        $signature = hash_hmac( 'sha256', $timestamp . "\n" . $body, $secret );

        $response = wp_remote_post(
            untrailingslashit( $settings['worker_url'] ) . $path,
            array(
                'timeout' => 15,
                'headers' => array(
                    'Content-Type'   => 'application/json',
                    'Accept'         => 'application/json',
                    'X-CV-Timestamp' => $timestamp,
                    'X-CV-Signature' => $signature,
                ),
                'body' => $body,
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code < 200 || $code >= 300 || empty( $data['ok'] ) ) {
            return new WP_Error( 'cvab_worker_error', 'Worker respondeu HTTP ' . $code . '.' );
        }

        return $data;
    }

    public function register_rest_routes(): void {
        register_rest_route(
            'cv-astro/v1',
            '/homepage',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'permission_callback' => '__return_true',
                'callback'            => array( $this, 'rest_homepage' ),
            )
        );

        register_rest_route(
            'cv-astro/v1',
            '/brand-catalog/(?P<slug>[a-zA-Z0-9\-_]+)',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'permission_callback' => '__return_true',
                'callback'            => array( $this, 'rest_brand_catalog' ),
            )
        );

        register_rest_route(
            'cv-astro/v1',
            '/brands-directory',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'permission_callback' => '__return_true',
                'callback'            => array( $this, 'rest_brands_directory' ),
            )
        );

        register_rest_route(
            'cv-astro/v1',
            '/shop-catalog',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'permission_callback' => '__return_true',
                'callback'            => array( $this, 'rest_shop_catalog' ),
            )
        );

        register_rest_route(
            'cv-astro/v1',
            '/category-catalog/(?P<slug>[a-zA-Z0-9\-_]+)',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'permission_callback' => '__return_true',
                'callback'            => array( $this, 'rest_category_catalog' ),
            )
        );

        register_rest_route(
            'cv-astro/v1',
            '/product/(?P<id>\d+)',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'permission_callback' => '__return_true',
                'callback'            => function ( WP_REST_Request $request ) {
                    return $this->rest_product( absint( $request['id'] ) );
                },
            )
        );

        register_rest_route(
            'cv-astro/v1',
            '/verify-worker-command',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'permission_callback' => '__return_true',
                'callback'            => array( $this, 'verify_worker_command' ),
            )
        );

        register_rest_route(
            'cv-astro/v1',
            '/product/by-slug/(?P<slug>[a-zA-Z0-9\-_]+)',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'permission_callback' => '__return_true',
                'callback'            => function ( WP_REST_Request $request ) {
                    $slug = sanitize_title( (string) $request['slug'] );
                    $post = get_page_by_path( $slug, OBJECT, 'product' );
                    return $post instanceof WP_Post
                        ? $this->rest_product( (int) $post->ID )
                        : new WP_Error( 'cvab_not_found', 'Produto não encontrado.', array( 'status' => 404 ) );
                },
            )
        );
    }

    public function rest_homepage() {
        $hero = function_exists( 'cvl_get_homepage_hero' )
            ? cvl_get_homepage_hero()
            : array();

        $highlights = function_exists( 'cvl_get_homepage_highlights' )
            ? cvl_get_homepage_highlights()
            : array();

        foreach ( array( 'main', 'side' ) as $hero_key ) {
            if ( isset( $hero[ $hero_key ] ) && is_array( $hero[ $hero_key ] ) && function_exists( 'cvl_homepage_hero_image_url' ) ) {
                $hero[ $hero_key ]['resolved_image_url'] = cvl_homepage_hero_image_url( $hero[ $hero_key ] );
            }
        }

        foreach ( $highlights as $index => $highlight ) {
            if ( ! is_array( $highlight ) ) {
                continue;
            }

            $highlights[ $index ]['resolved_image_url'] = function_exists( 'cvl_homepage_highlight_image_url' )
                ? cvl_homepage_highlight_image_url( $highlight )
                : '';

            $highlights[ $index ]['resolved_rotation_images'] = function_exists( 'cvl_homepage_highlight_rotation_images' )
                ? cvl_homepage_highlight_rotation_images( $highlight )
                : array();
        }

        $categories = array();

        if ( taxonomy_exists( 'product_cat' ) ) {
            $terms = get_terms(
                array(
                    'taxonomy'   => 'product_cat',
                    'parent'     => 0,
                    'hide_empty' => false,
                    'number'     => 0,
                    'orderby'    => 'name',
                    'order'      => 'ASC',
                )
            );

            if ( ! is_wp_error( $terms ) ) {
                $priority = array(
                    'oficina-automovel',
                    'ferramentas-electricas',
                    'ferramentas-manuais',
                    'elevacao-e-carga',
                    'ar-comprimido',
                    'maquinas-p-industria-metal',
                    'construcao-civil',
                    'floresta-e-jardim',
                    'limpeza',
                    'equipamentos-de-soldadura',
                    'geradores',
                    'carpintaria-de-madeiras',
                    'ferramentas-pneumaticas',
                    'medicao-e-nivelamento',
                    'proteccao-e-seguranca',
                    'estantaria-e-arrumacao',
                    'electricidade-e-electronica',
                    'iluminacao',
                    'ambiente',
                    'canalizacao-e-desentupimentos',
                    'embalamento',
                    'equip-p-agricultura',
                    'escadas-escadotes-e-andaimes',
                    'outros',
                );

                $rank = array_flip( $priority );

                usort(
                    $terms,
                    static function ( $a, $b ) use ( $rank ) {
                        $ra = $rank[ $a->slug ] ?? 999;
                        $rb = $rank[ $b->slug ] ?? 999;
                        return $ra === $rb ? strcasecmp( $a->name, $b->name ) : ( $ra <=> $rb );
                    }
                );

                foreach ( array_slice( $terms, 0, 10 ) as $term ) {
                    if ( ! $term instanceof WP_Term ) {
                        continue;
                    }

                    $thumbnail_id = absint( get_term_meta( $term->term_id, 'thumbnail_id', true ) );
                    $url = get_term_link( $term );

                    $categories[] = array(
                        'id'    => (int) $term->term_id,
                        'name'  => $term->name,
                        'slug'  => $term->slug,
                        'count' => (int) $term->count,
                        'image' => $thumbnail_id ? ( wp_get_attachment_image_url( $thumbnail_id, 'medium_large' ) ?: '' ) : '',
                        'url'   => is_wp_error( $url ) ? '' : $url,
                    );
                }
            }
        }

        return rest_ensure_response(
            array(
                'ok'         => true,
                'source'     => 'woocommerce-homepage',
                'updated_at' => current_time( DATE_ATOM, true ),
                'hero'       => $hero,
                'highlights' => array_values( $highlights ),
                'categories' => $categories,
                'benefits'   => array(
                    array( 'icon' => 'truck', 'title' => 'Entregas em Portugal', 'subtitle' => 'Encomendas iguais ou superiores a 100 € + IVA*' ),
                    array( 'icon' => 'box', 'title' => 'Stock para entrega imediata', 'subtitle' => 'Milhares de referências disponíveis' ),
                    array( 'icon' => 'headset', 'title' => 'Apoio especializado', 'subtitle' => 'Comercial, técnico e pós-venda' ),
                    array( 'icon' => 'cart', 'title' => 'Mais de 30.000 referências', 'subtitle' => 'Máquinas, ferramentas e consumíveis' ),
                ),
            )
        );
    }

    public function rest_brand_catalog( WP_REST_Request $request ) {
        $brand_slug = sanitize_title( (string) $request['slug'] );
        $brand      = get_term_by( 'slug', $brand_slug, 'product_brand' );

        if ( ! $brand instanceof WP_Term ) {
            return new WP_Error( 'cvab_brand_not_found', 'Marca não encontrada.', array( 'status' => 404 ) );
        }

        $selected_category = null;
        $selected_slug     = sanitize_title( (string) $request->get_param( 'categoria' ) );

        if ( $selected_slug ) {
            $candidate = get_term_by( 'slug', $selected_slug, 'product_cat' );
            if ( $candidate instanceof WP_Term ) {
                $selected_category = $candidate;
            }
        }

        $min_price = max( 0, (float) wc_format_decimal( (string) $request->get_param( 'min_price' ) ) );
        $max_price = max( 0, (float) wc_format_decimal( (string) $request->get_param( 'max_price' ) ) );

        $category_facets = function_exists( 'cvl_brand_archive_category_facets' )
            ? cvl_brand_archive_category_facets( (int) $brand->term_id, $min_price, $max_price )
            : array();

        $category_tree = function_exists( 'cvl_brand_archive_category_tree' )
            ? cvl_brand_archive_category_tree( $category_facets )
            : array();

        $price_bounds = function_exists( 'cvl_brand_archive_price_bounds' )
            ? cvl_brand_archive_price_bounds( (int) $brand->term_id, $selected_category )
            : array( 0.0, 0.0 );

        $tax_query = array(
            array(
                'taxonomy' => 'product_brand',
                'field'    => 'term_id',
                'terms'    => array( (int) $brand->term_id ),
                'operator' => 'IN',
            ),
        );

        if ( $selected_category instanceof WP_Term ) {
            $tax_query[] = array(
                'taxonomy'         => 'product_cat',
                'field'            => 'term_id',
                'terms'            => array( (int) $selected_category->term_id ),
                'include_children' => true,
                'operator'         => 'IN',
            );
        }

        if ( function_exists( 'wc_get_product_visibility_term_ids' ) ) {
            $visibility_ids = wc_get_product_visibility_term_ids();
            $excluded       = array();

            if ( ! empty( $visibility_ids['exclude-from-catalog'] ) ) {
                $excluded[] = (int) $visibility_ids['exclude-from-catalog'];
            }

            if (
                'yes' === get_option( 'woocommerce_hide_out_of_stock_items' )
                && ! empty( $visibility_ids['outofstock'] )
            ) {
                $excluded[] = (int) $visibility_ids['outofstock'];
            }

            if ( $excluded ) {
                $tax_query[] = array(
                    'taxonomy' => 'product_visibility',
                    'field'    => 'term_id',
                    'terms'    => $excluded,
                    'operator' => 'NOT IN',
                );
            }
        }

        $meta_query = array();

        if ( $min_price > 0 || $max_price > 0 ) {
            $price_rule = array(
                'key'  => '_price',
                'type' => 'NUMERIC',
            );

            if ( $min_price > 0 && $max_price > 0 ) {
                $price_rule['value']   = array( $min_price, $max_price );
                $price_rule['compare'] = 'BETWEEN';
            } elseif ( $min_price > 0 ) {
                $price_rule['value']   = $min_price;
                $price_rule['compare'] = '>=';
            } else {
                $price_rule['value']   = $max_price;
                $price_rule['compare'] = '<=';
            }

            $meta_query[] = $price_rule;
        }

        $page = max( 1, absint( $request->get_param( 'cvl_page' ) ?: $request->get_param( 'page' ) ) );

        $catalog_query = new WP_Query(
            array(
                'post_type'           => 'product',
                'post_status'         => 'publish',
                'posts_per_page'      => 24,
                'paged'               => $page,
                'ignore_sticky_posts' => true,
                'no_found_rows'       => false,
                'tax_query'           => $tax_query,
                'meta_query'          => $meta_query,
                'orderby'             => array(
                    'menu_order' => 'ASC',
                    'date'       => 'DESC',
                ),
            )
        );

        $products = array();

        foreach ( $catalog_query->posts as $post ) {
            $product = wc_get_product( $post->ID );
            if ( $product instanceof WC_Product ) {
                $products[] = $this->category_product_payload( $product );
            }
        }

        $thumbnail_id = absint( get_term_meta( $brand->term_id, 'thumbnail_id', true ) );
        $ancestor_ids = $selected_category instanceof WP_Term
            ? array_map( 'absint', get_ancestors( (int) $selected_category->term_id, 'product_cat', 'taxonomy' ) )
            : array();

        return rest_ensure_response(
            array(
                'ok'                  => true,
                'source'              => 'woocommerce-brand-catalog',
                'brand'               => array(
                    'id'    => (int) $brand->term_id,
                    'name'  => $brand->name,
                    'slug'  => $brand->slug,
                    'count' => (int) $brand->count,
                    'logo'  => $thumbnail_id ? ( wp_get_attachment_image_url( $thumbnail_id, 'medium' ) ?: '' ) : '',
                ),
                'selected_category'   => $selected_category instanceof WP_Term ? $selected_category->slug : '',
                'selected_category_id'=> $selected_category instanceof WP_Term ? (int) $selected_category->term_id : 0,
                'selected_ancestors'  => $ancestor_ids,
                'categories'          => array_values( $category_tree ),
                'price'               => array(
                    'min'   => $min_price,
                    'max'   => $max_price,
                    'floor' => (float) ( $price_bounds[0] ?? 0 ),
                    'ceil'  => (float) ( $price_bounds[1] ?? 0 ),
                ),
                'products'            => $products,
                'total'               => (int) $catalog_query->found_posts,
                'page'                => $page,
                'total_pages'         => max( 1, (int) $catalog_query->max_num_pages ),
                'per_page'            => 24,
            )
        );
    }

    public function rest_brands_directory() {
        if ( ! taxonomy_exists( 'product_brand' ) ) {
            return new WP_Error( 'cvab_brands_unavailable', 'Marcas indisponíveis.', array( 'status' => 503 ) );
        }

        $terms = get_terms(
            array(
                'taxonomy'   => 'product_brand',
                'hide_empty' => false,
                'number'     => 0,
                'orderby'    => 'name',
                'order'      => 'ASC',
            )
        );

        if ( is_wp_error( $terms ) ) {
            return $terms;
        }

        $groups = array();

        foreach ( $terms as $term ) {
            if ( ! $term instanceof WP_Term ) {
                continue;
            }

            $normalized = remove_accents( $term->name );
            $letter     = strtoupper( substr( $normalized, 0, 1 ) );

            if ( ! preg_match( '/^[A-Z]$/', $letter ) ) {
                $letter = '#';
            }

            if ( ! isset( $groups[ $letter ] ) ) {
                $groups[ $letter ] = array();
            }

            $thumbnail_id = absint( get_term_meta( $term->term_id, 'thumbnail_id', true ) );

            $groups[ $letter ][] = array(
                'id'    => (int) $term->term_id,
                'name'  => $term->name,
                'slug'  => $term->slug,
                'count' => (int) $term->count,
                'logo'  => $thumbnail_id ? ( wp_get_attachment_image_url( $thumbnail_id, 'medium' ) ?: '' ) : '',
            );
        }

        ksort( $groups, SORT_NATURAL );

        if ( isset( $groups['#'] ) ) {
            $other = $groups['#'];
            unset( $groups['#'] );
            $groups['#'] = $other;
        }

        $payload_groups = array();

        foreach ( $groups as $letter => $brands ) {
            $payload_groups[] = array(
                'letter' => $letter,
                'label'  => '#' === $letter ? '0–9 / Outros' : $letter,
                'id'     => '#' === $letter ? 'marcas-outros' : 'marcas-' . strtolower( $letter ),
                'brands' => array_values( $brands ),
            );
        }

        return rest_ensure_response(
            array(
                'ok'         => true,
                'source'     => 'woocommerce-product-brand',
                'updated_at' => current_time( DATE_ATOM, true ),
                'groups'     => $payload_groups,
                'total'      => count( $terms ),
            )
        );
    }

    public function rest_shop_catalog() {
        if ( ! taxonomy_exists( 'product_cat' ) ) {
            return new WP_Error( 'cvab_categories_unavailable', 'Categorias indisponíveis.', array( 'status' => 503 ) );
        }

        $exclude = array();
        $uncategorized = get_term_by( 'slug', 'uncategorized', 'product_cat' );

        if ( $uncategorized instanceof WP_Term ) {
            $exclude[] = (int) $uncategorized->term_id;
        }

        $terms = get_terms(
            array(
                'taxonomy'   => 'product_cat',
                'parent'     => 0,
                'hide_empty' => false,
                'exclude'    => $exclude,
                'number'     => 0,
                'orderby'    => 'name',
                'order'      => 'ASC',
            )
        );

        if ( is_wp_error( $terms ) ) {
            return $terms;
        }

        $categories = array();

        foreach ( $terms as $term ) {
            if ( ! $term instanceof WP_Term ) {
                continue;
            }

            $thumbnail_id = absint( get_term_meta( $term->term_id, 'thumbnail_id', true ) );

            $categories[] = array(
                'id'     => (int) $term->term_id,
                'name'   => $term->name,
                'slug'   => $term->slug,
                'parent' => (int) $term->parent,
                'count'  => (int) $term->count,
                'image'  => $thumbnail_id ? ( wp_get_attachment_image_url( $thumbnail_id, 'medium' ) ?: '' ) : '',
            );
        }

        return rest_ensure_response(
            array(
                'ok'         => true,
                'source'     => 'woocommerce-shop-root',
                'updated_at' => current_time( DATE_ATOM, true ),
                'categories' => $categories,
            )
        );
    }

    private function category_branch_ids( WP_Term $term ): array {
        $children = get_term_children( (int) $term->term_id, 'product_cat' );
        $children = is_wp_error( $children ) ? array() : array_map( 'absint', $children );

        return array_values(
            array_unique(
                array_filter(
                    array_merge( array( (int) $term->term_id ), $children )
                )
            )
        );
    }

    private function category_term_payload( WP_Term $term, int $depth = 0 ): array {
        $thumbnail_id = absint( get_term_meta( $term->term_id, 'thumbnail_id', true ) );
        $children     = array();

        if ( $depth < 5 ) {
            $child_terms = get_terms(
                array(
                    'taxonomy'   => 'product_cat',
                    'parent'     => (int) $term->term_id,
                    'hide_empty' => false,
                    'pad_counts' => true,
                    'orderby'    => 'name',
                    'order'      => 'ASC',
                )
            );

            if ( ! is_wp_error( $child_terms ) ) {
                foreach ( $child_terms as $child ) {
                    if ( $child instanceof WP_Term ) {
                        $children[] = $this->category_term_payload( $child, $depth + 1 );
                    }
                }
            }
        }

        return array(
            'id'       => (int) $term->term_id,
            'name'     => $term->name,
            'slug'     => $term->slug,
            'parent'   => (int) $term->parent,
            'count'    => (int) $term->count,
            'image'    => $thumbnail_id
                ? ( wp_get_attachment_image_url( $thumbnail_id, 'woocommerce_thumbnail' ) ?: '' )
                : ( function_exists( 'wc_placeholder_img_src' ) ? wc_placeholder_img_src( 'woocommerce_thumbnail' ) : '' ),
            'children' => $children,
        );
    }

    private function category_brand_facets( array $category_ids, float $min_price = 0.0, float $max_price = 0.0 ): array {
        if ( function_exists( 'cvl_category_archive_brand_facets' ) ) {
            return cvl_category_archive_brand_facets( $category_ids, $min_price, $max_price );
        }

        if ( ! taxonomy_exists( 'product_brand' ) || ! $category_ids ) {
            return array();
        }

        global $wpdb;

        $category_ids = array_values( array_filter( array_map( 'absint', $category_ids ) ) );
        if ( ! $category_ids ) {
            return array();
        }

        $placeholders = implode( ',', array_fill( 0, count( $category_ids ), '%d' ) );
        $lookup_table = $wpdb->prefix . 'wc_product_meta_lookup';
        $sql = "
            SELECT t.term_id, t.slug, t.name, COUNT(DISTINCT p.ID) AS product_count
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->term_relationships} trc ON trc.object_id = p.ID
            INNER JOIN {$wpdb->term_taxonomy} ttc
                ON ttc.term_taxonomy_id = trc.term_taxonomy_id
                AND ttc.taxonomy = 'product_cat'
            INNER JOIN {$wpdb->term_relationships} trb ON trb.object_id = p.ID
            INNER JOIN {$wpdb->term_taxonomy} ttb
                ON ttb.term_taxonomy_id = trb.term_taxonomy_id
                AND ttb.taxonomy = 'product_brand'
            INNER JOIN {$wpdb->terms} t ON t.term_id = ttb.term_id
            INNER JOIN {$lookup_table} l ON l.product_id = p.ID
            WHERE p.post_type = 'product'
              AND p.post_status = 'publish'
              AND ttc.term_id IN ({$placeholders})
        ";

        $args = $category_ids;

        if ( $min_price > 0 ) {
            $sql .= ' AND l.max_price >= %f';
            $args[] = $min_price;
        }
        if ( $max_price > 0 ) {
            $sql .= ' AND l.min_price <= %f';
            $args[] = $max_price;
        }

        $sql .= ' GROUP BY t.term_id, t.slug, t.name ORDER BY product_count DESC, t.name ASC';
        $rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A );

        return is_array( $rows ) ? $rows : array();
    }

    private function category_price_bounds( array $category_ids, array $brand_slugs = array() ): array {
        if ( function_exists( 'cvl_category_archive_price_bounds' ) ) {
            return cvl_category_archive_price_bounds( $category_ids, $brand_slugs );
        }

        if ( ! $category_ids ) {
            return array( 0.0, 0.0 );
        }

        global $wpdb;

        $category_ids = array_values( array_filter( array_map( 'absint', $category_ids ) ) );
        $brand_slugs  = array_values( array_unique( array_filter( array_map( 'sanitize_title', $brand_slugs ) ) ) );

        $placeholders = implode( ',', array_fill( 0, count( $category_ids ), '%d' ) );
        $lookup_table = $wpdb->prefix . 'wc_product_meta_lookup';
        $sql = "
            SELECT MIN(l.min_price) AS min_price, MAX(l.max_price) AS max_price
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->term_relationships} trc ON trc.object_id = p.ID
            INNER JOIN {$wpdb->term_taxonomy} ttc
                ON ttc.term_taxonomy_id = trc.term_taxonomy_id
                AND ttc.taxonomy = 'product_cat'
            INNER JOIN {$lookup_table} l ON l.product_id = p.ID
        ";
        $args = $category_ids;

        if ( $brand_slugs && taxonomy_exists( 'product_brand' ) ) {
            $sql .= "
                INNER JOIN {$wpdb->term_relationships} trb ON trb.object_id = p.ID
                INNER JOIN {$wpdb->term_taxonomy} ttb
                    ON ttb.term_taxonomy_id = trb.term_taxonomy_id
                    AND ttb.taxonomy = 'product_brand'
                INNER JOIN {$wpdb->terms} tb ON tb.term_id = ttb.term_id
            ";
        }

        $sql .= "
            WHERE p.post_type = 'product'
              AND p.post_status = 'publish'
              AND ttc.term_id IN ({$placeholders})
        ";

        if ( $brand_slugs && taxonomy_exists( 'product_brand' ) ) {
            $brand_placeholders = implode( ',', array_fill( 0, count( $brand_slugs ), '%s' ) );
            $sql .= " AND tb.slug IN ({$brand_placeholders})";
            $args = array_merge( $args, $brand_slugs );
        }

        $row = $wpdb->get_row( $wpdb->prepare( $sql, $args ), ARRAY_A );

        return array(
            isset( $row['min_price'] ) ? (float) $row['min_price'] : 0.0,
            isset( $row['max_price'] ) ? (float) $row['max_price'] : 0.0,
        );
    }

    private function category_product_payload( WC_Product $product ): array {
        $product_id = $product->get_id();
        $image_id   = $product->get_image_id();
        $categories = array();
        $brands     = array();

        foreach ( wp_get_post_terms( $product_id, 'product_cat' ) as $term ) {
            if ( $term instanceof WP_Term ) {
                $categories[] = array(
                    'id'   => (int) $term->term_id,
                    'name' => $term->name,
                    'slug' => $term->slug,
                );
            }
        }

        if ( taxonomy_exists( 'product_brand' ) ) {
            foreach ( wp_get_post_terms( $product_id, 'product_brand' ) as $term ) {
                if ( ! $term instanceof WP_Term ) {
                    continue;
                }

                $brand_image_id = absint( get_term_meta( $term->term_id, 'thumbnail_id', true ) );
                $brands[] = array(
                    'id'   => (int) $term->term_id,
                    'name' => $term->name,
                    'slug' => $term->slug,
                    'logo' => $brand_image_id ? ( wp_get_attachment_image_url( $brand_image_id, 'medium' ) ?: '' ) : '',
                );
            }
        }

        $brand = $brands[0] ?? array();

        return array(
            'id'                => $product_id,
            'name'              => $product->get_name(),
            'slug'              => $product->get_slug(),
            'sku'               => $product->get_sku(),
            'url'               => 'https://astro.chavevertical.com/produto/' . rawurlencode( $product->get_slug() ) . '/',
            'permalink'         => $product->get_permalink(),
            'purchaseUrl'       => 'https://loja.chavevertical.com/cart/?add-to-cart=' . $product_id,
            'priceValue'        => (float) $product->get_price(),
            'regularPriceValue' => (float) $product->get_regular_price(),
            'salePriceValue'    => (float) $product->get_sale_price(),
            'currency'          => get_woocommerce_currency(),
            'onSale'            => $product->is_on_sale(),
            'on_sale'           => $product->is_on_sale(),
            'image'             => $image_id ? ( wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' ) ?: '' ) : '',
            'imageAlt'          => $image_id ? (string) get_post_meta( $image_id, '_wp_attachment_image_alt', true ) : '',
            'categories'        => $categories,
            'categoryName'      => (string) ( $categories[0]['name'] ?? '' ),
            'brands'            => $brands,
            'brand'             => (string) ( $brand['name'] ?? '' ),
            'brandSlug'         => (string) ( $brand['slug'] ?? '' ),
            'brandLogo'         => (string) ( $brand['logo'] ?? '' ),
            'rating'            => (float) $product->get_average_rating(),
            'ratingCount'       => (int) $product->get_review_count(),
            'stockStatus'       => $product->get_stock_status(),
            'stock_status'      => $product->get_stock_status(),
            'stock'             => 'outofstock' !== $product->get_stock_status(),
        );
    }

    public function rest_category_catalog( WP_REST_Request $request ) {
        $base_slug = sanitize_title( (string) $request['slug'] );
        $base_term = get_term_by( 'slug', $base_slug, 'product_cat' );

        if ( ! $base_term instanceof WP_Term ) {
            return new WP_Error( 'cvab_category_not_found', 'Categoria não encontrada.', array( 'status' => 404 ) );
        }

        $base_branch_ids = $this->category_branch_ids( $base_term );
        $selected_slug   = sanitize_title( (string) $request->get_param( 'categoria' ) );
        $selected_term   = null;

        if ( $selected_slug && $selected_slug !== $base_term->slug ) {
            $candidate = get_term_by( 'slug', $selected_slug, 'product_cat' );
            if ( $candidate instanceof WP_Term && in_array( (int) $candidate->term_id, $base_branch_ids, true ) ) {
                $selected_term = $candidate;
            }
        }

        $navigation_parent = $selected_term instanceof WP_Term ? $selected_term : $base_term;

        $child_terms = get_terms(
            array(
                'taxonomy'   => 'product_cat',
                'hide_empty' => false,
                'pad_counts' => true,
                'parent'     => (int) $navigation_parent->term_id,
                'orderby'    => 'name',
                'order'      => 'ASC',
            )
        );
        $child_terms = is_wp_error( $child_terms ) ? array() : $child_terms;
        $is_final    = empty( $child_terms );

        $raw_brands = $request->get_param( 'marca' );
        $raw_brands = is_array( $raw_brands ) ? $raw_brands : explode( ',', (string) $raw_brands );
        $selected_brands = $is_final
            ? array_values( array_unique( array_filter( array_map( 'sanitize_title', $raw_brands ) ) ) )
            : array();

        $min_price = $is_final ? max( 0, (float) wc_format_decimal( (string) $request->get_param( 'min_price' ) ) ) : 0.0;
        $max_price = $is_final ? max( 0, (float) wc_format_decimal( (string) $request->get_param( 'max_price' ) ) ) : 0.0;

        $facet_category_ids = $this->category_branch_ids( $navigation_parent );
        $brand_facets       = $is_final ? $this->category_brand_facets( $facet_category_ids, $min_price, $max_price ) : array();
        $price_bounds       = $is_final ? $this->category_price_bounds( $facet_category_ids, $selected_brands ) : array( 0.0, 0.0 );

        $tax_query = array(
            array(
                'taxonomy'         => 'product_cat',
                'field'            => 'term_id',
                'terms'            => array( (int) $navigation_parent->term_id ),
                'include_children' => true,
                'operator'         => 'IN',
            ),
        );

        if ( $is_final && $selected_brands && taxonomy_exists( 'product_brand' ) ) {
            $tax_query[] = array(
                'taxonomy' => 'product_brand',
                'field'    => 'slug',
                'terms'    => $selected_brands,
                'operator' => 'IN',
            );
        }

        if ( function_exists( 'wc_get_product_visibility_term_ids' ) ) {
            $visibility_ids      = wc_get_product_visibility_term_ids();
            $excluded_visibility = array();

            if ( ! empty( $visibility_ids['exclude-from-catalog'] ) ) {
                $excluded_visibility[] = (int) $visibility_ids['exclude-from-catalog'];
            }

            if (
                'yes' === get_option( 'woocommerce_hide_out_of_stock_items' )
                && ! empty( $visibility_ids['outofstock'] )
            ) {
                $excluded_visibility[] = (int) $visibility_ids['outofstock'];
            }

            if ( $excluded_visibility ) {
                $tax_query[] = array(
                    'taxonomy' => 'product_visibility',
                    'field'    => 'term_id',
                    'terms'    => $excluded_visibility,
                    'operator' => 'NOT IN',
                );
            }
        }

        $meta_query = array();

        if ( $is_final && ( $min_price > 0 || $max_price > 0 ) ) {
            $price_rule = array(
                'key'  => '_price',
                'type' => 'NUMERIC',
            );

            if ( $min_price > 0 && $max_price > 0 ) {
                $price_rule['value']   = array( $min_price, $max_price );
                $price_rule['compare'] = 'BETWEEN';
            } elseif ( $min_price > 0 ) {
                $price_rule['value']   = $min_price;
                $price_rule['compare'] = '>=';
            } else {
                $price_rule['value']   = $max_price;
                $price_rule['compare'] = '<=';
            }

            $meta_query[] = $price_rule;
        }

        $page  = max( 1, absint( $request->get_param( 'cvl_page' ) ?: $request->get_param( 'page' ) ) );
        $catalog_args = array(
            'post_type'           => 'product',
            'post_status'         => 'publish',
            'posts_per_page'      => 24,
            'paged'               => $page,
            'ignore_sticky_posts' => true,
            'no_found_rows'       => false,
            'tax_query'           => $tax_query,
            'meta_query'          => $meta_query,
            'orderby'             => array(
                'menu_order' => 'ASC',
                'date'       => 'DESC',
            ),
        );

        $catalog_query = new WP_Query( $catalog_args );
        $products      = array();

        foreach ( $catalog_query->posts as $post ) {
            $product = wc_get_product( $post->ID );
            if ( $product instanceof WC_Product ) {
                $products[] = $this->category_product_payload( $product );
            }
        }

        $children = array();
        foreach ( $child_terms as $term ) {
            if ( $term instanceof WP_Term ) {
                $children[] = $this->category_term_payload( $term );
            }
        }

        $ancestors = array_reverse( get_ancestors( (int) $navigation_parent->term_id, 'product_cat', 'taxonomy' ) );
        $breadcrumb = array();

        foreach ( $ancestors as $ancestor_id ) {
            $ancestor = get_term( $ancestor_id, 'product_cat' );
            if ( $ancestor instanceof WP_Term ) {
                $breadcrumb[] = array(
                    'id'   => (int) $ancestor->term_id,
                    'name' => $ancestor->name,
                    'slug' => $ancestor->slug,
                );
            }
        }

        $breadcrumb[] = array(
            'id'   => (int) $navigation_parent->term_id,
            'name' => $navigation_parent->name,
            'slug' => $navigation_parent->slug,
        );

        return rest_ensure_response(
            array(
                'ok'                => true,
                'source'            => 'woocommerce-category-catalog',
                'base'              => $this->category_term_payload( $base_term, 5 ),
                'current'           => $this->category_term_payload( $navigation_parent, 5 ),
                'selected_category' => $selected_term instanceof WP_Term ? $selected_term->slug : '',
                'children'          => $children,
                'is_final'          => $is_final,
                'brands'            => array_values( $brand_facets ),
                'selected_brands'   => $selected_brands,
                'price'             => array(
                    'min'      => $min_price,
                    'max'      => $max_price,
                    'floor'    => (float) ( $price_bounds[0] ?? 0 ),
                    'ceil'     => (float) ( $price_bounds[1] ?? 0 ),
                ),
                'breadcrumb'         => $breadcrumb,
                'products'           => $products,
                'total'              => (int) $catalog_query->found_posts,
                'page'               => $page,
                'total_pages'        => max( 1, (int) $catalog_query->max_num_pages ),
                'per_page'           => 24,
            )
        );
    }

    public function verify_worker_command( WP_REST_Request $request ) {
        $timestamp = trim( (string) $request->get_header( 'x-cv-timestamp' ) );
        $signature = trim( (string) $request->get_header( 'x-cv-signature' ) );
        $body      = (string) $request->get_body();
        $settings  = $this->settings();
        $secret    = (string) $settings['secret'];

        if ( '' === $timestamp || '' === $signature || '' === $secret || ! ctype_digit( $timestamp ) ) {
            return new WP_REST_Response( array( 'ok' => false, 'verified' => false ), 401 );
        }

        if ( abs( time() - (int) $timestamp ) > 120 ) {
            return new WP_REST_Response( array( 'ok' => false, 'verified' => false, 'reason' => 'expired' ), 401 );
        }

        $expected = hash_hmac( 'sha256', $timestamp . "\n" . $body, $secret );
        $verified = hash_equals( $expected, $signature );

        return new WP_REST_Response(
            array(
                'ok'       => $verified,
                'verified' => $verified,
            ),
            $verified ? 200 : 401
        );
    }

    private function rest_product( int $product_id ) {
        $product = wc_get_product( $product_id );

        if ( ! $product || 'publish' !== $product->get_status() ) {
            return new WP_Error( 'cvab_not_found', 'Produto não encontrado.', array( 'status' => 404 ) );
        }

        $categories = array();
        foreach ( wp_get_post_terms( $product_id, 'product_cat' ) as $term ) {
            if ( $term instanceof WP_Term ) {
                $categories[] = array(
                    'id'     => $term->term_id,
                    'name'   => $term->name,
                    'slug'   => $term->slug,
                    'parent' => $term->parent,
                );
            }
        }

        $brands = array();
        if ( taxonomy_exists( 'product_brand' ) ) {
            foreach ( wp_get_post_terms( $product_id, 'product_brand' ) as $term ) {
                if ( ! $term instanceof WP_Term ) {
                    continue;
                }
                $thumbnail_id = absint( get_term_meta( $term->term_id, 'thumbnail_id', true ) );
                $brands[] = array(
                    'id'    => $term->term_id,
                    'name'  => $term->name,
                    'slug'  => $term->slug,
                    'logo'  => $thumbnail_id ? wp_get_attachment_image_url( $thumbnail_id, 'full' ) : '',
                );
            }
        }

        $images = array();
        foreach ( array_unique( array_filter( array_merge( array( $product->get_image_id() ), $product->get_gallery_image_ids() ) ) ) as $image_id ) {
            $images[] = array(
                'id'  => (int) $image_id,
                'src' => wp_get_attachment_image_url( $image_id, 'full' ) ?: '',
                'alt' => get_post_meta( $image_id, '_wp_attachment_image_alt', true ),
            );
        }

        $attributes = array();
        foreach ( $product->get_attributes() as $attribute ) {
            if ( $attribute instanceof WC_Product_Attribute ) {
                $attributes[] = array(
                    'id'      => $attribute->get_id(),
                    'name'    => wc_attribute_label( $attribute->get_name() ),
                    'slug'    => $attribute->get_name(),
                    'options' => $attribute->is_taxonomy()
                        ? wc_get_product_terms( $product_id, $attribute->get_name(), array( 'fields' => 'names' ) )
                        : $attribute->get_options(),
                );
            }
        }

        $seo_title = (string) get_post_meta( $product_id, 'rank_math_title', true );
        $seo_desc  = (string) get_post_meta( $product_id, 'rank_math_description', true );
        $focus_kw  = (string) get_post_meta( $product_id, 'rank_math_focus_keyword', true );

        return rest_ensure_response(
            array(
                'ok'                => true,
                'source'            => 'woocommerce',
                'id'                => $product->get_id(),
                'type'              => $product->get_type(),
                'name'              => $product->get_name(),
                'slug'              => $product->get_slug(),
                'sku'               => $product->get_sku(),
                'permalink'         => $product->get_permalink(),
                'status'            => $product->get_status(),
                'price'             => $product->get_price(),
                'regular_price'     => $product->get_regular_price(),
                'sale_price'        => $product->get_sale_price(),
                'on_sale'           => $product->is_on_sale(),
                'stock_status'      => $product->get_stock_status(),
                'manage_stock'      => $product->managing_stock(),
                'stock_quantity'    => in_array( (int) $product->get_stock_quantity(), array( 1, 2 ), true ) ? (int) $product->get_stock_quantity() : null,
                'backorders'        => $product->get_backorders(),
                'description'       => $product->get_description(),
                'short_description' => $product->get_short_description(),
                'weight'            => $product->get_weight(),
                'dimensions'        => array(
                    'length' => $product->get_length(),
                    'width'  => $product->get_width(),
                    'height' => $product->get_height(),
                ),
                'categories'        => $categories,
                'brands'            => $brands,
                'images'            => $images,
                'attributes'        => $attributes,
                'upsell_ids'        => array_map( 'intval', $product->get_upsell_ids() ),
                'cross_sell_ids'    => array_map( 'intval', $product->get_cross_sell_ids() ),
                'seo'               => array(
                    'title'         => $seo_title,
                    'description'   => $seo_desc,
                    'focus_keyword' => $focus_kw,
                ),
                'modified_gmt'      => get_post_modified_time( DATE_ATOM, true, $product_id ),
            )
        );
    }

    public function add_product_metabox(): void {
        add_meta_box(
            'cvab-product',
            'CHAVE VERTICAL — Astro',
            array( $this, 'render_product_metabox' ),
            'product',
            'side',
            'high'
        );
    }

    public function render_product_metabox( WP_Post $post ): void {
        $product = wc_get_product( $post->ID );
        if ( ! $product ) {
            return;
        }

        $astro_url = 'https://astro.chavevertical.com/produto/' . rawurlencode( $product->get_slug() ) . '/';
        ?>
        <p><strong>Frontend Astro</strong></p>
        <p><a href="<?php echo esc_url( $astro_url ); ?>" target="_blank" rel="noopener">Ver no Astro ↗</a></p>
        <p><code><?php echo esc_html( rest_url( 'cv-astro/v1/product/' . $post->ID ) ); ?></code></p>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="cvab_purge_product">
            <input type="hidden" name="product_id" value="<?php echo esc_attr( $post->ID ); ?>">
            <?php wp_nonce_field( 'cvab_purge_product' ); ?>
            <?php submit_button( 'Limpar / reaquecer cache', 'secondary', 'submit', false ); ?>
        </form>
        <?php
    }

    private function guard_admin( string $action ): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'Sem permissões.', 'cv-astro-bridge' ) );
        }
        check_admin_referer( $action );
    }

    private function redirect_admin( string $status, string $message ): void {
        wp_safe_redirect(
            add_query_arg(
                array(
                    'page'         => 'cv-astro-worker',
                    'cvab_status'  => $status,
                    'cvab_message' => rawurlencode( $message ),
                ),
                admin_url( 'admin.php' )
            )
        );
        exit;
    }
}

register_activation_hook( __FILE__, array( 'CV_Astro_Bridge', 'activate' ) );

add_action(
    'before_woocommerce_init',
    static function () {
        if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
        }
    }
);

add_action(
    'plugins_loaded',
    static function () {
        if ( class_exists( 'WooCommerce' ) ) {
            CV_Astro_Bridge::instance();
        }
    },
    30
);
