<?php
/**
 * One-off recovery of the remaining damaged orders from Gmail page 2.
 * Only the local legacy-order archive is changed.
 */

defined( 'ABSPATH' ) || exit;

$marker = 'cv_email_order_recovery_page2b_20261005_v1';

if ( get_option( $marker ) ) {
    echo "email-order-recovery-page2b=already-applied\n";
    return;
}

$orders = array(
    1676580 => array(
        'email_id'       => '1a0c912c1db7e0b5',
        'items'          => array(
            array(
                'product_id' => 1675397,
                'sku'        => 'SOLT10733',
                'name'       => 'CARRO UTS 1000 SOLDADURA SOLTER',
                'quantity'   => 1,
                'total'      => '127.50',
                'tax'        => '29.33',
            ),
            array(
                'product_id' => 1566563,
                'sku'        => 'ST10165',
                'name'       => 'MESA DE SOLDADURA 900X600MM C/ ORIFÍCIOS DE 16MM SOLTER',
                'quantity'   => 1,
                'total'      => '480.00',
                'tax'        => '110.40',
            ),
        ),
        'shipping_title' => 'ENTREGA GRATUITA*',
        'shipping_total' => '0.00',
        'cart_tax'       => '139.73',
        'order_total'    => '747.23',
    ),
    1676012 => array(
        'email_id'       => '1a0c5b1d502a334b',
        'items'          => array(
            array(
                'product_id' => 1518361,
                'sku'        => 'JBM53904',
                'name'       => 'Carrinho de Ferramentas JBM 7 Gavetas Verde com 172 Peças',
                'quantity'   => 1,
                'total'      => '400.00',
                'tax'        => '92.00',
            ),
        ),
        'shipping_title' => 'Envio grátis (Transporte marítimo é da responsabilidade do cliente)',
        'shipping_total' => '0.00',
        'cart_tax'       => '92.00',
        'order_total'    => '492.00',
    ),
    1676001 => array(
        'email_id'       => '1a0c472ff1534c96',
        'items'          => array(
            array(
                'product_id' => 57304,
                'sku'        => 'KT9730',
                'name'       => 'Aspirador de Óleo com Visor e Aparadeira KROFTOOLS 90L 10L',
                'quantity'   => 1,
                'total'      => '274.80',
                'tax'        => '63.20',
            ),
        ),
        'shipping_title' => 'ENTREGA GRATUITA*',
        'shipping_total' => '0.00',
        'cart_tax'       => '63.20',
        'order_total'    => '338.00',
    ),
    1675998 => array(
        'email_id'       => '1a0c4245556fcdc9',
        'items'          => array(
            array(
                'product_id' => 115732,
                'sku'        => 'HZMRSM710',
                'name'       => 'Roda Inglesa HOLZMANN RSM710 710 mm',
                'quantity'   => 1,
                'total'      => '699.19',
                'tax'        => '160.81',
            ),
            array(
                'product_id' => 1597903,
                'sku'        => 'ABT441220000',
                'name'       => 'Torno de Bancada FORTEX FTX-520X200 550W 230V',
                'quantity'   => 1,
                'total'      => '1622.76',
                'tax'        => '373.24',
            ),
            array(
                'product_id' => 1505603,
                'sku'        => 'VKPFM012',
                'name'       => 'Mini-Quinadeira para Torno VKP 300 mm 1,2 mm',
                'quantity'   => 1,
                'total'      => '175.61',
                'tax'        => '40.39',
            ),
            array(
                'product_id' => 129393,
                'sku'        => 'OPTI3241007',
                'name'       => 'Guilhotina Manual OPTIMUM PS 150 115mm',
                'quantity'   => 1,
                'total'      => '197.56',
                'tax'        => '45.44',
            ),
            array(
                'product_id' => 89717,
                'sku'        => 'POWERED260100',
                'name'       => 'Prensa Hidropneumática POWERED PPSP12 12 T',
                'quantity'   => 1,
                'total'      => '576.42',
                'tax'        => '132.58',
            ),
            array(
                'product_id' => 137982,
                'sku'        => 'MTK4101115',
                'name'       => 'Conjunto de 6 Mandris com Placa Furada METALLKRAFT para WPP 15 T',
                'quantity'   => 1,
                'total'      => '189.43',
                'tax'        => '43.57',
            ),
            array(
                'product_id' => 95869,
                'sku'        => '1.064-912.0',
                'name'       => 'Lavadora de Alta Pressão a Quente KARCHER HDS 5/15 U 150 bar 2.7 kW',
                'quantity'   => 2,
                'total'      => '4450.41',
                'tax'        => '1023.59',
            ),
            array(
                'product_id' => 1599561,
                'sku'        => 'AIRC2022855',
                'name'       => 'Compressor de Pistão Insonorizado AIRCRAFT SILENT 903/15 7,5 kW',
                'quantity'   => 2,
                'total'      => '10478.05',
                'tax'        => '2409.95',
            ),
        ),
        'shipping_title' => 'ENTREGA GRATUITA*',
        'shipping_total' => '0.00',
        'cart_tax'       => '4229.57',
        'order_total'    => '22619.00',
    ),
    1675928 => array(
        'email_id'       => '1a0c3594775bea52',
        'items'          => array(
            array(
                'product_id' => 125162,
                'sku'        => 'SOD08612',
                'name'       => 'Depósito para Gasóleo STILKER 200L 12V 40L/min Polietileno',
                'quantity'   => 1,
                'total'      => '308.94',
                'tax'        => '71.06',
            ),
        ),
        'shipping_title' => 'ENTREGA GRATUITA*',
        'shipping_total' => '0.00',
        'cart_tax'       => '71.06',
        'order_total'    => '380.00',
    ),
    1674910 => array(
        'email_id'       => '1a0b528af7984e07',
        'items'          => array(
            array(
                'product_id' => 86727,
                'sku'        => 'POWERED240152',
                'name'       => 'Dispensador de Diesel POWERED 230V 60 L/min com Contador e Pistola Automática',
                'quantity'   => 1,
                'total'      => '308.13',
                'tax'        => '70.87',
            ),
        ),
        'shipping_title' => 'ENTREGA GRATUITA*',
        'shipping_total' => '0.00',
        'cart_tax'       => '70.87',
        'order_total'    => '379.00',
    ),
    1674907 => array(
        'email_id'       => '1a0b4ba33792353e',
        'items'          => array(
            array(
                'product_id' => 1545636,
                'sku'        => 'SOD72517',
                'name'       => 'Carro de Ferramentas STILKER 7 Gavetas 205KG Azul/Cinza',
                'quantity'   => 1,
                'total'      => '199.19',
                'tax'        => '45.81',
            ),
        ),
        'shipping_title' => 'ENTREGA GRATUITA*',
        'shipping_total' => '0.00',
        'cart_tax'       => '45.81',
        'order_total'    => '245.00',
    ),
    1674906 => array(
        'email_id'       => '1a0b494572ece210',
        'items'          => array(
            array(
                'product_id' => 1523839,
                'sku'        => 'POWERED202679',
                'name'       => 'Bandeira extensível para guincho Powered com capacidade 600 kg (750 mm) / 300 kg (1100 mm)',
                'quantity'   => 1,
                'total'      => '55.28',
                'tax'        => '12.72',
            ),
        ),
        'shipping_title' => 'ENVIO (Portugal Continental)',
        'shipping_total' => '7.50',
        'cart_tax'       => '12.72',
        'order_total'    => '75.50',
    ),
    1674904 => array(
        'email_id'       => '1a0b44e030488365',
        'items'          => array(
            array(
                'product_id' => 56366,
                'sku'        => 'KT2084',
                'name'       => 'Enrolador de Ar Comprimido KROFTOOLS 15m 8x12mm 150 PSI',
                'quantity'   => 1,
                'total'      => '51.22',
                'tax'        => '11.78',
            ),
            array(
                'product_id' => 1575477,
                'sku'        => 'KT4851',
                'name'       => 'Macaco Balão Pneumático 3T KROFTOOLS 145-400mm 8-12 BAR',
                'quantity'   => 1,
                'total'      => '95.12',
                'tax'        => '21.88',
            ),
        ),
        'shipping_title' => 'ENTREGA GRATUITA*',
        'shipping_total' => '0.00',
        'cart_tax'       => '33.66',
        'order_total'    => '180.00',
    ),
    1674901 => array(
        'email_id'       => '1a0b3b43c0770642',
        'items'          => array(
            array(
                'product_id' => 125162,
                'sku'        => 'SOD08612',
                'name'       => 'Depósito para Gasóleo STILKER 200L 12V 40L/min Polietileno',
                'quantity'   => 1,
                'total'      => '308.94',
                'tax'        => '71.06',
            ),
        ),
        'shipping_title' => 'Envio grátis (Transporte marítimo é da responsabilidade do cliente)',
        'shipping_total' => '0.00',
        'cart_tax'       => '71.06',
        'order_total'    => '380.00',
    ),
);

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

