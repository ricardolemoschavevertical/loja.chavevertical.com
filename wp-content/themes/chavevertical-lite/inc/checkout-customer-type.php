<?php
/**
 * Chave Vertical — faturação no checkout clássico WooCommerce.
 *
 * Alterna Particular (NIF) e Empresa (nome da empresa + NIPC).
 * Guarda os valores pela API de encomendas (compatível com HPOS) e apresenta
 * o identificador fiscal no detalhe da encomenda e nos emails.
 *
 * Não altera gateways, montantes, regras de IVA nem métodos de pagamento.
 */
defined( 'ABSPATH' ) || exit;

/**
 * Lê a última escolha de um cliente identificado. Uma conta antiga que tinha
 * empresa preenchida, mas não tinha tipo definido, começa como Empresa.
 */
function cvl_checkout_customer_saved_type(): string {
    $user_id = get_current_user_id();
    if ( ! $user_id ) {
        return 'particular';
    }

    $saved = (string) get_user_meta( $user_id, 'billing_customer_type', true );
    if ( in_array( $saved, array( 'particular', 'empresa' ), true ) ) {
        return $saved;
    }

    $company = trim( (string) get_user_meta( $user_id, 'billing_company', true ) );
    return '' !== $company ? 'empresa' : 'particular';
}

/**
 * Os campos fiscais só são usados no checkout com shortcode (form clássico).
 */
add_filter(
    'woocommerce_checkout_fields',
    static function ( $fields ) {
        if ( ! isset( $fields['billing'] ) || ! is_array( $fields['billing'] ) ) {
            return $fields;
        }

        $fields['billing']['billing_customer_type'] = array(
            'type'        => 'radio',
            'label'       => 'Tipo de cliente',
            'options'     => array(
                'particular' => 'Particular',
                'empresa'    => 'Empresa',
            ),
            'default'     => cvl_checkout_customer_saved_type(),
            'required'    => true,
            'priority'    => 5,
            'class'       => array( 'form-row-wide', 'cvl-customer-type-choice' ),
        );

        // Aproveita o campo WooCommerce existente em vez de o duplicar.
        $company = isset( $fields['billing']['billing_company'] ) && is_array( $fields['billing']['billing_company'] )
            ? $fields['billing']['billing_company']
            : array( 'type' => 'text' );
        $company['label']    = 'Nome da empresa';
        $company['required'] = false; // A obrigatoriedade condicional é validada no servidor.
        $company['priority'] = 31;
        $company['class']    = array( 'form-row-wide', 'cvl-fiscal-conditional' );
        $company['placeholder'] = 'Designação social';
        $fields['billing']['billing_company'] = $company;

        $fields['billing']['billing_nipc'] = array(
            'type'        => 'text',
            'label'       => 'NIPC',
            'placeholder' => 'Número de identificação de pessoa coletiva',
            'required'    => false,
            'priority'    => 32,
            'class'       => array( 'form-row-wide', 'cvl-fiscal-conditional' ),
            'maxlength'   => 32,
            'custom_attributes' => array( 'inputmode' => 'numeric' ),
        );

        $fields['billing']['billing_nif'] = array(
            'type'        => 'text',
            'label'       => 'NIF',
            'placeholder' => 'Número de identificação fiscal',
            'required'    => false,
            'priority'    => 33,
            'class'       => array( 'form-row-wide', 'cvl-fiscal-conditional' ),
            'maxlength'   => 32,
            'custom_attributes' => array( 'inputmode' => 'numeric' ),
        );

        return $fields;
    },
    30
);

/**
 * Garante que o checkout não guarda dados fiscais de um tipo que já não está
 * selecionado, mesmo que um browser envie campos previamente ocultados.
 */
