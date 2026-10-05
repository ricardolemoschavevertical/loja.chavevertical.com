<?php
defined( 'ABSPATH' ) || exit;

final class CVLOA_Customer_Identities {
    private const HEADER = "<?php exit; ?>\n";

    public static function path(): string {
        return trailingslashit( CVLOA_Archive::base_dir() ) . 'customer-identities.json.php';
    }

    public static function ensure_storage() {
        $ready = CVLOA_Archive::ensure_storage();
        if ( is_wp_error( $ready ) ) {
            return $ready;
        }

        if ( ! file_exists( self::path() ) ) {
            $initial = array(
                'version'    => 1,
                'updated_at' => '',
                'dirty'      => true,
                'profiles'   => array(),
                'email_map'  => array(),
                'nif_map'    => array(),
                'phone_map'  => array(),
                'stats'      => array(),
            );

            $written = self::write( $initial );
            if ( is_wp_error( $written ) ) {
                return $written;
            }
        }

        @chmod( self::path(), 0660 );
        return true;
    }

    public static function mark_dirty(): void {
        $data = self::load();
        if ( ! empty( $data['_error'] ) ) {
            return;
        }

        if ( empty( $data['dirty'] ) ) {
            $data['dirty'] = true;
            self::write( $data );
        }
    }

    public static function load(): array {
        $ready = self::ensure_storage();
        if ( is_wp_error( $ready ) ) {
            return array(
                'version'   => 1,
                'dirty'     => true,
                'profiles'  => array(),
                'email_map' => array(),
                'nif_map'   => array(),
                'phone_map' => array(),
                'stats'     => array(),
                '_error'    => $ready->get_error_message(),
            );
        }

        $raw = @file_get_contents( self::path() );
        if ( false === $raw ) {
            return array(
                'version'   => 1,
                'dirty'     => true,
                'profiles'  => array(),
                'email_map' => array(),
                'nif_map'   => array(),
                'phone_map' => array(),
                'stats'     => array(),
                '_error'    => 'Não foi possível ler o índice local de identidades de clientes.',
            );
        }

        if ( str_starts_with( $raw, self::HEADER ) ) {
            $raw = substr( $raw, strlen( self::HEADER ) );
        }

        $decoded = json_decode( $raw, true );
        if ( ! is_array( $decoded ) ) {
            return array(
                'version'   => 1,
                'dirty'     => true,
                'profiles'  => array(),
                'email_map' => array(),
                'nif_map'   => array(),
                'phone_map' => array(),
                'stats'     => array(),
                '_error'    => 'O índice local de identidades de clientes está inválido.',
            );
        }

        foreach ( array( 'profiles', 'email_map', 'nif_map', 'phone_map', 'stats' ) as $key ) {
            if ( ! isset( $decoded[ $key ] ) || ! is_array( $decoded[ $key ] ) ) {
                $decoded[ $key ] = array();
            }
        }

        return $decoded;
    }

    private static function write( array $data ) {
        $data['version']    = 1;
        $data['updated_at'] = gmdate( 'c' );

        $json = wp_json_encode(
            $data,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );

        if ( false === $json ) {
            return new WP_Error( 'cvloa_identity_encode_failed', 'Não foi possível gerar o índice de identidades.' );
        }

        $tmp = trailingslashit( CVLOA_Archive::base_dir() ) . '.customer-identities-' . wp_generate_password( 12, false, false ) . '.tmp';

        if ( false === @file_put_contents( $tmp, self::HEADER . $json, LOCK_EX ) ) {
            return new WP_Error( 'cvloa_identity_write_failed', 'Não foi possível escrever o índice temporário de identidades.' );
        }

        @chmod( $tmp, 0660 );

        if ( ! @rename( $tmp, self::path() ) ) {
            @unlink( $tmp );
            return new WP_Error( 'cvloa_identity_replace_failed', 'Não foi possível substituir o índice local de identidades.' );
        }

        @chmod( self::path(), 0660 );
        return true;
    }

