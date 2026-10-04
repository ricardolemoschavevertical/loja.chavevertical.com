<?php
/**
 * Contact page for the Chave Vertical WooCommerce store.
 *
 * Template Name: Contactos
 */
defined( 'ABSPATH' ) || exit;

get_header();

$locations = array(
    array(
        'name'    => 'Loja / Armazém Aveiro',
        'address' => "Trading Park Cacia\nRua da Paz nº 123\nArmazém A & B · Quinta do Loureiro\n3800-587 Cacia - Aveiro",
        'maps'    => 'Trading Park Cacia Rua da Paz 123 Armazém B Quinta do Loureiro 3800-587 Cacia Aveiro',
    ),
    array(
        'name'    => 'Loja Condeixa - Coimbra',
        'address' => "R. Dona Maria Elsa Franco Sotto Mayor\nEdifício Conímbriga, Loja 21\n3150-133 Condeixa",
        'maps'    => 'R. Dona Maria Elsa Franco Sotto Mayor Edifício Conímbriga Loja 21 3150-133 Condeixa',
    ),
);
?>
<div class="cvl-shell cvl-contact-page">
    <section class="cvl-contact-hero">
        <div>
            <span class="cvl-contact-kicker"><span aria-hidden="true">💬</span> <?php esc_html_e( 'Apoio Chave Vertical', 'chavevertical-lite' ); ?></span>
            <h1><?php esc_html_e( 'Contactos', 'chavevertical-lite' ); ?></h1>
            <p><?php esc_html_e( 'Fale diretamente com a equipa certa para encomendas online, apoio comercial, pós-venda ou assuntos administrativos.', 'chavevertical-lite' ); ?></p>
        </div>

        <div class="cvl-contact-hero-actions">
            <a class="cvl-contact-action is-primary" href="tel:+351234020500"><span aria-hidden="true">📞</span><?php esc_html_e( 'Ligar 234 020 500', 'chavevertical-lite' ); ?></a>
            <a class="cvl-contact-action is-whatsapp" href="https://wa.me/351914580410" target="_blank" rel="noopener noreferrer"><span aria-hidden="true">💬</span><?php esc_html_e( 'WhatsApp', 'chavevertical-lite' ); ?></a>
            <a class="cvl-contact-action is-email" href="mailto:geral@chavevertical.pt"><span aria-hidden="true">✉️</span><?php esc_html_e( 'Email geral', 'chavevertical-lite' ); ?></a>
        </div>
    </section>

    <section class="cvl-contact-section">
        <header class="cvl-contact-section-head">
            <span><?php esc_html_e( 'Contacte a equipa certa', 'chavevertical-lite' ); ?></span>
            <h2><?php esc_html_e( 'Departamentos', 'chavevertical-lite' ); ?></h2>
        </header>

        <div class="cvl-contact-grid">
            <article class="cvl-contact-card is-online is-ecommerce">
                <div class="cvl-contact-card-icon" aria-hidden="true"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h2l2.1 10.2a2 2 0 0 0 2 1.6h7.8a2 2 0 0 0 2-1.6L20 8H7"></path><circle cx="10" cy="20" r="1.2"></circle><circle cx="18" cy="20" r="1.2"></circle></svg></div>
                <h3><span class="cvl-contact-card-emoji" aria-hidden="true">🛒</span><?php esc_html_e( 'Encomendas On-line', 'chavevertical-lite' ); ?></h3>
                <p class="cvl-contact-card-name">Ricardo Lemos</p>
                <ul class="cvl-contact-card-list">
                    <li><span aria-hidden="true">📱</span><a href="tel:+351914580410">914 580 410</a></li>
                    <li><span aria-hidden="true">✉️</span><a href="mailto:ricardo@chavevertical.pt">ricardo@chavevertical.pt</a></li>
                    <li><span aria-hidden="true">✉️</span><a href="mailto:ricardo@chavevertical.com">ricardo@chavevertical.com</a></li>
                </ul>
                <div class="cvl-contact-card-actions">
                    <a class="is-whatsapp" href="https://wa.me/351914580410" target="_blank" rel="noopener noreferrer"><span aria-hidden="true">💬</span><?php esc_html_e( 'WhatsApp', 'chavevertical-lite' ); ?></a>
                    <a class="is-email" href="mailto:ricardo@chavevertical.pt"><span aria-hidden="true">✉️</span><?php esc_html_e( 'Email', 'chavevertical-lite' ); ?></a>
                </div>
            </article>

            <article class="cvl-contact-card is-commercial">
                <div class="cvl-contact-card-icon" aria-hidden="true"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="7" width="18" height="13" rx="2"></rect><path d="M9 7V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2M3 12h18M10 12v2h4v-2"></path></svg></div>
                <h3><span class="cvl-contact-card-emoji" aria-hidden="true">🤝</span><?php esc_html_e( 'Departamento Comercial', 'chavevertical-lite' ); ?></h3>
                <p class="cvl-contact-card-name">Ricardo · José · Joana · Samuel · Andreia · Luis</p>
                <ul class="cvl-contact-card-list">
                    <li><span aria-hidden="true">☎️</span><a href="tel:+351234020500">234 020 500</a></li>
                    <li><span aria-hidden="true">📱</span><a href="tel:+351914938100">914 938 100</a></li>
                    <li><span aria-hidden="true">✉️</span><a href="mailto:geral@chavevertical.pt">geral@chavevertical.pt</a></li>
                    <li><span aria-hidden="true">✉️</span><a href="mailto:info@chavevertical.com">info@chavevertical.com</a></li>
                </ul>
            </article>

            <article class="cvl-contact-card is-service">
                <div class="cvl-contact-card-icon" aria-hidden="true"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14.6 6.4a4.5 4.5 0 0 0-5.7 5.7L3.5 17.5a2.1 2.1 0 0 0 3 3l5.4-5.4a4.5 4.5 0 0 0 5.7-5.7l-2.7 2.7-3-3 2.7-2.7Z"></path></svg></div>
                <h3><span class="cvl-contact-card-emoji" aria-hidden="true">🛠️</span><?php esc_html_e( 'Serviço Pós-Venda', 'chavevertical-lite' ); ?></h3>
                <p class="cvl-contact-card-name">Samuel</p>
                <ul class="cvl-contact-card-list">
                    <li><span aria-hidden="true">📱</span><a href="tel:+351928065970">928 065 970</a></li>
                    <li><span aria-hidden="true">☎️</span><a href="tel:+351234020500">234 020 500</a></li>
                    <li><span aria-hidden="true">✉️</span><a href="mailto:spv@chavevertical.pt">spv@chavevertical.pt</a></li>
                </ul>
            </article>

            <article class="cvl-contact-card is-accounting">
                <div class="cvl-contact-card-icon" aria-hidden="true"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3h8l4 4v14H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"></path><path d="M15 3v5h5M9 12h6M9 16h6"></path></svg></div>
                <h3><span class="cvl-contact-card-emoji" aria-hidden="true">🧾</span><?php esc_html_e( 'Contabilidade', 'chavevertical-lite' ); ?></h3>
                <p class="cvl-contact-card-name">João Colaço</p>
                <ul class="cvl-contact-card-list">
                    <li><span aria-hidden="true">☎️</span><a href="tel:+351300529937">300 529 937</a></li>
                    <li><span aria-hidden="true">☎️</span><a href="tel:+351234020500">234 020 500</a></li>
                    <li><span aria-hidden="true">✉️</span><a href="mailto:contabilidade@chavevertical.pt">contabilidade@chavevertical.pt</a></li>
                </ul>
            </article>

            <article class="cvl-contact-card is-admin">
                <div class="cvl-contact-card-icon" aria-hidden="true"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 21h16M6 21V8l6-4 6 4v13M9 11h2M13 11h2M9 15h2M13 15h2"></path></svg></div>
                <h3><span class="cvl-contact-card-emoji" aria-hidden="true">🏢</span><?php esc_html_e( 'Administração', 'chavevertical-lite' ); ?></h3>
                <p class="cvl-contact-card-name">Rui Lemos</p>
                <ul class="cvl-contact-card-list">
                    <li><span aria-hidden="true">📱</span><a href="tel:+351915216090">915 216 090</a></li>
                    <li><span aria-hidden="true">✉️</span><a href="mailto:ruilemos@chavevertical.pt">ruilemos@chavevertical.pt</a></li>
                </ul>
            </article>
        </div>
    </section>

    <section class="cvl-contact-section">
        <header class="cvl-contact-section-head">
            <span><span aria-hidden="true">📍</span> <?php esc_html_e( 'Visite-nos', 'chavevertical-lite' ); ?></span>
            <h2><?php esc_html_e( 'Lojas e armazéns', 'chavevertical-lite' ); ?></h2>
        </header>

        <div class="cvl-contact-locations">
            <?php foreach ( $locations as $location ) : ?>
                <?php
                $maps_url      = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $location['maps'] );
                $map_embed_url = 'https://www.google.com/maps?q=' . rawurlencode( $location['maps'] ) . '&output=embed';
                ?>
                <article class="cvl-contact-location">
                    <h3><span aria-hidden="true">📍</span><?php echo esc_html( $location['name'] ); ?></h3>
                    <p><?php echo nl2br( esc_html( $location['address'] ) ); ?></p>
                    <span class="cvl-contact-hours"><span aria-hidden="true">🕘</span><?php esc_html_e( 'Segunda a sexta · 09:00–18:00', 'chavevertical-lite' ); ?></span>
                    <div>
                        <a class="cvl-contact-action is-map" href="<?php echo esc_url( $maps_url ); ?>" target="_blank" rel="noopener noreferrer"><span aria-hidden="true">🧭</span><?php esc_html_e( 'Como chegar', 'chavevertical-lite' ); ?></a>
                    </div>

                    <div class="cvl-contact-location-map">
                        <iframe
                            src="<?php echo esc_url( $map_embed_url ); ?>"
                            title="<?php echo esc_attr( sprintf( __( 'Mapa — %s', 'chavevertical-lite' ), $location['name'] ) ); ?>"
                            loading="lazy"
                            allowfullscreen
                            referrerpolicy="no-referrer-when-downgrade"
                        ></iframe>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="cvl-contact-note">
        <div>
            <h2><span aria-hidden="true">🙋</span> <?php esc_html_e( 'Precisa de ajuda com um produto ou encomenda?', 'chavevertical-lite' ); ?></h2>
            <p><?php esc_html_e( 'A Loja Online pode ajudar com disponibilidade, prazo de entrega, compatibilidade e acompanhamento da encomenda.', 'chavevertical-lite' ); ?></p>
        </div>
        <a class="cvl-contact-action is-whatsapp" href="https://wa.me/351914580410" target="_blank" rel="noopener noreferrer"><span aria-hidden="true">💬</span><?php esc_html_e( 'Falar no WhatsApp', 'chavevertical-lite' ); ?></a>
    </section>

    <p class="cvl-contact-legal"><small>* Chamada para a rede móvel nacional. As chamadas para 234 020 500 são chamadas para a rede fixa nacional.</small></p>
</div>
<?php
get_footer();
