<?php
defined( 'ABSPATH' ) || exit;

final class CVR2_REST_Client {
    public static function settings(): array {
        $defaults = array(
            'source_url'  => 'https://chavevertical.com',
            'r2_base_url' => 'https://imagens.chavevertical.com',
            'batch_size'  => 10,
            'source_ck'   => '',
            'source_cs'   => '',
        );

        return wp_parse_args( (array) get_option( CVR2_OPTION, array() ), $defaults );
    }

    public static function source_url(): string {
        if ( defined( 'CVR2_SOURCE_URL' ) && CVR2_SOURCE_URL ) {
            return untrailingslashit( esc_url_raw( (string) CVR2_SOURCE_URL ) );
        }

        return untrailingslashit( esc_url_raw( (string) self::settings()['source_url'] ) );
    }

    public static function r2_base_url(): string {
        if ( defined( 'CVR2_R2_BASE_URL' ) && CVR2_R2_BASE_URL ) {
            return untrailingslashit( esc_url_raw( (string) CVR2_R2_BASE_URL ) );
        }

        return untrailingslashit( esc_url_raw( (string) self::settings()['r2_base_url'] ) );
    }

    public static function consumer_key(): string {
        if ( defined( 'CVR2_SOURCE_CONSUMER_KEY' ) && CVR2_SOURCE_CONSUMER_KEY ) {
            return (string) CVR2_SOURCE_CONSUMER_KEY;
        }

        return self::decrypt_secret( (string) self::settings()['source_ck'] );
    }

    public static function consumer_secret(): string {
        if ( defined( 'CVR2_SOURCE_CONSUMER_SECRET' ) && CVR2_SOURCE_CONSUMER_SECRET ) {
            return (string) CVR2_SOURCE_CONSUMER_SECRET;
        }

        return self::decrypt_secret( (string) self::settings()['source_cs'] );
    }

    public static function encrypt_secret( string $plain ): string {
        if ( '' === $plain ) {
            return '';
        }

        if ( ! function_exists( 'openssl_encrypt' ) ) {
            return 'plain:' . base64_encode( $plain );
        }

        $key = hash( 'sha256', wp_salt( 'auth' ), true );
        $iv  = random_bytes( 16 );
        $out = openssl_encrypt( $plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );

        if ( false === $out ) {
            return 'plain:' . base64_encode( $plain );
        }

        return 'enc:' . base64_encode( $iv . $out );
    }

    public static function decrypt_secret( string $stored ): string {
        if ( '' === $stored ) {
            return '';
        }

        if ( str_starts_with( $stored, 'plain:' ) ) {
            return (string) base64_decode( substr( $stored, 6 ), true );
        }

        if ( ! str_starts_with( $stored, 'enc:' ) || ! function_exists( 'openssl_decrypt' ) ) {
            return '';
        }

        $raw = base64_decode( substr( $stored, 4 ), true );

        if ( false === $raw || strlen( $raw ) <= 16 ) {
            return '';
        }

        $iv     = substr( $raw, 0, 16 );
        $cipher = substr( $raw, 16 );
        $key    = hash( 'sha256', wp_salt( 'auth' ), true );
        $plain  = openssl_decrypt( $cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );

        return false === $plain ? '' : $plain;
    }

    public static function request( string $path, array $query = array(), int $timeout = 30 ) {
        $ck = self::consumer_key();
        $cs = self::consumer_secret();

        if ( '' === $ck || '' === $cs ) {
            return new WP_Error( 'cvr2_missing_credentials', 'Faltam as credenciais REST WooCommerce do site original.' );
        }

        $url = self::source_url() . '/wp-json/wc/v3/' . ltrim( $path, '/' );

        if ( $query ) {
            $url = add_query_arg( $query, $url );
        }

        $response = wp_safe_remote_get(
            $url,
            array(
                'timeout'     => max( 5, $timeout ),
                'redirection' => 2,
                'headers'     => array(
                    'Accept'        => 'application/json',
                    'Authorization' => 'Basic ' . base64_encode( $ck . ':' . $cs ),
                    'User-Agent'    => 'CV-R2-REST-Importer/' . CVR2_VERSION,
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code < 200 || $code >= 300 ) {
            $message = is_array( $body ) && ! empty( $body['message'] )
                ? sanitize_text_field( (string) $body['message'] )
                : 'REST de origem respondeu HTTP ' . $code . '.';

            return new WP_Error(
                'cvr2_source_http_' . $code,
                $message,
                array( 'status' => $code )
            );
        }

        return array(
            'data'        => is_array( $body ) ? $body : array(),
            'total'       => (int) wp_remote_retrieve_header( $response, 'x-wp-total' ),
            'total_pages' => max( 1, (int) wp_remote_retrieve_header( $response, 'x-wp-totalpages' ) ),
            'headers'     => wp_remote_retrieve_headers( $response ),
        );
    }

    public static function all_pages( string $path, array $query = array(), int $per_page = 100 ) {
        $page = 1;
        $all  = array();

        do {
            $result = self::request(
                $path,
                array_merge(
                    $query,
                    array(
                        'per_page' => min( 100, max( 1, $per_page ) ),
                        'page'     => $page,
                    )
                ),
                45
            );

            if ( is_wp_error( $result ) ) {
                return $result;
            }

            $all = array_merge( $all, (array) $result['data'] );
            $max = max( 1, (int) $result['total_pages'] );
            $page++;
        } while ( $page <= $max );

        return $all;
    }

    public static function test_connection() {
        $result = self::request(
            'products',
            array(
                'per_page' => 1,
                'page'     => 1,
                'status'   => 'any',
            )
        );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return array(
            'ok'    => true,
            'total' => (int) $result['total'],
        );
    }
}
