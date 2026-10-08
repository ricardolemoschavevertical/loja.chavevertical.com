<?php
/**
 * Barra comercial de contactos: shop, categorias, produtos, marcas, promoções e melhor preço.
 * Mantenha o conteúdo alinhado com src/components/CategoryContactDock.astro.
 */
defined( 'ABSPATH' ) || exit;

function cvl_category_contact_dock_enabled() {
    // As condicionais do WooCommerce são avaliadas apenas depois de existir a query.
    if (
        ( function_exists( 'is_shop' ) && is_shop() )
        || ( function_exists( 'is_product_category' ) && is_product_category() )
        || ( function_exists( 'is_product' ) && is_product() )
        || ( taxonomy_exists( 'product_brand' ) && is_tax( 'product_brand' ) )
    ) {
        return true;
    }

    // Diretório de marcas e eventuais páginas institucionais destas campanhas.
    if (
        is_page( array( 'marcas', 'promocoes', 'promocoes-oportunidades', 'melhor-preco' ) )
    ) {
        return true;
    }

    // Estas duas vistas são geradas em /shop/?on_sale=1 e
    // /shop/?melhor_preco=1, sem páginas WordPress dedicadas.
    return (
        function_exists( 'cvl_is_promotion_catalog_request' )
        && cvl_is_promotion_catalog_request()
    ) || (
        function_exists( 'cvl_is_best_price_catalog_request' )
        && cvl_is_best_price_catalog_request()
    );
}

add_action( 'wp_enqueue_scripts', static function () {
    // Controlar a bolha inferior do Tawk em todas as páginas públicas, mesmo
    // quando o chat é inserido por um plugin independente do tema.
    // Carregar no <head> para registar onLoad antes do embed do Tawk.to.
    wp_enqueue_script(
        'cvl-category-contact-dock',
        get_template_directory_uri() . '/assets/js/category-contact-dock.js',
        array(),
        cvl_asset_version( 'assets/js/category-contact-dock.js' ),
        false
    );

    // Os cinco atalhos laterais só são renderizados nas páginas comerciais.
    if ( ! cvl_category_contact_dock_enabled() ) {
        return;
    }
    wp_enqueue_style(
        'cvl-category-contact-dock',
        get_template_directory_uri() . '/assets/css/category-contact-dock.css',
        array( 'cvl-main' ),
        cvl_asset_version( 'assets/css/category-contact-dock.css' )
    );
}, 5 );

add_filter( 'body_class', static function ( $classes ) {
    if ( cvl_category_contact_dock_enabled() ) {
        $classes[] = 'cv-has-category-contact-dock';
    }
    return $classes;
} );

