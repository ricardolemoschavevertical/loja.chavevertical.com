<?php
/**
 * Plugin Name: CV OpenSearch Catalog & Search
 * Description: Pesquisa e catálogo WooCommerce em OpenSearch remoto, com sincronização incremental, reindexação, pesquisa nativa e API para Astro.
 * Version: 0.1.6
 * Author: Chave Vertical
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * WC requires at least: 8.5
 * Text Domain: cv-opensearch
 */

defined( 'ABSPATH' ) || exit;

define( 'CVOS_VERSION', '0.1.6' );
define( 'CVOS_FILE', __FILE__ );
define( 'CVOS_PATH', plugin_dir_path( __FILE__ ) );
define( 'CVOS_URL', plugin_dir_url( __FILE__ ) );

require_once CVOS_PATH . 'includes/class-cvos-settings.php';
require_once CVOS_PATH . 'includes/class-cvos-client.php';
require_once CVOS_PATH . 'includes/class-cvos-serializer.php';
require_once CVOS_PATH . 'includes/class-cvos-indexer.php';
require_once CVOS_PATH . 'includes/class-cvos-search.php';
require_once CVOS_PATH . 'includes/class-cvos-plugin.php';

register_activation_hook( __FILE__, array( 'CVOS_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'CVOS_Plugin', 'deactivate' ) );

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
        if ( ! class_exists( 'WooCommerce' ) ) {
            add_action(
                'admin_notices',
                static function () {
                    if ( current_user_can( 'activate_plugins' ) ) {
                        echo '<div class="notice notice-error"><p>' . esc_html__( 'CV OpenSearch necessita do WooCommerce ativo.', 'cv-opensearch' ) . '</p></div>';
                    }
                }
            );
            return;
        }

        CVOS_Plugin::instance();
    },
    20
);
