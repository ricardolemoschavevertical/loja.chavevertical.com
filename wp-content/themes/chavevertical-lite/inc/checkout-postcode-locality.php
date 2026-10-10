<?php
/**
 * Localidade automática pelo código postal português no checkout.
 *
 * Consulta apenas o CP7, sem dados pessoais ou morada, através de endpoint
 * público do serviço Moradas (https://moradas.dev/cp/1234-567).
 * Compatível com WooCommerce Blocks e checkout clássico: AJAX no servidor.
 *
 * Nunca altera faturação/entrega, IVA, transportes, pagamentos ou pedidos.
 * Só sugere a localidade; permanece sempre editável pelo comprador.
 */
defined( 'ABSPATH' ) || exit;

/**
 * @return string CP7 no formato 1234-567 ou '' se não for português válido.
 */
function cvl_postcode_locality_normalize( string $postcode ): string {
    $digits = preg_replace( '/[\s-]+/u', '', trim( $postcode ) );
    if ( ! is_string( $digits ) || ! preg_match( '/^[0-9]{7}$/D', $digits ) ) {
        return '';
    }
    return substr( $digits, 0, 4 ) . '-' . substr( $digits, 4, 3 );
}

/**
 * Nome devolvido pelo serviço externo: limitar e remover indicadores inválidos.
 */
function cvl_postcode_locality_clean_name( $raw ): string {
    if ( ! is_string( $raw ) ) {
        return '';
    }

    $name = trim( sanitize_text_field( $raw ) );
    $name = preg_replace( '/\s+/u', ' ', $name );
    if (
        ! is_string( $name )
        || '' === $name
        || strlen( $name ) > 180
        || preg_match( '/^(?:n[\/.]?a|unknown|null|not found|indispon[ií]vel|[-*]+)$/iu', $name )
        || preg_match( '/[<>{}]/', $name )
    ) {
        return '';
    }

    return $name;
}

/**
 * Interpretar apenas nome da localidade e CP7. Nunca devolver ruas, pessoas,
 * coordenadas ou outros dados que possam vir no JSON remoto.
 *
 * @return array{status:string,localidade?:string,message?:string}
 */
function cvl_postcode_locality_parse_reply( int $http_status, string $body, string $requested_cp ): array {
    if ( 404 === $http_status ) {
        return array( 'status' => 'not_found' );
    }

    if ( 200 !== $http_status || '' === $body || strlen( $body ) > 65536 ) {
        return array( 'status' => 'unavailable' );
    }

    $payload = json_decode( $body, true );
    if ( ! is_array( $payload ) ) {
        return array( 'status' => 'unavailable' );
    }

    // O serviço Moradas devolve localidade, concelho, distrito e cp7.
    $record = $payload;
    if ( isset( $payload['data'] ) && is_array( $payload['data'] ) ) {
        $record = $payload['data'];
    }

    // Nunca associar uma localidade se o CP7 devolvido for diferente.
    foreach ( array( 'cp7', 'codigo_postal', 'codigoPostal', 'postcode' ) as $cp_key ) {
        if ( ! isset( $record[ $cp_key ] ) || ! is_scalar( $record[ $cp_key ] ) ) {
            continue;
        }
        if ( cvl_postcode_locality_normalize( (string) $record[ $cp_key ] ) !== $requested_cp ) {
            return array( 'status' => 'not_found' );
        }
        break;
    }

    // Se a API devolver localidades diferentes para o mesmo CP7, não adivinhar.
    $possible = array();
    if ( isset( $record['localidades'] ) && is_array( $record['localidades'] ) ) {
        foreach ( $record['localidades'] as $item ) {
            $candidate = cvl_postcode_locality_clean_name(
                is_array( $item ) ? ( $item['localidade'] ?? '' ) : $item
            );
            if ( '' !== $candidate ) {
                $key = function_exists( 'mb_strtolower' )
                    ? mb_strtolower( $candidate, 'UTF-8' )
                    : strtolower( $candidate );
                $possible[ $key ] = $candidate;
            }
        }
        if ( count( $possible ) > 1 ) {
            return array( 'status' => 'ambiguous' );
        }
    }

    // Preferir a localidade, e não o concelho (que podem ser diferentes).
    $locality = cvl_postcode_locality_clean_name( $record['localidade'] ?? ( $record['Localidade'] ?? '' ) );
    if ( '' === $locality && 1 === count( $possible ) ) {
        $locality = reset( $possible );
    }
    if ( '' === $locality ) {
        $locality = cvl_postcode_locality_clean_name( $record['designacao_postal'] ?? ( $record['Designação Postal'] ?? '' ) );
    }

    if ( '' === $locality ) {
        return array( 'status' => 'not_found' );
    }

    return array(
        'status'    => 'found',
        'localidade' => $locality,
    );
}

