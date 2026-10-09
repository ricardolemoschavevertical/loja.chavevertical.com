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
    $bar->add_node( array(
        'id'     => 'cv-chatgpt-screenshot',
        'parent' => 'cv-chatgpt-page',
        'title'  => 'Copiar screenshot (separador atual)',
        'href'   => '#',
    ) );
    $bar->add_node( array(
        'id'     => 'cv-chatgpt-screenshot-save',
        'parent' => 'cv-chatgpt-page',
        'title'  => 'Guardar screenshot PNG',
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
        const screenshotLink = document.querySelector('#wp-admin-bar-cv-chatgpt-screenshot > a');
        const screenshotSaveLink = document.querySelector('#wp-admin-bar-cv-chatgpt-screenshot-save > a');
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
        // O browser exige que o utilizador escolha e autorize o separador a capturar.
        // Nunca se envia a imagem para servidores: e copiada ou guardada localmente.
        const captureScreenshot = async (download) => {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getDisplayMedia) {
                window.alert('Este browser nao suporta capturas de ecrã nesta pagina.');
                return;
            }
            let stream;
            try {
                stream = await navigator.mediaDevices.getDisplayMedia({
                    video: { displaySurface: 'browser' },
                    audio: false,
                    preferCurrentTab: true,
                    selfBrowserSurface: 'include'
                });
                const video = document.createElement('video');
                video.muted = true;
                video.playsInline = true;
                video.srcObject = stream;
                await video.play();
                if (!video.videoWidth) {
                    await new Promise((resolve) => {
                        video.addEventListener('loadedmetadata', resolve, { once: true });
                    });
                }
                const canvas = document.createElement('canvas');
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                canvas.getContext('2d').drawImage(video, 0, 0);
                const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/png'));
                if (!blob) throw new Error('Falha ao criar a imagem PNG');
                if (download) {
                    const anchor = document.createElement('a');
                    const objectUrl = URL.createObjectURL(blob);
                    anchor.href = objectUrl;
                    anchor.download = 'cv-screenshot-' + Date.now() + '.png';
                    document.body.appendChild(anchor);
                    anchor.click();
                    anchor.remove();
                    setTimeout(() => URL.revokeObjectURL(objectUrl), 5000);
                } else {
                    if (!navigator.clipboard || !window.ClipboardItem) {
                        throw new Error('Copia de imagens nao suportada. Utilize Guardar screenshot PNG.');
                    }
                    await navigator.clipboard.write([new ClipboardItem({ 'image/png': blob })]);
                    window.alert('Screenshot copiado. Cole no ChatGPT com Ctrl+V.');
                }
            } catch (error) {
                if (error && (error.name === 'NotAllowedError' || error.name === 'AbortError')) return;
                window.alert('Nao foi possivel capturar o ecrã: ' + (error && error.message ? error.message : 'erro desconhecido'));
            } finally {
                if (stream) stream.getTracks().forEach(track => track.stop());
            }
        };
        if (screenshotLink) screenshotLink.addEventListener('click', event => {
            event.preventDefault();
            void captureScreenshot(false);
        });
        if (screenshotSaveLink) screenshotSaveLink.addEventListener('click', event => {
            event.preventDefault();
            void captureScreenshot(true);
        });
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
