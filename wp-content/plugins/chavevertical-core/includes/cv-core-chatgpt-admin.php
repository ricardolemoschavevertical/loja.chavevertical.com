<?php
/**
 * Botao ChatGPT na barra de administracao (apenas administradores).
 */
defined( 'ABSPATH' ) || exit;

add_action( 'admin_bar_menu', static function ( WP_Admin_Bar $bar ): void {
    if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
        return;
    }
    $bar->add_node( array(
        'id'    => 'cv-chatgpt-page',
        'title' => 'ChatGPT',
        'href'  => 'https://chatgpt.com/',
        'meta'  => array(
            'title'  => 'Abrir esta pagina no ChatGPT e copiar o link',
            'target' => '_blank',
            'rel'    => 'noopener noreferrer',
        ),
    ) );
    $bar->add_node( array(
        'id'     => 'cv-chatgpt-copy',
        'parent' => 'cv-chatgpt-page',
        'title'  => 'Copiar link da pagina',
        'href'   => '#',
    ) );
}, 100 );

add_action( 'admin_footer', 'cv_core_chatgpt_toolbar_script' );
add_action( 'wp_footer', 'cv_core_chatgpt_toolbar_script' );

function cv_core_chatgpt_toolbar_script(): void {
    if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) || ! is_admin_bar_showing() ) {
        return;
    }

    $url = '';
    if ( is_admin() ) {
        $post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0;
        if ( $post_id && get_post( $post_id ) ) {
            $url = get_permalink( $post_id );
        }
    } elseif ( is_singular() ) {
        $url = get_permalink();
    }
    if ( ! $url ) {
        $url = is_admin() ? admin_url() : home_url( add_query_arg( null, null ) );
    }
    ?>
    <script>
    (function () {
        const pageUrl = <?php echo wp_json_encode( esc_url_raw( $url ) ); ?>;
        const openLink = document.querySelector('#wp-admin-bar-cv-chatgpt-page > a');
        const copyLink = document.querySelector('#wp-admin-bar-cv-chatgpt-copy > a');
        if (!openLink) return;
        const copy = async () => {
            try {
                await navigator.clipboard.writeText(pageUrl);
            } catch (_) {
                const input = document.createElement('textarea');
                input.value = pageUrl;
                input.style.position = 'fixed';
                input.style.opacity = '0';
                document.body.appendChild(input);
                input.select();
                document.execCommand('copy');
                input.remove();
            }
        };
        openLink.href = 'https://chatgpt.com/?q=' + encodeURIComponent('Analisa esta página: ' + pageUrl);
        openLink.addEventListener('click', () => { void copy(); });
        if (copyLink) {
            copyLink.addEventListener('click', (event) => {
                event.preventDefault();
                void copy();
            });
        }
    })();
    </script>
    <?php
}
