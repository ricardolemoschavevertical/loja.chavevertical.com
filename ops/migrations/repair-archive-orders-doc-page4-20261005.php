<?php
/**
 * Apply the page-4 legacy archive reconciliation from a minimal JSON payload.
 * Only the archive copy is changed. Statuses, stock and customer data are not.
 */

defined( 'ABSPATH' ) || exit;

$data_file = __DIR__ . '/data/archive-doc-page5-20261005.json';

if ( ! is_readable( $data_file ) ) {
    fwrite( STDERR, "archive-doc-page4=data-file-missing\n" );
    exit( 1 );
}

$payload = json_decode( (string) file_get_contents( $data_file ), true );

if ( ! is_array( $payload ) || empty( $payload['orders'] ) ) {
    fwrite( STDERR, "archive-doc-page4=invalid-payload\n" );
    exit( 1 );
}

$marker = sanitize_key( (string) ( $payload['marker'] ?? 'cv_repair_archive_doc_page4_20261005_v1' ) );
$label  = sanitize_key( (string) ( $payload['label'] ?? 'archive-doc-page4' ) );
$batch  = sanitize_text_field( (string) ( $payload['batch'] ?? 'page4-20261005-v1' ) );

if ( get_option( $marker ) ) {
    echo "{$label}=already-applied\n";
    return;
}

$errors  = array();
$updated = 0;

