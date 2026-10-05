<?php
defined( 'ABSPATH' ) || exit;

final class CVLOA_Customer_Archive {
    private const HEADER = "<?php exit; ?>\n";

    public static function data_path(): string {
        return trailingslashit( CVLOA_Archive::base_dir() ) . 'customers.ndjson.php';
    }

    public static function index_path(): string {
        return trailingslashit( CVLOA_Archive::base_dir() ) . 'customers-index.json.php';
    }

    public static function ensure_storage() {
        $ready = CVLOA_Archive::ensure_storage();
        if ( is_wp_error( $ready ) ) {
            return $ready;
        }

        if ( ! file_exists( self::data_path() ) ) {
            if ( false === @file_put_contents( self::data_path(), self::HEADER, LOCK_EX ) ) {
                return new WP_Error( 'cvloa_customer_data_create_failed', 'Não foi possível criar o ficheiro local dos clientes.' );
            }
        }
        @chmod( self::data_path(), 0660 );

        if ( ! file_exists( self::index_path() ) ) {
            $created = self::write_index(
                array(
                    'version'    => 1,
                    'updated_at' => gmdate( 'c' ),
                    'customers'  => array(),
                )
            );

            if ( is_wp_error( $created ) ) {
                return $created;
            }
        }

        @chmod( self::index_path(), 0660 );

        if ( ! is_readable( self::data_path() ) || ! is_readable( self::index_path() ) ) {
            return new WP_Error( 'cvloa_customer_archive_not_readable', 'O arquivo local de clientes não tem permissões de leitura.' );
        }

        if ( ! is_writable( self::data_path() ) || ! is_writable( self::index_path() ) ) {
            return new WP_Error( 'cvloa_customer_archive_not_writable', 'O arquivo local de clientes não tem permissões de escrita.' );
        }

        return true;
    }

    public static function load_index(): array {
        $ready = self::ensure_storage();
        if ( is_wp_error( $ready ) ) {
            return array(
                'version'    => 1,
                'updated_at' => '',
                'customers'  => array(),
                '_error'     => $ready->get_error_message(),
            );
        }

        $raw = @file_get_contents( self::index_path() );
        if ( false === $raw ) {
            return array(
                'version'    => 1,
                'updated_at' => '',
                'customers'  => array(),
                '_error'     => 'Não foi possível ler o índice local dos clientes.',
            );
        }

        if ( str_starts_with( $raw, self::HEADER ) ) {
            $raw = substr( $raw, strlen( self::HEADER ) );
        }

        $decoded = json_decode( $raw, true );
        if ( ! is_array( $decoded ) ) {
            return array(
                'version'    => 1,
                'updated_at' => '',
                'customers'  => array(),
                '_error'     => 'O índice local dos clientes está inválido.',
            );
        }

        $decoded['customers'] = isset( $decoded['customers'] ) && is_array( $decoded['customers'] )
            ? $decoded['customers']
            : array();

        return $decoded;
    }

    private static function write_index( array $index ) {
        $dir = CVLOA_Archive::base_dir();
        if ( ! is_dir( $dir ) ) {
            return new WP_Error( 'cvloa_customer_index_dir_missing', 'A pasta privada do arquivo não existe.' );
        }

        $index['version']    = 1;
        $index['updated_at'] = gmdate( 'c' );

        $json = wp_json_encode(
            $index,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );

        if ( false === $json ) {
            return new WP_Error( 'cvloa_customer_index_encode_failed', 'Não foi possível gerar o índice dos clientes.' );
        }

        $tmp = trailingslashit( $dir ) . '.customers-index-' . wp_generate_password( 12, false, false ) . '.tmp';

        if ( false === @file_put_contents( $tmp, self::HEADER . $json, LOCK_EX ) ) {
            return new WP_Error( 'cvloa_customer_index_write_failed', 'Não foi possível escrever o índice temporário dos clientes.' );
        }

        @chmod( $tmp, 0660 );

        if ( ! @rename( $tmp, self::index_path() ) ) {
            @unlink( $tmp );
            return new WP_Error( 'cvloa_customer_index_replace_failed', 'Não foi possível substituir o índice local dos clientes.' );
        }

        @chmod( self::index_path(), 0660 );
        return true;
    }

