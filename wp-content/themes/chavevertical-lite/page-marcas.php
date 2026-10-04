<?php
/**
 * Brands directory for the WooCommerce product_brand taxonomy.
 *
 * Template Name: Marcas
 */
defined( 'ABSPATH' ) || exit;

get_header();

$brand_terms = array();

if ( taxonomy_exists( 'product_brand' ) ) {
    $brand_terms = get_terms(
        array(
            'taxonomy'   => 'product_brand',
            'hide_empty' => false,
            'number'     => 0,
            'orderby'    => 'name',
            'order'      => 'ASC',
        )
    );
}

$brand_groups = array();

if ( ! is_wp_error( $brand_terms ) && ! empty( $brand_terms ) ) {
    foreach ( $brand_terms as $brand_term ) {
        $normalized = remove_accents( $brand_term->name );
        $letter     = strtoupper( substr( $normalized, 0, 1 ) );

        if ( ! preg_match( '/^[A-Z]$/', $letter ) ) {
            $letter = '#';
        }

        if ( ! isset( $brand_groups[ $letter ] ) ) {
            $brand_groups[ $letter ] = array();
        }

        $brand_groups[ $letter ][] = $brand_term;
    }

    ksort( $brand_groups, SORT_NATURAL );

    if ( isset( $brand_groups['#'] ) ) {
        $other_brands = $brand_groups['#'];
        unset( $brand_groups['#'] );
        $brand_groups['#'] = $other_brands;
    }
}
?>
<div class="cvl-shell cvl-brands-page">
    <header class="cvl-brands-hero">
        <p class="cvl-brands-eyebrow"><?php esc_html_e( 'Catálogo por marca', 'chavevertical-lite' ); ?></p>
        <h1><?php esc_html_e( 'Marcas', 'chavevertical-lite' ); ?></h1>
        <p class="cvl-brands-intro"><?php esc_html_e( 'Consulte as marcas disponíveis na Chave Vertical e aceda diretamente aos respetivos produtos.', 'chavevertical-lite' ); ?></p>
    </header>

    <?php if ( is_wp_error( $brand_terms ) || empty( $brand_groups ) ) : ?>
        <p class="cvl-brands-empty"><?php esc_html_e( 'Ainda não existem marcas disponíveis.', 'chavevertical-lite' ); ?></p>
    <?php else : ?>
        <nav class="cvl-brands-index" aria-label="<?php esc_attr_e( 'Índice alfabético de marcas', 'chavevertical-lite' ); ?>">
            <?php foreach ( array_keys( $brand_groups ) as $letter ) : ?>
                <?php $group_id = '#' === $letter ? 'marcas-outros' : 'marcas-' . strtolower( $letter ); ?>
                <a href="#<?php echo esc_attr( $group_id ); ?>"><?php echo esc_html( '#' === $letter ? '0–9' : $letter ); ?></a>
            <?php endforeach; ?>
        </nav>

        <div class="cvl-brands-groups">
            <?php foreach ( $brand_groups as $letter => $terms ) : ?>
                <?php $group_id = '#' === $letter ? 'marcas-outros' : 'marcas-' . strtolower( $letter ); ?>
                <section id="<?php echo esc_attr( $group_id ); ?>" class="cvl-brand-group">
                    <h2 class="cvl-brand-group-title"><?php echo esc_html( '#' === $letter ? '0–9 / Outros' : $letter ); ?></h2>

                    <div class="cvl-brands-grid">
                        <?php foreach ( $terms as $term ) : ?>
                            <?php
                            $term_url = get_term_link( $term );

                            if ( is_wp_error( $term_url ) ) {
                                continue;
                            }

                            $thumbnail_id = absint( get_term_meta( $term->term_id, 'thumbnail_id', true ) );
                            $logo_html    = '';

                            if ( $thumbnail_id ) {
                                $logo_html = wp_get_attachment_image(
                                    $thumbnail_id,
                                    'medium',
                                    false,
                                    array(
                                        'loading' => 'lazy',
                                        'alt'     => $term->name,
                                    )
                                );
                            }
                            ?>
                            <a class="cvl-brand-card" href="<?php echo esc_url( $term_url ); ?>">
                                <?php if ( $logo_html ) : ?>
                                    <span class="cvl-brand-card-media"><?php echo wp_kses_post( $logo_html ); ?></span>
                                    <strong class="cvl-brand-card-name"><?php echo esc_html( $term->name ); ?></strong>
                                <?php else : ?>
                                    <strong class="cvl-brand-card-name is-fallback"><?php echo esc_html( $term->name ); ?></strong>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php
get_footer();
