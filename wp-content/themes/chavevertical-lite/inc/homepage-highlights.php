<?php
defined( 'ABSPATH' ) || exit;

function cvl_homepage_highlights_config_path() {
    return get_template_directory() . '/config/homepage-highlights.json';
}

function cvl_homepage_highlights_fallback_cards() {
    return array(
        array(
            'eyebrow'                => 'MAIS PROCURADO',
            'title'                  => 'Equipar uma oficina',
            'description'            => 'Carros de ferramentas, elevadores, máquinas e equipamentos para utilização profissional.',
            'cta'                    => 'VER SOLUÇÕES →',
            'url'                    => home_url( '/?s=oficina&post_type=product' ),
            'background'             => '#101820',
            'text_color'             => '#ffffff',
            'image_id'               => 0,
            'image_url'              => '',
            'fallback_category_slug' => 'oficina-automovel',
        ),
        array(
            'eyebrow'                => 'AR COMPRIMIDO',
            'title'                  => 'Produção e tratamento de ar',
            'description'            => 'Compressores, secadores, enroladores, reservatórios e acessórios pneumáticos.',
            'cta'                    => 'EXPLORAR →',
            'url'                    => home_url( '/?s=compressor&post_type=product' ),
            'background'             => '#006b62',
            'text_color'             => '#ffffff',
            'image_id'               => 0,
            'image_url'              => '',
            'fallback_category_slug' => 'ar-comprimido',
        ),
        array(
            'eyebrow'                => 'MOVIMENTAÇÃO',
            'title'                  => 'Elevação e logística',
            'description'            => 'Porta-paletes, mesas elevatórias, gruas e soluções para armazém e oficina.',
            'cta'                    => 'VER EQUIPAMENTO →',
            'url'                    => home_url( '/?s=porta-paletes&post_type=product' ),
            'background'             => '#f5f7f7',
            'text_color'             => '#182129',
            'image_id'               => 0,
            'image_url'              => '',
            'fallback_category_slug' => 'elevacao-e-carga',
        ),
        array(
            'eyebrow'                => 'NÃO ENCONTRA?',
            'title'                  => 'Tratamos da pesquisa por si.',
            'description'            => 'Indique a aplicação, referência ou características técnicas e ajudamos a encontrar a solução.',
            'cta'                    => 'PEDIR ACONSELHAMENTO →',
            'url'                    => home_url( '/contactos/' ),
            'background'             => '#b51f1f',
            'text_color'             => '#ffffff',
            'image_id'               => 0,
            'image_url'              => '',
            'fallback_category_slug' => '',
        ),
    );
}

function cvl_normalize_homepage_highlight( $card, $fallback = array() ) {
    $base = wp_parse_args(
        is_array( $fallback ) ? $fallback : array(),
        array(
            'eyebrow'                => '',
            'title'                  => '',
            'description'            => '',
            'cta'                    => '',
            'url'                    => '#',
            'background'             => '#182129',
            'text_color'             => '#ffffff',
            'image_id'               => 0,
            'image_url'              => '',
            'fallback_category_slug' => '',
        )
    );

    $card = wp_parse_args( is_array( $card ) ? $card : array(), $base );

    $image_url = isset( $card['image_url'] ) ? trim( (string) $card['image_url'] ) : '';
    if ( 0 !== strpos( $image_url, 'theme://' ) ) {
        $image_url = esc_url_raw( $image_url );
    } else {
        $image_url = sanitize_text_field( $image_url );
    }

    $background = sanitize_hex_color( $card['background'] );
    $text_color = sanitize_hex_color( $card['text_color'] );

    return array(
        'eyebrow'                => sanitize_text_field( $card['eyebrow'] ),
        'title'                  => sanitize_text_field( $card['title'] ),
        'description'            => sanitize_textarea_field( $card['description'] ),
        'cta'                    => sanitize_text_field( $card['cta'] ),
        'url'                    => esc_url_raw( $card['url'] ),
        'background'             => $background ? $background : $base['background'],
        'text_color'             => $text_color ? $text_color : $base['text_color'],
        'image_id'               => absint( $card['image_id'] ),
        'image_url'              => $image_url,
        'fallback_category_slug' => sanitize_title( $card['fallback_category_slug'] ),
    );
}