    public static function archive_batch( array $customers, bool $replace_existing = false ) {
        $ready = self::ensure_storage();
        if ( is_wp_error( $ready ) ) {
            return $ready;
        }

        $index = self::load_index();
        if ( ! empty( $index['_error'] ) ) {
            return new WP_Error( 'cvloa_customer_index_error', (string) $index['_error'] );
        }

        $handle = @fopen( self::data_path(), 'c+b' );
        if ( false === $handle ) {
            return new WP_Error( 'cvloa_customer_data_open_failed', 'Não foi possível abrir o ficheiro local dos clientes.' );
        }

        if ( ! flock( $handle, LOCK_EX ) ) {
            fclose( $handle );
            return new WP_Error( 'cvloa_customer_data_lock_failed', 'Não foi possível bloquear o arquivo de clientes para escrita.' );
        }

        $result = array(
            'archived' => 0,
            'updated'  => 0,
            'ignored'  => 0,
            'errors'   => array(),
        );

        try {
            fseek( $handle, 0, SEEK_END );

            foreach ( $customers as $customer ) {
                $customer = (array) $customer;
                $id       = absint( $customer['id'] ?? 0 );

                if ( ! $id ) {
                    $result['errors'][] = 'Cliente sem ID na origem.';
                    continue;
                }

                $key = sanitize_key( (string) ( $customer['_cvloa_archive_key'] ?? '' ) );
                if ( '' === $key ) {
                    $key = (string) $id;
                }

                $customer['_cvloa_archive_key'] = $key;
                $existing = ! empty( $index['customers'][ $key ] );

                if ( $existing && ! $replace_existing ) {
                    $result['ignored']++;
                    continue;
                }

                $json = wp_json_encode(
                    $customer,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
                );

                if ( false === $json ) {
                    $result['errors'][] = '#' . $id . ': falha ao gerar JSON.';
                    continue;
                }

                $offset = ftell( $handle );
                $line   = $json . "\n";
                $bytes  = fwrite( $handle, $line );

                if ( false === $bytes || $bytes !== strlen( $line ) ) {
                    $result['errors'][] = '#' . $id . ': falha ao escrever no arquivo.';
                    continue;
                }

                $index['customers'][ $key ] = self::summarize_customer( $customer, $key, (int) $offset, (int) $bytes );

                if ( $existing ) {
                    $result['updated']++;
                } else {
                    $result['archived']++;
                }
            }

            fflush( $handle );
        } finally {
            flock( $handle, LOCK_UN );
            fclose( $handle );
        }

        $written = self::write_index( $index );
        if ( is_wp_error( $written ) ) {
            return $written;
        }

        return $result;
    }

    private static function summarize_customer( array $customer, string $archive_key, int $offset, int $length ): array {
        $billing  = (array) ( $customer['billing'] ?? array() );
        $shipping = (array) ( $customer['shipping'] ?? array() );

        $first_name = sanitize_text_field( (string) ( $customer['first_name'] ?? $billing['first_name'] ?? '' ) );
        $last_name  = sanitize_text_field( (string) ( $customer['last_name'] ?? $billing['last_name'] ?? '' ) );
        $name       = trim( $first_name . ' ' . $last_name );
        $email      = sanitize_email( (string) ( $customer['email'] ?? $billing['email'] ?? '' ) );
        $company    = sanitize_text_field( (string) ( $billing['company'] ?? '' ) );
        $phone      = sanitize_text_field( (string) ( $billing['phone'] ?? '' ) );

        $search = implode(
            ' ',
            array(
                (string) ( $customer['id'] ?? '' ),
                $name,
                (string) ( $customer['username'] ?? '' ),
                $email,
                $company,
                $phone,
                (string) ( $billing['address_1'] ?? '' ),
                (string) ( $billing['postcode'] ?? '' ),
                (string) ( $billing['city'] ?? '' ),
                (string) ( $shipping['company'] ?? '' ),
            )
        );

        return array(
            'archive_key'       => sanitize_key( $archive_key ),
            'origin'            => sanitize_key( (string) ( $customer['_cvloa_origin'] ?? '' ) ),
            'source_url'        => esc_url_raw( (string) ( $customer['_cvloa_source_url'] ?? '' ) ),
            'id'                => absint( $customer['id'] ?? 0 ),
            'name'              => $name,
            'first_name'        => $first_name,
            'last_name'         => $last_name,
            'username'          => sanitize_text_field( (string) ( $customer['username'] ?? '' ) ),
            'email'             => $email,
            'company'           => $company,
            'phone'             => $phone,
            'role'              => sanitize_key( (string) ( $customer['role'] ?? '' ) ),
            'date_created'      => sanitize_text_field( (string) ( $customer['date_created'] ?? '' ) ),
            'date_modified'     => sanitize_text_field( (string) ( $customer['date_modified'] ?? '' ) ),
            'orders_count'      => absint( $customer['orders_count'] ?? 0 ),
            'total_spent'       => sanitize_text_field( (string) ( $customer['total_spent'] ?? '' ) ),
            'billing_country'   => sanitize_text_field( (string) ( $billing['country'] ?? '' ) ),
            'billing_postcode'  => sanitize_text_field( (string) ( $billing['postcode'] ?? '' ) ),
            'billing_city'      => sanitize_text_field( (string) ( $billing['city'] ?? '' ) ),
            'offset'            => $offset,
            'length'            => $length,
            'archived_at'       => gmdate( 'c' ),
            'search'            => strtolower( remove_accents( wp_strip_all_tags( $search ) ) ),
        );
    }

    public static function read_customer( $customer_key ): ?array {
        $index = self::load_index();
        $key   = sanitize_key( (string) $customer_key );

        if ( empty( $index['customers'][ $key ] ) ) {
            return null;
        }

        $entry  = (array) $index['customers'][ $key ];
        $offset = absint( $entry['offset'] ?? 0 );
        $length = absint( $entry['length'] ?? 0 );

        if ( ! $length ) {
            return null;
        }

        $handle = @fopen( self::data_path(), 'rb' );
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

    public static function stats(): array {
        $index     = self::load_index();
        $customers = (array) ( $index['customers'] ?? array() );

        return array(
            'count'        => count( $customers ),
            'updated_at'   => (string) ( $index['updated_at'] ?? '' ),
            'data_bytes'   => file_exists( self::data_path() ) ? (int) filesize( self::data_path() ) : 0,
            'index_bytes'  => file_exists( self::index_path() ) ? (int) filesize( self::index_path() ) : 0,
            'storage_path' => CVLOA_Archive::base_dir(),
            'error'        => (string) ( $index['_error'] ?? '' ),
        );
    }
}
