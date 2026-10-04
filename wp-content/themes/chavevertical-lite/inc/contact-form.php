<?php
/**
 * Contact form handler for the Contactos page.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Redirects back to the contact page with a controlled status flag.
 */
function cvl_contact_form_redirect( string $status ) {
    $url = add_query_arg(
        'contact_status',
        sanitize_key( $status ),
        cvl_contact_page_url()
    );

    wp_safe_redirect( $url );
    exit;
}

/**
 * Handles authenticated and guest contact form submissions.
 */
function cvl_handle_contact_form_submission() {
    $nonce = isset( $_POST['cvl_contact_nonce'] )
        ? sanitize_text_field( wp_unslash( $_POST['cvl_contact_nonce'] ) )
        : '';

    if ( ! wp_verify_nonce( $nonce, 'cvl_contact_submit' ) ) {
        cvl_contact_form_redirect( 'invalid' );
    }

    $honeypot = isset( $_POST['website'] )
        ? sanitize_text_field( wp_unslash( $_POST['website'] ) )
        : '';

    if ( '' !== $honeypot ) {
        cvl_contact_form_redirect( 'sent' );
    }

    $started = isset( $_POST['cvl_started'] ) ? absint( $_POST['cvl_started'] ) : 0;

    if ( $started && ( time() - $started ) < 2 ) {
        cvl_contact_form_redirect( 'invalid' );
    }

    $name = isset( $_POST['name'] )
        ? sanitize_text_field( wp_unslash( $_POST['name'] ) )
        : '';
    $email = isset( $_POST['email'] )
        ? sanitize_email( wp_unslash( $_POST['email'] ) )
        : '';
    $phone = isset( $_POST['phone'] )
        ? sanitize_text_field( wp_unslash( $_POST['phone'] ) )
        : '';
    $subject = isset( $_POST['subject'] )
        ? sanitize_text_field( wp_unslash( $_POST['subject'] ) )
        : '';
    $message = isset( $_POST['message'] )
        ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) )
        : '';
    $consent = ! empty( $_POST['consent'] );

    if ( '' === $name || ! is_email( $email ) || '' === $subject || '' === $message || ! $consent ) {
        cvl_contact_form_redirect( 'invalid' );
    }

    $source = wp_get_referer();

    if ( ! $source ) {
        $source = cvl_contact_page_url();
    }

    $internal_to = 'ricardo@chavevertical.com';
    $internal_subject = sprintf(
        '[Contacto Loja] %1$s — %2$s',
        $subject,
        $name
    );

    $internal_body  = '<h2>Novo pedido de contacto</h2>';
    $internal_body .= '<p><strong>Nome:</strong> ' . esc_html( $name ) . '</p>';
    $internal_body .= '<p><strong>Email:</strong> ' . esc_html( $email ) . '</p>';
    $internal_body .= '<p><strong>Telefone:</strong> ' . esc_html( $phone ?: 'Não indicado' ) . '</p>';
    $internal_body .= '<p><strong>Assunto:</strong> ' . esc_html( $subject ) . '</p>';
    $internal_body .= '<p><strong>Mensagem:</strong><br>' . nl2br( esc_html( $message ) ) . '</p>';
    $internal_body .= '<hr>';
    $internal_body .= '<p><small>Origem: ' . esc_html( $source ) . '<br>Enviado em: ' . esc_html( wp_date( 'd/m/Y H:i' ) ) . '</small></p>';

    $internal_headers = array(
        'Content-Type: text/html; charset=UTF-8',
        sprintf( 'Reply-To: %1$s <%2$s>', $name, $email ),
    );

    $internal_sent = wp_mail(
        $internal_to,
        $internal_subject,
        $internal_body,
        $internal_headers
    );

    if ( ! $internal_sent ) {
        cvl_contact_form_redirect( 'error' );
    }

    $client_subject = 'Recebemos o seu pedido — Chave Vertical';
    $client_body  = '<p>Olá ' . esc_html( $name ) . ',</p>';
    $client_body .= '<p>Recebemos o seu pedido de contacto e a nossa equipa irá analisá-lo.</p>';
    $client_body .= '<p><strong>Assunto:</strong> ' . esc_html( $subject ) . '</p>';
    $client_body .= '<p><strong>A sua mensagem:</strong><br>' . nl2br( esc_html( $message ) ) . '</p>';
    $client_body .= '<p>Entraremos em contacto através dos dados indicados.</p>';
    $client_body .= '<p>Obrigado,<br><strong>Chave Vertical</strong></p>';

    $client_headers = array(
        'Content-Type: text/html; charset=UTF-8',
    );

    $client_sent = wp_mail(
        $email,
        $client_subject,
        $client_body,
        $client_headers
    );

    cvl_contact_form_redirect( $client_sent ? 'sent' : 'partial' );
}
add_action( 'admin_post_nopriv_cvl_contact_submit', 'cvl_handle_contact_form_submission' );
add_action( 'admin_post_cvl_contact_submit', 'cvl_handle_contact_form_submission' );
