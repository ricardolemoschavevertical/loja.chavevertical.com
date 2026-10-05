<?php
/**
 * Recover the next most recent damaged WooCommerce orders from the consolidated
 * Gmail order summary. Idempotent and limited to orders with no line items.
 */

defined( 'ABSPATH' ) || exit;

$marker = 'cv_repair_live_orders_gmail_next_recent_20261005_v1';

if ( get_option( $marker ) ) {
    echo "next-recent-order-recovery=already-applied\n";
    return;
}

$orders = array(
    1676580 => array(
        'email_id' => '1a0c912c1db7e0b5',
        'items' => array(
            array( 'product_id' => 1675397, 'sku' => 'SOLT10733', 'name' => 'CARRO UTS 1000 SOLDADURA SOLTER', 'quantity' => 1, 'net' => '127.50', 'tax' => '29.33' ),
            array( 'product_id' => 1566563, 'sku' => 'ST10165', 'name' => 'MESA DE SOLDADURA 900X600MM C/ ORIFÍCIOS DE 16MM SOLTER', 'quantity' => 1, 'net' => '480.00', 'tax' => '110.40' ),
        ),
        'shipping_title' => 'ENTREGA GRATUITA*', 'shipping_total' => '0.00', 'cart_tax' => '139.73', 'order_total' => '747.23',
    ),
    1676012 => array(
        'email_id' => '1a0c5b1d502a334b',
        'items' => array(
            array( 'product_id' => 1518361, 'sku' => 'JBM53904', 'name' => 'Carrinho de Ferramentas JBM 7 Gavetas Verde com 172 Peças', 'quantity' => 1, 'net' => '400.00', 'tax' => '92.00' ),
        ),
        'shipping_title' => 'Envio grátis (Transporte marítimo é da responsabilidade do cliente)', 'shipping_total' => '0.00', 'cart_tax' => '92.00', 'order_total' => '492.00',
    ),
    1676001 => array(
        'email_id' => '1a0c472ff1534c96',
        'items' => array(
            array( 'product_id' => 57304, 'sku' => 'KT9730', 'name' => 'Aspirador de Óleo com Visor e Aparadeira KROFTOOLS 90L 10L', 'quantity' => 1, 'net' => '274.80', 'tax' => '63.20' ),
        ),
        'shipping_title' => 'ENTREGA GRATUITA*', 'shipping_total' => '0.00', 'cart_tax' => '63.20', 'order_total' => '338.00',
    ),
    1675998 => array(
        'email_id' => '1a0c4245556fcdc9',
        'items' => array(
            array( 'product_id' => 115732, 'sku' => 'HZMRSM710', 'name' => 'Roda Inglesa HOLZMANN RSM710 710 mm', 'quantity' => 1, 'net' => '699.19', 'tax' => '160.81' ),
            array( 'product_id' => 1597903, 'sku' => 'ABT441220000', 'name' => 'Torno de Bancada FORTEX FTX-520X200 550W 230V', 'quantity' => 1, 'net' => '1622.76', 'tax' => '373.24' ),
            array( 'product_id' => 1505603, 'sku' => 'VKPFM012', 'name' => 'Mini-Quinadeira para Torno VKP 300 mm 1,2 mm', 'quantity' => 1, 'net' => '175.61', 'tax' => '40.39' ),
            array( 'product_id' => 129393, 'sku' => 'OPTI3241007', 'name' => 'Guilhotina Manual OPTIMUM PS 150 115mm', 'quantity' => 1, 'net' => '197.56', 'tax' => '45.44' ),
            array( 'product_id' => 89717, 'sku' => 'POWERED260100', 'name' => 'Prensa Hidropneumática POWERED PPSP12 12 T', 'quantity' => 1, 'net' => '576.42', 'tax' => '132.58' ),
            array( 'product_id' => 137982, 'sku' => 'MTK4101115', 'name' => 'Conjunto de 6 Mandris com Placa Furada METALLKRAFT para WPP 15 T', 'quantity' => 1, 'net' => '189.43', 'tax' => '43.57' ),
            array( 'product_id' => 95869, 'sku' => '1.064-912.0', 'name' => 'Lavadora de Alta Pressão a Quente KARCHER HDS 5/15 U 150 bar 2.7 kW', 'quantity' => 2, 'net' => '4450.41', 'tax' => '1023.59' ),
            array( 'product_id' => 1599561, 'sku' => 'AIRC2022855', 'name' => 'Compressor de Pistão Insonorizado AIRCRAFT SILENT 903/15 7,5 kW', 'quantity' => 2, 'net' => '10478.05', 'tax' => '2409.95' ),
        ),
        'shipping_title' => 'ENTREGA GRATUITA*', 'shipping_total' => '0.00', 'cart_tax' => '4229.57', 'order_total' => '22619.00',
    ),
    1675928 => array(
        'email_id' => '1a0c3594775bea52',
        'items' => array(
            array( 'product_id' => 125162, 'sku' => 'SOD08612', 'name' => 'Depósito para Gasóleo STILKER 200L 12V 40L/min Polietileno', 'quantity' => 1, 'net' => '308.94', 'tax' => '71.06' ),
        ),
        'shipping_title' => 'ENTREGA GRATUITA*', 'shipping_total' => '0.00', 'cart_tax' => '71.06', 'order_total' => '380.00',
    ),
    1674910 => array(
        'email_id' => '1a0b528af7984e07',
        'items' => array(
            array( 'product_id' => 86727, 'sku' => 'POWERED240152', 'name' => 'Dispensador de Diesel POWERED 230V 60 L/min com Contador e Pistola Automática', 'quantity' => 1, 'net' => '308.13', 'tax' => '70.87' ),
        ),
        'shipping_title' => 'ENTREGA GRATUITA*', 'shipping_total' => '0.00', 'cart_tax' => '70.87', 'order_total' => '379.00',
    ),
    1674907 => array(
        'email_id' => '1a0b4ba33792353e',
        'items' => array(
            array( 'product_id' => 1545636, 'sku' => 'SOD72517', 'name' => 'Carro de Ferramentas STILKER 7 Gavetas 205KG Azul/Cinza', 'quantity' => 1, 'net' => '199.19', 'tax' => '45.81' ),
        ),
        'shipping_title' => 'ENTREGA GRATUITA*', 'shipping_total' => '0.00', 'cart_tax' => '45.81', 'order_total' => '245.00',
    ),
    1674906 => array(
        'email_id' => '1a0b494572ece210',
        'items' => array(
            array( 'product_id' => 1523839, 'sku' => 'POWERED202679', 'name' => 'Bandeira extensível para guincho Powered com capacidade 600 kg (750 mm) / 300 kg (1100 mm)', 'quantity' => 1, 'net' => '55.28', 'tax' => '12.72' ),
        ),
        'shipping_title' => 'ENVIO (Portugal Continental)', 'shipping_total' => '7.50', 'cart_tax' => '12.72', 'order_total' => '75.50',
    ),
    1674904 => array(
        'email_id' => '1a0b44e030488365',
        'items' => array(
            array( 'product_id' => 56366, 'sku' => 'KT2084', 'name' => 'Enrolador de Ar Comprimido KROFTOOLS 15m 8x12mm 150 PSI', 'quantity' => 1, 'net' => '51.22', 'tax' => '11.78' ),
            array( 'product_id' => 1575477, 'sku' => 'KT4851', 'name' => 'Macaco Balão Pneumático 3T KROFTOOLS 145-400mm 8-12 BAR', 'quantity' => 1, 'net' => '95.12', 'tax' => '21.88' ),
        ),
        'shipping_title' => 'ENTREGA GRATUITA*', 'shipping_total' => '0.00', 'cart_tax' => '33.66', 'order_total' => '180.00',
    ),
    1674901 => array(
        'email_id' => '1a0b3b43c0770642',
        'items' => array(
            array( 'product_id' => 125162, 'sku' => 'SOD08612', 'name' => 'Depósito para Gasóleo STILKER 200L 12V 40L/min Polietileno', 'quantity' => 1, 'net' => '308.94', 'tax' => '71.06' ),
        ),
        'shipping_title' => 'Envio grátis (Transporte marítimo é da responsabilidade do cliente)', 'shipping_total' => '0.00', 'cart_tax' => '71.06', 'order_total' => '380.00',
    ),
    1674898 => array(
        'email_id' => '1a0b1077858e27b3',
        'items' => array(
            array( 'product_id' => 56366, 'sku' => 'KT2084', 'name' => 'Enrolador de Ar Comprimido KROFTOOLS 15m 8x12mm 150 PSI', 'quantity' => 1, 'net' => '51.22', 'tax' => '11.78' ),
        ),
        'shipping_title' => 'ENVIO (Portugal Continental)', 'shipping_total' => '7.50', 'cart_tax' => '11.78', 'order_total' => '70.50',
    ),
    1674804 => array(
        'email_id' => '1a0afc8ea44bbfa9',
        'items' => array(
            array( 'product_id' => 1537208, 'sku' => 'SOD08470', 'name' => 'Bacia de Retenção DRAKKAR 1100L PE 1 IBC 1500kg', 'quantity' => 1, 'net' => '686.99', 'tax' => '158.01' ),
        ),
        'shipping_title' => 'ENTREGA GRATUITA*', 'shipping_total' => '0.00',
        'fee_name' => 'Pagamento na entrega', 'fee_net' => '5.00', 'fee_tax' => '1.15',
        'cart_tax' => '159.16', 'order_total' => '851.15',
    ),
    1674779 => array(
        'email_id' => '1a0ab26202d5298b',
        'items' => array(
            array( 'product_id' => 1620820, 'sku' => 'SOD71525', 'name' => 'Kit de Sincronização STILKER para VAG 1.4-1.6-2.0 CR TDI', 'quantity' => 1, 'net' => '25.20', 'tax' => '5.80' ),
        ),
        'shipping_title' => 'ENVIO (Portugal Continental)', 'shipping_total' => '7.50', 'cart_tax' => '5.80', 'order_total' => '38.50',
    ),
    1674654 => array(
        'email_id' => '1a09c824a8fd2c08',
        'items' => array(
            array( 'product_id' => 117146, 'sku' => 'MEGA-5298', 'name' => 'Balancé de Cargas MEGA 500 KG', 'quantity' => 1, 'net' => '229.27', 'tax' => '52.73' ),
        ),
        'shipping_title' => 'ENTREGA GRATUITA*', 'shipping_total' => '0.00', 'cart_tax' => '52.73', 'order_total' => '282.00',
    ),
    1674652 => array(
        'email_id' => '1a096536419697c6',
        'items' => array(
            array( 'product_id' => 40974, 'sku' => 'HZMABS120MM_4LFM', 'name' => 'Mangueira de Aspiração HOLZMANN Ø 120mm 4m', 'quantity' => 1, 'net' => '59.35', 'tax' => '13.65' ),
        ),
        'shipping_title' => 'ENVIO (Portugal Continental)', 'shipping_total' => '7.50', 'cart_tax' => '13.65', 'order_total' => '80.50',
    ),
    1674650 => array(
        'email_id' => '1a095673f53599e6',
        'items' => array(
            array( 'product_id' => 57011, 'sku' => 'KT7015', 'name' => 'Despolidor de Cilindros Kroftools 32-89mm Pedras 50mm', 'quantity' => 1, 'net' => '13.01', 'tax' => '2.99' ),
        ),
        'shipping_title' => 'ENVIO (Portugal Continental)', 'shipping_total' => '7.50', 'cart_tax' => '2.99', 'order_total' => '23.50',
    ),
    1674646 => array(
        'email_id' => '1a0925884e5a9662',
        'items' => array(
            array( 'product_id' => 102685, 'sku' => 'JBM51266', 'name' => 'Cinto de Segurança de 3 Pontos Enrolável para Autocarros JBM 51266 2900mm 1.13Kg', 'quantity' => 1, 'net' => '24.39', 'tax' => '5.61' ),
        ),
        'shipping_title' => 'ENVIO (Portugal Continental)', 'shipping_total' => '7.50', 'cart_tax' => '5.61', 'order_total' => '37.50',
    ),
    1674590 => array(
        'email_id' => '1a0909e892af92c9',
        'items' => array(
            array( 'product_id' => 104869, 'sku' => 'JBM54433', 'name' => 'Kit de Sincronização PSA 1.0-2.0 JBM 7 Peças Multimarca', 'quantity' => 1, 'net' => '15.45', 'tax' => '3.55' ),
        ),
        'shipping_title' => 'ENVIO (Portugal Continental)', 'shipping_total' => '7.50', 'cart_tax' => '3.55', 'order_total' => '26.50',
    ),
    1674044 => array(
        'email_id' => '1a08c10f74e135fd',
        'items' => array(
            array( 'product_id' => 97782, 'sku' => 'POWERED354923', 'name' => 'FITA SERRA 2090x20 5/8 POWERED', 'quantity' => 2, 'net' => '48.00', 'tax' => '11.04' ),
        ),
        'shipping_title' => 'ENVIO (Portugal Continental)', 'shipping_total' => '7.50', 'cart_tax' => '11.04', 'order_total' => '66.54',
    ),
    1674036 => array(
        'email_id' => '1a08bef65762810a',
        'items' => array(
            array( 'product_id' => 1640789, 'sku' => 'KT3281', 'name' => 'Bomba Manual de Transfega KROFTOOLS 32L/min para Bidões 50/200L', 'quantity' => 1, 'net' => '22.76', 'tax' => '5.24' ),
        ),
        'shipping_title' => 'ENVIO (Portugal Continental)', 'shipping_total' => '7.50', 'cart_tax' => '5.24', 'order_total' => '35.50',
    ),
);

