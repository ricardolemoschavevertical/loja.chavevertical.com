<?php
/**
 * One-off recovery of damaged historical orders from the original WooCommerce
 * "Nova encomenda" emails in Gmail.
 *
 * Scope: page 2 reviewed on 2026-10-05. Only orders whose line items were
 * missing are touched. No status changes, no stock reduction and no emails.
 */

defined( 'ABSPATH' ) || exit;

$marker = 'cv_email_order_recovery_page2_20261005_v1';

if ( get_option( $marker ) ) {
    echo "email-order-recovery-page2=already-applied\n";
    return;
}

$orders = array(
    1677559 => array(
        'email_id'       => '1a0d3ce408ce1bb2',
        'product_id'     => 101409,
        'sku'            => 'JBM53308',
        'name'           => 'Kit de Bloqueio de Distribuição Alfa Romeo Twin Spark JBM',
        'quantity'       => 1,
        'line_total'     => '44.72',
        'line_tax'       => '10.28',
        'shipping_title' => 'ENVIO (Portugal Continental)',
        'shipping_total' => '7.50',
        'cart_tax'       => '10.28',
        'order_total'    => '62.50',
    ),
    1677556 => array(
        'email_id'       => '1a0d38b9fc438187',
        'product_id'     => 1661492,
        'sku'            => 'ZI-BAS205',
        'name'           => 'Serra de Fita para Madeira ZIPPER BAS205 250W 230V',
        'quantity'       => 1,
        'line_total'     => '109.76',
        'line_tax'       => '25.24',
        'shipping_title' => 'ENTREGA GRATUITA*',
        'shipping_total' => '0.00',
        'cart_tax'       => '25.24',
        'order_total'    => '135.00',
    ),
    1677555 => array(
        'email_id'       => '1a0d37a3621226b5',
        'product_id'     => 1661492,
        'sku'            => 'ZI-BAS205',
        'name'           => 'Serra de Fita para Madeira ZIPPER BAS205 250W 230V',
        'quantity'       => 1,
        'line_total'     => '109.76',
        'line_tax'       => '25.24',
        'shipping_title' => 'ENTREGA GRATUITA*',
        'shipping_total' => '0.00',
        'cart_tax'       => '25.24',
        'order_total'    => '135.00',
    ),
    1677464 => array(
        'email_id'       => '1a0d2d0142776167',
        'product_id'     => 125162,
        'sku'            => 'SOD08612',
        'name'           => 'Depósito para Gasóleo STILKER 200L 12V 40L/min Polietileno',
        'quantity'       => 1,
        'line_total'     => '308.94',
        'line_tax'       => '71.06',
        'shipping_title' => 'ENTREGA GRATUITA*',
        'shipping_total' => '0.00',
        'cart_tax'       => '71.06',
        'order_total'    => '380.00',
    ),
    1677442 => array(
        'email_id'       => '1a0d0c358995f481',
        'product_id'     => 41472,
        'sku'            => 'HZMWB126',
        'name'           => 'Bancada de Trabalho em Madeira HOLZMANN WB126',
        'quantity'       => 1,
        'line_total'     => '169.11',
        'line_tax'       => '38.89',
        'shipping_title' => 'ENTREGA GRATUITA*',
        'shipping_total' => '0.00',
        'cart_tax'       => '38.89',
        'order_total'    => '208.00',
    ),
    1677317 => array(
        'email_id'       => '1a0ceefab370d053',
        'product_id'     => 1519961,
        'sku'            => 'A11013030',
        'name'           => 'Escada de Alumínio Tripla LIT 3m Degrau Quadrado Reforçada',
        'quantity'       => 1,
        'line_total'     => '193.50',
        'line_tax'       => '44.50',
        'shipping_title' => 'ENTREGA GRATUITA*',
        'shipping_total' => '0.00',
        'cart_tax'       => '44.50',
        'order_total'    => '238.00',
    ),
    1677282 => array(
        'email_id'       => '1a0ce2ed489e7546',
        'product_id'     => 1598197,
        'sku'            => 'SOD56852',
        'name'           => 'Bomba de Massa 18V STILKER 690 Bar 2 Baterias',
        'quantity'       => 1,
        'line_total'     => '128.46',
        'line_tax'       => '29.54',
        'shipping_title' => 'ENTREGA GRATUITA*',
        'shipping_total' => '0.00',
        'cart_tax'       => '29.54',
        'order_total'    => '158.00',
    ),
    1677281 => array(
        'email_id'       => '1a0ce193992a1085',
        'product_id'     => 1534208,
        'sku'            => 'KINGT853421M',
        'name'           => 'CHAVE DE IMPACTO LONGA PARA RODAS QUADRADO INTERNO 21MM 1\' KING TONY',
        'quantity'       => 1,
        'line_total'     => '28.00',
        'line_tax'       => '6.44',
        'shipping_title' => 'ENVIO (Portugal Continental)',
        'shipping_total' => '7.50',
        'fee_name'       => 'Pagamento na entrega',
        'fee_total'      => '5.00',
        'fee_tax'        => '1.15',
        'cart_tax'       => '7.59',
        'order_total'    => '48.09',
    ),
    1676630 => array(
        'email_id'       => '1a0c9bd08cdd48c5',
        'product_id'     => 1566368,
        'sku'            => 'HU-ACBF20SGWT1800',
        'name'           => 'Porta-paletes de garfos compridos HU-LIFT ACBF20 2000kg 1800mm',
        'quantity'       => 1,
        'line_total'     => '450.41',
        'line_tax'       => '103.59',
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
        $errors[] = "#{$order_id}: encomenda não encontrada.";
        continue;
    }

    if ( $order->get_meta( '_cv_email_recovered_page2_v1', true ) ) {
        $skipped++;
        continue;
    }

    $had_line_items = count( $order->get_items( 'line_item' ) ) > 0;

    if ( ! $had_line_items ) {
        $product = wc_get_product( (int) $data['product_id'] );

        if ( ! $product instanceof WC_Product ) {
            $errors[] = "#{$order_id}: produto {$data['sku']} não encontrado.";
            continue;
        }

        if ( 0 !== strcasecmp( (string) $product->get_sku(), (string) $data['sku'] ) ) {
            $errors[] = "#{$order_id}: SKU do produto atual não corresponde a {$data['sku']}.";
            continue;
        }

        $item = new WC_Order_Item_Product();
        $item->set_product( $product );
        $item->set_name( (string) $data['name'] );
        $item->set_quantity( (int) $data['quantity'] );
        $item->set_subtotal( (string) $data['line_total'] );
        $item->set_total( (string) $data['line_total'] );
        $item->set_subtotal_tax( (string) $data['line_tax'] );
        $item->set_total_tax( (string) $data['line_tax'] );
        $item->add_meta_data( '_cv_recovered_sku', (string) $data['sku'], true );
        $item->add_meta_data( '_cv_recovered_from_email', (string) $data['email_id'], true );
        $order->add_item( $item );
    }

    $shipping_items = $order->get_items( 'shipping' );

    if ( empty( $shipping_items ) ) {
        $shipping = new WC_Order_Item_Shipping();
        $shipping->set_method_title( (string) $data['shipping_title'] );
        $shipping->set_method_id( 'cv-email-recovered' );
        $shipping->set_total( (string) $data['shipping_total'] );
        $shipping->add_meta_data( '_cv_recovered_from_email', (string) $data['email_id'], true );
        $order->add_item( $shipping );
    } elseif ( 1 === count( $shipping_items ) ) {
        $shipping = reset( $shipping_items );
        if ( $shipping instanceof WC_Order_Item_Shipping ) {
            $shipping->set_method_title( (string) $data['shipping_title'] );
            $shipping->set_total( (string) $data['shipping_total'] );
            $shipping->save();
        }
    }

    if ( ! empty( $data['fee_name'] ) ) {
        $fee_exists = false;

        foreach ( $order->get_items( 'fee' ) as $fee_item ) {
            if ( 0 === strcasecmp( (string) $fee_item->get_name(), (string) $data['fee_name'] ) ) {
                $fee_exists = true;
                break;
            }
        }

        if ( ! $fee_exists ) {
            $fee = new WC_Order_Item_Fee();
            $fee->set_name( (string) $data['fee_name'] );
            $fee->set_tax_status( 'taxable' );
            $fee->set_total( (string) $data['fee_total'] );
            $fee->set_total_tax( (string) $data['fee_tax'] );
            $fee->add_meta_data( '_cv_recovered_from_email', (string) $data['email_id'], true );
            $order->add_item( $fee );
        }
    }

    // Restore the values shown in the original WooCommerce email. We do not
    // call calculate_totals(), because that would re-price an historical order
    // using the current catalogue/tax configuration.
    $order->set_shipping_total( (string) $data['shipping_total'] );
    $order->set_shipping_tax( '0' );
    $order->set_cart_tax( (string) $data['cart_tax'] );
    $order->set_total( (string) $data['order_total'] );
    $order->update_meta_data( '_cv_email_recovery_source', 'woocommerce-new-order-email' );
    $order->update_meta_data( '_cv_email_recovery_message_id', (string) $data['email_id'] );
    $order->save();

    clean_post_cache( $order_id );
    $check = wc_get_order( $order_id );

    if ( ! $check instanceof WC_Order ) {
        $errors[] = "#{$order_id}: falha ao reler depois da recuperação.";
        continue;
    }

    $line_items = $check->get_items( 'line_item' );
    $ship_items = $check->get_items( 'shipping' );

    if ( empty( $line_items ) ) {
        $errors[] = "#{$order_id}: linha de produto continua em falta.";
        continue;
    }

    if ( empty( $ship_items ) ) {
        $errors[] = "#{$order_id}: linha de envio continua em falta.";
        continue;
    }

    if ( abs( (float) $check->get_total() - (float) $data['order_total'] ) > 0.01 ) {
        $errors[] = "#{$order_id}: total final não corresponde ao email.";
        continue;
    }

    $check->update_meta_data( '_cv_email_recovered_page2_v1', gmdate( 'c' ) );
    $check->save();
    $repaired++;

    echo sprintf(
        "recovered-order=%d;sku=%s;shipping=%s;total=%s\n",
        $order_id,
        $data['sku'],
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
    "email-order-recovery-page2=success;repaired=%d;skipped=%d\n",
    $repaired,
    $skipped
);