    public static function rebuild() {
        $orders_index = CVLOA_Archive::load_index();
        if ( ! empty( $orders_index['_error'] ) ) {
            return new WP_Error( 'cvloa_identity_orders_index', (string) $orders_index['_error'] );
        }

        $customers_index = CVLOA_Customer_Archive::load_index();
        if ( ! empty( $customers_index['_error'] ) ) {
            return new WP_Error( 'cvloa_identity_customers_index', (string) $customers_index['_error'] );
        }

        @set_time_limit( 300 );

        $profiles  = array();
        $email_map = array();
        $nif_map   = array();
        $phone_map = array();
        $next_id   = 1;

        $customer_handle = @fopen( CVLOA_Customer_Archive::data_path(), 'rb' );
        if ( false !== $customer_handle ) {
            try {
                foreach ( (array) ( $customers_index['customers'] ?? array() ) as $archive_key => $entry ) {
                    $entry = (array) $entry;

                    if ( 'guest-orders' === sanitize_key( (string) ( $entry['origin'] ?? '' ) ) ) {
                        continue;
                    }

                    $customer = self::read_entry( $customer_handle, $entry );
                    if ( ! is_array( $customer ) ) {
                        continue;
                    }

                    $billing  = (array) ( $customer['billing'] ?? array() );
                    $shipping = (array) ( $customer['shipping'] ?? array() );

                    self::add_observation(
                        $profiles,
                        $email_map,
                        $nif_map,
                        $phone_map,
                        $next_id,
                        array(
                            'emails'                   => self::emails_from_customer( $customer ),
                            'nifs'                     => self::nifs_from_record( $customer ),
                            'phones'                   => self::phones_from_customer( $customer ),
                            'names'                    => array_filter(
                                array(
                                    trim( (string) ( $customer['first_name'] ?? '' ) . ' ' . (string) ( $customer['last_name'] ?? '' ) ),
                                )
                            ),
                            'companies'                => array_filter( array( (string) ( $billing['company'] ?? '' ) ) ),
                            'registered_customer_keys' => array( sanitize_key( (string) $archive_key ) ),
                            'order_keys'               => array(),
                            'guest_order_keys'         => array(),
                            'billing'                  => $billing,
                            'shipping'                 => $shipping,
                            'first_name'               => sanitize_text_field( (string) ( $customer['first_name'] ?? $billing['first_name'] ?? '' ) ),
                            'last_name'                => sanitize_text_field( (string) ( $customer['last_name'] ?? $billing['last_name'] ?? '' ) ),
                            'company'                  => sanitize_text_field( (string) ( $billing['company'] ?? '' ) ),
                            'latest_at'                => (string) ( $customer['date_modified'] ?? $customer['date_created'] ?? '' ),
                            'total_spent'              => (float) ( $customer['total_spent'] ?? 0 ),
                        )
                    );
                }
            } finally {
                fclose( $customer_handle );
            }
        }

        $orders_handle = @fopen( CVLOA_Archive::data_path(), 'rb' );
        $orders_scanned = 0;
        $guest_orders   = 0;

        if ( false === $orders_handle ) {
            return new WP_Error( 'cvloa_identity_orders_open', 'Não foi possível abrir o arquivo local de encomendas.' );
        }

        try {
            foreach ( (array) ( $orders_index['orders'] ?? array() ) as $archive_key => $entry ) {
                $entry = (array) $entry;
                $order = self::read_entry( $orders_handle, $entry );

                if ( ! is_array( $order ) ) {
                    continue;
                }

                $orders_scanned++;

                $billing    = (array) ( $order['billing'] ?? array() );
                $shipping   = (array) ( $order['shipping'] ?? array() );
                $is_guest   = 0 === absint( $order['customer_id'] ?? 0 );

                if ( $is_guest ) {
                    $guest_orders++;
                }

                self::add_observation(
                    $profiles,
                    $email_map,
                    $nif_map,
                    $phone_map,
                    $next_id,
                    array(
                        'emails'                   => self::emails_from_order( $order ),
                        'nifs'                     => self::nifs_from_record( $order ),
                        'phones'                   => self::phones_from_order( $order ),
                        'names'                    => array_filter(
                            array(
                                trim( (string) ( $billing['first_name'] ?? '' ) . ' ' . (string) ( $billing['last_name'] ?? '' ) ),
                            )
                        ),
                        'companies'                => array_filter( array( (string) ( $billing['company'] ?? '' ) ) ),
                        'registered_customer_keys' => array(),
                        'order_keys'               => array( sanitize_key( (string) $archive_key ) ),
                        'guest_order_keys'         => $is_guest ? array( sanitize_key( (string) $archive_key ) ) : array(),
                        'billing'                  => $billing,
                        'shipping'                 => $shipping,
                        'first_name'               => sanitize_text_field( (string) ( $billing['first_name'] ?? '' ) ),
                        'last_name'                => sanitize_text_field( (string) ( $billing['last_name'] ?? '' ) ),
                        'company'                  => sanitize_text_field( (string) ( $billing['company'] ?? '' ) ),
                        'latest_at'                => (string) ( $order['date_created'] ?? '' ),
                        'total_spent'              => (float) ( $order['total'] ?? 0 ),
                    )
                );
            }
        } finally {
            fclose( $orders_handle );
        }

        $final_profiles  = array();
        $final_email_map = array();
        $final_nif_map   = array();
        $final_phone_map = array();

        foreach ( $profiles as $profile ) {
            $profile = self::finalize_profile( (array) $profile );
            if ( ! $profile['emails'] && ! $profile['nifs'] && ! $profile['phones'] ) {
                continue;
            }

            $signature = implode(
                '|',
                array_merge(
                    array_map( static fn( $v ) => 'e:' . $v, $profile['emails'] ),
                    array_map( static fn( $v ) => 'n:' . $v, $profile['nifs'] ),
                    array_map( static fn( $v ) => 'p:' . $v, $profile['phones'] )
                )
            );

            $identity_key = 'identity-' . substr( sha1( $signature ), 0, 20 );
            $profile['identity_key'] = $identity_key;
            $final_profiles[ $identity_key ] = $profile;

            foreach ( $profile['emails'] as $email ) {
                $final_email_map[ $email ] = $identity_key;
            }
            foreach ( $profile['nifs'] as $nif ) {
                $final_nif_map[ $nif ] = $identity_key;
            }
            foreach ( $profile['phones'] as $phone ) {
                $final_phone_map[ $phone ] = $identity_key;
            }
        }

        $guest_profiles = 0;
        foreach ( $final_profiles as $profile ) {
            if ( ! empty( $profile['guest_order_keys'] ) ) {
                $guest_profiles++;
            }
        }

        $data = array(
            'version'    => 1,
            'updated_at' => gmdate( 'c' ),
            'dirty'      => false,
            'profiles'   => $final_profiles,
            'email_map'  => $final_email_map,
            'nif_map'    => $final_nif_map,
            'phone_map'  => $final_phone_map,
            'stats'      => array(
                'orders_scanned' => $orders_scanned,
                'guest_orders'   => $guest_orders,
                'profiles'       => count( $final_profiles ),
                'guest_profiles' => $guest_profiles,
            ),
        );

        $written = self::write( $data );
        if ( is_wp_error( $written ) ) {
            return $written;
        }

        $GLOBALS['cvloa_rebuilding_identities'] = true;
        try {
            $guest_sync = CVLOA_Customer_Archive::replace_guest_profiles( $final_profiles );
        } finally {
            unset( $GLOBALS['cvloa_rebuilding_identities'] );
        }

        if ( is_wp_error( $guest_sync ) ) {
            return $guest_sync;
        }

        return array(
            'stats'      => $data['stats'],
            'guest_sync' => $guest_sync,
        );
    }

