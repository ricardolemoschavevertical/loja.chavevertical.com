<?php
/**
 * Identidade visual uniforme dos emails HTML WooCommerce — Chave Vertical.
 *
 * Reutiliza o modelo aprovado de "Nova encomenda" sem interferir com
 * destinatarios, assunto, remetente, gatilhos, estados ou dados das encomendas.
 */
defined( 'ABSPATH' ) || exit;

/**
 * Contagem dos produtos pai publicados, arredondada por defeito ao milhar.
 */
function cvl_order_email_published_products_thousands(): int {
    $counts = wp_count_posts( 'product' );
    $published = is_object( $counts ) ? max( 0, (int) ( $counts->publish ?? 0 ) ) : 0;

    return intdiv( $published, 1000 ) * 1000;
}

function cvl_order_email_published_products_label(): string {
    return number_format( cvl_order_email_published_products_thousands(), 0, ',', '.' );
}

/**
 * Nao adicionar blocos HTML a emails em texto simples.
 */
function cvl_order_email_is_html( $email ): bool {
    return class_exists( 'WC_Email' )
        && $email instanceof WC_Email
        && ( ! method_exists( $email, 'get_email_type' ) || 'plain' !== $email->get_email_type() );
}

/**
 * Mesmo cartao de marca do email administrativo "Nova encomenda".
 * Nesse email o cartao ja esta no template: nao o repetir via hook.
 */
function cvl_order_email_store_identity(): void {
    ?>
    <table class="cvl-email-store-intro" role="presentation" cellpadding="0" cellspacing="0" width="100%" style="width:100%;background-color:#f5f7f9;border:1px solid #e6e9ed;margin:0 0 20px">
        <tr>
            <td style="padding:16px 20px;text-align:left;font-family:Arial,Helvetica,sans-serif">
                <p style="font-size:15px;line-height:1.45;font-weight:800;color:#15202b;margin:0 0 7px">
                    CHAVE VERTICAL ONLINE — MAIS DE
                    <span style="color:#d71920"><?php echo esc_html( cvl_order_email_published_products_label() ); ?></span>
                    PRODUTOS À DISTÂNCIA DE UM CLIQUE
                </p>
                <p style="font-size:12px;line-height:1.55;color:#566170;margin:0">
                    <strong style="color:#15202b">CHAVE VERTICAL LDA</strong>
                    <span style="color:#9aa3af">&nbsp;·&nbsp;</span>
                    NIPC: 509514502
                </p>
            </td>
        </tr>
    </table>
    <?php
}

add_action(
    'woocommerce_email_header',
    static function ( $heading, $email = null ): void {
        if ( ! cvl_order_email_is_html( $email ) || 'new_order' === (string) ( $email->id ?? '' ) ) {
            return;
        }

        cvl_order_email_store_identity();
    },
    20,
    2
);

/**
 * As mesmas cores, tipografia, espacos e tabelas em TODOS os emails HTML.
 * As regras especificas da nova encomenda permanecem isoladas nessa classe.
 */
add_filter(
    'woocommerce_email_styles',
    static function ( $css, $email = null ) {
        if ( ! cvl_order_email_is_html( $email ) ) {
            return $css;
        }

        $css .= <<<'CSS'

/* CHAVE VERTICAL — identidade comum de emails WooCommerce. */
#wrapper { background-color: #f5f7f9; }
#template_container {
    background-color: #ffffff;
    border: 1px solid #e6e9ed;
    box-shadow: none;
}
#template_header { background-color: #ffffff; }
#template_header #header_wrapper {
    background-color: #ffffff;
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
#body_content { background-color: #ffffff; }
#body_content_inner {
    color: #192330;
    font-family: Arial, Helvetica, sans-serif;
    font-size: 13px;
    line-height: 1.6;
}
#body_content_inner h2,
#body_content_inner h3 {
    color: #192330;
    font-weight: 800;
}
#body_content_inner a { color: #b81d26; }
#body_content_inner .cvl-email-store-intro td,
#body_content_inner .cvl-email-order-support td {
    text-align: left;
}
#body_content_inner .email-order-details {
    border-collapse: collapse;
    width: 100%;
}
#body_content_inner .email-order-details thead { background-color: #f4f6f8; }
#body_content_inner .email-order-details td,
#body_content_inner .email-order-details th {
    border-bottom: 1px solid #e8ebef;
    font-size: 12px;
    line-height: 1.45;
    color: #192330;
}
#body_content_inner .email-order-details .order-totals-last td {
    color: #c91720;
    font-weight: 800;
    font-size: 19px;
}
#body_content_inner .email-order-details .order-totals-last th {
    font-weight: 800;
    color: #15202b;
}
#body_content_inner #addresses td {
    vertical-align: top;
    overflow-wrap: anywhere;
}
#body_content_inner #addresses .address-title {
    color: #27374b;
    font-size: 13px;
    font-weight: 800;
}
#body_content_inner #addresses address {
    color: #4b596b;
    font-size: 12px;
    line-height: 1.55;
    word-break: normal;
}
#body_content_inner .cvl-email-new-order h2 { font-size: 17px; }
#body_content_inner .cvl-email-new-order .cvl-email-facts td {
    line-height: 1.45;
    vertical-align: top;
}
#template_footer #credit {
    color: #64748b;
    font-family: Arial, Helvetica, sans-serif;
    font-size: 11px;
    line-height: 1.6;
}
CSS;

        return $css;
    },
    30,
    2
);

/**
 * O bloco de suporte e comum, mas o titulo adapta-se a emails de conta.
 */
add_action(
    'woocommerce_email_footer',
    static function ( $email = null ): void {
        if ( ! cvl_order_email_is_html( $email ) ) {
            return;
        }

        $is_order_email = class_exists( 'WC_Order' )
            && isset( $email->object )
            && $email->object instanceof WC_Order;
        $heading = $is_order_email ? 'Informação sobre encomendas online:' : 'Apoio à loja online:';
        ?>
        <table class="cvl-email-order-support" role="presentation" cellpadding="0" cellspacing="0" width="100%" style="width:100%;background-color:#fff4f4;border:1px solid #f2d5d7;border-radius:7px;margin-top:20px">
            <tr>
                <td style="padding:18px;font-family:Arial,Helvetica,sans-serif;text-align:left">
                    <p style="margin:0 0 7px;font-size:15px;line-height:1.35;color:#15202b;font-weight:800"><?php echo esc_html( $heading ); ?></p>
                    <p style="margin:0;font-size:13px;line-height:1.7;color:#15202b">
                        Telefone: <a href="tel:+351914580410" style="color:#d71920;text-decoration:none;font-weight:700">914 580 410</a>
                        <span style="color:#8993a1">&nbsp;/&nbsp;</span>
                        E-mail: <a href="mailto:encomendas@chavevertical.com" style="color:#d71920;text-decoration:none;font-weight:700;word-break:break-word">encomendas@chavevertical.com</a>
                    </p>
                </td>
            </tr>
        </table>
        <?php
    },
    7,
    1
);

/**
 * O rodape WooCommerce deve ter sempre identificacao legal e ligacao
 * dinamica a loja; nao repor rodapes antigos nem texto publicitario datado.
 */
add_filter(
    'woocommerce_email_footer_text',
    static function ( $original ) {
        return sprintf(
            'CHAVE VERTICAL LDA · NIPC 509514502 · <a href="%s">Loja online</a>',
            esc_url( home_url( '/' ) )
        );
    },
    20,
    1
);
