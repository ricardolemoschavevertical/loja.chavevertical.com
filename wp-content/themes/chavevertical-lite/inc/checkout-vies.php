<?php
/**
 * Consulta opcional do VIES para faturação de EMPRESAS no checkout clássico.
 *
 * Segurança: endpoint fixo da Comissão Europeia, nonce, controlo de formato e
 * frequência, timeout, tamanho máximo da resposta, sem gravar dados VIES.
 * Não altera moradas de entrega, regras de IVA, envios ou pagamentos.
 *
 * O VIES valida o registo para IVA intracomunitário. Uma resposta negativa ou
 * indisponível nunca bloqueia a introdução manual dos dados de faturação.
 */
defined( 'ABSPATH' ) || exit;

/**
 * Códigos aceites pelo VIES, incluindo XI (Irlanda do Norte).
 */
function cvl_vies_supported_countries(): array {
    return array(
        'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'EL', 'ES',
        'FI', 'FR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT',
        'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK', 'XI',
    );
}

/**
 * Valida o país e o formato antes de contactar o serviço europeu.
 * Devolve apenas o país VIES e o número sem prefixo (sem dados pessoais).
 *
 * @return array{countryCode:string,vatNumber:string}|WP_Error
 */
function cvl_vies_normalize_request( string $country, string $vat ) {
    $country = strtoupper( trim( $country ) );
    $vat     = strtoupper( preg_replace( '/[\s.\-]+/u', '', trim( $vat ) ) );

    // O dropdown WooCommerce usa GR, mas a Comissão usa EL.
    if ( 'GR' === $country ) {
        $country = 'EL';
    }

    // Números XI são exclusivos do protocolo da Irlanda do Norte.
    if ( 'GB' === $country && str_starts_with( $vat, 'XI' ) ) {
        $country = 'XI';
    }

    if ( ! in_array( $country, cvl_vies_supported_countries(), true ) ) {
        return new WP_Error( 'country_unsupported', 'O VIES apenas verifica números de IVA da UE e da Irlanda do Norte.' );
    }

    // Não aceitar um prefixo de outro Estado-Membro que não corresponda ao país selecionado.
    $prefix = substr( $vat, 0, 2 );
    $allowed_prefix = 'EL' === $country ? array( 'EL', 'GR' ) : array( $country );

    if ( preg_match( '/^[A-Z]{2}/D', $prefix ) && in_array( $prefix, cvl_vies_supported_countries(), true ) && ! in_array( $prefix, $allowed_prefix, true ) ) {
        return new WP_Error( 'country_mismatch', 'O prefixo do número de IVA não corresponde ao país de faturação selecionado.' );
    }

    foreach ( $allowed_prefix as $candidate ) {
        if ( str_starts_with( $vat, $candidate ) ) {
            $vat = substr( $vat, strlen( $candidate ) );
            break;
        }
    }

    if ( ! preg_match( '/^[A-Z0-9]{3,20}$/D', $vat ) ) {
        return new WP_Error( 'invalid_vat_format', 'Indique um número de IVA válido, sem símbolos especiais.' );
    }

    if ( 'PT' === $country ) {
        if ( ! function_exists( 'cvl_checkout_pt_tax_id_kind' ) ) {
            return new WP_Error( 'validation_unavailable', 'Validação do NIPC indisponível. Preencha os dados de faturação manualmente.' );
        }
        $kind = cvl_checkout_pt_tax_id_kind( $vat );
        if ( ! in_array( $kind, array( 'nipc', 'entidade_at' ), true ) ) {
            return new WP_Error( 'invalid_pt_tax_id', 'Introduza um NIPC português válido, ou o NIF de uma entidade registada na AT.' );
        }
    }

    return array( 'countryCode' => $country, 'vatNumber' => $vat );
}

/** Valores fictícios/ocultados, não apropriados para preencher formulários. */
function cvl_vies_clean_profile_text( $value, int $max = 220 ): string {
    if ( ! is_string( $value ) ) {
        return '';
    }

    $value = trim( preg_replace( '/[[:cntrl:]]+/u', ' ', $value ) );
    $value = sanitize_text_field( $value );

    if ( '' === $value || preg_match( '/^\s*(?:[-–—*\/]+|N\/A|NOT\s+DISCLOSED|UNKNOWN|NULL)\s*$/iu', $value ) ) {
        return '';
    }

    return substr( $value, 0, $max );
}

/**
 * A morada genérica do VIES nem sempre vem separada.
 * Só preenche dados que a API devolva em campos estruturados. Uma primeira
 * linha isolada também pode ser usada como rua, sem inferir cidades/códigos.
 *
 * @param array<string,mixed> $payload Resposta VIES JSON.
 * @return array<string,string>
 */
function cvl_vies_extract_billing_profile( array $payload ): array {
    $name        = cvl_vies_clean_profile_text( $payload['name'] ?? '', 180 );
    $full        = cvl_vies_clean_profile_text( $payload['address'] ?? '', 500 );
    $street      = cvl_vies_clean_profile_text( $payload['traderStreet'] ?? '' );
    $postal      = cvl_vies_clean_profile_text( $payload['traderPostalCode'] ?? '', 30 );
    $city        = cvl_vies_clean_profile_text( $payload['traderCity'] ?? '', 120 );

    if ( '' === $street && is_string( $payload['address'] ?? null ) ) {
        $parts = preg_split( '/\r\n|\r|\n/u', $payload['address'] );
        $parts = array_values( array_filter( array_map( 'trim', $parts ) ) );
        // A primeira linha pode ser aplicada como rua apenas se vier separada
        // de pelo menos outra linha (sem inventar uma divisão de morada).
        if ( count( $parts ) >= 2 ) {
            $street = cvl_vies_clean_profile_text( $parts[0] );
        }
    }

    return array(
        'company'  => $name,
        'address'  => $full,
        'street'   => $street,
        'postcode' => $postal,
        'city'     => $city,
    );
}

