<?php
defined( 'ABSPATH' ) || exit;
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<div class="cvl-utility">
    <div class="cvl-shell">
        <span>Entregas gratuitas para encomendas iguais ou superiores a 100€ + IVA*</span>
    </div>
</div>

<header class="cvl-header">
    <div class="cvl-meta">
        <div class="cvl-shell cvl-meta-inner">
            <div class="cvl-meta-contact">
                <a href="mailto:geral@chavevertical.pt">geral@chavevertical.pt</a>
                <span aria-hidden="true"></span>
                <a href="https://wa.me/351914580410" target="_blank" rel="noopener noreferrer">WhatsApp</a>
            </div>
            <div class="cvl-meta-links">
                <a href="<?php echo esc_url( home_url( '/quem-somos/' ) ); ?>">Quem somos</a>
                <a href="<?php echo esc_url( home_url( '/contactos/' ) ); ?>">Contactos</a>
            </div>
        </div>
    </div>

    <div class="cvl-shell cvl-header-main">
        <a class="cvl-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php esc_attr_e( 'Chave Vertical — início', 'chavevertical-lite' ); ?>">
            <?php
            if ( has_custom_logo() ) {
                the_custom_logo();
            } else {
                echo '<span class="cvl-brand-text">CHAVE VERTICAL</span>';
            }
            ?>
        </a>

        <form class="cvl-search" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
            <label class="screen-reader-text" for="cvl-search-input"><?php esc_html_e( 'Pesquisar produtos', 'chavevertical-lite' ); ?></label>
            <button type="submit" aria-label="<?php esc_attr_e( 'Pesquisar', 'chavevertical-lite' ); ?>">⌕</button>
            <input id="cvl-search-input" type="search" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="Pesquisar por produto, marca ou referência…">
            <input type="hidden" name="post_type" value="product">
        </form>

        <div class="cvl-header-actions">
            <a class="cvl-phone" href="tel:+351234020500">
                <span class="cvl-phone-icon" aria-hidden="true">☎</span>
                <span><small>Contacte-nos</small><strong>234 020 500</strong></span>
            </a>
            <a class="cvl-icon-link" href="<?php echo esc_url( cvl_account_url() ); ?>" aria-label="<?php esc_attr_e( 'A minha conta', 'chavevertical-lite' ); ?>">◯</a>
            <a class="cvl-icon-link cvl-cart-link" href="<?php echo esc_url( cvl_cart_url() ); ?>" aria-label="<?php esc_attr_e( 'Carrinho', 'chavevertical-lite' ); ?>">
                🛒<span class="cvl-cart-count"><?php echo esc_html( cvl_cart_count() ); ?></span>
            </a>
            <button class="cvl-menu-toggle" type="button" aria-expanded="false" aria-controls="cvl-main-nav">☰</button>
        </div>
    </div>

    <nav class="cvl-nav" id="cvl-main-nav" aria-label="<?php esc_attr_e( 'Navegação principal', 'chavevertical-lite' ); ?>">
        <div class="cvl-shell cvl-nav-inner">
            <?php
            $cvl_top_categories = array();

            if ( taxonomy_exists( 'product_cat' ) ) {
                $cvl_top_categories = get_terms( array(
                    'taxonomy'   => 'product_cat',
                    'parent'     => 0,
                    'hide_empty' => false,
                    'number'     => 18,
                    'orderby'    => 'name',
                    'order'      => 'ASC',
                ) );

                if ( is_wp_error( $cvl_top_categories ) ) {
                    $cvl_top_categories = array();
                }
            }
            ?>

            <?php if ( ! empty( $cvl_top_categories ) ) : ?>
                <details class="cvl-categories-menu">
                    <summary class="cvl-categories-link">☰ <span>CATEGORIAS</span><span aria-hidden="true">⌄</span></summary>
                    <div class="cvl-categories-dropdown">
                        <?php foreach ( $cvl_top_categories as $cvl_category ) : ?>
                            <?php $cvl_category_url = get_term_link( $cvl_category ); ?>
                            <?php if ( ! is_wp_error( $cvl_category_url ) ) : ?>
                                <a href="<?php echo esc_url( $cvl_category_url ); ?>">
                                    <span><?php echo esc_html( $cvl_category->name ); ?></span>
                                    <small><?php echo esc_html( number_format_i18n( $cvl_category->count ) ); ?></small>
                                </a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <a class="cvl-categories-all" href="<?php echo esc_url( cvl_shop_url() ); ?>">
                            <strong><?php esc_html_e( 'VER TODOS OS PRODUTOS', 'chavevertical-lite' ); ?></strong>
                        </a>
                    </div>
                </details>
            <?php else : ?>
                <a class="cvl-categories-link" href="<?php echo esc_url( cvl_shop_url() ); ?>">☰ <span>CATEGORIAS</span></a>
            <?php endif; ?>

            <?php
            if ( has_nav_menu( 'primary' ) ) {
                wp_nav_menu( array(
                    'theme_location' => 'primary',
                    'container'      => false,
                    'menu_class'     => 'cvl-menu',
                    'fallback_cb'    => false,
                    'depth'          => 2,
                ) );
            } else {
                ?>
                <ul class="cvl-menu cvl-menu-fallback">
                    <li><a href="<?php echo esc_url( cvl_shop_url() ); ?>"><?php esc_html_e( 'PRODUTOS', 'chavevertical-lite' ); ?></a></li>
                    <li><a href="<?php echo esc_url( home_url( '/marcas/' ) ); ?>"><?php esc_html_e( 'MARCAS', 'chavevertical-lite' ); ?></a></li>
                    <li><a href="<?php echo esc_url( home_url( '/quem-somos/' ) ); ?>"><?php esc_html_e( 'QUEM SOMOS', 'chavevertical-lite' ); ?></a></li>
                    <li><a href="<?php echo esc_url( home_url( '/contactos/' ) ); ?>"><?php esc_html_e( 'CONTACTOS', 'chavevertical-lite' ); ?></a></li>
                </ul>
                <?php
            }
            ?>
        </div>
    </nav>
</header>

<main id="content" class="cvl-main">
