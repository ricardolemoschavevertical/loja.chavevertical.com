<?php
defined( 'ABSPATH' ) || exit;

final class CVLOA_Archive {
    private const HEADER = "<?php exit; ?>\n";

    public static function root_dir(): string {
        return dirname( self::base_dir() );
    }

    public static function base_dir(): string {
        if ( class_exists( 'CV_Core_Order_History' ) ) {
            return CV_Core_Order_History::archive_root_dir();
        }

        if ( defined( 'CV_ORDER_ARCHIVE_DIR' ) && CV_ORDER_ARCHIVE_DIR ) {
            return untrailingslashit( (string) CV_ORDER_ARCHIVE_DIR );
        }

        return trailingslashit( dirname( dirname( ABSPATH ) ) ) . 'private-data/chavevertical/legacy-orders';
    }

    private static function legacy_base_dir(): string {
        return trailingslashit( WP_CONTENT_DIR ) . 'cv-private-data/legacy-orders';
    }

    private static function migrate_legacy_storage(): void {
        $legacy = self::legacy_base_dir();
        $target = self::base_dir();

        if ( $legacy === $target || ! is_dir( $legacy ) ) {
            return;
        }

        if ( ! is_dir( $target ) ) {
            @wp_mkdir_p( $target );
        }

        foreach ( array( 'orders.ndjson.php', 'orders-index.json.php' ) as $file ) {
            $from = trailingslashit( $legacy ) . $file;
            $to   = trailingslashit( $target ) . $file;

            if ( is_file( $from ) && ! is_file( $to ) ) {
                @copy( $from, $to );
                @chmod( $to, 0660 );
            }
        }
    }

    public static function data_path(): string {
        return trailingslashit( self::base_dir() ) . 'orders.ndjson.php';
    }

    public static function index_path(): string {
        return trailingslashit( self::base_dir() ) . 'orders-index.json.php';
    }

    public static function ensure_storage() {
        self::migrate_legacy_storage();

        $root = self::root_dir();
        $dir  = self::base_dir();

        $normalized_dir    = trailingslashit( wp_normalize_path( $dir ) );
        $normalized_public = trailingslashit( wp_normalize_path( ABSPATH ) );

        if ( str_starts_with( $normalized_dir, $normalized_public ) ) {
            return new WP_Error(
                'cvloa_storage_inside_document_root',
                'Por segurança, o arquivo de encomendas não pode ficar dentro do document root público do WordPress.'
            );
        }

        if ( ! is_dir( $root ) && ! wp_mkdir_p( $root ) ) {
            return new WP_Error( 'cvloa_storage_root_create_failed', 'Não foi possível criar a pasta privada do arquivo.' );
        }

        // O deploy pode criar estas pastas através de WP-CLI com umask restritivo.
        // Repor permissões de grupo para que o PHP-FPM da própria loja consiga
        // ler e escrever sem tornar o arquivo público no sistema.
        @chmod( $root, 0770 );

        if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
            return new WP_Error( 'cvloa_storage_create_failed', 'Não foi possível criar a pasta privada das encomendas.' );
        }

        @chmod( $dir, 0770 );

        if ( ! is_readable( $dir ) || ! is_writable( $dir ) ) {
            return new WP_Error(
                'cvloa_storage_not_accessible',
                'A pasta privada do arquivo não tem permissões de leitura/escrita para o PHP da loja.'
            );
        }

        $protect_files = array(
            trailingslashit( $root ) . 'index.php' => "<?php\nexit;\n",
            trailingslashit( $root ) . '.htaccess' => "Require all denied\nDeny from all\n",
            trailingslashit( $dir ) . 'index.php'  => "<?php\nexit;\n",
            trailingslashit( $dir ) . '.htaccess'  => "Require all denied\nDeny from all\n",
        );

