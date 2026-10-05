<?php
/**
 * Delivery information page replicated from the Astro institutional page.
 *
 * Template Name: Entregas ao Domicilio
 */
defined( 'ABSPATH' ) || exit;

get_header();

$contact_url = function_exists( 'cvl_contact_page_url' )
    ? cvl_contact_page_url()
    : home_url( '/contactos/' );

$sections = array(
    array(
        'title' => '1. Zonas de entrega',
        'body'  => '<p>A CHAVE VERTICAL realiza entregas para Portugal Continental, Ilhas e outros destinos mediante disponibilidade logística e confirmação das condições de transporte.</p><p>As entregas são efetuadas para a morada indicada pelo Cliente no momento da encomenda.</p><p>O Cliente é responsável por garantir que os dados de entrega estão completos e corretos, incluindo nome, morada, código postal, localidade, contacto telefónico e demais informações necessárias para a correta expedição da encomenda.</p>',
    ),
    array(
        'title' => '2. Portugal Continental',
        'body'  => '<p>Para Portugal Continental, o prazo estimado de entrega é, normalmente, de 1 a 5 dias úteis após confirmação da encomenda e disponibilidade do produto.</p><p>Este prazo é meramente indicativo e pode variar em função da transportadora, da morada de entrega, do volume da encomenda, da disponibilidade do produto ou de outros fatores externos à CHAVE VERTICAL.</p>',
    ),
    array(
        'title' => '3. Ilhas e outros destinos',
        'body'  => '<p>Para envios para Madeira, Açores e outros destinos, o prazo e o custo de transporte são confirmados caso a caso, de acordo com o peso, volume, tipo de produto e destino da encomenda.</p><p>Sempre que necessário, a equipa da CHAVE VERTICAL contactará o Cliente para confirmar as condições de envio antes da expedição.</p>',
    ),
    array(
        'title' => '4. Produtos volumosos, pesados ou sujeitos a transporte especial',
        'body'  => '<p>Alguns equipamentos, máquinas, ferramentas ou artigos de grandes dimensões podem exigir cotação específica de transporte.</p><p>Esta situação pode aplicar-se, nomeadamente, a produtos:</p><ul><li>volumosos;</li><li>pesados;</li><li>frágeis;</li><li>com requisitos especiais de descarga;</li><li>com necessidade de transporte dedicado;</li><li>com entrega sujeita a validação prévia da transportadora.</li></ul><p>Nestes casos, o prazo e o custo de envio poderão ser confirmados pela CHAVE VERTICAL antes da expedição da encomenda.</p><p>Em produtos volumosos ou pesados, a entrega poderá ser efetuada ao nível da rua, salvo contratação expressa de serviço adicional de descarga, movimentação ou entrega em local específico.</p>',
    ),
    array(
        'title' => '5. Produtos por encomenda ou sem disponibilidade imediata',
        'body'  => '<p>Quando o produto não se encontre disponível para entrega imediata, o prazo estimado será comunicado ao Cliente após confirmação junto do fornecedor ou fabricante.</p><p>Nestas situações, a encomenda poderá:</p><ul><li>aguardar reposição de stock, mediante acordo com o Cliente;</li><li>ser parcialmente enviada, caso existam outros produtos disponíveis;</li><li>ser total ou parcialmente cancelada, com reembolso da quantia correspondente, caso já tenha sido paga.</li></ul>',
    ),
    array(
        'title' => '6. Custos de envio',
        'body'  => '<p>Os custos de envio são apresentados durante o processo de compra, sempre que aplicável.</p><p>O valor dos portes pode variar em função do peso, volume, destino, tipo de produto, transportadora disponível e condições específicas de entrega.</p><p>Em determinados produtos, nomeadamente equipamentos volumosos, pesados, frágeis ou sujeitos a transporte especial, poderá ser necessário confirmar posteriormente o custo de transporte com a equipa da CHAVE VERTICAL.</p>',
    ),
    array(
        'title' => '7. Entrega gratuita',
        'body'  => '<p>Quando aplicável, poderão existir condições promocionais de entrega gratuita para determinadas encomendas, produtos, marcas ou valores mínimos de compra.</p><p>A entrega gratuita poderá não ser aplicável a produtos volumosos, pesados, frágeis, artigos sujeitos a transporte especial, envios para Ilhas ou outros destinos específicos.</p><p>As condições de entrega gratuita, quando existentes, serão apresentadas no website ou comunicadas ao Cliente antes da expedição da encomenda.</p>',
    ),
    array(
        'title' => '8. Levantamento em loja',
        'body'  => '<p>O Cliente poderá solicitar o levantamento da encomenda nas instalações da CHAVE VERTICAL, mediante confirmação prévia de disponibilidade.</p><p>O levantamento apenas deverá ser efetuado após confirmação por parte da equipa da CHAVE VERTICAL.</p>',
    ),
    array(
        'title' => '9. Atrasos na entrega',
        'body'  => '<p>Os prazos de entrega são indicativos e podem sofrer alterações por motivos alheios à CHAVE VERTICAL, incluindo atrasos de transportadora, ruturas de stock, condições meteorológicas, períodos de maior volume logístico, greves, feriados ou outros fatores externos.</p><p>Sempre que se verifique um atraso relevante, a CHAVE VERTICAL procurará informar o Cliente logo que possível.</p>',
    ),
    array(
        'title' => '10. Verificação da encomenda no ato da entrega',
        'body'  => '<p>No ato da entrega, o Cliente deverá verificar o estado exterior da embalagem.</p><p>Caso existam danos visíveis, sinais de violação, embalagem deformada ou qualquer anomalia aparente, o Cliente deverá mencionar a situação na guia de transporte e contactar de imediato a CHAVE VERTICAL.</p><p>A comunicação deverá ser efetuada através dos contactos disponíveis no website, sempre que possível acompanhada de fotografias da embalagem e do produto.</p>',
    ),
    array(
        'title' => '11. Dados incorretos ou impossibilidade de entrega',
        'body'  => '<p>Caso os dados de entrega fornecidos pelo Cliente estejam incorretos, incompletos ou impossibilitem a entrega da encomenda, a CHAVE VERTICAL não poderá ser responsabilizada por atrasos, devoluções à origem ou impossibilidade de entrega.</p><p>Nestas situações, quaisquer custos adicionais decorrentes de nova tentativa de entrega, alteração de morada, armazenagem, devolução à origem ou reexpedição da encomenda poderão ser imputados ao Cliente, sempre que o erro seja da sua responsabilidade.</p><p>Caso a encomenda seja devolvida à CHAVE VERTICAL por motivo imputável ao Cliente, nomeadamente morada incorreta, ausência reiterada no local de entrega, recusa injustificada da encomenda ou falta de levantamento no prazo indicado pela transportadora, o reenvio da encomenda ficará sujeito ao pagamento prévio dos novos custos de transporte.</p>',
    ),
    array(
        'title' => '12. Contactos',
        'body'  => '<p>Para dúvidas sobre prazos de entrega, custos de transporte, disponibilidade de produtos ou condições especiais de envio, o Cliente poderá contactar a CHAVE VERTICAL através dos meios disponíveis na página de contactos.</p><p>E-mail: <a href="mailto:geral@chavevertical.pt">geral@chavevertical.pt</a><br>Telefone: <a href="tel:+351234020500">234 020 500</a></p>',
    ),
);
?>
<section class="cvl-institutional-catalog-hero">
    <div class="cvl-shell">
        <div class="cvl-institutional-breadcrumbs">
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Início', 'chavevertical-lite' ); ?></a>
            <span aria-hidden="true">/</span>
            <span><?php esc_html_e( 'Envios e Entregas', 'chavevertical-lite' ); ?></span>
        </div>
        <span class="cvl-institutional-catalog-kicker"><?php esc_html_e( 'Logística profissional', 'chavevertical-lite' ); ?></span>
        <h1><?php esc_html_e( 'Envios e Entregas', 'chavevertical-lite' ); ?></h1>
        <p><?php esc_html_e( 'Condições de envio e entrega das encomendas da Chave Vertical.', 'chavevertical-lite' ); ?></p>
    </div>
