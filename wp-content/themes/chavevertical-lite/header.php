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

<div class="cvl-utility cvl-utility-contactbar">
    <div class="cvl-shell cvl-utility-inner">
        <div class="cvl-meta-contact">
            <a class="cvl-meta-link" href="tel:+351234020500" aria-label="Ligar para a Chave Vertical: 234 020 500">
                <span class="cvl-meta-link-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M6.8 3.6 9.1 7.5c.3.5.2 1.1-.2 1.5l-1.5 1.2a13.8 13.8 0 0 0 6.4 6.4l1.2-1.5c.4-.4 1-.5 1.5-.2l3.9 2.3c.5.3.8.9.6 1.5l-.6 1.8c-.2.6-.8 1-1.5 1C9.7 21.5 2.5 14.3 2.5 5.1c0-.7.4-1.3 1-1.5l1.8-.6c.6-.2 1.2.1 1.5.6Z"></path></svg>
                </span>
                <span class="cvl-utility-contact-label">234 020 500</span>
            </a>
            <span class="cvl-meta-separator" aria-hidden="true"></span>
            <a class="cvl-meta-link" href="mailto:geral@chavevertical.pt">
                <span class="cvl-meta-link-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M4 6.5h16v11H4z"></path><path d="m5 7.5 7 5.5 7-5.5"></path></svg>
                </span>
                <span class="cvl-utility-contact-label">geral@chavevertical.pt</span>
            </a>
            <span class="cvl-meta-separator" aria-hidden="true"></span>
            <a class="cvl-meta-link" href="https://wa.me/351914580410" target="_blank" rel="noopener noreferrer">
                <span class="cvl-meta-link-icon cvl-meta-link-icon-whatsapp" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M12.04 2a9.91 9.91 0 0 0-8.59 14.86L2.05 22l5.25-1.38A9.9 9.9 0 1 0 12.04 2Zm5.79 14.1c-.24.68-1.4 1.25-1.92 1.32-.5.07-1.14.1-3.32-.8-2.79-1.15-4.58-4.01-4.72-4.2-.14-.19-1.13-1.5-1.13-2.86 0-1.36.71-2.03.96-2.31.25-.28.56-.35.75-.35.19 0 .37 0 .53.01.17.01.4.06.61.57.24.57.81 1.98.88 2.12.07.14.12.31.02.5-.09.19-.14.31-.28.47-.14.17-.3.37-.43.5-.14.14-.29.3-.12.59.16.28.73 1.2 1.56 1.94 1.07.95 1.97 1.25 2.25 1.39.28.14.45.12.61-.07.17-.19.72-.84.91-1.13.19-.28.38-.24.64-.14.26.09 1.66.78 1.95.92.28.14.47.21.54.33.07.12.07.7-.17 1.38Z"></path></svg>
                </span>
                <span class="cvl-utility-contact-label">WhatsApp</span>
            </a>
        </div>

        <div class="cvl-meta-social" aria-label="Redes sociais">
            <a href="https://facebook.com/chavevertical" target="_blank" rel="noopener noreferrer" aria-label="Facebook"><svg viewBox="0 0 24 24"><path d="M14 8h3V4h-3c-3 0-5 2-5 5v2H6v4h3v7h4v-7h3l1-4h-4V9c0-.7.3-1 1-1z"></path></svg></a>
            <a href="https://x.com/ChaveVertical" target="_blank" rel="noopener noreferrer" aria-label="X"><svg viewBox="0 0 24 24"><path d="M5 4l14 16M19 4L5 20"></path></svg></a>
            <a href="https://youtube.com/@chavevertical" target="_blank" rel="noopener noreferrer" aria-label="YouTube"><svg viewBox="0 0 24 24"><path d="M21 8.2a3 3 0 0 0-2.1-2.1C17 5.5 12 5.5 12 5.5s-5 0-6.9.6A3 3 0 0 0 3 8.2 31 31 0 0 0 3 12a31 31 0 0 0 .1 3.8 3 3 0 0 0 2.1 2.1c1.9.6 6.9.6 6.9.6s5 0 6.9-.6a3 3 0 0 0 2.1-2.1A31 31 0 0 0 21 12a31 31 0 0 0 0-3.8z"></path><path class="cvl-social-play" d="M10 9l5 3-5 3z"></path></svg></a>
            <a href="https://instagram.com/chavevertical" target="_blank" rel="noopener noreferrer" aria-label="Instagram"><svg viewBox="0 0 24 24"><rect x="4" y="4" width="16" height="16" rx="4"></rect><circle cx="12" cy="12" r="3.5"></circle><circle class="cvl-social-dot" cx="17.4" cy="6.7" r="1"></circle></svg></a>
            <a href="https://www.linkedin.com/company/chave-vertical/" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn"><svg viewBox="0 0 24 24"><path d="M6 9v10M6 5.5v.1M10.5 19v-6c0-2 1.3-3.5 3.4-3.5 2 0 3.1 1.3 3.1 3.5v6M10.5 13.5c0-2.2 1.4-4 3.8-4"></path></svg></a>
        </div>
    </div>
