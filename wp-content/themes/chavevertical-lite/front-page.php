<?php
defined( 'ABSPATH' ) || exit;

get_header();

$shop_url   = cvl_shop_url();
$categories = array();
$products   = array();
$brands     = array();

if ( taxonomy_exists( 'product_cat' ) ) {
    $excluded = array();
    $uncategorized = get_term_by( 'slug', 'uncategorized', 'product_cat' );

    if ( $uncategorized && ! is_wp_error( $uncategorized ) ) {
        $excluded[] = (int) $uncategorized->term_id;
    }

    $categories = get_terms( array(
        'taxonomy'   => 'product_cat',
        'parent'     => 0,
        'hide_empty' => false,
        'exclude'    => $excluded,
        'number'     => 0,
        'orderby'    => 'name',
        'order'      => 'ASC',
    ) );

    if ( is_wp_error( $categories ) ) {
        $categories = array();
    }
}

if ( ! empty( $categories ) ) {
    $priority = array(
        'oficina-automovel',
        'ferramentas-electricas',
        'ferramentas-manuais',
        'elevacao-e-carga',
        'ar-comprimido',
        'maquinas-p-industria-metal',
        'construcao-civil',
        'floresta-e-jardim',
        'limpeza',
        'equipamentos-de-soldadura',
        'geradores',
        'carpintaria-de-madeiras',
        'ferramentas-pneumaticas',
        'medicao-e-nivelamento',
        'proteccao-e-seguranca',
        'estantaria-e-arrumacao',
        'electricidade-e-electronica',
        'iluminacao',
        'ambiente',
        'canalizacao-e-desentupimentos',
        'embalamento',
        'equip-p-agricultura',
        'escadas-escadotes-e-andaimes',
        'outros',
    );

    $rank = array_flip( $priority );

    usort(
        $categories,
        static function ( $a, $b ) use ( $rank ) {
            $ra = $rank[ $a->slug ] ?? 999;
            $rb = $rank[ $b->slug ] ?? 999;

            if ( $ra === $rb ) {
                return strcasecmp( $a->name, $b->name );
            }

            return $ra <=> $rb;
        }
    );
}

$hero_categories = array_slice( $categories, 0, 3 );

if ( taxonomy_exists( 'product_brand' ) ) {
    $brands = get_terms( array(
        'taxonomy'   => 'product_brand',
        'hide_empty' => false,
        'number'     => 28,
        'orderby'    => 'name',
        'order'      => 'ASC',
        'meta_query' => array(
            array(
                'key'     => 'thumbnail_id',
                'compare' => 'EXISTS',
            ),
        ),
    ) );

    if ( is_wp_error( $brands ) ) {
        $brands = array();
    }

    $brands = array_values(
        array_filter(
            $brands,
            static fn( $brand ) => absint( get_term_meta( $brand->term_id, 'thumbnail_id', true ) ) > 0
        )
    );
}

if ( function_exists( 'wc_get_products' ) ) {
    $products = wc_get_products( array(
        'status'  => 'publish',
        'limit'   => 10,
        'orderby' => 'date',
        'order'   => 'DESC',
        'return'  => 'objects',
    ) );
}

$cvl_category_image = static function ( $term, $size = 'large', $loading = 'lazy' ) {
    if ( ! $term instanceof WP_Term ) {
        return '';
    }

    $thumbnail_id = absint( get_term_meta( $term->term_id, 'thumbnail_id', true ) );

    if ( ! $thumbnail_id ) {
        return '';
    }

    return wp_get_attachment_image(
        $thumbnail_id,
        $size,
        false,
        array(
            'loading'  => $loading,
            'decoding' => 'async',
            'alt'      => $term->name,
        )
    );
};
?>

