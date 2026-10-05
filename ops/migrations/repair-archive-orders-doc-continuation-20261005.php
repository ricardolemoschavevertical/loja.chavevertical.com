<?php
/**
 * Reconcile the continuation of the visible legacy-order archive page with the
 * consolidated Gmail order summary document. Only the local archive is changed.
 *
 * Source document:
 * Google Doc ID 15AKFFgU0JenFSeeQg7v-tw2FIcX9-nqqW1hjc-wReyQ
 *
 * This migration deliberately does not change order status, stock, customers,
 * or send emails. Rows absent from the source document are left untouched.
 */

defined( 'ABSPATH' ) || exit;

$marker = 'cv_repair_archive_doc_continuation_20261005_v1';

if ( get_option( $marker ) ) {
    echo "archive-doc-continuation=already-applied\n";
    return;
}

$payload = json_decode( <<<'JSON'
{"orders":[{"id":1673873,"archive_key":"remote-8d20db3492-1673873","email_id":"1a086fe7ac0572e3","items":[{"name":"Elevador 2 Colunas 4T Basic-Line KROFTOOLS 4000kg Monofásico","sku":"KT9802","quantity":1,"gross":"1799.00"}],"shipping_title":"ENVIO (Portugal Continental)","shipping_total":"150.50","fee_gross":"6.15","total":"1955.65","total_tax":"337.55","payment_method_title":"Envio à cobrança","customer_note":""},{"id":1673809,"archive_key":"remote-8d20db3492-1673809","email_id":"1a08287c5229d5b0","items":[{"name":"Kit de Sincronização STILKER para VAG Gasolina 1.2 6V-1.2 12V","sku":"SOD71544","quantity":1,"gross":"13.00"}],"shipping_title":"ENVIO (Portugal Continental)","shipping_total":"7.50","fee_gross":"0.00","total":"20.50","total_tax":"2.43","payment_method_title":"Pagamento por Fatura Pro-forma","customer_note":""},{"id":1673789,"archive_key":"remote-8d20db3492-1673789","email_id":"1a081c54e30859a4","items":[{"name":"Aspirador de Aparas HOLZMANN ABS 2480","sku":"HZMABS2480_230V","quantity":1,"gross":"453.87"}],"shipping_title":"ENTREGA GRATUITA*","shipping_total":"0.00","fee_gross":"0.00","total":"453.87","total_tax":"84.87","payment_method_title":"MBWAY","customer_note":"Estarei fora apartir de dia 16. Tenho urgência."},{"id":1673788,"archive_key":"remote-8d20db3492-1673788","email_id":"1a08144dcc71ef80","items":[{"name":"Martelo Quebra-Vidros JBM com Suporte e Corta-Cinto 13.5cm","sku":"JBM52259","quantity":8,"gross":"29.52"}],"shipping_title":"ENVIO (Portugal Continental)","shipping_total":"7.50","fee_gross":"0.00","total":"37.02","total_tax":"5.52","payment_method_title":"MBWAY","customer_note":""},{"id":1672965,"archive_key":"remote-8d20db3492-1672965","email_id":"1a077e0c811b193c","items":[{"name":"Carro Armazém Reforçado com Báscula SIRL 200kg 3.50-4","sku":"SIRLU.04500.1275","quantity":1,"gross":"61.00"}],"shipping_title":"ENVIO (Portugal Continental)","shipping_total":"7.50","fee_gross":"0.00","total":"68.50","total_tax":"11.41","payment_method_title":"Pagamento de Serviços no Multibanco","customer_note":""},{"id":1672687,"archive_key":"remote-8d20db3492-1672687","email_id":"1a071a16d3c76dda","items":[{"name":"Grua Desdobrável Pneumática KROFTOOLS 2 Toneladas 2360mm","sku":"KT4865","quantity":1,"gross":"368.00"}],"shipping_title":"ENTREGA GRATUITA*","shipping_total":"0.00","fee_gross":"0.00","total":"368.00","total_tax":"68.81","payment_method_title":"Pagamento de Serviços no Multibanco","customer_note":""},{"id":1672588,"archive_key":"remote-8d20db3492-1672588","email_id":"1a06cd9aa3bddde2","items":[{"name":"Depósito para Gasóleo STILKER 200L 12V 40L/min Polietileno","sku":"SOD08612","quantity":1,"gross":"380.00"}],"shipping_title":"ENTREGA GRATUITA*","shipping_total":"0.00","fee_gross":"0.00","total":"380.00","total_tax":"71.06","payment_method_title":"Pagamento de Serviços no Multibanco","customer_note":""},{"id":1672505,"archive_key":"remote-8d20db3492-1672505","email_id":"1a06c9564570bc04","items":[{"name":"Contentor de Lixo em Polietileno 800L Elevação Ochsner e DIN","sku":"SOP05226000000","quantity":1,"gross":"368.00"}],"shipping_title":"ENTREGA GRATUITA*","shipping_total":"0.00","fee_gross":"0.00","total":"368.00","total_tax":"68.81","payment_method_title":"VISA / MASTERCARD","customer_note":""},{"id":1672278,"archive_key":"remote-8d20db3492-1672278","email_id":"1a068f762dc57893","items":[{"name":"Bomba de Gasóleo Mural STILKER 230V 50 L/Min com Contador e Filtro","sku":"SOD56130","quantity":1,"gross":"216.00"}],"shipping_title":"ENTREGA GRATUITA*","shipping_total":"0.00","fee_gross":"0.00","total":"216.00","total_tax":"40.39","payment_method_title":"Pagamento de Serviços no Multibanco","customer_note":""},{"id":1672276,"archive_key":"remote-8d20db3492-1672276","email_id":"1a0689c9a2566117","items":[{"name":"Escada de Alumínio Tripla LIT 3m Degrau Quadrado Reforçada","sku":"A11013030","quantity":1,"gross":"238.00"}],"shipping_title":"ENTREGA GRATUITA*","shipping_total":"0.00","fee_gross":"0.00","total":"238.00","total_tax":"44.50","payment_method_title":"Pagamento de Serviços no Multibanco","customer_note":""},{"id":1672275,"archive_key":"remote-8d20db3492-1672275","email_id":"1a06895433d4d01f","items":[{"name":"Escada de Alumínio Tripla LIT 3m Degrau Quadrado Reforçada","sku":"A11013030","quantity":1,"gross":"238.00"},{"name":"ESCADA MULTIUSOS ALUMÍNIO 5,25M 4X5 LIT","sku":"LITA10200005","quantity":1,"gross":"174.66"}],"shipping_title":"ENTREGA GRATUITA*","shipping_total":"0.00","fee_gross":"0.00","total":"412.66","total_tax":"77.16","payment_method_title":"Pagamento de Serviços no Multibanco","customer_note":""},{"id":1672268,"archive_key":"remote-8d20db3492-1672268","email_id":"1a066bdde48930ea","items":[{"name":"Depósito para Gasóleo STILKER 200L 12V 40L/min Polietileno","sku":"SOD08612","quantity":1,"gross":"380.00"}],"shipping_title":"ENTREGA GRATUITA*","shipping_total":"0.00","fee_gross":"0.00","total":"380.00","total_tax":"71.06","payment_method_title":"Pagamento de Serviços no Multibanco","customer_note":""},{"id":1672002,"archive_key":"remote-8d20db3492-1672002","email_id":"1a06354dfcee6353","items":[{"name":"Grua Desdobrável Hidráulica KROFTOOLS 3 Toneladas 2400mm","sku":"KT4867","quantity":1,"gross":"491.00"}],"shipping_title":"ENTREGA GRATUITA*","shipping_total":"0.00","fee_gross":"0.00","total":"491.00","total_tax":"91.81","payment_method_title":"MBWAY","customer_note":""},{"id":1671915,"archive_key":"remote-8d20db3492-1671915","email_id":"1a0626aa19805240","items":[{"name":"MANGUEIRA FLEXÍVEL DE POLIURETANO Ø 60MM / 2,5 HOLZSTAR","sku":"HOLZ5142501-2,5","quantity":1,"gross":"30.75"}],"shipping_title":"ENVIO (Portugal Continental)","shipping_total":"7.50","fee_gross":"0.00","total":"38.25","total_tax":"5.75","payment_method_title":"MBWAY","customer_note":""},{"id":1671674,"archive_key":"remote-8d20db3492-1671674","email_id":"1a0623563559112a","items":[{"name":"Elevador de Motos Hidráulico VKP 360 kg 1345x490 mm","sku":"VKPML37KH","quantity":1,"gross":"499.00"}],"shipping_title":"ENTREGA GRATUITA*","shipping_total":"0.00","fee_gross":"0.00","total":"499.00","total_tax":"93.31","payment_method_title":"Pagamento de Serviços no Multibanco","customer_note":""},{"id":1671673,"archive_key":"remote-8d20db3492-1671673","email_id":"1a0621986a49b934","items":[{"name":"Grua Desdobrável Hidráulica KROFTOOLS 3 Toneladas 2400mm","sku":"KT4867","quantity":1,"gross":"491.00"}],"shipping_title":"ENTREGA GRATUITA*","shipping_total":"0.00","fee_gross":"0.00","total":"491.00","total_tax":"91.81","payment_method_title":"Até 6x sem juros","customer_note":""},{"id":1671105,"archive_key":"remote-8d20db3492-1671105","email_id":"1a06187bbd9f3d98","items":[{"name":"CARRO PLATAFORMA MINI REBATÍVEL ND","sku":"ND6005","quantity":2,"gross":"158.00"}],"shipping_title":"ENTREGA GRATUITA*","shipping_total":"0.00","fee_gross":"0.00","total":"158.00","total_tax":"29.54","payment_method_title":"Pagamento de Serviços no Multibanco","customer_note":""},{"id":1671044,"archive_key":"remote-8d20db3492-1671044","email_id":"1a05e6c8d6a442ba","items":[{"name":"Alicate de Cortar Tubos de Escape com Corrente Kroftools 160mm","sku":"KT6178","quantity":1,"gross":"25.00"},{"name":"Kit sacar injetores completo KROFTOOLS","sku":"KT8549","quantity":1,"gross":"244.00"},{"name":"KIT LIMPEZA ASSENTOS INJETORES DIESEL KROFTOOLS","sku":"KT4410","quantity":1,"gross":"108.24"}],"shipping_title":"ENTREGA GRATUITA*","shipping_total":"0.00","fee_gross":"0.00","total":"377.24","total_tax":"70.54","payment_method_title":"VISA / MASTERCARD","customer_note":""},{"id":1671042,"archive_key":"remote-8d20db3492-1671042","email_id":"1a05e4bc42e8b0c1","items":[{"name":"Desmontador de Pneus Automático Kroftools 12\"-24\" 220V","sku":"KT9047","quantity":1,"gross":"1000.00"}],"shipping_title":"ENVIO (Portugal Continental)","shipping_total":"77.50","fee_gross":"0.00","total":"1077.50","total_tax":"186.99","payment_method_title":"Transferência Bancária","customer_note":""},{"id":1669755,"archive_key":"remote-8d20db3492-1669755","email_id":"1a05cb4ddad7033d","items":[{"name":"LUBRIFICANTE REFRIGERANTE 5L METAIS EUROBOOR","sku":"ABT444399309","quantity":1,"gross":"83.64"}],"shipping_title":"ENVIO (Portugal Continental)","shipping_total":"7.50","fee_gross":"0.00","total":"91.14","total_tax":"15.64","payment_method_title":"VISA / MASTERCARD","customer_note":""},{"id":1669564,"archive_key":"remote-8d20db3492-1669564","email_id":"1a05c829bf8241e7","items":[{"name":"Roda de Vulkollan AYERBE Ø 80 mm x 72 mm","sku":"AY580550","quantity":4,"gross":"68.00"},{"name":"Roda Vulkollan AYERBE 180 mm x 50 mm com Rolamentos de 20 mm","sku":"AY5417825","quantity":2,"gross":"46.00"}],"shipping_title":"ENVIO (Portugal Continental)","shipping_total":"7.50","fee_gross":"0.00","total":"121.50","total_tax":"21.32","payment_method_title":"MBWAY","customer_note":""},{"id":1669334,"archive_key":"remote-8d20db3492-1669334","email_id":"1a059c8aa376d71d","items":[{"name":"Filtro de Ar de Carvão Ativado ANI 1/2\" Fêmea 12 bar","sku":"SOD10555","quantity":1,"gross":"181.00"}],"shipping_title":"ENTREGA GRATUITA*","shipping_total":"0.00","fee_gross":"0.00","total":"181.00","total_tax":"33.85","payment_method_title":"MBWAY","customer_note":""},{"id":1668535,"archive_key":"remote-8d20db3492-1668535","email_id":"1a057e31cde683fc","items":[{"name":"Carro de Oficina STILKER 7 Gavetas 300kg","sku":"SOD72518","quantity":1,"gross":"250.00"}],"shipping_title":"ENTREGA GRATUITA*","shipping_total":"0.00","fee_gross":"0.00","total":"250.00","total_tax":"46.75","payment_method_title":"MBWAY","customer_note":"Concessionária Toyota, ligar para o meu número 910190211 para eu vir buscar na entrada"},{"id":1668508,"archive_key":"remote-8d20db3492-1668508","email_id":"1a057397337778f7","items":[{"name":"Carro de Oficina STILKER 7 Gavetas 300kg","sku":"SOD72518","quantity":1,"gross":"250.00"}],"shipping_title":"ENTREGA GRATUITA*","shipping_total":"0.00","fee_gross":"0.00","total":"250.00","total_tax":"46.75","payment_method_title":"Pagamento de Serviços no Multibanco","customer_note":""},{"id":1668507,"archive_key":"remote-8d20db3492-1668507","email_id":"1a0572e15be96bde","items":[{"name":"Carro de Ferramentas JBM com 7 Gavetas e 172 Peças","sku":"JBM43904","quantity":1,"gross":"416.00"}],"shipping_title":"ENTREGA GRATUITA*","shipping_total":"0.00","fee_gross":"0.00","total":"416.00","total_tax":"77.79","payment_method_title":"MBWAY","customer_note":""},{"id":1666660,"archive_key":"remote-8d20db3492-1666660","email_id":"1a04d727cd5a9f97","items":[{"name":"Guincho Manual Tirfor UNICRAFT USZ 801 0.8t 20m","sku":"UNIC6171608","quantity":1,"gross":"203.00"}],"shipping_title":"ENTREGA GRATUITA*","shipping_total":"0.00","fee_gross":"0.00","total":"203.00","total_tax":"37.96","payment_method_title":"Pagamento de Serviços no Multibanco","customer_note":""},{"id":1666573,"archive_key":"remote-8d20db3492-1666573","email_id":"1a04ae39d2e693ca","items":[{"name":"Kit Martelo Perfurador e Rebarbadora CAT DX2140 + DX3090 800W 125mm","sku":"CAT2316","quantity":1,"gross":"90.00"},{"name":"Broca Craniana 68mm MADER","sku":"MAD95856","quantity":1,"gross":"13.00"},{"name":"NÍVEL TUBULAR 40CM HEAVYWARE","sku":"POWERED242319","quantity":1,"gross":"8.61"},{"name":"Extensão de Fio Elétrico MADER 10m 3Gx1mm²","sku":"MAD90672","quantity":1,"gross":"8.27"},{"name":"CHAVE AJUSTAVEL (INGLESA) 250MM (10\") HEAVYWARE TOOLS","sku":"POWERED203551","quantity":1,"gross":"6.15"}],"shipping_title":"ENTREGA GRATUITA*","shipping_total":"0.00","fee_gross":"0.00","total":"126.03","total_tax":"23.57","payment_method_title":"MBWAY","customer_note":"Percebi corretamente que o martelo perfurador vem numa mala de plástico e inclui um conjunto de cinzéis?"},{"id":1666443,"archive_key":"remote-8d20db3492-1666443","email_id":"1a04943acff69f2e","items":[{"name":"Elevador Tesoura Chão Hidráulico Amovível Kroftools 3Ton 2.2KW","sku":"KT9817","quantity":1,"gross":"1907.00"},{"name":"Taco de Borracha para Elevador de Tesoura TOOLHUB 120x160x40mm","sku":"TR9954","quantity":4,"gross":"80.00"}],"shipping_title":"ENTREGA GRATUITA*","shipping_total":"0.00","fee_gross":"0.00","total":"1987.00","total_tax":"371.55","payment_method_title":"MBWAY","customer_note":""},{"id":1666437,"archive_key":"remote-8d20db3492-1666437","email_id":"1a047b243573d5cf","items":[{"name":"Fita de Serra POWERED 3010x27 mm 8/12 dentes","sku":"POWERED354097","quantity":2,"gross":"84.00"}],"shipping_title":"ENVIO (Portugal Continental)","shipping_total":"7.50","fee_gross":"0.00","total":"91.50","total_tax":"15.71","payment_method_title":"Pagamento de Serviços no Multibanco","customer_note":""},{"id":1666436,"archive_key":"remote-8d20db3492-1666436","email_id":"1a04794e4b789e88","items":[{"name":"Máquina de Decapar Jato de Areia KROFTOOLS 80L com Mangueira 2,5m","sku":"KT9761","quantity":1,"gross":"185.00"}],"shipping_title":"ENTREGA GRATUITA*","shipping_total":"0.00","fee_gross":"0.00","total":"185.00","total_tax":"34.59","payment_method_title":"Pagamento de Serviços no Multibanco","customer_note":""},{"id":1666322,"archive_key":"remote-8d20db3492-1666322","email_id":"1a043fa96b903889","items":[{"name":"KIT DE BLOQUEIO VOLKSWAGEN / SEAT / SKODA 1.2L JBM","sku":"JBM53274","quantity":1,"gross":"30.75"}],"shipping_title":"Portes em Portugal Continental (Transporte marítimo é da responsabilidade do cliente)","shipping_total":"7.00","fee_gross":"0.00","total":"37.75","total_tax":"5.75","payment_method_title":"MBWAY","customer_note":""},{"id":1666316,"archive_key":"remote-8d20db3492-1666316","email_id":"1a043d20603516ad","items":[{"name":"PLATAFORMA ESCADA MULTIUSO 1.5M WERKU","sku":"WK700070","quantity":1,"gross":"18.45"}],"shipping_title":"ENVIO (Portugal Continental)","shipping_total":"7.50","fee_gross":"0.00","total":"25.95","total_tax":"3.45","payment_method_title":"MBWAY","customer_note":""},{"id":1666295,"archive_key":"remote-8d20db3492-1666295","email_id":"1a041684433b1bde","items":[{"name":"Tanque Vertical FINI 270L 11 Bar","sku":"BOL10600545","quantity":1,"gross":"453.00"}],"shipping_title":"ENTREGA GRATUITA*","shipping_total":"0.00","fee_gross":"0.00","total":"453.00","total_tax":"84.71","payment_method_title":"Pagamento de Serviços no Multibanco","customer_note":""},{"id":1666179,"archive_key":"remote-8d20db3492-1666179","email_id":"1a0431dce94b8aae","items":[{"name":"Betoneira Bricolage BELLE Minimix 130 230V 130L 34kg","sku":"IRBMC.ME12","quantity":1,"gross":"602.70"}],"shipping_title":"ENTREGA GRATUITA*","shipping_total":"0.00","fee_gross":"0.00","total":"602.70","total_tax":"112.70","payment_method_title":"VISA / MASTERCARD","customer_note":""},{"id":1666153,"archive_key":"remote-8d20db3492-1666153","email_id":"1a03df845de49e37","items":[{"name":"Kit Sincronização VAG JBM 1.7 1.9 Diesel SDI TDI 12 Peças","sku":"JBM54408","quantity":1,"gross":"46.00"}],"shipping_title":"LEVANTAMENTO EM CACIA*","shipping_total":"0.00","fee_gross":"0.00","total":"46.00","total_tax":"8.60","payment_method_title":"MBWAY","customer_note":""},{"id":1666141,"archive_key":"remote-8d20db3492-1666141","email_id":"1a03cefc1a541384","items":[{"name":"RODA TRASEIRA PU VERMELHO Ø180X50MM POWERED","sku":"POWERED235792","quantity":2,"gross":"51.66"},{"name":"Carrinho de Transporte Sobe Escadas VITO 250 kg Rodas Triplas","sku":"VICTE250","quantity":1,"gross":"109.00"}],"shipping_title":"ENTREGA GRATUITA*","shipping_total":"0.00","fee_gross":"0.00","total":"160.66","total_tax":"30.04","payment_method_title":"MBWAY","customer_note":""},{"id":1666020,"archive_key":"remote-8d20db3492-1666020","email_id":"1a0386d4512583b7","items":[{"name":"Guilhotina Manual de faca HOLZMANN BSS1000","sku":"HZMBSS1000","quantity":1,"gross":"467.00"}],"shipping_title":"ENTREGA GRATUITA*","shipping_total":"0.00","fee_gross":"0.00","total":"467.00","total_tax":"87.33","payment_method_title":"Pagamento de Serviços no Multibanco","customer_note":""},{"id":1666014,"archive_key":"remote-8d20db3492-1666014","email_id":"1a03614c020805cf","items":[{"name":"Elevador 2 Colunas 4T Basic-Line KROFTOOLS 4000kg Monofásico","sku":"KT9802","quantity":1,"gross":"1799.00"}],"shipping_title":"Portes em Portugal Continental (Transporte marítimo é da responsabilidade do cliente)","shipping_total":"7.00","fee_gross":"0.00","total":"1806.00","total_tax":"336.40","payment_method_title":"Até 6x sem juros","customer_note":""},{"id":1666013,"archive_key":"remote-8d20db3492-1666013","email_id":"1a03612e9a681611","items":[{"name":"Elevador 2 Colunas 4T Basic-Line KROFTOOLS 4000kg Monofásico","sku":"KT9802","quantity":1,"gross":"1799.00"}],"shipping_title":"Portes em Portugal Continental (Transporte marítimo é da responsabilidade do cliente)","shipping_total":"7.00","fee_gross":"0.00","total":"1806.00","total_tax":"336.40","payment_method_title":"Até 6x sem juros","customer_note":""},{"id":1665999,"archive_key":"remote-8d20db3492-1665999","email_id":"1a034ddbec25f5bd","items":[{"name":"Depósito para Gasóleo STILKER 200L 12V 40L/min Polietileno","sku":"SOD08612","quantity":1,"gross":"380.00"}],"shipping_title":"ENTREGA GRATUITA*","shipping_total":"0.00","fee_gross":"0.00","total":"380.00","total_tax":"71.06","payment_method_title":"Pagamento de Serviços no Multibanco","customer_note":""},{"id":1665994,"archive_key":"remote-8d20db3492-1665994","email_id":"1a034d69dbe72964","items":[{"name":"Carro Armazém Cervejeiro SIRL Alumínio Roda Anti Furo 100Kg","sku":"SIRLU.04600.1266","quantity":1,"gross":"103.00"}],"shipping_title":"LEVANTAMENTO EM CACIA*","shipping_total":"0.00","fee_gross":"0.00","total":"103.00","total_tax":"19.26","payment_method_title":"Pagamento de Serviços no Multibanco","customer_note":""},{"id":1665993,"archive_key":"remote-8d20db3492-1665993","email_id":"1a034d3779408e39","items":[{"name":"Carro Armazém Cervejeiro SIRL Alumínio Roda Anti Furo 100Kg","sku":"SIRLU.04600.1266","quantity":1,"gross":"103.00"}],"shipping_title":"ENVIO (Portugal Continental)","shipping_total":"7.50","fee_gross":"0.00","total":"110.50","total_tax":"19.26","payment_method_title":"Pagamento de Serviços no Multibanco","customer_note":""},{"id":1665961,"archive_key":"remote-8d20db3492-1665961","email_id":"1a03334995905428","items":[{"name":"Fita de Serra POWERED 5300x34 mm Dente 3/4","sku":"POWERED354327","quantity":3,"gross":"261.00"}],"shipping_title":"ENTREGA GRATUITA*","shipping_total":"0.00","fee_gross":"0.00","total":"261.00","total_tax":"48.80","payment_method_title":"Transferência Bancária","customer_note":""},{"id":1665877,"archive_key":"remote-8d20db3492-1665877","email_id":"1a02a6f96a8317e4","items":[{"name":"Escada de Alumínio Tripla LIT 3,5m Degrau Quadrado Max 9,00m","sku":"A10013035","quantity":1,"gross":"237.00"}],"shipping_title":"LEVANTAMENTO EM CACIA*","shipping_total":"0.00","fee_gross":"6.15","total":"243.15","total_tax":"45.47","payment_method_title":"Envio à cobrança","customer_note":""},{"id":1665876,"archive_key":"remote-8d20db3492-1665876","email_id":"1a025f2b44d1416f","items":[{"name":"CINTA LIXA 75X1016MM G.40 (SL75-2) OPTIMUM","sku":"OPTI3357682","quantity":2,"gross":"100.86"}],"shipping_title":"ENVIO (Portugal Continental)","shipping_total":"7.50","fee_gross":"0.00","total":"108.36","total_tax":"18.86","payment_method_title":"Pagamento de Serviços no Multibanco","customer_note":"Oficina dos Caldeireiros (Loulé Criativo)"},{"id":1665875,"archive_key":"remote-8d20db3492-1665875","email_id":"1a025a38ee26c18d","items":[{"name":"Endoscópio Wifi JBM 8mm 1m HD IP67","sku":"JBM53724","quantity":1,"gross":"47.00"}],"shipping_title":"ENVIO (Portugal Continental)","shipping_total":"7.50","fee_gross":"0.00","total":"54.50","total_tax":"8.79","payment_method_title":"Pagamento de Serviços no Multibanco","customer_note":""},{"id":1665855,"archive_key":"remote-8d20db3492-1665855","email_id":"1a024f6f7319d868","items":[{"name":"CINTA LIXA 75X1016MM G.40 (SL75-2) OPTIMUM","sku":"OPTI3357682","quantity":4,"gross":"201.72"}],"shipping_title":"ENTREGA GRATUITA*","shipping_total":"0.00","fee_gross":"0.00","total":"201.72","total_tax":"37.72","payment_method_title":"Pagamento de Serviços no Multibanco","customer_note":"Oficina dos Caldeireiros(Loulé Criativo)"},{"id":1665842,"archive_key":"remote-8d20db3492-1665842","email_id":"1a021506d7bb83e1","items":[{"name":"Elevador 2 Colunas 4T Basic-Line KROFTOOLS 4000kg Monofásico","sku":"KT9802","quantity":1,"gross":"1799.00"}],"shipping_title":"Portes em Portugal Continental (Transporte marítimo é da responsabilidade do cliente)","shipping_total":"7.00","fee_gross":"0.00","total":"1806.00","total_tax":"336.40","payment_method_title":"Até 6x sem juros","customer_note":""}],"unsupported":[1673549,1672862]}
JSON
, true );

