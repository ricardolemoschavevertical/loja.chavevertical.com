<?php
/**
 * Plugin Name: CV Legacy Orders Archive
 * Description: Arquiva encomendas e clientes históricos de um WooCommerce remoto em ficheiros locais privados, com associação por email e consulta no admin.
 * Version: 1.4.0
 * Author: Chave Vertical
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * WC requires at least: 9.0
 * Text Domain: cv-legacy-orders-archive
 */

defined( 'ABSPATH' ) || exit;

define( 'CVLOA_VERSION', '1.4.0' );
define( 'CVLOA_FILE', __FILE__ );
define( 'CVLOA_DIR', plugin_dir_path( __FILE__ ) );
define( 'CVLOA_OPTION', 'cvloa_settings' );
define( 'CVLOA_STATE_OPTION', 'cvloa_import_state' );
define( 'CVLOA_CUSTOMER_STATE_OPTION', 'cvloa_customer_import_state' );
define( 'CVLOA_STATUSES_OPTION', 'cvloa_source_order_statuses' );

require_once CVLOA_DIR . 'includes/class-cvloa-rest-client.php';
require_once CVLOA_DIR . 'includes/class-cvloa-archive.php';
require_once CVLOA_DIR . 'includes/class-cvloa-customer-archive.php';
require_once CVLOA_DIR . 'includes/class-cvloa-customer-identities.php';
require_once CVLOA_DIR . 'includes/class-cvloa-customer-admin.php';
require_once CVLOA_DIR . 'includes/class-cvloa-customer-account-history.php';
require_once CVLOA_DIR . 'includes/class-cvloa-lazy-customer-accounts.php';
require_once CVLOA_DIR . 'includes/class-cvloa-order-statuses.php';
require_once CVLOA_DIR . 'includes/class-cvloa-current-order-archive.php';
require_once CVLOA_DIR . 'includes/class-cvloa-admin.php';

add_action(
    'before_woocommerce_init',
    static function (): void {
        if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', CVLOA_FILE, true );
        }
    }
);

add_action(
    'plugins_loaded',
    static function (): void {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return;
        }

        CVLOA_Order_Statuses::init();
        CVLOA_Current_Order_Archive::init();
        CVLOA_Admin::init();
        CVLOA_Customer_Admin::init();
        CVLOA_Customer_Account_History::init();
        CVLOA_Lazy_Customer_Accounts::init();
    },
    45
);

add_filter(
    'cv_admin_modules',
    static function ( array $modules ): array {
        $modules[] = array(
            'title'       => 'Encomendas antigas',
            'description' => 'Arquivo local privado das encomendas históricas, separado das encomendas WooCommerce atuais.',
            'url'         => admin_url( 'admin.php?page=cv-legacy-orders' ),
            'active'      => true,
        );
        $modules[] = array(
            'title'       => 'Clientes antigos',
            'description' => 'Arquivo privado de clientes históricos, convidados deduplicados e ativação local segura por email quando necessário.',
            'url'         => admin_url( 'admin.php?page=cv-legacy-customers' ),
            'active'      => true,
        );
        return $modules;
    }
);

register_activation_hook(
    __FILE__,
    static function (): void {
        if ( ! get_option( CVLOA_OPTION ) ) {
            add_option(
                CVLOA_OPTION,
                array(
                    'source_url' => 'https://chavevertical.com',
                    'reuse_cvr2' => 1,
                    'source_ck'  => '',
                    'source_cs'  => '',
                ),
                '',
                false
            );
        }

        CVLOA_Archive::ensure_storage();
        CVLOA_Customer_Archive::ensure_storage();
        CVLOA_Customer_Identities::ensure_storage();
        CVLOA_Customer_Account_History::register_endpoint();
        flush_rewrite_rules();
    }
);
