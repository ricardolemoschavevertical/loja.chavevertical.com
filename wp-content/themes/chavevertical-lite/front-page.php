<?php
defined( 'ABSPATH' ) || exit;

get_header();

$shop_url   = cvl_shop_url();
$categories = array();
$products   = array();
$brands     = array();

if ( taxonomy_exists( 'product_cat' ) ) {
    $categories = get_terms( array(
        'taxonomy'   => 'product_cat',
        'parent'     => 0,
        'hide_empty' => false,
        'number'     => 8,
        'orderby'    => 'count',
        'order'      => 'DESC',
    ) );

    if ( is_wp_error( $categories ) ) {
        $categories = array();
    }
}

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

$hero_product = ! empty( $products ) ? reset( $products ) : null;
?>

<section class="cvl-hero cvl-hero-premium">
    <div class="cvl-shell cvl-hero-grid">
        <div class="cvl-hero-copy">
            <span class="cvl-kicker">EQUIPAMENTO PROFISSIONAL · APOIO ESPECIALIZADO</span>
            <h1>Ferramentas certas.<br><span>Trabalho melhor.</span></h1>
            <p>Máquinas, ferramentas e equipamento para oficina, indústria, construção e manutenção — com apoio de uma equipa que conhece o produto.</p>

            <div class="cvl-hero-actions">
                <a class="cvl-button cvl-button-primary" href="<?php echo esc_url( $shop_url ); ?>">
                    EXPLORAR CATÁLOGO <span aria-hidden="true">→</span>
                </a>
                <a class="cvl-button cvl-button-ghost" href="<?php echo esc_url( home_url( '/contactos/' ) ); ?>">PEDIR COTAÇÃO</a>
            </div>

            <div class="cvl-popular-searches" aria-label="Pesquisas populares">
                <span>Mais procurado:</span>
                <a href="<?php echo esc_url( home_url( '/?s=carro+de+ferramentas&post_type=product' ) ); ?>">Carros de ferramentas</a>
                <a href="<?php echo esc_url( home_url( '/?s=compressores&post_type=product' ) ); ?>">Compressores</a>
                <a href="<?php echo esc_url( home_url( '/?s=porta-paletes&post_type=product' ) ); ?>">Porta-paletes</a>
                <a href="<?php echo esc_url( home_url( '/?s=geradores&post_type=product' ) ); ?>">Geradores</a>
            </div>
        </div>

        <div class="cvl-hero-stage" aria-label="CHAVE VERTICAL">
            <span class="cvl-hero-grid-pattern" aria-hidden="true"></span>
            <span class="cvl-hero-orbit cvl-hero-orbit-one" aria-hidden="true"></span>
            <span class="cvl-hero-orbit cvl-hero-orbit-two" aria-hidden="true"></span>

            <?php if ( $hero_product instanceof WC_Product ) : ?>
                <a class="cvl-hero-product" href="<?php echo esc_url( $hero_product->get_permalink() ); ?>">
                    <span class="cvl-hero-product-image">
                        <?php echo wp_kses_post( $hero_product->get_image( 'woocommerce_single', array( 'loading' => 'eager', 'fetchpriority' => 'high' ) ) ); ?>
                    </span>
                    <span class="cvl-hero-product-card">
                        <small>PRODUTO EM DESTAQUE</small>
                        <strong><?php echo esc_html( $hero_product->get_name() ); ?></strong>
                        <?php if ( $hero_product->get_price_html() ) : ?>
                            <span><?php echo wp_kses_post( $hero_product->get_price_html() ); ?></span>
                        <?php endif; ?>
                    </span>
                </a>
            <?php else : ?>
                <div class="cvl-hero-visual-fallback">
                    <span class="cvl-hero-machine-mark">CV</span>
                    <strong>CATÁLOGO PROFISSIONAL</strong>
                    <small>OFICINA · INDÚSTRIA · CONSTRUÇÃO</small>
                </div>
            <?php endif; ?>

            <div class="cvl-hero-mini-card cvl-hero-mini-card-one">
                <span>30K+</span>
                <small>referências preparadas</small>
            </div>
            <div class="cvl-hero-mini-card cvl-hero-mini-card-two">
                <span>PT</span>
                <small>entregas em Portugal</small>
            </div>
        </div>
    </div>