if ( ! is_array( $payload ) || empty( $payload['orders'] ) ) {
    fwrite( STDERR, "Documento consolidado sem dados válidos para esta migração.\n" );
    exit( 1 );
}

$split_gross = static function ( $gross ): array {
    $gross = (float) $gross;
    $net   = round( $gross / 1.23, 2 );
    $tax   = round( $gross - $net, 2 );

    return array(
        'net' => wc_format_decimal( $net, 2 ),
        'tax' => wc_format_decimal( $tax, 2 ),
    );
};

$errors  = array();
$updated = 0;

foreach ( (array) $payload['orders'] as $data ) {
    $order_id    = absint( $data['id'] ?? 0 );
    $archive_key = sanitize_key( (string) ( $data['archive_key'] ?? '' ) );
    $order       = CVLOA_Archive::read_order( $archive_key );

    if ( ! $order_id || '' === $archive_key || ! is_array( $order ) ) {
        $errors[] = "#{$order_id}: não foi possível ler {$archive_key}.";
        continue;
    }

    $line_items          = array();
    $calculated_tax      = 0.0;
    $expected_quantities = 0;

    foreach ( (array) ( $data['items'] ?? array() ) as $item_data ) {
        $sku      = (string) ( $item_data['sku'] ?? '' );
        $quantity = max( 1, absint( $item_data['quantity'] ?? 1 ) );
        $gross    = (float) ( $item_data['gross'] ?? 0 );
        $parts    = $split_gross( $gross );

        $product_id = 0;
        if ( '' !== $sku && function_exists( 'wc_get_product_id_by_sku' ) ) {
            $product_id = absint( wc_get_product_id_by_sku( $sku ) );
        }

        $calculated_tax      += (float) $parts['tax'];
        $expected_quantities += $quantity;

        $line_items[] = array(
            'id'           => 0,
            'name'         => (string) ( $item_data['name'] ?? '' ),
            'product_id'   => $product_id,
            'variation_id' => 0,
            'quantity'     => $quantity,
            'tax_class'    => '',
            'subtotal'     => (string) $parts['net'],
            'subtotal_tax' => (string) $parts['tax'],
            'total'        => (string) $parts['net'],
            'total_tax'    => (string) $parts['tax'],
            'taxes'        => array(),
            'sku'          => $sku,
            'price'        => wc_format_decimal( (float) $parts['net'] / $quantity, wc_get_price_decimals() ),
            'meta_data'    => array(
                array(
                    'key'   => '_cv_recovered_sku',
                    'value' => $sku,
                ),
                array(
                    'key'   => '_cv_recovered_from_email',
                    'value' => (string) ( $data['email_id'] ?? '' ),
                ),
            ),
        );
    }

    if ( ! $line_items ) {
        $errors[] = "#{$order_id}: o documento não contém produtos válidos.";
        continue;
    }

    $order['line_items'] = $line_items;

    $order['shipping_lines'] = array(
        array(
            'id'           => 0,
            'method_title' => (string) ( $data['shipping_title'] ?? '' ),
            'method_id'    => 'cv-email-recovered',
            'instance_id'  => '',
            'total'        => (string) ( $data['shipping_total'] ?? '0.00' ),
            'total_tax'    => '0.00',
            'taxes'        => array(),
            'meta_data'    => array(
                array(
                    'key'   => '_cv_recovered_from_email',
                    'value' => (string) ( $data['email_id'] ?? '' ),
                ),
            ),
        ),
    );

    $order['fee_lines'] = array();
    $fee_gross = (float) ( $data['fee_gross'] ?? 0 );

    if ( $fee_gross > 0 ) {
        $fee_parts       = $split_gross( $fee_gross );
        $calculated_tax += (float) $fee_parts['tax'];

        $order['fee_lines'][] = array(
            'id'         => 0,
            'name'       => 'Pagamento na entrega',
            'tax_class'  => '',
            'tax_status' => 'taxable',
            'total'      => (string) $fee_parts['net'],
            'total_tax'  => (string) $fee_parts['tax'],
            'taxes'      => array(),
            'meta_data'  => array(
                array(
                    'key'   => '_cv_recovered_from_email',
                    'value' => (string) ( $data['email_id'] ?? '' ),
                ),
            ),
        );
    }

    $declared_tax = (float) ( $data['total_tax'] ?? 0 );
    if ( abs( round( $calculated_tax, 2 ) - $declared_tax ) > 0.02 ) {
        $errors[] = sprintf(
            '#%d: IVA calculado %.2f não corresponde ao documento %.2f.',
            $order_id,
            round( $calculated_tax, 2 ),
            $declared_tax
        );
        continue;
    }

    if ( ! empty( $data['payment_method_title'] ) ) {
        $order['payment_method_title'] = (string) $data['payment_method_title'];
    }

    if ( ! empty( $data['customer_note'] ) ) {
        $order['customer_note'] = (string) $data['customer_note'];
    }

    $order['shipping_total'] = (string) ( $data['shipping_total'] ?? '0.00' );
    $order['shipping_tax']   = '0.00';
    $order['total_tax']      = (string) ( $data['total_tax'] ?? '0.00' );
    $order['total']          = (string) ( $data['total'] ?? '0.00' );
    $order['_cvloa_archive_key'] = $archive_key;
    $order['_cvloa_doc_reconciled'] = array(
        'document_id'   => '15AKFFgU0JenFSeeQg7v-tw2FIcX9-nqqW1hjc-wReyQ',
        'email_id'      => (string) ( $data['email_id'] ?? '' ),
        'reconciled_at' => gmdate( 'c' ),
        'batch'         => 'continuation-20261005-v1',
    );

    $result = CVLOA_Archive::archive_batch( array( $order ), true );

    if ( is_wp_error( $result ) ) {
        $errors[] = "#{$order_id}: " . $result->get_error_message();
        continue;
    }

    $check = CVLOA_Archive::read_order( $archive_key );

    if ( ! is_array( $check ) ) {
        $errors[] = "#{$order_id}: falhou a releitura depois da atualização.";
        continue;
    }

    $actual_items = (array) ( $check['line_items'] ?? array() );
    if ( count( $actual_items ) !== count( (array) $data['items'] ) ) {
        $errors[] = "#{$order_id}: produtos não correspondem ao documento.";
        continue;
    }

    $actual_quantity = 0;
    $actual_skus     = array();

    foreach ( $actual_items as $item ) {
        $item = (array) $item;
        $actual_quantity += max( 0, (int) ( $item['quantity'] ?? 0 ) );
        $actual_skus[] = (string) ( $item['sku'] ?? '' );
    }

    $expected_skus = array_map(
        static fn( array $item ): string => (string) ( $item['sku'] ?? '' ),
        (array) $data['items']
    );

    sort( $actual_skus );
    sort( $expected_skus );

    if ( $actual_skus !== $expected_skus || $actual_quantity !== $expected_quantities ) {
        $errors[] = "#{$order_id}: SKU/quantidades não correspondem ao documento.";
        continue;
    }

    if ( empty( $check['shipping_lines'] ) ) {
        $errors[] = "#{$order_id}: envio ficou em falta.";
        continue;
    }

    if ( abs( (float) ( $check['total'] ?? 0 ) - (float) $data['total'] ) > 0.01 ) {
        $errors[] = "#{$order_id}: total final não corresponde ao documento.";
        continue;
    }

    $updated++;

    echo sprintf(
        "updated-archive-order=%d;key=%s;items=%d;quantity=%d;shipping=%s;total=%s\n",
        $order_id,
        $archive_key,
        count( (array) $data['items'] ),
        $expected_quantities,
        (string) $data['shipping_title'],
        (string) $data['total']
    );
}

if ( $errors ) {
    foreach ( $errors as $error ) {
        fwrite( STDERR, $error . "\n" );
    }
    exit( 1 );
}

update_option(
    $marker,
    array(
        'applied_at' => gmdate( 'c' ),
        'updated'    => $updated,
        'unsupported_without_source' => array_map( 'absint', (array) ( $payload['unsupported'] ?? array() ) ),
    ),
    false
);

echo sprintf(
    "archive-doc-continuation=success;updated=%d;unsupported=%d\n",
    $updated,
    count( (array) ( $payload['unsupported'] ?? array() ) )
);
