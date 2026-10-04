<?php
defined( 'ABSPATH' ) || exit;

get_header();

$cvl_query          = get_search_query();
$cvl_selected_brand = isset( $_GET['marca'] ) ? sanitize_title( wp_unslash( $_GET['marca'] ) ) : '';
$cvl_selected_cat   = isset( $_GET['categoria'] ) ? sanitize_title( wp_unslash( $_GET['categoria'] ) ) : '';
$cvl_min_price      = isset( $_GET['min_price'] ) ? max( 0, (float) wc_format_decimal( wp_unslash( $_GET['min_price'] ) ) ) : 0.0;
$cvl_max_price      = isset( $_GET['max_price'] ) ? max( 0, (float) wc_format_decimal( wp_unslash( $_GET['max_price'] ) ) ) : 0.0;

$cvl_brand_facets    = array();
$cvl_category_facets = array();
$cvl_price_facet     = null;

if ( class_exists( 'CVOS_Plugin' ) ) {
    $cvl_engine = CVOS_Plugin::instance()->search;

    $brand_probe = $cvl_engine->search(
        array(
            'q'         => $cvl_query,
            'page'      => 1,
            'per_page'  => 1,
            'brand'     => '',
            'category'  => $cvl_selected_cat,
            'min_price' => $cvl_min_price,
            'max_price' => $cvl_max_price,
            'facets'    => true,
            'analytics' => false,
        )
    );
    if ( ! is_wp_error( $brand_probe ) ) {
        $cvl_brand_facets = $brand_probe['facets']['brands'] ?? array();
    }

    $category_probe = $cvl_engine->search(
        array(
            'q'         => $cvl_query,
            'page'      => 1,
            'per_page'  => 1,
            'brand'     => $cvl_selected_brand,
            'category'  => '',
            'min_price' => $cvl_min_price,
            'max_price' => $cvl_max_price,
            'facets'    => true,
            'analytics' => false,
        )
    );
    if ( ! is_wp_error( $category_probe ) ) {
        $cvl_category_facets = $category_probe['facets']['categories'] ?? array();
    }

    $price_probe = $cvl_engine->search(
        array(
            'q'         => $cvl_query,
            'page'      => 1,
            'per_page'  => 1,
            'brand'     => $cvl_selected_brand,
            'category'  => $cvl_selected_cat,
            'min_price' => 0,
            'max_price' => 0,
            'facets'    => true,
            'analytics' => false,
        )
    );
    if ( ! is_wp_error( $price_probe ) ) {
        $cvl_price_facet = $price_probe['facets']['price'] ?? null;
    }
}

// Uma categoria que corresponda ao termo pesquisado fica em primeiro lugar.
$cvl_query_slug = sanitize_title( $cvl_query );
usort(
    $cvl_category_facets,
    static function ( array $a, array $b ) use ( $cvl_query_slug ): int {
        $a_slug = sanitize_title( (string) ( $a['slug'] ?? '' ) );
        $b_slug = sanitize_title( (string) ( $b['slug'] ?? '' ) );
        $a_name = sanitize_title( (string) ( $a['name'] ?? '' ) );
        $b_name = sanitize_title( (string) ( $b['name'] ?? '' ) );

        $a_exact = ( $a_slug === $cvl_query_slug || $a_name === $cvl_query_slug ) ? 1 : 0;
        $b_exact = ( $b_slug === $cvl_query_slug || $b_name === $cvl_query_slug ) ? 1 : 0;
        if ( $a_exact !== $b_exact ) {
            return $b_exact <=> $a_exact;
        }

        $a_contains = $cvl_query_slug && ( str_contains( $a_slug, $cvl_query_slug ) || str_contains( $a_name, $cvl_query_slug ) ) ? 1 : 0;
        $b_contains = $cvl_query_slug && ( str_contains( $b_slug, $cvl_query_slug ) || str_contains( $b_name, $cvl_query_slug ) ) ? 1 : 0;
        if ( $a_contains !== $b_contains ) {
            return $b_contains <=> $a_contains;
        }

        return (int) ( $b['count'] ?? 0 ) <=> (int) ( $a['count'] ?? 0 );
    }
);