function cvl_homepage_highlights_github_cards() {
    static $cards = null;

    if ( null !== $cards ) {
        return $cards;
    }

    $fallback = cvl_homepage_highlights_fallback_cards();
    $cards    = array();
    $path     = cvl_homepage_highlights_config_path();

    if ( is_readable( $path ) ) {
        $decoded = json_decode( (string) file_get_contents( $path ), true );

        if ( is_array( $decoded ) ) {
            $decoded = array_values( $decoded );

            for ( $i = 0; $i < 4; $i++ ) {
                $cards[] = cvl_normalize_homepage_highlight(
                    isset( $decoded[ $i ] ) ? $decoded[ $i ] : array(),
                    $fallback[ $i ]
                );
            }
        }
    }

    if ( empty( $cards ) ) {
        foreach ( $fallback as $card ) {
            $cards[] = cvl_normalize_homepage_highlight( $card, $card );
        }
    }

    return $cards;
}

function cvl_homepage_highlights_source() {
    $source = get_option( 'cvl_homepage_highlights_source', 'github' );
    return in_array( $source, array( 'github', 'admin' ), true ) ? $source : 'github';
}

function cvl_get_homepage_highlights() {
    $github_cards = cvl_homepage_highlights_github_cards();

    if ( 'admin' !== cvl_homepage_highlights_source() ) {
        return apply_filters( 'cvl_homepage_highlights', $github_cards );
    }

    $admin_cards = get_option( 'cvl_homepage_highlights_admin', array() );
    $cards       = array();

    for ( $i = 0; $i < 4; $i++ ) {
        $cards[] = cvl_normalize_homepage_highlight(
            isset( $admin_cards[ $i ] ) ? $admin_cards[ $i ] : array(),
            $github_cards[ $i ]
        );
    }

    return apply_filters( 'cvl_homepage_highlights', $cards );
}

function cvl_homepage_highlight_image_url( $card ) {
    if ( ! is_array( $card ) ) {
        return '';
    }

    $image_id = isset( $card['image_id'] ) ? absint( $card['image_id'] ) : 0;
    if ( $image_id ) {
        $url = wp_get_attachment_image_url( $image_id, 'large' );
        if ( $url ) {
            return $url;
        }
    }

    $image_url = isset( $card['image_url'] ) ? trim( (string) $card['image_url'] ) : '';
    if ( $image_url ) {
        if ( 0 === strpos( $image_url, 'theme://' ) ) {
            return get_template_directory_uri() . '/' . ltrim( substr( $image_url, 8 ), '/' );
        }
        return $image_url;
    }

    $slug = isset( $card['fallback_category_slug'] ) ? sanitize_title( $card['fallback_category_slug'] ) : '';
    if ( $slug && taxonomy_exists( 'product_cat' ) ) {
        $term = get_term_by( 'slug', $slug, 'product_cat' );

        if ( $term && ! is_wp_error( $term ) ) {
            $thumbnail_id = absint( get_term_meta( $term->term_id, 'thumbnail_id', true ) );
            if ( $thumbnail_id ) {
                $url = wp_get_attachment_image_url( $thumbnail_id, 'large' );
                if ( $url ) {
                    return $url;
                }
            }
        }
    }

    return '';
}

add_action( 'admin_menu', function () {
    add_submenu_page(
        'woocommerce',
        __( 'Destaques Homepage', 'chavevertical-lite' ),
        __( 'Destaques Homepage', 'chavevertical-lite' ),
        'manage_woocommerce',
        'cvl-homepage-highlights',
        'cvl_render_homepage_highlights_admin'
    );
}, 45 );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
    if ( 'woocommerce_page_cvl-homepage-highlights' !== $hook ) {
        return;
    }

    wp_enqueue_media();
    wp_enqueue_script( 'jquery' );

    wp_add_inline_script(
        'jquery',
        <<<'JS'
(function($){
    $(document).on('click','.cvl-highlight-select-image',function(e){
        e.preventDefault();
        var card = $(this).closest('.cvl-highlight-admin-card');
        var frame = wp.media({
            title: 'Selecionar imagem do destaque',
            button: { text: 'Usar esta imagem' },
            multiple: false
        });

        frame.on('select',function(){
            var attachment = frame.state().get('selection').first().toJSON();
            card.find('.cvl-highlight-image-id').val(attachment.id || '');
            card.find('.cvl-highlight-image-url').val('');
            card.find('.cvl-highlight-preview')
                .attr('src', attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url)
                .show();
            card.find('.cvl-highlight-preview-empty').hide();
        });

        frame.open();
    });

    $(document).on('click','.cvl-highlight-remove-image',function(e){
        e.preventDefault();
        var card = $(this).closest('.cvl-highlight-admin-card');
        card.find('.cvl-highlight-image-id').val('');
        card.find('.cvl-highlight-image-url').val('');
        card.find('.cvl-highlight-preview').hide().attr('src','');
        card.find('.cvl-highlight-preview-empty').show();
    });
})(jQuery);
JS
    );
} );