foreach ( (array) $payload['orders'] as $data ) {
    $order_id    = absint( $data['id'] ?? 0 );
    $archive_key = 'remote-8d20db3492-' . $order_id;
    $order       = CVLOA_Archive::read_order( $archive_key );

    if ( ! $order_id || ! is_array( $order ) ) {
        $errors[] = "#{$order_id}: arquivo não encontrado.";
        continue;
    }

    $source_items = (array) ( $data['items'] ?? array() );

    if ( ! $source_items ) {
        $errors[] = "#{$order_id}: documento sem produtos.";
        continue;
    }

    $tax_basis = (float) ( $data['fee_gross'] ?? 0 );

    foreach ( $source_items as $source_item ) {
        $tax_basis += (float) ( $source_item['gross'] ?? 0 );
    }

    $declared_tax = (float) ( $data['total_tax'] ?? 0 );
    $tax_left     = $declared_tax;
    $components   = count( $source_items ) + ( (float) ( $data['fee_gross'] ?? 0 ) > 0 ? 1 : 0 );
    $component_no = 0;
    $line_items   = array();
    $expected_qty = 0;

    foreach ( $source_items as $item_data ) {
        $component_no++;

        $sku      = sanitize_text_field( (string) ( $item_data['sku'] ?? '' ) );
        $quantity = max( 1, absint( $item_data['quantity'] ?? 1 ) );
        $gross    = (float) ( $item_data['gross'] ?? 0 );

        $item_tax = $component_no === $components
            ? round( $tax_left, 2 )
            : ( $tax_basis > 0 ? round( $declared_tax * ( $gross / $tax_basis ), 2 ) : 0.0 );

        if ( $component_no !== $components ) {
            $tax_left -= $item_tax;
        }

        $item_net   = round( $gross - $item_tax, 2 );
        $product_id = '' !== $sku && function_exists( 'wc_get_product_id_by_sku' )
            ? absint( wc_get_product_id_by_sku( $sku ) )
            : 0;
        $product    = $product_id ? wc_get_product( $product_id ) : false;
        $name       = $product instanceof WC_Product ? $product->get_name() : $sku;

        $expected_qty += $quantity;

        $line_items[] = array(
            'id'           => 0,
            'name'         => $name,
            'product_id'   => $product_id,
            'variation_id' => 0,
            'quantity'     => $quantity,
            'tax_class'    => '',
            'subtotal'     => wc_format_decimal( $item_net, 2 ),
            'subtotal_tax' => wc_format_decimal( $item_tax, 2 ),
            'total'        => wc_format_decimal( $item_net, 2 ),
            'total_tax'    => wc_format_decimal( $item_tax, 2 ),
            'taxes'        => array(),
            'sku'          => $sku,
            'price'        => wc_format_decimal( $item_net / $quantity, wc_get_price_decimals() ),
            'meta_data'    => array(),
        );
    }

    $order['line_items'] = $line_items;

    $order['shipping_lines'] = array(
        array(
            'id'           => 0,
            'method_title' => (string) ( $data['shipping_title'] ?? '' ),
            'method_id'    => 'cv-email-recovered',
            'instance_id'  => '',
            'total'        => (string) ( $data['shipping_total'] ?? '0.00' ),
            'total_tax'    => '0.00',
            'taxes'        => array(),
            'meta_data'    => array(),
        ),
    );

    $order['fee_lines'] = array();

    $fee_gross = (float) ( $data['fee_gross'] ?? 0 );

    if ( $fee_gross > 0 ) {
        $component_no++;

        $fee_tax = $component_no === $components
            ? round( $tax_left, 2 )
            : ( $tax_basis > 0 ? round( $declared_tax * ( $fee_gross / $tax_basis ), 2 ) : 0.0 );
        $fee_net = round( $fee_gross - $fee_tax, 2 );

        $order['fee_lines'][] = array(
            'id'         => 0,
            'name'       => 'Pagamento na entrega',
            'tax_class'  => '',
            'tax_status' => 'taxable',
            'total'      => wc_format_decimal( $fee_net, 2 ),
            'total_tax'  => wc_format_decimal( $fee_tax, 2 ),
            'taxes'      => array(),
            'meta_data'  => array(),
        );
    }

    if ( ! empty( $data['payment_method_title'] ) ) {
        $order['payment_method_title'] = (string) $data['payment_method_title'];
    }

    $order['shipping_total'] = (string) ( $data['shipping_total'] ?? '0.00' );
    $order['shipping_tax']   = '0.00';
    $order['total_tax']      = (string) ( $data['total_tax'] ?? '0.00' );
    $order['total']          = (string) ( $data['total'] ?? '0.00' );
    $order['_cvloa_archive_key'] = $archive_key;
    $order['_cvloa_doc_reconciled'] = array(
        'document_id'   => (string) ( $payload['source_document'] ?? '' ),
        'reconciled_at' => gmdate( 'c' ),
        'batch'         => $batch,
    );

    $saved = CVLOA_Archive::archive_batch( array( $order ), true );

    if ( is_wp_error( $saved ) ) {
        $errors[] = "#{$order_id}: " . $saved->get_error_message();
        continue;
    }

    $check = CVLOA_Archive::read_order( $archive_key );

    if ( ! is_array( $check ) ) {
        $errors[] = "#{$order_id}: falhou a releitura.";
        continue;
    }

    $actual_items = (array) ( $check['line_items'] ?? array() );
    $actual_qty   = 0;
    $actual_skus  = array();

    foreach ( $actual_items as $item ) {
        $item = (array) $item;
        $actual_qty += max( 0, (int) ( $item['quantity'] ?? 0 ) );
        $actual_skus[] = (string) ( $item['sku'] ?? '' );
    }

    $expected_skus = array_map(
        static fn( array $item ): string => (string) ( $item['sku'] ?? '' ),
        $source_items
    );

    sort( $actual_skus );
    sort( $expected_skus );

    if (
        count( $actual_items ) !== count( $source_items )
        || $actual_qty !== $expected_qty
        || $actual_skus !== $expected_skus
    ) {
        $errors[] = "#{$order_id}: linhas/SKU/quantidades não correspondem.";
        continue;
    }

    if ( empty( $check['shipping_lines'] ) ) {
        $errors[] = "#{$order_id}: envio em falta.";
        continue;
    }

    if ( abs( (float) ( $check['total'] ?? 0 ) - (float) $data['total'] ) > 0.01 ) {
        $errors[] = "#{$order_id}: total não corresponde.";
        continue;
    }

    if ( abs( (float) ( $check['total_tax'] ?? 0 ) - $declared_tax ) > 0.01 ) {
        $errors[] = "#{$order_id}: IVA não corresponde.";
        continue;
    }

    $updated++;

    echo sprintf(
        "updated-archive-order=%d;items=%d;quantity=%d;shipping=%s;total=%s\n",
        $order_id,
        count( $source_items ),
        $expected_qty,
        (string) ( $data['shipping_title'] ?? '' ),
        (string) ( $data['total'] ?? '0.00' )
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
        'unsupported_without_source' => array_map(
            'absint',
            (array) ( $payload['unsupported'] ?? array() )
        ),
    ),
    false
);

echo sprintf(
    "%s=success;updated=%d;unsupported=%d\n",
    $label,
    $updated,
    count( (array) ( $payload['unsupported'] ?? array() ) )
);
