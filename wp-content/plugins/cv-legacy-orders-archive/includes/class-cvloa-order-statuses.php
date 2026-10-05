<?php
defined( 'ABSPATH' ) || exit;

final class CVLOA_Order_Statuses {
    public static function init(): void {
        if ( class_exists( 'CV_Core_Order_History' ) ) {
            CV_Core_Order_History::merge_permanent_statuses( self::cached_statuses() );
        }

        add_action( 'init', array( __CLASS__, 'register_cached_statuses' ), 40 );
        add_filter( 'wc_order_statuses', array( __CLASS__, 'add_cached_statuses_to_woocommerce' ), 40 );
    }

    public static function cached_statuses(): array {
        $cache = (array) get_option( CVLOA_STATUSES_OPTION, array() );

        return isset( $cache['statuses'] ) && is_array( $cache['statuses'] )
            ? $cache['statuses']
            : array();
    }

    public static function register_cached_statuses(): array {
        $result = array(
            'registered' => 0,
            'existing'   => 0,
            'skipped'    => 0,
            'warnings'   => array(),
        );

        foreach ( self::cached_statuses() as $row ) {
            $row    = (array) $row;
            $slug   = self::normalize_slug( (string) ( $row['slug'] ?? '' ) );
            $label  = sanitize_text_field( (string) ( $row['name'] ?? '' ) );
            $status = self::post_status_name( $slug );

            if ( '' === $slug || '' === $label ) {
                $result['skipped']++;
                continue;
            }

            /*
             * post_status / HPOS status continuam limitados a 20 caracteres.
             * Não truncamos porque isso poderia criar um estado diferente do
             * estado real da origem.
             */
            if ( strlen( $status ) > 20 ) {
                $result['skipped']++;
                $result['warnings'][] = sprintf(
                    'O estado "%1$s" (%2$s) não foi criado porque o slug excede o limite seguro de 20 caracteres.',
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
                        'cv-legacy-orders-archive'
                    ),
                )
            );

            if ( get_post_status_object( $status ) ) {
                $result['registered']++;
            } else {
                $result['skipped']++;
                $result['warnings'][] = sprintf(
                    'Não foi possível registar o estado "%1$s" (%2$s).',
                    $label,
                    $status
                );
            }
        }

        return $result;
    }

    public static function add_cached_statuses_to_woocommerce( array $statuses ): array {
        foreach ( self::cached_statuses() as $row ) {
            $row    = (array) $row;
            $slug   = self::normalize_slug( (string) ( $row['slug'] ?? '' ) );
            $label  = sanitize_text_field( (string) ( $row['name'] ?? '' ) );
            $status = self::post_status_name( $slug );

            if ( '' === $slug || '' === $label || strlen( $status ) > 20 ) {
                continue;
            }

            // Estados standard ou já fornecidos por outro plugin mantêm a
            // definição existente. Só acrescentamos os que estão realmente em falta.
            if ( ! array_key_exists( $status, $statuses ) ) {
                $statuses[ $status ] = $label;
            }
        }

        return $statuses;
    }

    public static function sync_now(): array {
        $before = function_exists( 'wc_get_order_statuses' )
            ? (array) wc_get_order_statuses()
            : array();

        $registration = self::register_cached_statuses();

        $after = function_exists( 'wc_get_order_statuses' )
            ? (array) wc_get_order_statuses()
            : array();

        $available = 0;
        foreach ( self::cached_statuses() as $row ) {
            $row    = (array) $row;
            $status = self::post_status_name( self::normalize_slug( (string) ( $row['slug'] ?? '' ) ) );

            if ( $status && isset( $after[ $status ] ) ) {
                $available++;
            }
        }

        $registration['available']       = $available;
        $registration['before_count']    = count( $before );
        $registration['after_count']     = count( $after );

        return $registration;
    }

    private static function normalize_slug( string $slug ): string {
        $slug = sanitize_key( $slug );
        $slug = preg_replace( '/^wc-/', '', $slug );

        return sanitize_key( (string) $slug );
    }

    private static function post_status_name( string $slug ): string {
        if ( '' === $slug ) {
            return '';
        }

        return 'wc-' . $slug;
    }
}