        foreach ( $protect_files as $path => $content ) {
            if ( ! file_exists( $path ) ) {
                if ( false === @file_put_contents( $path, $content, LOCK_EX ) ) {
                    return new WP_Error(
                        'cvloa_protection_write_failed',
                        'Não foi possível criar os ficheiros de proteção do arquivo.'
                    );
                }
            }
            @chmod( $path, 0660 );
        }

        if ( ! file_exists( self::data_path() ) ) {
            if ( false === @file_put_contents( self::data_path(), self::HEADER, LOCK_EX ) ) {
                return new WP_Error( 'cvloa_data_create_failed', 'Não foi possível criar o ficheiro local das encomendas.' );
            }
        }
        @chmod( self::data_path(), 0660 );

        if ( ! file_exists( self::index_path() ) ) {
            $created = self::write_index(
                array(
                    'version'    => 1,
                    'updated_at' => gmdate( 'c' ),
                    'orders'     => array(),
                )
            );

            if ( is_wp_error( $created ) ) {
                return $created;
            }
        }

        @chmod( self::index_path(), 0660 );

        if ( ! is_readable( self::index_path() ) ) {
            return new WP_Error(
                'cvloa_index_not_readable',
                'O índice local existe, mas o PHP da loja não tem permissão para o ler.'
            );
        }

        if ( ! is_writable( self::data_path() ) || ! is_writable( self::index_path() ) ) {
            return new WP_Error(
                'cvloa_archive_not_writable',
                'O arquivo local existe, mas o PHP da loja não tem permissão para o atualizar.'
            );
        }

