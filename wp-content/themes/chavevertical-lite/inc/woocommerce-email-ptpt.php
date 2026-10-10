<?php
/**
 * Traduções de recurso PT-PT para as notificações WooCommerce da Chave Vertical.
 *
 * O catálogo de traduções oficial tem prioridade. Este ficheiro cobre apenas
 * textos padrão ingleses que permaneçam por traduzir, sem modificar dados,
 * destinatários, métodos de pagamento, regras comerciais ou o conteúdo livre.
 */
defined( 'ABSPATH' ) || exit;

/**
 * A tradução de etiquetas no corpo aplica-se apenas entre os hooks de
 * cabeçalho e rodapé do email, tanto em HTML como em texto simples.
 */
function cvl_email_ptpt_active(): bool {
    return ! empty( $GLOBALS['cvl_email_ptpt_depth'] );
}

/**
 * Dicionário de textos nativos do WooCommerce, preservando os especificadores
 * sprintf, entidades e marcadores necessários à apresentação das encomendas.
 */
function cvl_email_ptpt_dictionary(): array {
    static $map = array(
        // Cabeçalhos, tabela de produtos, moradas e totais.
        'Order summary' => 'Resumo da encomenda',
        'Order details' => 'Detalhes da encomenda',
        'Order' => 'Encomenda',
        '[Order #%s]' => '[Encomenda n.º %s]',
        'Order #%s' => 'Encomenda n.º %s',
        'Order #%1$s (%2$s)' => 'Encomenda n.º %1$s (%2$s)',
        '[Order #%1$s] (%2$s)' => '[Encomenda n.º %1$s] (%2$s)',
        'Product' => 'Produto',
        'Products' => 'Produtos',
        'Quantity' => 'Quantidade',
        'Qty' => 'Qtd.',
        'Price' => 'Preço',
        'Subtotal:' => 'Subtotal:',
        'Discount:' => 'Desconto:',
        'Shipping:' => 'Envio:',
        'Total:' => 'Total:',
        'Tax:' => 'Imposto:',
        'Payment method' => 'Método de pagamento',
        'Payment method:' => 'Método de pagamento:',
        'Billing address' => 'Morada de faturação',
        'Shipping address' => 'Morada de envio',
        'Customer details' => 'Dados do cliente',
        'Customer note' => 'Nota do cliente',
        'Note:' => 'Nota:',
        'Free!' => 'Grátis!',
        'N/A' => 'Não aplicável',
        'View order: %s' => 'Ver encomenda: %s',
        'View your order' => 'Ver a sua encomenda',
        'Pay for this order' => 'Pagar esta encomenda',

        // Saudações e confirmações ao cliente, em ambos os layouts WooCommerce.
        'Hi %s,' => 'Olá %s,',
        'Hi,' => 'Olá,',
        'Hi there,' => 'Olá,',
        'Just to let you know &mdash; we’ve received your order, and it is now being processed.' =>
            'Informamos que recebemos a sua encomenda e que já está em processamento.',
        'Just to let you know &mdash; we&#039;ve received your order, and it is now being processed.' =>
            'Informamos que recebemos a sua encomenda e que já está em processamento.',
        "Just to let you know &mdash; we've received your order #%s, and it is now being processed:" =>
            'Informamos que recebemos a encomenda n.º %s e que já está em processamento:',
        'Here’s a reminder of what you’ve ordered:' =>
            'Relembramos os produtos da sua encomenda:',
        'We have finished processing your order.' => 'Concluímos o processamento da sua encomenda.',
        'We’ve received your order and it’s currently on hold until we can confirm your payment has been processed.' =>
            'Recebemos a sua encomenda, que ficará em espera até confirmarmos o pagamento.',
        'Thanks for your order. It’s on-hold until we confirm that payment has been received.' =>
            'Obrigado pela sua encomenda. Ficará em espera até confirmarmos a receção do pagamento.',
        'The following note has been added to your order:' =>
            'Foi adicionada a seguinte nota à sua encomenda:',
        'As a reminder, here are your order details:' =>
            'Para sua referência, seguem os detalhes da encomenda:',
        'Your order from %s has been partially refunded.' =>
            'A sua encomenda em %s foi parcialmente reembolsada.',
        'Your order from %s has been refunded.' =>
            'A sua encomenda em %s foi reembolsada.',
        'Your order on %s has been partially refunded. There are more details below for your reference:' =>
            'A sua encomenda em %s foi parcialmente reembolsada. Consulte os detalhes abaixo:',
        'Your order on %s has been refunded. There are more details below for your reference:' =>
            'A sua encomenda em %s foi reembolsada. Consulte os detalhes abaixo:',
        'Here are the details of your order placed on %s:' =>
            'Seguem os detalhes da encomenda efetuada em %s:',
        'An order has been created for you on %1$s. Your order details are below, with a link to make payment when you’re ready: %2$s' =>
            'Foi criada uma encomenda para si em %1$s. Seguem os detalhes e a ligação para efetuar o pagamento: %2$s',
        'Sorry, your order on %1$s was unsuccessful. Your order details are below, with a link to try your payment again: %2$s' =>
            'Não foi possível concluir a sua encomenda em %1$s. Seguem os detalhes e uma ligação para tentar novamente o pagamento: %2$s',
        "Unfortunately, we couldn't complete your order due to an issue with your payment method." =>
            'Não foi possível concluir a sua encomenda devido a um problema com o método de pagamento.',
        "If you'd like to continue with your purchase, please return to %s and try a different method of payment." =>
            'Para concluir a compra, volte a %s e experimente outro método de pagamento.',
        'Your order details are as follows:' => 'Seguem os detalhes da sua encomenda:',

        // Notificações administrativas.
        'You’ve received the following order from %s:' =>
            'Recebeu a seguinte encomenda de %s:',
        'You’ve received a new order from %s:' => 'Recebeu uma nova encomenda de %s:',
        'Notification to let you know &mdash; order #%1$s belonging to %2$s has been cancelled:' =>
            'Informamos que a encomenda n.º %1$s de %2$s foi cancelada:',
        'We’re getting in touch to let you know that order #%1$s from %2$s has been cancelled.' =>
            'Informamos que a encomenda n.º %1$s de %2$s foi cancelada.',
        'Payment for order #%1$s from %2$s has failed. The order was as follows:' =>
            'O pagamento da encomenda n.º %1$s de %2$s falhou. Seguem os detalhes:',
        'Unfortunately, the payment for order #%1$s from %2$s has failed. The order was as follows:' =>
            'O pagamento da encomenda n.º %1$s de %2$s falhou. Seguem os detalhes:',

        // Criação e recuperação de conta.
        'My account' => 'A minha conta',
        'Username: <b>%s</b>' => 'Nome de utilizador: <b>%s</b>',
        'Username: %s' => 'Nome de utilizador: %s',
        'Set your new password.' => 'Definir a sua nova palavra-passe.',
        'Click here to set your new password.' => 'Clique aqui para definir a sua nova palavra-passe.',
        'Reset your password' => 'Repor a palavra-passe',
        'Click here to reset your password' => 'Clique aqui para repor a palavra-passe',
        'Thanks for creating an account on %s. Here’s a copy of your user details.' =>
            'Obrigado por criar uma conta em %s. Seguem os dados da sua conta.',
        'Thanks for creating an account on %1$s. Your username is %2$s. You can access your account area to view orders, change your password, and more at: %3$s' =>
            'Obrigado por criar uma conta em %1$s. O seu nome de utilizador é %2$s. Pode consultar encomendas e gerir a conta em: %3$s',
        'You can access your account area to view orders, change your password, and more via the link below:' =>
            'Pode aceder à sua área de cliente para consultar encomendas, alterar a palavra-passe e muito mais:',
        'Someone has requested a new password for the following account on %s:' =>
            'Foi solicitada uma nova palavra-passe para a seguinte conta em %s:',
        'If you didn’t make this request, just ignore this email. If you’d like to proceed, reset your password via the link below:' =>
            'Se não efetuou este pedido, ignore este email. Para continuar, reponha a palavra-passe através da ligação abaixo:',
        "If you didn't make this request, just ignore this email. If you'd like to proceed:" =>
            'Se não efetuou este pedido, ignore este email. Caso pretenda continuar:',

        // Texto de rodapé predefinido, incluindo opções antigas guardadas.
        'Congratulations on the sale!' => 'Parabéns pela venda!',
        'Congratulations on the sale.' => 'Parabéns pela venda.',
        'Thanks for using {site_url}!' => 'Obrigado por comprar em {site_url}!',
        'Thanks for shopping with us.' => 'Obrigado por comprar connosco.',
        'Thanks again! If you need any help with your order, please contact us at {store_email}.' =>
            'Mais uma vez, obrigado! Se precisar de ajuda com a encomenda, contacte-nos através de {store_email}.',
        'If you need any help with your order, please contact us at {store_email}.' =>
            'Se precisar de ajuda com a encomenda, contacte-nos através de {store_email}.',
        'We look forward to fulfilling your order soon.' =>
            'Esperamos poder preparar a sua encomenda brevemente.',
        'We hope to see you again soon.' => 'Esperamos voltar a recebê-lo em breve.',
        'We look forward to seeing you soon.' => 'Esperamos voltar a vê-lo em breve.',
        'Thanks for reading.' => 'Obrigado pela atenção.',
    );

    return $map;
}

