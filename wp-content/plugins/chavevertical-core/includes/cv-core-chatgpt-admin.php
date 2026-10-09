<?php
/**
 * Botao ChatGPT na barra de administracao (apenas administradores).
 */
defined( 'ABSPATH' ) || exit;

add_action( 'admin_bar_menu', static function ( WP_Admin_Bar $bar ): void {
    if ( ! current_user_can( 'manage_options' ) ) return;
    $bar->add_node( array( 'id' => 'cv-chatgpt-page', 'title' => 'ChatGPT', 'href' => 'https://chatgpt.com/', 'meta' => array( 'target' => '_blank', 'rel' => 'noopener noreferrer' ) ) );
    $items = array(
        array( 'id' => 'cv-chatgpt-screenshot', 'title' => '1. Print screen (copiar imagem)' ),
        array( 'id' => 'cv-chatgpt-github', 'title' => '2. Copiar [$github]' ),
        array( 'id' => 'cv-chatgpt-copy', 'title' => 'Copiar link da página' ),
        array( 'id' => 'cv-chatgpt-screenshot-save', 'title' => 'Guardar screenshot PNG' ),
    );
    foreach ( cv_core_chatgpt_custom_items() as $index => $item ) {
        $items[] = array( 'id' => 'cv-chatgpt-custom-' . $index, 'title' => $item['title'] );
    }
    foreach ( $items as $item ) {
        $bar->add_node( array( 'id' => $item['id'], 'parent' => 'cv-chatgpt-page', 'title' => $item['title'], 'href' => '#' ) );
    }
}, 100 );

function cv_core_chatgpt_custom_items(): array {
    $items = get_option( 'cv_core_chatgpt_submenus', array() );
    if ( ! is_array( $items ) ) return array();
    $out = array();
    foreach ( array_slice( $items, 0, 20 ) as $item ) {
        if ( ! is_array( $item ) || empty( $item['title'] ) || ! isset( $item['text'] ) ) continue;
        $out[] = array( 'title' => sanitize_text_field( $item['title'] ), 'text' => sanitize_textarea_field( $item['text'] ) );
    }
    return $out;
}

add_action( 'admin_menu', static function (): void {
    add_submenu_page( 'chave-vertical', 'Atalhos ChatGPT', 'Atalhos ChatGPT', 'manage_options', 'cv-chatgpt-shortcuts', 'cv_core_chatgpt_render_settings' );
}, 30 );

function cv_core_chatgpt_render_settings(): void {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Sem permissão.' );
    if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['cv_chatgpt_save'] ) ) {
        check_admin_referer( 'cv_chatgpt_save_shortcuts' );
        $titles = isset( $_POST['cv_titles'] ) && is_array( $_POST['cv_titles'] ) ? wp_unslash( $_POST['cv_titles'] ) : array();
        $texts = isset( $_POST['cv_texts'] ) && is_array( $_POST['cv_texts'] ) ? wp_unslash( $_POST['cv_texts'] ) : array();
        $saved = array();
        foreach ( array_slice( $titles, 0, 20 ) as $i => $title ) {
            $title = sanitize_text_field( $title );
            $value = isset( $texts[$i] ) ? sanitize_textarea_field( $texts[$i] ) : '';
            if ( '' !== trim( $title ) && '' !== trim( $value ) ) $saved[] = array( 'title' => $title, 'text' => $value );
        }
        update_option( 'cv_core_chatgpt_submenus', $saved, false );
        echo '<div class="notice notice-success"><p>Atalhos guardados.</p></div>';
    }
    $items = cv_core_chatgpt_custom_items();
    $items[] = array( 'title' => '', 'text' => '' );
    echo '<div class="wrap"><h1>Atalhos ChatGPT</h1><p>Adicione submenus personalizados à barra de administração. Cada atalho copia o texto definido. Apenas administradores podem usar e alterar estes atalhos.</p>';
    echo '<form method="post">';
    wp_nonce_field( 'cv_chatgpt_save_shortcuts' );
    echo '<table class="widefat striped"><thead><tr><th>Nome do submenu</th><th>Texto a copiar</th></tr></thead><tbody id="cv-chatgpt-shortcuts-rows">';
    foreach ( $items as $item ) {
        echo '<tr><td><input style="width:100%" maxlength="100" name="cv_titles[]" value="' . esc_attr( $item['title'] ) . '"></td><td><textarea style="width:100%" rows="3" name="cv_texts[]">' . esc_textarea( $item['text'] ) . '</textarea></td></tr>';
    }
    echo '</tbody></table><p><button type="button" class="button" id="cv-chatgpt-add-row">Adicionar submenu</button> <button type="submit" name="cv_chatgpt_save" value="1" class="button button-primary">Guardar</button></p></form></div>';
    echo '<script>document.getElementById("cv-chatgpt-add-row").addEventListener("click",function(){const body=document.getElementById("cv-chatgpt-shortcuts-rows");if(body.rows.length>=20)return;const row=body.insertRow();const a=row.insertCell(),b=row.insertCell();const input=document.createElement("input"),textarea=document.createElement("textarea");input.name="cv_titles[]";input.maxLength=100;input.style.width="100%";textarea.name="cv_texts[]";textarea.rows=3;textarea.style.width="100%";a.appendChild(input);b.appendChild(textarea);});</script>';
}

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
        const githubLink = document.querySelector('#wp-admin-bar-cv-chatgpt-github > a');
        const customItems = <?php echo wp_json_encode( cv_core_chatgpt_custom_items() ); ?>;
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
        const copyText = async (value) => {
            try { await navigator.clipboard.writeText(value); }
            catch (_) {
                const field = document.createElement('textarea');
                field.value = value;
                field.style.position = 'fixed';
                field.style.opacity = '0';
                document.body.appendChild(field);
                field.select();
                document.execCommand('copy');
                field.remove();
            }
        };
        if (githubLink) githubLink.addEventListener('click', event => {
            event.preventDefault();
            void copyText('[$github](app://connector_76869538009648d5b282a4bb21c3d157)');
        });
        customItems.forEach((item, index) => {
            const link = document.querySelector('#wp-admin-bar-cv-chatgpt-custom-' + index + ' > a');
            if (link) link.addEventListener('click', event => {
                event.preventDefault();
                void copyText(item.text);
            });
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
