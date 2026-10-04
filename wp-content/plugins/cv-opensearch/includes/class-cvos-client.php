<?php
defined( 'ABSPATH' ) || exit;

final class CVOS_Client {
    private CVOS_Settings $settings;

    public function __construct( CVOS_Settings $settings ) {
        $this->settings = $settings;
    }

    public function health() {
        return $this->request( 'GET', '/_cluster/health', null, 8 );
    }

    public function info() {
        return $this->request( 'GET', '/', null, 8 );
    }

    public function ensure_index() {
        $index = $this->settings->index_name();
        $check = $this->request( 'GET', '/' . rawurlencode( $index ), null, 8 );
        if ( ! is_wp_error( $check ) ) {
            return true;
        }

        $data = $check->get_error_data();
        if ( 404 !== (int) ( $data['status'] ?? 0 ) ) {
            return $check;
        }

        return $this->create_index( $index );
    }

    public function recreate_index() {
        $index = $this->settings->index_name();
        $this->request( 'DELETE', '/' . rawurlencode( $index ), null, 20, array( 404 ) );
        return $this->create_index( $index );
    }

    private function create_index( string $index ) {
        $body = array(
            'settings' => array(
                'number_of_shards'   => 1,
                'number_of_replicas' => 0,
                'analysis'           => array(
                    'normalizer' => array(
                        'cv_keyword' => array(
                            'type'   => 'custom',
                            'filter' => array( 'lowercase', 'asciifolding' ),
                        ),
                    ),
                    'analyzer' => array(
                        'cv_text' => array(
                            'type'      => 'custom',
                            'tokenizer' => 'standard',
                            'filter'    => array( 'lowercase', 'asciifolding' ),
                        ),
                    ),
                ),
            ),
            'mappings' => array(
                'dynamic'    => false,
                'properties' => array(
                    'id'                    => array( 'type' => 'long' ),
                    'parent_id'             => array( 'type' => 'long' ),
                    'name'                  => array(
                        'type'     => 'text',
                        'analyzer' => 'cv_text',
                        'fields'   => array(
                            'keyword' => array( 'type' => 'keyword', 'normalizer' => 'cv_keyword' ),
                        ),
                    ),
                    'name_sort'             => array( 'type' => 'keyword', 'normalizer' => 'cv_keyword' ),
                    'slug'                  => array( 'type' => 'keyword' ),
                    'url'                   => array( 'type' => 'keyword', 'index' => false ),
                    'purchase_url'          => array( 'type' => 'keyword', 'index' => false ),
                    'sku'                   => array(
                        'type'       => 'keyword',
                        'normalizer' => 'cv_keyword',
                        'fields'     => array(
                            'text' => array( 'type' => 'text', 'analyzer' => 'cv_text' ),
                        ),
                    ),
                    'variation_skus'        => array( 'type' => 'keyword', 'normalizer' => 'cv_keyword' ),
                    'guid'                  => array( 'type' => 'keyword', 'normalizer' => 'cv_keyword' ),
                    'short_description'     => array( 'type' => 'text', 'analyzer' => 'cv_text' ),
                    'description'           => array( 'type' => 'text', 'analyzer' => 'cv_text' ),
                    'brand_names'           => array( 'type' => 'text', 'analyzer' => 'cv_text' ),
                    'brand_slugs'           => array( 'type' => 'keyword' ),
                    'brand_ids'             => array( 'type' => 'long' ),
                    'brand_facets'          => array( 'type' => 'keyword' ),
                    'category_names'        => array( 'type' => 'text', 'analyzer' => 'cv_text' ),
                    'category_slugs'        => array( 'type' => 'keyword' ),
                    'category_ids'          => array( 'type' => 'long' ),
                    'tag_names'             => array( 'type' => 'text', 'analyzer' => 'cv_text' ),
                    'tag_ids'               => array( 'type' => 'long' ),
                    'attributes_text'       => array( 'type' => 'text', 'analyzer' => 'cv_text' ),
                    'custom_fields_text'    => array( 'type' => 'text', 'analyzer' => 'cv_text' ),
                    'price'                 => array( 'type' => 'double' ),
                    'regular_price'         => array( 'type' => 'double' ),
                    'sale_price'            => array( 'type' => 'double' ),
                    'on_sale'               => array( 'type' => 'boolean' ),
                    'currency'              => array( 'type' => 'keyword' ),
                    'stock_status'          => array( 'type' => 'keyword' ),
                    'stock_quantity'        => array( 'type' => 'double' ),
                    'featured'              => array( 'type' => 'boolean' ),
                    'average_rating'        => array( 'type' => 'float' ),
                    'rating_count'          => array( 'type' => 'integer' ),
                    'type'                  => array( 'type' => 'keyword' ),
                    'status'                => array( 'type' => 'keyword' ),
                    'modified_gmt'          => array( 'type' => 'date' ),
                    'categories'            => array( 'type' => 'object', 'enabled' => false ),
                    'brands'                => array( 'type' => 'object', 'enabled' => false ),
                    'tags'                  => array( 'type' => 'object', 'enabled' => false ),
                    'attributes'            => array( 'type' => 'object', 'enabled' => false ),
                    'gallery'               => array( 'type' => 'object', 'enabled' => false ),
                    'variations'            => array( 'type' => 'object', 'enabled' => false ),
                    'image_url'             => array( 'type' => 'keyword', 'index' => false ),
                    'image_alt'             => array( 'type' => 'keyword', 'index' => false ),
                ),
            ),
        );

        return $this->request( 'PUT', '/' . rawurlencode( $index ), $body, 30 );
    }

