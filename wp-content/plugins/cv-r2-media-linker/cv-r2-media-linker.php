<?php
/**
 * Plugin Name: CV R2 Media Linker
 * Description: Importação REST WooCommerce + Rank Math e associação inteligente de media no Cloudflare R2.
 * Version: 2.4.0
 * Author: Chave Vertical
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * WC requires at least: 9.0
 * Text Domain: cv-r2-media-linker
 */

defined( 'ABSPATH' ) || exit;

define( 'CVR2_VERSION', '2.4.0' );
define( 'CVR2_FILE', __FILE__ );
define( 'CVR2_DIR', plugin_dir_path( __FILE__ ) );
define( 'CVR2_OPTION', 'cvr2_settings' );
define( 'CVR2_STATE_OPTION', 'cvr2_import_state' );

require_once CVR2_DIR . 'includes/class-cvr2-rest-client.php';
require_once CVR2_DIR . 'includes/class-cvr2-media.php';
require_once CVR2_DIR . 'includes/class-cvr2-product-importer.php';
require_once CVR2_DIR . 'includes/class-cvr2-r2-tools.php';
require_once CVR2_DIR . 'includes/class-cvr2-slug-audit.php';
require_once CVR2_DIR . 'includes/class-cvr2-backend-seo.php';
require_once CVR2_DIR . 'includes/class-cvr2-admin.php';

add_action(
    'before_woocommerce_init',
    static function (): void {
        if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', CVR2_FILE, true );
        }
    }
);

add_action(
    'plugins_loaded',
    static function (): void {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return;
        }

        CVR2_Media::init();
        CVR2_Product_Importer::init();
        CVR2_R2_Tools::init();
        CVR2_Slug_Audit::init();
        CVR2_Backend_SEO::init();
        CVR2_Admin::init();
    },
    40
);

add_filter(
    'cv_admin_modules',
    static function ( array $modules ): array {
        $modules[] = array(
            'title'       => 'Importação REST + R2',
            'description' => 'Importa produtos do chavevertical.com por REST API, preserva slugs/SEO e reutiliza imagens existentes no R2.',
            'url'         => admin_url( 'admin.php?page=cv-r2-rest-import' ),
            'active'      => true,
        );
        return $modules;
    }
);

register_activation_hook(
    __FILE__,
    static function (): void {
        if ( ! get_option( CVR2_OPTION ) ) {
            add_option(
                CVR2_OPTION,
                array(
                    'source_url'  => 'https://chavevertical.com',
                    'r2_base_url' => 'https://imagens.chavevertical.com',
                    'batch_size'  => 1,
                ),
                '',
                false
            );
        }
    }
);