/**
 * Usar o pacote de idioma oficial sempre que a expressão já esteja traduzida.
 */
function cvl_email_ptpt_gettext( $translated, $original, $domain ) {
    if ( 'woocommerce' !== $domain || ! cvl_email_ptpt_active()
        || ! is_string( $original ) || ! is_string( $translated )
        || $translated !== $original ) {
        return $translated;
    }

    $dictionary = cvl_email_ptpt_dictionary();
    return $dictionary[ $original ] ?? $translated;
}

add_filter( 'gettext', 'cvl_email_ptpt_gettext', 30, 3 );
add_filter(
    'gettext_with_context',
    static function ( $translated, $original, $context, $domain ) {
        return cvl_email_ptpt_gettext( $translated, $original, $domain );
    },
    30,
    4
);

add_action(
    'woocommerce_email_header',
    static function ( $heading, $email = null ): void {
        if ( class_exists( 'WC_Email' ) && $email instanceof WC_Email ) {
            $GLOBALS['cvl_email_ptpt_depth'] = (int) ( $GLOBALS['cvl_email_ptpt_depth'] ?? 0 ) + 1;
        }
    },
    0,
    2
);
add_action(
    'woocommerce_email_footer',
    static function ( $email = null ): void {
        if ( class_exists( 'WC_Email' ) && $email instanceof WC_Email ) {
            $GLOBALS['cvl_email_ptpt_depth'] = max( 0, (int) ( $GLOBALS['cvl_email_ptpt_depth'] ?? 0 ) - 1 );
        }
    },
    PHP_INT_MAX,
    1
);