    public function index_document( int $id, array $document ) {
        return $this->request(
            'PUT',
            '/' . rawurlencode( $this->settings->index_name() ) . '/_doc/' . $id,
            $document,
            20
        );
    }

    public function delete_document( int $id ) {
        return $this->request(
            'DELETE',
            '/' . rawurlencode( $this->settings->index_name() ) . '/_doc/' . $id,
            null,
            15,
            array( 404 )
        );
    }

    public function bulk( array $documents ) {
        if ( ! $documents ) {
            return array( 'errors' => false, 'items' => array() );
        }

        $lines = array();
        $index = $this->settings->index_name();

        foreach ( $documents as $document ) {
            $id = absint( $document['id'] ?? 0 );
            if ( ! $id ) {
                continue;
            }
            $lines[] = wp_json_encode( array( 'index' => array( '_index' => $index, '_id' => $id ) ) );
            $lines[] = wp_json_encode( $document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
        }

        $body = implode( "\n", $lines ) . "\n";

        return $this->request_raw(
            'POST',
            '/_bulk?refresh=false',
            $body,
            'application/x-ndjson',
            60
        );
    }

    public function search( array $body ) {
        return $this->request(
            'POST',
            '/' . rawurlencode( $this->settings->index_name() ) . '/_search',
            $body,
            max( 5, absint( $this->settings->get( 'timeout', 12 ) ) )
        );
    }

    public function request( string $method, string $path, ?array $body = null, int $timeout = 12, array $accept_codes = array() ) {
        $encoded = null === $body ? null : wp_json_encode( $body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
        return $this->request_raw( $method, $path, $encoded, 'application/json', $timeout, $accept_codes );
    }

    private function request_raw( string $method, string $path, ?string $body, string $content_type, int $timeout, array $accept_codes = array() ) {
        if ( ! $this->settings->configured() ) {
            return new WP_Error( 'cvos_not_configured', 'OpenSearch não configurado.' );
        }

        $base  = $this->settings->endpoint();
        $parts = wp_parse_url( $base );

        if ( ! is_array( $parts ) || empty( $parts['host'] ) || ! in_array( strtolower( (string) ( $parts['scheme'] ?? '' ) ), array( 'http', 'https' ), true ) ) {
            return new WP_Error( 'cvos_invalid_endpoint', 'Endpoint OpenSearch inválido.' );
        }

        if ( 'http' === strtolower( (string) $parts['scheme'] ) && ! $this->settings->is_yes( 'allow_insecure_http' ) ) {
            return new WP_Error( 'cvos_insecure_endpoint', 'HTTP sem TLS está bloqueado. Ative explicitamente a opção apenas numa rede privada.' );
        }

        $headers = array(
            'Accept'       => 'application/json',
            'Content-Type' => $content_type,
            'User-Agent'   => 'CV-OpenSearch/' . CVOS_VERSION . '; ' . home_url( '/' ),
        );

        $secret = $this->settings->secret();
        if ( 'basic' === $this->settings->auth_type() && $this->settings->username() && $secret ) {
            $headers['Authorization'] = 'Basic ' . base64_encode( $this->settings->username() . ':' . $secret );
        } elseif ( 'bearer' === $this->settings->auth_type() && $secret ) {
            $headers['Authorization'] = 'Bearer ' . $secret;
        }

        $args = array(
            'method'      => strtoupper( $method ),
            'timeout'     => $timeout,
            'redirection' => 0,
            'sslverify'   => $this->settings->is_yes( 'verify_ssl' ),
            'headers'     => $headers,
        );

        if ( null !== $body ) {
            $args['body'] = $body;
        }

        $response = wp_remote_request( $base . '/' . ltrim( $path, '/' ), $args );
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        $text = (string) wp_remote_retrieve_body( $response );
        $data = '' === trim( $text ) ? array() : json_decode( $text, true );

        if ( ( $code < 200 || $code >= 300 ) && ! in_array( $code, $accept_codes, true ) ) {
            $message = is_array( $data ) && isset( $data['error']['reason'] )
                ? (string) $data['error']['reason']
                : ( is_array( $data ) && isset( $data['error'] ) && is_string( $data['error'] ) ? $data['error'] : 'OpenSearch HTTP ' . $code );

            return new WP_Error(
                'cvos_remote_error',
                sanitize_text_field( $message ),
                array(
                    'status' => $code,
                    'body'   => $data,
                )
            );
        }

        return is_array( $data ) ? $data : array(
            'raw'    => $text,
            'status' => $code,
        );
    }
}