    public static function find_by_email( string $email ): ?array {
        $email = self::normalize_email( $email );
        if ( '' === $email ) {
            return null;
        }

        $data = self::load();
        $key  = sanitize_key( (string) ( $data['email_map'][ $email ] ?? '' ) );

        if ( '' === $key || empty( $data['profiles'][ $key ] ) ) {
            return null;
        }

        return (array) $data['profiles'][ $key ];
    }

    public static function orders_for_email( string $email ): array {
        $profile = self::find_by_email( $email );

        if ( ! is_array( $profile ) ) {
            return CVLOA_Archive::find_by_billing_email( $email );
        }

        return CVLOA_Archive::summaries_by_keys( (array) ( $profile['order_keys'] ?? array() ) );
    }

    public static function email_owns_order( string $email, string $order_key ): bool {
        $profile = self::find_by_email( $email );

        if ( is_array( $profile ) ) {
            return in_array(
                sanitize_key( $order_key ),
                array_map( 'sanitize_key', (array) ( $profile['order_keys'] ?? array() ) ),
                true
            );
        }

        $order = CVLOA_Archive::read_order( $order_key );
        if ( ! is_array( $order ) ) {
            return false;
        }

        $billing = (array) ( $order['billing'] ?? array() );

        return self::normalize_email( (string) ( $billing['email'] ?? '' ) ) === self::normalize_email( $email );
    }

