<?php
/**
 * CHAVE VERTICAL — dados fiscais para o WooCommerce Checkout Blocks (WC 9.9+).
 *
 * API OFICIAL woocommerce_register_additional_checkout_field:
 *   localização "order": aparece uma única vez, nunca na entrega.
 *   campos condicionais: Particular -> NIF; Empresa -> Nome + NIPC.
 *
 * O Bloco "Informação adicional" pode ser colocado junto de Faturação.
 * Não altera meios de pagamento, impostos, transportes ou Stock API.
 *
 * O checkout CLÁSSICO mantém os seus próprios campos/filtros no ficheiro
 * checkout-customer-type.php. Ambos os fluxos partilham as regras PT.
 */
defined( 'ABSPATH' ) || exit;

/**
 * Condição JSON Schema (suportada no frontend e backend do WC Blocks).
 *
 * location="order" coloca os valores em checkout.additional_fields.
 */
function cvl_blocks_fiscal_type_condition( string $customer_type, bool $inverse = false ): array {
    $comparison = $inverse
        ? array( 'not' => array( 'const' => $customer_type ) )
        : array( 'const' => $customer_type );

    return array(
        'checkout' => array(
            'properties' => array(
                'additional_fields' => array(
                    'properties' => array(
                        'cvl-fiscal/customer-type' => $comparison,
                    ),
                ),
            ),
        ),
    );
}

/** Normalizar apenas entradas de texto fornecidas pelo utilizador. */
function cvl_blocks_fiscal_sanitize( $value ): string {
    return is_scalar( $value )
        ? trim( sanitize_text_field( (string) $value ) )
        : '';
}

/** Lista única dos campos desta integração, usada também nos testes. */
function cvl_blocks_fiscal_fields(): array {
    $person = cvl_blocks_fiscal_type_condition( 'particular' );
    $company = cvl_blocks_fiscal_type_condition( 'empresa' );

    return array(
        array(
            'id'       => 'cvl-fiscal/customer-type',
            'label'    => 'Tipo de cliente para faturação',
            'location' => 'order',
            'type'     => 'select',
            'required' => true,
            'placeholder' => 'Selecionar Particular ou Empresa',
            'options'  => array(
                array( 'value' => 'particular', 'label' => 'Particular' ),
                array( 'value' => 'empresa', 'label' => 'Empresa' ),
            ),
        ),
        array(
            'id'       => 'cvl-fiscal/nif',
            'label'    => 'NIF',
            'location' => 'order',
            'type'     => 'text',
            'required' => $person,
            'hidden'   => cvl_blocks_fiscal_type_condition( 'particular', true ),
            'attributes' => array(
                'maxLength' => 32,
                'autocomplete' => 'off',
                'title' => 'NIF do particular: em Portugal são 9 dígitos.',
            ),
            'sanitize_callback' => 'cvl_blocks_fiscal_sanitize',
        ),
        array(
            'id'       => 'cvl-fiscal/company-name',
            'label'    => 'Nome da empresa (designação social)',
            'location' => 'order',
            'type'     => 'text',
            'required' => $company,
            'hidden'   => cvl_blocks_fiscal_type_condition( 'empresa', true ),
            'attributes' => array(
                'maxLength' => 180,
                'autocomplete' => 'organization',
            ),
            'sanitize_callback' => 'cvl_blocks_fiscal_sanitize',
        ),
        array(
            'id'       => 'cvl-fiscal/nipc',
            'label'    => 'NIPC / Número de IVA',
            'location' => 'order',
            'type'     => 'text',
            'required' => $company,
            'hidden'   => cvl_blocks_fiscal_type_condition( 'empresa', true ),
            'attributes' => array(
                'maxLength' => 32,
                'autocomplete' => 'off',
                'title' => 'NIPC português com 9 dígitos ou número de IVA estrangeiro.',
            ),
            'sanitize_callback' => 'cvl_blocks_fiscal_sanitize',
        ),
        // O envio pode usar a morada de faturação sem pedir novo endereço.
        // É um campo nativo do Woo Blocks, acessível e independente dos campos
        // fiscais: o JS apenas sincroniza billing -> shipping quando selecionado.
        array(
            'id'       => 'cvl-fiscal/use-billing-for-shipping',
            'label'    => 'Usar a mesma morada de faturação para entrega',
            'optionalLabel' => 'Usar a mesma morada de faturação para entrega',
            'location' => 'order',
            'type'     => 'checkbox',
            'required' => false,
        ),
    );
}

