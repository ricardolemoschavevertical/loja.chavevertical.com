<?php
defined( 'ABSPATH' ) || exit;

final class CVOS_Settings {
    public const OPTION = 'cvos_settings';
    public const SECRET_OPTION = 'cvos_secret';
    public const CA_PEM_OPTION = 'cvos_ca_pem';

    public static function defaults(): array {
        return array(
            'endpoint'                    => '',
            'auth_type'                   => 'basic',
            'username'                    => '',
            'index_name'                  => 'wordpress-chavevertical-products',
            'ca_file'                     => '',
            'verify_ssl'                  => 'yes',
            'allow_insecure_http'         => 'no',
            'timeout'                     => 12,
            'enabled'                     => 'no',
            'replace_native_search'       => 'yes',
            'enable_instant_search'       => 'yes',
            'enable_astro_api'            => 'yes',
            'public_base_url'             => 'https://chavevertical.com',
            'min_chars'                   => 2,
            'suggestions_limit'           => 10,
            'results_per_page'            => 24,
            'exclude_out_of_stock'        => 'no',
            'fuzzy_mode'                  => 'normal',
            'search_in_description'       => 'yes',
            'search_in_short_description' => 'yes',
            'search_in_sku'               => 'yes',
            'search_in_attributes'        => 'yes',
            'search_in_categories'        => 'yes',
            'search_in_tags'              => 'yes',
            'search_in_brands'            => 'yes',
            'search_in_custom_fields'     => 'yes',
            'custom_fields'               => '',
            'weight_name'                 => 14.0,
            'weight_sku'                  => 16.0,
            'weight_variation_sku'        => 12.0,
            'weight_brands'               => 7.0,
            'weight_categories'           => 5.0,
            'weight_tags'                 => 3.0,
            'weight_attributes'           => 4.0,
            'weight_short_description'    => 2.0,
            'weight_description'          => 1.0,
            'weight_custom_fields'        => 3.0,
            'synonyms'                    => '',
            'filter_mode'                 => 'exclude',
            'filter_product_ids'          => '',
            'filter_category_ids'         => '',
            'filter_tag_ids'              => '',
            'filter_brand_ids'            => '',
            'analytics_enabled'           => 'yes',
            'analytics_retention_days'    => 90,
            'schedule_enabled'            => 'yes',
            'schedule_interval'           => 'weekly',
        );
    }

    public function all(): array {
        return wp_parse_args( get_option( self::OPTION, array() ), self::defaults() );
    }

    public function get( string $key, $default = null ) {
        $all = $this->all();
        return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
    }

    public function is_yes( string $key ): bool {
        return 'yes' === $this->get( $key, 'no' );
    }

    public function endpoint(): string {
        if ( defined( 'CVOS_ENDPOINT' ) && CVOS_ENDPOINT ) {
            return untrailingslashit( (string) CVOS_ENDPOINT );
        }
        return untrailingslashit( (string) $this->get( 'endpoint', '' ) );
    }

    public function username(): string {
        if ( defined( 'CVOS_USERNAME' ) && CVOS_USERNAME ) {
            return (string) CVOS_USERNAME;
        }
        return (string) $this->get( 'username', '' );
    }

    public function secret(): string {
        if ( defined( 'CVOS_PASSWORD' ) && CVOS_PASSWORD ) {
            return (string) CVOS_PASSWORD;
        }
        if ( defined( 'CVOS_BEARER_TOKEN' ) && CVOS_BEARER_TOKEN ) {
            return (string) CVOS_BEARER_TOKEN;
        }
        return (string) get_option( self::SECRET_OPTION, '' );
    }

    public function auth_type(): string {
        if ( defined( 'CVOS_BEARER_TOKEN' ) && CVOS_BEARER_TOKEN ) {
            return 'bearer';
        }
        $type = sanitize_key( (string) $this->get( 'auth_type', 'basic' ) );
        return in_array( $type, array( 'none', 'basic', 'bearer' ), true ) ? $type : 'basic';
    }

    public function index_name(): string {
        $name = strtolower( (string) $this->get( 'index_name', 'wordpress-chavevertical-products' ) );
        $name = preg_replace( '/[^a-z0-9_-]+/', '-', $name );
        return trim( $name, '-_' ) ?: 'wordpress-chavevertical-products';
    }