/**
 * As linhas de assunto e os títulos são preparados ANTES do header.
 * Apenas expressões predefinidas do WooCommerce são substituídas, com o nome
 * da loja, número de encomenda e data mantidos intactos.
 */
function cvl_email_ptpt_titles(): array {
    return array(
        'new_order' => array(
            'subject' => array(
                '[{site_title}]: New order #{order_number}' => '[{site_title}]: Nova encomenda #{order_number}',
                "[{site_title}]: You've got a new order: #{order_number}" =>
                    '[{site_title}]: Recebida nova encomenda: #{order_number}',
            ),
            'heading' => array(
                'New Order: #{order_number}' => 'Nova encomenda: #{order_number}',
                'New order: #{order_number}' => 'Nova encomenda: #{order_number}',
            ),
        ),
        'cancelled_order' => array(
            'subject' => array(
                '[{site_title}]: Order #{order_number} has been cancelled' =>
                    '[{site_title}]: Encomenda #{order_number} cancelada',
            ),
            'heading' => array(
                'Order Cancelled: #{order_number}' => 'Encomenda cancelada: #{order_number}',
                'Order cancelled: #{order_number}' => 'Encomenda cancelada: #{order_number}',
            ),
        ),
        'failed_order' => array(
            'subject' => array(
                '[{site_title}]: Order #{order_number} has failed' => '[{site_title}]: Falha na encomenda #{order_number}',
            ),
            'heading' => array(
                'Order Failed: #{order_number}' => 'Falha na encomenda: #{order_number}',
                'Order failed: #{order_number}' => 'Falha na encomenda: #{order_number}',
            ),
        ),
        'customer_processing_order' => array(
            'subject' => array(
                'Your {site_title} order has been received!' => 'Recebemos a sua encomenda na {site_title}!',
            ),
            'heading' => array(
                'Thank you for your order' => 'Obrigado pela sua encomenda',
            ),
        ),
        'customer_on_hold_order' => array(
            'subject' => array(
                'Your {site_title} order has been received!' => 'A sua encomenda na {site_title} está em espera',
                'Your order from {site_title} is on hold' => 'A sua encomenda na {site_title} está em espera',
            ),
            'heading' => array(
                'Thank you for your order' => 'Obrigado pela sua encomenda',
                'Your order is on hold' => 'A sua encomenda está em espera',
            ),
        ),
        'customer_completed_order' => array(
            'subject' => array(
                'Your {site_title} order is now complete' => 'A sua encomenda na {site_title} foi concluída',
                'Your order from {site_title} is on its way!' => 'A sua encomenda da {site_title} está a caminho!',
            ),
            'heading' => array(
                'Thanks for shopping with us' => 'Obrigado por comprar connosco',
                'Good things are heading your way!' => 'A sua encomenda está a caminho!',
            ),
        ),
        'customer_refunded_order' => array(
            'subject' => array(
                'Your {site_title} order #{order_number} has been partially refunded' =>
                    'A sua encomenda #{order_number} na {site_title} foi parcialmente reembolsada',
                'Your {site_title} order #{order_number} has been refunded' =>
                    'A sua encomenda #{order_number} na {site_title} foi reembolsada',
            ),
            'heading' => array(
                'Partial Refund: Order {order_number}' => 'Reembolso parcial: encomenda {order_number}',
                'Partial refund: Order {order_number}' => 'Reembolso parcial: encomenda {order_number}',
                'Order Refunded: {order_number}' => 'Encomenda reembolsada: {order_number}',
                'Order refunded: {order_number}' => 'Encomenda reembolsada: {order_number}',
            ),
        ),
        'customer_invoice' => array(
            'subject' => array(
                'Details for order #{order_number} on {site_title}' =>
                    'Detalhes da encomenda #{order_number} em {site_title}',
            ),
            'heading' => array(
                'Details for order #{order_number}' => 'Detalhes da encomenda #{order_number}',
            ),
        ),
        'customer_note' => array(
            'subject' => array(
                'A note has been added to your order from {site_title}' =>
                    'Foi adicionada uma nota à sua encomenda na {site_title}',
                'Note added to your {site_title} order from {order_date}' =>
                    'Nova nota na sua encomenda na {site_title} de {order_date}',
            ),
            'heading' => array(
                'A note has been added to your order' => 'Foi adicionada uma nota à sua encomenda',
            ),
        ),
        'customer_new_account' => array(
            'subject' => array(
                'Your {site_title} account has been created!' => 'A sua conta na {site_title} foi criada!',
            ),
            'heading' => array(
                'Welcome to {site_title}' => 'Bem-vindo à {site_title}',
            ),
        ),
        'customer_reset_password' => array(
            'subject' => array(
                'Reset your password for {site_title}' => 'Reponha a palavra-passe da {site_title}',
                'Password Reset Request for {site_title}' =>
                    'Pedido de reposição de palavra-passe da {site_title}',
            ),
            'heading' => array(
                'Reset your password' => 'Repor a palavra-passe',
                'Password Reset Request' => 'Pedido de reposição de palavra-passe',
            ),
        ),
        'customer_failed_order' => array(
            'subject' => array(
                'Your order at {site_title} was unsuccessful' =>
                    'Não foi possível concluir a sua encomenda na {site_title}',
            ),
            'heading' => array(
                'Sorry, your order was unsuccessful' => 'Não foi possível concluir a sua encomenda',
            ),
        ),
    );
}

