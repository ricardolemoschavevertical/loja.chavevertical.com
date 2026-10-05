<?php
/**
 * About page for the Chave Vertical WooCommerce store.
 *
 * Template Name: Quem Somos
 */
defined( 'ABSPATH' ) || exit;

get_header();

$contact_url = function_exists( 'cvl_contact_page_url' )
    ? cvl_contact_page_url()
    : home_url( '/contactos/' );
?>
<section class="cvl-about-catalog-hero">
    <div class="cvl-shell">
        <div class="cvl-about-breadcrumbs">
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Início', 'chavevertical-lite' ); ?></a>
            <span aria-hidden="true">/</span>
            <span><?php esc_html_e( 'Quem somos', 'chavevertical-lite' ); ?></span>
        </div>
        <span class="cvl-about-catalog-kicker"><?php esc_html_e( 'A Chave Vertical', 'chavevertical-lite' ); ?></span>
        <h1><?php esc_html_e( 'Quem somos', 'chavevertical-lite' ); ?></h1>
        <p><?php esc_html_e( 'Conheça a história da Chave Vertical desde 2010 e o nosso compromisso com máquinas, ferramentas e equipamento profissional.', 'chavevertical-lite' ); ?></p>
    </div>
</section>

<section class="cvl-about-page cvl-shell">
    <div class="cvl-about-hero">
        <div class="cvl-about-hero-copy">
            <span class="cvl-about-kicker"><?php esc_html_e( 'A nossa história', 'chavevertical-lite' ); ?></span>
            <h2><?php esc_html_e( 'A Chave Vertical', 'chavevertical-lite' ); ?></h2>
            <p class="cvl-about-lead"><?php esc_html_e( 'A Chave Vertical, Lda. nasceu a 21 de julho de 2010, na bonita cidade de Aveiro, pelas mãos do seu fundador, Rui Pedro Lemos.', 'chavevertical-lite' ); ?></p>
            <p><?php esc_html_e( 'Desde o início, o nosso objetivo foi claro: oferecer uma vasta gama de produtos para os mais variados setores de atividade, a preços competitivos e, acima de tudo, proporcionar um serviço de entrega rápido e eficiente, com entregas em 24 horas em Portugal Continental sempre que possível.', 'chavevertical-lite' ); ?></p>
            <p><?php esc_html_e( 'Para tornar isso possível, estabelecemos parcerias com várias empresas e selecionámos os melhores produtos de cada marca, assegurando que comercializamos apenas artigos de qualidade, com assistência técnica garantida.', 'chavevertical-lite' ); ?></p>
            <p><?php esc_html_e( 'Estas parcerias permitem-nos também ter maior variedade e, assim, disponibilizar o produto certo para responder às necessidades dos nossos clientes no mínimo tempo possível.', 'chavevertical-lite' ); ?></p>
            <a class="cvl-about-button" href="<?php echo esc_url( $contact_url ); ?>">
                <span><?php esc_html_e( 'Falar connosco', 'chavevertical-lite' ); ?></span>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"></path></svg>
            </a>
        </div>

        <div class="cvl-about-brand-panel">
            <img
                src="https://imagens.chavevertical.com/2026/01/logogrande-scaled.webp"
                alt="<?php esc_attr_e( 'Chave Vertical', 'chavevertical-lite' ); ?>"
                width="520"
                height="200"
                loading="lazy"
                decoding="async"
            >
            <div class="cvl-about-since">
                <strong>2010</strong>
                <span><?php esc_html_e( 'Desde Aveiro para profissionais de todo o país.', 'chavevertical-lite' ); ?></span>
            </div>
        </div>
    </div>

    <div class="cvl-about-values-grid" aria-label="<?php esc_attr_e( 'Compromissos Chave Vertical', 'chavevertical-lite' ); ?>">
        <article>
            <span>01</span>
            <h3><?php esc_html_e( 'Qualidade', 'chavevertical-lite' ); ?></h3>
            <p><?php esc_html_e( 'Produtos selecionados, de marcas reconhecidas e com assistência técnica garantida.', 'chavevertical-lite' ); ?></p>
        </article>
        <article>
            <span>02</span>
            <h3><?php esc_html_e( 'Variedade', 'chavevertical-lite' ); ?></h3>
            <p><?php esc_html_e( 'Uma oferta ampla para responder rapidamente às necessidades de diferentes setores profissionais.', 'chavevertical-lite' ); ?></p>
        </article>
        <article>
            <span>03</span>
            <h3><?php esc_html_e( 'Proximidade', 'chavevertical-lite' ); ?></h3>
            <p><?php esc_html_e( 'Acompanhamento comercial e técnico antes, durante e depois da compra.', 'chavevertical-lite' ); ?></p>
        </article>
    </div>

    <div class="cvl-about-timeline">
        <div class="cvl-about-timeline-year">2017</div>
        <div class="cvl-about-timeline-copy">
            <span class="cvl-about-kicker"><?php esc_html_e( 'Um novo espaço para crescer', 'chavevertical-lite' ); ?></span>
            <h3><?php esc_html_e( 'Mais capacidade, mais exposição e mais variedade.', 'chavevertical-lite' ); ?></h3>
            <p><?php esc_html_e( 'Em 2017, devido à falta de espaço, deixámos as pequenas instalações onde tudo começou e mudámo-nos para um armazém na Zona Industrial de Cacia. Além de muito mais capacidade de armazenagem, passámos a ter uma grande exposição dos produtos que comercializamos.', 'chavevertical-lite' ); ?></p>
            <p><?php esc_html_e( 'Com este novo espaço, temos mais quantidade, mais qualidade e, acima de tudo, mais variedade — para o servir melhor.', 'chavevertical-lite' ); ?></p>
        </div>
    </div>
</section>
<?php
get_footer();