</div>

<header class="cvl-header">

    <div class="cvl-shell cvl-header-main">
        <a class="cvl-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php esc_attr_e( 'Chave Vertical — início', 'chavevertical-lite' ); ?>">
            <?php if ( has_custom_logo() ) : ?>
                <?php the_custom_logo(); ?>
            <?php else : ?>
                <img class="cvl-default-logo" src="https://astro.chavevertical.com/logo-chavevertical.webp?v=20260927-2" alt="Chave Vertical" width="220" height="70" decoding="async" fetchpriority="high">
            <?php endif; ?>
        </a>

        <form class="cvl-search" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
            <label class="screen-reader-text" for="cvl-search-input"><?php esc_html_e( 'Pesquisar produtos', 'chavevertical-lite' ); ?></label>
            <button type="submit" aria-label="<?php esc_attr_e( 'Pesquisar', 'chavevertical-lite' ); ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-4.5-4.5"></path></svg></button>
            <input id="cvl-search-input" type="search" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="Pesquisar por produto, marca ou referência…" autocomplete="off">
            <input type="hidden" name="post_type" value="product">
        </form>

        <div class="cvl-header-actions">
            <a class="cvl-header-action cvl-account-action" href="<?php echo esc_url( cvl_account_url() ); ?>" aria-label="<?php esc_attr_e( 'A minha conta', 'chavevertical-lite' ); ?>">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"></circle><path d="M4.5 21a7.5 7.5 0 0 1 15 0"></path></svg>
                <span class="cvl-action-label">Conta</span>
            </a>

            <?php
            $cvl_hide_wishlist = ( function_exists( 'is_cart' ) && is_cart() )
                || ( function_exists( 'is_checkout' ) && is_checkout() );
            ?>
            <?php if ( ! $cvl_hide_wishlist ) : ?>
                <a class="cvl-header-action cvl-wishlist-link" href="<?php echo esc_url( cvl_wishlist_url() ); ?>" aria-label="<?php esc_attr_e( 'Favoritos', 'chavevertical-lite' ); ?>">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.8 4.8a5.5 5.5 0 0 0-7.8 0L12 5.8l-1-1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.4a5.5 5.5 0 0 0 0-7.8Z"></path></svg>
                    <span class="cvl-action-label">Favoritos</span>
                </a>
            <?php endif; ?>

            <a class="cvl-header-action cvl-cart-link" href="<?php echo esc_url( cvl_cart_url() ); ?>" aria-label="<?php esc_attr_e( 'Carrinho', 'chavevertical-lite' ); ?>">
                <span class="cvl-cart-icon-wrap"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h2l2.1 10.2a2 2 0 0 0 2 1.6h7.8a2 2 0 0 0 2-1.6L20 8H7"></path><circle cx="10" cy="20" r="1.2"></circle><circle cx="18" cy="20" r="1.2"></circle></svg><span class="cvl-cart-count"><?php echo esc_html( cvl_cart_count() ); ?></span></span>
                <span class="cvl-action-label">Carrinho</span>
            </a>

            <button class="cvl-menu-toggle" type="button" aria-expanded="false" aria-controls="cvl-main-nav" aria-label="<?php esc_attr_e( 'Abrir menu', 'chavevertical-lite' ); ?>">☰</button>
        </div>
    </div>

    <nav class="cvl-nav" id="cvl-main-nav" aria-label="<?php esc_attr_e( 'Navegação principal', 'chavevertical-lite' ); ?>">
        <div class="cvl-shell cvl-nav-inner">
            <?php $cvl_category_tree = cvl_get_product_category_tree(); ?>

            <?php if ( ! empty( $cvl_category_tree[0] ) ) : ?>
                <button
                    class="cvl-categories-link cvl-categories-trigger"
                    type="button"
                    aria-expanded="false"
                    aria-controls="cvl-category-drawer"
                >
                    <span class="cvl-nav-menu-icon" aria-hidden="true"><i></i><i></i><i></i></span>
                    <span>CATEGORIAS</span>
                </button>
            <?php else : ?>
                <a class="cvl-categories-link" href="<?php echo esc_url( cvl_shop_url() ); ?>">☰ <span>CATEGORIAS</span></a>
            <?php endif; ?>

            <?php
            $cvl_reference_menu = array(
                array(
                    'label' => 'CATÁLOGOS',
                    'url'   => home_url( '/catalogos/' ),
                ),
                array(
                    'label' => 'MARCAS',
                    'url'   => cvl_brands_page_url(),
                ),
                array(
                    'label' => 'QUEM SOMOS',
                    'url'   => cvl_about_page_url(),
                ),
                array(
                    'label' => 'CONTACTOS',
                    'url'   => cvl_contact_page_url(),
                ),
                array(
                    'label' => 'FOLHETOS',
                    'url'   => 'https://chavevertical.com/folhetos/',
                    'badge' => 'CAMPANHAS',
                    'badge_class' => 'is-campaign',
                ),
                array(
                    'label' => 'PROMOÇÕES',
                    'url'   => function_exists( 'cvl_promotions_url' )
                        ? cvl_promotions_url()
                        : add_query_arg( 'on_sale', '1', home_url( '/shop/' ) ),
                    'badge' => 'OPORTUNIDADES',
                    'badge_class' => 'is-opportunity',
                ),
                array(
                    'label' => 'PREÇO!',
                    'url'   => function_exists( 'cvl_best_price_url' )
                        ? cvl_best_price_url()
                        : add_query_arg( 'melhor_preco', '1', home_url( '/shop/' ) ),
                    'badge' => 'MELHOR',
                    'badge_class' => 'is-best',
                ),
                array(
                    'label' => 'WHATSAPP',
                    'url'   => 'https://api.whatsapp.com/send?phone=351914580410',
                    'contact' => true,
                ),
            );
            ?>

            <ul class="cvl-menu cvl-menu-reference" aria-label="<?php esc_attr_e( 'Menu principal', 'chavevertical-lite' ); ?>">
                <?php foreach ( $cvl_reference_menu as $cvl_menu_item ) : ?>
                    <?php if ( ! empty( $cvl_menu_item['contact'] ) ) : ?>
                        <li class="is-contact-item">
                            <details class="cvl-contact-menu">
                                <summary class="cvl-contact-menu-trigger">
                                    <a class="cvl-contact-menu-direct" href="<?php echo esc_url( $cvl_menu_item['url'] ); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e( 'Abrir WhatsApp', 'chavevertical-lite' ); ?>">
                                        <span class="cvl-contact-menu-icon" aria-hidden="true">
                                            <svg viewBox="0 0 24 24"><path d="M12.04 2a9.91 9.91 0 0 0-8.59 14.86L2.05 22l5.25-1.38A9.9 9.9 0 1 0 12.04 2Zm5.79 14.1c-.24.68-1.4 1.25-1.92 1.32-.5.07-1.14.1-3.32-.8-2.79-1.15-4.58-4.01-4.72-4.2-.14-.19-1.13-1.5-1.13-2.86 0-1.36.71-2.03.96-2.31.25-.28.56-.35.75-.35.19 0 .37 0 .53.01.17.01.4.06.61.57.24.57.81 1.98.88 2.12.07.14.12.31.02.5-.09.19-.14.31-.28.47-.14.17-.3.37-.43.5-.14.14-.29.3-.12.59.16.28.73 1.2 1.56 1.94 1.07.95 1.97 1.25 2.25 1.39.28.14.45.12.61-.07.17-.19.72-.84.91-1.13.19-.28.38-.24.64-.14.26.09 1.66.78 1.95.92.28.14.47.21.54.33.07.12.07.7-.17 1.38Z"></path></svg>
                                        </span>
                                        <span><?php echo esc_html( $cvl_menu_item['label'] ); ?></span>
                                    </a>
                                    <span class="cvl-contact-menu-chevron" aria-hidden="true">⌄</span>
                                </summary>

                                <div class="cvl-contact-dropdown">
                                    <a href="https://chavevertical.com/contacto-pedido-de-cotacao/">
                                        <span class="cvl-contact-dropdown-icon" aria-hidden="true">▧</span>
                                        <span>FORMULÁRIO</span>
                                    </a>

                                    <a href="https://api.whatsapp.com/send?phone=351914580410" target="_blank" rel="noopener noreferrer">
                                        <span class="cvl-contact-dropdown-icon" aria-hidden="true">◉</span>
                                        <span>WHATSAPP</span>
                                    </a>

                                    <a href="mailto:ricardo@chavevertical.pt">
                                        <span class="cvl-contact-dropdown-icon" aria-hidden="true">✉</span>
                                        <span>EMAIL (LOJA ONLINE)</span>
                                    </a>

                                    <a href="mailto:geral@chavevertical.pt">
                                        <span class="cvl-contact-dropdown-icon" aria-hidden="true">✉</span>
                                        <span>EMAIL (DEPARTAMENTO COMERCIAL)</span>
                                    </a>
                                </div>
                            </details>
                        </li>
                    <?php else : ?>
                        <li class="<?php echo ! empty( $cvl_menu_item['badge'] ) ? 'has-menu-badge' : ''; ?>">
                            <?php if ( ! empty( $cvl_menu_item['badge'] ) ) : ?>
                                <span class="cvl-menu-badge <?php echo esc_attr( $cvl_menu_item['badge_class'] ?? '' ); ?>">
                                    <?php echo esc_html( $cvl_menu_item['badge'] ); ?>
                                </span>
                            <?php endif; ?>

                            <a href="<?php echo esc_url( $cvl_menu_item['url'] ); ?>">
                                <span><?php echo esc_html( $cvl_menu_item['label'] ); ?></span>
                            </a>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        </div>
    </nav>

    <?php if ( ! empty( $cvl_category_tree[0] ) ) : ?>
        <div class="cvl-category-overlay" hidden></div>

        <aside
            id="cvl-category-drawer"
            class="cvl-category-drawer"
            aria-hidden="true"
            aria-label="<?php esc_attr_e( 'Categorias de produtos', 'chavevertical-lite' ); ?>"
        >
            <div class="cvl-category-drawer-header">
                <strong><?php esc_html_e( 'CATEGORIAS', 'chavevertical-lite' ); ?></strong>
                <button class="cvl-category-drawer-close" type="button" aria-label="<?php esc_attr_e( 'Fechar categorias', 'chavevertical-lite' ); ?>">×</button>
            </div>

            <div class="cvl-category-drawer-body">
                <?php cvl_render_category_drawer_items( $cvl_category_tree ); ?>
            </div>

            <div class="cvl-category-drawer-footer">
                <a href="<?php echo esc_url( cvl_shop_url() ); ?>"><?php esc_html_e( 'VER TODOS OS PRODUTOS', 'chavevertical-lite' ); ?> <span aria-hidden="true">→</span></a>
            </div>
        </aside>
    <?php endif; ?>
</header>

<main id="content" class="cvl-main">
