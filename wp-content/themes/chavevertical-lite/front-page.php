<?php
get_header();
?>
<section class="cvl-hero">
    <div class="cvl-shell cvl-hero-grid">
        <div>
            <span class="cvl-kicker">MÁQUINAS E FERRAMENTAS PROFISSIONAIS</span>
            <h1>Equipamento profissional com uma loja mais rápida e simples.</h1>
            <p>A nova loja CHAVE VERTICAL está a ser construída sobre WooCommerce com um tema próprio, leve e sem page builders.</p>
            <div class="cvl-hero-actions">
                <a class="cvl-button cvl-button-primary" href="<?php echo esc_url( cvl_shop_url() ); ?>">VER PRODUTOS</a>
                <a class="cvl-button" href="<?php echo esc_url( home_url( '/contactos/' ) ); ?>">CONTACTOS</a>
            </div>
        </div>
        <div class="cvl-hero-panel">
            <strong>LOJA EM CONSTRUÇÃO</strong>
            <span>Catálogo será sincronizado numa fase posterior.</span>
        </div>
    </div>
</section>

<section class="cvl-shell cvl-home-section">
    <header class="cvl-section-heading">
        <span>CHAVE VERTICAL</span>
        <h2>Base preparada para WooCommerce</h2>
        <p>Estrutura visual inspirada no Astro, sem trazer o peso de um theme builder.</p>
    </header>

    <div class="cvl-feature-grid">
        <article><strong>RÁPIDA</strong><span>CSS próprio e JavaScript mínimo.</span></article>
        <article><strong>NATIVA</strong><span>WooCommerce sem reconstruir funcionalidades essenciais.</span></article>
        <article><strong>ESCALÁVEL</strong><span>Preparada para receber o catálogo posteriormente.</span></article>
    </div>
</section>
<?php
get_footer();
