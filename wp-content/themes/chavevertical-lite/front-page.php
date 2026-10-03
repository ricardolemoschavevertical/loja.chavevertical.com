<?php
defined( 'ABSPATH' ) || exit;

get_header();

$shop_url   = cvl_shop_url();
$categories = array();
$products   = array();

if ( taxonomy_exists( 'product_cat' ) ) {
    $categories = get_terms( array(
        'taxonomy'   => 'product_cat',
        'parent'     => 0,
        'hide_empty' => false,
        'number'     => 8,
        'orderby'    => 'name',
        'order'      => 'ASC',
    ) );

    if ( is_wp_error( $categories ) ) {
        $categories = array();
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
?>

<section class="cvl-hero">
    <div class="cvl-shell cvl-hero-grid">
        <div>
            <span class="cvl-kicker">MÁQUINAS E FERRAMENTAS PROFISSIONAIS</span>
            <h1>Equipamento profissional para oficina, indústria e construção.</h1>
            <p>Uma loja direta, rápida e focada no catálogo. Pesquisa simples, informação comercial clara e compra sem elementos desnecessários.</p>
            <div class="cvl-hero-actions">
                <a class="cvl-button cvl-button-primary" href="<?php echo esc_url( $shop_url ); ?>">VER PRODUTOS</a>
                <a class="cvl-button" href="<?php echo esc_url( home_url( '/contactos/' ) ); ?>">CONTACTOS</a>
            </div>
        </div>

        <div class="cvl-hero-panel">
            <span class="cvl-hero-panel-label">CHAVE VERTICAL</span>
            <strong>Máquinas, ferramentas e equipamento técnico.</strong>
            <span>Apoio comercial especializado antes e depois da compra.</span>
            <a href="tel:+351234020500">234 020 500 →</a>
        </div>
    </div>
</section>

<?php if ( ! empty( $categories ) ) : ?>
<section class="cvl-shell cvl-home-section cvl-category-section">
    <header class="cvl-section-heading cvl-section-heading-row">
        <div>
            <span>CATÁLOGO</span>
            <h2>Comprar por categoria</h2>
        </div>
        <a href="<?php echo esc_url( $shop_url ); ?>">VER TODAS →</a>
    </header>

    <div class="cvl-category-grid">
        <?php foreach ( $categories as $category ) : ?>
            <?php
            $category_url = get_term_link( $category );
            $thumbnail_id = absint( get_term_meta( $category->term_id, 'thumbnail_id', true ) );

            if ( is_wp_error( $category_url ) ) {
                continue;
            }
            ?>
            <a class="cvl-category-card" href="<?php echo esc_url( $category_url ); ?>">
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
                        echo '<span class="cvl-category-placeholder" aria-hidden="true">+</span>';
                    }
                    ?>
                </span>
                <strong><?php echo esc_html( $category->name ); ?></strong>
                <?php if ( $category->count ) : ?>
                    <small><?php echo esc_html( number_format_i18n( $category->count ) ); ?> produtos</small>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php if ( ! empty( $products ) ) : ?>
<section class="cvl-home-products">
    <div class="cvl-shell">
        <header class="cvl-section-heading cvl-section-heading-row">
            <div>
                <span>NOVIDADES</span>
                <h2>Produtos recentes</h2>
            </div>
            <a href="<?php echo esc_url( $shop_url ); ?>">VER CATÁLOGO →</a>
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
<?php else : ?>
<section class="cvl-shell cvl-home-section">
    <header class="cvl-section-heading">
        <span>CATÁLOGO</span>
        <h2>Preparado para receber os produtos</h2>
        <p>Assim que o catálogo for sincronizado, os produtos passam a aparecer automaticamente nesta página sem necessidade de reconstruir o layout.</p>
    </header>

    <div class="cvl-feature-grid">
        <article><strong>PESQUISA RÁPIDA</strong><span>Pesquisa nativa por produto, marca ou referência.</span></article>
        <article><strong>COMPRA DIRETA</strong><span>WooCommerce nativo, sem camadas de page builder.</span></article>
        <article><strong>CATÁLOGO ESCALÁVEL</strong><span>Estrutura preparada para dezenas de milhares de produtos.</span></article>
    </div>
</section>
<?php endif; ?>

<section class="cvl-commercial-strip">
    <div class="cvl-shell">
        <div>
            <span>APOIO COMERCIAL</span>
            <h2>Precisa de ajuda a escolher o equipamento?</h2>
            <p>Fale diretamente com a nossa equipa comercial.</p>
        </div>
        <div class="cvl-commercial-actions">
            <a class="cvl-button cvl-button-primary" href="tel:+351234020500">234 020 500</a>
            <a class="cvl-button" href="https://wa.me/351914580410" target="_blank" rel="noopener noreferrer">WHATSAPP</a>
        </div>
    </div>
</section>

<?php
get_footer();
