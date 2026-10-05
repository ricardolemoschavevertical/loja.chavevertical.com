<?php
/**
 * Privacy policy page replicated from the Astro institutional page.
 *
 * Template Name: Politica de Privacidade
 */
defined( 'ABSPATH' ) || exit;

get_header();

$contact_url = function_exists( 'cvl_contact_page_url' )
    ? cvl_contact_page_url()
    : home_url( '/contactos/' );

$sections = array(
    array(
        'title' => '1. Introdução',
        'body'  => '<p>A CHAVE VERTICAL respeita a privacidade dos seus clientes e visitantes do website e compromete-se a proteger os seus dados pessoais em conformidade com o Regulamento Geral sobre a Proteção de Dados (RGPD) e demais legislação aplicável.</p><p>A presente Política de Privacidade explica como recolhemos, utilizamos, armazenamos e protegemos os seus dados pessoais quando utiliza o website https://chavevertical.com.</p>',
    ),
    array(
        'title' => '2. Responsável pelo Tratamento dos Dados',
        'body'  => '<p><strong>CHAVE VERTICAL, LDA</strong><br>NIPC: 509514502<br>Email: <a href="mailto:geral@chavevertical.pt">geral@chavevertical.pt</a><br>Telefone: <a href="tel:+351234020500">+351 234 020 500</a><br>Website: https://chavevertical.com</p>',
    ),
    array(
        'title' => '3. Dados Recolhidos',
        'body'  => '<p>Podemos recolher os seguintes dados:</p><ul><li>Nome</li><li>Morada</li><li>Endereço de email</li><li>Número de telefone</li><li>NIF (quando necessário para faturação)</li><li>Dados de encomendas e compras</li><li>Endereço IP</li><li>Dados de navegação e utilização do website</li><li>Informações fornecidas através de formulários de contacto</li></ul>',
    ),
    array(
        'title' => '4. Finalidade da Recolha dos Dados',
        'body'  => '<p>Os dados pessoais são tratados para:</p><ul><li>Processamento e envio de encomendas;</li><li>Faturação e cumprimento de obrigações legais;</li><li>Gestão da relação comercial com os clientes;</li><li>Resposta a pedidos de contacto;</li><li>Prestação de assistência técnica e apoio ao cliente;</li><li>Melhoria da experiência de utilização do website;</li><li>Envio de comunicações comerciais, quando autorizado pelo utilizador.</li></ul>',
    ),
    array(
        'title' => '5. Fundamento Jurídico',
        'body'  => '<p>O tratamento dos dados pessoais baseia-se em:</p><ul><li>Execução de contrato;</li><li>Cumprimento de obrigações legais;</li><li>Interesse legítimo da empresa;</li><li>Consentimento do titular dos dados, quando aplicável.</li></ul>',
    ),
    array(
        'title' => '6. Conservação dos Dados',
        'body'  => '<p>Os dados pessoais serão conservados apenas pelo período necessário para cumprir as finalidades para as quais foram recolhidos e para satisfazer obrigações legais, fiscais e contabilísticas.</p>',
    ),
    array(
        'title' => '7. Partilha de Dados',
        'body'  => '<p>A CHAVE VERTICAL poderá partilhar dados com:</p><ul><li>Transportadoras e operadores logísticos;</li><li>Prestadores de serviços de pagamento;</li><li>Empresas de faturação e contabilidade;</li><li>Fornecedores de alojamento, segurança e manutenção informática;</li><li>Autoridades públicas quando exigido por lei.</li></ul><p>Os dados não serão vendidos ou cedidos a terceiros para fins comerciais.</p>',
    ),
    array(
        'title' => '8. Cookies',
        'body'  => '<p>O website utiliza cookies para melhorar a experiência de navegação, analisar estatísticas e garantir o correto funcionamento do site.</p><p>O utilizador pode configurar ou desativar os cookies através das definições do seu navegador.</p><p>Para mais informações consulte a nossa Política de Cookies.</p>',
    ),
    array(
        'title' => '9. Direitos dos Titulares dos Dados',
        'body'  => '<p>Nos termos da legislação aplicável, o utilizador pode exercer os seguintes direitos:</p><ul><li>Direito de acesso;</li><li>Direito de retificação;</li><li>Direito ao apagamento;</li><li>Direito à limitação do tratamento;</li><li>Direito de oposição;</li><li>Direito à portabilidade dos dados;</li><li>Direito de retirar o consentimento.</li></ul><p>Os pedidos poderão ser efetuados através do email <a href="mailto:geral@chavevertical.pt">geral@chavevertical.pt</a>.</p>',
    ),
    array(
        'title' => '10. Segurança dos Dados',
        'body'  => '<p>A CHAVE VERTICAL adota medidas técnicas e organizativas adequadas para proteger os dados pessoais contra perda, utilização indevida, acesso não autorizado, divulgação ou destruição.</p>',
    ),
    array(
        'title' => '11. Reclamações',
        'body'  => '<p>O utilizador tem o direito de apresentar reclamação à Comissão Nacional de Proteção de Dados (CNPD), através do website https://www.cnpd.pt.</p>',
    ),
    array(
        'title' => '12. Alterações à Política de Privacidade',
        'body'  => '<p>A CHAVE VERTICAL reserva-se o direito de alterar esta Política de Privacidade a qualquer momento. As alterações serão publicadas nesta página.</p>',
    ),
    array(
        'title' => '13. Contacto',
        'body'  => '<p>Para qualquer questão relacionada com a proteção de dados pessoais, contacte:</p><p>Email: <a href="mailto:geral@chavevertical.pt">geral@chavevertical.pt</a><br>Telefone: <a href="tel:+351234020500">+351 234 020 500</a><br>Website: https://chavevertical.com</p>',
    ),
);
?>
<section class="cvl-institutional-catalog-hero">
    <div class="cvl-shell">
        <div class="cvl-institutional-breadcrumbs">
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Início', 'chavevertical-lite' ); ?></a>
            <span aria-hidden="true">/</span>
            <span><?php esc_html_e( 'Política de Privacidade', 'chavevertical-lite' ); ?></span>
        </div>
        <span class="cvl-institutional-catalog-kicker"><?php esc_html_e( 'Privacidade e dados', 'chavevertical-lite' ); ?></span>
        <h1><?php esc_html_e( 'Política de Privacidade', 'chavevertical-lite' ); ?></h1>
        <p><?php esc_html_e( 'Saiba como a Chave Vertical recolhe, utiliza, armazena e protege os seus dados pessoais.', 'chavevertical-lite' ); ?></p>
    </div>
