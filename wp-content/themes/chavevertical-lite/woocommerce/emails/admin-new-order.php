<?php
/**
 * Notificação de nova encomenda — Chave Vertical.
 *
 * Preserva os hooks WooCommerce (linhas, impostos, métodos, metadados,
 * moradas e extensões). Apenas personaliza o HTML do email administrativo.
 *
 * @package ChaveVerticalLite\WooCommerce\Emails
 * @version 10.4.0
 */

defined( 'ABSPATH' ) || exit;

/** @var WC_Order|false $order */
/** @var WC_Email $email */

do_action( 'woocommerce_email_header', $email_heading, $email );

if ( ! $order instanceof WC_Order ) {
    echo '<p>' . esc_html__( 'Não foi possível apresentar o resumo desta encomenda.', 'chavevertical-lite' ) . '</p>';
    do_action( 'woocommerce_email_footer', $email );
    return;
}

$order_number  = $order->get_order_number();
$created       = $order->get_date_created();
$display_date  = $created ? wc_format_datetime( $created, 'd/m/Y H:i' ) : '—';
$customer_name = trim( (string) $order->get_formatted_billing_full_name() );
if ( ! $customer_name ) {
    $customer_name = $order->get_billing_company() ?: 'Cliente';
}
$customer_email = sanitize_email( (string) $order->get_billing_email() );
$payment        = $order->get_payment_method_title() ?: 'Não indicado';
$shipping       = $order->needs_shipping_address()
    ? ( $order->get_shipping_method() ?: 'A definir' )
    : 'Não aplicável';
$order_status   = wc_get_order_status_name( $order->get_status() );
$order_link     = $order->get_edit_order_url();
?>
<div class="cvl-email-new-order" style="color:#192330;font-family:Arial,Helvetica,sans-serif;line-height:1.5">
    <table class="cvl-email-intro" role="presentation" cellpadding="0" cellspacing="0" width="100%" style="width:100%;background-color:#fff4f4;border-left:4px solid #d71920;margin:0 0 20px">
        <tr>
            <td style="padding:20px 22px">
                <p style="font-size:11px;font-weight:800;letter-spacing:1.2px;color:#ba1820;margin:0 0 6px">NOVA ENCOMENDA RECEBIDA</p>
                <p style="font-size:21px;font-weight:800;color:#15202b;line-height:1.35;margin:0 0 8px">
                    <?php echo esc_html( sprintf( 'Encomenda #%s', $order_number ) ); ?>
                </p>
                <p style="font-size:13px;color:#4b5563;margin:0">Recebemos uma nova encomenda na loja online. Consulte os dados e confirme o processamento no painel.</p>
            </td>
        </tr>
    </table>

    <table class="cvl-email-facts" role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:separate;border-spacing:0;width:100%;background-color:#f5f7f9;border:1px solid #e6e9ed;margin:0 0 24px">
        <tr>
            <td width="50%" valign="top" style="padding:15px;border-bottom:1px solid #e6e9ed;border-right:1px solid #e6e9ed">
                <span class="cvl-email-label" style="display:block;font-size:10px;letter-spacing:.8px;font-weight:800;color:#596575;margin-bottom:5px">DATA</span>
                <strong style="font-size:13px;color:#15202b"><?php echo esc_html( $display_date ); ?></strong>
            </td>
            <td width="50%" valign="top" style="padding:15px;border-bottom:1px solid #e6e9ed">
                <span class="cvl-email-label" style="display:block;font-size:10px;letter-spacing:.8px;font-weight:800;color:#596575;margin-bottom:5px">CLIENTE</span>
                <strong style="font-size:13px;color:#15202b"><?php echo esc_html( $customer_name ); ?></strong>
                <?php if ( $customer_email ) : ?>
                    <br><a href="mailto:<?php echo esc_attr( $customer_email ); ?>" style="font-size:11px;color:#4c5b6d;text-decoration:none;overflow-wrap:anywhere"><?php echo esc_html( $customer_email ); ?></a>
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <td width="50%" valign="top" style="padding:15px;border-right:1px solid #e6e9ed">
                <span class="cvl-email-label" style="display:block;font-size:10px;letter-spacing:.8px;font-weight:800;color:#596575;margin-bottom:5px">PAGAMENTO</span>
                <strong style="font-size:13px;color:#15202b"><?php echo esc_html( $payment ); ?></strong>
                <br><span style="font-size:11px;color:#64748b"><?php echo esc_html( $order_status ); ?></span>
            </td>
            <td width="50%" valign="top" style="padding:15px">
                <span class="cvl-email-label" style="display:block;font-size:10px;letter-spacing:.8px;font-weight:800;color:#596575;margin-bottom:5px">ENVIO</span>
                <strong style="font-size:13px;color:#15202b"><?php echo esc_html( $shipping ); ?></strong>
            </td>
        </tr>
    </table>

    <div class="cvl-email-products" style="margin-bottom:18px">
        <p style="font-size:12px;letter-spacing:.8px;font-weight:800;color:#293748;text-transform:uppercase;margin:0 0 12px">Produtos encomendados</p>
        <?php
        // WooCommerce mantém a sua tabela, imagens, SKUs, variações, impostos,
        // preços, portes, cupões e extensões através deste hook.
        do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );
        ?>
    </div>

    <?php
    // Dados adicionais de pagamento e entrega inseridos pelos plugins.
    do_action( 'woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email );
    ?>

    <div class="cvl-email-addresses" style="margin:10px 0 20px">
        <?php
        // As moradas (incluindo NIF/NIPC, quando disponibilizado) vêm da encomenda.
        do_action( 'woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email );
        ?>
    </div>

    <?php if ( $order_link ) : ?>
        <table class="cvl-email-cta" role="presentation" cellpadding="0" cellspacing="0" width="100%" style="width:100%;background-color:#fff0f1;margin:14px 0 22px">
            <tr>
                <td style="padding:20px 18px">
                    <strong style="font-size:15px;color:#14212d">Ver encomenda no painel</strong>
                    <p style="font-size:12px;color:#596575;margin:5px 0 0">Aceda aos dados completos e atualize o estado da encomenda.</p>
                </td>
            </tr>
            <tr>
                <td style="padding:0 18px 20px">
                    <a href="<?php echo esc_url( $order_link ); ?>" style="display:inline-block;background-color:#d71920;border-radius:5px;padding:12px 20px;color:#fff;font-size:13px;font-weight:800;text-align:center;text-decoration:none">VER ENCOMENDA &rarr;</a>
                </td>
            </tr>
        </table>
    <?php endif; ?>

    <?php if ( ! empty( $additional_content ) ) : ?>
        <div class="cvl-email-additional-content" style="font-size:13px;color:#596575;padding:8px 0 16px">
            <?php echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) ); ?>
        </div>
    <?php endif; ?>
</div>
<?php
do_action( 'woocommerce_email_footer', $email );
