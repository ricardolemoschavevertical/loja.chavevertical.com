<?php
/**
 * Terms and conditions page replicated from the Astro institutional page.
 *
 * Template Name: Termos e Condicoes
 */
defined( 'ABSPATH' ) || exit;

get_header();

$contact_url = function_exists( 'cvl_contact_page_url' )
    ? cvl_contact_page_url()
    : home_url( '/contactos/' );

$sections = array(
    array(
        'title' => '1. Identificação da empresa',
        'body'  => '<p>A loja online chavevertical.com é propriedade de:</p><p><strong>CHAVE VERTICAL UNIPESSOAL LDA</strong><br>Morada: Trading Park Cacia – Rua da Paz n.º 123 – Armazém B, Quinta do Loureiro, 3800-587 Cacia, Aveiro<br>E-mail: <a href="mailto:geral@chavevertical.pt">geral@chavevertical.pt</a><br>Telefone: <a href="tel:+351234020500">234 020 500</a></p>',
    ),
    array(
        'title' => '2. Objeto',
        'body'  => '<p>Os presentes Termos e Condições definem as regras aplicáveis à utilização do website, à apresentação dos produtos, à realização de encomendas, aos pagamentos, às entregas, às garantias, ao tratamento de dados pessoais, às reclamações e à resolução de litígios.</p><p>A CHAVE VERTICAL reserva-se o direito de alterar os presentes Termos e Condições a qualquer momento, sendo a versão em vigor a que se encontrar publicada no website.</p>',
    ),
    array(
        'title' => '3. Informação comercial e apoio ao Cliente',
        'body'  => '<p>Toda a informação disponibilizada no website, bem como qualquer esclarecimento prestado pelos nossos colaboradores por telefone, e-mail, carta, fax ou outro meio de comunicação, tem natureza meramente informativa e comercial.</p><p>A informação prestada tem como objetivo ajudar o Cliente na escolha e compra dos produtos, não constituindo aconselhamento técnico, jurídico, financeiro ou profissional especializado, salvo quando expressamente indicado por escrito.</p><p>Sempre que existam dúvidas sobre características técnicas, compatibilidades, medidas, acessórios incluídos ou aplicação do produto, o Cliente deverá contactar previamente o serviço de apoio ao Cliente antes de concluir a encomenda.</p>',
    ),
    array(
        'title' => '4. Informação dos produtos',
        'body'  => '<p>A CHAVE VERTICAL procura apresentar os produtos com o maior rigor possível, incluindo descrições, características técnicas, imagens e demais informações relevantes.</p><p>No entanto, as imagens dos produtos são meramente ilustrativas e podem não corresponder exatamente ao artigo entregue, nomeadamente quanto a cor, acessórios incluídos, embalagem, configuração, marcações, versão ou pequenos detalhes visuais.</p><p>As informações técnicas dos produtos são, em grande parte, fornecidas pelos fabricantes, distribuidores ou fornecedores. A CHAVE VERTICAL não se responsabiliza por erros, omissões ou alterações técnicas efetuadas pelos fabricantes sem aviso prévio.</p><p>A CHAVE VERTICAL reserva-se o direito de corrigir, a qualquer momento, erros de informação, descrição, preço, stock, imagem ou qualquer outro conteúdo apresentado no website.</p>',
    ),
    array(
        'title' => '5. Preços, IVA e custos de envio',
        'body'  => '<p>Os preços apresentados no website incluem IVA à taxa legal em vigor, salvo indicação expressa em contrário.</p><p>Os preços dos produtos estão sujeitos a alteração sem aviso prévio.</p><p>Os custos de envio não estão incluídos no preço dos produtos, salvo indicação expressa em contrário, e são calculados em função do tipo de produto, peso, volume, morada de entrega e transportadora disponível.</p><p>Em determinados produtos, nomeadamente equipamentos volumosos, pesados, frágeis ou com requisitos especiais de descarga, poderá ser necessário cobrar serviços adicionais de transporte, manuseamento, entrega especial ou descarga.</p><p>Sempre que se verifique essa necessidade, o Cliente será informado antes da expedição da encomenda.</p>',
    ),
    array(
        'title' => '6. Encomendas',
        'body'  => '<p>A realização de uma encomenda no website pressupõe a aceitação dos presentes Termos e Condições.</p><p>Após a submissão da encomenda, o Cliente receberá uma confirmação por e-mail com os dados da mesma. Esta confirmação não constitui aceitação definitiva da encomenda quando existam erros manifestos de preço, stock, descrição, sistema ou qualquer outra anomalia técnica.</p><p>A CHAVE VERTICAL reserva-se o direito de não aceitar ou cancelar encomendas quando se verifique:</p><ul><li>erro manifesto no preço;</li><li>erro de stock;</li><li>erro técnico ou informático;</li><li>erro tipográfico;</li><li>suspeita de fraude;</li><li>impossibilidade de entrega;</li><li>indisponibilidade do produto;</li><li>dados de faturação ou entrega incompletos ou incorretos.</li></ul><p>Quando uma encomenda seja cancelada por motivo imputável à CHAVE VERTICAL e já tenha sido paga, o Cliente terá direito ao reembolso da quantia paga relativamente aos produtos não fornecidos.</p>',
    ),
    array(
        'title' => '7. Disponibilidade e rutura de stock',
        'body'  => '<p>A disponibilidade apresentada no website é indicativa e pode sofrer alterações sem aviso prévio.</p><p>Caso se verifique uma rutura temporária de stock após a realização da encomenda, o Cliente será informado através do e-mail associado à encomenda.</p><p>Nessa situação, a encomenda poderá:</p><ul><li>aguardar reposição de stock, mediante acordo com o Cliente;</li><li>ser parcialmente enviada, caso existam outros produtos disponíveis;</li><li>ser total ou parcialmente cancelada, com reembolso da quantia correspondente, caso já tenha sido paga.</li></ul><p>Em caso de indisponibilidade definitiva do produto, a CHAVE VERTICAL informará o Cliente e procederá ao cancelamento da encomenda ou da parte afetada.</p>',
    ),
    array(
        'title' => '8. Promoções, campanhas e vouchers',
        'body'  => '<p>A loja chavevertical.com poderá disponibilizar promoções, campanhas, descontos ou vouchers no próprio website, por e-mail, newsletter ou outros canais de comunicação.</p><p>As promoções são válidas durante o período indicado ou até rutura de stock.</p><p>Regras gerais de utilização de vouchers:</p><ul><li>apenas pode ser utilizado um voucher por encomenda, salvo indicação expressa em contrário;</li><li>os vouchers não são acumuláveis com outras campanhas, exceto quando expressamente indicado;</li><li>os vouchers poderão estar limitados a determinadas marcas, categorias, produtos, valores mínimos de compra ou períodos promocionais;</li><li>mensagens adicionais poderão surgir no carrinho ou checkout no momento da inserção do código;</li><li>a utilização indevida, abusiva ou fraudulenta de vouchers poderá levar ao cancelamento da encomenda.</li></ul><p>A CHAVE VERTICAL reserva-se o direito de alterar, suspender ou cancelar campanhas promocionais e vouchers a qualquer momento, sem prejuízo dos direitos legalmente aplicáveis às encomendas já confirmadas.</p>',
    ),
    array(
        'title' => '9. Pagamentos e soluções de financiamento',
        'body'  => '<p>Os métodos de pagamento disponíveis serão apresentados no checkout.</p><p>Quando aplicável, poderão estar disponíveis soluções de pagamento faseado, crédito ou financiamento através de entidades terceiras, como, por exemplo, soluções de crédito ao consumo.</p><p>A aprovação, análise, gestão e condições associadas a essas soluções são da responsabilidade da respetiva entidade financeira.</p><p>Em caso de cancelamento ou devolução de uma encomenda associada a financiamento, poderão existir custos administrativos, custos de abertura, gestão de processo ou outros encargos cobrados pela entidade financeira, nos termos contratados entre o Cliente e essa entidade.</p><p>Esses encargos, quando legal e contratualmente aplicáveis, poderão não ser reembolsáveis pela CHAVE VERTICAL.</p>',
    ),
    array(
        'title' => '10. Entregas',
        'body'  => '<p>As entregas são efetuadas para a morada indicada pelo Cliente no momento da encomenda.</p><p>O Cliente é responsável por garantir que os dados de entrega estão completos e corretos, incluindo nome, morada, código postal, localidade, contacto telefónico e demais informações necessárias.</p><p><strong>Dados incorretos de entrega:</strong> caso os dados de entrega fornecidos pelo Cliente estejam incorretos, incompletos ou impossibilitem a entrega da encomenda, a CHAVE VERTICAL não poderá ser responsabilizada por atrasos, devoluções à origem ou impossibilidade de entrega.</p><p>Nestas situações, quaisquer custos adicionais decorrentes de nova tentativa de entrega, alteração de morada, armazenagem, devolução à origem ou reexpedição da encomenda poderão ser imputados ao Cliente, sempre que o erro seja da sua responsabilidade.</p><p>Caso a encomenda seja devolvida à CHAVE VERTICAL por motivo imputável ao Cliente, nomeadamente morada incorreta, ausência reiterada no local de entrega, recusa injustificada da encomenda ou falta de levantamento no prazo indicado pela transportadora, o reenvio da encomenda ficará sujeito ao pagamento prévio dos novos custos de transporte.</p><p>Os prazos de entrega são indicativos e podem variar em função da disponibilidade do produto, transportadora, morada de entrega, volume da encomenda ou outros fatores externos à CHAVE VERTICAL.</p><p>Em produtos volumosos ou pesados, a entrega poderá ser efetuada ao nível da rua, salvo contratação expressa de serviço adicional de descarga, movimentação ou entrega em local específico.</p><p>No ato da entrega, o Cliente deverá verificar o estado exterior da embalagem. Caso existam danos visíveis, deverá mencioná-los na guia de transporte e contactar de imediato a CHAVE VERTICAL.</p>',
    ),
    array(
        'title' => '11. Devoluções, trocas e reembolsos',
        'body'  => '<p>O Cliente consumidor dispõe do direito de livre resolução do contrato no prazo legal de 14 dias, nos termos da legislação aplicável.</p><p>As condições detalhadas relativas a devoluções, trocas, custos de devolução, reembolsos, exclusões e formulário de livre resolução encontram-se disponíveis na página Política de Devoluções e Reembolsos.</p><p>Esta política faz parte integrante dos presentes Termos e Condições.</p>',
    ),
    array(
        'title' => '12. Garantia legal e conformidade dos bens',
        'body'  => '<p>Os produtos comercializados pela CHAVE VERTICAL beneficiam da garantia legal aplicável nos termos da legislação em vigor.</p><p>Em caso de falta de conformidade do produto, o Cliente deverá contactar a CHAVE VERTICAL, apresentando a fatura ou comprovativo de compra e descrevendo o problema verificado.</p><p>A garantia não cobre danos resultantes de:</p><ul><li>utilização incorreta ou negligente;</li><li>instalação inadequada;</li><li>falta de manutenção;</li><li>desgaste normal do produto;</li><li>utilização fora das recomendações do fabricante;</li><li>intervenção, reparação ou alteração por terceiros não autorizados;</li><li>danos provocados por transporte, queda, acidente, humidade, sobrecarga ou utilização inadequada;</li><li>uso profissional intensivo em produtos que não estejam preparados para esse tipo de utilização.</li></ul><p>Esta cláusula não prejudica os direitos legalmente conferidos ao consumidor.</p>',
    ),
    array(
        'title' => '13. Tratamento de dados pessoais',
        'body'  => '<p>A CHAVE VERTICAL UNIPESSOAL LDA é a entidade responsável pelo tratamento dos dados pessoais recolhidos através do website chavevertical.com.</p><p>Os dados pessoais fornecidos pelo Cliente serão tratados para as seguintes finalidades:</p><ul><li>gestão de encomendas;</li><li>faturação;</li><li>entrega de produtos;</li><li>apoio ao Cliente;</li><li>assistência pós-venda;</li><li>cumprimento de obrigações legais;</li><li>gestão de conta de cliente;</li><li>comunicações comerciais e marketing, quando exista consentimento ou fundamento legal aplicável;</li><li>definição de perfis comerciais, quando aplicável e permitido por lei.</li></ul><p>As bases de licitude para o tratamento dos dados poderão incluir:</p><ul><li>execução de contrato;</li><li>cumprimento de obrigações legais;</li><li>consentimento do titular dos dados;</li><li>interesse legítimo da CHAVE VERTICAL, quando aplicável.</li></ul><p>Quando o tratamento se baseie no consentimento, o titular dos dados poderá retirar esse consentimento a qualquer momento, sem que tal afete a legalidade do tratamento efetuado até essa data.</p>',
    ),
    array(
        'title' => '14. Acesso aos dados e subcontratantes',
        'body'  => '<p>Poderão ter acesso aos dados pessoais a CHAVE VERTICAL e entidades subcontratadas que prestem serviços necessários ao funcionamento da loja online, nomeadamente serviços de alojamento, faturação, transporte, pagamentos, apoio técnico, marketing ou comunicações.</p><p>Os subcontratantes apenas terão acesso aos dados estritamente necessários à prestação dos respetivos serviços e nos termos contratualmente definidos.</p>',
    ),
    array(
        'title' => '15. Direitos do titular dos dados',
        'body'  => '<p>Nos termos do RGPD e da legislação aplicável, o titular dos dados pode exercer os seguintes direitos:</p><ul><li>direito de acesso;</li><li>direito de retificação;</li><li>direito ao apagamento;</li><li>direito à limitação do tratamento;</li><li>direito de oposição;</li><li>direito à portabilidade dos dados;</li><li>direito de retirar o consentimento, quando aplicável;</li><li>direito de apresentar reclamação junto da Comissão Nacional de Proteção de Dados.</li></ul><p>Para exercer os seus direitos, o titular poderá contactar a CHAVE VERTICAL através do e-mail <a href="mailto:geral@chavevertical.pt">geral@chavevertical.pt</a>.</p>',
    ),
    array(
        'title' => '16. Reclamações e Livro de Reclamações',
        'body'  => '<p>O Cliente poderá apresentar reclamação através dos contactos da CHAVE VERTICAL:</p><p>E-mail: <a href="mailto:geral@chavevertical.pt">geral@chavevertical.pt</a><br>Telefone: <a href="tel:+351234020500">234 020 500</a><br>Morada: Trading Park Cacia – Rua da Paz n.º 123 – Armazém B, Quinta do Loureiro, 3800-587 Cacia, Aveiro</p><p>A CHAVE VERTICAL disponibiliza também o acesso ao Livro de Reclamações Eletrónico, nos termos legalmente aplicáveis.</p>',
    ),
    array(
        'title' => '17. Resolução alternativa de litígios de consumo',
        'body'  => '<p>Em caso de litígio de consumo, o Cliente poderá recorrer a uma Entidade de Resolução Alternativa de Litígios de Consumo.</p><p>Para mais informações, poderá consultar a lista de entidades de Resolução Alternativa de Litígios de Consumo disponibilizada pela Direção-Geral do Consumidor.</p><p>A resolução alternativa de litígios de consumo permite a mediação, conciliação ou arbitragem de conflitos de consumo através de entidades independentes.</p>',
    ),
    array(
        'title' => '18. Alterações aos Termos e Condições',
        'body'  => '<p>A CHAVE VERTICAL reserva-se o direito de alterar os presentes Termos e Condições a qualquer momento.</p><p>As alterações produzem efeitos a partir da data da sua publicação no website, salvo disposição legal em contrário.</p><p>Recomenda-se que o Cliente consulte regularmente esta página.</p>',
    ),
    array(
        'title' => '19. Lei aplicável',
        'body'  => '<p>Os presentes Termos e Condições são regidos pela lei portuguesa.</p><p>Em caso de litígio, e sem prejuízo das normas legais imperativas aplicáveis aos consumidores, será competente o foro legalmente determinado nos termos da legislação portuguesa.</p>',
    ),
);
?>
<section class="cvl-institutional-catalog-hero">
    <div class="cvl-shell">
        <div class="cvl-institutional-breadcrumbs">
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Início', 'chavevertical-lite' ); ?></a>
            <span aria-hidden="true">/</span>
            <span><?php esc_html_e( 'Termos e Condições', 'chavevertical-lite' ); ?></span>
        </div>
        <span class="cvl-institutional-catalog-kicker"><?php esc_html_e( 'Informação legal', 'chavevertical-lite' ); ?></span>
        <h1><?php esc_html_e( 'Termos e Condições', 'chavevertical-lite' ); ?></h1>
        <p><?php esc_html_e( 'Termos e condições aplicáveis à utilização da loja online Chave Vertical e às encomendas efetuadas através do website.', 'chavevertical-lite' ); ?></p>
    </div>
</section>

<section class="cvl-institutional-page cvl-shell">
    <div class="cvl-institutional-intro-card">
        <div>
            <span class="cvl-institutional-kicker"><?php esc_html_e( 'Informação legal', 'chavevertical-lite' ); ?></span>
            <h2><?php esc_html_e( 'Termos e Condições', 'chavevertical-lite' ); ?></h2>
            <p class="cvl-institutional-lead"><?php esc_html_e( 'Os presentes Termos e Condições regulam o acesso e a utilização do website chavevertical.com, bem como as condições aplicáveis às encomendas efetuadas através da loja online.', 'chavevertical-lite' ); ?></p>
            <p><?php esc_html_e( 'Ao navegar no website ou ao efetuar uma encomenda, o Cliente declara ter lido, compreendido e aceite os presentes Termos e Condições.', 'chavevertical-lite' ); ?></p>
        </div>
        <div class="cvl-institutional-meta">
            <span><?php esc_html_e( 'Última atualização: 16 de maio de 2026', 'chavevertical-lite' ); ?></span>
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