add_action(
    'woocommerce_init',
    static function (): void {
        if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
            return;
        }
        foreach ( cvl_blocks_fiscal_fields() as $field ) {
            woocommerce_register_additional_checkout_field( $field );
        }
    },
    20
);

/**
 * Os campos "order" são passados juntos neste hook. Validar o conjunto
 * (incluindo prefixos legais PT + módulo 11) no servidor, nunca só em JS.
 *
 * @param WP_Error $errors Erros de validação do Store API.
 * @param array    $fields Valores submetidos e visíveis nesta localização.
 * @param string   $group  other para campos de encomenda.
 */
function cvl_blocks_fiscal_validate( $errors, $fields, $group ): void {
    if ( ! $errors instanceof WP_Error || 'other' !== $group || ! is_array( $fields ) ) {
        return;
    }

    $type = sanitize_key( (string) ( $fields['cvl-fiscal/customer-type'] ?? '' ) );
    if ( '' === $type ) {
        // O cliente tem de escolher expressamente Particular ou Empresa.
        // Nunca aceitar Checkout Blocks sem a escolha, mesmo que o cliente
        // esconda/retire o campo no browser.
        $errors->add(
            'cvl_fiscal_customer_type_required',
            'Selecione Particular ou Empresa para a faturação.',
            array( 'id' => 'order-cvl-fiscal-customer-type' )
        );
        return;
    }

    $country = 'PT';
    if ( function_exists( 'WC' ) && WC() && isset( WC()->customer ) && WC()->customer ) {
        $country = (string) WC()->customer->get_billing_country();
    }

    // Usa exatamente a validação já implementada para o checkout clássico.
    $data = array(
        'billing_country' => '' !== $country ? $country : 'PT',
        'billing_customer_type' => $type,
        'billing_company' => cvl_blocks_fiscal_sanitize( $fields['cvl-fiscal/company-name'] ?? '' ),
        'billing_nif' => cvl_blocks_fiscal_sanitize( $fields['cvl-fiscal/nif'] ?? '' ),
        'billing_nipc' => cvl_blocks_fiscal_sanitize( $fields['cvl-fiscal/nipc'] ?? '' ),
    );

    cvl_checkout_customer_validate( $data, $errors );
}
add_action( 'woocommerce_blocks_validate_location_other_fields', 'cvl_blocks_fiscal_validate', 15, 3 );

/**
 * O WooCommerce grava automaticamente os additional_fields em metadados
 * próprios. Espelhar nos meta existentes para faturação/ERP, HPOS e emails.
 *
 * Este hook recebe sempre o pedido Store API, não depende de $_POST.
 */
function cvl_blocks_fiscal_save_order( $order, $request ): void {
    if ( ! $order instanceof WC_Order || ! $request instanceof WP_REST_Request ) {
        return;
    }

    $extra = $request->get_param( 'additional_fields' );
    if ( ! is_array( $extra ) ) {
        return;
    }

    $type = sanitize_key( (string) ( $extra['cvl-fiscal/customer-type'] ?? '' ) );
    if ( ! in_array( $type, array( 'particular', 'empresa' ), true ) ) {
        return;
    }

    $billing = $request->get_param( 'billing_address' );
    $country = is_array( $billing ) ? (string) ( $billing['country'] ?? $order->get_billing_country() ) : (string) $order->get_billing_country();

    $data = cvl_checkout_customer_clean_data(
        array(
            'billing_country' => '' !== $country ? $country : 'PT',
            'billing_customer_type' => $type,
            'billing_company' => cvl_blocks_fiscal_sanitize( $extra['cvl-fiscal/company-name'] ?? '' ),
            'billing_nif' => cvl_blocks_fiscal_sanitize( $extra['cvl-fiscal/nif'] ?? '' ),
            'billing_nipc' => cvl_blocks_fiscal_sanitize( $extra['cvl-fiscal/nipc'] ?? '' ),
        )
    );

    $order->update_meta_data( '_billing_customer_type', $type );
    $order->update_meta_data( '_billing_nif', $data['billing_nif'] );
    $order->update_meta_data( '_billing_nipc', $data['billing_nipc'] );

    // Não usar set_shipping_company nem alterar qualquer campo shipping_*.
    $order->set_billing_company( 'empresa' === $type ? $data['billing_company'] : '' );
}
add_action( 'woocommerce_store_api_checkout_update_order_from_request', 'cvl_blocks_fiscal_save_order', 20, 2 );