/**
 * Comparação exata com placeholders WooCommerce conhecidos. Nunca altera
 * um assunto escrito manualmente pelo administrador.
 */
function cvl_email_ptpt_match_title( $actual, array $patterns ) {
    if ( ! is_string( $actual ) ) {
        return $actual;
    }

    foreach ( $patterns as $english => $portuguese ) {
        $regex = preg_quote( $english, '~' );
        foreach ( array( 'site_title', 'order_number', 'order_date' ) as $key ) {
            $regex = str_replace(
                preg_quote( '{' . $key . '}', '~' ),
                '(?P<' . $key . '>.*?)',
                $regex
            );
        }
        if ( ! preg_match( '~^' . $regex . '$~uD', $actual, $matches ) ) {
            continue;
        }

        $variables = array();
        foreach ( array( 'site_title', 'order_number', 'order_date' ) as $key ) {
            if ( isset( $matches[ $key ] ) ) {
                $variables[ '{' . $key . '}' ] = $matches[ $key ];
            }
        }
        return strtr( $portuguese, $variables );
    }

    return $actual;
}

foreach ( cvl_email_ptpt_titles() as $email_id => $kinds ) {
    foreach ( $kinds as $kind => $patterns ) {
        add_filter(
            'woocommerce_email_' . $kind . '_' . $email_id,
            static function ( $current ) use ( $patterns ) {
                return cvl_email_ptpt_match_title( $current, $patterns );
            },
            30,
            1
        );
    }
}

// A fatura de encomenda paga utiliza hooks de assunto/título distintos.
$titles = cvl_email_ptpt_titles();
foreach ( array( 'subject', 'heading' ) as $kind ) {
    add_filter(
        'woocommerce_email_' . $kind . '_customer_invoice_paid',
        static function ( $current ) use ( $kind, $titles ) {
            return cvl_email_ptpt_match_title( $current, $titles['customer_invoice'][ $kind ] );
        },
        30,
        1
    );
}

/**
 * Traduz apenas os textos adicionais de origem WooCommerce que tenham sido
 * guardados em inglês nas opções antigas (sem escrever na base de dados).
 */
add_filter(
    'woocommerce_email_get_option',
    static function ( $value, $email, $original, $key ) {
        if ( 'additional_content' !== $key || ! is_string( $value )
            || ! class_exists( 'WC_Email' ) || ! $email instanceof WC_Email ) {
            return $value;
        }

        $dictionary = cvl_email_ptpt_dictionary();
        return $dictionary[ $value ] ?? $value;
    },
    30,
    4
);
