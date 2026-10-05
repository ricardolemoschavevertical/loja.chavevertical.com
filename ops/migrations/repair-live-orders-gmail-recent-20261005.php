<?php
/**
 * Recover the most recent damaged WooCommerce orders from the consolidated
 * Gmail order summary.
 *
 * Safety:
 * - only orders with missing line items are repaired;
 * - order status is never changed;
 * - no stock reduction/restock is triggered;
 * - no transactional email is sent;
 * - historical totals are restored from the original WooCommerce email.
 */

defined( 'ABSPATH' ) || exit;

$marker = 'cv_repair_live_orders_gmail_recent_20261005_v1';

if ( get_option( $marker ) ) {
    echo "recent-order-recovery=already-applied\n";
    return;
}

$orders = array(
    1677559 => array(
        'email_id'       => '1a0d3ce408ce1bb2',
        'items'          => array(
            array(
                'product_id' => 101409,
                'sku'        => 'JBM53308',
                'name'       => 'Kit de Bloqueio de Distribuição Alfa Romeo Twin Spark JBM',
                'quantity'   => 1,
                'net'        => '44.72',
                'tax'        => '10.28',
            ),
        ),
        'shipping_title' => 'ENVIO (Portugal Continental)',
        'shipping_total' => '7.50',
        'cart_tax'       => '10.28',
        'order_total'    => '62.50',
    ),
    1677556 => array(
        'email_id'       => '1a0d38b9fc438187',
        'items'          => array(
            array(
                'product_id' => 1661492,
                'sku'        => 'ZI-BAS205',
                'name'       => 'Serra de Fita para Madeira ZIPPER BAS205 250W 230V',
                'quantity'   => 1,
                'net'        => '109.76',
                'tax'        => '25.24',
            ),
        ),
        'shipping_title' => 'ENTREGA GRATUITA*',
        'shipping_total' => '0.00',
        'cart_tax'       => '25.24',
        'order_total'    => '135.00',
    ),
    1677555 => array(
        'email_id'       => '1a0d37a3621226b5',
        'items'          => array(
            array(
                'product_id' => 1661492,
                'sku'        => 'ZI-BAS205',
                'name'       => 'Serra de Fita para Madeira ZIPPER BAS205 250W 230V',
                'quantity'   => 1,
                'net'        => '109.76',
                'tax'        => '25.24',
            ),
        ),
        'shipping_title' => 'ENTREGA GRATUITA*',
        'shipping_total' => '0.00',
        'cart_tax'       => '25.24',
        'order_total'    => '135.00',
    ),
    1677464 => array(
        'email_id'       => '1a0d2d0142776167',
        'items'          => array(
            array(
                'product_id' => 125162,
                'sku'        => 'SOD08612',
                'name'       => 'Depósito para Gasóleo STILKER 200L 12V 40L/min Polietileno',
                'quantity'   => 1,
                'net'        => '308.94',
                'tax'        => '71.06',
            ),
        ),
        'shipping_title' => 'ENTREGA GRATUITA*',
        'shipping_total' => '0.00',
        'cart_tax'       => '71.06',
        'order_total'    => '380.00',
    ),
    1677442 => array(
        'email_id'       => '1a0d0c358995f481',
        'items'          => array(
            array(
                'product_id' => 41472,
                'sku'        => 'HZMWB126',
                'name'       => 'Bancada de Trabalho em Madeira HOLZMANN WB126',
                'quantity'   => 1,
                'net'        => '169.11',
                'tax'        => '38.89',
            ),
        ),
        'shipping_title' => 'ENTREGA GRATUITA*',
        'shipping_total' => '0.00',
        'cart_tax'       => '38.89',
        'order_total'    => '208.00',
    ),
    1677317 => array(
        'email_id'       => '1a0ceefab370d053',
        'items'          => array(
            array(
                'product_id' => 1519961,
                'sku'        => 'A11013030',
                'name'       => 'Escada de Alumínio Tripla LIT 3m Degrau Quadrado Reforçada',
                'quantity'   => 1,
                'net'        => '193.50',
                'tax'        => '44.50',
            ),
        ),
        'shipping_title' => 'ENTREGA GRATUITA*',
        'shipping_total' => '0.00',
        'cart_tax'       => '44.50',
        'order_total'    => '238.00',
    ),
    1677282 => array(
        'email_id'       => '1a0ce2ed489e7546',
        'items'          => array(
            array(
                'product_id' => 1598197,
                'sku'        => 'SOD56852',
                'name'       => 'Bomba de Massa 18V STILKER 690 Bar 2 Baterias',
                'quantity'   => 1,
                'net'        => '128.46',
                'tax'        => '29.54',
            ),
        ),
        'shipping_title' => 'ENTREGA GRATUITA*',
        'shipping_total' => '0.00',
        'cart_tax'       => '29.54',
        'order_total'    => '158.00',
    ),
    1677281 => array(
        'email_id'       => '1a0ce193992a1085',
        'items'          => array(
            array(
                'product_id' => 1534208,
                'sku'        => 'KINGT853421M',
                'name'       => 'CHAVE DE IMPACTO LONGA PARA RODAS QUADRADO INTERNO 21MM 1\' KING TONY',
                'quantity'   => 1,
                'net'        => '28.00',
                'tax'        => '6.44',
            ),
        ),
        'shipping_title' => 'ENVIO (Portugal Continental)',
        'shipping_total' => '7.50',
        'fee_name'       => 'Pagamento na entrega',
        'fee_net'        => '5.00',
        'fee_tax'        => '1.15',
        'cart_tax'       => '7.59',
        'order_total'    => '48.09',
    ),
    1676630 => array(
        'email_id'       => '1a0c9bd08cdd48c5',
        'items'          => array(
            array(
                'product_id' => 1566368,
                'sku'        => 'HU-ACBF20SGWT1800',
                'name'       => 'Porta-paletes de garfos compridos HU-LIFT ACBF20 2000kg 1800mm',
                'quantity'   => 1,
                'net'        => '450.41',
                'tax'        => '103.59',
            ),
        ),
        'shipping_title' => 'ENTREGA GRATUITA*',
        'shipping_total' => '0.00',
        'cart_tax'       => '103.59',
        'order_total'    => '554.00',
    ),
);

