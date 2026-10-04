<?php
defined( 'ABSPATH' ) || exit;

$cvl_footer_assets = trailingslashit( get_template_directory_uri() ) . 'assets/images/footer/';
?>
</main>

<footer class="cvl-footer cvl-footer-reference cvl-footer-v2" id="contactos">
    <div class="cvl-shell cvl-footer-v2-grid">
        <section class="cvl-footer-v2-brand" aria-label="<?php esc_attr_e( 'Chave Vertical', 'chavevertical-lite' ); ?>">
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="cvl-footer-v2-logo">
                <img src="<?php echo esc_url( $cvl_footer_assets . 'logo-footer.png' ); ?>" alt="Chave Vertical" loading="lazy">
            </a>

            <p class="cvl-footer-v2-intro">Máquinas, ferramentas e equipamentos profissionais para oficina, indústria, construção e logística.</p>

            <div class="cvl-footer-v2-online">
                <span>LOJA ONLINE</span>
                <a href="mailto:info@chavevertical.com">info@chavevertical.com</a>
                <a href="tel:+351914580410">+351 914 580 410*</a>
            </div>

            <div class="cvl-footer-v2-actions">
                <a class="cvl-footer-v2-action is-primary" href="https://wa.me/351914580410" target="_blank" rel="noopener">
                    WhatsApp
                </a>
                <a class="cvl-footer-v2-action" href="<?php echo esc_url( cvl_contact_page_url() ); ?>">
                    Contactar
                </a>
            </div>
        </section>

        <section class="cvl-footer-v2-section">
            <h2>APOIO AO CLIENTE</h2>

            <div class="cvl-footer-v2-contact">
                <strong>Departamento Comercial</strong>
                <a href="mailto:geral@chavevertical.pt">geral@chavevertical.pt</a>
                <span><a href="tel:+351234020500">234 020 500</a> <small>·</small> <a href="tel:+351914938100">914 938 100*</a></span>
            </div>

            <div class="cvl-footer-v2-contact">
                <strong>Serviço Pós-Venda</strong>
                <a href="mailto:spv@chavevertical.pt">spv@chavevertical.pt</a>
                <span><a href="tel:+351234020500">234 020 500</a> <small>·</small> <a href="tel:+351928065970">928 065 970*</a></span>
            </div>

            <div class="cvl-footer-v2-contact">
                <strong>Contabilidade</strong>
                <a href="mailto:contabilidade@chavevertical.pt">contabilidade@chavevertical.pt</a>
                <a href="tel:+351234020500">234 020 500</a>
            </div>
        </section>

        <section class="cvl-footer-v2-section">
            <h2>LOJAS</h2>

            <div class="cvl-footer-v2-location">
                <strong>Aveiro — Cacia</strong>
                <p>Trading Park Cacia<br>Rua da Paz nº 123, Armazém B<br>Quinta do Loureiro<br>3800-587 Cacia - Aveiro</p>
                <span>Seg–Sex · 09:00–18:00</span>
            </div>

            <div class="cvl-footer-v2-location">
                <strong>Condeixa — Coimbra</strong>
                <p>R. Dona Maria Elsa Franco Sotto Mayor<br>Edifício Conímbriga, Loja 21<br>3150-133 Condeixa</p>
                <span>Seg–Sex · 09:00–18:00</span>
            </div>
        </section>

        <section class="cvl-footer-v2-section cvl-footer-v2-info">
            <h2>INFORMAÇÃO</h2>
            <nav aria-label="<?php esc_attr_e( 'Informação institucional', 'chavevertical-lite' ); ?>">
                <ul>
                    <li><a href="<?php echo esc_url( cvl_contact_page_url() ); ?>">Contactos</a></li>
                    <li><a href="<?php echo esc_url( home_url( '/quem-somos/' ) ); ?>">Sobre Nós</a></li>
                    <li><a href="<?php echo esc_url( home_url( '/entregas-ao-domicilio/' ) ); ?>">Entregas ao Domicílio</a></li>
                    <li><a href="<?php echo esc_url( home_url( '/catalogos/' ) ); ?>">Catálogos Técnicos</a></li>
                    <li><a href="<?php echo esc_url( home_url( '/melhor-preco/' ) ); ?>">Melhor Preço</a></li>
                    <li><a href="<?php echo esc_url( home_url( '/termos-e-condicoes/' ) ); ?>">Termos e Condições</a></li>
                    <li><a href="<?php echo esc_url( home_url( '/politica-de-privacidade/' ) ); ?>">Política de Privacidade</a></li>
                    <li><a href="https://www.livroreclamacoes.pt/inicio/inicio" target="_blank" rel="noopener">Livro de Reclamações</a></li>
                    <li><a href="<?php echo esc_url( cvl_account_url() ); ?>">A minha conta</a></li>
                </ul>
            </nav>
        </section>
    </div>

    <div class="cvl-footer-v2-trust">
        <div class="cvl-shell cvl-footer-v2-trust-grid">
            <div class="cvl-footer-v2-badges" aria-label="<?php esc_attr_e( 'Confiança e segurança', 'chavevertical-lite' ); ?>">
                <img src="<?php echo esc_url( $cvl_footer_assets . 'livro-reclamacoes.png' ); ?>" alt="Livro de Reclamações" loading="lazy">
                <img src="<?php echo esc_url( $cvl_footer_assets . 'shopmania-badge.png' ); ?>" alt="ShopMania" loading="lazy">
                <img src="<?php echo esc_url( $cvl_footer_assets . 'google-site-seguro.png' ); ?>" alt="Google Site Seguro" loading="lazy">
            </div>

            <div class="cvl-footer-v2-payments">
                <span>PAGAMENTOS SEGUROS</span>
                <img src="<?php echo esc_url( $cvl_footer_assets . 'pagamentos.png' ); ?>" alt="Métodos de pagamento seguros" loading="lazy">
            </div>

            <div class="cvl-footer-social" aria-label="Redes sociais">
                <a href="https://www.facebook.com/chavevertical" target="_blank" rel="noopener nofollow" aria-label="Facebook"><svg viewBox="0 0 24 24"><path d="M14 8h3V4h-3c-3 0-5 2-5 5v2H6v4h3v7h4v-7h3l1-4h-4V9c0-.7.3-1 1-1z"></path></svg></a>
                <a href="https://www.instagram.com/chavevertical" target="_blank" rel="noopener nofollow" aria-label="Instagram"><svg viewBox="0 0 24 24"><rect x="4" y="4" width="16" height="16" rx="4"></rect><circle cx="12" cy="12" r="3.5"></circle><circle cx="17.4" cy="6.7" r="1"></circle></svg></a>
                <a href="https://www.youtube.com/@chavevertical" target="_blank" rel="noopener nofollow" aria-label="YouTube"><svg viewBox="0 0 24 24"><path d="M21 8.2a3 3 0 0 0-2.1-2.1C17 5.5 12 5.5 12 5.5s-5 0-6.9.6A3 3 0 0 0 3 8.2 31 31 0 0 0 3 12a31 31 0 0 0 .1 3.8 3 3 0 0 0 2.1 2.1c1.9.6 6.9.6 6.9.6s5 0 6.9-.6a3 3 0 0 0 2.1-2.1A31 31 0 0 0 21 12a31 31 0 0 0 0-3.8z"></path><path class="cvl-footer-social-play" d="M10 9l5 3-5 3z"></path></svg></a>
                <a href="https://wa.me/351914580410" target="_blank" rel="noopener nofollow" aria-label="WhatsApp"><svg viewBox="0 0 24 24"><path d="M12.04 2a9.91 9.91 0 0 0-8.59 14.86L2.05 22l5.25-1.38A9.9 9.9 0 1 0 12.04 2Zm5.79 14.1c-.24.68-1.4 1.25-1.92 1.32-.5.07-1.14.1-3.32-.8-2.79-1.15-4.58-4.01-4.72-4.2-.14-.19-1.13-1.5-1.13-2.86 0-1.36.71-2.03.96-2.31.25-.28.56-.35.75-.35.19 0 .37 0 .53.01.17.01.4.06.61.57.24.57.81 1.98.88 2.12.07.14.12.31.02.5-.09.19-.14.31-.28.47-.14.17-.3.37-.43.5-.14.14-.29.3-.12.59.16.28.73 1.2 1.56 1.94 1.07.95 1.97 1.25 2.25 1.39.28.14.45.12.61-.07.17-.19.72-.84.91-1.13.19-.28.38-.24.64-.14.26.09 1.66.78 1.95.92.28.14.47.21.54.33.07.12.07.7-.17 1.38Z"></path></svg></a>
            </div>
        </div>
    </div>

    <div class="cvl-footer-v2-bottom">
        <div class="cvl-shell cvl-footer-v2-bottom-inner">
            <span>© <?php echo esc_html( wp_date( 'Y' ) ); ?> Chave Vertical, Lda. Todos os direitos reservados.</span>
            <span>* Chamada para a rede móvel nacional. 234 020 500: chamada para a rede fixa nacional.</span>
        </div>
    </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