function cvl_render_homepage_highlights_admin() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        return;
    }

    $source       = cvl_homepage_highlights_source();
    $github_cards = cvl_homepage_highlights_github_cards();
    $saved_cards  = get_option( 'cvl_homepage_highlights_admin', array() );
    $cards        = array();

    for ( $i = 0; $i < 4; $i++ ) {
        $cards[] = cvl_normalize_homepage_highlight(
            isset( $saved_cards[ $i ] ) ? $saved_cards[ $i ] : array(),
            $github_cards[ $i ]
        );
    }
    ?>
    <div class="wrap cvl-highlights-admin">
        <h1><?php esc_html_e( 'Destaques Homepage', 'chavevertical-lite' ); ?></h1>

        <?php if ( isset( $_GET['updated'] ) ) : ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Destaques guardados.', 'chavevertical-lite' ); ?></p></div>
        <?php endif; ?>

        <p><?php esc_html_e( 'Estes são os quatro quadrados apresentados imediatamente por baixo das categorias na homepage.', 'chavevertical-lite' ); ?></p>

        <div class="cvl-highlights-source-help">
            <strong><?php esc_html_e( 'Edição pelo GitHub:', 'chavevertical-lite' ); ?></strong>
            <code>wp-content/themes/chavevertical-lite/config/homepage-highlights.json</code>
        </div>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="cvl_save_homepage_highlights">
            <?php wp_nonce_field( 'cvl_save_homepage_highlights', 'cvl_homepage_highlights_nonce' ); ?>

            <fieldset class="cvl-highlights-source">
                <legend><strong><?php esc_html_e( 'Fonte ativa', 'chavevertical-lite' ); ?></strong></legend>
                <label><input type="radio" name="source" value="admin" <?php checked( $source, 'admin' ); ?>> <?php esc_html_e( 'Usar valores do Admin', 'chavevertical-lite' ); ?></label>
                <label><input type="radio" name="source" value="github" <?php checked( $source, 'github' ); ?>> <?php esc_html_e( 'Usar configuração do GitHub', 'chavevertical-lite' ); ?></label>
            </fieldset>

            <div class="cvl-highlight-admin-grid">
                <?php foreach ( $cards as $index => $card ) : ?>
                    <?php $preview = cvl_homepage_highlight_image_url( $card ); ?>
                    <section class="cvl-highlight-admin-card">
                        <h2><?php echo esc_html( sprintf( 'Destaque %d', $index + 1 ) ); ?></h2>

                        <div class="cvl-highlight-admin-preview">
                            <img class="cvl-highlight-preview" src="<?php echo esc_url( $preview ); ?>" alt="" <?php echo $preview ? '' : 'style="display:none"'; ?>>
                            <div class="cvl-highlight-preview-empty" <?php echo $preview ? 'style="display:none"' : ''; ?>><?php esc_html_e( 'Sem imagem personalizada', 'chavevertical-lite' ); ?></div>
                        </div>

                        <input class="cvl-highlight-image-id" type="hidden" name="cards[<?php echo esc_attr( $index ); ?>][image_id]" value="<?php echo esc_attr( $card['image_id'] ); ?>">
                        <input class="cvl-highlight-image-url" type="hidden" name="cards[<?php echo esc_attr( $index ); ?>][image_url]" value="<?php echo esc_attr( $card['image_url'] ); ?>">
                        <input type="hidden" name="cards[<?php echo esc_attr( $index ); ?>][fallback_category_slug]" value="<?php echo esc_attr( $card['fallback_category_slug'] ); ?>">

                        <p class="cvl-highlight-media-actions">
                            <button type="button" class="button cvl-highlight-select-image"><?php esc_html_e( 'Escolher imagem', 'chavevertical-lite' ); ?></button>
                            <button type="button" class="button-link-delete cvl-highlight-remove-image"><?php esc_html_e( 'Remover imagem personalizada', 'chavevertical-lite' ); ?></button>
                        </p>

                        <label>
                            <span><?php esc_html_e( 'Texto superior', 'chavevertical-lite' ); ?></span>
                            <input type="text" name="cards[<?php echo esc_attr( $index ); ?>][eyebrow]" value="<?php echo esc_attr( $card['eyebrow'] ); ?>">
                        </label>

                        <label>
                            <span><?php esc_html_e( 'Título', 'chavevertical-lite' ); ?></span>
                            <input type="text" name="cards[<?php echo esc_attr( $index ); ?>][title]" value="<?php echo esc_attr( $card['title'] ); ?>">
                        </label>

                        <label>
                            <span><?php esc_html_e( 'Descrição', 'chavevertical-lite' ); ?></span>
                            <textarea rows="4" name="cards[<?php echo esc_attr( $index ); ?>][description]"><?php echo esc_textarea( $card['description'] ); ?></textarea>
                        </label>

                        <label>
                            <span><?php esc_html_e( 'Texto do botão', 'chavevertical-lite' ); ?></span>
                            <input type="text" name="cards[<?php echo esc_attr( $index ); ?>][cta]" value="<?php echo esc_attr( $card['cta'] ); ?>">
                        </label>

                        <label>
                            <span><?php esc_html_e( 'Link', 'chavevertical-lite' ); ?></span>
                            <input type="text" name="cards[<?php echo esc_attr( $index ); ?>][url]" value="<?php echo esc_attr( $card['url'] ); ?>">
                        </label>

                        <div class="cvl-highlight-color-row">
                            <label><span><?php esc_html_e( 'Fundo', 'chavevertical-lite' ); ?></span><input type="color" name="cards[<?php echo esc_attr( $index ); ?>][background]" value="<?php echo esc_attr( $card['background'] ); ?>"></label>
                            <label><span><?php esc_html_e( 'Texto', 'chavevertical-lite' ); ?></span><input type="color" name="cards[<?php echo esc_attr( $index ); ?>][text_color]" value="<?php echo esc_attr( $card['text_color'] ); ?>"></label>
                        </div>
                    </section>
                <?php endforeach; ?>
            </div>

            <?php submit_button( __( 'Guardar destaques', 'chavevertical-lite' ) ); ?>
        </form>
    </div>

    <style>
        .cvl-highlights-source-help{margin:16px 0;padding:12px 14px;background:#fff;border-left:4px solid #17820f}
        .cvl-highlights-source{margin:18px 0;padding:14px;background:#fff;border:1px solid #dcdcde}
        .cvl-highlights-source label{margin-right:24px}
        .cvl-highlight-admin-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;max-width:1200px}
        .cvl-highlight-admin-card{padding:18px;background:#fff;border:1px solid #dcdcde;border-radius:8px}
        .cvl-highlight-admin-card h2{margin-top:0}
        .cvl-highlight-admin-preview{height:180px;margin-bottom:12px;display:grid;place-items:center;overflow:hidden;background:#f3f5f5;border:1px solid #e3e6e6}
        .cvl-highlight-admin-preview img{width:100%;height:100%;object-fit:cover}
        .cvl-highlight-preview-empty{color:#6b7377}
        .cvl-highlight-admin-card label{display:block;margin-top:12px}
        .cvl-highlight-admin-card label>span{display:block;margin-bottom:5px;font-weight:600}
        .cvl-highlight-admin-card input[type="text"],.cvl-highlight-admin-card textarea{width:100%}
        .cvl-highlight-media-actions{display:flex;align-items:center;gap:12px}
        .cvl-highlight-color-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
        .cvl-highlight-color-row input[type="color"]{width:100%;height:38px;padding:2px}
        @media(max-width:800px){.cvl-highlight-admin-grid{grid-template-columns:1fr}}
    </style>
    <?php
}

add_action( 'admin_post_cvl_save_homepage_highlights', function () {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_die( esc_html__( 'Sem permissão para alterar os destaques.', 'chavevertical-lite' ) );
    }

    check_admin_referer( 'cvl_save_homepage_highlights', 'cvl_homepage_highlights_nonce' );

    $source = isset( $_POST['source'] ) ? sanitize_key( wp_unslash( $_POST['source'] ) ) : 'github';
    $source = in_array( $source, array( 'github', 'admin' ), true ) ? $source : 'github';

    $posted_cards = isset( $_POST['cards'] ) && is_array( $_POST['cards'] ) ? wp_unslash( $_POST['cards'] ) : array();
    $github_cards = cvl_homepage_highlights_github_cards();
    $cards        = array();

    for ( $i = 0; $i < 4; $i++ ) {
        $cards[] = cvl_normalize_homepage_highlight(
            isset( $posted_cards[ $i ] ) ? $posted_cards[ $i ] : array(),
            $github_cards[ $i ]
        );
    }

    update_option( 'cvl_homepage_highlights_source', $source, false );
    update_option( 'cvl_homepage_highlights_admin', $cards, false );

    wp_safe_redirect(
        add_query_arg(
            array(
                'page'    => 'cvl-homepage-highlights',
                'updated' => '1',
            ),
            admin_url( 'admin.php' )
        )
    );
    exit;
} );
