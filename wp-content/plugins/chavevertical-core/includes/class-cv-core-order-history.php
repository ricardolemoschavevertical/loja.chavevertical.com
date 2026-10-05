<?php
defined( 'ABSPATH' ) || exit;

final class CV_Core_Order_History {
    private const ARCHIVE_HEADER = "<?php exit; ?>\n";
    private const STATUS_OPTION  = 'cv_core_permanent_order_statuses';

    public static function init(): void {
        add_action( 'init', array( __CLASS__, 'register_permanent_statuses' ), 35 );
        add_filter( 'wc_order_statuses', array( __CLASS__, 'add_permanent_statuses' ), 35 );
        add_action( 'wp_loaded', array( __CLASS__, 'replace_account_order_handlers' ), 30 );
    }

    public static function merge_permanent_statuses( array $statuses ): array {
        $stored = self::permanent_statuses();

        foreach ( $statuses as $key => $row ) {
            $row = is_array( $row ) ? $row : array();

            $slug = sanitize_key( (string) ( $row['slug'] ?? $key ) );
            $slug = preg_replace( '/^wc-/', '', $slug );
            $slug = sanitize_key( (string) $slug );
            $name = sanitize_text_field( (string) ( $row['name'] ?? '' ) );

            if ( '' === $slug || '' === $name ) {
                continue;
            }

            $stored[ $slug ] = array(
                'slug' => $slug,
                'name' => $name,
            );
        }

        update_option( self::STATUS_OPTION, $stored, false );

        // Disponibilizar já no request atual.
        self::register_permanent_statuses();

        return $stored;
    }

    public static function permanent_statuses(): array {
        $stored = (array) get_option( self::STATUS_OPTION, array() );
        $clean  = array();

        foreach ( $stored as $key => $row ) {
            $row  = is_array( $row ) ? $row : array();
            $slug = sanitize_key( (string) ( $row['slug'] ?? $key ) );
            $slug = preg_replace( '/^wc-/', '', $slug );
            $slug = sanitize_key( (string) $slug );
            $name = sanitize_text_field( (string) ( $row['name'] ?? '' ) );

            if ( '' !== $slug && '' !== $name ) {
                $clean[ $slug ] = array(
                    'slug' => $slug,
                    'name' => $name,
                );
            }
        }

        return $clean;
    }

    public static function register_permanent_statuses(): array {
        $result = array(
            'registered' => 0,
            'existing'   => 0,
            'skipped'    => 0,
            'warnings'   => array(),
        );

        foreach ( self::permanent_statuses() as $row ) {
            $slug   = (string) $row['slug'];
            $label  = (string) $row['name'];
            $status = 'wc-' . $slug;

            // WordPress/WooCommerce continuam a trabalhar com status <= 20 chars.
            if ( strlen( $status ) > 20 ) {
                $result['skipped']++;
                $result['warnings'][] = sprintf(
                    'O estado "%1$s" (%2$s) excede o limite de 20 caracteres.',
                    $label,
                    $status
                );
                continue;
            }

            if ( get_post_status_object( $status ) ) {
                $result['existing']++;
                continue;
            }

            register_post_status(
                $status,
                array(
                    'label'                     => $label,
                    'public'                    => false,
                    'exclude_from_search'       => false,
                    'show_in_admin_all_list'    => true,
                    'show_in_admin_status_list' => true,
                    'label_count'               => _n_noop(
                        $label . ' <span class="count">(%s)</span>',
                        $label . ' <span class="count">(%s)</span>',
                        'chavevertical-core'
                    ),
                )
            );

            if ( get_post_status_object( $status ) ) {
                $result['registered']++;
            } else {
                $result['skipped']++;
            }
        }

        return $result;
    }

    public static function add_permanent_statuses( array $statuses ): array {
        foreach ( self::permanent_statuses() as $row ) {
            $status = 'wc-' . (string) $row['slug'];

            if ( strlen( $status ) > 20 ) {
                continue;
            }

            if ( ! array_key_exists( $status, $statuses ) ) {
                $statuses[ $status ] = (string) $row['name'];
            }
        }

        return $statuses;
    }

    public static function replace_account_order_handlers(): void {
        if ( ! function_exists( 'wc_get_page_permalink' ) ) {
            return;
        }

        remove_action( 'woocommerce_account_orders_endpoint', 'woocommerce_account_orders' );
        add_action( 'woocommerce_account_orders_endpoint', array( __CLASS__, 'render_account_orders' ) );

        remove_action( 'woocommerce_account_view-order_endpoint', 'woocommerce_account_view_order' );
        add_action( 'woocommerce_account_view-order_endpoint', array( __CLASS__, 'render_account_view_order' ) );
    }

