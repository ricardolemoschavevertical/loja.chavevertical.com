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
            'rotation_image_ids'     => array(),
            'rotation_image_urls'    => array(),
            'rotation_seconds'       => 5,
            'fallback_category_slug' => '',
        ),
    );
}

function cvl_homepage_hero_fallback() {
    return array(
        'main' => array(
            'eyebrow'                => 'ELEVAÇÃO PROFISSIONAL',
            'title'                  => 'Elevador 2 Colunas 4T',
            'title_accent'           => 'Basic-Line KROFTOOLS',
            'description'            => 'Robusto, fiável e preparado para utilização profissional em oficina.',
            'cta'                    => 'VER PRODUTO',
            'url'                    => 'https://loja.chavevertical.com/produto/elevador-2-colunas-4t-basic-line-220v-kroftools/',
            'secondary_cta'          => 'Ver elevadores',
            'secondary_url'          => '/categoria-produto/elevadores-elevadores/',
            'badge'                  => 'ELEVAÇÃO PROFISSIONAL',
            'background'             => '#f4f5f5',
            'text_color'             => '#101820',
            'accent_color'           => '#d62828',
            'image_id'               => 0,
            'image_url'              => 'https://imagens.chavevertical.com/2024/01/elevador-2-colunas-4t-basic-line-kroftools-4000kg-monofasico-1549282-1.webp',
            'fallback_category_slug' => 'elevadores-elevadores',
            'specs'                  => array(
                array( 'value' => '4000 kg', 'label' => 'Capacidade de carga' ),
                array( 'value' => '220V', 'label' => 'Monofásico' ),
                array( 'value' => '40–60 s', 'label' => 'Tempo de elevação' ),
                array( 'value' => '2824 mm', 'label' => 'Altura total' ),
                array( 'value' => '3185 mm', 'label' => 'Largura total' ),
                array( 'value' => '2820 mm', 'label' => 'Distância entre colunas' ),
                array( 'value' => '2500 mm', 'label' => 'Largura de passagem' ),
            ),
        ),
        'side' => array(
            'eyebrow'                => 'CATÁLOGO PROFISSIONAL',
            'title'                  => 'Tudo num só lugar.',
            'title_accent'           => 'Pronto a trabalhar.',
            'description'            => 'Mais de 30.000 referências para oficina, indústria e construção.',
            'cta'                    => 'EXPLORAR',
            'url'                    => '',
            'secondary_cta'          => '',
            'secondary_url'          => '',
            'badge'                  => '',
            'background'             => '#f0f2e7',
            'text_color'             => '#17302c',
            'accent_color'           => '#17302c',
            'image_id'               => 0,
            'image_url'              => '',
            'fallback_category_slug' => 'ferramentas-electricas',
        ),
    );
}

