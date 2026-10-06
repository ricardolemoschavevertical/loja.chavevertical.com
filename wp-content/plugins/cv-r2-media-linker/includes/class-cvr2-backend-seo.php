<?php
defined( 'ABSPATH' ) || exit;

final class CVR2_Backend_SEO {
    private const OPTION = 'cvr2_backend_seo';

    public static function init(): void {
        add_action( 'admin_post_cvr2_save_backend_seo', array( __CLASS__, 'save' ) );
        add_filter( 'wp_robots', array( __CLASS__, 'wp_robots' ), 999 );
        add_filter( 'rank_math/frontend/robots', array( __CLASS__, 'rank_math_robots' ), 999 );
        add_filter( 'rank_math/frontend/canonical', array( __CLASS__, 'rank_math_canonical' ), 999 );
        add_filter( 'get_canonical_url', array( __CLASS__, 'core_canonical' ), 999, 2 );
        add_action( 'send_headers', array( __CLASS__, 'send_noindex_header' ), 999 );
        add_filter( 'robots_txt', array( __CLASS__, 'robots_txt' ), 999, 2 );
        add_action( 'template_redirect', array( __CLASS__, 'redirect_backend_sitemaps' ), 1 );
    }

    public static function settings(): array {
        return wp_parse_args(
            (array) get_option( self::OPTION, array() ),
            array(
                'enabled'    => 1,
                'public_url' => 'https://chavevertical.com',
            )
        );
    }

    private static function is_backend_host(): bool {
        $host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
        return 'loja.chavevertical.com' === $host;
    }

    public static function enabled(): bool {
        $settings = self::settings();
        return self::is_backend_host() && ! empty( $settings['enabled'] );
    }

    private static function public_root(): string {
        $settings = self::settings();
        return untrailingslashit( esc_url_raw( (string) $settings['public_url'] ) );
    }

    public static function save(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( 'Sem permissões.' );
        }

        check_admin_referer( 'cvr2_save_backend_seo' );

        $public_url = untrailingslashit(
            esc_url_raw( wp_unslash( $_POST['public_url'] ?? 'https://chavevertical.com' ) )
        );

        if ( ! $public_url ) {
            $public_url = 'https://chavevertical.com';
        }

        update_option(
            self::OPTION,
            array(
                'enabled'    => isset( $_POST['enabled'] ) ? 1 : 0,
                'public_url' => $public_url,
            ),
            false
        );

