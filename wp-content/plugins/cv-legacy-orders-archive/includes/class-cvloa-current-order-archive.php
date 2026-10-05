<?php
defined( 'ABSPATH' ) || exit;

final class CVLOA_Current_Order_Archive {
    public static function init(): void {
        add_filter( 'woocommerce_admin_order_actions', array( __CLASS__, 'add_row_action' ), 30, 2 );
        add_action( 'admin_post_cvloa_archive_order', array( __CLASS__, 'handle_archive_order' ) );
        add_action( 'admin_head', array( __CLASS__, 'admin_styles' ) );
        add_action( 'admin_notices', array( __CLASS__, 'admin_notice' ) );
    }

    public static function add_row_action( array $actions, $order ): array {
        if ( ! $order instanceof WC_Order || ! current_user_can( 'manage_woocommerce' ) ) {
            return $actions;
        }

        $order_id = $order->get_id();
        $url      = wp_nonce_url(
            add_query_arg(
                array(
                    'action'   => 'cvloa_archive_order',
                    'order_id' => $order_id,
                ),
                admin_url( 'admin-post.php' )
            ),
            'cvloa_archive_order_' . $order_id
        );

        $actions['cvloa_archive'] = array(
            'url'    => $url,
            'name'   => 'Arquivar encomenda',
            'action' => 'cvloa-archive',
        );

        return $actions;
    }