function cvl_normalize_homepage_hero_panel( $panel, $fallback = array() ) {
    $base = wp_parse_args(
        is_array( $fallback ) ? $fallback : array(),
        array(
            'eyebrow'                => '',
            'title'                  => '',
            'title_accent'           => '',
            'description'            => '',
            'cta'                    => '',
            'url'                    => '',
            'secondary_cta'          => '',
            'secondary_url'          => '',
            'badge'                  => '',
            'background'             => '#f4f5f5',
            'text_color'             => '#101820',
            'accent_color'           => '#d62828',
            'image_id'               => 0,
            'image_url'              => '',
            'fallback_category_slug' => '',
            'specs'                  => array(),
        )
    );

    $panel = wp_parse_args( is_array( $panel ) ? $panel : array(), $base );

    $image_url = isset( $panel['image_url'] ) ? trim( (string) $panel['image_url'] ) : '';
    if ( 0 === strpos( $image_url, 'theme://' ) ) {
        $image_url = sanitize_text_field( $image_url );
    } else {
        $image_url = esc_url_raw( $image_url );
    }

    $background   = sanitize_hex_color( $panel['background'] );
    $text_color   = sanitize_hex_color( $panel['text_color'] );
    $accent_color = sanitize_hex_color( $panel['accent_color'] );

    $specs = array();
    $raw_specs = isset( $panel['specs'] ) && is_array( $panel['specs'] ) ? $panel['specs'] : array();
    foreach ( array_slice( $raw_specs, 0, 7 ) as $spec ) {
        if ( ! is_array( $spec ) ) {
            continue;
        }
        $value = sanitize_text_field( $spec['value'] ?? '' );
        $label = sanitize_text_field( $spec['label'] ?? '' );
        if ( '' !== $value || '' !== $label ) {
            $specs[] = array( 'value' => $value, 'label' => $label );
        }
    }

    return array(
        'eyebrow'                => sanitize_text_field( $panel['eyebrow'] ),
        'title'                  => sanitize_text_field( $panel['title'] ),
        'title_accent'           => sanitize_text_field( $panel['title_accent'] ),
        'description'            => sanitize_textarea_field( $panel['description'] ),
        'cta'                    => sanitize_text_field( $panel['cta'] ),
        'url'                    => esc_url_raw( $panel['url'] ),
        'secondary_cta'          => sanitize_text_field( $panel['secondary_cta'] ),
        'secondary_url'          => esc_url_raw( $panel['secondary_url'] ),
        'badge'                  => sanitize_text_field( $panel['badge'] ),
        'background'             => $background ? $background : $base['background'],
        'text_color'             => $text_color ? $text_color : $base['text_color'],
        'accent_color'           => $accent_color ? $accent_color : $base['accent_color'],
        'image_id'               => absint( $panel['image_id'] ),
        'image_url'              => $image_url,
        'fallback_category_slug' => sanitize_title( $panel['fallback_category_slug'] ),
        'specs'                  => $specs,
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

    $rotation_ids_raw = isset( $card['rotation_image_ids'] ) ? $card['rotation_image_ids'] : array();
    if ( is_string( $rotation_ids_raw ) ) {
        $rotation_ids_raw = preg_split( '/\s*,\s*/', $rotation_ids_raw, -1, PREG_SPLIT_NO_EMPTY );
    }
    $rotation_image_ids = array_values(
        array_unique(
            array_filter(
                array_map( 'absint', is_array( $rotation_ids_raw ) ? $rotation_ids_raw : array() )
            )
        )
    );

    $rotation_urls_raw = isset( $card['rotation_image_urls'] ) ? $card['rotation_image_urls'] : array();
    if ( is_string( $rotation_urls_raw ) ) {
        $rotation_urls_raw = preg_split( '/\r\n|\r|\n|,/', $rotation_urls_raw, -1, PREG_SPLIT_NO_EMPTY );
    }
    $rotation_image_urls = array();
    foreach ( is_array( $rotation_urls_raw ) ? $rotation_urls_raw : array() as $rotation_url ) {
        $rotation_url = trim( (string) $rotation_url );
        if ( '' === $rotation_url ) {
            continue;
        }
        $rotation_image_urls[] = 0 === strpos( $rotation_url, 'theme://' )
            ? sanitize_text_field( $rotation_url )
            : esc_url_raw( $rotation_url );
    }
    $rotation_image_urls = array_values( array_unique( array_filter( $rotation_image_urls ) ) );

    $rotation_seconds = isset( $card['rotation_seconds'] ) ? absint( $card['rotation_seconds'] ) : 5;
    $rotation_seconds = max( 2, min( 60, $rotation_seconds ) );

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
        'rotation_image_ids'     => $rotation_image_ids,
        'rotation_image_urls'    => $rotation_image_urls,
        'rotation_seconds'       => $rotation_seconds,
        'fallback_category_slug' => sanitize_title( $card['fallback_category_slug'] ),
    );
}

