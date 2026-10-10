<?php
/**
 * Imagens alternativas para categorias WooCommerce.
 * A imagem normal continua a ser o thumbnail_id nativo.
 * As variantes sao opcionais e nao alteram a imagem principal.
 */
defined( 'ABSPATH' ) || exit;

function cvl_category_extra_image_fields(): array {
    return array(
        'cvl_category_mobile_image_id' => array(
            'label'       => __( 'Imagem para dispositivos móveis', 'chavevertical-lite' ),
            'description' => __( 'Usada nos cartões de categorias em telemóveis (até 767 px). Se estiver vazia, utiliza a imagem normal da categoria.', 'chavevertical-lite' ),
        ),
        'cvl_category_menu_image_id' => array(
            'label'       => __( 'Imagem para menus', 'chavevertical-lite' ),
            'description' => __( 'Imagem opcional apresentada ao lado do nome da categoria no menu lateral de categorias. Se estiver vazia, o menu continua apenas com texto.', 'chavevertical-lite' ),
        ),
    );
}

add_action( 'init', static function (): void {
    foreach ( array_keys( cvl_category_extra_image_fields() ) as $meta_key ) {
        register_term_meta(
            'product_cat',
            $meta_key,
            array(
                'type'              => 'integer',
                'single'            => true,
                'default'           => 0,
                'sanitize_callback' => 'absint',
                'show_in_rest'      => false,
            )
        );
    }
} );

/**
 * Mantem compatibilidade com os formularios de adicionar e editar categorias.
 */
function cvl_render_category_extra_image_fields( ?WP_Term $term = null ): void {
    foreach ( cvl_category_extra_image_fields() as $meta_key => $field ) {
        $image_id = $term ? absint( get_term_meta( $term->term_id, $meta_key, true ) ) : 0;
        $preview  = $image_id ? wp_get_attachment_image(
            $image_id,
            'thumbnail',
            false,
            array(
                'class' => 'cvl-category-image-preview-thumb',
                'alt'   => '',
            )
        ) : '';

        if ( $term ) {
            echo '<tr class="form-field cvl-category-extra-image-field">';
            echo '<th scope="row"><label for="' . esc_attr( $meta_key ) . '">' . esc_html( $field['label'] ) . '</label></th>';
            echo '<td>';
        } else {
            echo '<div class="form-field cvl-category-extra-image-field">';
            echo '<label for="' . esc_attr( $meta_key ) . '">' . esc_html( $field['label'] ) . '</label>';
        }

        if ( 'cvl_category_mobile_image_id' === $meta_key ) {
            wp_nonce_field( 'cvl_category_images_save', 'cvl_category_images_nonce' );
        }

        echo '<div class="cvl-category-image-control">';
        echo '<input type="hidden" id="' . esc_attr( $meta_key ) . '" name="' . esc_attr( $meta_key ) . '" value="' . esc_attr( (string) $image_id ) . '">';
        echo '<div class="cvl-category-image-preview"' . ( $preview ? '' : ' hidden' ) . '>' . $preview . '</div>';
        echo '<div class="cvl-category-image-actions">';
        echo '<button type="button" class="button cvl-category-image-select">' . esc_html__( 'Selecionar imagem', 'chavevertical-lite' ) . '</button> ';
        echo '<button type="button" class="button-link-delete cvl-category-image-remove"' . ( $preview ? '' : ' hidden' ) . '>' . esc_html__( 'Remover imagem', 'chavevertical-lite' ) . '</button>';
        echo '</div>';
        echo '</div>';
        echo '<p class="description">' . esc_html( $field['description'] ) . '</p>';

        if ( $term ) {
            echo '</td></tr>';
        } else {
            echo '</div>';
        }
    }
}
add_action( 'product_cat_add_form_fields', static function (): void {
    cvl_render_category_extra_image_fields();
}, 30 );
add_action( 'product_cat_edit_form_fields', static function ( $term ): void {
    if ( $term instanceof WP_Term ) {
        cvl_render_category_extra_image_fields( $term );
    }
}, 30 );