</section>

<section class="cvl-institutional-page cvl-shell">
    <div class="cvl-institutional-intro-card">
        <div>
            <span class="cvl-institutional-kicker"><?php esc_html_e( 'Logística profissional', 'chavevertical-lite' ); ?></span>
            <h2><?php esc_html_e( 'Envios e Entregas', 'chavevertical-lite' ); ?></h2>
            <p class="cvl-institutional-lead"><?php esc_html_e( 'A presente página apresenta as condições gerais aplicáveis aos envios e entregas das encomendas efetuadas através da loja online chavevertical.com.', 'chavevertical-lite' ); ?></p>
            <p><?php esc_html_e( 'A CHAVE VERTICAL procura assegurar que as encomendas são expedidas com a maior brevidade possível, tendo em conta a disponibilidade dos produtos, a morada de entrega, o tipo de artigo, o peso, o volume e a transportadora disponível.', 'chavevertical-lite' ); ?></p>
        </div>
        <div class="cvl-institutional-meta">
            <span><?php esc_html_e( 'Última atualização: 20 de junho de 2026', 'chavevertical-lite' ); ?></span>
            <a href="<?php echo esc_url( $contact_url ); ?>">
                <span><?php esc_html_e( 'Precisa de ajuda?', 'chavevertical-lite' ); ?></span>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"></path></svg>
            </a>
        </div>
    </div>

    <div class="cvl-delivery-highlights">
        <article><b>01</b><h3><?php esc_html_e( 'Preparação', 'chavevertical-lite' ); ?></h3><p><?php esc_html_e( 'Confirmamos a disponibilidade e preparamos cuidadosamente a encomenda.', 'chavevertical-lite' ); ?></p></article>
        <article><b>02</b><h3><?php esc_html_e( 'Expedição', 'chavevertical-lite' ); ?></h3><p><?php esc_html_e( 'Selecionamos o transporte adequado ao peso, volume e destino.', 'chavevertical-lite' ); ?></p></article>
        <article><b>03</b><h3><?php esc_html_e( 'Acompanhamento', 'chavevertical-lite' ); ?></h3><p><?php esc_html_e( 'Mantemos o cliente informado e prestamos apoio sempre que necessário.', 'chavevertical-lite' ); ?></p></article>
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