<section class="cvl-ref-hero">
    <div class="cvl-shell cvl-ref-hero-grid">
        <div class="cvl-ref-hero-copy">
            <span class="cvl-ref-kicker">EQUIPAMENTO PROFISSIONAL · APOIO ESPECIALIZADO</span>
            <h1>Ferramentas certas.<br><span>Trabalho melhor.</span></h1>
            <p>Máquinas, ferramentas e equipamento para oficina, indústria, construção e manutenção — com apoio de uma equipa que conhece o produto.</p>

            <div class="cvl-ref-hero-actions">
                <a class="cvl-ref-button cvl-ref-button-primary" href="<?php echo esc_url( $shop_url ); ?>">EXPLORAR CATÁLOGO <span aria-hidden="true">→</span></a>
                <a class="cvl-ref-button cvl-ref-button-ghost" href="<?php echo esc_url( home_url( '/contactos/' ) ); ?>">PEDIR COTAÇÃO</a>
            </div>

            <div class="cvl-ref-popular-searches">
                <span>Mais procurado:</span>
                <a href="<?php echo esc_url( home_url( '/?s=carro+de+ferramentas&post_type=product' ) ); ?>">Carros de ferramentas</a>
                <a href="<?php echo esc_url( home_url( '/?s=compressor&post_type=product' ) ); ?>">Compressores</a>
                <a href="<?php echo esc_url( home_url( '/?s=porta-paletes&post_type=product' ) ); ?>">Porta-paletes</a>
                <a href="<?php echo esc_url( home_url( '/?s=gerador&post_type=product' ) ); ?>">Geradores</a>
            </div>
        </div>

        <div class="cvl-ref-hero-visual" aria-label="CHAVE VERTICAL">
            <span class="cvl-ref-hero-shape" aria-hidden="true"></span>
            <div class="cvl-ref-hero-machine">
                <span>CV</span>
                <strong>CATÁLOGO PROFISSIONAL</strong>
                <small>OFICINA · INDÚSTRIA · CONSTRUÇÃO</small>
            </div>
            <div class="cvl-ref-hero-card">
                <small>EQUIPAMENTO PROFISSIONAL</small>
                <b>Mais de 30.000 referências</b>
                <span>Compra técnica acompanhada</span>
            </div>
        </div>
    </div>
</section>

<section class="cvl-ref-benefits" aria-label="Vantagens Chave Vertical">
    <div class="cvl-shell cvl-ref-benefit-grid">
        <div><span class="cvl-ref-benefit-icon">↗</span><span><b>Entregas em Portugal</b><small>Transporte adequado ao equipamento</small></span></div>
        <div><span class="cvl-ref-benefit-icon">✓</span><span><b>Compra acompanhada</b><small>Informação clara antes da encomenda</small></span></div>
        <div><span class="cvl-ref-benefit-icon">◎</span><span><b>Apoio especializado</b><small>Comercial, técnico e pós-venda</small></span></div>
        <div><span class="cvl-ref-benefit-icon">⚙</span><span><b>Catálogo profissional</b><small>Máquinas, ferramentas e consumíveis</small></span></div>
    </div>
</section>