    public static function admin_styles(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }
        ?>
        <style>
            .wc-action-button-cvloa-archive::after{
                font-family:dashicons!important;
                content:"\f480"!important;
            }
        </style>
        <script>
        document.addEventListener('click', function(event) {
            const button = event.target.closest('.wc-action-button-cvloa-archive');
            if (!button) return;

            if (!window.confirm('Arquivar esta encomenda? A cópia será verificada e, se estiver correta, a encomenda será removida da lista atual e passará para Encomendas antigas.')) {
                event.preventDefault();
            }
        });
        </script>
        <?php
    }

    public static function handle_archive_order(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'Sem permissões.', 'cv-legacy-orders-archive' ) );
        }

        $order_id = absint( wp_unslash( $_GET['order_id'] ?? 0 ) );

        if ( ! $order_id ) {
            self::redirect_with_notice( 'error', 'Encomenda inválida.' );
        }

        check_admin_referer( 'cvloa_archive_order_' . $order_id );

        $order = wc_get_order( $order_id );

        if ( ! $order instanceof WC_Order ) {
            self::redirect_with_notice( 'error', 'A encomenda já não existe no WooCommerce.' );
        }

        $payload = self::serialize_order( $order );
        $key     = (string) $payload['_cvloa_archive_key'];

        $result = CVLOA_Archive::archive_batch( array( $payload ), true );

        if ( is_wp_error( $result ) ) {
            self::redirect_with_notice( 'error', 'Não foi possível arquivar: ' . $result->get_error_message() );
        }

        $verified = CVLOA_Archive::read_order( $key );

        if (
            ! is_array( $verified )
            || absint( $verified['id'] ?? 0 ) !== $order_id
            || (string) ( $verified['number'] ?? '' ) !== (string) $order->get_order_number()
            || (string) ( $verified['total'] ?? '' ) !== (string) $order->get_total()
        ) {
            self::redirect_with_notice(
                'error',
                'A cópia local não passou a verificação. A encomenda original não foi eliminada.'
            );
        }

        /*
         * Só apagamos a encomenda WooCommerce depois de a cópia local completa
         * ter sido escrita e relida com sucesso. Não fazemos mudança de estado,
         * por isso não são disparados emails de transição de estado.
         */
        $deleted = $order->delete( true );

        if ( ! $deleted ) {
            self::redirect_with_notice(
                'warning',
                'A encomenda foi copiada para Encomendas antigas, mas não foi possível removê-la das encomendas atuais.'
            );
        }

        self::redirect_with_notice(
            'success',
            sprintf(
                'Encomenda #%s arquivada e movida para Encomendas antigas.',
                $order->get_order_number()
            )
        );
    }

    public static function admin_notice(): void {
        if ( empty( $_GET['cvloa_archive_notice'] ) || empty( $_GET['cvloa_archive_message'] ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        $type = sanitize_key( (string) wp_unslash( $_GET['cvloa_archive_notice'] ) );
        if ( ! in_array( $type, array( 'success', 'warning', 'error' ), true ) ) {
            $type = 'warning';
        }

        $message = sanitize_text_field( (string) wp_unslash( $_GET['cvloa_archive_message'] ) );

        echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
    }

    private static function redirect_with_notice( string $type, string $message ): void {
        $url = self::orders_admin_url();
        $url = add_query_arg(
            array(
                'cvloa_archive_notice'  => sanitize_key( $type ),
                'cvloa_archive_message' => $message,
            ),
            $url
        );

        wp_safe_redirect( $url );
        exit;
    }

    private static function orders_admin_url(): string {
        if (
            class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' )
            && AutomatticWooCommerceUtilitiesOrderUtil::custom_orders_table_usage_is_enabled()
        ) {
            return admin_url( 'admin.php?page=wc-orders' );
        }

        return admin_url( 'edit.php?post_type=shop_order' );
    }

    private static function serialize_order( WC_Order $order ): array {
        $order_id = $order->get_id();

        return array(
            'id'                  => $order_id,
            'parent_id'           => $order->get_parent_id(),
            'number'              => (string) $order->get_order_number(),
            'order_key'           => (string) $order->get_order_key(),
            'created_via'         => (string) $order->get_created_via(),
            'version'             => (string) $order->get_version(),
            'status'              => (string) $order->get_status(),
            'currency'            => (string) $order->get_currency(),
            'date_created'        => self::date_value( $order->get_date_created() ),
            'date_created_gmt'    => self::date_value_gmt( $order->get_date_created() ),
            'date_modified'       => self::date_value( $order->get_date_modified() ),
            'date_modified_gmt'   => self::date_value_gmt( $order->get_date_modified() ),
            'discount_total'      => (string) $order->get_discount_total(),
            'discount_tax'        => (string) $order->get_discount_tax(),
            'shipping_total'      => (string) $order->get_shipping_total(),
            'shipping_tax'        => (string) $order->get_shipping_tax(),
            'cart_tax'            => (string) $order->get_cart_tax(),
            'total'               => (string) $order->get_total(),
            'total_tax'           => (string) $order->get_total_tax(),
            'prices_include_tax'  => (bool) $order->get_prices_include_tax(),
            'customer_id'         => (int) $order->get_customer_id(),
            'customer_ip_address' => (string) $order->get_customer_ip_address(),
            'customer_user_agent' => (string) $order->get_customer_user_agent(),
            'customer_note'       => (string) $order->get_customer_note(),
            'billing'             => (array) $order->get_address( 'billing' ),
            'shipping'            => (array) $order->get_address( 'shipping' ),
            'payment_method'      => (string) $order->get_payment_method(),
            'payment_method_title'=> (string) $order->get_payment_method_title(),
            'transaction_id'      => (string) $order->get_transaction_id(),
            'date_paid'           => self::date_value( $order->get_date_paid() ),
            'date_paid_gmt'       => self::date_value_gmt( $order->get_date_paid() ),
            'date_completed'      => self::date_value( $order->get_date_completed() ),
            'date_completed_gmt'  => self::date_value_gmt( $order->get_date_completed() ),
            'cart_hash'           => (string) $order->get_cart_hash(),
            'line_items'          => self::line_items( $order ),
            'tax_lines'           => self::tax_lines( $order ),
            'shipping_lines'      => self::shipping_lines( $order ),
            'fee_lines'           => self::fee_lines( $order ),
            'coupon_lines'        => self::coupon_lines( $order ),
            'refunds'             => self::refunds( $order ),
            'meta_data'           => self::meta_data( $order->get_meta_data() ),
            '_cvloa_notes'        => self::order_notes( $order_id ),
            '_cvloa_origin'       => 'local',
            '_cvloa_source_url'   => home_url( '/' ),
            '_cvloa_archive_key'  => 'local-' . $order_id,
            '_cvloa_archived_at'  => gmdate( 'c' ),
        );
    }

    private static function line_items( WC_Order $order ): array {
        $rows = array();

        foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
            if ( ! $item instanceof WC_Order_Item_Product ) {
                continue;
            }

            $product = $item->get_product();
            $qty     = (float) $item->get_quantity();

            $rows[] = array(
                'id'           => (int) $item_id,
                'name'         => (string) $item->get_name(),
                'product_id'   => (int) $item->get_product_id(),
                'variation_id' => (int) $item->get_variation_id(),
                'quantity'     => $qty,
                'tax_class'    => (string) $item->get_tax_class(),
                'subtotal'     => (string) $item->get_subtotal(),
                'subtotal_tax' => (string) $item->get_subtotal_tax(),
                'total'        => (string) $item->get_total(),
                'total_tax'    => (string) $item->get_total_tax(),
                'taxes'        => self::normalize_value( $item->get_taxes() ),
                'sku'          => $product instanceof WC_Product
                    ? (string) $product->get_sku()
                    : (string) $item->get_meta( '_cv_recovered_sku', true ),
                'price'        => $qty > 0 ? (string) ( (float) $item->get_subtotal() / $qty ) : '0',
                'meta_data'    => self::meta_data( $item->get_meta_data() ),
            );
        }

        return $rows;
    }

    private static function tax_lines( WC_Order $order ): array {
        $rows = array();

        foreach ( $order->get_items( 'tax' ) as $item_id => $item ) {
            if ( ! $item instanceof WC_Order_Item_Tax ) {
                continue;
            }

            $rows[] = array(
                'id'                => (int) $item_id,
                'rate_code'         => (string) $item->get_rate_code(),
                'rate_id'           => (int) $item->get_rate_id(),
                'label'             => (string) $item->get_label(),
                'compound'          => (bool) $item->get_compound(),
                'tax_total'         => (string) $item->get_tax_total(),
                'shipping_tax_total'=> (string) $item->get_shipping_tax_total(),
                'rate_percent'      => (float) $item->get_rate_percent(),
                'meta_data'         => self::meta_data( $item->get_meta_data() ),
            );
        }

        return $rows;
    }

    private static function shipping_lines( WC_Order $order ): array {
        $rows = array();

        foreach ( $order->get_items( 'shipping' ) as $item_id => $item ) {
            if ( ! $item instanceof WC_Order_Item_Shipping ) {
                continue;
            }

            $rows[] = array(
                'id'           => (int) $item_id,
                'method_title' => (string) $item->get_method_title(),
                'method_id'    => (string) $item->get_method_id(),
                'instance_id'  => (string) $item->get_instance_id(),
                'total'        => (string) $item->get_total(),
                'total_tax'    => (string) $item->get_total_tax(),
                'taxes'        => self::normalize_value( $item->get_taxes() ),
                'meta_data'    => self::meta_data( $item->get_meta_data() ),
            );
        }

        return $rows;
    }

    private static function fee_lines( WC_Order $order ): array {
        $rows = array();

        foreach ( $order->get_items( 'fee' ) as $item_id => $item ) {
            if ( ! $item instanceof WC_Order_Item_Fee ) {
                continue;
            }

            $rows[] = array(
                'id'        => (int) $item_id,
                'name'      => (string) $item->get_name(),
                'tax_class' => (string) $item->get_tax_class(),
                'tax_status'=> (string) $item->get_tax_status(),
                'total'     => (string) $item->get_total(),
                'total_tax' => (string) $item->get_total_tax(),
                'taxes'     => self::normalize_value( $item->get_taxes() ),
                'meta_data' => self::meta_data( $item->get_meta_data() ),
            );
        }

        return $rows;
    }

    private static function coupon_lines( WC_Order $order ): array {
        $rows = array();

        foreach ( $order->get_items( 'coupon' ) as $item_id => $item ) {
            if ( ! $item instanceof WC_Order_Item_Coupon ) {
                continue;
            }

            $rows[] = array(
                'id'           => (int) $item_id,
                'code'         => (string) $item->get_code(),
                'discount'     => (string) $item->get_discount(),
                'discount_tax' => (string) $item->get_discount_tax(),
                'meta_data'    => self::meta_data( $item->get_meta_data() ),
            );
        }

        return $rows;
    }

    private static function refunds( WC_Order $order ): array {
        $rows = array();

        foreach ( $order->get_refunds() as $refund ) {
            if ( ! $refund instanceof WC_Order_Refund ) {
                continue;
            }

            $rows[] = array(
                'id'           => (int) $refund->get_id(),
                'date_created' => self::date_value( $refund->get_date_created() ),
                'reason'       => (string) $refund->get_reason(),
                'total'        => (string) $refund->get_amount(),
                'refunded_by'  => (int) $refund->get_refunded_by(),
                'meta_data'    => self::meta_data( $refund->get_meta_data() ),
            );
        }

        return $rows;
    }

    private static function order_notes( int $order_id ): array {
        $rows  = array();
        $notes = wc_get_order_notes(
            array(
                'order_id' => $order_id,
                'limit'    => 0,
                'orderby'  => 'date_created',
                'order'    => 'ASC',
            )
        );

        foreach ( $notes as $note ) {
            $rows[] = array(
                'id'            => absint( $note->id ?? 0 ),
                'date_created'  => isset( $note->date_created ) && $note->date_created instanceof WC_DateTime
                    ? self::date_value( $note->date_created )
                    : '',
                'note'          => (string) ( $note->content ?? '' ),
                'customer_note' => ! empty( $note->customer_note ),
                'added_by'      => (string) ( $note->added_by ?? '' ),
                'user_id'       => absint( $note->user_id ?? 0 ),
            );
        }

        return $rows;
    }

    private static function meta_data( array $meta_objects ): array {
        $rows = array();

        foreach ( $meta_objects as $meta ) {
            if ( ! is_object( $meta ) || ! method_exists( $meta, 'get_data' ) ) {
                continue;
            }

            $data = (array) $meta->get_data();
            $rows[] = array(
                'id'    => absint( $data['id'] ?? 0 ),
                'key'   => (string) ( $data['key'] ?? '' ),
                'value' => self::normalize_value( $data['value'] ?? '' ),
            );
        }

        return $rows;
    }

    private static function normalize_value( $value ) {
        if ( null === $value || is_scalar( $value ) ) {
            return $value;
        }

        if ( $value instanceof DateTimeInterface ) {
            return $value->format( DATE_ATOM );
        }

        if ( is_array( $value ) ) {
            $out = array();
            foreach ( $value as $key => $item ) {
                $out[ $key ] = self::normalize_value( $item );
            }
            return $out;
        }

        if ( is_object( $value ) ) {
            if ( method_exists( $value, 'get_data' ) ) {
                return self::normalize_value( $value->get_data() );
            }

            return self::normalize_value( get_object_vars( $value ) );
        }

        return (string) $value;
    }

    private static function date_value( $date ): ?string {
        return $date instanceof WC_DateTime ? $date->date( DATE_ATOM ) : null;
    }

    private static function date_value_gmt( $date ): ?string {
        if ( ! $date instanceof WC_DateTime ) {
            return null;
        }

        return gmdate( 'Y-m-d\TH:i:s', $date->getTimestamp() );
    }
}
