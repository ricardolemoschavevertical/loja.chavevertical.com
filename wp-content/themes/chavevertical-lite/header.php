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
    <div class="cvl-shell cvl-utility-inner">
        <span><strong>ENTREGAS GRATUITAS</strong> para encomendas iguais ou superiores a 100€ + IVA*</span>
        <span class="cvl-utility-note">Apoio especializado · Compra profissional</span>
    </div>
</div>

<header class="cvl-header">
    <div class="cvl-meta">
        <div class="cvl-shell cvl-meta-inner">
            <div class="cvl-meta-contact">
                <a href="mailto:geral@chavevertical.pt">
                    <span class="cvl-meta-icon" aria-hidden="true">✉</span>
                    geral@chavevertical.pt
                </a>
                <span class="cvl-meta-separator" aria-hidden="true"></span>
                <a href="https://wa.me/351914580410" target="_blank" rel="noopener noreferrer">
                    <span class="cvl-meta-whatsapp" aria-hidden="true">●</span>
                    WhatsApp 914 580 410
                </a>
            </div>
            <div class="cvl-meta-links">
                <a href="<?php echo esc_url( home_url( '/quem-somos/' ) ); ?>">Quem somos</a>
                <a href="<?php echo esc_url( home_url( '/contactos/' ) ); ?>">Contactos</a>
                <a href="<?php echo esc_url( cvl_account_url() ); ?>">Área de cliente</a>
            </div>
        </div>
    </div>

    <div class="cvl-shell cvl-header-main">
        <a class="cvl-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php esc_attr_e( 'Chave Vertical — início', 'chavevertical-lite' ); ?>">
            <?php if ( has_custom_logo() ) : ?>
                <?php the_custom_logo(); ?>
            <?php else : ?>
                <img
                    class="cvl-default-logo"
                    src="https://astro.chavevertical.com/logo-chavevertical.webp?v=20260927-2"
                    alt="CHAVE VERTICAL — Máquinas e Ferramentas Profissionais"
                    width="240"
                    height="58"
                    decoding="async"
                    fetchpriority="high"
                >
            <?php endif; ?>
        </a>

        <form class="cvl-search" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
            <label class="screen-reader-text" for="cvl-search-input"><?php esc_html_e( 'Pesquisar produtos', 'chavevertical-lite' ); ?></label>
            <button type="submit" aria-label="<?php esc_attr_e( 'Pesquisar', 'chavevertical-lite' ); ?>">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-4.5-4.5"></path></svg>
            </button>
            <input id="cvl-search-input" type="search" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="Pesquisar por produto, marca ou referência…" autocomplete="off">
            <input type="hidden" name="post_type" value="product">
        </form>

        <div class="cvl-header-actions">
            <a class="cvl-phone" href="tel:+351234020500">
                <span class="cvl-phone-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M6.6 3.8 9 3.2l2.1 5-1.7 1.4a14 14 0 0 0 5 5l1.4-1.7 5 2.1-.6 2.4a3 3 0 0 1-3 2.3C9.9 17.1 4.9 12.1 4.3 6.8a3 3 0 0 1 2.3-3Z"></path></svg>
                </span>
                <span><small>Contacte-nos</small><strong>234 020 500</strong></span>
            </a>
            <a class="cvl-icon-link" href="<?php echo esc_url( cvl_account_url() ); ?>" aria-label="<?php esc_attr_e( 'A minha conta', 'chavevertical-lite' ); ?>">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"></circle><path d="M4.5 21a7.5 7.5 0 0 1 15 0"></path></svg>
            </a>
            <a class="cvl-icon-link cvl-cart-link" href="<?php echo esc_url( cvl_cart_url() ); ?>" aria-label="<?php esc_attr_e( 'Carrinho', 'chavevertical-lite' ); ?>">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h2l2.1 10.2a2 2 0 0 0 2 1.6h7.8a2 2 0 0 0 2-1.6L20 8H7"></path><circle cx="10" cy="20" r="1.2"></circle><circle cx="18" cy="20" r="1.2"></circle></svg>
                <span class="cvl-cart-count"><?php echo esc_html( cvl_cart_count() ); ?></span>
            </a>
            <button class="cvl-menu-toggle" type="button" aria-expanded="false" aria-controls="cvl-main-nav" aria-label="<?php esc_attr_e( 'Abrir menu', 'chavevertical-lite' ); ?>">☰</button>
        </div>
    </div>

    <nav class="cvl-nav" id="cvl-main-nav" aria-label="<?php esc_attr_e( 'Navegação principal', 'chavevertical-lite' ); ?>">
        <div class="cvl-shell cvl-nav-inner">
            <?php
            $cvl_top_categories = array();

            if ( taxonomy_exists( 'product_cat' ) ) {
                $cvl_excluded_nav_categories = array();
                $cvl_uncategorized_nav = get_term_by( 'slug', 'uncategorized', 'product_cat' );

                if ( $cvl_uncategorized_nav && ! is_wp_error( $cvl_uncategorized_nav ) ) {
                    $cvl_excluded_nav_categories[] = (int) $cvl_uncategorized_nav->term_id;
                }

                $cvl_top_categories = get_terms( array(
                    'taxonomy'   => 'product_cat',
                    'parent'     => 0,
                    'hide_empty' => false,
                    'exclude'    => $cvl_excluded_nav_categories,
                    'number'     => 0,
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
                    <summary class="cvl-categories-link">
                        <span class="cvl-nav-menu-icon" aria-hidden="true">☰</span>
                        <span>CATEGORIAS</span>
                        <span class="cvl-nav-chevron" aria-hidden="true">⌄</span>
                    </summary>
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
                    <li><a href="<?php echo esc_url( cvl_shop_url() ); ?>">PRODUTOS</a></li>
                    <li><a href="<?php echo esc_url( home_url( '/marcas/' ) ); ?>">MARCAS</a></li>
                    <li><a href="<?php echo esc_url( home_url( '/catalogos/' ) ); ?>">CATÁLOGOS</a></li>
                    <li><a href="<?php echo esc_url( home_url( '/quem-somos/' ) ); ?>">QUEM SOMOS</a></li>
                    <li><a href="<?php echo esc_url( home_url( '/contactos/' ) ); ?>">CONTACTOS</a></li>
                </ul>
                <?php
            }
            ?>

            <a class="cvl-nav-cta" href="<?php echo esc_url( home_url( '/contactos/' ) ); ?>">
                <span>PEDIDO DE CONTACTO</span>
                <span aria-hidden="true">→</span>
            </a>
        </div>
    </nav>
</header>

<main id="content" class="cvl-main">