</section>

<section class="cvl-institutional-page cvl-shell">
    <div class="cvl-institutional-intro-card">
        <div>
            <span class="cvl-institutional-kicker"><?php esc_html_e( 'Privacidade e dados', 'chavevertical-lite' ); ?></span>
            <h2><?php esc_html_e( 'Política de Privacidade', 'chavevertical-lite' ); ?></h2>
            <p class="cvl-institutional-lead"><?php esc_html_e( 'A CHAVE VERTICAL respeita a privacidade dos seus clientes e visitantes do website e compromete-se a proteger os seus dados pessoais em conformidade com o RGPD e demais legislação aplicável.', 'chavevertical-lite' ); ?></p>
            <p><?php esc_html_e( 'Consulte abaixo como recolhemos, utilizamos, armazenamos e protegemos os seus dados pessoais.', 'chavevertical-lite' ); ?></p>
        </div>
        <div class="cvl-institutional-meta">
            <a href="<?php echo esc_url( $contact_url ); ?>">
                <span><?php esc_html_e( 'Precisa de ajuda?', 'chavevertical-lite' ); ?></span>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"></path></svg>
            </a>
        </div>
    </div>

    <div class="cvl-institutional-layout">
        <aside class="cvl-institutional-toc" aria-label="<?php esc_attr_e( 'Índice da página', 'chavevertical-lite' ); ?>">
            <strong><?php esc_html_e( 'Nesta página', 'chavevertical-lite' ); ?></strong>
            <nav>
                <?php foreach ( $sections as $index => $section ) : ?>
                    <a href="#secao-<?php echo esc_attr( (string) ( $index + 1 ) ); ?>"><?php echo esc_html( $section['title'] ); ?></a>
                <?php endforeach; ?>
            </nav>
        </aside>

        <div class="cvl-institutional-sections">
            <?php foreach ( $sections as $index => $section ) : ?>
                <article class="cvl-institutional-section" id="secao-<?php echo esc_attr( (string) ( $index + 1 ) ); ?>">
                    <div class="cvl-institutional-section-number"><?php echo esc_html( str_pad( (string) ( $index + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></div>
                    <div>
                        <h3><?php echo esc_html( $section['title'] ); ?></h3>
                        <div class="cvl-institutional-copy"><?php echo wp_kses_post( $section['body'] ); ?></div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="cvl-institutional-contact">
        <div>
            <span class="cvl-institutional-kicker"><?php esc_html_e( 'Ficou com alguma dúvida?', 'chavevertical-lite' ); ?></span>
            <h3><?php esc_html_e( 'Fale diretamente com a nossa equipa.', 'chavevertical-lite' ); ?></h3>
            <p><?php esc_html_e( 'Estamos disponíveis para esclarecer condições comerciais, entregas, encomendas e questões relacionadas com os seus dados.', 'chavevertical-lite' ); ?></p>
        </div>
        <div class="cvl-institutional-contact-actions">
            <a href="tel:+351234020500">234 020 500</a>
            <a href="mailto:geral@chavevertical.pt">geral@chavevertical.pt</a>
        </div>
    </div>
</section>
<?php
get_footer();
