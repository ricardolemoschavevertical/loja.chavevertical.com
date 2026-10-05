<?php
/**
 * Plugin Name: CV Astro Bridge
 * Description: Ponte entre WooCommerce, Astro e Cloudflare Worker da Chave Vertical.
 * Version: 0.1.0
 * Author: Chave Vertical
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * WC requires at least: 8.5
 * Text Domain: cv-astro-bridge
 */

defined( 'ABSPATH' ) || exit;

define( 'CVAB_VERSION', '0.1.0' );
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

        $settings = $this->settings();
        $status   = isset( $_GET['cvab_status'] ) ? sanitize_key( wp_unslash( $_GET['cvab_status'] ) ) : '';
        $message  = isset( $_GET['cvab_message'] ) ? sanitize_text_field( rawurldecode( wp_unslash( $_GET['cvab_message'] ) ) ) : '';
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
                        <h2>Estado esperado</h2>
                        <div class="cvab-status-grid">
                            <div class="cvab-stat"><strong>Backend</strong><span>loja.chavevertical.com</span></div>
                            <div class="cvab-stat"><strong>Frontend</strong><span>astro.chavevertical.com</span></div>
                            <div class="cvab-stat"><strong>D1</strong><span>Desativado</span></div>
                            <div class="cvab-stat"><strong>Fonte</strong><span>WooCommerce</span></div>
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

        $started  = microtime( true );
        $response = wp_remote_get(
            $this->settings()['worker_url'] . '/api/cv-admin/health',
            array(
                'timeout'     => 15,
                'redirection' => 2,
                'headers'     => array( 'Accept' => 'application/json' ),
            )
        );

        if ( is_wp_error( $response ) ) {
            $this->redirect_admin( 'error', 'Worker indisponível: ' . $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $ms   = (int) round( ( microtime( true ) - $started ) * 1000 );

        if ( 200 !== $code || empty( $body['ok'] ) ) {
            $this->redirect_admin( 'error', 'O Worker respondeu HTTP ' . $code . '.' );
        }

        $source = isset( $body['source'] ) ? (string) $body['source'] : 'desconhecida';
        $this->redirect_admin( 'ok', sprintf( 'Worker OK em %d ms. Fonte: %s.', $ms, $source ) );
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