    public static function render_account_orders( $current_page = 1 ): void {
        if ( ! is_user_logged_in() ) {
            return;
        }

        $user         = wp_get_current_user();
        $current_page = max( 1, absint( $current_page ) );
        $per_page     = max( 1, absint( apply_filters( 'cv_core_account_orders_per_page', 10 ) ) );
        $records      = array();

        $live_orders = wc_get_orders(
            array(
                'customer_id' => (int) $user->ID,
                'limit'       => -1,
                'orderby'     => 'date',
                'order'       => 'DESC',
            )
        );

        foreach ( $live_orders as $order ) {
            if ( ! $order instanceof WC_Order ) {
                continue;
            }

            $date = $order->get_date_created();

            $records[] = array(
                'type'      => 'live',
                'timestamp' => $date ? $date->getTimestamp() : 0,
                'order'     => $order,
            );
        }

        foreach ( self::customer_archive_summaries( (int) $user->ID, (string) $user->user_email ) as $summary ) {
            $summary = (array) $summary;

            // Se uma encomenda local ainda existir no WooCommerce, mostrar apenas
            // a versão viva para não duplicar durante uma eventual falha de remoção.
            if (
                'local' === (string) ( $summary['origin'] ?? '' )
                && ! empty( $summary['id'] )
                && wc_get_order( absint( $summary['id'] ) )
            ) {
                continue;
            }

            $timestamp = strtotime( (string) ( $summary['date_created'] ?? '' ) ) ?: 0;

            $records[] = array(
                'type'      => 'archive',
                'timestamp' => $timestamp,
                'summary'   => $summary,
            );
        }

        usort(
            $records,
            static fn( array $a, array $b ): int => (int) $b['timestamp'] <=> (int) $a['timestamp']
        );

        $total       = count( $records );
        $max_pages   = max( 1, (int) ceil( $total / $per_page ) );
        $current_page = min( $current_page, $max_pages );
        $page_rows   = array_slice( $records, ( $current_page - 1 ) * $per_page, $per_page );
        $columns     = wc_get_account_orders_columns();

        if ( ! $page_rows ) {
            wc_print_notice( esc_html__( 'Ainda não foram efetuadas encomendas.', 'woocommerce' ), 'notice' );
            return;
        }
        ?>
        <table class="woocommerce-orders-table woocommerce-MyAccount-orders shop_table shop_table_responsive my_account_orders account-orders-table">
            <thead>
                <tr>
                    <?php foreach ( $columns as $column_id => $column_name ) : ?>
                        <th class="woocommerce-orders-table__header woocommerce-orders-table__header-<?php echo esc_attr( $column_id ); ?>">
                            <span class="nobr"><?php echo esc_html( $column_name ); ?></span>
                        </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $page_rows as $record ) : ?>
                    <?php
                    $is_live = 'live' === $record['type'];
                    $order   = $is_live ? $record['order'] : null;
                    $summary = $is_live ? array() : (array) $record['summary'];
                    ?>
                    <tr class="woocommerce-orders-table__row woocommerce-orders-table__row--status-<?php echo esc_attr( $is_live ? $order->get_status() : sanitize_key( (string) ( $summary['status'] ?? '' ) ) ); ?> order">
                        <?php foreach ( $columns as $column_id => $column_name ) : ?>
                            <td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-<?php echo esc_attr( $column_id ); ?>" data-title="<?php echo esc_attr( $column_name ); ?>">
                                <?php
                                if ( 'order-number' === $column_id ) {
                                    $number = $is_live ? $order->get_order_number() : (string) ( $summary['number'] ?? $summary['id'] ?? '' );
                                    $url    = $is_live ? $order->get_view_order_url() : self::archive_view_url( (string) ( $summary['archive_key'] ?? '' ) );
                                    echo '<a href="' . esc_url( $url ) . '">#' . esc_html( $number ) . '</a>';
                                } elseif ( 'order-date' === $column_id ) {
                                    $date_value = $is_live
                                        ? ( $order->get_date_created() ? $order->get_date_created()->date_i18n( get_option( 'date_format' ) ) : '' )
                                        : self::format_archive_date( (string) ( $summary['date_created'] ?? '' ) );
                                    echo esc_html( $date_value );
                                } elseif ( 'order-status' === $column_id ) {
                                    $status = $is_live ? $order->get_status() : (string) ( $summary['status'] ?? '' );
                                    echo esc_html( self::status_label( $status ) );
                                } elseif ( 'order-total' === $column_id ) {
                                    if ( $is_live ) {
                                        echo wp_kses_post( $order->get_formatted_order_total() );
                                    } else {
                                        echo wp_kses_post(
                                            wc_price(
                                                (float) ( $summary['total'] ?? 0 ),
                                                array( 'currency' => (string) ( $summary['currency'] ?? get_woocommerce_currency() ) )
                                            )
                                        );
                                    }
                                } elseif ( 'order-actions' === $column_id ) {
                                    if ( $is_live ) {
                                        $actions = wc_get_account_orders_actions( $order );
                                        foreach ( $actions as $key => $action ) {
                                            echo '<a href="' . esc_url( $action['url'] ) . '" class="woocommerce-button button ' . esc_attr( sanitize_html_class( $key ) ) . '">' . esc_html( $action['name'] ) . '</a> ';
                                        }
                                    } else {
                                        echo '<a href="' . esc_url( self::archive_view_url( (string) ( $summary['archive_key'] ?? '' ) ) ) . '" class="woocommerce-button button view">' . esc_html__( 'Ver', 'woocommerce' ) . '</a>';
                                    }
                                } else {
                                    if ( $is_live ) {
                                        do_action( 'woocommerce_my_account_my_orders_column_' . $column_id, $order );
                                    } else {
                                        echo '&mdash;';
                                    }
                                }
                                ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ( $max_pages > 1 ) : ?>
            <div class="woocommerce-pagination woocommerce-pagination--without-numbers woocommerce-Pagination">
                <?php if ( $current_page > 1 ) : ?>
                    <a class="woocommerce-button woocommerce-button--previous button" href="<?php echo esc_url( wc_get_endpoint_url( 'orders', $current_page - 1, wc_get_page_permalink( 'myaccount' ) ) ); ?>"><?php esc_html_e( 'Anterior', 'woocommerce' ); ?></a>
                <?php endif; ?>
                <?php if ( $current_page < $max_pages ) : ?>
                    <a class="woocommerce-button woocommerce-button--next button" href="<?php echo esc_url( wc_get_endpoint_url( 'orders', $current_page + 1, wc_get_page_permalink( 'myaccount' ) ) ); ?>"><?php esc_html_e( 'Seguinte', 'woocommerce' ); ?></a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <?php
    }

    public static function render_account_view_order( $value ): void {
        $value = (string) $value;

        if ( ! str_starts_with( $value, 'archive-' ) ) {
            if ( function_exists( 'woocommerce_account_view_order' ) ) {
                woocommerce_account_view_order( absint( $value ) );
            }
            return;
        }

        if ( ! is_user_logged_in() ) {
            wc_print_notice( esc_html__( 'Esta encomenda não está disponível.', 'woocommerce' ), 'error' );
            return;
        }

        $archive_key = sanitize_text_field( substr( $value, 8 ) );
        $summary     = self::archive_summary( $archive_key );
        $user        = wp_get_current_user();

        if ( ! $summary || ! self::summary_belongs_to_customer( $summary, (int) $user->ID, (string) $user->user_email ) ) {
            wc_print_notice( esc_html__( 'Esta encomenda não está disponível.', 'woocommerce' ), 'error' );
            return;
        }

        $order = self::read_archive_order( $archive_key );

        if ( ! $order ) {
            wc_print_notice( esc_html__( 'Não foi possível abrir esta encomenda.', 'woocommerce' ), 'error' );
            return;
        }

        self::render_archived_order_detail( $order );
    }

    private static function customer_archive_summaries( int $user_id, string $email ): array {
        $index = self::archive_index();
        $rows  = array();

        foreach ( (array) ( $index['orders'] ?? array() ) as $key => $summary ) {
            $summary = (array) $summary;
            $summary['archive_key'] = (string) ( $summary['archive_key'] ?? $key );

            if ( self::summary_belongs_to_customer( $summary, $user_id, $email ) ) {
                $rows[] = $summary;
            }
        }

        return $rows;
    }

    private static function summary_belongs_to_customer( array $summary, int $user_id, string $email ): bool {
        $origin       = (string) ( $summary['origin'] ?? '' );
        $customer_id  = absint( $summary['customer_id'] ?? 0 );
        $billing_email = strtolower( trim( (string) ( $summary['billing_email'] ?? '' ) ) );
        $email         = strtolower( trim( $email ) );

        if ( 'local' === $origin && $customer_id && $customer_id === $user_id ) {
            return true;
        }

        return '' !== $email && '' !== $billing_email && hash_equals( $email, $billing_email );
    }

    public static function archive_root_dir(): string {
        if ( defined( 'CV_ORDER_ARCHIVE_DIR' ) && CV_ORDER_ARCHIVE_DIR ) {
            return untrailingslashit( (string) CV_ORDER_ARCHIVE_DIR );
        }

        // ABSPATH = /home/.../htdocs/loja.chavevertical.com/
        // Dois níveis acima fica fora do document root publicado pelo domínio.
        return trailingslashit( dirname( dirname( ABSPATH ) ) ) . 'private-data/chavevertical/legacy-orders';
    }

    private static function archive_index_path(): string {
        return trailingslashit( self::archive_root_dir() ) . 'orders-index.json.php';
    }

    private static function archive_data_path(): string {
        return trailingslashit( self::archive_root_dir() ) . 'orders.ndjson.php';
    }

    private static function archive_index(): array {
        $path = self::archive_index_path();

        if ( ! is_readable( $path ) ) {
            return array( 'orders' => array() );
        }

        $raw = @file_get_contents( $path );
        if ( false === $raw ) {
            return array( 'orders' => array() );
        }

        if ( str_starts_with( $raw, self::ARCHIVE_HEADER ) ) {
            $raw = substr( $raw, strlen( self::ARCHIVE_HEADER ) );
        }

        $decoded = json_decode( $raw, true );

        if ( ! is_array( $decoded ) ) {
            return array( 'orders' => array() );
        }

        $decoded['orders'] = isset( $decoded['orders'] ) && is_array( $decoded['orders'] )
            ? $decoded['orders']
            : array();

        return $decoded;
    }

    private static function archive_summary( string $key ): ?array {
        $index = self::archive_index();

        if ( isset( $index['orders'][ $key ] ) && is_array( $index['orders'][ $key ] ) ) {
            $summary = (array) $index['orders'][ $key ];
            $summary['archive_key'] = (string) ( $summary['archive_key'] ?? $key );
            return $summary;
        }

        return null;
    }

    private static function read_archive_order( string $key ): ?array {
        $summary = self::archive_summary( $key );
        if ( ! $summary ) {
            return null;
        }

        $offset = absint( $summary['offset'] ?? 0 );
        $length = absint( $summary['length'] ?? 0 );
        $path   = self::archive_data_path();

        if ( ! $length || ! is_readable( $path ) ) {
            return null;
        }

        $handle = @fopen( $path, 'rb' );
        if ( false === $handle ) {
            return null;
        }

        try {
            if ( 0 !== fseek( $handle, $offset ) ) {
                return null;
            }
            $raw = fread( $handle, $length );
        } finally {
            fclose( $handle );
        }

        if ( false === $raw || '' === $raw ) {
            return null;
        }

        $decoded = json_decode( trim( $raw ), true );
        return is_array( $decoded ) ? $decoded : null;
    }

    private static function archive_view_url( string $key ): string {
        return wc_get_endpoint_url(
            'view-order',
            'archive-' . rawurlencode( $key ),
            wc_get_page_permalink( 'myaccount' )
        );
    }

    private static function status_label( string $status ): string {
        $status = sanitize_key( preg_replace( '/^wc-/', '', $status ) );

        if ( '' === $status ) {
            return '';
        }

        $stored = self::permanent_statuses();
        if ( ! empty( $stored[ $status ]['name'] ) ) {
            return (string) $stored[ $status ]['name'];
        }

        $label = wc_get_order_status_name( $status );
        return $label ?: ucfirst( str_replace( '-', ' ', $status ) );
    }

    private static function format_archive_date( string $date ): string {
        $timestamp = strtotime( $date );
        return $timestamp ? wp_date( get_option( 'date_format' ), $timestamp ) : $date;
    }

    private static function render_archived_order_detail( array $order ): void {
        $number   = (string) ( $order['number'] ?? $order['id'] ?? '' );
        $status   = self::status_label( (string) ( $order['status'] ?? '' ) );
        $currency = (string) ( $order['currency'] ?? get_woocommerce_currency() );
        $created  = self::format_archive_date( (string) ( $order['date_created'] ?? '' ) );
        ?>
        <p>
            <?php
            echo wp_kses_post(
                sprintf(
                    'Encomenda <mark class="order-number">#%1$s</mark> efetuada em <mark class="order-date">%2$s</mark> encontra-se atualmente como <mark class="order-status">%3$s</mark>.',
                    esc_html( $number ),
                    esc_html( $created ),
                    esc_html( $status )
                )
            );
            ?>
        </p>

        <section class="woocommerce-order-details">
            <h2 class="woocommerce-order-details__title"><?php esc_html_e( 'Detalhes da encomenda', 'woocommerce' ); ?></h2>
            <table class="woocommerce-table woocommerce-table--order-details shop_table order_details">
                <thead>
                    <tr>
                        <th class="woocommerce-table__product-name product-name"><?php esc_html_e( 'Produto', 'woocommerce' ); ?></th>
                        <th class="woocommerce-table__product-table product-total"><?php esc_html_e( 'Total', 'woocommerce' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( (array) ( $order['line_items'] ?? array() ) as $item ) : ?>
                        <?php $item = (array) $item; ?>
                        <tr class="woocommerce-table__line-item order_item">
                            <td class="woocommerce-table__product-name product-name">
                                <?php echo esc_html( (string) ( $item['name'] ?? '' ) ); ?>
                                <strong class="product-quantity">×&nbsp;<?php echo esc_html( (string) absint( $item['quantity'] ?? 0 ) ); ?></strong>
                            </td>
                            <td class="woocommerce-table__product-total product-total">
                                <?php
                                echo wp_kses_post(
                                    wc_price(
                                        (float) ( $item['total'] ?? 0 ) + (float) ( $item['total_tax'] ?? 0 ),
                                        array( 'currency' => $currency )
                                    )
                                );
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <?php if ( isset( $order['shipping_total'] ) ) : ?>
                        <tr><th scope="row"><?php esc_html_e( 'Envio:', 'woocommerce' ); ?></th><td><?php echo wp_kses_post( wc_price( (float) $order['shipping_total'], array( 'currency' => $currency ) ) ); ?></td></tr>
                    <?php endif; ?>
                    <?php if ( ! empty( $order['payment_method_title'] ) ) : ?>
                        <tr><th scope="row"><?php esc_html_e( 'Método de pagamento:', 'woocommerce' ); ?></th><td><?php echo esc_html( (string) $order['payment_method_title'] ); ?></td></tr>
                    <?php endif; ?>
                    <tr><th scope="row"><?php esc_html_e( 'Total:', 'woocommerce' ); ?></th><td><?php echo wp_kses_post( wc_price( (float) ( $order['total'] ?? 0 ), array( 'currency' => $currency ) ) ); ?></td></tr>
                </tfoot>
            </table>
        </section>

        <?php
        $billing  = (array) ( $order['billing'] ?? array() );
        $shipping = (array) ( $order['shipping'] ?? array() );
        ?>
        <section class="woocommerce-customer-details">
            <section class="woocommerce-columns woocommerce-columns--2 woocommerce-columns--addresses col2-set addresses">
                <div class="woocommerce-column woocommerce-column--1 woocommerce-column--billing-address col-1">
                    <h2 class="woocommerce-column__title"><?php esc_html_e( 'Morada de faturação', 'woocommerce' ); ?></h2>
                    <?php self::render_archive_address( $billing ); ?>
                </div>
                <div class="woocommerce-column woocommerce-column--2 woocommerce-column--shipping-address col-2">
                    <h2 class="woocommerce-column__title"><?php esc_html_e( 'Morada de envio', 'woocommerce' ); ?></h2>
                    <?php self::render_archive_address( $shipping ); ?>
                </div>
            </section>
        </section>
        <?php
    }

    private static function render_archive_address( array $address ): void {
        $lines = array_filter(
            array(
                trim( (string) ( $address['first_name'] ?? '' ) . ' ' . (string) ( $address['last_name'] ?? '' ) ),
                (string) ( $address['company'] ?? '' ),
                (string) ( $address['address_1'] ?? '' ),
                (string) ( $address['address_2'] ?? '' ),
                trim( (string) ( $address['postcode'] ?? '' ) . ' ' . (string) ( $address['city'] ?? '' ) ),
                (string) ( $address['state'] ?? '' ),
                (string) ( $address['country'] ?? '' ),
            )
        );

        echo '<address>';
        if ( $lines ) {
            echo implode( '<br>', array_map( 'esc_html', $lines ) );
        } else {
            esc_html_e( 'Sem morada disponível.', 'woocommerce' );
        }

        if ( ! empty( $address['phone'] ) ) {
            echo '<p class="woocommerce-customer-details--phone">' . esc_html( (string) $address['phone'] ) . '</p>';
        }

        if ( ! empty( $address['email'] ) ) {
            echo '<p class="woocommerce-customer-details--email">' . esc_html( (string) $address['email'] ) . '</p>';
        }
        echo '</address>';
    }
}