/**
 * Converte uma resposta REST do VIES para um resultado limitado e seguro.
 * Invalid é distinto de serviço indisponível; não implica empresa inexistente.
 *
 * @return array<string,mixed>
 */
function cvl_vies_parse_service_reply( int $http_code, string $json ): array {
    if ( 200 !== $http_code || '' === $json || strlen( $json ) > 48000 ) {
        return array(
            'status'  => 'unavailable',
            'message' => 'O VIES está temporariamente indisponível. Preencha manualmente os dados de faturação.',
        );
    }

    $data = json_decode( $json, true );
    if ( ! is_array( $data ) || ! isset( $data['valid'] ) || ! is_bool( $data['valid'] ) ) {
        return array(
            'status'  => 'unavailable',
            'message' => 'Não foi possível interpretar a resposta do VIES. Preencha manualmente os dados de faturação.',
        );
    }

    if ( ! $data['valid'] ) {
        return array(
            'status'  => 'not_found',
            'message' => 'Número não confirmado no VIES. Pode não estar ativo para transações intracomunitárias. Preencha manualmente os dados de faturação.',
        );
    }

    return array(
        'status'  => 'valid',
        'message' => 'Número confirmado no VIES. Confira os dados de faturação antes de concluir a encomenda.',
        'profile' => cvl_vies_extract_billing_profile( $data ),
    );
}

/**
 * Adiciona a ação junto ao campo NIPC. Não adiciona campos fiscais à entrega.
 * Esta função não corre em páginas de conta, e não envia emails.
 */
add_filter(
    'woocommerce_form_field',
    static function ( $html, $key ) {
        if ( 'billing_nipc' !== $key || ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
            return $html;
        }

        if ( str_contains( $html, 'id="cvl-vies-check"' ) ) {
            return $html;
        }

        $controls = '<span class="cvl-vies-controls">'
            . '<button type="button" class="button cvl-vies-button" id="cvl-vies-check">Verificar no VIES</button>'
            . '<span id="cvl-vies-message" class="cvl-vies-message" role="status" aria-live="polite"></span>'
            . '</span>'
            . '<span class="cvl-vies-profile" id="cvl-vies-profile" hidden>'
            . '<strong>Dados disponibilizados pelo VIES</strong>'
            . '<span class="cvl-vies-profile-name" id="cvl-vies-profile-name"></span>'
            . '<span class="cvl-vies-profile-address" id="cvl-vies-profile-address"></span>'
            . '<small>Confirme e complete os dados de faturação. A morada de entrega não será alterada.</small>'
            . '</span>';

        $position = strrpos( $html, '</p>' );
        if ( false === $position ) {
            return $html;
        }

        return substr_replace( $html, $controls, $position, 0 );
    },
    20,
    2
);

/**
 * Serviço AJAX público acessível apenas com token da sessão de checkout.
 * Proteção extra de abuso por origem IP, sem guardar NIPC, nome ou morada.
 */
function cvl_vies_checkout_lookup_ajax(): void {
    if ( ! function_exists( 'wc_get_page_id' ) ) {
        wp_send_json_error( array( 'message' => 'O checkout não está disponível.' ), 503 );
    }

    if ( false === check_ajax_referer( 'cvl_vies_checkout', 'nonce', false ) ) {
        wp_send_json_error( array( 'message' => 'Sessão expirada. Atualize a página antes de consultar o VIES.' ), 403 );
    }

    $remote_addr = isset( $_SERVER['REMOTE_ADDR'] )
        ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) )
        : '';
    $rate_key = 'cvl_vies_rl_' . hash_hmac( 'sha256', $remote_addr . '|' . (string) get_current_user_id(), wp_salt( 'nonce' ) );
    $attempts = (int) get_transient( $rate_key );
    if ( $attempts >= 20 ) {
        wp_send_json_error( array( 'message' => 'Demasiadas consultas VIES. Preencha os dados manualmente ou volte a tentar mais tarde.' ), 429 );
    }
    set_transient( $rate_key, $attempts + 1, 10 * MINUTE_IN_SECONDS );

    $country = isset( $_POST['country'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['country'] ) ) : '';
    $vat     = isset( $_POST['vat'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['vat'] ) ) : '';
    if ( strlen( $vat ) > 40 ) {
        wp_send_json_error( array( 'message' => 'O número de IVA é demasiado longo.' ), 400 );
    }

    $normalized = cvl_vies_normalize_request( $country, $vat );
    if ( is_wp_error( $normalized ) ) {
        wp_send_json_success( array(
            'status'  => 'invalid_input',
            'message' => $normalized->get_error_message() . ' Pode preencher os dados de faturação manualmente.',
        ) );
    }

    $response = wp_remote_post(
        'https://ec.europa.eu/taxation_customs/vies/rest-api/check-vat-number',
        array(
            'timeout'     => 9,
            'redirection' => 0,
            'headers'     => array(
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ),
            'body'        => wp_json_encode( $normalized ),
            'sslverify'   => true,
        )
    );

    if ( is_wp_error( $response ) ) {
        wp_send_json_success( cvl_vies_parse_service_reply( 503, '' ) );
    }

    $status = (int) wp_remote_retrieve_response_code( $response );
    $body   = (string) wp_remote_retrieve_body( $response );
    wp_send_json_success( cvl_vies_parse_service_reply( $status, $body ) );
}

add_action( 'wp_ajax_cvl_vies_billing_lookup', 'cvl_vies_checkout_lookup_ajax' );
add_action( 'wp_ajax_nopriv_cvl_vies_billing_lookup', 'cvl_vies_checkout_lookup_ajax' );