$errors   = array();
$repaired = 0;
$skipped  = 0;

foreach ( $orders as $order_id => $data ) {
    $order = wc_get_order( $order_id );

    if ( ! $order instanceof WC_Order ) {
        /*
         * Estas encomendas podem já existir apenas no arquivo histórico.
         * A ausência no WooCommerce ativo não é um erro de deploy.
         */
        $skipped++;
        echo "skipped-missing-live-order={$order_id}\n";
        continue;
    }

    if ( $order->get_meta( '_cv_gmail_recent_recovery_v1', true ) ) {
        $skipped++;
        continue;
    }

    /*
     * Never overwrite an order that already has products. This migration is
     * deliberately limited to the damaged historical records identified above.
     */
    if ( count( $order->get_items( 'line_item' ) ) > 0 ) {
        $order->update_meta_data( '_cv_gmail_recent_recovery_v1', 'skipped-existing-lines:' . gmdate( 'c' ) );
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
            $errors[] = "#{$order_id}: produto {$item_data['product_id']} já não corresponde ao SKU {$item_data['sku']}.";
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

    $shipping_items = $order->get_items( 'shipping' );

    if ( $shipping_items ) {
        foreach ( $shipping_items as $shipping_item ) {
            $order->remove_item( $shipping_item->get_id() );
        }
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

    // Restore historical totals exactly; do not recalculate against current tax
    // rules or catalogue prices.
    $order->set_shipping_total( wc_format_decimal( $data['shipping_total'] ) );
    $order->set_shipping_tax( '0' );
    $order->set_cart_tax( wc_format_decimal( $data['cart_tax'] ) );
    $order->set_total( wc_format_decimal( $data['order_total'] ) );
    $order->update_meta_data( '_cv_gmail_recovery_source', 'google-doc:15AKFFgU0JenFSeeQg7v-tw2FIcX9-nqqW1hjc-wReyQ' );
    $order->update_meta_data( '_cv_gmail_recovery_email_id', (string) $data['email_id'] );
    $order->save();

    clean_post_cache( $order_id );

    $check = wc_get_order( $order_id );

    if ( ! $check instanceof WC_Order ) {
        $errors[] = "#{$order_id}: não foi possível reler a encomenda.";
        continue;
    }

    $check_lines = $check->get_items( 'line_item' );
    $check_ship  = $check->get_items( 'shipping' );

    if ( count( $check_lines ) !== count( $data['items'] ) ) {
        $errors[] = "#{$order_id}: número de linhas recuperadas não corresponde ao documento.";
        continue;
    }

    if ( empty( $check_ship ) ) {
        $errors[] = "#{$order_id}: envio não foi recuperado.";
        continue;
    }

    if ( abs( (float) $check->get_total() - (float) $data['order_total'] ) > 0.01 ) {
        $errors[] = "#{$order_id}: total final não corresponde ao documento.";
        continue;
    }

    $expected_skus = array_map(
        static fn( array $row ): string => (string) $row['sku'],
        (array) $data['items']
    );

    $actual_skus = array();
    foreach ( $check_lines as $line ) {
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

    $check->update_meta_data( '_cv_gmail_recent_recovery_v1', gmdate( 'c' ) );
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
    "recent-order-recovery=success;repaired=%d;skipped=%d\n",
    $repaired,
    $skipped
);