    public static function stats(): array {
        $data = self::load();

        return array(
            'dirty'       => ! empty( $data['dirty'] ),
            'updated_at'  => (string) ( $data['updated_at'] ?? '' ),
            'bytes'       => file_exists( self::path() ) ? (int) filesize( self::path() ) : 0,
            'profiles'    => absint( $data['stats']['profiles'] ?? 0 ),
            'guest_profiles' => absint( $data['stats']['guest_profiles'] ?? 0 ),
            'guest_orders'   => absint( $data['stats']['guest_orders'] ?? 0 ),
            'orders_scanned' => absint( $data['stats']['orders_scanned'] ?? 0 ),
        );
    }

    private static function read_entry( $handle, array $entry ): ?array {
        $offset = absint( $entry['offset'] ?? 0 );
        $length = absint( $entry['length'] ?? 0 );

        if ( ! $length || 0 !== fseek( $handle, $offset ) ) {
            return null;
        }

        $raw = fread( $handle, $length );
        if ( false === $raw || '' === $raw ) {
            return null;
        }

        $decoded = json_decode( trim( $raw ), true );
        return is_array( $decoded ) ? $decoded : null;
    }

    private static function add_observation(
        array &$profiles,
        array &$email_map,
        array &$nif_map,
        array &$phone_map,
        int &$next_id,
        array $observation
    ): void {
        $emails = array_values( array_unique( array_filter( array_map( array( __CLASS__, 'normalize_email' ), (array) ( $observation['emails'] ?? array() ) ) ) ) );
        $nifs   = array_values( array_unique( array_filter( array_map( array( __CLASS__, 'normalize_nif' ), (array) ( $observation['nifs'] ?? array() ) ) ) ) );
        $phones = array_values( array_unique( array_filter( array_map( array( __CLASS__, 'normalize_phone' ), (array) ( $observation['phones'] ?? array() ) ) ) ) );

        if ( ! $emails && ! $nifs && ! $phones ) {
            return;
        }

        $matches = array();

        foreach ( $emails as $value ) {
            if ( isset( $email_map[ $value ] ) ) {
                $matches[] = (int) $email_map[ $value ];
            }
        }
        foreach ( $nifs as $value ) {
            if ( isset( $nif_map[ $value ] ) ) {
                $matches[] = (int) $nif_map[ $value ];
            }
        }
        foreach ( $phones as $value ) {
            if ( isset( $phone_map[ $value ] ) ) {
                $matches[] = (int) $phone_map[ $value ];
            }
        }

        $matches = array_values( array_unique( array_filter( $matches ) ) );

        if ( ! $matches ) {
            $base_id = $next_id++;
            $profiles[ $base_id ] = self::empty_profile();
        } else {
            $base_id = (int) array_shift( $matches );

            foreach ( $matches as $merge_id ) {
                $merge_id = (int) $merge_id;
                if ( $merge_id === $base_id || empty( $profiles[ $merge_id ] ) ) {
                    continue;
                }

                $profiles[ $base_id ] = self::merge_profile_data(
                    (array) $profiles[ $base_id ],
                    (array) $profiles[ $merge_id ]
                );

                foreach ( (array) ( $profiles[ $merge_id ]['emails'] ?? array() ) as $value ) {
                    $email_map[ $value ] = $base_id;
                }
                foreach ( (array) ( $profiles[ $merge_id ]['nifs'] ?? array() ) as $value ) {
                    $nif_map[ $value ] = $base_id;
                }
                foreach ( (array) ( $profiles[ $merge_id ]['phones'] ?? array() ) as $value ) {
                    $phone_map[ $value ] = $base_id;
                }

                unset( $profiles[ $merge_id ] );
            }
        }

        $observation['emails'] = $emails;
        $observation['nifs']   = $nifs;
        $observation['phones'] = $phones;

        $profiles[ $base_id ] = self::merge_profile_data(
            (array) $profiles[ $base_id ],
            $observation
        );

        foreach ( (array) $profiles[ $base_id ]['emails'] as $value ) {
            $email_map[ $value ] = $base_id;
        }
        foreach ( (array) $profiles[ $base_id ]['nifs'] as $value ) {
            $nif_map[ $value ] = $base_id;
        }
        foreach ( (array) $profiles[ $base_id ]['phones'] as $value ) {
            $phone_map[ $value ] = $base_id;
        }
    }

