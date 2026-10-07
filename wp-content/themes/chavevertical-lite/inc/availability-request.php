<?php
defined( 'ABSPATH' ) || exit;

/**
 * Public availability request endpoint used by WooCommerce and the Astro frontend.
 */
function cvl_availability_request_confirmation_message() {
    return __( 'Agradecemos a sua consulta. O seu pedido foi encaminhado para Ricardo Lemos. Se preferir um contacto direto, pode fazê-lo através do e-mail Ricardo@chavevertical.com ou do telefone 914 580 410.', 'chavevertical-lite' );
}

/**
 * Resolve a product from the public request without trusting client-supplied names or URLs.
 */
function cvl_availability_request_get_product( WP_REST_Request $request ) {
    $product_id = absint( $request->get_param( 'product_id' ) );

    if ( $product_id > 0 ) {
        $product = wc_get_product( $product_id );
        if ( $product instanceof WC_Product ) {
            return $product;
        }
    }

    $slug = sanitize_title( (string) $request->get_param( 'product_slug' ) );
    if ( '' !== $slug ) {
        $post = get_page_by_path( $slug, OBJECT, 'product' );
        if ( $post instanceof WP_Post ) {
            $product = wc_get_product( $post->ID );
            if ( $product instanceof WC_Product ) {
                return $product;
            }
        }
    }

    return null;
}

/**
 * Only products with no available stock and backorders disabled use this flow.
 */
function cvl_product_requires_availability_request( WC_Product $product ) {
    $status         = $product->get_stock_status();
    $stock_quantity = $product->managing_stock() ? $product->get_stock_quantity() : null;
    $has_no_stock   = 'outofstock' === $status
        || ( null !== $stock_quantity && (int) $stock_quantity <= 0 );

    return $has_no_stock && ! $product->backorders_allowed();
}

function cvl_handle_availability_request( WP_REST_Request $request ) {
    $allowed_origins = array(
        'https://chavevertical.com',
        'https://www.chavevertical.com',
        'https://astro.chavevertical.com',
        'https://loja.chavevertical.com',
    );

    $origin = get_http_origin();
    if ( $origin && ! in_array( untrailingslashit( $origin ), $allowed_origins, true ) ) {
        return new WP_Error(
            'cvl_availability_origin',
            __( 'Origem do pedido não autorizada.', 'chavevertical-lite' ),
            array( 'status' => 403 )
        );
    }

    // Honeypot: return the normal success shape without sending anything.
    if ( '' !== trim( (string) $request->get_param( 'company' ) ) ) {
        return rest_ensure_response(
            array(
                'ok'      => true,
                'message' => cvl_availability_request_confirmation_message(),
            )
        );
    }

    $name  = sanitize_text_field( (string) $request->get_param( 'name' ) );
    $email = sanitize_email( (string) $request->get_param( 'email' ) );
    $phone = sanitize_text_field( (string) $request->get_param( 'phone' ) );

    if ( '' === $name || ! is_email( $email ) || '' === $phone ) {
        return new WP_Error(
            'cvl_availability_invalid_fields',
            __( 'Preencha o nome, e-mail e telefone corretamente.', 'chavevertical-lite' ),
            array( 'status' => 400 )
        );
    }

    $product = cvl_availability_request_get_product( $request );
    if ( ! $product instanceof WC_Product ) {
        return new WP_Error(
            'cvl_availability_product',
            __( 'Não foi possível identificar o produto.', 'chavevertical-lite' ),
            array( 'status' => 404 )
        );
    }

    if ( ! cvl_product_requires_availability_request( $product ) ) {
        return new WP_Error(
            'cvl_availability_not_required',
            __( 'Este produto já não necessita de consulta de disponibilidade.', 'chavevertical-lite' ),
            array( 'status' => 409 )
        );
    }

    $remote_ip = sanitize_text_field( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) );
    $rate_key  = 'cvl_availability_' . md5( strtolower( $email ) . '|' . $remote_ip . '|' . $product->get_id() );

    if ( get_transient( $rate_key ) ) {
        return new WP_Error(
            'cvl_availability_rate_limit',
            __( 'O pedido já foi enviado. Aguarde um momento antes de repetir.', 'chavevertical-lite' ),
            array( 'status' => 429 )
        );
    }

    set_transient( $rate_key, 1, MINUTE_IN_SECONDS );

    $subject = sprintf(
        '[Consulta disponibilidade] %s — Ref. %s',
        $product->get_name(),
        $product->get_sku() ? $product->get_sku() : (string) $product->get_id()
    );

    $body = implode(
        "\n",
        array(
            'Nova consulta de disponibilidade recebida no site.',
            '',
            'Produto: ' . $product->get_name(),
            'Referência: ' . ( $product->get_sku() ? $product->get_sku() : '—' ),
            'Produto ID: ' . $product->get_id(),
            'URL: ' . $product->get_permalink(),
            '',
            'Nome: ' . $name,
            'E-mail: ' . $email,
            'Telefone: ' . $phone,
            '',
            'Stock: sem stock',
            'Backorders: desligados',
            'Origem: ' . ( $origin ? $origin : 'pedido direto' ),
        )
    );

    $headers = array(
        'Content-Type: text/plain; charset=UTF-8',
        'Reply-To: ' . $name . ' <' . $email . '>',
    );

    $sent = wp_mail( 'info@chavevertical.com', $subject, $body, $headers );

    if ( ! $sent ) {
        delete_transient( $rate_key );

        return new WP_Error(
            'cvl_availability_mail',
            __( 'Não foi possível enviar o pedido neste momento. Tente novamente ou contacte-nos diretamente.', 'chavevertical-lite' ),
            array( 'status' => 500 )
        );
    }

    return rest_ensure_response(
        array(
            'ok'      => true,
            'message' => cvl_availability_request_confirmation_message(),
        )
    );
}

add_action(
    'rest_api_init',
    static function () {
        register_rest_route(
            'chavevertical/v1',
            '/availability-request',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => 'cvl_handle_availability_request',
                'permission_callback' => '__return_true',
            )
        );
    }
);
