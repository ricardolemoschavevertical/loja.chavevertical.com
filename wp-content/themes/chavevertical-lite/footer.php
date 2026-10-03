<?php
defined( 'ABSPATH' ) || exit;
?>
</main>

<footer class="cvl-footer cvl-footer-premium">
    <div class="cvl-shell cvl-footer-top">
        <section class="cvl-footer-col">
            <div class="cvl-footer-card">
                <span class="cvl-footer-kicker">DEPARTAMENTO COMERCIAL</span>
                <h3>Fale com a equipa comercial</h3>
                <p><a href="mailto:comercial@chavevertical.pt">comercial@chavevertical.pt</a></p>
                <p><a href="tel:+351234020500">234 020 500</a> · <a href="tel:+351914938100">914 938 100</a></p>
            </div>
            <div class="cvl-footer-card">
                <span class="cvl-footer-kicker">SERVIÇO PÓS-VENDA</span>
                <h3>Apoio depois da compra</h3>
                <p><a href="mailto:spv@chavevertical.pt">spv@chavevertical.pt</a></p>
                <p><a href="tel:+351928065970">928 065 970</a></p>
            </div>
        </section>

        <section class="cvl-footer-col">
            <div class="cvl-footer-card">
                <span class="cvl-footer-kicker">LOJA / ARMAZÉM</span>
                <h3>Aveiro</h3>
                <p>Trading Park Cacia<br>Rua da Paz nº 123, Armazém B<br>3800-587 Cacia - Aveiro</p>
                <small>Seg-Sex · 09:00 - 18:00</small>
            </div>
            <div class="cvl-footer-card">
                <span class="cvl-footer-kicker">LOJA</span>
                <h3>Condeixa - Coimbra</h3>
                <p>R. Dona Maria Elsa Franco Sotto Mayor<br>Edifício Conímbriga, Loja 21<br>3150-133 Condeixa</p>
                <small>Seg-Sex · 09:00 - 18:00</small>
            </div>
        </section>

        <section class="cvl-footer-col">
            <div class="cvl-footer-card cvl-footer-card-highlight">
                <span class="cvl-footer-kicker">LOJA ONLINE</span>
                <h3>Contacto direto</h3>
                <p><a href="mailto:ricardo@chavevertical.pt">ricardo@chavevertical.pt</a></p>
                <p><a href="tel:+351914580410">914 580 410</a></p>
                <div class="cvl-footer-direct-actions">
                    <a href="https://wa.me/351914580410" target="_blank" rel="noopener noreferrer">WHATSAPP</a>
                    <a href="https://t.me/chavevertical" target="_blank" rel="noopener noreferrer">TELEGRAM</a>
                </div>
            </div>
            <div class="cvl-footer-card cvl-footer-links-card">
                <a href="<?php echo esc_url( home_url( '/contactos/' ) ); ?>">Pedido de cotação</a>
                <a href="<?php echo esc_url( cvl_account_url() ); ?>">A minha conta</a>
                <a href="<?php echo esc_url( cvl_cart_url() ); ?>">Carrinho</a>
            </div>
        </section>

        <section class="cvl-footer-col">
            <div class="cvl-footer-card">
                <span class="cvl-footer-kicker">A CHAVE VERTICAL</span>
                <nav class="cvl-footer-nav" aria-label="<?php esc_attr_e( 'Links institucionais', 'chavevertical-lite' ); ?>">
                    <a href="<?php echo esc_url( home_url( '/quem-somos/' ) ); ?>">Quem somos</a>
                    <a href="<?php echo esc_url( home_url( '/contactos/' ) ); ?>">Contactos</a>
                    <a href="<?php echo esc_url( home_url( '/catalogos/' ) ); ?>">Catálogos técnicos</a>
                    <a href="<?php echo esc_url( cvl_shop_url() ); ?>">Produtos</a>
                    <a href="https://www.livroreclamacoes.pt/inicio/inicio" target="_blank" rel="noopener noreferrer">Livro de Reclamações</a>
                </nav>
            </div>
        </section>
    </div>

    <div class="cvl-footer-brandbar">
        <div class="cvl-shell cvl-footer-brandbar-grid">
            <div class="cvl-footer-logo-wrap">
                <img src="https://astro.chavevertical.com/logo-footer.webp?v=20260927-3" alt="Chave Vertical" width="220" height="72" loading="lazy" decoding="async">
            </div>
            <div class="cvl-footer-payments">
                <span>Pagamentos seguros</span>
                <img src="https://astro.chavevertical.com/pagamentos.webp?v=20260927-3" alt="Métodos de pagamento seguros" width="550" height="64" loading="lazy" decoding="async">
            </div>
            <div class="cvl-footer-socials" aria-label="Redes sociais">
                <a href="https://www.facebook.com/chavevertical" target="_blank" rel="noopener nofollow">FB</a>
                <a href="https://www.instagram.com/chavevertical" target="_blank" rel="noopener nofollow">IG</a>
                <a href="https://www.youtube.com/@chavevertical" target="_blank" rel="noopener nofollow">YT</a>
                <a href="https://wa.me/351914580410" target="_blank" rel="noopener nofollow">WA</a>
            </div>
        </div>
    </div>

    <div class="cvl-shell cvl-footer-bottom">
        <span>© <?php echo esc_html( wp_date( 'Y' ) ); ?> Chave Vertical, Lda. Todos os direitos reservados.</span>
        <span>*Chamadas para as redes fixa e móvel nacionais.</span>
    </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