function cvl_save_category_extra_images( int $term_id ): void {
    $taxonomy = get_taxonomy( 'product_cat' );
    if ( ! $taxonomy || ! current_user_can( $taxonomy->cap->edit_terms ) ) {
        return;
    }

    if (
        ! isset( $_POST['cvl_category_images_nonce'] )
        || ! is_string( $_POST['cvl_category_images_nonce'] )
        || ! wp_verify_nonce(
            sanitize_text_field( wp_unslash( $_POST['cvl_category_images_nonce'] ) ),
            'cvl_category_images_save'
        )
    ) {
        return;
    }

    foreach ( array_keys( cvl_category_extra_image_fields() ) as $meta_key ) {
        if ( ! isset( $_POST[ $meta_key ] ) || ! is_scalar( $_POST[ $meta_key ] ) ) {
            continue;
        }
        $image_id = absint( wp_unslash( $_POST[ $meta_key ] ) );

        if ( $image_id && wp_attachment_is_image( $image_id ) ) {
            update_term_meta( $term_id, $meta_key, $image_id );
        } else {
            // Remover uma imagem opcional nao apaga o ficheiro da biblioteca.
            delete_term_meta( $term_id, $meta_key );
        }
    }
}
add_action( 'created_product_cat', 'cvl_save_category_extra_images', 30 );
add_action( 'edited_product_cat', 'cvl_save_category_extra_images', 30 );

add_action( 'admin_enqueue_scripts', static function ( string $hook ): void {
    if ( ! in_array( $hook, array( 'edit-tags.php', 'term.php' ), true ) ) {
        return;
    }
    $taxonomy = isset( $_GET['taxonomy'] ) && is_string( $_GET['taxonomy'] )
        ? sanitize_key( wp_unslash( $_GET['taxonomy'] ) )
        : '';
    if ( 'product_cat' !== $taxonomy ) {
        return;
    }

    wp_enqueue_media();
    wp_enqueue_script(
        'cvl-category-images-admin',
        get_template_directory_uri() . '/assets/js/category-images-admin.js',
        array(),
        cvl_asset_version( 'assets/js/category-images-admin.js' ),
        true
    );
    wp_enqueue_style(
        'cvl-category-images-admin',
        get_template_directory_uri() . '/assets/css/category-images-admin.css',
        array(),
        cvl_asset_version( 'assets/css/category-images-admin.css' )
    );
} );

/**
 * Cartoes de categorias: source mobile especifico com fallback nativo.
 * Evita mudar os URLs, tamanho e imagem principal WooCommerce.
 */
function cvl_category_picture( WP_Term $term, string $size = 'medium', string $loading = 'lazy' ): string {
    $desktop_id = absint( get_term_meta( $term->term_id, 'thumbnail_id', true ) );
    $mobile_id  = absint( get_term_meta( $term->term_id, 'cvl_category_mobile_image_id', true ) );
    $default_id = $desktop_id ? $desktop_id : $mobile_id;

    if ( ! $default_id ) {
        return '';
    }

    $img = wp_get_attachment_image(
        $default_id,
        $size,
        false,
        array(
            'alt'      => $term->name,
            'loading'  => $loading,
            'decoding' => 'async',
        )
    );
    if ( ! $img ) {
        return '';
    }

    if ( ! $mobile_id || $mobile_id === $default_id ) {
        return $img;
    }

    $mobile_src = wp_get_attachment_image_url( $mobile_id, $size );
    if ( ! $mobile_src ) {
        return $img;
    }

    $mobile_srcset = wp_get_attachment_image_srcset( $mobile_id, $size );

    return '<picture class="cvl-category-responsive-picture">'
        . '<source media="(max-width: 767px)" srcset="' . esc_attr( $mobile_srcset ? $mobile_srcset : $mobile_src ) . '" sizes="(max-width: 480px) 90vw, 50vw">'
        . $img
        . '</picture>';
}

/**
 * Apenas aparece um icone no menu se tiver sido configurada uma imagem de menu.
 * As categorias existentes sem esta imagem mantem a navegacao por texto.
 */
function cvl_category_menu_image( WP_Term $term ): string {
    $image_id = absint( get_term_meta( $term->term_id, 'cvl_category_menu_image_id', true ) );
    if ( ! $image_id ) {
        return '';
    }

    return (string) wp_get_attachment_image(
        $image_id,
        'thumbnail',
        false,
        array(
            'class'    => 'cvl-category-menu-thumb',
            'alt'      => '',
            'loading'  => 'lazy',
            'decoding' => 'async',
        )
    );
}
