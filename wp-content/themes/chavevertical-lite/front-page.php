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
        'number'     => 16,
        'orderby'    => 'count',
        'order'      => 'DESC',
    ) );

    if ( is_wp_error( $brands ) ) {
        $brands = array();
    }
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

<section class="cvl-v4-hero">
    <div class="cvl-shell cvl-v4-hero-grid">
        <div class="cvl-v4-hero-copy">
            <span class="cvl-v4-eyebrow">MÁQUINAS · FERRAMENTAS · EQUIPAMENTO PROFISSIONAL</span>
            <h1>Equipamento profissional para quem precisa de trabalhar.</h1>
            <p>Oficina, indústria, construção, manutenção e logística. Um catálogo técnico organizado para encontrar rapidamente o produto certo.</p>

            <div class="cvl-v4-hero-actions">
                <a class="cvl-v4-button cvl-v4-button-primary" href="<?php echo esc_url( $shop_url ); ?>">
                    VER CATÁLOGO
                    <span aria-hidden="true">→</span>
                </a>
                <a class="cvl-v4-button cvl-v4-button-secondary" href="<?php echo esc_url( home_url( '/contactos/' ) ); ?>">
                    PEDIR COTAÇÃO
                </a>
            </div>

            <div class="cvl-v4-quick-searches">
                <span>Pesquisa rápida</span>
                <a href="<?php echo esc_url( home_url( '/?s=carro+de+ferramentas&post_type=product' ) ); ?>">Carros de ferramentas</a>
                <a href="<?php echo esc_url( home_url( '/?s=compressor&post_type=product' ) ); ?>">Compressores</a>
                <a href="<?php echo esc_url( home_url( '/?s=porta-paletes&post_type=product' ) ); ?>">Porta-paletes</a>
                <a href="<?php echo esc_url( home_url( '/?s=gerador&post_type=product' ) ); ?>">Geradores</a>
            </div>
        </div>

        <?php if ( ! empty( $hero_categories ) ) : ?>
            <div class="cvl-v4-hero-categories" aria-label="<?php esc_attr_e( 'Áreas principais', 'chavevertical-lite' ); ?>">
                <?php foreach ( $hero_categories as $index => $hero_category ) : ?>
                    <?php
                    $hero_url = get_term_link( $hero_category );

                    if ( is_wp_error( $hero_url ) ) {
                        continue;
                    }

                    $hero_image = $cvl_category_image(
                        $hero_category,
                        0 === $index ? 'large' : 'medium_large',
                        'eager'
                    );
                    ?>
                    <a class="cvl-v4-hero-category <?php echo 0 === $index ? 'is-primary' : ''; ?>" href="<?php echo esc_url( $hero_url ); ?>">
                        <span class="cvl-v4-hero-category-media">
                            <?php
                            if ( $hero_image ) {
                                echo wp_kses_post( $hero_image );
                            } else {
                                echo '<span class="cvl-v4-category-fallback" aria-hidden="true">CV</span>';
                            }
                            ?>
                        </span>
                        <span class="cvl-v4-hero-category-copy">
                            <small><?php echo 0 === $index ? 'ÁREA EM DESTAQUE' : 'EXPLORAR'; ?></small>
                            <strong><?php echo esc_html( $hero_category->name ); ?></strong>
                            <span aria-hidden="true">↗</span>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="cvl-v4-trust">
    <div class="cvl-shell cvl-v4-trust-grid">
        <div><strong>30.000+</strong><span>referências profissionais</span></div>
        <div><strong>Portugal</strong><span>entregas em todo o país</span></div>
        <div><strong>Apoio técnico</strong><span>antes e depois da compra</span></div>
        <div><strong>B2B</strong><span>propostas para empresas e projetos</span></div>
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
            wc_set_loop_prop( 'columns', 5 );
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
<section class="cvl-shell cvl-v4-brands">
    <header class="cvl-v4-section-head">
        <div>
            <span>MARCAS</span>
            <h2>Marcas profissionais</h2>
        </div>
        <a href="<?php echo esc_url( home_url( '/marcas/' ) ); ?>">VER MARCAS →</a>
    </header>

    <div class="cvl-v4-brand-grid">
        <?php foreach ( $brands as $brand ) : ?>
            <?php
            $brand_url = get_term_link( $brand );

            if ( is_wp_error( $brand_url ) ) {
                continue;
            }

            $thumbnail_id = absint( get_term_meta( $brand->term_id, 'thumbnail_id', true ) );
            ?>
            <a href="<?php echo esc_url( $brand_url ); ?>" aria-label="<?php echo esc_attr( $brand->name ); ?>">
                <?php
                if ( $thumbnail_id ) {
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
                } else {
                    echo '<strong>' . esc_html( $brand->name ) . '</strong>';
                }
                ?>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php
get_footer();
