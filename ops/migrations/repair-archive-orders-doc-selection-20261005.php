<?php
/**
 * Reconcile the selected legacy-order archive rows with the consolidated Gmail
 * order summary document. Only the local archive is changed.
 *
 * Source document:
 * Google Doc ID 15AKFFgU0JenFSeeQg7v-tw2FIcX9-nqqW1hjc-wReyQ
 *
 * #1674803 and #1674657 are deliberately not present because the source
 * document has no matching order record for them.
 */

defined( 'ABSPATH' ) || exit;

$marker = 'cv_repair_archive_doc_selection_20261005_v1';

if ( get_option( $marker ) ) {
    echo "archive-doc-selection=already-applied\n";
    return;
}

$orders = array(
    1674901 => array(
        'archive_key' => 'remote-8d20db3492-1674901',
        'email_id' => '1a0b3b43c0770642',
        'payment_method_title' => 'Pagamento de Serviços no Multibanco',
        'items' => array(
            array( 'product_id' => 125162, 'sku' => 'SOD08612', 'name' => 'Depósito para Gasóleo STILKER 200L 12V 40L/min Polietileno', 'quantity' => 1, 'net' => '308.94', 'tax' => '71.06' ),
        ),
        'shipping_title' => 'Envio grátis (Transporte marítimo é da responsabilidade do cliente)',
        'shipping_total' => '0.00',
        'total_tax' => '71.06',
        'order_total' => '380.00',
    ),
    1674898 => array(
        'archive_key' => 'remote-8d20db3492-1674898',
        'email_id' => '1a0b1077858e27b3',
        'payment_method_title' => 'MBWAY',
        'items' => array(
            array( 'product_id' => 56366, 'sku' => 'KT2084', 'name' => 'Enrolador de Ar Comprimido KROFTOOLS 15m 8x12mm 150 PSI', 'quantity' => 1, 'net' => '51.22', 'tax' => '11.78' ),
        ),
        'shipping_title' => 'ENVIO (Portugal Continental)',
        'shipping_total' => '7.50',
        'total_tax' => '11.78',
        'order_total' => '70.50',
    ),
    1674804 => array(
        'archive_key' => 'remote-8d20db3492-1674804',
        'email_id' => '1a0afc8ea44bbfa9',
        'payment_method_title' => 'Envio à cobrança',
        'customer_note' => 'Carlos Monteiro',
        'items' => array(
            array( 'product_id' => 1537208, 'sku' => 'SOD08470', 'name' => 'Bacia de Retenção DRAKKAR 1100L PE 1 IBC 1500kg', 'quantity' => 1, 'net' => '686.99', 'tax' => '158.01' ),
        ),
        'shipping_title' => 'ENTREGA GRATUITA*',
        'shipping_total' => '0.00',
        'fee_name' => 'Pagamento na entrega',
        'fee_net' => '5.00',
        'fee_tax' => '1.15',
        'total_tax' => '159.16',
        'order_total' => '851.15',
    ),
    1674779 => array(
        'archive_key' => 'remote-8d20db3492-1674779',
        'email_id' => '1a0ab26202d5298b',
        'payment_method_title' => 'MBWAY',
        'items' => array(
            array( 'product_id' => 1620820, 'sku' => 'SOD71525', 'name' => 'Kit de Sincronização STILKER para VAG 1.4-1.6-2.0 CR TDI', 'quantity' => 1, 'net' => '25.20', 'tax' => '5.80' ),
        ),
        'shipping_title' => 'ENVIO (Portugal Continental)',
        'shipping_total' => '7.50',
        'total_tax' => '5.80',
        'order_total' => '38.50',
    ),
    1674654 => array(
        'archive_key' => 'remote-8d20db3492-1674654',
        'email_id' => '1a09c824a8fd2c08',
        'payment_method_title' => 'Pagamento de Serviços no Multibanco',
        'items' => array(
            array( 'product_id' => 117146, 'sku' => 'MEGA-5298', 'name' => 'Balancé de Cargas MEGA 500 KG', 'quantity' => 1, 'net' => '229.27', 'tax' => '52.73' ),
        ),
        'shipping_title' => 'ENTREGA GRATUITA*',
        'shipping_total' => '0.00',
        'total_tax' => '52.73',
        'order_total' => '282.00',
    ),
    1674652 => array(
        'archive_key' => 'remote-8d20db3492-1674652',
        'email_id' => '1a096536419697c6',
        'payment_method_title' => 'Pagamento por Fatura Pro-forma',
        'customer_note' => 'Entrar em contacto uma hora antes da entregar',
        'items' => array(
            array( 'product_id' => 40974, 'sku' => 'HZMABS120MM_4LFM', 'name' => 'Mangueira de Aspiração HOLZMANN Ø 120mm 4m', 'quantity' => 1, 'net' => '59.35', 'tax' => '13.65' ),
        ),
        'shipping_title' => 'ENVIO (Portugal Continental)',
        'shipping_total' => '7.50',
        'total_tax' => '13.65',
        'order_total' => '80.50',
    ),
    1674650 => array(
        'archive_key' => 'remote-8d20db3492-1674650',
        'email_id' => '1a095673f53599e6',
        'payment_method_title' => 'Pagamento por Fatura Pro-forma',
        'items' => array(
            array( 'product_id' => 57011, 'sku' => 'KT7015', 'name' => 'Despolidor de Cilindros Kroftools 32-89mm Pedras 50mm', 'quantity' => 1, 'net' => '13.01', 'tax' => '2.99' ),
        ),
        'shipping_title' => 'ENVIO (Portugal Continental)',
        'shipping_total' => '7.50',
        'total_tax' => '2.99',
        'order_total' => '23.50',
    ),
    1674646 => array(
        'archive_key' => 'remote-8d20db3492-1674646',
        'email_id' => '1a0925884e5a9662',
        'payment_method_title' => 'MBWAY',
        'items' => array(
            array( 'product_id' => 102685, 'sku' => 'JBM51266', 'name' => 'Cinto de Segurança de 3 Pontos Enrolável para Autocarros JBM 51266 2900mm 1.13Kg', 'quantity' => 1, 'net' => '24.39', 'tax' => '5.61' ),
        ),
        'shipping_title' => 'ENVIO (Portugal Continental)',
        'shipping_total' => '7.50',
        'total_tax' => '5.61',
        'order_total' => '37.50',
    ),
    1674590 => array(
        'archive_key' => 'remote-8d20db3492-1674590',
        'email_id' => '1a0909e892af92c9',
        'payment_method_title' => 'MBWAY',
        'items' => array(
            array( 'product_id' => 104869, 'sku' => 'JBM54433', 'name' => 'Kit de Sincronização PSA 1.0-2.0 JBM 7 Peças Multimarca', 'quantity' => 1, 'net' => '15.45', 'tax' => '3.55' ),
        ),
        'shipping_title' => 'ENVIO (Portugal Continental)',
        'shipping_total' => '7.50',
        'total_tax' => '3.55',
        'order_total' => '26.50',
    ),
    1674044 => array(
        'archive_key' => 'remote-8d20db3492-1674044',
        'email_id' => '1a08c10f74e135fd',
        'payment_method_title' => 'Pagamento de Serviços no Multibanco',
        'items' => array(
            array( 'product_id' => 97782, 'sku' => 'POWERED354923', 'name' => 'FITA SERRA 2090x20 5/8 POWERED', 'quantity' => 2, 'net' => '48.00', 'tax' => '11.04' ),
        ),
        'shipping_title' => 'ENVIO (Portugal Continental)',
        'shipping_total' => '7.50',
        'total_tax' => '11.04',
        'order_total' => '66.54',
    ),
    1674036 => array(
        'archive_key' => 'remote-8d20db3492-1674036',
        'email_id' => '1a08bef65762810a',
        'payment_method_title' => 'Pagamento por Fatura Pro-forma',
        'items' => array(
            array( 'product_id' => 1640789, 'sku' => 'KT3281', 'name' => 'Bomba Manual de Transfega KROFTOOLS 32L/min para Bidões 50/200L', 'quantity' => 1, 'net' => '22.76', 'tax' => '5.24' ),
        ),
        'shipping_title' => 'ENVIO (Portugal Continental)',
        'shipping_total' => '7.50',
        'total_tax' => '5.24',
        'order_total' => '35.50',
    ),
    1674025 => array(
        'archive_key' => 'remote-8d20db3492-1674025',
        'email_id' => '1a08bb409acbfee8',
        'payment_method_title' => 'VISA / MASTERCARD',
        'items' => array(
            array( 'product_id' => 88448, 'sku' => 'POWERED276064', 'name' => 'Bomba de Água 220V 100W POWERED', 'quantity' => 1, 'net' => '154.47', 'tax' => '35.53' ),
        ),
        'shipping_title' => 'ENTREGA GRATUITA*',
        'shipping_total' => '0.00',
        'total_tax' => '35.53',
        'order_total' => '190.00',
    ),
    1673964 => array(
        'archive_key' => 'remote-8d20db3492-1673964',
        'email_id' => '1a087e9a3d722dff',
        // O documento não contém método de pagamento para esta encomenda.
        'items' => array(
            array( 'product_id' => 143842, 'sku' => 'SOD11020', 'name' => 'Gerador AVR Monofásico Gasolina DRAKKAR 5500W 13CV Arranque Elétrico', 'quantity' => 1, 'net' => '569.11', 'tax' => '130.89' ),
        ),
        'shipping_title' => 'ENTREGA GRATUITA*',
        'shipping_total' => '0.00',
        'total_tax' => '130.89',
        'order_total' => '700.00',
    ),
    1673914 => array(
        'archive_key' => 'remote-8d20db3492-1673914',
        'email_id' => '1a0877d0e398c5be',
        'payment_method_title' => 'MBWAY',
        'items' => array(
            array( 'product_id' => 56330, 'sku' => 'KT1941', 'name' => 'Compressor de Molas MacPherson Kroftools 1941 80-195mm 453mm', 'quantity' => 1, 'net' => '104.07', 'tax' => '23.93' ),
        ),
        'shipping_title' => 'ENTREGA GRATUITA*',
        'shipping_total' => '0.00',
        'total_tax' => '23.93',
        'order_total' => '128.00',
    ),
);