function cvl_checkout_customer_clean_data( $data ) {
    $type = sanitize_key( (string) ( $data['billing_customer_type'] ?? '' ) );

    if ( 'particular' === $type ) {
        $data['billing_company'] = '';
        $data['billing_nipc']    = '';
    } elseif ( 'empresa' === $type ) {
        $data['billing_nif'] = '';
    }

    $country = strtoupper( (string) ( $data['billing_country'] ?? 'PT' ) );
    foreach ( array( 'billing_nif', 'billing_nipc' ) as $key ) {
        $value = sanitize_text_field( (string) ( $data[ $key ] ?? '' ) );
        if ( 'PT' === $country || '' === $country ) {
            $compact = strtoupper( preg_replace( '/[\s.\-]+/', '', $value ) );
            if ( str_starts_with( $compact, 'PT' ) ) {
                $compact = substr( $compact, 2 );
            }
            $value = $compact;
        }
        $data[ $key ] = $value;
    }

    return $data;
}
add_filter( 'woocommerce_checkout_posted_data', 'cvl_checkout_customer_clean_data', 20 );

/**
 * Algoritmo oficial de controlo, comum ao NIF e ao NIPC nacionais (9 dígitos).
 * Aceita espaços, pontos, hífen e prefixo PT digitados pelo cliente.
 */
function cvl_checkout_valid_pt_tax_id( string $value ): bool {
    $clean = strtoupper( preg_replace( '/[\s.\-]+/', '', trim( $value ) ) );
    if ( str_starts_with( $clean, 'PT' ) ) {
        $clean = substr( $clean, 2 );
    }

    if ( ! preg_match( '/^[0-9]{9}$/D', $clean ) ) {
        return false;
    }

    $sum = 0;
    for ( $i = 0; $i < 8; $i++ ) {
        $sum += (int) $clean[ $i ] * ( 9 - $i );
    }

    $digit = 11 - ( $sum % 11 );
    return ( $digit >= 10 ? 0 : $digit ) === (int) $clean[8];
}

/**
 * Não é suficiente esconder os campos no browser: validar também no servidor
 * impede encomendas Empresa sem nome/NIPC ou Particular sem NIF.
 */
function cvl_checkout_customer_validate( $data, $errors ): void {
    if ( ! $errors instanceof WP_Error ) {
        return;
    }

    $type = sanitize_key( (string) ( $data['billing_customer_type'] ?? '' ) );
    if ( ! in_array( $type, array( 'particular', 'empresa' ), true ) ) {
        $errors->add(
            'billing_customer_type_invalid',
            'Selecione Particular ou Empresa.',
            array( 'id' => 'billing_customer_type_field' )
        );
        return;
    }

    if ( 'empresa' === $type && '' === trim( (string) ( $data['billing_company'] ?? '' ) ) ) {
        $errors->add(
            'billing_company_required',
            'Preencha o Nome da empresa para continuar.',
            array( 'id' => 'billing_company' )
        );
    }

    $field = 'empresa' === $type ? 'billing_nipc' : 'billing_nif';
    $label = 'empresa' === $type ? 'NIPC' : 'NIF';
    $tax   = trim( (string) ( $data[ $field ] ?? '' ) );

    if ( '' === $tax ) {
        $errors->add(
            $field . '_required',
            'Preencha o ' . $label . ' para continuar.',
            array( 'id' => $field )
        );
        return;
    }

    $country = strtoupper( (string) ( $data['billing_country'] ?? 'PT' ) );
    if ( 'PT' === $country || '' === $country ) {
        if ( ! cvl_checkout_valid_pt_tax_id( $tax ) ) {
            $errors->add(
                $field . '_invalid',
                'Introduza um ' . $label . ' português válido (9 dígitos).',
                array( 'id' => $field )
            );
        }
    } elseif ( strlen( $tax ) > 32 ) {
        $errors->add( $field . '_too_long', 'O ' . $label . ' é demasiado longo.', array( 'id' => $field ) );
    }
}
add_action( 'woocommerce_after_checkout_validation', 'cvl_checkout_customer_validate', 10, 2 );

/**
 * Os campos billing_* personalizados também são guardados pelo WooCommerce,
 * mas explicitamos os metadados normalizados antes do save para suportar HPOS.
 */