    private static function empty_profile(): array {
        return array(
            'emails'                   => array(),
            'nifs'                     => array(),
            'phones'                   => array(),
            'names'                    => array(),
            'companies'                => array(),
            'registered_customer_keys' => array(),
            'order_keys'               => array(),
            'guest_order_keys'         => array(),
            'billing'                  => array(),
            'shipping'                 => array(),
            'first_name'               => '',
            'last_name'                => '',
            'company'                  => '',
            'latest_at'                => '',
            'total_spent'              => 0.0,
        );
    }

    private static function merge_profile_data( array $base, array $incoming ): array {
        foreach ( array( 'emails', 'nifs', 'phones', 'names', 'companies', 'registered_customer_keys', 'order_keys', 'guest_order_keys' ) as $key ) {
            $base[ $key ] = array_values(
                array_unique(
                    array_filter(
                        array_merge(
                            (array) ( $base[ $key ] ?? array() ),
                            (array) ( $incoming[ $key ] ?? array() )
                        ),
                        static fn( $value ): bool => '' !== trim( (string) $value )
                    )
                )
            );
        }

        $base['total_spent'] = (float) ( $base['total_spent'] ?? 0 ) + (float) ( $incoming['total_spent'] ?? 0 );

        $base_time     = strtotime( (string) ( $base['latest_at'] ?? '' ) ) ?: 0;
        $incoming_time = strtotime( (string) ( $incoming['latest_at'] ?? '' ) ) ?: 0;

        if ( $incoming_time >= $base_time ) {
            foreach ( array( 'billing', 'shipping', 'first_name', 'last_name', 'company', 'latest_at' ) as $key ) {
                if ( isset( $incoming[ $key ] ) && ( is_array( $incoming[ $key ] ) || '' !== trim( (string) $incoming[ $key ] ) ) ) {
                    $base[ $key ] = $incoming[ $key ];
                }
            }
        }

        return $base;
    }

