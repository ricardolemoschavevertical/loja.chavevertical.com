<?php
/**
 * Plugin Name: CV R2 Media Bridge
 * Description: Regista imagens ja existentes no R2 como anexos WordPress por REST, sem copiar o ficheiro.
 * Version: 0.1.0
 * Requires PHP: 8.0
 * Author: Chave Vertical
 */
defined( 'ABSPATH' ) || exit;

final class CV_R2_Media_Bridge {
    const META_KEY = '_cv_r2_bridge_key';
    const REST_NS = 'cv-transfer/v1';

    public static function init(): void {
        add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
        add_filter( 'wp_get_attachment_url', array( __CLASS__, 'attachment_url' ), 20, 2 );
        add_filter( 'image_downsize', array( __CLASS__, 'image_downsize' ), 20, 3 );
    }

    private static function base_url(): string {
        // Define CV_TRANSFER_R2_PUBLIC_BASE in wp-config.php, e.g. https://media.example.com.
        if ( ! defined( 'CV_TRANSFER_R2_PUBLIC_BASE' ) ) {
            return '';
        }
        $url = untrailingslashit( (string) CV_TRANSFER_R2_PUBLIC_BASE );
        $parts = wp_parse_url( $url );
        if ( ! is_array( $parts ) || 'https' !== ( $parts['scheme'] ?? '' ) || empty( $parts['host'] ) ||
            isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['query'] ) || isset( $parts['fragment'] ) ) {
            return '';
        }
        return $url;
    }

    private static function object_url( string $key ): string {
        $segments = explode( '/', $key );
        return self::base_url() . '/' . implode( '/', array_map( 'rawurlencode', $segments ) );
    }

    public static function register_routes(): void {
        register_rest_route( self::REST_NS, '/media/resolve', array(
            'methods' => 'POST',
            'permission_callback' => static function (): bool {
                return current_user_can( 'upload_files' ) && current_user_can( 'edit_products' );
            },
            'callback' => array( __CLASS__, 'resolve' ),
            'args' => array(
                'key' => array( 'required' => true, 'type' => 'string' ),
            ),
        ) );
    }

    public static function resolve( WP_REST_Request $request ) {
        global $wpdb;
        if ( ! self::base_url() ) {
            return new WP_Error( 'cv_r2_config', 'Configurar CV_TRANSFER_R2_PUBLIC_BASE com HTTPS.', array( 'status' => 503 ) );
        }
        $key = trim( (string) $request->get_param( 'key' ) );
        if ( strlen( $key ) > 1024 || ! preg_match( '~^(?!/)(?!.*(?:^|/)\\.\\.?(/|$))[a-zA-Z0-9_./ -]+\\.(?:webp|png|jpe?g|gif)$~iD', $key )
            || str_contains( $key, '//' ) || str_contains( $key, '\\' ) ) {
            return new WP_Error( 'cv_r2_key', 'Chave de objeto invalida.', array( 'status' => 400 ) );
        }

        // Reuse a record created by this bridge only if it still is an attachment.
        $ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s LIMIT 3",
            self::META_KEY, $key
        ) );
        $valid = array_values( array_filter( array_map( 'intval', $ids ), 'wp_attachment_is_image' ) );
        if ( count( $valid ) > 1 ) {
            return new WP_Error( 'cv_r2_duplicate', 'Existem anexos duplicados para esta chave.', array( 'status' => 409 ) );
        }
        if ( count( $valid ) === 1 ) {
            return rest_ensure_response( array( 'id' => $valid[0], 'created' => false, 'url' => self::object_url( $key ) ) );
        }

        $url = self::object_url( $key );
        // Fixed, administrator-configured host: never accept a URL from the caller.
        $res = wp_remote_head( $url, array( 'timeout' => 12, 'redirection' => 0, 'sslverify' => true ) );
        if ( is_wp_error( $res ) || 200 !== wp_remote_retrieve_response_code( $res ) ) {
            return new WP_Error( 'cv_r2_not_found', 'O ficheiro nao foi confirmado no URL publico R2.', array( 'status' => 422 ) );
        }
        $types = array( 'webp' => 'image/webp', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif' );
        $ext = strtolower( pathinfo( $key, PATHINFO_EXTENSION ) );
        $mime = $types[$ext];
        $remote_type = strtolower( trim( explode( ';', (string) wp_remote_retrieve_header( $res, 'content-type' ) )[0] ) );
        if ( $remote_type && $remote_type !== $mime ) {
            return new WP_Error( 'cv_r2_mime', 'O tipo de ficheiro no R2 nao corresponde a extensao.', array( 'status' => 422 ) );
        }

        $attachment_id = wp_insert_attachment( array(
            'post_title' => sanitize_text_field( pathinfo( basename( $key ), PATHINFO_FILENAME ) ),
            'post_mime_type' => $mime,
            'post_status' => 'inherit',
            'guid' => $url,
        ), false, 0, true );
        if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
            return new WP_Error( 'cv_r2_insert', 'Nao foi possivel criar o anexo.', array( 'status' => 500 ) );
        }
        update_post_meta( $attachment_id, self::META_KEY, $key );
        // Intentionally no _wp_attached_file: object exists remotely and must not be treated as local.
        return rest_ensure_response( array( 'id' => (int) $attachment_id, 'created' => true, 'url' => $url ) );
    }

    public static function attachment_url( $url, $attachment_id ) {
        $key = get_post_meta( (int) $attachment_id, self::META_KEY, true );
        return $key && self::base_url() ? self::object_url( (string) $key ) : $url;
    }

    public static function image_downsize( $downsize, $id, $size ) {
        $key = get_post_meta( (int) $id, self::META_KEY, true );
        if ( ! $key || ! self::base_url() ) {
            return $downsize;
        }
        $meta = wp_get_attachment_metadata( $id );
        return array( self::object_url( (string) $key ), (int) ( $meta['width'] ?? 0 ), (int) ( $meta['height'] ?? 0 ), false );
    }
}
CV_R2_Media_Bridge::init();
