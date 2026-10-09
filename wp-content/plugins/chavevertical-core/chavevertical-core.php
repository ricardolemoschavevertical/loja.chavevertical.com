<?php
/**
 * Plugin Name: Chave Vertical Core
 * Description: Base comum dos plugins internos da Chave Vertical e menu central de administração.
 * Version: 0.2.2
 * Author: Chave Vertical
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * Text Domain: chavevertical-core
 */

defined( 'ABSPATH' ) || exit;

define( 'CV_CORE_VERSION', '0.2.2' );
define( 'CV_CORE_MENU_SLUG', 'chave-vertical' );

require_once __DIR__ . '/includes/class-cv-core-order-history.php';
require_once __DIR__ . '/includes/cv-core-chatgpt-admin.php';
require_once __DIR__ . '/includes/class-cv-core-brand-logo-webp.php';

function cv_admin_parent_slug(): string {
    return CV_CORE_MENU_SLUG;
}

function cv_core_admin_capability(): string {
    return current_user_can( 'manage_woocommerce' ) ? 'manage_woocommerce' : 'manage_options';
}

function cv_core_register_admin_menu(): void {
    $capability = 'manage_woocommerce';

    add_menu_page(
        __( 'Chave Vertical', 'chavevertical-core' ),
        __( 'CHAVE VERTICAL', 'chavevertical-core' ),
        $capability,
        CV_CORE_MENU_SLUG,
        'cv_core_render_dashboard',
        'dashicons-store',
        56
    );

    add_submenu_page(
        CV_CORE_MENU_SLUG,
        __( 'Visão geral', 'chavevertical-core' ),
        __( 'Visão geral', 'chavevertical-core' ),
        $capability,
        CV_CORE_MENU_SLUG,
        'cv_core_render_dashboard'
    );
}
add_action( 'admin_menu', 'cv_core_register_admin_menu', 5 );

function cv_core_render_dashboard(): void {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_die( esc_html__( 'Não tem permissões para aceder a esta página.', 'chavevertical-core' ) );
    }

    $modules = apply_filters( 'cv_admin_modules', array() );
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'CHAVE VERTICAL', 'chavevertical-core' ); ?></h1>
        <p><?php esc_html_e( 'Centro de controlo das integrações e ferramentas próprias da Chave Vertical.', 'chavevertical-core' ); ?></p>

        <style>
            .cv-core-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px;max-width:1100px;margin-top:20px}
            .cv-core-card{background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:18px;box-shadow:0 1px 2px rgba(0,0,0,.04)}
            .cv-core-card h2{margin:0 0 8px;font-size:17px}
            .cv-core-card p{margin:0 0 14px;color:#50575e}
            .cv-core-status{display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:700;text-transform:uppercase}
            .cv-core-dot{width:8px;height:8px;border-radius:50%;background:#8c8f94}
            .cv-core-status.is-active .cv-core-dot{background:#00a32a}
        </style>

        <div class="cv-core-grid">
            <?php if ( empty( $modules ) ) : ?>
                <div class="cv-core-card">
                    <h2><?php esc_html_e( 'Sem módulos registados', 'chavevertical-core' ); ?></h2>
                    <p><?php esc_html_e( 'Os plugins Chave Vertical ativos aparecem aqui.', 'chavevertical-core' ); ?></p>
                </div>
            <?php else : ?>
                <?php foreach ( $modules as $module ) : ?>
                    <?php
                    $title       = isset( $module['title'] ) ? (string) $module['title'] : 'Módulo';
                    $description = isset( $module['description'] ) ? (string) $module['description'] : '';
                    $url         = isset( $module['url'] ) ? (string) $module['url'] : '';
                    $active      = ! empty( $module['active'] );
                    ?>
                    <div class="cv-core-card">
                        <h2><?php echo esc_html( $title ); ?></h2>
                        <p><?php echo esc_html( $description ); ?></p>
                        <p class="cv-core-status <?php echo $active ? 'is-active' : ''; ?>">
                            <span class="cv-core-dot"></span>
                            <?php echo esc_html( $active ? 'Ativo' : 'Inativo' ); ?>
                        </p>
                        <?php if ( $url ) : ?>
                            <p><a class="button button-secondary" href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Abrir', 'chavevertical-core' ); ?></a></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php
}


add_action(
    'plugins_loaded',
    static function (): void {
        if ( class_exists( 'WooCommerce' ) ) {
            CV_Core_Order_History::init();
        }

        if ( class_exists( 'CV_Core_Brand_Logo_WebP' ) ) {
            CV_Core_Brand_Logo_WebP::init();
        }
    },
    50
);