    private static function finalize_profile( array $profile ): array {
        foreach ( array( 'emails', 'nifs', 'phones', 'names', 'companies', 'registered_customer_keys', 'order_keys', 'guest_order_keys' ) as $key ) {
            $profile[ $key ] = array_values( array_unique( array_filter( (array) ( $profile[ $key ] ?? array() ) ) ) );
            sort( $profile[ $key ], SORT_NATURAL | SORT_FLAG_CASE );
        }

        $profile['primary_email'] = (string) ( $profile['emails'][0] ?? '' );
        $profile['primary_nif']   = (string) ( $profile['nifs'][0] ?? '' );
        $profile['primary_phone'] = (string) ( $profile['phones'][0] ?? '' );
        $profile['orders_count']  = count( $profile['order_keys'] );
        $profile['guest_orders_count'] = count( $profile['guest_order_keys'] );
        $profile['total_spent']   = (float) ( $profile['total_spent'] ?? 0 );

        return $profile;
    }

    private static function emails_from_customer( array $customer ): array {
        $billing = (array) ( $customer['billing'] ?? array() );

        return array(
            (string) ( $customer['email'] ?? '' ),
            (string) ( $billing['email'] ?? '' ),
        );
    }

    private static function emails_from_order( array $order ): array {
        $billing = (array) ( $order['billing'] ?? array() );

        return array(
            (string) ( $billing['email'] ?? '' ),
        );
    }

    private static function phones_from_customer( array $customer ): array {
        $billing  = (array) ( $customer['billing'] ?? array() );
        $shipping = (array) ( $customer['shipping'] ?? array() );

        return array(
            (string) ( $billing['phone'] ?? '' ),
            (string) ( $shipping['phone'] ?? '' ),
        );
    }

    private static function phones_from_order( array $order ): array {
        $billing  = (array) ( $order['billing'] ?? array() );
        $shipping = (array) ( $order['shipping'] ?? array() );

        return array(
            (string) ( $billing['phone'] ?? '' ),
            (string) ( $shipping['phone'] ?? '' ),
        );
    }

    private static function nifs_from_record( array $record ): array {
        $values = array();
        $billing = (array) ( $record['billing'] ?? array() );

        foreach ( array( 'nif', 'nipc', 'nif_nipc', 'vat', 'vat_number', 'tax_id', 'fiscal_number' ) as $key ) {
            if ( isset( $billing[ $key ] ) ) {
                $values[] = (string) $billing[ $key ];
            }
        }

        $known_meta_keys = array(
            'billing_nif',
            '_billing_nif',
            'billing_nipc',
            '_billing_nipc',
            'billing_nif_nipc',
            '_billing_nif_nipc',
            'nif',
            'nipc',
            'billing_vat',
            '_billing_vat',
            'billing_vat_number',
            '_billing_vat_number',
            'vat_number',
            '_vat_number',
            'billing_tax_id',
            '_billing_tax_id',
            'billing_fiscal_number',
            '_billing_fiscal_number',
        );

        foreach ( (array) ( $record['meta_data'] ?? array() ) as $meta ) {
            $meta = (array) $meta;
            $key  = strtolower( trim( (string) ( $meta['key'] ?? '' ) ) );

            if ( in_array( $key, $known_meta_keys, true ) ) {
                $values[] = is_scalar( $meta['value'] ?? null ) ? (string) $meta['value'] : '';
            }
        }

        return $values;
    }

    private static function normalize_email( string $email ): string {
        return strtolower( sanitize_email( trim( $email ) ) );
    }

    private static function normalize_nif( string $value ): string {
        $value = strtoupper( trim( $value ) );
        $value = preg_replace( '/[^A-Z0-9]/', '', $value );

        if ( str_starts_with( $value, 'PT' ) && 11 === strlen( $value ) ) {
            $value = substr( $value, 2 );
        }

        return preg_match( '/^\d{9}$/', $value ) ? $value : '';
    }

    private static function normalize_phone( string $value ): string {
        $digits = preg_replace( '/\D+/', '', $value );

        if ( str_starts_with( $digits, '00' ) ) {
            $digits = substr( $digits, 2 );
        }

        if ( 12 === strlen( $digits ) && str_starts_with( $digits, '351' ) ) {
            $digits = substr( $digits, 3 );
        }

        $length = strlen( $digits );

        return $length >= 7 && $length <= 15 ? $digits : '';
    }
}