$errors   = array();
$updated  = 0;

foreach ( $orders as $order_id => $data ) {
    $archive_key = (string) $data['archive_key'];
    $order       = CVLOA_Archive::read_order( $archive_key );

    if ( ! is_array( $order ) ) {
        $errors[] = "#{$order_id}: não foi possível ler {$archive_key}.";
        continue;
    }

    $line_items = array();

    foreach ( (array) $data['items'] as $item_data ) {
        $quantity = max( 1, absint( $item_data['quantity'] ) );

        $line_items[] = array(
            'id'           => 0,
            'name'         => (string) $item_data['name'],
            'product_id'   => absint( $item_data['product_id'] ),
            'variation_id' => 0,
            'quantity'     => $quantity,
            'tax_class'    => '',
            'subtotal'     => (string) $item_data['net'],
            'subtotal_tax' => (string) $item_data['tax'],
            'total'        => (string) $item_data['net'],
            'total_tax'    => (string) $item_data['tax'],
            'taxes'        => array(),
            'sku'          => (string) $item_data['sku'],
            'price'        => wc_format_decimal( (float) $item_data['net'] / $quantity, wc_get_price_decimals() ),
            'meta_data'    => array(
                array(
                    'key'   => '_cv_recovered_sku',
                    'value' => (string) $item_data['sku'],
                ),
                array(
                    'key'   => '_cv_recovered_from_email',
                    'value' => (string) $data['email_id'],
                ),
            ),
        );
    }

    $order['line_items'] = $line_items;

    $order['shipping_lines'] = array(
        array(
            'id'           => 0,
            'method_title' => (string) $data['shipping_title'],
            'method_id'    => 'cv-email-recovered',
            'instance_id'  => '',
            'total'        => (string) $data['shipping_total'],
            'total_tax'    => '0.00',
            'taxes'        => array(),
            'meta_data'    => array(
                array(
                    'key'   => '_cv_recovered_from_email',
                    'value' => (string) $data['email_id'],
                ),
            ),
        ),
    );

    if ( ! empty( $data['fee_name'] ) ) {
        $order['fee_lines'] = array(
            array(
                'id'         => 0,
                'name'       => (string) $data['fee_name'],
                'tax_class'  => '',
                'tax_status' => 'taxable',
                'total'      => (string) $data['fee_net'],
                'total_tax'  => (string) $data['fee_tax'],
                'taxes'      => array(),
                'meta_data'  => array(
                    array(
                        'key'   => '_cv_recovered_from_email',
                        'value' => (string) $data['email_id'],
                    ),
                ),
            ),
        );
    }

    if ( array_key_exists( 'payment_method_title', $data ) ) {
        $order['payment_method_title'] = (string) $data['payment_method_title'];
    }

    if ( array_key_exists( 'customer_note', $data ) ) {
        $order['customer_note'] = (string) $data['customer_note'];
    }

    $order['shipping_total'] = (string) $data['shipping_total'];
    $order['shipping_tax']   = '0.00';
    $order['total_tax']      = (string) $data['total_tax'];
    $order['total']          = (string) $data['order_total'];
    $order['_cvloa_archive_key'] = $archive_key;
    $order['_cvloa_doc_reconciled'] = array(
        'document_id'   => '15AKFFgU0JenFSeeQg7v-tw2FIcX9-nqqW1hjc-wReyQ',
        'email_id'      => (string) $data['email_id'],
        'reconciled_at' => gmdate( 'c' ),
    );

    $result = CVLOA_Archive::archive_batch( array( $order ), true );

    if ( is_wp_error( $result ) ) {
        $errors[] = "#{$order_id}: " . $result->get_error_message();
        continue;
    }

    $check = CVLOA_Archive::read_order( $archive_key );

    if ( ! is_array( $check ) ) {
        $errors[] = "#{$order_id}: falhou a releitura depois da atualização.";
        continue;
    }

    if ( count( (array) ( $check['line_items'] ?? array() ) ) !== count( $data['items'] ) ) {
        $errors[] = "#{$order_id}: produtos não correspondem ao documento.";
        continue;
    }

    if ( empty( $check['shipping_lines'] ) ) {
        $errors[] = "#{$order_id}: envio ficou em falta.";
        continue;
    }

    if ( abs( (float) ( $check['total'] ?? 0 ) - (float) $data['order_total'] ) > 0.01 ) {
        $errors[] = "#{$order_id}: total final não corresponde ao documento.";
        continue;
    }

    $updated++;

    echo sprintf(
        "updated-archive-order=%d;key=%s;items=%d;shipping=%s;total=%s\n",
        $order_id,
        $archive_key,
        count( $data['items'] ),
        $data['shipping_title'],
        $data['order_total']
    );
}

if ( $errors ) {
    foreach ( $errors as $error ) {
        fwrite( STDERR, $error . "\n" );
    }
    exit( 1 );
}

update_option(
    $marker,
    array(
        'applied_at' => gmdate( 'c' ),
        'updated'    => $updated,
        'skipped_without_source' => array( 1674803, 1674657 ),
    ),
    false
);

echo sprintf(
    "archive-doc-selection=success;updated=%d;unsupported=2\n",
    $updated
);