$errors   = array();
$repaired = 0;
$skipped  = 0;

foreach ( $orders as $order_id => $data ) {
    $order = wc_get_order( $order_id );

    if ( ! $order instanceof WC_Order ) {
        $errors[] = "#{$order_id}: encomenda não encontrada.";
        continue;
    }

    if ( $order->get_meta( '_cv_gmail_next_recent_recovery_v1', true ) ) {
        $skipped++;
        continue;
    }

    if ( count( $order->get_items( 'line_item' ) ) > 0 ) {
        $order->update_meta_data( '_cv_gmail_next_recent_recovery_v1', 'skipped-existing-lines:' . gmdate( 'c' ) );
        $order->save();
        $skipped++;
        echo "skipped-existing-lines={$order_id}\n";
        continue;
    }

    foreach ( (array) $data['items'] as $item_data ) {
        $product = wc_get_product( absint( $item_data['product_id'] ) );

        if ( ! $product instanceof WC_Product ) {
            $errors[] = "#{$order_id}: produto {$item_data['sku']} não encontrado.";
            continue 2;
        }

        if ( 0 !== strcasecmp( (string) $product->get_sku(), (string) $item_data['sku'] ) ) {
            $errors[] = "#{$order_id}: produto {$item_data['product_id']} não corresponde ao SKU {$item_data['sku']}.";
            continue 2;
        }

        $line = new WC_Order_Item_Product();
        $line->set_product( $product );
        $line->set_name( (string) $item_data['name'] );
        $line->set_quantity( absint( $item_data['quantity'] ) );
        $line->set_subtotal( wc_format_decimal( $item_data['net'] ) );
        $line->set_total( wc_format_decimal( $item_data['net'] ) );
        $line->set_subtotal_tax( wc_format_decimal( $item_data['tax'] ) );
        $line->set_total_tax( wc_format_decimal( $item_data['tax'] ) );
        $line->add_meta_data( '_cv_recovered_sku', (string) $item_data['sku'], true );
        $line->add_meta_data( '_cv_recovered_from_email', (string) $data['email_id'], true );
        $order->add_item( $line );
    }

    foreach ( $order->get_items( 'shipping' ) as $shipping_item ) {
        $order->remove_item( $shipping_item->get_id() );
    }

    $shipping = new WC_Order_Item_Shipping();
    $shipping->set_method_title( (string) $data['shipping_title'] );
    $shipping->set_method_id( 'cv-email-recovered' );
    $shipping->set_total( wc_format_decimal( $data['shipping_total'] ) );
    $shipping->set_taxes( array( 'total' => array() ) );
    $shipping->add_meta_data( '_cv_recovered_from_email', (string) $data['email_id'], true );
    $order->add_item( $shipping );

    if ( ! empty( $data['fee_name'] ) ) {
        $fee_exists = false;

        foreach ( $order->get_items( 'fee' ) as $fee_item ) {
            if ( 0 === strcasecmp( (string) $fee_item->get_name(), (string) $data['fee_name'] ) ) {
                $fee_exists = true;
                $fee_item->set_total( wc_format_decimal( $data['fee_net'] ) );
                $fee_item->set_total_tax( wc_format_decimal( $data['fee_tax'] ) );
                $fee_item->save();
                break;
            }
        }

        if ( ! $fee_exists ) {
            $fee = new WC_Order_Item_Fee();
            $fee->set_name( (string) $data['fee_name'] );
            $fee->set_tax_status( 'taxable' );
            $fee->set_total( wc_format_decimal( $data['fee_net'] ) );
            $fee->set_total_tax( wc_format_decimal( $data['fee_tax'] ) );
            $fee->add_meta_data( '_cv_recovered_from_email', (string) $data['email_id'], true );
            $order->add_item( $fee );
        }
    }

    $order->set_shipping_total( wc_format_decimal( $data['shipping_total'] ) );
    $order->set_shipping_tax( '0' );
    $order->set_cart_tax( wc_format_decimal( $data['cart_tax'] ) );
    $order->set_total( wc_format_decimal( $data['order_total'] ) );
    $order->update_meta_data( '_cv_gmail_recovery_source', 'google-doc:15AKFFgU0JenFSeeQg7v-tw2FIcX9-nqqW1hjc-wReyQ' );
    $order->update_meta_data( '_cv_gmail_recovery_email_id', (string) $data['email_id'] );
    $order->save();

    $check = wc_get_order( $order_id );

    if ( ! $check instanceof WC_Order ) {
        $errors[] = "#{$order_id}: não foi possível reler a encomenda.";
        continue;
    }

    if ( count( $check->get_items( 'line_item' ) ) !== count( $data['items'] ) ) {
        $errors[] = "#{$order_id}: linhas recuperadas não correspondem ao documento.";
        continue;
    }

    if ( empty( $check->get_items( 'shipping' ) ) ) {
        $errors[] = "#{$order_id}: envio não foi recuperado.";
        continue;
    }

    if ( abs( (float) $check->get_total() - (float) $data['order_total'] ) > 0.01 ) {
        $errors[] = "#{$order_id}: total não corresponde ao documento.";
        continue;
    }

    $expected_skus = array_map( static fn( array $row ): string => (string) $row['sku'], (array) $data['items'] );
    $actual_skus   = array();

    foreach ( $check->get_items( 'line_item' ) as $line ) {
        $product = $line->get_product();
        $actual_skus[] = $product instanceof WC_Product
            ? (string) $product->get_sku()
            : (string) $line->get_meta( '_cv_recovered_sku', true );
    }

    sort( $expected_skus );
    sort( $actual_skus );

    if ( $expected_skus !== $actual_skus ) {
        $errors[] = "#{$order_id}: SKU recuperado não corresponde ao documento.";
        continue;
    }

    $check->update_meta_data( '_cv_gmail_next_recent_recovery_v1', gmdate( 'c' ) );
    $check->save();

    $repaired++;
    echo sprintf(
        "repaired-order=%d;items=%d;shipping=%s;total=%s\n",
        $order_id,
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
        'repaired'   => $repaired,
        'skipped'    => $skipped,
    ),
    false
);

echo sprintf(
    "next-recent-order-recovery=success;repaired=%d;skipped=%d\n",
    $repaired,
    $skipped
);
