<?php
/**
 * Design e contactos dos emails de encomenda Chave Vertical.
 *
 * Apenas apresentação: não altera destinatários, remetentes, estados,
 * gatilhos de envio nem dados persistidos das encomendas.
 */
defined( 'ABSPATH' ) || exit;

/**
 * Estilos do resumo de nova encomenda, isolados do restante email WooCommerce.
 */
add_filter(
    'woocommerce_email_styles',
    static function ( $css, $email = null ) {
        if ( ! is_object( $email ) || ! isset( $email->id ) || 'new_order' !== $email->id ) {
            return $css;
        }

        $css .= <<<'CSS'

/* Chave Vertical — apenas email HTML administrativo de nova encomenda. */
#template_header #header_wrapper {
    border-bottom: 3px solid #d71920;
    padding-bottom: 22px;
}
#template_header #header_wrapper h1 {
    color: #15202b;
    font-size: 25px;
    font-weight: 800;
    line-height: 1.25;
    letter-spacing: -0.3px;
}
#body_content_inner .cvl-email-new-order h2 {
    font-size: 17px;
    color: #192330;
}
#body_content_inner .cvl-email-new-order .email-order-details {
    border-collapse: collapse;
    width: 100%;
}
#body_content_inner .cvl-email-new-order .email-order-details thead {
    background-color: #f4f6f8;
}
#body_content_inner .cvl-email-new-order .email-order-details td,
#body_content_inner .cvl-email-new-order .email-order-details th {
    border-bottom: 1px solid #e8ebef;
    font-size: 12px;
    line-height: 1.45;
    color: #192330;
}
#body_content_inner .cvl-email-new-order .email-order-details .order-totals-last td {
    color: #c91720;
    font-weight: 800;
    font-size: 19px;
}
#body_content_inner .cvl-email-new-order .email-order-details .order-totals-last th {
    font-weight: 800;
    color: #15202b;
}
#body_content_inner .cvl-email-new-order .cvl-email-facts td {
    line-height: 1.45;
    vertical-align: top;
}
#body_content_inner .cvl-email-new-order #addresses td {
    vertical-align: top;
    overflow-wrap: anywhere;
}
#body_content_inner .cvl-email-new-order #addresses .address-title {
    color: #27374b;
    font-size: 13px;
    font-weight: 800;
}
#body_content_inner .cvl-email-new-order #addresses address {
    color: #4b596b;
    font-size: 12px;
    line-height: 1.55;
    word-break: normal;
}
CSS;

        return $css;
    },
    30,
    2
);

/**
 * Contactos de encomendas visíveis em todos os emails HTML associados a
 * encomendas. Usa apenas dados de contacto confirmados pelo utilizador.
 */
add_action(
    'woocommerce_email_footer',
    static function ( $email = null ): void {
        if (
            ! class_exists( 'WC_Email' )
            || ! class_exists( 'WC_Order' )
            || ! ( $email instanceof WC_Email )
            || ! isset( $email->object )
            || ! ( $email->object instanceof WC_Order )
        ) {
            return;
        }
        ?>
        <table class="cvl-email-order-support" role="presentation" cellpadding="0" cellspacing="0" width="100%" style="width:100%;border-top:2px solid #d71920;margin-top:22px">
            <tr>
                <td style="padding:18px 0 8px;font-family:Arial,Helvetica,sans-serif;text-align:left">
                    <p style="margin:0 0 7px;font-size:11px;letter-spacing:1px;color:#556170;font-weight:800">INFORMAÇÕES SOBRE ENCOMENDAS</p>
                    <p style="margin:0;font-size:13px;line-height:1.7;color:#15202b">
                        <a href="tel:+351914580410" style="color:#15202b;text-decoration:none;font-weight:700">914 580 410</a>
                        <span style="color:#a2aab4">&nbsp;|&nbsp;</span>
                        <a href="mailto:encomendas@chavevertical.com" style="color:#b81d26;text-decoration:none;font-weight:700">encomendas@chavevertical.com</a>
                    </p>
                </td>
            </tr>
        </table>
        <?php
    },
    7,
    1
);