</section>

<section class="cvl-benefits" aria-label="Vantagens Chave Vertical">
    <div class="cvl-shell cvl-benefit-grid">
        <article>
            <span class="cvl-benefit-icon">↗</span>
            <span><strong>Entregas em Portugal</strong><small>Transporte adequado ao equipamento</small></span>
        </article>
        <article>
            <span class="cvl-benefit-icon">✓</span>
            <span><strong>Compra acompanhada</strong><small>Informação clara antes da encomenda</small></span>
        </article>
        <article>
            <span class="cvl-benefit-icon">◎</span>
            <span><strong>Apoio especializado</strong><small>Comercial, técnico e pós-venda</small></span>
        </article>
        <article>
            <span class="cvl-benefit-icon">⚙</span>
            <span><strong>Catálogo profissional</strong><small>Máquinas, ferramentas e consumíveis</small></span>
        </article>
    </div>
</section>

<?php if ( ! empty( $categories ) ) : ?>
<section class="cvl-shell cvl-home-section cvl-category-section">
    <header class="cvl-section-heading cvl-section-heading-row">
        <div>
            <span>CATÁLOGO ORGANIZADO POR ÁREA</span>
            <h2>Categorias em destaque</h2>
        </div>
        <a href="<?php echo esc_url( $shop_url ); ?>">VER TODAS AS CATEGORIAS →</a>
    </header>

    <div class="cvl-category-grid cvl-category-grid-premium">
        <?php foreach ( $categories as $index => $category ) : ?>
            <?php
            $category_url = get_term_link( $category );
            $thumbnail_id = absint( get_term_meta( $category->term_id, 'thumbnail_id', true ) );

            if ( is_wp_error( $category_url ) ) {
                continue;
            }
            ?>
            <a class="cvl-category-card <?php echo 0 === $index ? 'is-featured' : ''; ?>" href="<?php echo esc_url( $category_url ); ?>">
                <span class="cvl-category-index"><?php echo esc_html( str_pad( (string) ( $index + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></span>
                <span class="cvl-category-image">
                    <?php
                    if ( $thumbnail_id ) {
                        echo wp_kses_post(
                            wp_get_attachment_image(
                                $thumbnail_id,
                                'medium',
                                false,
                                array(
                                    'loading' => 'lazy',
                                    'alt'     => $category->name,
                                )
                            )
                        );
                    } else {
                        echo '<span class="cvl-category-placeholder" aria-hidden="true">⚙</span>';
                    }
                    ?>
                </span>
                <span class="cvl-category-copy">
                    <strong><?php echo esc_html( $category->name ); ?></strong>
                    <small><?php echo esc_html( number_format_i18n( $category->count ) ); ?> produtos</small>
                    <span>EXPLORAR →</span>
                </span>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<section class="cvl-solutions cvl-shell" aria-label="Soluções profissionais">
    <a class="cvl-solution-card cvl-solution-card-dark" href="<?php echo esc_url( home_url( '/?s=oficina&post_type=product' ) ); ?>">
        <span class="cvl-solution-number">01</span>
        <span class="cvl-kicker">MAIS PROCURADO</span>
        <h2>Equipar uma oficina</h2>
        <p>Carros de ferramentas, elevadores, máquinas e equipamentos para utilização profissional.</p>
        <strong>VER SOLUÇÕES →</strong>
    </a>

    <a class="cvl-solution-card cvl-solution-card-teal" href="<?php echo esc_url( home_url( '/?s=compressor&post_type=product' ) ); ?>">
        <span class="cvl-solution-number">02</span>
        <span class="cvl-kicker">AR COMPRIMIDO</span>
        <h2>Produção e tratamento de ar</h2>
        <p>Compressores, secadores, enroladores, reservatórios e acessórios pneumáticos.</p>
        <strong>EXPLORAR →</strong>
    </a>

    <a class="cvl-solution-card cvl-solution-card-light" href="<?php echo esc_url( home_url( '/?s=porta-paletes&post_type=product' ) ); ?>">
        <span class="cvl-solution-number">03</span>
        <span class="cvl-kicker">MOVIMENTAÇÃO</span>
        <h2>Elevação e logística</h2>
        <p>Porta-paletes, mesas elevatórias, gruas e soluções para armazém e oficina.</p>
        <strong>VER EQUIPAMENTO →</strong>
    </a>

    <a class="cvl-solution-card cvl-solution-card-contact" href="<?php echo esc_url( home_url( '/contactos/' ) ); ?>">
        <span class="cvl-solution-number">04</span>
        <span class="cvl-kicker">NÃO ENCONTRA?</span>
        <h2>Tratamos da pesquisa por si.</h2>
        <p>Indique a aplicação, referência ou características técnicas e ajudamos a encontrar a solução.</p>
        <strong>PEDIR ACONSELHAMENTO →</strong>
    </a>
</section>

<?php if ( ! empty( $products ) ) : ?>
<section class="cvl-home-products">
    <div class="cvl-shell">
        <header class="cvl-section-heading cvl-section-heading-row">
            <div>
                <span>ENTRADAS RECENTES</span>
                <h2>Produtos em destaque</h2>
            </div>
            <a href="<?php echo esc_url( $shop_url ); ?>">VER CATÁLOGO COMPLETO →</a>
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

<section class="cvl-dark-promo">
    <div class="cvl-shell cvl-dark-promo-grid">
        <div>
            <span class="cvl-kicker">PROJETOS E FORNECIMENTOS PROFISSIONAIS</span>
            <h2>Precisa de equipar uma oficina, armazém ou linha de trabalho?</h2>
            <p>Envie-nos a lista de material ou as especificações. A equipa comercial ajuda a validar equivalências, disponibilidade e configuração.</p>
            <a class="cvl-button cvl-button-primary" href="<?php echo esc_url( home_url( '/contactos/' ) ); ?>">PEDIR PROPOSTA →</a>
        </div>
        <div class="cvl-promo-steps">
            <span><b>01</b> Analisamos o pedido</span>
            <span><b>02</b> Validamos a solução</span>
            <span><b>03</b> Preparamos a cotação</span>
        </div>
    </div>
</section>

<?php if ( ! empty( $brands ) ) : ?>
<section class="cvl-shell cvl-brand-section">
    <header class="cvl-section-heading cvl-section-heading-row">
        <div>
            <span>MARCAS PROFISSIONAIS</span>
            <h2>Trabalhamos com marcas de referência</h2>
        </div>
        <a href="<?php echo esc_url( home_url( '/marcas/' ) ); ?>">VER MARCAS →</a>
    </header>

    <div class="cvl-brand-grid">
        <?php foreach ( $brands as $brand ) : ?>
            <?php
            $brand_url    = get_term_link( $brand );
            $thumbnail_id = absint( get_term_meta( $brand->term_id, 'thumbnail_id', true ) );

            if ( is_wp_error( $brand_url ) ) {
                continue;
            }
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
                                'loading' => 'lazy',
                                'alt'     => $brand->name,
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

<section class="cvl-commercial-strip cvl-commercial-strip-premium">
    <div class="cvl-shell">
        <div>
            <span>RESPOSTA RÁPIDA</span>
            <h2>Tem uma referência ou ficha técnica?</h2>
            <p>Envie o pedido e ajudamos a encontrar o produto certo.</p>
        </div>
        <div class="cvl-commercial-actions">
            <a class="cvl-button cvl-button-primary" href="<?php echo esc_url( home_url( '/contactos/' ) ); ?>">PEDIR COTAÇÃO</a>
            <a class="cvl-button" href="tel:+351234020500">234 020 500</a>
        </div>
    </div>
</section>

<?php
get_footer();