function cvl_checkout_customer_save_order( $order, $data ): void {
    if ( ! $order instanceof WC_Order ) {
        return;
    }

    $type = sanitize_key( (string) ( $data['billing_customer_type'] ?? '' ) );
    if ( ! in_array( $type, array( 'particular', 'empresa' ), true ) ) {
        return;
    }

    $data = cvl_checkout_customer_clean_data( $data );
    $order->update_meta_data( '_billing_customer_type', $type );
    $order->update_meta_data( '_billing_nif', (string) ( $data['billing_nif'] ?? '' ) );
    $order->update_meta_data( '_billing_nipc', (string) ( $data['billing_nipc'] ?? '' ) );

    // Assegura que um Particular não herda a empresa de uma encomenda anterior.
    if ( 'particular' === $type ) {
        $order->set_billing_company( '' );
    }
}
add_action( 'woocommerce_checkout_create_order', 'cvl_checkout_customer_save_order', 20, 2 );

/**
 * Para clientes com conta, pré-preenche a escolha na encomenda seguinte.
 */
add_action(
    'woocommerce_checkout_update_user_meta',
    static function ( $user_id, $data ): void {
        $user_id = absint( $user_id );
        if ( ! $user_id ) {
            return;
        }

        $type = sanitize_key( (string) ( $data['billing_customer_type'] ?? '' ) );
        if ( ! in_array( $type, array( 'particular', 'empresa' ), true ) ) {
            return;
        }

        $data = cvl_checkout_customer_clean_data( $data );
        update_user_meta( $user_id, 'billing_customer_type', $type );
        update_user_meta( $user_id, 'billing_nif', (string) ( $data['billing_nif'] ?? '' ) );
        update_user_meta( $user_id, 'billing_nipc', (string) ( $data['billing_nipc'] ?? '' ) );
    },
    10,
    2
);

/**
 * Mostrar os dados fiscais nas encomendas da administração, com HPOS ou sem.
 */
add_action(
    'woocommerce_admin_order_data_after_billing_address',
    static function ( $order ): void {
        if ( ! $order instanceof WC_Order ) {
            return;
        }

        $type = (string) $order->get_meta( '_billing_customer_type', true );
        $nif  = (string) $order->get_meta( '_billing_nif', true );
        $nipc = (string) $order->get_meta( '_billing_nipc', true );

        if ( ! $type && ! $nif && ! $nipc ) {
            return; // Não alterar pedidos antigos sem identificação fiscal.
        }

        $is_company = 'empresa' === $type || ( ! $type && '' !== $nipc );
        echo '<p><strong>Tipo de cliente:</strong> ' . esc_html( $is_company ? 'Empresa' : 'Particular' ) . '</p>';

        $value = $is_company ? $nipc : $nif;
        if ( $value ) {
            echo '<p><strong>' . esc_html( $is_company ? 'NIPC' : 'NIF' ) . ':</strong> ' . esc_html( $value ) . '</p>';
        }
    },
    15
);

/**
 * Incluir o número fiscal nos emails da encomenda, tanto HTML como texto,
 * sem duplicar o Nome da empresa que já consta da morada WooCommerce.
 */
add_filter(
    'woocommerce_email_order_meta_fields',
    static function ( $fields, $sent_to_admin, $order ) {
        if ( ! $order instanceof WC_Order ) {
            return $fields;
        }

        $type = (string) $order->get_meta( '_billing_customer_type', true );
        $nif  = (string) $order->get_meta( '_billing_nif', true );
        $nipc = (string) $order->get_meta( '_billing_nipc', true );

        if ( 'empresa' === $type || ( ! $type && '' !== $nipc ) ) {
            if ( $nipc ) {
                $fields['cvl_billing_nipc'] = array( 'label' => 'NIPC', 'value' => esc_html( $nipc ) );
            }
        } elseif ( $nif ) {
            $fields['cvl_billing_nif'] = array( 'label' => 'NIF', 'value' => esc_html( $nif ) );
        }

        return $fields;
    },
    20,
    3
);