    public function ca_file(): string {
        if ( defined( 'CVOS_CA_FILE' ) && CVOS_CA_FILE ) {
            return wp_normalize_path( (string) CVOS_CA_FILE );
        }

        $path = trim( (string) $this->get( 'ca_file', '' ) );
        if ( $path ) {
            return wp_normalize_path( $path );
        }

        $bundled = defined( 'CVOS_PATH' ) ? CVOS_PATH . 'certs/opensearch-ca.pem' : '';
        return $bundled && is_readable( $bundled ) ? wp_normalize_path( $bundled ) : '';
    }

    public function using_bundled_ca(): bool {
        $configured = trim( (string) $this->get( 'ca_file', '' ) );
        if ( defined( 'CVOS_CA_FILE' ) && CVOS_CA_FILE ) {
            return false;
        }
        return '' === $configured && is_readable( CVOS_PATH . 'certs/opensearch-ca.pem' );
    }

    public function ca_pem(): string {
        if ( defined( 'CVOS_CA_PEM' ) && CVOS_CA_PEM ) {
            return trim( (string) CVOS_CA_PEM );
        }

        return trim( (string) get_option( self::CA_PEM_OPTION, '' ) );
    }

    public function has_ca_pem(): bool {
        return '' !== $this->ca_pem();
    }

    public function normalize_ca_pem( string $pem ) {
        $pem = trim( str_replace( "\r", '', $pem ) );

        if ( '' === $pem ) {
            return new WP_Error( 'cvos_empty_ca', 'O certificado CA está vazio.' );
        }

        if ( preg_match( '/-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----/i', $pem ) ) {
            return new WP_Error( 'cvos_private_key_rejected', 'Não cole chaves privadas. Este campo aceita apenas certificados públicos PEM.' );
        }

        if ( ! preg_match_all( '/-----BEGIN CERTIFICATE-----\s+[A-Za-z0-9+\/=\s]+-----END CERTIFICATE-----/s', $pem, $matches ) || empty( $matches[0] ) ) {
            return new WP_Error( 'cvos_invalid_ca', 'Não foi encontrado um certificado PEM válido.' );
        }

        $normalized = array();

        foreach ( $matches[0] as $certificate ) {
            $certificate = trim( preg_replace( "/\n{3,}/", "\n\n", str_replace( "\r", '', $certificate ) ) );

            if ( function_exists( 'openssl_x509_read' ) ) {
                $resource = openssl_x509_read( $certificate );
                if ( false === $resource ) {
                    return new WP_Error( 'cvos_invalid_ca', 'O conteúdo PEM não é um certificado X.509 válido.' );
                }
            } else {
                $body = preg_replace(
                    '/-----BEGIN CERTIFICATE-----|-----END CERTIFICATE-----|\s+/',
                    '',
                    $certificate
                );
                if ( false === base64_decode( $body, true ) ) {
                    return new WP_Error( 'cvos_invalid_ca', 'O conteúdo PEM não pôde ser validado.' );
                }
            }

            $normalized[] = $certificate;
        }

        return implode( "\n", $normalized ) . "\n";
    }

    public function configured(): bool {
        return '' !== $this->endpoint() && '' !== $this->index_name();
    }