$cvl_filter_url = static function ( array $overrides = array() ) use (
    $cvl_query,
    $cvl_selected_brand,
    $cvl_selected_cat,
    $cvl_min_price,
    $cvl_max_price
): string {
    $state = array(
        's'         => $cvl_query,
        'post_type' => 'product',
        'marca'     => $cvl_selected_brand,
        'categoria' => $cvl_selected_cat,
        'min_price' => $cvl_min_price > 0 ? $cvl_min_price : '',
        'max_price' => $cvl_max_price > 0 ? $cvl_max_price : '',
    );

    foreach ( $overrides as $key => $value ) {
        $state[ $key ] = $value;
    }

    return add_query_arg( array_filter( $state, static fn( $value ) => '' !== $value && null !== $value ), home_url( '/' ) );
};

$cvl_clear_url = add_query_arg(
    array(
        's'         => $cvl_query,
        'post_type' => 'product',
    ),
    home_url( '/' )
);

global $wp_query;
$cvl_found = (int) $wp_query->found_posts;
?>
<div class="cvl-shell cvl-content cvl-search-page">
    <header class="cvl-search-heading">
        <span><?php esc_html_e( 'PESQUISA', 'chavevertical-lite' ); ?></span>
        <h1><?php printf( esc_html__( 'Resultados para: %s', 'chavevertical-lite' ), esc_html( $cvl_query ) ); ?></h1>
        <p class="cvl-search-count">
            <?php
            printf(
                esc_html( _n( '%s produto encontrado', '%s produtos encontrados', $cvl_found, 'chavevertical-lite' ) ),
                esc_html( number_format_i18n( $cvl_found ) )
            );
            ?>
        </p>
    </header>

    <div class="cvl-search-layout">
        <aside class="cvl-search-filters" aria-label="<?php esc_attr_e( 'Filtros da pesquisa', 'chavevertical-lite' ); ?>">
            <div class="cvl-search-filters-head">
                <strong><?php esc_html_e( 'FILTRAR RESULTADOS', 'chavevertical-lite' ); ?></strong>
                <?php if ( $cvl_selected_brand || $cvl_selected_cat || $cvl_min_price > 0 || $cvl_max_price > 0 ) : ?>
                    <a href="<?php echo esc_url( $cvl_clear_url ); ?>"><?php esc_html_e( 'Limpar', 'chavevertical-lite' ); ?></a>
                <?php endif; ?>
            </div>

            <?php if ( $cvl_brand_facets ) : ?>
                <section class="cvl-search-filter-group">
                    <h2><?php esc_html_e( 'MARCAS', 'chavevertical-lite' ); ?></h2>
                    <div class="cvl-search-filter-list">
                        <?php foreach ( $cvl_brand_facets as $facet ) : ?>
                            <?php
                            $slug = sanitize_title( (string) ( $facet['slug'] ?? '' ) );
                            if ( ! $slug ) {
                                continue;
                            }
                            $selected = $slug === $cvl_selected_brand;
                            ?>
                            <a class="<?php echo $selected ? 'is-active' : ''; ?>" href="<?php echo esc_url( $cvl_filter_url( array( 'marca' => $selected ? '' : $slug ) ) ); ?>">
                                <span><?php echo esc_html( $facet['name'] ?? $slug ); ?></span>
                                <small><?php echo esc_html( number_format_i18n( (int) ( $facet['count'] ?? 0 ) ) ); ?></small>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ( $cvl_category_facets ) : ?>
                <section class="cvl-search-filter-group">
                    <h2><?php esc_html_e( 'CATEGORIAS', 'chavevertical-lite' ); ?></h2>
                    <div class="cvl-search-filter-list">
                        <?php foreach ( $cvl_category_facets as $facet ) : ?>
                            <?php
                            $slug = sanitize_title( (string) ( $facet['slug'] ?? '' ) );
                            if ( ! $slug ) {
                                continue;
                            }
                            $selected = $slug === $cvl_selected_cat;
                            $matched  = $cvl_query_slug && ( $slug === $cvl_query_slug || sanitize_title( (string) ( $facet['name'] ?? '' ) ) === $cvl_query_slug );
                            ?>
                            <a class="<?php echo esc_attr( trim( ( $selected ? 'is-active ' : '' ) . ( $matched ? 'is-query-match' : '' ) ) ); ?>" href="<?php echo esc_url( $cvl_filter_url( array( 'categoria' => $selected ? '' : $slug ) ) ); ?>">
                                <span><?php echo esc_html( $facet['name'] ?? $slug ); ?></span>
                                <small><?php echo esc_html( number_format_i18n( (int) ( $facet['count'] ?? 0 ) ) ); ?></small>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ( is_array( $cvl_price_facet ) && (float) ( $cvl_price_facet['max'] ?? 0 ) > 0 ) : ?>
                <?php
                $cvl_price_floor = max( 0, floor( (float) ( $cvl_price_facet['min'] ?? 0 ) ) );
                $cvl_price_ceil  = max( $cvl_price_floor, ceil( (float) ( $cvl_price_facet['max'] ?? 0 ) ) );
                ?>
                <section class="cvl-search-filter-group">
                    <h2><?php esc_html_e( 'PREÇO', 'chavevertical-lite' ); ?></h2>
                    <form class="cvl-search-price-filter" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
                        <input type="hidden" name="s" value="<?php echo esc_attr( $cvl_query ); ?>">
                        <input type="hidden" name="post_type" value="product">
                        <?php if ( $cvl_selected_brand ) : ?><input type="hidden" name="marca" value="<?php echo esc_attr( $cvl_selected_brand ); ?>"><?php endif; ?>
                        <?php if ( $cvl_selected_cat ) : ?><input type="hidden" name="categoria" value="<?php echo esc_attr( $cvl_selected_cat ); ?>"><?php endif; ?>

                        <div class="cvl-search-price-inputs">
                            <label>
                                <span><?php esc_html_e( 'Mín.', 'chavevertical-lite' ); ?></span>
                                <input type="number" min="0" step="0.01" name="min_price" value="<?php echo $cvl_min_price > 0 ? esc_attr( wc_format_decimal( $cvl_min_price, 2 ) ) : ''; ?>" placeholder="<?php echo esc_attr( number_format_i18n( $cvl_price_floor, 0 ) ); ?>">
                            </label>
                            <label>
                                <span><?php esc_html_e( 'Máx.', 'chavevertical-lite' ); ?></span>
                                <input type="number" min="0" step="0.01" name="max_price" value="<?php echo $cvl_max_price > 0 ? esc_attr( wc_format_decimal( $cvl_max_price, 2 ) ) : ''; ?>" placeholder="<?php echo esc_attr( number_format_i18n( $cvl_price_ceil, 0 ) ); ?>">
                            </label>
                        </div>
                        <div class="cvl-search-price-range"><?php echo wp_kses_post( wc_price( $cvl_price_floor ) ); ?> – <?php echo wp_kses_post( wc_price( $cvl_price_ceil ) ); ?></div>
                        <button type="submit"><?php esc_html_e( 'APLICAR PREÇO', 'chavevertical-lite' ); ?></button>
                    </form>
                </section>
            <?php endif; ?>
        </aside>

        <section class="cvl-search-products woocommerce">
            <?php if ( have_posts() ) : ?>
                <?php woocommerce_product_loop_start(); ?>
                <?php while ( have_posts() ) : the_post(); ?>
                    <?php if ( 'product' === get_post_type() ) : ?>
                        <?php wc_get_template_part( 'content', 'product' ); ?>
                    <?php endif; ?>
                <?php endwhile; ?>
                <?php woocommerce_product_loop_end(); ?>
                <?php woocommerce_pagination(); ?>
            <?php else : ?>
                <div class="cvl-empty-state">
                    <h2><?php esc_html_e( 'Não encontrámos produtos.', 'chavevertical-lite' ); ?></h2>
                    <p><?php esc_html_e( 'Experimente outra referência, marca ou descrição, ou limpe os filtros selecionados.', 'chavevertical-lite' ); ?></p>
                    <?php if ( $cvl_selected_brand || $cvl_selected_cat || $cvl_min_price > 0 || $cvl_max_price > 0 ) : ?>
                        <a class="cvl-button cvl-button-primary" href="<?php echo esc_url( $cvl_clear_url ); ?>"><?php esc_html_e( 'LIMPAR FILTROS', 'chavevertical-lite' ); ?></a>
                    <?php else : ?>
                        <a class="cvl-button cvl-button-primary" href="<?php echo esc_url( cvl_shop_url() ); ?>"><?php esc_html_e( 'VER CATÁLOGO', 'chavevertical-lite' ); ?></a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>
<?php get_footer(); ?>
