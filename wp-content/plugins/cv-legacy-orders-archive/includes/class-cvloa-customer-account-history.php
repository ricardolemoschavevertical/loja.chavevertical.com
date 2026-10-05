<?php
defined( 'ABSPATH' ) || exit;

final class CVLOA_Customer_Account_History {
    private const ENDPOINT = 'encomendas-antigas';

    public static function init(): void {
        add_action( 'init', array( __CLASS__, 'register_endpoint' ) );
        add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'account_menu_items' ), 35 );
        add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', array( __CLASS__, 'render_endpoint' ) );
    }

    public static function register_endpoint(): void {
        add_rewrite_endpoint( self::ENDPOINT, EP_ROOT | EP_PAGES );
    }

    public static function account_menu_items( array $items ): array {
        if ( ! is_user_logged_in() ) {
            return $items;
        }

        $user = wp_get_current_user();
        if ( ! $user instanceof WP_User || ! $user->exists() ) {
            return $items;
        }

        $orders = CVLOA_Archive::find_by_billing_email( (string) $user->user_email );
        if ( ! $orders ) {
            return $items;
        }

        $new_items = array();

        foreach ( $items as $key => $label ) {
            $new_items[ $key ] = $label;

            if ( 'orders' === $key ) {
                $new_items[ self::ENDPOINT ] = 'Encomendas antigas';
            }
        }

        if ( ! isset( $new_items[ self::ENDPOINT ] ) ) {
            $new_items[ self::ENDPOINT ] = 'Encomendas antigas';
        }

        return $new_items;
    }

    public static function render_endpoint(): void {
        if ( ! is_user_logged_in() ) {
            return;
        }

        $user = wp_get_current_user();
        if ( ! $user instanceof WP_User || ! $user->exists() ) {
            return;
        }

        $email = sanitize_email( (string) $user->user_email );
        if ( '' === $email ) {
            echo '<p>Não foi possível identificar o email da conta.</p>';
            return;
        }

        $view_key = sanitize_key( (string) wp_unslash( $_GET['cvloa_order'] ?? '' ) );

        if ( $view_key ) {
            self::render_order_detail( $view_key, $email );
            return;
        }

        $orders = CVLOA_Archive::find_by_billing_email( $email );

        echo '<h2>' . esc_html__( 'Encomendas antigas', 'cv-legacy-orders-archive' ) . '</h2>';
        echo '<p>' . esc_html__( 'Histórico arquivado associado ao email da sua conta.', 'cv-legacy-orders-archive' ) . '</p>';

        if ( ! $orders ) {
            echo '<p>' . esc_html__( 'Não existem encomendas antigas associadas a esta conta.', 'cv-legacy-orders-archive' ) . '</p>';
            return;
        }

        echo '<table class="woocommerce-orders-table woocommerce-MyAccount-orders shop_table shop_table_responsive my_account_orders account-orders-table">';
        echo '<thead><tr>';
        echo '<th><span class="nobr">Encomenda</span></th>';
        echo '<th><span class="nobr">Data</span></th>';
        echo '<th><span class="nobr">Estado</span></th>';
        echo '<th><span class="nobr">Total</span></th>';
        echo '<th><span class="nobr">Ações</span></th>';
        echo '</tr></thead><tbody>';

        foreach ( $orders as $row ) {
            $archive_key = sanitize_key( (string) ( $row['archive_key'] ?? $row['id'] ?? '' ) );
            $number      = (string) ( $row['number'] ?: $row['id'] ?? '' );
            $status      = sanitize_key( preg_replace( '/^wc-/', '', (string) ( $row['status'] ?? '' ) ) );
            $status_name = wc_get_order_status_name( $status );
            if ( ! $status_name ) {
                $status_name = ucfirst( str_replace( '-', ' ', $status ) );
            }

            $url = add_query_arg(
                'cvloa_order',
                $archive_key,
                wc_get_account_endpoint_url( self::ENDPOINT )
            );

            echo '<tr class="woocommerce-orders-table__row order">';
            echo '<td data-title="Encomenda"><strong>#' . esc_html( $number ) . '</strong></td>';
            echo '<td data-title="Data">' . esc_html( self::format_date( (string) ( $row['date_created'] ?? '' ) ) ) . '</td>';
            echo '<td data-title="Estado">' . esc_html( $status_name ) . '</td>';
            echo '<td data-title="Total">' . wp_kses_post(
                wc_price(
                    (float) ( $row['total'] ?? 0 ),
                    array( 'currency' => (string) ( $row['currency'] ?? get_woocommerce_currency() ) )
                )
            ) . '</td>';
            echo '<td data-title="Ações"><a class="woocommerce-button button view" href="' . esc_url( $url ) . '">Ver</a></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    private static function render_order_detail( string $order_key, string $account_email ): void {
        $order = CVLOA_Archive::read_order( $order_key );

        if ( ! is_array( $order ) ) {
            wc_print_notice( 'Não foi possível encontrar esta encomenda antiga.', 'error' );
            return;
        }

        $billing = (array) ( $order['billing'] ?? array() );
        $email   = sanitize_email( (string) ( $billing['email'] ?? '' ) );

        if ( '' === $email || strtolower( $email ) !== strtolower( $account_email ) ) {
            wc_print_notice( 'Esta encomenda não está associada à sua conta.', 'error' );
            return;
        }

        $currency = (string) ( $order['currency'] ?? get_woocommerce_currency() );
        $back_url = wc_get_account_endpoint_url( self::ENDPOINT );

        echo '<p><a class="button" href="' . esc_url( $back_url ) . '">← Voltar às encomendas antigas</a></p>';
        echo '<h2>Encomenda #' . esc_html( (string) ( $order['number'] ?? $order['id'] ?? '' ) ) . '</h2>';
        echo '<p>Data: ' . esc_html( self::format_date( (string) ( $order['date_created'] ?? '' ) ) ) . '</p>';

        echo '<table class="shop_table shop_table_responsive">';
        echo '<thead><tr><th>Produto</th><th>SKU</th><th>Quantidade</th><th>Total</th></tr></thead><tbody>';

        foreach ( (array) ( $order['line_items'] ?? array() ) as $item ) {
            $item = (array) $item;
            echo '<tr>';
            echo '<td>' . esc_html( (string) ( $item['name'] ?? '' ) ) . '</td>';
            echo '<td>' . esc_html( (string) ( $item['sku'] ?? '' ) ) . '</td>';
            echo '<td>' . esc_html( (string) ( $item['quantity'] ?? 0 ) ) . '</td>';
            echo '<td>' . wp_kses_post(
                wc_price(
                    (float) ( $item['total'] ?? 0 ),
                    array( 'currency' => $currency )
                )
            ) . '</td>';
            echo '</tr>';
        }

        echo '</tbody><tfoot>';
        echo '<tr><th colspan="3">Transporte</th><td>' . wp_kses_post( wc_price( (float) ( $order['shipping_total'] ?? 0 ), array( 'currency' => $currency ) ) ) . '</td></tr>';
        echo '<tr><th colspan="3">IVA</th><td>' . wp_kses_post( wc_price( (float) ( $order['total_tax'] ?? 0 ), array( 'currency' => $currency ) ) ) . '</td></tr>';
        echo '<tr><th colspan="3">Total</th><td><strong>' . wp_kses_post( wc_price( (float) ( $order['total'] ?? 0 ), array( 'currency' => $currency ) ) ) . '</strong></td></tr>';
        echo '</tfoot></table>';
    }

    private static function format_date( string $value ): string {
        if ( '' === $value ) {
            return '—';
        }

        $time = strtotime( $value );
        return $time ? wp_date( 'd/m/Y H:i', $time ) : $value;
    }
}