        return true;
    }

    public static function load_index(): array {
        $ready = self::ensure_storage();
        if ( is_wp_error( $ready ) ) {
            return array(
                'version'    => 1,
                'updated_at' => '',
                'orders'     => array(),
                '_error'     => $ready->get_error_message(),
            );
        }

        $raw = @file_get_contents( self::index_path() );

        if ( false === $raw ) {
            return array(
                'version'    => 1,
                'updated_at' => '',
                'orders'     => array(),
                '_error'     => 'Não foi possível ler o índice local.',
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
                'orders'     => array(),
                '_error'     => 'O índice local está inválido.',
            );
        }

        $decoded['orders'] = isset( $decoded['orders'] ) && is_array( $decoded['orders'] )
            ? $decoded['orders']
            : array();

        return $decoded;
    }

    private static function write_index( array $index ) {
        $dir = self::base_dir();
        if ( ! is_dir( $dir ) ) {
            return new WP_Error( 'cvloa_index_dir_missing', 'A pasta privada do arquivo não existe.' );
        }

        $index['version']    = 1;
        $index['updated_at'] = gmdate( 'c' );

        $json = wp_json_encode(
            $index,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );

        if ( false === $json ) {
            return new WP_Error( 'cvloa_index_encode_failed', 'Não foi possível gerar o índice das encomendas.' );
        }

        $tmp = trailingslashit( $dir ) . '.orders-index-' . wp_generate_password( 12, false, false ) . '.tmp';

        if ( false === @file_put_contents( $tmp, self::HEADER . $json, LOCK_EX ) ) {
            return new WP_Error( 'cvloa_index_write_failed', 'Não foi possível escrever o índice temporário.' );
        }

        @chmod( $tmp, 0660 );

        if ( ! @rename( $tmp, self::index_path() ) ) {
            @unlink( $tmp );
            return new WP_Error( 'cvloa_index_replace_failed', 'Não foi possível substituir o índice local.' );
        }

        @chmod( self::index_path(), 0660 );
        return true;
    }

    public static function archive_batch( array $orders, bool $replace_existing = false ) {
        $ready = self::ensure_storage();
        if ( is_wp_error( $ready ) ) {
            return $ready;
        }

        $index = self::load_index();
        if ( ! empty( $index['_error'] ) ) {
            return new WP_Error( 'cvloa_index_error', (string) $index['_error'] );
        }

        $handle = @fopen( self::data_path(), 'c+b' );
        if ( false === $handle ) {
            return new WP_Error( 'cvloa_data_open_failed', 'Não foi possível abrir o ficheiro local das encomendas.' );
        }

        if ( ! flock( $handle, LOCK_EX ) ) {
            fclose( $handle );
            return new WP_Error( 'cvloa_data_lock_failed', 'Não foi possível bloquear o arquivo para escrita.' );
        }

        $result = array(
            'archived' => 0,
            'updated'  => 0,
            'ignored'  => 0,
            'errors'   => array(),
        );

        try {
            fseek( $handle, 0, SEEK_END );

            foreach ( $orders as $order ) {
                $order = (array) $order;
                $id    = absint( $order['id'] ?? 0 );

                if ( ! $id ) {
                    $result['errors'][] = 'Encomenda sem ID na origem.';
                    continue;
                }

                $key = sanitize_key( (string) ( $order['_cvloa_archive_key'] ?? '' ) );
                if ( '' === $key ) {
                    $key = (string) $id;
                }

                $order['_cvloa_archive_key'] = $key;
                $existing = ! empty( $index['orders'][ $key ] );

                // Compatibilidade com importações feitas pela versão inicial,
                // que indexava a origem remota apenas pelo ID numérico.
                if (
                    ! $existing
                    && 'remote' === sanitize_key( (string) ( $order['_cvloa_origin'] ?? '' ) )
                    && ! empty( $index['orders'][ (string) $id ] )
                ) {
                    $key = (string) $id;
                    $order['_cvloa_archive_key'] = $key;
                    $existing = true;
                }

                if ( $existing && ! $replace_existing ) {
                    $result['ignored']++;
                    continue;
                }

                $json = wp_json_encode(
                    $order,
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

                $index['orders'][ $key ] = self::summarize_order( $order, $key, (int) $offset, (int) $bytes );

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

    private static function summarize_order( array $order, string $archive_key, int $offset, int $length ): array {
        $billing = (array) ( $order['billing'] ?? array() );
        $items   = (array) ( $order['line_items'] ?? array() );

        $item_search = array();
        $item_count  = 0;

        foreach ( $items as $item ) {
            $item = (array) $item;
            $item_count += max( 0, (int) ( $item['quantity'] ?? 0 ) );
            $item_search[] = trim(
                (string) ( $item['name'] ?? '' ) . ' ' . (string) ( $item['sku'] ?? '' )
            );
        }

        $shipping_methods = array();
        foreach ( (array) ( $order['shipping_lines'] ?? array() ) as $shipping_line ) {
            $shipping_line = (array) $shipping_line;
            if ( ! empty( $shipping_line['method_title'] ) ) {
                $shipping_methods[] = sanitize_text_field( (string) $shipping_line['method_title'] );
            }
        }

        $search = implode(
            ' ',
            array(
                (string) ( $order['id'] ?? '' ),
                (string) ( $order['number'] ?? '' ),
                (string) ( $billing['first_name'] ?? '' ),
                (string) ( $billing['last_name'] ?? '' ),
                (string) ( $billing['company'] ?? '' ),
                (string) ( $billing['email'] ?? '' ),
                (string) ( $billing['phone'] ?? '' ),
                implode( ' ', $item_search ),
            )
        );

        return array(
            'archive_key'          => sanitize_key( $archive_key ),
            'origin'               => sanitize_key( (string) ( $order['_cvloa_origin'] ?? '' ) ),
            'source_url'           => esc_url_raw( (string) ( $order['_cvloa_source_url'] ?? '' ) ),
            'customer_id'          => absint( $order['customer_id'] ?? 0 ),
            'id'                   => absint( $order['id'] ?? 0 ),
            'number'               => sanitize_text_field( (string) ( $order['number'] ?? '' ) ),
            'status'               => sanitize_key( (string) ( $order['status'] ?? '' ) ),
            'date_created'         => sanitize_text_field( (string) ( $order['date_created'] ?? '' ) ),
            'date_created_gmt'     => sanitize_text_field( (string) ( $order['date_created_gmt'] ?? '' ) ),
            'date_modified'        => sanitize_text_field( (string) ( $order['date_modified'] ?? '' ) ),
            'currency'             => sanitize_text_field( (string) ( $order['currency'] ?? '' ) ),
            'total'                => sanitize_text_field( (string) ( $order['total'] ?? '' ) ),
            'billing_name'         => sanitize_text_field( trim( (string) ( $billing['first_name'] ?? '' ) . ' ' . (string) ( $billing['last_name'] ?? '' ) ) ),
            'billing_company'      => sanitize_text_field( (string) ( $billing['company'] ?? '' ) ),
            'billing_email'        => sanitize_email( (string) ( $billing['email'] ?? '' ) ),
            'billing_phone'        => sanitize_text_field( (string) ( $billing['phone'] ?? '' ) ),
            'payment_method_title' => sanitize_text_field( (string) ( $order['payment_method_title'] ?? '' ) ),
            'shipping_methods'     => array_values( array_unique( $shipping_methods ) ),
            'item_count'           => $item_count,
            'offset'               => $offset,
            'length'               => $length,
            'archived_at'          => gmdate( 'c' ),
            'search'               => strtolower( remove_accents( wp_strip_all_tags( $search ) ) ),
        );
    }

    public static function find_by_billing_email( string $email ): array {
        $email = strtolower( sanitize_email( $email ) );
        if ( '' === $email ) {
            return array();
        }

        $index = self::load_index();
        if ( ! empty( $index['_error'] ) ) {
            return array();
        }

        $matches = array_values(
            array_filter(
                (array) ( $index['orders'] ?? array() ),
                static function ( array $row ) use ( $email ): bool {
                    return $email === strtolower(
                        sanitize_email( (string) ( $row['billing_email'] ?? '' ) )
                    );
                }
            )
        );

        usort(
            $matches,
            static function ( array $a, array $b ): int {
                $ad = strtotime( (string) ( $a['date_created'] ?? '' ) ) ?: 0;
                $bd = strtotime( (string) ( $b['date_created'] ?? '' ) ) ?: 0;
                return $bd <=> $ad;
            }
        );

        return $matches;
    }

    public static function email_order_counts(): array {
        $index = self::load_index();
        if ( ! empty( $index['_error'] ) ) {
            return array();
        }

        $counts = array();

        foreach ( (array) ( $index['orders'] ?? array() ) as $row ) {
            $email = strtolower(
                sanitize_email( (string) ( $row['billing_email'] ?? '' ) )
            );

            if ( '' !== $email ) {
                $counts[ $email ] = absint( $counts[ $email ] ?? 0 ) + 1;
            }
        }

        return $counts;
    }

    public static function read_order( $order_key ): ?array {
        $index = self::load_index();
        $key   = sanitize_key( (string) $order_key );

        if ( '' === $key && is_numeric( $order_key ) ) {
            $key = (string) absint( $order_key );
        }

        if ( empty( $index['orders'][ $key ] ) ) {
            // Compatibilidade com arquivos da primeira versão, indexados só pelo ID.
            $legacy_key = is_numeric( $order_key ) ? (string) absint( $order_key ) : '';
            if ( '' === $legacy_key || empty( $index['orders'][ $legacy_key ] ) ) {
                return null;
            }
            $key = $legacy_key;
        }

        $entry  = (array) $index['orders'][ $key ];
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

    public static function update_statuses( array $order_keys, string $new_status, int $user_id = 0 ) {
        $ready = self::ensure_storage();
        if ( is_wp_error( $ready ) ) {
            return $ready;
        }

        $new_status = sanitize_key( preg_replace( '/^wc-/', '', $new_status ) );
        if ( '' === $new_status ) {
            return new WP_Error( 'cvloa_invalid_status', 'Estado inválido.' );
        }

        $keys = array_values(
            array_unique(
                array_filter(
                    array_map(
                        static fn( $key ): string => sanitize_key( (string) $key ),
                        $order_keys
                    )
                )
            )
        );

        if ( ! $keys ) {
            return new WP_Error( 'cvloa_no_orders_selected', 'Não foram selecionadas encomendas.' );
        }

        $index = self::load_index();
        if ( ! empty( $index['_error'] ) ) {
            return new WP_Error( 'cvloa_index_error', (string) $index['_error'] );
        }

        $handle = @fopen( self::data_path(), 'rb' );
        if ( false === $handle ) {
            return new WP_Error( 'cvloa_data_open_failed', 'Não foi possível abrir o ficheiro local das encomendas.' );
        }

        $orders  = array();
        $ignored = 0;
        $errors  = array();

        try {
            foreach ( $keys as $key ) {
                $lookup_key = $key;

                if ( empty( $index['orders'][ $lookup_key ] ) && is_numeric( $lookup_key ) ) {
                    $legacy_key = (string) absint( $lookup_key );
                    if ( ! empty( $index['orders'][ $legacy_key ] ) ) {
                        $lookup_key = $legacy_key;
                    }
                }

                if ( empty( $index['orders'][ $lookup_key ] ) ) {
                    $errors[] = $key . ': encomenda não encontrada no índice.';
                    continue;
                }

                $entry  = (array) $index['orders'][ $lookup_key ];
                $offset = absint( $entry['offset'] ?? 0 );
                $length = absint( $entry['length'] ?? 0 );

                if ( ! $length || 0 !== fseek( $handle, $offset ) ) {
                    $errors[] = $key . ': localização inválida no arquivo.';
                    continue;
                }

                $raw = fread( $handle, $length );
                if ( false === $raw || '' === $raw ) {
                    $errors[] = $key . ': não foi possível ler a encomenda.';
                    continue;
                }

                $order = json_decode( trim( $raw ), true );
                if ( ! is_array( $order ) ) {
                    $errors[] = $key . ': registo JSON inválido.';
                    continue;
                }

                $previous_status = sanitize_key(
                    preg_replace( '/^wc-/', '', (string) ( $order['status'] ?? '' ) )
                );

                if ( $previous_status === $new_status ) {
                    $ignored++;
                    continue;
                }

                $history   = isset( $order['_cvloa_archive_status_history'] ) && is_array( $order['_cvloa_archive_status_history'] )
                    ? $order['_cvloa_archive_status_history']
                    : array();
                $history[] = array(
                    'from'       => $previous_status,
                    'to'         => $new_status,
                    'changed_at' => gmdate( 'c' ),
                    'user_id'    => max( 0, $user_id ),
                );

                $order['status']                            = $new_status;
                $order['_cvloa_archive_status_history']     = array_slice( $history, -50 );
                $order['_cvloa_archive_status_changed_at']  = gmdate( 'c' );
                $order['_cvloa_archive_status_changed_by']  = max( 0, $user_id );

                $orders[] = $order;
            }
        } finally {
            fclose( $handle );
        }

        if ( ! $orders ) {
            return array(
                'updated' => 0,
                'ignored' => $ignored,
                'errors'  => $errors,
            );
        }

        $saved = self::archive_batch( $orders, true );
        if ( is_wp_error( $saved ) ) {
            return $saved;
        }

        return array(
            'updated' => absint( $saved['updated'] ?? 0 ) + absint( $saved['archived'] ?? 0 ),
            'ignored' => $ignored + absint( $saved['ignored'] ?? 0 ),
            'errors'  => array_merge( $errors, (array) ( $saved['errors'] ?? array() ) ),
        );
    }

    public static function stats(): array {
        $index  = self::load_index();
        $orders = (array) ( $index['orders'] ?? array() );

        return array(
            'count'        => count( $orders ),
            'updated_at'   => (string) ( $index['updated_at'] ?? '' ),
            'data_bytes'   => file_exists( self::data_path() ) ? (int) filesize( self::data_path() ) : 0,
            'index_bytes'  => file_exists( self::index_path() ) ? (int) filesize( self::index_path() ) : 0,
            'storage_path' => self::base_dir(),
            'error'        => (string) ( $index['_error'] ?? '' ),
        );
    }
}
