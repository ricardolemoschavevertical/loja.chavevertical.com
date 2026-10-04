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

$hero_categories     = array_slice( $categories, 0, 3 );
$homepage_categories = array_slice( $categories, 0, 10 );

if ( taxonomy_exists( 'product_brand' ) ) {
    $brands = get_terms( array(
        'taxonomy'   => 'product_brand',
        'hide_empty' => false,
        'number'     => 0,
        'orderby'    => 'name',
        'order'      => 'ASC',
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

<?php
$hero_primary_category   = $hero_categories[0] ?? null;
$hero_secondary_category = $hero_categories[1] ?? null;
$hero_primary_url        = $shop_url;
$hero_secondary_url      = $shop_url;
$hero_primary_image      = '';
$hero_secondary_image    = '';

if ( $hero_primary_category instanceof WP_Term ) {
    $primary_term_url = get_term_link( $hero_primary_category );
    if ( ! is_wp_error( $primary_term_url ) ) {
        $hero_primary_url = $primary_term_url;
    }
    $hero_primary_image = $cvl_category_image( $hero_primary_category, 'large', 'eager' );
}

if ( $hero_secondary_category instanceof WP_Term ) {
    $secondary_term_url = get_term_link( $hero_secondary_category );
    if ( ! is_wp_error( $secondary_term_url ) ) {
        $hero_secondary_url = $secondary_term_url;
    }
    $hero_secondary_image = $cvl_category_image( $hero_secondary_category, 'large', 'eager' );
}

if ( ! $hero_primary_image && ! empty( $products[0] ) ) {
    $hero_primary_image = $products[0]->get_image(
        'woocommerce_single',
        array(
            'loading'       => 'eager',
            'decoding'      => 'async',
            'fetchpriority' => 'high',
        )
    );
}

if ( ! $hero_secondary_image && ! empty( $products[1] ) ) {
    $hero_secondary_image = $products[1]->get_image(
        'woocommerce_single',
        array(
            'loading'  => 'eager',
            'decoding' => 'async',
        )
    );
}
?>

<section class="cvl-ref-hero cvl-ref-hero-showcase">
    <div class="cvl-shell cvl-ref-hero-grid">
        <article class="cvl-ref-hero-main">
            <div class="cvl-ref-hero-main-copy">
                <span class="cvl-ref-kicker">PARA QUEM FAZ ACONTECER</span>
                <h1>O trabalho é exigente.<br><span>A escolha é simples.</span></h1>
                <p>Máquinas, ferramentas e equipamento profissional para oficina, indústria, construção e manutenção — com apoio de quem conhece o produto.</p>

                <div class="cvl-ref-hero-actions">
                    <a class="cvl-ref-button cvl-ref-button-primary" href="<?php echo esc_url( $shop_url ); ?>">
                        EXPLORAR EQUIPAMENTOS <span aria-hidden="true">→</span>
                    </a>
                    <a class="cvl-ref-hero-help" href="<?php echo esc_url( home_url( '/contactos/' ) ); ?>">
                        Precisa de ajuda? <span aria-hidden="true">→</span>
                    </a>
                </div>
            </div>

            <a class="cvl-ref-hero-main-media" href="<?php echo esc_url( $hero_primary_url ); ?>" aria-label="<?php esc_attr_e( 'Explorar equipamento profissional', 'chavevertical-lite' ); ?>">
                <span class="cvl-ref-hero-orbit" aria-hidden="true"></span>
                <?php if ( $hero_primary_image ) : ?>
                    <?php echo wp_kses_post( $hero_primary_image ); ?>
                <?php else : ?>
                    <span class="cvl-ref-hero-media-fallback" aria-hidden="true">CV</span>
                <?php endif; ?>
                <span class="cvl-ref-hero-professional-badge">ESCOLHA<br>PROFISSIONAL</span>
            </a>

            <span class="cvl-ref-hero-signature" aria-hidden="true">CHAVE VERTICAL — 01</span>
        </article>

        <a class="cvl-ref-hero-side" href="<?php echo esc_url( $hero_secondary_url ); ?>">
            <span class="cvl-ref-hero-side-copy">
                <small>CATÁLOGO PROFISSIONAL</small>
                <strong>Tudo num só lugar.<br>Pronto a trabalhar.</strong>
                <span>Mais de 30.000 referências para oficina, indústria e construção.</span>
            </span>

            <span class="cvl-ref-hero-side-media">
                <?php if ( $hero_secondary_image ) : ?>
                    <?php echo wp_kses_post( $hero_secondary_image ); ?>
                <?php else : ?>
                    <span class="cvl-ref-hero-media-fallback" aria-hidden="true">CV</span>
                <?php endif; ?>
            </span>

            <span class="cvl-ref-hero-side-arrow" aria-hidden="true">→</span>
        </a>
    </div>
</section>

<section class="cvl-ref-benefits" aria-label="Vantagens Chave Vertical">
    <div class="cvl-shell cvl-ref-benefit-grid">
        <div><span class="cvl-ref-benefit-icon" aria-hidden="true">🚚</span><span><b>Entregas em Portugal</b><small>Encomendas iguais ou superiores a 100 € + IVA*</small></span></div>
        <div><span class="cvl-ref-benefit-icon" aria-hidden="true">📦</span><span><b>Stock para entrega imediata</b><small>Milhares de referências disponíveis</small></span></div>
        <div><span class="cvl-ref-benefit-icon cvl-ref-benefit-callcenter" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4.5 13v-1a7.5 7.5 0 0 1 15 0v1"></path><path d="M4.5 13h2v5h-2a2 2 0 0 1-2-2v-1a2 2 0 0 1 2-2Z"></path><path d="M19.5 13h-2v5h2a2 2 0 0 0 2-2v-1a2 2 0 0 0-2-2Z"></path><path d="M17.5 18c-.4 2-2 3-4.5 3h-2"></path><circle cx="10" cy="21" r=".8"></circle></svg></span><span><b>Apoio especializado</b><small>Comercial, técnico e pós-venda</small></span></div>
        <div><span class="cvl-ref-benefit-icon" aria-hidden="true">🛒</span><span><b>Mais de 30.000 referências</b><small>Máquinas, ferramentas e consumíveis</small></span></div>
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
        <a href="<?php echo esc_url( $shop_url ); ?>">VER TODAS AS CATEGORIAS</a>
    </header>

    <div class="cvl-v4-category-grid">
        <?php foreach ( $homepage_categories as $category ) : ?>
            <?php
            $category_url = get_term_link( $category );

            if ( is_wp_error( $category_url ) ) {
                continue;
            }

            $image = $cvl_category_image( $category, 'medium_large' );
            ?>
            <a
                class="cvl-v4-category-card"
                href="<?php echo esc_url( $category_url ); ?>"
                aria-label="<?php echo esc_attr( $category->name ); ?>"
                title="<?php echo esc_attr( $category->name ); ?>"
            >
                <span class="cvl-v4-category-media">
                    <?php
                    if ( $image ) {
                        echo wp_kses_post( $image );
                    } else {
                        echo '<span class="cvl-v4-category-fallback" aria-hidden="true">CV</span>';
                    }
                    ?>
                </span>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>



<?php
$homepage_highlights = function_exists( 'cvl_get_homepage_highlights' )
    ? cvl_get_homepage_highlights()
    : array();
?>

<?php if ( ! empty( $homepage_highlights ) ) : ?>
<section class="cvl-shell cvl-homepage-highlights-section" aria-label="<?php esc_attr_e( 'Destaques Chave Vertical', 'chavevertical-lite' ); ?>">
    <div class="cvl-solutions cvl-homepage-highlights">
        <?php foreach ( $homepage_highlights as $highlight_index => $highlight ) : ?>
            <?php
            $highlight_image = function_exists( 'cvl_homepage_highlight_image_url' )
                ? cvl_homepage_highlight_image_url( $highlight )
                : '';
            $highlight_images = function_exists( 'cvl_homepage_highlight_rotation_images' )
                ? cvl_homepage_highlight_rotation_images( $highlight )
                : array_filter( array( $highlight_image ) );
            $highlight_rotation_seconds = isset( $highlight['rotation_seconds'] )
                ? max( 2, min( 60, absint( $highlight['rotation_seconds'] ) ) )
                : 5;
            $highlight_number = str_pad( (string) ( $highlight_index + 1 ), 2, '0', STR_PAD_LEFT );
            $highlight_style  = sprintf(
                '--cvl-highlight-bg:%1$s;--cvl-highlight-color:%2$s;',
                esc_attr( $highlight['background'] ),
                esc_attr( $highlight['text_color'] )
            );
            ?>
            <a
                class="cvl-solution-card cvl-homepage-highlight"
                href="<?php echo esc_url( $highlight['url'] ); ?>"
                style="<?php echo esc_attr( $highlight_style ); ?>"
            >
                <span
                    class="cvl-homepage-highlight-media<?php echo ! empty( $highlight_images ) ? '' : ' is-empty'; ?>"
                    <?php if ( count( $highlight_images ) > 1 ) : ?>
                        data-cvl-highlight-rotation
                        data-cvl-rotation-ms="<?php echo esc_attr( (string) ( $highlight_rotation_seconds * 1000 ) ); ?>"
                    <?php endif; ?>
                >
                    <?php if ( ! empty( $highlight_images ) ) : ?>
                        <?php foreach ( $highlight_images as $highlight_image_index => $rotation_image ) : ?>
                            <img
                                class="cvl-homepage-highlight-slide<?php echo 0 === $highlight_image_index ? ' is-active' : ''; ?>"
                                src="<?php echo esc_url( $rotation_image ); ?>"
                                alt="<?php echo esc_attr( $highlight['title'] ); ?>"
                                loading="lazy"
                                decoding="async"
                                aria-hidden="<?php echo 0 === $highlight_image_index ? 'false' : 'true'; ?>"
                            >
                        <?php endforeach; ?>
                    <?php else : ?>
                        <span class="cvl-homepage-highlight-placeholder" aria-hidden="true">CV</span>
                    <?php endif; ?>
                </span>

                <span class="cvl-homepage-highlight-content">
                    <span class="cvl-solution-number" aria-hidden="true"><?php echo esc_html( $highlight_number ); ?></span>
                    <span class="cvl-kicker"><?php echo esc_html( $highlight['eyebrow'] ); ?></span>
                    <h2><?php echo esc_html( $highlight['title'] ); ?></h2>
                    <p><?php echo esc_html( $highlight['description'] ); ?></p>
                    <strong><?php echo esc_html( $highlight['cta'] ); ?></strong>
                </span>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>


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
        <?php
        $cvl_brand_duration = max(
            32,
            (int) round( ( max( 1, count( $brands ) ) / 28 ) * 32 )
        );
        ?>
        <div class="cvl-brand-carousel-track" style="--cvl-brand-duration: <?php echo esc_attr( (string) $cvl_brand_duration ); ?>s;">
            <?php
            $cvl_brand_track = array_merge( $brands, $brands );

            foreach ( $cvl_brand_track as $brand ) :
                $brand_url = get_term_link( $brand );

                if ( is_wp_error( $brand_url ) ) {
                    continue;
                }

                $thumbnail_id = absint( get_term_meta( $brand->term_id, 'thumbnail_id', true ) );

                ?>
                <a
                    class="cvl-brand-carousel-item"
                    href="<?php echo esc_url( $brand_url ); ?>"
                    title="<?php echo esc_attr( $brand->name ); ?>"
                    tabindex="-1"
                >
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
                        echo '<span class="cvl-brand-carousel-name">' . esc_html( $brand->name ) . '</span>';
                    }
                    ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php
get_footer();
