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
            <span class="cvl-contact-kicker"><?php esc_html_e( 'Apoio Chave Vertical', 'chavevertical-lite' ); ?></span>
            <h1><?php esc_html_e( 'Contactos', 'chavevertical-lite' ); ?></h1>
            <p><?php esc_html_e( 'Fale diretamente com a equipa certa para encomendas online, apoio comercial, pós-venda ou assuntos administrativos.', 'chavevertical-lite' ); ?></p>
        </div>

        <div class="cvl-contact-hero-actions">
            <a class="cvl-contact-action is-primary" href="tel:+351234020500"><?php esc_html_e( 'Ligar 234 020 500', 'chavevertical-lite' ); ?></a>
            <a class="cvl-contact-action" href="https://wa.me/351914580410" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'WhatsApp', 'chavevertical-lite' ); ?></a>
            <a class="cvl-contact-action" href="mailto:geral@chavevertical.pt"><?php esc_html_e( 'Email geral', 'chavevertical-lite' ); ?></a>
        </div>
    </section>

    <section class="cvl-contact-section">
        <header class="cvl-contact-section-head">
            <span><?php esc_html_e( 'Contacte a equipa certa', 'chavevertical-lite' ); ?></span>
            <h2><?php esc_html_e( 'Departamentos', 'chavevertical-lite' ); ?></h2>
        </header>

        <div class="cvl-contact-grid">
            <article class="cvl-contact-card is-online">
                <div class="cvl-contact-card-icon" aria-hidden="true">↗</div>
                <h3><?php esc_html_e( 'Encomendas On-line', 'chavevertical-lite' ); ?></h3>
                <p class="cvl-contact-card-name">Ricardo Lemos</p>
                <ul class="cvl-contact-card-list">
                    <li><a href="tel:+351914580410">914 580 410</a></li>
                    <li><a href="mailto:ricardo@chavevertical.pt">ricardo@chavevertical.pt</a></li>
                    <li><a href="mailto:ricardo@chavevertical.com">ricardo@chavevertical.com</a></li>
                </ul>
                <div class="cvl-contact-card-actions">
                    <a href="https://wa.me/351914580410" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'WhatsApp', 'chavevertical-lite' ); ?></a>
                    <a href="mailto:ricardo@chavevertical.pt"><?php esc_html_e( 'Email', 'chavevertical-lite' ); ?></a>
                </div>
            </article>

            <article class="cvl-contact-card">
                <div class="cvl-contact-card-icon" aria-hidden="true">C</div>
                <h3><?php esc_html_e( 'Departamento Comercial', 'chavevertical-lite' ); ?></h3>
                <p class="cvl-contact-card-name">Ricardo · José · Joana · Samuel · Andreia · Luis</p>
                <ul class="cvl-contact-card-list">
                    <li><a href="tel:+351234020500">234 020 500</a></li>
                    <li><a href="tel:+351914938100">914 938 100</a></li>
                    <li><a href="mailto:geral@chavevertical.pt">geral@chavevertical.pt</a></li>
                    <li><a href="mailto:info@chavevertical.com">info@chavevertical.com</a></li>
                </ul>
            </article>

            <article class="cvl-contact-card">
                <div class="cvl-contact-card-icon" aria-hidden="true">SPV</div>
                <h3><?php esc_html_e( 'Serviço Pós-Venda', 'chavevertical-lite' ); ?></h3>
                <p class="cvl-contact-card-name">Samuel</p>
                <ul class="cvl-contact-card-list">
                    <li><a href="tel:+351928065970">928 065 970</a></li>
                    <li><a href="tel:+351234020500">234 020 500</a></li>
                    <li><a href="mailto:spv@chavevertical.pt">spv@chavevertical.pt</a></li>
                </ul>
            </article>

            <article class="cvl-contact-card">
                <div class="cvl-contact-card-icon" aria-hidden="true">€</div>
                <h3><?php esc_html_e( 'Contabilidade', 'chavevertical-lite' ); ?></h3>
                <p class="cvl-contact-card-name">João Colaço</p>
                <ul class="cvl-contact-card-list">
                    <li><a href="tel:+351300529937">300 529 937</a></li>
                    <li><a href="tel:+351234020500">234 020 500</a></li>
                    <li><a href="mailto:contabilidade@chavevertical.pt">contabilidade@chavevertical.pt</a></li>
                </ul>
            </article>

            <article class="cvl-contact-card">
                <div class="cvl-contact-card-icon" aria-hidden="true">A</div>
                <h3><?php esc_html_e( 'Administração', 'chavevertical-lite' ); ?></h3>
                <p class="cvl-contact-card-name">Rui Lemos</p>
                <ul class="cvl-contact-card-list">
                    <li><a href="tel:+351915216090">915 216 090</a></li>
                    <li><a href="mailto:ruilemos@chavevertical.pt">ruilemos@chavevertical.pt</a></li>
                </ul>
            </article>
        </div>
    </section>

    <section class="cvl-contact-section">
        <header class="cvl-contact-section-head">
            <span><?php esc_html_e( 'Visite-nos', 'chavevertical-lite' ); ?></span>
            <h2><?php esc_html_e( 'Lojas e armazéns', 'chavevertical-lite' ); ?></h2>
        </header>

        <div class="cvl-contact-locations">
            <?php foreach ( $locations as $location ) : ?>
                <?php $maps_url = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $location['maps'] ); ?>
                <article class="cvl-contact-location">
                    <h3><?php echo esc_html( $location['name'] ); ?></h3>
                    <p><?php echo nl2br( esc_html( $location['address'] ) ); ?></p>
                    <span class="cvl-contact-hours"><?php esc_html_e( 'Segunda a sexta · 09:00–18:00', 'chavevertical-lite' ); ?></span>
                    <div>
                        <a class="cvl-contact-action" href="<?php echo esc_url( $maps_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Como chegar', 'chavevertical-lite' ); ?></a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="cvl-contact-note">
        <div>
            <h2><?php esc_html_e( 'Precisa de ajuda com um produto ou encomenda?', 'chavevertical-lite' ); ?></h2>
            <p><?php esc_html_e( 'A Loja Online pode ajudar com disponibilidade, prazo de entrega, compatibilidade e acompanhamento da encomenda.', 'chavevertical-lite' ); ?></p>
        </div>
        <a class="cvl-contact-action" href="https://wa.me/351914580410" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Falar no WhatsApp', 'chavevertical-lite' ); ?></a>
    </section>

    <p class="cvl-contact-legal"><small>* Chamada para a rede móvel nacional. As chamadas para 234 020 500 são chamadas para a rede fixa nacional.</small></p>
</div>
<?php
get_footer();
