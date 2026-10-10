<?php
/**
 * Chave Vertical — validação de códigos postais no checkout clássico.
 *
 * PT: sete algarismos no formato CTT 1234-567.
 * Billing e shipping são tratados como endereços independentes.
 * Os restantes países mantêm as regras nativas do WooCommerce.
 * No Checkout Blocks, a validação de servidor acontece pela Store API
 * via WC_Validation::is_postcode / woocommerce_validate_postcode.
 *
 * IMPORTANTE: valida a estrutura, não a existência do código numa base CTT
 * nem a correspondência entre o código postal, a rua e a localidade.
 */
defined( 'ABSPATH' ) || exit;

/** Aceita formas comuns coladas pelo cliente, sem inventar dígitos. */
function cvl_checkout_normalize_pt_postcode( string $value ): string {
    $value = trim( $value );
    $compact = preg_replace( '/[\s-]+/u', '', $value );

    if ( is_string( $compact ) && preg_match( '/^[0-9]{7}$/D', $compact ) ) {
        return substr( $compact, 0, 4 ) . '-' . substr( $compact, 4 );
    }

    return $value;
}

/** Só formato. A existência real exige dados oficiais dos CTT. */
function cvl_checkout_valid_pt_postcode( string $value ): bool {
    return (bool) preg_match( '/^[0-9]{4}-[0-9]{3}$/D', $value );
}

/** Normalizar os dois códigos sem os misturar. */
function cvl_checkout_postcodes_normalize_data( $data ) {
    if ( ! is_array( $data ) ) {
        return $data;
    }

    foreach ( array( 'billing', 'shipping' ) as $section ) {
        $country_key = $section . '_country';
        $postcode_key = $section . '_postcode';

        if (
            'PT' !== strtoupper( trim( (string) ( $data[ $country_key ] ?? '' ) ) )
            || ! isset( $data[ $postcode_key ] )
            || ! is_scalar( $data[ $postcode_key ] )
        ) {
            continue;
        }

        $data[ $postcode_key ] = cvl_checkout_normalize_pt_postcode( (string) $data[ $postcode_key ] );
    }

    return $data;
}
add_filter( 'woocommerce_checkout_posted_data', 'cvl_checkout_postcodes_normalize_data', 30 );

/**
 * Respeita as configurações de obrigatoriedade de cada país. Apenas assegura
 * que os campos Woo existentes continuam a usar a validação postal nativa.
 */
add_filter(
    'woocommerce_checkout_fields',
    static function ( $fields ) {
        foreach ( array( 'billing', 'shipping' ) as $section ) {
            $key = $section . '_postcode';
            if ( ! isset( $fields[ $section ][ $key ] ) || ! is_array( $fields[ $section ][ $key ] ) ) {
                continue;
            }

            $validate = isset( $fields[ $section ][ $key ]['validate'] )
                ? (array) $fields[ $section ][ $key ]['validate']
                : array();
            if ( ! in_array( 'postcode', $validate, true ) ) {
                $validate[] = 'postcode';
            }
            $fields[ $section ][ $key ]['validate'] = array_values( array_unique( $validate ) );
        }

        return $fields;
    },
    25
);

/** Validação uniforme quando outro plugin chama WC_Validation diretamente. */
add_filter(
    'woocommerce_validate_postcode',
    static function ( $valid, $postcode, $country ) {
        if ( 'PT' !== strtoupper( (string) $country ) ) {
            return $valid;
        }

        return (bool) $valid && cvl_checkout_valid_pt_postcode( (string) $postcode );
    },
    25,
    3
);

/** Traduzir e esclarecer o erro nativo, sem alterar as mensagens estrangeiras. */
add_filter(
    'woocommerce_checkout_postcode_validation_notice',
    static function ( $notice, $country ) {
        if ( 'PT' !== strtoupper( (string) $country ) ) {
            return $notice;
        }

        return 'Introduza um código postal português válido no formato 1234-567.';
    },
    25,
    2
);

/**
 * Validação adicional para integrações que retirem a regra 'postcode' aos
 * campos clássicos. Evita duplicar o erro emitido pelo WooCommerce nativo.
 */
function cvl_checkout_postcodes_validate_data( $data, $errors ): void {
    if ( ! is_array( $data ) || ! $errors instanceof WP_Error ) {
        return;
    }

    foreach ( array( 'billing', 'shipping' ) as $section ) {
        if ( 'shipping' === $section && empty( $data['ship_to_different_address'] ) ) {
            continue;
        }

        if ( 'shipping' === $section && function_exists( 'WC' ) ) {
            $wc = WC();
            if ( is_object( $wc ) && isset( $wc->cart ) && $wc->cart && ! $wc->cart->needs_shipping_address() ) {
                continue;
            }
        }

        $country = strtoupper( (string) ( $data[ $section . '_country' ] ?? '' ) );
        $raw = (string) ( $data[ $section . '_postcode' ] ?? '' );

        // Campos vazios continuam a obedecer à obrigatoriedade dinâmica Woo.
        if ( 'PT' !== $country || '' === trim( $raw ) ) {
            continue;
        }

        $normalized = cvl_checkout_normalize_pt_postcode( $raw );
        if ( cvl_checkout_valid_pt_postcode( $normalized ) ) {
            continue;
        }

        // O WooCommerce usa este código para erros postais nativos.
        if ( $errors->get_error_message( $section . '_postcode_validation' ) ) {
            continue;
        }

        $label = 'billing' === $section ? 'faturação' : 'entrega';
        $errors->add(
            'cvl_' . $section . '_postcode_invalid',
            'O código postal de ' . $label . ' deve ter 7 algarismos no formato 1234-567.',
            array( 'id' => $section . '_postcode' )
        );
    }
}
add_action( 'woocommerce_after_checkout_validation', 'cvl_checkout_postcodes_validate_data', 20, 2 );

/**
 * Link de pesquisa oficial, se for necessário confirmar a rua e localidade.
 * Nunca sugere que a simples validação de formato confirma a morada.
 */
add_filter(
    'woocommerce_form_field',
    static function ( $html, $key ) {
        if (
            ! in_array( $key, array( 'billing_postcode', 'shipping_postcode' ), true )
            || ! function_exists( 'is_checkout' )
            || ! is_checkout()
            || str_contains( $html, 'cvl-postcode-help' )
        ) {
            return $html;
        }

        $help = '<span class="cvl-postcode-help" aria-live="off">'
            . 'Formato português: <strong>1234-567</strong>. '
            . '<a href="https://www.ctt.pt/feapl_2/app/open/postalCodeSearch/postalCodeSearch.jspx"'
            . ' target="_blank" rel="noopener noreferrer">'
            . 'Confirmar morada nos CTT</a>'
            . '</span>';

        $pos = strrpos( $html, '</p>' );
        return false === $pos ? $html : substr_replace( $html, $help, $pos, 0 );
    },
    22,
    2
);