$errors    = array();
$repaired  = 0;
$skipped   = 0;
$not_found = 0;

foreach ( $orders as $order_id => $data ) {
    $archive_key = $find_archive_key( (int) $order_id );

    if ( '' === $archive_key ) {
        $not_found++;
        echo "skipped-not-archived-order={$order_id}\n";
        continue;
    }

    $order = CVLOA_Archive::read_order( $archive_key );

    if ( ! is_array( $order ) ) {
        $errors[] = "#{$order_id}: não foi possível ler a encomenda do arquivo.";
        continue;
    }

    if ( ! empty( $order['_cvloa_email_recovered_page2b_v1'] ) ) {
        $skipped++;
        continue;
    }

    if ( empty( $order['line_items'] ) ) {
        $line_items = array();

        foreach ( (array) $data['items'] as $item_data ) {
            $quantity = max( 1, absint( $item_data['quantity'] ?? 1 ) );
            $line_total = (float) ( $item_data['total'] ?? 0 );

            $line_items[] = array(
                'id'           => 0,
                'name'         => (string) $item_data['name'],
                'product_id'   => absint( $item_data['product_id'] ?? 0 ),
                'variation_id' => 0,
                'quantity'     => $quantity,
                'tax_class'    => '',
                'subtotal'     => (string) $item_data['total'],
                'subtotal_tax' => (string) $item_data['tax'],
                'total'        => (string) $item_data['total'],
                'total_tax'    => (string) $item_data['tax'],
                'taxes'        => array(),
                'sku'          => (string) $item_data['sku'],
                'price'        => wc_format_decimal( $line_total / $quantity, wc_get_price_decimals() ),
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

    $order['shipping_total'] = (string) $data['shipping_total'];
    $order['shipping_tax']   = '0.00';
    $order['total_tax']      = (string) $data['cart_tax'];
    $order['total']          = (string) $data['order_total'];
    $order['_cvloa_archive_key'] = $archive_key;
    $order['_cvloa_email_recovered_page2b_v1'] = array(
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
        "recovered-archive-order=%d;key=%s;items=%d;shipping=%s;total=%s\n",
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
        'repaired'   => $repaired,
        'skipped'    => $skipped,
        'not_found'  => $not_found,
    ),
    false
);

echo sprintf(
    "email-order-recovery-page2b=success;repaired=%d;skipped=%d;not_found=%d\n",
    $repaired,
    $skipped,
    $not_found
);