        wp_safe_redirect(
            add_query_arg(
                array(
                    'page'          => 'cv-r2-rest-import',
                    'tab'           => 'seo',
                    'cvr2_seo_saved'=> 1,
                ),
                admin_url( 'admin.php' )
            )
        );
        exit;
    }

    public static function wp_robots( array $robots ): array {
        if ( ! self::enabled() || is_admin() ) {
            return $robots;
        }

        unset( $robots['index'], $robots['noindex'] );
        $robots['noindex'] = true;
        $robots['follow']  = true;
        $robots['max-image-preview'] = 'large';

        return $robots;
    }

    public static function rank_math_robots( array $robots ): array {
        if ( ! self::enabled() || is_admin() ) {
            return $robots;
        }

        $robots['index']  = 'noindex';
        $robots['follow'] = 'follow';

        return $robots;
    }

    private static function sensitive_commerce_page(): bool {
        return function_exists( 'is_cart' ) && (
            is_cart()
            || is_checkout()
            || is_account_page()
        );
    }

    public static function canonical_url(): string {
        if ( ! self::enabled() || self::sensitive_commerce_page() || is_search() || is_404() ) {
            return '';
        }

        $root = self::public_root();

        if ( is_front_page() || is_home() ) {
            return $root . '/';
        }

        if ( function_exists( 'is_product' ) && is_product() ) {
            $product_id = get_queried_object_id();
            $slug       = (string) get_post_meta( $product_id, '_cvr2_source_slug', true );

            if ( ! $slug ) {
                $slug = (string) get_post_field( 'post_name', $product_id );
            }

            return $slug ? $root . '/produto/' . rawurlencode( $slug ) . '/' : '';
        }

        if ( function_exists( 'is_product_category' ) && is_product_category() ) {
            $term = get_queried_object();
            return $term instanceof WP_Term
                ? $root . '/categoria-produto/' . rawurlencode( $term->slug ) . '/'
                : '';
        }

        if ( is_tax( 'product_brand' ) ) {
            $term = get_queried_object();
            return $term instanceof WP_Term
                ? $root . '/marca/' . rawurlencode( $term->slug ) . '/'
                : '';
        }

        if ( function_exists( 'is_shop' ) && is_shop() ) {
            return $root . '/shop/';
        }

        if ( is_page() ) {
            $slug = (string) get_post_field( 'post_name', get_queried_object_id() );

            if ( 'marcas' === $slug ) {
                return $root . '/marcas/';
            }

            $permalink = get_permalink( get_queried_object_id() );
            $relative  = $permalink ? wp_make_link_relative( $permalink ) : '';

            return $relative ? $root . '/' . ltrim( $relative, '/' ) : '';
        }

        $request_uri = isset( $_SERVER['REQUEST_URI'] )
            ? wp_unslash( $_SERVER['REQUEST_URI'] )
            : '';

        if ( $request_uri && '/' === substr( $request_uri, 0, 1 ) ) {
            return $root . $request_uri;
        }

        return '';
    }

    public static function rank_math_canonical( string $canonical ): string {
        $public = self::canonical_url();
        return $public ?: $canonical;
    }

    public static function core_canonical( string $canonical, WP_Post $post ): string {
        if ( ! self::enabled() ) {
            return $canonical;
        }

        $public = self::canonical_url();
        return $public ?: $canonical;
    }

    public static function send_noindex_header(): void {
        if (
            ! self::enabled()
            || is_admin()
            || ( function_exists( 'wp_is_json_request' ) && wp_is_json_request() )
        ) {
            return;
        }

        header( 'X-Robots-Tag: noindex, follow', true );
    }

    public static function robots_txt( string $output, bool $public ): string {
        if ( ! self::enabled() ) {
            return $output;
        }

        return "User-agent: *\n"
            . "Disallow: /wp-admin/\n"
            . "Allow: /wp-admin/admin-ajax.php\n\n"
            . 'Sitemap: ' . self::public_root() . "/sitemap_index.xml\n";
    }

    public static function redirect_backend_sitemaps(): void {
        if ( ! self::enabled() || is_admin() ) {
            return;
        }

        $path = (string) wp_parse_url(
            isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '',
            PHP_URL_PATH
        );

        if ( ! in_array( $path, array( '/sitemap_index.xml', '/wp-sitemap.xml' ), true ) ) {
            return;
        }

        $target = self::public_root() . ( '/wp-sitemap.xml' === $path ? '/wp-sitemap.xml' : '/sitemap_index.xml' );
        wp_safe_redirect( $target, 301 );
        exit;
    }

    public static function render(): void {
        $settings = self::settings();
        ?>
        <div class="cvr2-card">
            <h2>SEO Backend — loja.chavevertical.com</h2>

            <?php if ( isset( $_GET['cvr2_seo_saved'] ) ) : ?>
                <div class="notice notice-success inline"><p>Configuração SEO guardada.</p></div>
            <?php endif; ?>

            <p>Esta proteção mantém a <strong>loja.chavevertical.com</strong> como backend WooCommerce/fallback sem competir com o domínio público nos resultados de pesquisa.</p>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="cvr2_save_backend_seo">
                <?php wp_nonce_field( 'cvr2_save_backend_seo' ); ?>

                <p>
                    <label>
                        <input type="checkbox" name="enabled" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?>>
                        <strong>Não indexar loja.chavevertical.com</strong>
                    </label>
                </p>

                <div class="cvr2-row">
                    <label for="cvr2-public-url"><strong>Domínio público indexável</strong></label>
                    <input id="cvr2-public-url" name="public_url" type="url" value="<?php echo esc_attr( (string) $settings['public_url'] ); ?>" required>
                </div>

                <p><button type="submit" class="button button-primary">Guardar SEO</button></p>
            </form>

            <h3>Com esta opção ativa</h3>
            <ul style="list-style:disc;padding-left:22px">
                <li>todas as páginas públicas da loja recebem <code>noindex,follow</code>;</li>
                <li>é enviado também o cabeçalho <code>X-Robots-Tag: noindex, follow</code>;</li>
                <li>produto da loja aponta canonical para <code>chavevertical.com/produto/slug/</code>;</li>
                <li>categorias apontam para <code>chavevertical.com/categoria-produto/slug/</code>;</li>
                <li>marcas apontam para <code>chavevertical.com/marca/slug/</code>;</li>
                <li><code>/shop/</code> e <code>/marcas/</code> apontam para o domínio público;</li>
                <li>carrinho, checkout, conta, pesquisa e 404 ficam noindex sem canonical forçado;</li>
                <li>o robots.txt da loja referencia o sitemap do domínio público em vez de bloquear o crawler antes de este ver o noindex.</li>
            </ul>
        </div>
        <?php
    }
}
