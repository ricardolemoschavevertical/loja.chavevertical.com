<?php
defined( 'ABSPATH' ) || exit;

final class CVLOA_REST_Client {
    public static function settings(): array {
        return wp_parse_args(
            (array) get_option( CVLOA_OPTION, array() ),
            array(
                'source_url' => 'https://chavevertical.com',
                'reuse_cvr2' => 1,
                'source_ck'  => '',
                'source_cs'  => '',
            )
        );
    }

    public static function source_url(): string {
        if ( defined( 'CVLOA_SOURCE_URL' ) && CVLOA_SOURCE_URL ) {
            return untrailingslashit( esc_url_raw( (string) CVLOA_SOURCE_URL ) );
        }

        return untrailingslashit( esc_url_raw( (string) self::settings()['source_url'] ) );
    }

    public static function consumer_key(): string {
        if ( defined( 'CVLOA_SOURCE_CONSUMER_KEY' ) && CVLOA_SOURCE_CONSUMER_KEY ) {
            return (string) CVLOA_SOURCE_CONSUMER_KEY;
        }

        $settings = self::settings();
        $own      = self::decrypt_secret( (string) ( $settings['source_ck'] ?? '' ) );

        if ( '' !== $own ) {
            return $own;
        }

        if ( ! empty( $settings['reuse_cvr2'] ) ) {
            $cvr2 = (array) get_option( 'cvr2_settings', array() );
            return self::decrypt_secret( (string) ( $cvr2['source_ck'] ?? '' ) );
        }

        return '';
    }

    public static function consumer_secret(): string {
        if ( defined( 'CVLOA_SOURCE_CONSUMER_SECRET' ) && CVLOA_SOURCE_CONSUMER_SECRET ) {
            return (string) CVLOA_SOURCE_CONSUMER_SECRET;
        }

        $settings = self::settings();
        $own      = self::decrypt_secret( (string) ( $settings['source_cs'] ?? '' ) );

        if ( '' !== $own ) {
            return $own;
        }

        if ( ! empty( $settings['reuse_cvr2'] ) ) {
            $cvr2 = (array) get_option( 'cvr2_settings', array() );
            return self::decrypt_secret( (string) ( $cvr2['source_cs'] ?? '' ) );
        }

        return '';
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
            $decoded = base64_decode( substr( $stored, 6 ), true );
            return false === $decoded ? '' : (string) $decoded;
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

        return false === $plain ? '' : (string) $plain;
    }

    public static function request( string $path, array $query = array(), int $timeout = 45 ) {
        $ck = self::consumer_key();
        $cs = self::consumer_secret();

        if ( '' === $ck || '' === $cs ) {
            return new WP_Error(
                'cvloa_missing_credentials',
                'Faltam as credenciais REST WooCommerce da loja de origem.'
            );
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
                    'User-Agent'    => 'CV-Legacy-Orders-Archive/' . CVLOA_VERSION,
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
                'cvloa_source_http_' . $code,
                $message,
                array( 'status' => $code )
            );
        }

        return array(
            'data'        => is_array( $body ) ? $body : array(),
            'total'       => (int) wp_remote_retrieve_header( $response, 'x-wp-total' ),
            'total_pages' => max( 1, (int) wp_remote_retrieve_header( $response, 'x-wp-totalpages' ) ),
        );
    }

    public static function test_connection() {
        $result = self::request(
            'orders',
            array(
                'per_page' => 1,
                'page'     => 1,
                'orderby'  => 'id',
                'order'    => 'asc',
            ),
            30
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