    public function sanitize( array $input ): array {
        $defaults = self::defaults();
        $out      = $defaults;

        $endpoint = isset( $input['endpoint'] ) ? trim( (string) $input['endpoint'] ) : '';
        if ( $endpoint ) {
            $parts  = wp_parse_url( $endpoint );
            $scheme = strtolower( (string) ( $parts['scheme'] ?? '' ) );
            if ( ! in_array( $scheme, array( 'http', 'https' ), true ) || empty( $parts['host'] ) || ! empty( $parts['user'] ) || ! empty( $parts['pass'] ) ) {
                $endpoint = '';
            } elseif ( 'http' === $scheme && 'yes' !== ( $input['allow_insecure_http'] ?? 'no' ) ) {
                $endpoint = '';
            }
        }

        $out['endpoint']            = untrailingslashit( esc_url_raw( $endpoint ) );
        $out['auth_type']           = in_array( $input['auth_type'] ?? '', array( 'none', 'basic', 'bearer' ), true ) ? $input['auth_type'] : 'basic';
        $out['username']            = sanitize_text_field( $input['username'] ?? '' );
        $out['index_name']          = sanitize_key( str_replace( '.', '-', $input['index_name'] ?? 'wordpress-chavevertical-products' ) ) ?: 'wordpress-chavevertical-products';
        $out['ca_file']             = sanitize_text_field( $input['ca_file'] ?? '' );
        $out['timeout']             = min( 60, max( 2, absint( $input['timeout'] ?? 12 ) ) );
        $out['min_chars']           = min( 10, max( 1, absint( $input['min_chars'] ?? 2 ) ) );
        $out['suggestions_limit']   = min( 30, max( 3, absint( $input['suggestions_limit'] ?? 10 ) ) );
        $out['results_per_page']    = min( 100, max( 6, absint( $input['results_per_page'] ?? 24 ) ) );
        $out['public_base_url']     = untrailingslashit( esc_url_raw( $input['public_base_url'] ?? 'https://chavevertical.com' ) );
        $out['fuzzy_mode']          = in_array( $input['fuzzy_mode'] ?? '', array( 'off', 'normal', 'aggressive' ), true ) ? $input['fuzzy_mode'] : 'normal';
        $out['custom_fields']       = sanitize_text_field( $input['custom_fields'] ?? '' );
        $out['synonyms']            = sanitize_textarea_field( $input['synonyms'] ?? '' );
        $out['filter_mode']         = in_array( $input['filter_mode'] ?? '', array( 'include', 'exclude' ), true ) ? $input['filter_mode'] : 'exclude';
        $out['filter_product_ids']  = $this->sanitize_id_list( $input['filter_product_ids'] ?? '' );
        $out['filter_category_ids'] = $this->sanitize_id_list( $input['filter_category_ids'] ?? '' );
        $out['filter_tag_ids']      = $this->sanitize_id_list( $input['filter_tag_ids'] ?? '' );
        $out['filter_brand_ids']    = $this->sanitize_id_list( $input['filter_brand_ids'] ?? '' );
        $out['analytics_retention_days'] = min( 365, max( 7, absint( $input['analytics_retention_days'] ?? 90 ) ) );
        $out['schedule_interval']   = in_array( $input['schedule_interval'] ?? '', array( 'daily', 'weekly' ), true ) ? $input['schedule_interval'] : 'weekly';

        foreach ( array(
            'verify_ssl','allow_insecure_http','enabled','replace_native_search','enable_instant_search','enable_astro_api',
            'exclude_out_of_stock','search_in_description','search_in_short_description','search_in_sku','search_in_attributes',
            'search_in_categories','search_in_tags','search_in_brands','search_in_custom_fields','analytics_enabled','schedule_enabled'
        ) as $key ) {
            $out[ $key ] = ! empty( $input[ $key ] ) ? 'yes' : 'no';
        }

        foreach ( array(
            'weight_name','weight_sku','weight_variation_sku','weight_brands','weight_categories','weight_tags',
            'weight_attributes','weight_short_description','weight_description','weight_custom_fields'
        ) as $key ) {
            $out[ $key ] = max( 0.1, min( 50.0, (float) ( $input[ $key ] ?? $defaults[ $key ] ) ) );
        }

        return $out;
    }

    private function sanitize_id_list( $value ): string {
        $ids = array_unique( array_filter( array_map( 'absint', preg_split( '/[\s,;]+/', (string) $value ) ) ) );
        return implode( ',', $ids );
    }

    public function id_list( string $key ): array {
        return array_values( array_filter( array_map( 'absint', explode( ',', (string) $this->get( $key, '' ) ) ) ) );
    }

    public function synonyms(): array {
        $groups = array();
        foreach ( preg_split( '/\r\n|\r|\n/', (string) $this->get( 'synonyms', '' ) ) as $line ) {
            $terms = array_values( array_unique( array_filter( array_map( 'trim', explode( ',', $line ) ) ) ) );
            if ( count( $terms ) > 1 ) {
                $groups[] = $terms;
            }
        }
        return $groups;
    }
}
