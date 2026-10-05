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

$index = CVLOA_Archive::load_index();

if ( ! empty( $index['_error'] ) ) {
    fwrite( STDERR, 'Não foi possível ler o arquivo local: ' . $index['_error'] . "\n" );
    exit( 1 );
}

$find_archive_key = static function ( int $order_id ) use ( $index ): string {
    $fallback = '';

    foreach ( (array) ( $index['orders'] ?? array() ) as $key => $summary ) {
        $summary = (array) $summary;

        if ( absint( $summary['id'] ?? 0 ) !== $order_id ) {
            continue;
        }

        if ( 'remote' === sanitize_key( (string) ( $summary['origin'] ?? '' ) ) ) {
            return (string) $key;
        }

        if ( '' === $fallback ) {
            $fallback = (string) $key;
        }
    }

    return $fallback;
};

foreach ( $orders as $order_id => $data ) {
    $archive_key = $find_archive_key( (int) $order_id );

    if ( '' === $archive_key ) {
        $errors[] = "#{$order_id}: encomenda não encontrada no arquivo local.";
        continue;
    }

    $order = CVLOA_Archive::read_order( $archive_key );

    if ( ! is_array( $order ) ) {
        $errors[] = "#{$order_id}: não foi possível ler a encomenda do arquivo.";
        continue;
    }

    if ( ! empty( $order['_cvloa_email_recovered_page2_v1'] ) ) {
        $skipped++;
        continue;
    }

    if ( empty( $order['line_items'] ) ) {
        $order['line_items'] = array(
            array(
                'id'           => 0,
                'name'         => (string) $data['name'],
                'product_id'   => absint( $data['product_id'] ),
                'variation_id' => 0,
                'quantity'     => absint( $data['quantity'] ),
                'tax_class'    => '',
                'subtotal'     => (string) $data['line_total'],
                'subtotal_tax' => (string) $data['line_tax'],
                'total'        => (string) $data['line_total'],
                'total_tax'    => (string) $data['line_tax'],
                'taxes'        => array(),
                'sku'          => (string) $data['sku'],
                'price'        => (string) $data['line_total'],
                'meta_data'    => array(
                    array(
                        'key'   => '_cv_recovered_sku',
                        'value' => (string) $data['sku'],
                    ),
                    array(
                        'key'   => '_cv_recovered_from_email',
                        'value' => (string) $data['email_id'],
                    ),
                ),
            ),
        );
    }

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
                'total'      => (string) $data['fee_total'],
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

    $order['shipping_total'] = (string) $data['shipping_total'];
    $order['shipping_tax']   = '0.00';
    $order['total_tax']      = (string) $data['cart_tax'];
    $order['total']          = (string) $data['order_total'];
    $order['_cvloa_archive_key'] = $archive_key;
    $order['_cvloa_email_recovered_page2_v1'] = array(
        'email_id'     => (string) $data['email_id'],
        'recovered_at' => gmdate( 'c' ),
    );

    $result = CVLOA_Archive::archive_batch( array( $order ), true );

    if ( is_wp_error( $result ) ) {
        $errors[] = "#{$order_id}: " . $result->get_error_message();
        continue;
    }

    $check = CVLOA_Archive::read_order( $archive_key );

    if ( ! is_array( $check ) || empty( $check['line_items'] ) || empty( $check['shipping_lines'] ) ) {
        $errors[] = "#{$order_id}: a recuperação não passou a verificação final.";
        continue;
    }

    if ( abs( (float) ( $check['total'] ?? 0 ) - (float) $data['order_total'] ) > 0.01 ) {
        $errors[] = "#{$order_id}: total final não corresponde ao email.";
        continue;
    }

    $repaired++;

    echo sprintf(
        "recovered-archive-order=%d;key=%s;sku=%s;shipping=%s;total=%s\n",
        $order_id,
        $archive_key,
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
