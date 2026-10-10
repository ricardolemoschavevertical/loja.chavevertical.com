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
            'placeholder' => 'NIPC português com 9 dígitos',
            'description' => 'Em Portugal, os NIPC começam por 5, 6, 8 ou 9. Entidades com NIF fiscal 7 também são aceites.',
            'required'    => false,
            'priority'    => 32,
            'class'       => array( 'form-row-wide', 'cvl-fiscal-conditional' ),
            'maxlength'   => 32,
            'custom_attributes' => array( 'inputmode' => 'numeric' ),
        );

        $fields['billing']['billing_nif'] = array(
            'type'        => 'text',
            'label'       => 'NIF',
            'placeholder' => 'NIF português com 9 dígitos',
            'description' => 'Em Portugal, o NIF de pessoa singular começa por 1, 2, 3 ou 4.',
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
        // Se a faturação for PT, remover PT do campo normalizado.
        // Para faturação noutro país, PRESERVAR PT: retirar o prefixo
        // permitia contornar a validação fiscal portuguesa depois da limpeza.
        $compact = strtoupper( preg_replace( '/[\s.\-]+/', '', $value ) );
        if ( 'PT' === $country || '' === $country ) {
            $value = str_starts_with( $compact, 'PT' )
                ? substr( $compact, 2 )
                : $compact;
        } elseif ( str_starts_with( $compact, 'PT' ) ) {
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

    if ( ! preg_match( '/^[1-9][0-9]{8}$/D', $clean ) ) {
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
 * Tipo legal da identificação fiscal portuguesa (o dígito de controlo deve
 * estar correto antes de classificar). Art. 4.º e 11.º DL 14/2013;
 * art. 13.º DL 129/98. Um prefixo não prova titularidade ou atividade.
 *
 * Pessoas singulares: 1–4 (inclui gama 45 para situações especiais).
 * NIPC do RNPC: 5, 6, 8, 9.
 * NIF de outras entidades atribuído pela AT: 7 (não é NIPC).
 */
function cvl_checkout_pt_tax_id_kind( string $value ): string {
    if ( ! cvl_checkout_valid_pt_tax_id( $value ) ) {
        return '';
    }

    $clean = strtoupper( preg_replace( '/[\\s.\\-]+/', '', trim( $value ) ) );
    if ( str_starts_with( $clean, 'PT' ) ) {
        $clean = substr( $clean, 2 );
    }

    if ( in_array( $clean[0], array( '1', '2', '3', '4' ), true ) ) {
        return 'singular';
    }
    if ( in_array( $clean[0], array( '5', '6', '8', '9' ), true ) ) {
        return 'nipc';
    }
    if ( '7' === $clean[0] ) {
        return 'entidade_at';
    }

    return '';
}

/** As entidades com NIF 7 podem faturar como Empresa sem NIPC do RNPC. */
function cvl_checkout_tax_kind_allowed( string $kind, string $customer_type ): bool {
    if ( 'particular' === $customer_type ) {
        return 'singular' === $kind;
    }
    if ( 'empresa' === $customer_type ) {
        return in_array( $kind, array( 'nipc', 'entidade_at' ), true );
    }

    return false;
}

/**
 * Categoria do número para impressão em emails e no administrador.
 * NIF 7 é designado NIF da entidade, nunca falsamente NIPC.
 */
function cvl_checkout_company_tax_label( string $value, string $country ): string {
    if ( 'PT' !== strtoupper( $country ) && ! preg_match( '/^PT/i', $value ) ) {
        return 'N.º fiscal / VAT';
    }
    return 'entidade_at' === cvl_checkout_pt_tax_id_kind( $value )
        ? 'NIF da entidade'
        : 'NIPC';
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

    $country = strtoupper( trim( (string) ( $data['billing_country'] ?? 'PT' ) ) );
    $is_pt_identifier = 'PT' === $country
        || '' === $country
        || (bool) preg_match( '/^PT/i', $tax );

    if ( $is_pt_identifier ) {
        $kind = cvl_checkout_pt_tax_id_kind( $tax );
        if ( '' === $kind ) {
            $errors->add(
                $field . '_invalid',
                'O ' . $label . ' português deve ter 9 dígitos e um dígito de controlo válido.',
                array( 'id' => $field )
            );
        } elseif ( ! cvl_checkout_tax_kind_allowed( $kind, $type ) ) {
            $message = 'particular' === $type
                ? 'O NIF de particular deve começar por 1, 2, 3 ou 4.'
                : 'O NIPC deve começar por 5, 6, 8 ou 9. Entidades com NIF atribuído pela AT iniciado por 7 também são aceites. Se é empresário em nome individual e usa NIF pessoal, selecione Particular.';
            $errors->add( $field . '_wrong_kind', $message, array( 'id' => $field ) );
        }
    } elseif ( strlen( $tax ) > 32 ) {
        // Outros países têm regras diferentes. Não aplicar o algoritmo PT
        // nem bloquear porque VIES está offline ou não tem o contribuinte.
        $errors->add(
            $field . '_too_long',
            'O número fiscal estrangeiro não pode ultrapassar 32 caracteres.',
            array( 'id' => $field )
        );
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
            $label = $is_company
                ? cvl_checkout_company_tax_label( $value, $order->get_billing_country() )
                : 'NIF';
            echo '<p><strong>' . esc_html( $label ) . ':</strong> ' . esc_html( $value ) . '</p>';
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
                $fields['cvl_billing_nipc'] = array(
                    'label' => cvl_checkout_company_tax_label( $nipc, $order->get_billing_country() ),
                    'value' => esc_html( $nipc ),
                );
            }
        } elseif ( $nif ) {
            $fields['cvl_billing_nif'] = array( 'label' => 'NIF', 'value' => esc_html( $nif ) );
        }

        return $fields;
    },
    20,
    3
);
