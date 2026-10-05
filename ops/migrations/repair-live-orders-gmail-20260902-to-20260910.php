<?php
/**
 * Recover damaged WooCommerce orders from 2026-09-02 through 2026-09-10
 * using the consolidated Gmail order summary as the source of truth.
 *
 * Only orders with no line items are changed.
 */

defined( 'ABSPATH' ) || exit;

$marker = 'cv_repair_live_orders_gmail_20260902_20260910_v1';

if ( get_option( $marker ) ) {
    echo "gmail-order-recovery-20260902-20260910=already-applied\n";
    return;
}

$orders = array(
    1674025 => array(
        'email_id' => '1a08bb409acbfee8',
        'items' => array(
            array( 'product_id' => 88448, 'sku' => 'POWERED276064', 'name' => 'Bomba de Água 220V 100W POWERED', 'quantity' => 1, 'net' => '154.47', 'tax' => '35.53' ),
        ),
        'shipping_title' => 'ENTREGA GRATUITA*', 'shipping_total' => '0.00', 'cart_tax' => '35.53', 'order_total' => '190.00',
    ),
    1673964 => array(
        'email_id' => '1a087e9a3d722dff',
        'items' => array(
            array( 'product_id' => 143842, 'sku' => 'SOD11020', 'name' => 'Gerador AVR Monofásico Gasolina DRAKKAR 5500W 13CV Arranque Elétrico', 'quantity' => 1, 'net' => '569.11', 'tax' => '130.89' ),
        ),
        'shipping_title' => 'ENTREGA GRATUITA*', 'shipping_total' => '0.00', 'cart_tax' => '130.89', 'order_total' => '700.00',
    ),
    1673914 => array(
        'email_id' => '1a0877d0e398c5be',
        'items' => array(
            array( 'product_id' => 56330, 'sku' => 'KT1941', 'name' => 'Compressor de Molas MacPherson Kroftools 1941 80-195mm 453mm', 'quantity' => 1, 'net' => '104.07', 'tax' => '23.93' ),
        ),
        'shipping_title' => 'ENTREGA GRATUITA*', 'shipping_total' => '0.00', 'cart_tax' => '23.93', 'order_total' => '128.00',
    ),
    1673873 => array(
        'email_id' => '1a086fe7ac0572e3',
        'items' => array(
            array( 'product_id' => 1549282, 'sku' => 'KT9802', 'name' => 'Elevador 2 Colunas 4T Basic-Line KROFTOOLS 4000kg Monofásico', 'quantity' => 1, 'net' => '1462.60', 'tax' => '336.40' ),
        ),
        'shipping_title' => 'ENVIO (Portugal Continental)', 'shipping_total' => '150.50',
        'fee_name' => 'Pagamento na entrega', 'fee_net' => '5.00', 'fee_tax' => '1.15',
        'cart_tax' => '337.55', 'order_total' => '1955.65',
    ),
    1673809 => array(
        'email_id' => '1a08287c5229d5b0',
        'items' => array(
            array( 'product_id' => 1620872, 'sku' => 'SOD71544', 'name' => 'Kit de Sincronização STILKER para VAG Gasolina 1.2 6V-1.2 12V', 'quantity' => 1, 'net' => '10.57', 'tax' => '2.43' ),
        ),
        'shipping_title' => 'ENVIO (Portugal Continental)', 'shipping_total' => '7.50', 'cart_tax' => '2.43', 'order_total' => '20.50',
    ),
    1673789 => array(
        'email_id' => '1a081c54e30859a4',
        'items' => array(
            array( 'product_id' => 41023, 'sku' => 'HZMABS2480_230V', 'name' => 'Aspirador de Aparas HOLZMANN ABS 2480', 'quantity' => 1, 'net' => '369.00', 'tax' => '84.87' ),
        ),
        'shipping_title' => 'ENTREGA GRATUITA*', 'shipping_total' => '0.00', 'cart_tax' => '84.87', 'order_total' => '453.87',
    ),
    1673788 => array(
        'email_id' => '1a08144dcc71ef80',
        'items' => array(
            array( 'product_id' => 102538, 'sku' => 'JBM52259', 'name' => 'Martelo Quebra-Vidros JBM com Suporte e Corta-Cinto 13.5cm', 'quantity' => 8, 'net' => '24.00', 'tax' => '5.52' ),
        ),
        'shipping_title' => 'ENVIO (Portugal Continental)', 'shipping_total' => '7.50', 'cart_tax' => '5.52', 'order_total' => '37.02',
    ),
    1672965 => array(
        'email_id' => '1a077e0c811b193c',
        'items' => array(
            array( 'product_id' => 74774, 'sku' => 'SIRLU.04500.1275', 'name' => 'Carro Armazém Reforçado com Báscula SIRL 200kg 3.50-4', 'quantity' => 1, 'net' => '49.59', 'tax' => '11.41' ),
        ),
        'shipping_title' => 'ENVIO (Portugal Continental)', 'shipping_total' => '7.50', 'cart_tax' => '11.41', 'order_total' => '68.50',
    ),
    1672687 => array(
        'email_id' => '1a071a16d3c76dda',
        'items' => array(
            array( 'product_id' => 56759, 'sku' => 'KT4865', 'name' => 'Grua Desdobrável Pneumática KROFTOOLS 2 Toneladas 2360mm', 'quantity' => 1, 'net' => '299.19', 'tax' => '68.81' ),
        ),
        'shipping_title' => 'ENTREGA GRATUITA*', 'shipping_total' => '0.00', 'cart_tax' => '68.81', 'order_total' => '368.00',
    ),
    1672588 => array(
        'email_id' => '1a06cd9aa3bddde2',
        'items' => array(
            array( 'product_id' => 125162, 'sku' => 'SOD08612', 'name' => 'Depósito para Gasóleo STILKER 200L 12V 40L/min Polietileno', 'quantity' => 1, 'net' => '308.94', 'tax' => '71.06' ),
        ),
        'shipping_title' => 'ENTREGA GRATUITA*', 'shipping_total' => '0.00', 'cart_tax' => '71.06', 'order_total' => '380.00',
    ),
    1672505 => array(
        'email_id' => '1a06c9564570bc04',
        'items' => array(
            array( 'product_id' => 1563829, 'sku' => 'SOP05226000000', 'name' => 'Contentor de Lixo em Polietileno 800L Elevação Ochsner e DIN', 'quantity' => 1, 'net' => '299.19', 'tax' => '68.81' ),
        ),
        'shipping_title' => 'ENTREGA GRATUITA*', 'shipping_total' => '0.00', 'cart_tax' => '68.81', 'order_total' => '368.00',
    ),
    1672278 => array(
        'email_id' => '1a068f762dc57893',
        'items' => array(
            array( 'product_id' => 1608395, 'sku' => 'SOD56130', 'name' => 'Bomba de Gasóleo Mural STILKER 230V 50 L/Min com Contador e Filtro', 'quantity' => 1, 'net' => '175.61', 'tax' => '40.39' ),
        ),
        'shipping_title' => 'ENTREGA GRATUITA*', 'shipping_total' => '0.00', 'cart_tax' => '40.39', 'order_total' => '216.00',
    ),
    1672276 => array(
        'email_id' => '1a0689c9a2566117',
        'items' => array(
            array( 'product_id' => 1519961, 'sku' => 'A11013030', 'name' => 'Escada de Alumínio Tripla LIT 3m Degrau Quadrado Reforçada', 'quantity' => 1, 'net' => '193.50', 'tax' => '44.50' ),
        ),
        'shipping_title' => 'ENTREGA GRATUITA*', 'shipping_total' => '0.00', 'cart_tax' => '44.50', 'order_total' => '238.00',
    ),
    1672275 => array(
        'email_id' => '1a06895433d4d01f',
        'items' => array(
            array( 'product_id' => 1519961, 'sku' => 'A11013030', 'name' => 'Escada de Alumínio Tripla LIT 3m Degrau Quadrado Reforçada', 'quantity' => 1, 'net' => '193.50', 'tax' => '44.50' ),
            array( 'product_id' => 1602342, 'sku' => 'LITA10200005', 'name' => 'ESCADA MULTIUSOS ALUMÍNIO 5,25M 4X5 LIT', 'quantity' => 1, 'net' => '142.00', 'tax' => '32.66' ),
        ),
        'shipping_title' => 'ENTREGA GRATUITA*', 'shipping_total' => '0.00', 'cart_tax' => '77.16', 'order_total' => '412.66',
    ),
    1672268 => array(
        'email_id' => '1a066bdde48930ea',
        'items' => array(
            array( 'product_id' => 125162, 'sku' => 'SOD08612', 'name' => 'Depósito para Gasóleo STILKER 200L 12V 40L/min Polietileno', 'quantity' => 1, 'net' => '308.94', 'tax' => '71.06' ),
        ),
        'shipping_title' => 'ENTREGA GRATUITA*', 'shipping_total' => '0.00', 'cart_tax' => '71.06', 'order_total' => '380.00',
    ),
    1672002 => array(
        'email_id' => '1a06354dfcee6353',
        'items' => array(
            array( 'product_id' => 56760, 'sku' => 'KT4867', 'name' => 'Grua Desdobrável Hidráulica KROFTOOLS 3 Toneladas 2400mm', 'quantity' => 1, 'net' => '399.19', 'tax' => '91.81' ),
        ),
        'shipping_title' => 'ENTREGA GRATUITA*', 'shipping_total' => '0.00', 'cart_tax' => '91.81', 'order_total' => '491.00',
    ),
    1671915 => array(
        'email_id' => '1a0626aa19805240',
        'items' => array(
            array( 'product_id' => 1483971, 'sku' => 'HOLZ5142501-2,5', 'name' => 'MANGUEIRA FLEXÍVEL DE POLIURETANO Ø 60MM / 2,5 HOLZSTAR', 'quantity' => 1, 'net' => '25.00', 'tax' => '5.75' ),
        ),
        'shipping_title' => 'ENVIO (Portugal Continental)', 'shipping_total' => '7.50', 'cart_tax' => '5.75', 'order_total' => '38.25',
    ),
    1671674 => array(
        'email_id' => '1a0623563559112a',
        'items' => array(
            array( 'product_id' => 1505705, 'sku' => 'VKPML37KH', 'name' => 'Elevador de Motos Hidráulico VKP 360 kg 1345x490 mm', 'quantity' => 1, 'net' => '405.69', 'tax' => '93.31' ),
        ),
        'shipping_title' => 'ENTREGA GRATUITA*', 'shipping_total' => '0.00', 'cart_tax' => '93.31', 'order_total' => '499.00',
    ),
    1671673 => array(
        'email_id' => '1a0621986a49b934',
        'items' => array(
            array( 'product_id' => 56760, 'sku' => 'KT4867', 'name' => 'Grua Desdobrável Hidráulica KROFTOOLS 3 Toneladas 2400mm', 'quantity' => 1, 'net' => '399.19', 'tax' => '91.81' ),
        ),
        'shipping_title' => 'ENTREGA GRATUITA*', 'shipping_total' => '0.00', 'cart_tax' => '91.81', 'order_total' => '491.00',
    ),
    1671105 => array(
        'email_id' => '1a06187bbd9f3d98',
        'items' => array(
            array( 'product_id' => 1563432, 'sku' => 'ND6005', 'name' => 'CARRO PLATAFORMA MINI REBATÍVEL ND', 'quantity' => 2, 'net' => '128.46', 'tax' => '29.54' ),
        ),
        'shipping_title' => 'ENTREGA GRATUITA*', 'shipping_total' => '0.00', 'cart_tax' => '29.54', 'order_total' => '158.00',
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

    if ( $order->get_meta( '_cv_gmail_recovery_20260902_20260910_v1', true ) ) {
        $skipped++;
        continue;
    }

    if ( count( $order->get_items( 'line_item' ) ) > 0 ) {
        $order->update_meta_data( '_cv_gmail_recovery_20260902_20260910_v1', 'skipped-existing-lines:' . gmdate( 'c' ) );
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

    $check->update_meta_data( '_cv_gmail_recovery_20260902_20260910_v1', gmdate( 'c' ) );
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
    "gmail-order-recovery-20260902-20260910=success;repaired=%d;skipped=%d\n",
    $repaired,
    $skipped
);