function cvl_homepage_highlights_github_payload() {
    static $payload = null;

    if ( null !== $payload ) {
        return $payload;
    }

    $fallback_cards = cvl_homepage_highlights_fallback_cards();
    $fallback_hero  = cvl_homepage_hero_fallback();
    $payload        = array(
        'hero'       => $fallback_hero,
        'highlights' => $fallback_cards,
    );

    $path = cvl_homepage_highlights_config_path();

    if ( ! is_readable( $path ) ) {
        return $payload;
    }

    $decoded = json_decode( (string) file_get_contents( $path ), true );

    if ( ! is_array( $decoded ) ) {
        return $payload;
    }

    // Compatibilidade com o formato antigo: array simples com os quatro destaques.
    if ( isset( $decoded['hero'] ) || isset( $decoded['highlights'] ) ) {
        $raw_hero  = isset( $decoded['hero'] ) && is_array( $decoded['hero'] ) ? $decoded['hero'] : array();
        $raw_cards = isset( $decoded['highlights'] ) && is_array( $decoded['highlights'] ) ? array_values( $decoded['highlights'] ) : array();
    } else {
        $raw_hero  = array();
        $raw_cards = array_values( $decoded );
    }

    foreach ( array( 'main', 'side' ) as $hero_key ) {
        $payload['hero'][ $hero_key ] = cvl_normalize_homepage_hero_panel(
            isset( $raw_hero[ $hero_key ] ) ? $raw_hero[ $hero_key ] : array(),
            $fallback_hero[ $hero_key ]
        );
    }

    $payload['highlights'] = array();

    for ( $i = 0; $i < 4; $i++ ) {
        $payload['highlights'][] = cvl_normalize_homepage_highlight(
            isset( $raw_cards[ $i ] ) ? $raw_cards[ $i ] : array(),
            $fallback_cards[ $i ]
        );
    }

    return $payload;
}

function cvl_homepage_highlights_github_cards() {
    $payload = cvl_homepage_highlights_github_payload();
    return $payload['highlights'];
}

function cvl_homepage_highlights_github_hero() {
    $payload = cvl_homepage_highlights_github_payload();
    return $payload['hero'];
}

