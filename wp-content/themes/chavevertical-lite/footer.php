<?php
defined( 'ABSPATH' ) || exit;
?>
</main>

<footer class="cvl-footer">
    <div class="cvl-shell cvl-footer-grid">
        <section>
            <h2>CHAVE VERTICAL</h2>
            <p>Máquinas, ferramentas e equipamentos para profissionais.</p>
        </section>

        <section>
            <h3>LOJA</h3>
            <a href="<?php echo esc_url( cvl_shop_url() ); ?>">Produtos</a>
            <a href="<?php echo esc_url( cvl_account_url() ); ?>">A minha conta</a>
            <a href="<?php echo esc_url( cvl_cart_url() ); ?>">Carrinho</a>
        </section>

        <section>
            <h3>CONTACTOS</h3>
            <a href="tel:+351234020500">234 020 500</a>
            <a href="mailto:geral@chavevertical.pt">geral@chavevertical.pt</a>
            <a href="https://wa.me/351914580410" target="_blank" rel="noopener noreferrer">WhatsApp</a>
        </section>

        <?php if ( is_active_sidebar( 'footer-widgets' ) ) : ?>
            <section><?php dynamic_sidebar( 'footer-widgets' ); ?></section>
        <?php endif; ?>
    </div>

    <div class="cvl-shell cvl-footer-bottom">
        <span>© <?php echo esc_html( wp_date( 'Y' ) ); ?> CHAVE VERTICAL</span>
        <?php
        wp_nav_menu( array(
            'theme_location' => 'footer',
            'container'      => false,
            'menu_class'     => 'cvl-footer-menu',
            'fallback_cb'    => false,
            'depth'          => 1,
        ) );
        ?>
    </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