<?php if ( ! empty( $categories ) ) : ?>
<section class="cvl-shell cvl-v4-section cvl-v4-categories-section">
    <header class="cvl-v4-section-head">
        <div>
            <span>CATÁLOGO PROFISSIONAL</span>
            <h2>Comprar por categoria</h2>
            <p>Escolha a área e aceda diretamente às respetivas subcategorias e produtos.</p>
        </div>
        <a href="<?php echo esc_url( $shop_url ); ?>">VER CATÁLOGO →</a>
    </header>

    <div class="cvl-v4-category-grid">
        <?php foreach ( $categories as $category ) : ?>
            <?php
            $category_url = get_term_link( $category );

            if ( is_wp_error( $category_url ) ) {
                continue;
            }

            $image = $cvl_category_image( $category, 'medium_large' );
            ?>
            <a class="cvl-v4-category-card" href="<?php echo esc_url( $category_url ); ?>">
                <span class="cvl-v4-category-media">
                    <?php
                    if ( $image ) {
                        echo wp_kses_post( $image );
                    } else {
                        echo '<span class="cvl-v4-category-fallback" aria-hidden="true">CV</span>';
                    }
                    ?>
                </span>
                <span class="cvl-v4-category-name">
                    <strong><?php echo esc_html( $category->name ); ?></strong>
                    <span aria-hidden="true">→</span>
                </span>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<section class="cvl-v4-assistance">
    <div class="cvl-shell cvl-v4-assistance-grid">
        <div>
            <span>COMPRA TÉCNICA ACOMPANHADA</span>
            <h2>Tem uma referência, aplicação ou ficha técnica?</h2>
            <p>Envie-nos o pedido. Validamos produto, equivalências, disponibilidade e configuração antes da encomenda.</p>
        </div>
        <div class="cvl-v4-assistance-actions">
            <a class="cvl-v4-button cvl-v4-button-light" href="<?php echo esc_url( home_url( '/contactos/' ) ); ?>">PEDIR PROPOSTA</a>
            <a class="cvl-v4-assistance-phone" href="tel:+351234020500"><small>Apoio comercial</small><strong>234 020 500</strong></a>
        </div>
    </div>
</section>

<?php if ( ! empty( $products ) ) : ?>
<section class="cvl-v4-products">
    <div class="cvl-shell">
        <header class="cvl-v4-section-head">
            <div>
                <span>NOVIDADES</span>
                <h2>Produtos recentes</h2>
            </div>
            <a href="<?php echo esc_url( $shop_url ); ?>">VER TODOS →</a>
        </header>

        <?php
        if ( function_exists( 'woocommerce_product_loop_start' ) ) {
            wc_set_loop_prop( 'columns', 6 );
            woocommerce_product_loop_start();

            global $post;

            foreach ( $products as $home_product ) {
                $post = get_post( $home_product->get_id() );

                if ( ! $post ) {
                    continue;
                }

                setup_postdata( $post );
                wc_get_template_part( 'content', 'product' );
            }

            wp_reset_postdata();
            woocommerce_product_loop_end();
        }
        ?>
    </div>
</section>
<?php endif; ?>

<?php if ( ! empty( $brands ) ) : ?>
<section class="cvl-brand-carousel-section" aria-label="<?php esc_attr_e( 'Marcas representadas', 'chavevertical-lite' ); ?>">
    <div class="cvl-shell cvl-brand-carousel-header">
        <span class="cvl-brand-carousel-kicker"><?php esc_html_e( 'Marcas representadas', 'chavevertical-lite' ); ?></span>
        <h2><?php esc_html_e( 'As ferramentas em que os profissionais confiam.', 'chavevertical-lite' ); ?></h2>
    </div>

    <div class="cvl-brand-carousel-shell" aria-hidden="true">
        <div class="cvl-brand-carousel-track">
            <?php
            $cvl_brand_track = array_merge( $brands, $brands );

            foreach ( $cvl_brand_track as $brand ) :
                $brand_url = get_term_link( $brand );

                if ( is_wp_error( $brand_url ) ) {
                    continue;
                }

                $thumbnail_id = absint( get_term_meta( $brand->term_id, 'thumbnail_id', true ) );

                if ( ! $thumbnail_id ) {
                    continue;
                }
                ?>
                <a
                    class="cvl-brand-carousel-item"
                    href="<?php echo esc_url( $brand_url ); ?>"
                    title="<?php echo esc_attr( $brand->name ); ?>"
                    tabindex="-1"
                >
                    <?php
                    echo wp_kses_post(
                        wp_get_attachment_image(
                            $thumbnail_id,
                            'medium',
                            false,
                            array(
                                'loading'  => 'lazy',
                                'decoding' => 'async',
                                'alt'      => $brand->name,
                            )
                        )
                    );
                    ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php
get_footer();