function cvl_get_homepage_hero() {
    $github_hero = cvl_homepage_highlights_github_hero();

    if ( 'admin' !== cvl_homepage_highlights_source() ) {
        return apply_filters( 'cvl_homepage_hero', $github_hero );
    }

    $saved = get_option( 'cvl_homepage_hero_admin', array() );
    $hero  = array();

    foreach ( array( 'main', 'side' ) as $hero_key ) {
        $hero[ $hero_key ] = cvl_normalize_homepage_hero_panel(
            isset( $saved[ $hero_key ] ) ? $saved[ $hero_key ] : array(),
            $github_hero[ $hero_key ]
        );
    }

    return apply_filters( 'cvl_homepage_hero', $hero );
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

/**
 * Returns the ordered image set used by a rotating homepage highlight.
 * The primary image is always first, followed by additional selected images.
 */
function cvl_homepage_highlight_rotation_images( $card ) {
    if ( ! is_array( $card ) ) {
        return array();
    }

    $images  = array();
    $primary = cvl_homepage_highlight_image_url( $card );

    if ( $primary ) {
        $images[] = $primary;
    }

    $rotation_ids = isset( $card['rotation_image_ids'] ) && is_array( $card['rotation_image_ids'] )
        ? $card['rotation_image_ids']
        : array();

    foreach ( $rotation_ids as $image_id ) {
        $url = wp_get_attachment_image_url( absint( $image_id ), 'large' );
        if ( $url ) {
            $images[] = $url;
        }
    }

    $rotation_urls = isset( $card['rotation_image_urls'] ) && is_array( $card['rotation_image_urls'] )
        ? $card['rotation_image_urls']
        : array();

    foreach ( $rotation_urls as $url ) {
        $url = trim( (string) $url );
        if ( '' === $url ) {
            continue;
        }

        if ( 0 === strpos( $url, 'theme://' ) ) {
            $url = get_template_directory_uri() . '/' . ltrim( substr( $url, 8 ), '/' );
        }

        if ( $url ) {
            $images[] = $url;
        }
    }

    return array_values( array_unique( array_filter( $images ) ) );
}

function cvl_homepage_hero_image_url( $panel ) {
    return cvl_homepage_highlight_image_url( $panel );
}

function cvl_homepage_visual_migration_2026() {
    if ( '1' === get_option( 'cvl_homepage_visual_migration_2026', '' ) ) {
        return;
    }

    $saved = get_option( 'cvl_homepage_hero_admin', array() );
    if ( isset( $saved['main'] ) && is_array( $saved['main'] ) ) {
        $background = strtolower( (string) ( $saved['main']['background'] ?? '' ) );
        if ( in_array( $background, array( '#0b4e46', '#123f39', '#073b36' ), true ) ) {
            $saved['main']['background']   = '#f4f5f5';
            $saved['main']['text_color']   = '#101820';
            $saved['main']['accent_color'] = '#d62828';
            update_option( 'cvl_homepage_hero_admin', $saved, false );
        }
    }

    update_option( 'cvl_homepage_visual_migration_2026', '1', false );
}
add_action( 'admin_init', 'cvl_homepage_visual_migration_2026' );

add_action( 'admin_menu', function () {
    $parent = function_exists( 'cv_admin_parent_slug' ) ? cv_admin_parent_slug() : 'woocommerce';

    add_submenu_page(
        $parent,
        __( 'Destaques Homepage', 'chavevertical-lite' ),
        __( 'Destaques Homepage', 'chavevertical-lite' ),
        'manage_woocommerce',
        'cvl-homepage-highlights',
        'cvl_render_homepage_highlights_admin'
    );
}, 45 );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
    if ( false === strpos( (string) $hook, 'cvl-homepage-highlights' ) ) {
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

    $(document).on('click','.cvl-highlight-select-rotation-images',function(e){
        e.preventDefault();
        var card = $(this).closest('.cvl-highlight-admin-card');
        var frame = wp.media({
            title: 'Selecionar imagens para rotação',
            button: { text: 'Usar estas imagens' },
            multiple: true
        });

        frame.on('select',function(){
            var selection = frame.state().get('selection').toJSON();
            var ids = [];
            var preview = card.find('.cvl-highlight-rotation-preview');
            preview.empty();

            selection.forEach(function(attachment){
                if (!attachment.id) {
                    return;
                }

                ids.push(attachment.id);
                var src = attachment.sizes && attachment.sizes.thumbnail
                    ? attachment.sizes.thumbnail.url
                    : attachment.url;

                $('<img>', {
                    src: src,
                    alt: '',
                    'data-id': attachment.id
                }).appendTo(preview);
            });

            card.find('.cvl-highlight-rotation-ids').val(ids.join(','));
            card.find('.cvl-highlight-rotation-empty').toggle(ids.length === 0);
        });

        frame.open();
    });

    $(document).on('click','.cvl-highlight-clear-rotation-images',function(e){
        e.preventDefault();
        var card = $(this).closest('.cvl-highlight-admin-card');
        card.find('.cvl-highlight-rotation-ids').val('');
        card.find('.cvl-highlight-rotation-preview').empty();
        card.find('.cvl-highlight-rotation-empty').show();
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
    $github_hero  = cvl_homepage_highlights_github_hero();
    $saved_cards  = get_option( 'cvl_homepage_highlights_admin', array() );
    $saved_hero   = get_option( 'cvl_homepage_hero_admin', array() );
    $cards        = array();
    $hero         = array();

    foreach ( array( 'main', 'side' ) as $hero_key ) {
        $hero[ $hero_key ] = cvl_normalize_homepage_hero_panel(
            isset( $saved_hero[ $hero_key ] ) ? $saved_hero[ $hero_key ] : array(),
            $github_hero[ $hero_key ]
        );
    }

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

        <p><?php esc_html_e( 'Personalize o Hero principal, incluindo imagem, textos, botões, links, selo e cores, além da caixa lateral e dos quatro destaques da homepage. Selecione "Usar valores do Admin" para aplicar estas alterações no site.', 'chavevertical-lite' ); ?></p>
        <p><strong><?php esc_html_e( 'Formatos recomendados:', 'chavevertical-lite' ); ?></strong> Hero principal 1400×720 px · Destaque lateral 600×840 px · Destaques 800×800 px. Preferir WEBP.</p>

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

            <h2 class="cvl-homepage-admin-section-title"><?php esc_html_e( 'Hero principal e caixa lateral', 'chavevertical-lite' ); ?></h2>
            <div class="cvl-highlight-admin-grid cvl-hero-admin-grid">
                <?php
                $hero_admin_labels = array(
                    'main' => __( 'Hero principal', 'chavevertical-lite' ),
                    'side' => __( 'Caixa lateral', 'chavevertical-lite' ),
                );
                ?>
                <?php foreach ( array( 'main', 'side' ) as $hero_key ) : ?>
                    <?php
                    $hero_panel   = $hero[ $hero_key ];
                    $hero_preview = cvl_homepage_hero_image_url( $hero_panel );
                    ?>
                    <section class="cvl-highlight-admin-card cvl-hero-admin-card">
                        <h2><?php echo esc_html( $hero_admin_labels[ $hero_key ] ); ?></h2>

                        <div class="cvl-highlight-admin-preview">
                            <img class="cvl-highlight-preview" src="<?php echo esc_url( $hero_preview ); ?>" alt="" <?php echo $hero_preview ? '' : 'style="display:none"'; ?>>
                            <div class="cvl-highlight-preview-empty" <?php echo $hero_preview ? 'style="display:none"' : ''; ?>><?php esc_html_e( 'Sem imagem personalizada', 'chavevertical-lite' ); ?></div>
                        </div>

                        <input class="cvl-highlight-image-id" type="hidden" name="hero[<?php echo esc_attr( $hero_key ); ?>][image_id]" value="<?php echo esc_attr( $hero_panel['image_id'] ); ?>">
                        <label>
                            <span><?php esc_html_e( 'URL da imagem / GitHub / CDN', 'chavevertical-lite' ); ?></span>
                            <input class="cvl-highlight-image-url" type="text" name="hero[<?php echo esc_attr( $hero_key ); ?>][image_url]" value="<?php echo esc_attr( $hero_panel['image_url'] ); ?>" placeholder="https://... ou theme://assets/images/...">
                        </label>

                        <p class="cvl-highlight-media-actions">
                            <button type="button" class="button cvl-highlight-select-image"><?php esc_html_e( 'Escolher imagem', 'chavevertical-lite' ); ?></button>
                            <button type="button" class="button-link-delete cvl-highlight-remove-image"><?php esc_html_e( 'Remover imagem personalizada', 'chavevertical-lite' ); ?></button>
                        </p>

                        <label>
                            <span><?php esc_html_e( 'Texto superior', 'chavevertical-lite' ); ?></span>
                            <input type="text" name="hero[<?php echo esc_attr( $hero_key ); ?>][eyebrow]" value="<?php echo esc_attr( $hero_panel['eyebrow'] ); ?>">
                        </label>

                        <label>
                            <span><?php esc_html_e( 'Título — linha 1', 'chavevertical-lite' ); ?></span>
                            <input type="text" name="hero[<?php echo esc_attr( $hero_key ); ?>][title]" value="<?php echo esc_attr( $hero_panel['title'] ); ?>">
                        </label>

                        <label>
                            <span><?php esc_html_e( 'Título — linha 2', 'chavevertical-lite' ); ?></span>
                            <input type="text" name="hero[<?php echo esc_attr( $hero_key ); ?>][title_accent]" value="<?php echo esc_attr( $hero_panel['title_accent'] ); ?>">
                        </label>

                        <label>
                            <span><?php esc_html_e( 'Descrição', 'chavevertical-lite' ); ?></span>
                            <textarea rows="4" name="hero[<?php echo esc_attr( $hero_key ); ?>][description]"><?php echo esc_textarea( $hero_panel['description'] ); ?></textarea>
                        </label>

                        <label>
                            <span><?php esc_html_e( 'Texto do botão', 'chavevertical-lite' ); ?></span>
                            <input type="text" name="hero[<?php echo esc_attr( $hero_key ); ?>][cta]" value="<?php echo esc_attr( $hero_panel['cta'] ); ?>">
                        </label>

                        <label>
                            <span><?php esc_html_e( 'Link principal', 'chavevertical-lite' ); ?></span>
                            <input type="text" name="hero[<?php echo esc_attr( $hero_key ); ?>][url]" value="<?php echo esc_attr( $hero_panel['url'] ); ?>" placeholder="<?php esc_attr_e( 'Vazio = catálogo', 'chavevertical-lite' ); ?>">
                        </label>

                        <?php if ( 'main' === $hero_key ) : ?>
                            <label>
                                <span><?php esc_html_e( 'Texto do segundo link', 'chavevertical-lite' ); ?></span>
                                <input type="text" name="hero[main][secondary_cta]" value="<?php echo esc_attr( $hero_panel['secondary_cta'] ); ?>">
                            </label>

                            <label>
                                <span><?php esc_html_e( 'Link do segundo botão', 'chavevertical-lite' ); ?></span>
                                <input type="text" name="hero[main][secondary_url]" value="<?php echo esc_attr( $hero_panel['secondary_url'] ); ?>">
                            </label>

                            <label>
                                <span><?php esc_html_e( 'Selo', 'chavevertical-lite' ); ?></span>
                                <input type="text" name="hero[main][badge]" value="<?php echo esc_attr( $hero_panel['badge'] ); ?>">
                            </label>
                        <?php endif; ?>

                        <label>
                            <span><?php esc_html_e( 'Categoria de imagem fallback (slug)', 'chavevertical-lite' ); ?></span>
                            <input type="text" name="hero[<?php echo esc_attr( $hero_key ); ?>][fallback_category_slug]" value="<?php echo esc_attr( $hero_panel['fallback_category_slug'] ); ?>">
                        </label>

                        <div class="cvl-highlight-color-row cvl-hero-color-row">
                            <label><span><?php esc_html_e( 'Fundo', 'chavevertical-lite' ); ?></span><input type="color" name="hero[<?php echo esc_attr( $hero_key ); ?>][background]" value="<?php echo esc_attr( $hero_panel['background'] ); ?>"></label>
                            <label><span><?php esc_html_e( 'Texto', 'chavevertical-lite' ); ?></span><input type="color" name="hero[<?php echo esc_attr( $hero_key ); ?>][text_color]" value="<?php echo esc_attr( $hero_panel['text_color'] ); ?>"></label>
                            <label><span><?php esc_html_e( 'Destaque', 'chavevertical-lite' ); ?></span><input type="color" name="hero[<?php echo esc_attr( $hero_key ); ?>][accent_color]" value="<?php echo esc_attr( $hero_panel['accent_color'] ); ?>"></label>
                        </div>
                    </section>
                <?php endforeach; ?>
            </div>

            <h2 class="cvl-homepage-admin-section-title"><?php esc_html_e( 'Destaques abaixo das categorias', 'chavevertical-lite' ); ?></h2>
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
                        <label>
                            <span><?php esc_html_e( 'URL da imagem / GitHub / CDN', 'chavevertical-lite' ); ?></span>
                            <input class="cvl-highlight-image-url" type="text" name="cards[<?php echo esc_attr( $index ); ?>][image_url]" value="<?php echo esc_attr( $card['image_url'] ); ?>" placeholder="https://... ou theme://assets/images/...">
                        </label>
                        <input type="hidden" name="cards[<?php echo esc_attr( $index ); ?>][fallback_category_slug]" value="<?php echo esc_attr( $card['fallback_category_slug'] ); ?>">

                        <p class="cvl-highlight-media-actions">
                            <button type="button" class="button cvl-highlight-select-image"><?php esc_html_e( 'Escolher imagem', 'chavevertical-lite' ); ?></button>
                            <button type="button" class="button-link-delete cvl-highlight-remove-image"><?php esc_html_e( 'Remover imagem personalizada', 'chavevertical-lite' ); ?></button>
                        </p>

                        <?php
                        $rotation_ids = isset( $card['rotation_image_ids'] ) && is_array( $card['rotation_image_ids'] )
                            ? array_values( array_filter( array_map( 'absint', $card['rotation_image_ids'] ) ) )
                            : array();
                        ?>
                        <div class="cvl-highlight-rotation-box">
                            <h3><?php esc_html_e( 'Rotação de imagens', 'chavevertical-lite' ); ?></h3>
                            <p class="description"><?php esc_html_e( 'A imagem principal aparece primeiro. As imagens abaixo rodam a seguir automaticamente.', 'chavevertical-lite' ); ?></p>

                            <input
                                class="cvl-highlight-rotation-ids"
                                type="hidden"
                                name="cards[<?php echo esc_attr( $index ); ?>][rotation_image_ids]"
                                value="<?php echo esc_attr( implode( ',', $rotation_ids ) ); ?>"
                            >

                            <div class="cvl-highlight-rotation-preview">
                                <?php foreach ( $rotation_ids as $rotation_id ) : ?>
                                    <?php $rotation_thumb = wp_get_attachment_image_url( $rotation_id, 'thumbnail' ); ?>
                                    <?php if ( $rotation_thumb ) : ?>
                                        <img src="<?php echo esc_url( $rotation_thumb ); ?>" alt="" data-id="<?php echo esc_attr( $rotation_id ); ?>">
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>

                            <div class="cvl-highlight-rotation-empty" <?php echo ! empty( $rotation_ids ) ? 'style="display:none"' : ''; ?>>
                                <?php esc_html_e( 'Sem imagens adicionais', 'chavevertical-lite' ); ?>
                            </div>

                            <p class="cvl-highlight-media-actions">
                                <button type="button" class="button cvl-highlight-select-rotation-images"><?php esc_html_e( 'Escolher várias imagens', 'chavevertical-lite' ); ?></button>
                                <button type="button" class="button-link-delete cvl-highlight-clear-rotation-images"><?php esc_html_e( 'Limpar rotação', 'chavevertical-lite' ); ?></button>
                            </p>

                            <label>
                                <span><?php esc_html_e( 'Tempo entre imagens (segundos)', 'chavevertical-lite' ); ?></span>
                                <input
                                    type="number"
                                    min="2"
                                    max="60"
                                    step="1"
                                    name="cards[<?php echo esc_attr( $index ); ?>][rotation_seconds]"
                                    value="<?php echo esc_attr( (string) $card['rotation_seconds'] ); ?>"
                                >
                            </label>
                        </div>

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

            <?php submit_button( __( 'Guardar homepage', 'chavevertical-lite' ) ); ?>
        </form>
    </div>

    <style>
        .cvl-highlights-source-help{margin:16px 0;padding:12px 14px;background:#fff;border-left:4px solid #17820f}
        .cvl-highlights-source{margin:18px 0;padding:14px;background:#fff;border:1px solid #dcdcde}
        .cvl-highlights-source label{margin-right:24px}
        .cvl-homepage-admin-section-title{margin:28px 0 12px}.cvl-highlight-admin-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;max-width:1200px}
        .cvl-highlight-admin-card{padding:18px;background:#fff;border:1px solid #dcdcde;border-radius:8px}
        .cvl-highlight-admin-card h2{margin-top:0}
        .cvl-highlight-admin-preview{height:180px;margin-bottom:12px;display:grid;place-items:center;overflow:hidden;background:#f3f5f5;border:1px solid #e3e6e6}
        .cvl-highlight-admin-preview img{width:100%;height:100%;object-fit:cover}
        .cvl-highlight-preview-empty{color:#6b7377}
        .cvl-highlight-admin-card label{display:block;margin-top:12px}
        .cvl-highlight-admin-card label>span{display:block;margin-bottom:5px;font-weight:600}
        .cvl-highlight-admin-card input[type="text"],.cvl-highlight-admin-card textarea{width:100%}
        .cvl-highlight-media-actions{display:flex;align-items:center;gap:12px}
        .cvl-highlight-rotation-box{margin:16px 0 4px;padding:14px;border:1px solid #dcdcde;border-radius:8px;background:#f8faf9}
        .cvl-highlight-rotation-box h3{margin:0 0 4px}
        .cvl-highlight-rotation-preview{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:7px;margin:10px 0}
        .cvl-highlight-rotation-preview img{width:100%;aspect-ratio:1/1;object-fit:cover;border:1px solid #dcdcde;border-radius:5px;background:#fff}
        .cvl-highlight-rotation-empty{margin:10px 0;padding:12px;text-align:center;color:#6b7377;background:#fff;border:1px dashed #c9cecf}
        .cvl-highlight-rotation-box input[type="number"]{width:110px}
        .cvl-highlight-color-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}.cvl-hero-color-row{grid-template-columns:repeat(3,1fr)}
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
    $posted_hero  = isset( $_POST['hero'] ) && is_array( $_POST['hero'] ) ? wp_unslash( $_POST['hero'] ) : array();
    $github_cards = cvl_homepage_highlights_github_cards();
    $github_hero  = cvl_homepage_highlights_github_hero();
    $cards        = array();
    $hero         = array();

    foreach ( array( 'main', 'side' ) as $hero_key ) {
        $hero[ $hero_key ] = cvl_normalize_homepage_hero_panel(
            isset( $posted_hero[ $hero_key ] ) ? $posted_hero[ $hero_key ] : array(),
            $github_hero[ $hero_key ]
        );
    }

    for ( $i = 0; $i < 4; $i++ ) {
        $cards[] = cvl_normalize_homepage_highlight(
            isset( $posted_cards[ $i ] ) ? $posted_cards[ $i ] : array(),
            $github_cards[ $i ]
        );
    }

    update_option( 'cvl_homepage_highlights_source', $source, false );
    update_option( 'cvl_homepage_highlights_admin', $cards, false );
    update_option( 'cvl_homepage_hero_admin', $hero, false );

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