add_action( 'wp_footer', static function () {
    if ( ! cvl_category_contact_dock_enabled() ) {
        return;
    }
    ?>
<aside class="cv-category-contact-dock" data-cv-category-contact-dock aria-label="Solicitar Cotação ou Orçamento">
  <details class="cv-dock-item cv-dock-whatsapp">
    <summary aria-label="Abrir contacto por WhatsApp"><span class="cv-dock-icon"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path d="M12.04 2a9.91 9.91 0 0 0-8.59 14.86L2.05 22l5.25-1.38A9.9 9.9 0 1 0 12.04 2zm5.79 14.1c-.24.68-1.4 1.25-1.92 1.32-.5.07-1.14.1-3.32-.8-2.79-1.15-4.58-4.01-4.72-4.2-.14-.19-1.13-1.5-1.13-2.86 0-1.36.71-2.03.96-2.31.25-.28.56-.35.75-.35.19 0 .37 0 .53.01.17.01.4.06.61.57.24.57.81 1.98.88 2.12.07.14.12.31.02.5-.09.19-.14.31-.28.47-.14.17-.3.37-.43.5-.14.14-.29.3-.12.59.16.28.73 1.2 1.56 1.94 1.07.95 1.97 1.25 2.25 1.39.28.14.45.12.61-.07.17-.19.72-.84.91-1.13.19-.28.38-.24.64-.14.26.09 1.66.78 1.95.92.28.14.47.21.54.33.07.12.07.7-.17 1.38z"/></svg></span></summary>
    <div class="cv-dock-panel">
      <span class="cv-dock-help">Como podemos ajudar? <b>Online</b></span>
      <a data-cv-dock-primary href="https://wa.me/351914580410?text=Ol%C3%A1%2C%20pretendo%20solicitar%20uma%20cota%C3%A7%C3%A3o%20%2F%20or%C3%A7amento." target="_blank" rel="noopener noreferrer">Fale Connosco pelo WhatsApp</a>
    </div>
  </details>
  <details class="cv-dock-item cv-dock-phone">
    <summary aria-label="Mostrar o telemóvel 914 580 410"><span class="cv-dock-icon"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path d="M6.8 3.6 9.1 7.5c.3.5.2 1.1-.2 1.5l-1.5 1.2a13.8 13.8 0 0 0 6.4 6.4l1.2-1.5c.4-.4 1-.5 1.5-.2l3.9 2.3c.5.3.8.9.6 1.5l-.6 1.8c-.2.6-.8 1-1.5 1C9.7 21.5 2.5 14.3 2.5 5.1c0-.7.4-1.3 1-1.5l1.8-.6c.6-.2 1.2.1 1.5.6z"/></svg></span></summary>
    <div class="cv-dock-panel">
      <span class="cv-dock-help">Prefere falar connosco? <b>Telemóvel</b></span>
      <span class="cv-dock-phone-links"><a data-cv-dock-primary href="tel:+351914580410" aria-label="Ligar para o telemóvel 914 580 410">914 580 410</a><span aria-hidden="true">|</span><a href="tel:+351234020500" aria-label="Ligar para o telefone fixo 234 020 500">234 020 500</a></span>
    </div>
  </details>
  <details class="cv-dock-item cv-dock-tawk">
    <summary aria-label="Abrir Chat Online Tawk.to"><span class="cv-dock-icon"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M4.5 4.5h15a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2h-8l-5 3v-3h-2a2 2 0 0 1-2-2v-10a2 2 0 0 1 2-2Z"/><path d="M7.5 10h9M7.5 13.5h6"/></svg></span></summary>
    <div class="cv-dock-panel">
      <span class="cv-dock-help">Podemos ajudar em direto? <b>Chat Online</b></span>
      <a data-cv-dock-primary href="https://tawk.to/chat/5fb80845a1d54c18d8ebc361/default" target="_blank" rel="noopener noreferrer" data-cv-tawk-launch>Conversar pelo Tawk.to</a>
    </div>
  </details>
  <details class="cv-dock-item cv-dock-email">
    <summary aria-label="Abrir contacto por email"><span class="cv-dock-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true" focusable="false"><path d="M3 5h18v14H3zM3 6l9 7 9-7"/></svg></span></summary>
    <div class="cv-dock-panel">
      <span class="cv-dock-help">Também pode contactar por email <b>Email</b></span>
      <a data-cv-dock-primary href="mailto:geral@chavevertical.pt?subject=Pedido%20de%20Cota%C3%A7%C3%A3o%20%2F%20Or%C3%A7amento">geral@chavevertical.pt</a>
    </div>
  </details>
  <details class="cv-dock-item cv-dock-form">
    <summary aria-label="Abrir formulário de cotação"><span class="cv-dock-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true" focusable="false"><path d="M5 3h10l4 4v14H5zM14 3v5h5M8 13h8M8 17h6"/></svg></span></summary>
    <div class="cv-dock-panel">
      <span class="cv-dock-help">Prefere enviar os dados? <b>Formulário</b></span>
      <a data-cv-dock-primary href="https://chavevertical.com/contacto-pedido-de-cotacao/">Pedir Cotação por Formulário</a>
    </div>
  </details>
</aside>
<?php
}, 5 );