/**
 * Cache partilhada por código postal — reduz chamadas externas e latência.
 * Sucesso: 30 dias; "não encontrado": 6 horas; falhas: não armazenadas.
 */
function cvl_postcode_locality_resolve( string $postcode ): array {
    $key = 'cvl_cp_loc_' . md5( $postcode );
    $cached = get_transient( $key );
    if ( is_array( $cached ) && isset( $cached['status'] ) ) {
        return $cached;
    }

    // URL fixa + CP7 estritamente validado. Sem possibilidade de SSRF.
    $response = wp_remote_get(
        'https://moradas.dev/cp/' . rawurlencode( $postcode ),
        array(
            'timeout'             => 5,
            'redirection'         => 0,
            'sslverify'           => true,
            'reject_unsafe_urls'  => true,
            'limit_response_size' => 65536,
            'headers'             => array( 'Accept' => 'application/json' ),
        )
    );

    if ( is_wp_error( $response ) ) {
        return array( 'status' => 'unavailable' );
    }

    $result = cvl_postcode_locality_parse_reply(
        (int) wp_remote_retrieve_response_code( $response ),
        (string) wp_remote_retrieve_body( $response ),
        $postcode
    );

    if ( 'found' === $result['status'] ) {
        set_transient( $key, $result, 30 * DAY_IN_SECONDS );
    } elseif ( 'not_found' === $result['status'] ) {
        set_transient( $key, $result, 6 * HOUR_IN_SECONDS );
    }

    return $result;
}

/** Resposta AJAX apenas a partir do checkout da própria loja. */
function cvl_postcode_locality_ajax(): void {
    if ( false === check_ajax_referer( 'cvl_postcode_locality', 'nonce', false ) ) {
        wp_send_json_error( array( 'message' => 'Sessão expirada.' ), 403 );
    }

    $country = isset( $_POST['country'] )
        ? strtoupper( sanitize_text_field( wp_unslash( (string) $_POST['country'] ) ) )
        : '';
    if ( 'PT' !== $country ) {
        wp_send_json_success( array( 'status' => 'unsupported' ) );
    }

    $raw_cp = isset( $_POST['postcode'] )
        ? sanitize_text_field( wp_unslash( (string) $_POST['postcode'] ) )
        : '';
    if ( strlen( $raw_cp ) > 24 ) {
        wp_send_json_success( array( 'status' => 'invalid' ) );
    }

    $postcode = cvl_postcode_locality_normalize( $raw_cp );
    if ( '' === $postcode ) {
        wp_send_json_success( array( 'status' => 'invalid' ) );
    }

    // Limite de abuso no endpoint AJAX, sem armazenar IP legível.
    $remote_addr = isset( $_SERVER['REMOTE_ADDR'] )
        ? (string) $_SERVER['REMOTE_ADDR']
        : '';
    $rate_key = 'cvl_cp_rl_' . hash_hmac( 'sha256', $remote_addr . '|' . (string) get_current_user_id(), wp_salt( 'nonce' ) );
    $count = (int) get_transient( $rate_key );
    if ( $count >= 90 ) {
        wp_send_json_success( array( 'status' => 'unavailable' ) );
    }
    set_transient( $rate_key, $count + 1, 10 * MINUTE_IN_SECONDS );

    $result = cvl_postcode_locality_resolve( $postcode );
    wp_send_json_success( $result );
}

add_action( 'wp_ajax_cvl_postcode_locality', 'cvl_postcode_locality_ajax' );
add_action( 'wp_ajax_nopriv_cvl_postcode_locality', 'cvl_postcode_locality_ajax' );
