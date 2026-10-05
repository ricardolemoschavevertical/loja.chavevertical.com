<?php
/**
 * Reconcile the next legacy-order archive page with the consolidated Gmail
 * order summary document. Only the local historical archive is changed.
 *
 * Source: Google Doc 15AKFFgU0JenFSeeQg7v-tw2FIcX9-nqqW1hjc-wReyQ
 * Scope: visible archive rows from #1665649 through #1664132.
 *
 * Statuses, stock, customer accounts and emails are deliberately untouched.
 */

defined( 'ABSPATH' ) || exit;

$marker = 'cv_repair_archive_doc_page3_20261005_v1';

if ( get_option( $marker ) ) {
    echo "archive-doc-page3=already-applied\n";
    return;
}

$payload = json_decode( <<<'JSON'
{"orders":[{"id":1665649,"archive_key":"remote-8d20db3492-1665649","email_id":"1a01a8d187cfe36d","items":[{"name":"Recarga de Oxigénio 1L Oxyturbo 480300","sku":"MAD67049","quantity":3,"gross":"93.00"}],"shipping_title":"Portes em Portugal Continental (Transporte marítimo é da responsabilidade do cliente)","shipping_total":"7.00","fee_gross":"0.00","total":"100.00","total_tax":"17.39","payment_method_title":"Pagamento de Serviços no Multibanco","customer_note":""},{"id":1665582,"archive_key":"remote-8d20db3492-1665582","email_id":"1a016172002e89f8","items":[{"name":"Depósito para Gasóleo STILKER 200L 12V 40L/min Polietileno","sku":"SOD08612","quantity":1,"gross":"380.00"}],"shipping_title":"ENTREGA GRATUITA*","shipping_total":"0.00","fee_gross":"0.00","total":"380.00","total_tax":"71.06","payment_method_title":"Pagamento de Serviços no Multibanco","customer_note":""},{"id":1665579,"archive_key":"remote-8d20db3492-1665579","email_id":"1a015a7ebffe1f00","items":[{"name":"Kit de bloqueio de distribuição VAG JBM","sku":"JBM52271","quantity":1,"gross":"18.45"}],"shipping_title":"ENVIO (Portugal Continental)","shipping_total":"6.50","fee_gross":"0.00","total":"24.95","total_tax":"3.45","payment_method_title":"Transferência Bancária","customer_note":""},{"id":1665576,"archive_key":"remote-8d20db3492-1665576","email_id":"1a0159aa500de9e7","items":[{"name":"Mobiliário de Oficina 4 Elementos com 147 Ferramentas STILKER","sku":"SOD84080","quantity":1,"gross":"1452.00"}],"shipping_title":"ENTREGA GRATUITA*","shipping_total":"0.00","fee_gross":"0.00","total":"1452.00","total_tax":"271.51","payment_method_title":"Pagamento de Serviços no Multibanco","customer_note":""},{"id":1665575,"archive_key":"remote-8d20db3492-1665575","email_id":"1a01588843ade2e2","items":[{"name":"Luvas de Nitrilo Pretas Diamond Kroftools Tamanho L 7.0MIL 100 Unidades","sku":"KT6002","quantity":1,"gross":"16.00"}],"shipping_title":"ENVIO (Portugal Continental)","shipping_total":"6.50","fee_gross":"0.00","total":"22.50","total_tax":"2.99","payment_method_title":"Transferência Bancária","customer_note":""},{"id":1665546,"archive_key":"remote-8d20db3492-1665546","email_id":"1a011381f44d24eb","items":[{"name":"Carro de Ferramentas Vazio STILKER 7 Gavetas Azul/Preto","sku":"SOD72520","quantity":1,"gross":"250.00"}],"shipping_title":"ENTREGA GRATUITA*","shipping_total":"0.00","fee_gross":"0.00","total":"250.00","total_tax":"46.75","payment_method_title":"Até 6x sem juros","customer_note":""},{"id":1665545,"archive_key":"remote-8d20db3492-1665545","email_id":"1a0112bf5030faa5","items":[{"name":"Carro de Ferramentas Vazio STILKER 7 Gavetas Azul/Preto","sku":"SOD72520","quantity":1,"gross":"250.00"}],"shipping_title":"ENTREGA GRATUITA*","shipping_total":"0.00","fee_gross":"0.00","total":"250.00","total_tax":"46.75","payment_method_title":"Até 6x sem juros","customer_note":""},{"id":1665544,"archive_key":"remote-8d20db3492-1665544","email_id":"1a0111479af3c162","items":[{"name":"Compressor Silencioso Sem Óleo STANLEY SXCMS250HE 50L 2.0HP 10 BAR","sku":"NUARB2DC404STN742","quantity":1,"gross":"187.00"}],"shipping_title":"ENTREGA GRATUITA*","shipping_total":"0.00","fee_gross":"0.00","total":"187.00","total_tax":"34.97","payment_method_title":"MBWAY","customer_note":""},{"id":1665538,"archive_key":"remote-8d20db3492-1665538","email_id":"1a00ed9225ae6e98","items":[{"name":"Gerador Inverter Hyundai HY3000i 3.3kW 230V Gasolina","sku":"GRVHY3000I","quantity":1,"gross":"367.46"}],"shipping_title":"ENTREGA GRATUITA*","shipping_total":"0.00","fee_gross":"0.00","total":"367.46","total_tax":"68.71","payment_method_title":"Transferência Bancária","customer_note":""},{"id":1665536,"archive_key":"remote-8d20db3492-1665536","email_id":"1a00a7495f42b89e","items":[{"name":"Kit de Sincronização STILKER para VAG Gasolina 1.2 6V-1.2 12V","sku":"SOD71544","quantity":1,"gross":"13.00"}],"shipping_title":"ENVIO (Portugal Continental)","shipping_total":"6.50","fee_gross":"0.00","total":"19.50","total_tax":"2.43","payment_method_title":"MBWAY","customer_note":""},{"id":1665535,"archive_key":"remote-8d20db3492-1665535","email_id":"1a00f69c209c573f","items":[{"name":"Manómetro para Verificar Pressão de Turbos KROFTOOLS -1 a 3 BAR 2M","sku":"KT4482","quantity":1,"gross":"51.66"}],"shipping_title":"ENVIO (Portugal Continental)","shipping_total":"6.50","fee_gross":"0.00","total":"58.16","total_tax":"9.66","payment_method_title":"VISA / MASTERCARD","customer_note":""},{"id":1665534,"archive_key":"remote-8d20db3492-1665534","email_id":"1a005b138e277f03","items":[{"name":"Elevador de Motos Hidráulico KROFTOOLS 450kg 175-750mm","sku":"KT9860","quantity":1,"gross":"554.00"}],"shipping_title":"ENTREGA GRATUITA*","shipping_total":"0.00","fee_gross":"0.00","total":"554.00","total_tax":"103.59","payment_method_title":"Até 6x sem juros","customer_note":""},{"id":1665533,"archive_key":"remote-8d20db3492-1665533","email_id":"1a00543a842691fb","items":[{"name":"Saco de Areia para Decapar 30/40 25kg POWERED","sku":"POWERED205917","quantity":1,"gross":"25.00"}],"shipping_title":"ENVIO (Portugal Continental)","shipping_total":"6.50","fee_gross":"0.00","total":"31.50","total_tax":"4.67","payment_method_title":"MBWAY","customer_note":""},{"id":1665532,"archive_key":"remote-8d20db3492-1665532","email_id":"1a001413b57c1361","items":[{"name":"Cabeça para Roscadora de Tubos POWERED PEPT50 - 1\"","sku":"POWERED235768","quantity":1,"gross":"39.36"},{"name":"Cabeça para Roscadora de Tubos POWERED PEPT50 - 1-1/4\"","sku":"POWERED235769","quantity":1,"gross":"44.28"},{"name":"Cabeça para Roscadora de Tubos POWERED PEPT50 - 1-1/2\"","sku":"POWERED235778","quantity":1,"gross":"54.12"},{"name":"Cabeça para Roscadora de Tubos POWERED PEPT50 - 2\"","sku":"POWERED235779","quantity":1,"gross":"60.27"}],"shipping_title":"ENTREGA GRATUITA*","shipping_total":"0.00","fee_gross":"0.00","total":"198.03","total_tax":"37.03","payment_method_title":"Transferência Bancária","customer_note":""},{"id":1665401,"archive_key":"remote-8d20db3492-1665401","email_id":"19ffc5df0e0054c9","items":[{"name":"Depósito Reabastecimento Gasóleo Stilker 400L 12V","sku":"SOD56000","quantity":1,"gross":"577.00"}],"shipping_title":"ENTREGA GRATUITA*","shipping_total":"0.00","fee_gross":"0.00","total":"577.00","total_tax":"107.89","payment_method_title":"MBWAY","customer_note":""},{"id":1665365,"archive_key":"remote-8d20db3492-1665365","email_id":"19ffa7653be369ab","items":[{"name":"Carro de Mão Elétrico ZIPPER EWB300-160L 40V 300kg","sku":"ZI-EWB300-160L","quantity":1,"gross":"1031.99"}],"shipping_title":"ENTREGA GRATUITA*","shipping_total":"0.00","fee_gross":"0.00","total":"1031.99","total_tax":"192.97","payment_method_title":"VISA / MASTERCARD","customer_note":"conforme mensagens com o sr. Ricardo, solicitamos entrega dia 20/8 entre as 9.30 e as 13.30. Obrigado"},{"id":1665362,"archive_key":"remote-8d20db3492-1665362","email_id":"19ffa3cff533ad64","items":[{"name":"Bomba Hidráulica Manual MAMMUTH 700 Bar 2000 ml","sku":"VKPCP700B","quantity":1,"gross":"368.00"},{"name":"Cilindro Hidráulico Duplo VKP 50 Ton Elevação 64mm","sku":"VKPMFL50D","quantity":1,"gross":"320.00"}],"shipping_title":"ENTREGA GRATUITA*","shipping_total":"0.00","fee_gross":"0.00","total":"688.00","total_tax":"128.65","payment_method_title":"Pagamento de Serviços no Multibanco","customer_note":""},{"id":1665280,"archive_key":"remote-8d20db3492-1665280","email_id":"19ff5d06fbdd3d59","items":[{"name":"Carro Armazém Reforçado com Báscula SIRL 200kg 3.50-4","sku":"SIRLU.04500.1275","quantity":1,"gross":"61.00"}],"shipping_title":"ENVIO (Portugal Continental)","shipping_total":"6.50","fee_gross":"0.00","total":"67.50","total_tax":"11.41","payment_method_title":"MBWAY","customer_note":""},{"id":1665216,"archive_key":"remote-8d20db3492-1665216","email_id":"19ff26165d586150","items":[{"name":"Kit de Bloqueio de Motores Ford Diesel KROFTOOLS 1.6 1.8 2.5","sku":"KT1521","quantity":1,"gross":"39.00"}],"shipping_title":"ENVIO (Portugal Continental)","shipping_total":"6.50","fee_gross":"0.00","total":"45.50","total_tax":"7.29","payment_method_title":"MBWAY","customer_note":""},{"id":1665214,"archive_key":"remote-8d20db3492-1665214","email_id":"1a014d475b49d882","items":[{"name":"Extrator e Rebitador de Corrente para Moto TOOLHUB 428-530","sku":"TR2840","quantity":1,"gross":"30.00"}],"shipping_title":"LEVANTAMENTO EM CONDEIXA*","shipping_total":"0.00","fee_gross":"0.00","total":"30.00","total_tax":"5.61","payment_method_title":"VISA / MASTERCARD","customer_note":""},{"id":1665213,"archive_key":"remote-8d20db3492-1665213","email_id":"19ff0bfece084637","items":[{"name":"Extrator e Rebitador de Corrente para Moto TOOLHUB 428-530","sku":"TR2840","quantity":1,"gross":"30.00"}],"shipping_title":"LEVANTAMENTO EM CONDEIXA*","shipping_total":"0.00","fee_gross":"0.00","total":"30.00","total_tax":"5.61","payment_method_title":"MBWAY","customer_note":""},{"id":1665207,"archive_key":"remote-8d20db3492-1665207","email_id":"19fecde8caa00797","items":[{"name":"Carro de Mão MADER 200KG 140L Roda Pneumática","sku":"MAD87649","quantity":1,"gross":"119.00"}],"shipping_title":"ENVIO (Portugal Continental)","shipping_total":"6.50","fee_gross":"0.00","total":"125.50","total_tax":"22.25","payment_method_title":"MBWAY","customer_note":""},{"id":1665150,"archive_key":"remote-8d20db3492-1665150","email_id":"19fdbb5c9c5d2e95","items":[{"name":"Módulo Vazio KROFTOOLS para Chaves de Caixa 1/4 42 Peças","sku":"KT8510V","quantity":1,"gross":"12.30"},{"name":"Módulo Vazio KROFTOOLS para Chaves de Caixa 1/4 19 Peças","sku":"KT8511V","quantity":1,"gross":"12.30"},{"name":"Módulo Vazio KROFTOOLS para Alicates 3 Peças","sku":"KT8512V","quantity":1,"gross":"12.30"},{"name":"Módulo Vazio KROFTOOLS para Alicates de Freios 4 Peças","sku":"KT8513V","quantity":1,"gross":"12.30"},{"name":"Módulo Vazio KROFTOOLS para Chaves T UMB. 6 Peças","sku":"KT8514V","quantity":1,"gross":"12.30"},{"name":"MODULO 085Pcs BITS VAZIO KROFTOOLS","sku":"KT8516V","quantity":1,"gross":"12.30"},{"name":"Módulo Vazio KROFTOOLS para Chaves de Travão 6-24 MM","sku":"KT8517V","quantity":1,"gross":"12.30"},{"name":"MODULO 017Pcs CHAVES BOCA LUNETA 6-22 VAZIO KROFTOOLS","sku":"KT8518V","quantity":1,"gross":"12.30"},{"name":"Módulo Vazio KROFTOOLS para Torx com Punho T10-T40 7 Peças","sku":"KT8519V","quantity":1,"gross":"12.30"},{"name":"Módulo Vazio KROFTOOLS para Chaves de Fenda e Cruz 8 Peças","sku":"KT8520V","quantity":1,"gross":"12.30"},{"name":"Módulo Vazio KROFTOOLS para Limas 5 Peças","sku":"KT8521V","quantity":1,"gross":"12.30"},{"name":"Módulo Vazio KROFTOOLS para Martelos 2 Peças","sku":"KT8522V","quantity":1,"gross":"12.30"},{"name":"Módulo Vazio KROFTOOLS para Alicates 2 Peças","sku":"KT8523V","quantity":1,"gross":"12.30"},{"name":"MODULO 008Pcs CHAVES LUNETA 6-22 VAZIO KROFTOOLS","sku":"KT8524V","quantity":1,"gross":"12.30"},{"name":"Módulo Vazio KROFTOOLS para Chaves de Boca 6-32 MM","sku":"KT8526V","quantity":1,"gross":"12.30"},{"name":"Módulo Vazio KROFTOOLS para Ferramenta de Medição 7 Peças","sku":"KT8528V","quantity":1,"gross":"12.30"},{"name":"MODULO 101Pcs ALICATE REBITES E REBITES VAZIO KROFTOOLS","sku":"KT8529V","quantity":1,"gross":"12.30"},{"name":"Módulo Vazio KROFTOOLS para Chaves Boca-Luneta 24-32 MM","sku":"KT8527V","quantity":1,"gross":"17.22"},{"name":"Módulo de Chaves de Fenda e Cruz KROFTOOLS 8 Peças","sku":"KT8520","quantity":1,"gross":"25.83"},{"name":"Carro de Ferramentas Kroftools 14 Gavetas Vazio","sku":"KT8660","quantity":1,"gross":"811.00"}],"shipping_title":"ENTREGA GRATUITA*","shipping_total":"0.00","fee_gross":"0.00","total":"1063.15","total_tax":"198.80","payment_method_title":"MBWAY","customer_note":""},{"id":1665148,"archive_key":"remote-8d20db3492-1665148","email_id":"19fd809c5578de06","items":[{"name":"Depósito para Gasóleo STILKER 200L 12V 40L/min Polietileno","sku":"SOD08612","quantity":1,"gross":"380.00"}],"shipping_title":"ENTREGA GRATUITA*","shipping_total":"0.00","fee_gross":"0.00","total":"380.00","total_tax":"71.06","payment_method_title":"MBWAY","customer_note":""},{"id":1665103,"archive_key":"remote-8d20db3492-1665103","email_id":"19fd2b87f4f78ed6","items":[{"name":"Andaime Completo de 2 m SIRL","sku":"SIRLE.04010.4210","quantity":2,"gross":"170.00"},{"name":"Prancha Metálica Galvanizada SIRL 2165x298 mm","sku":"SIRLE.04510.4235","quantity":3,"gross":"105.00"}],"shipping_title":"ENTREGA GRATUITA*","shipping_total":"0.00","fee_gross":"0.00","total":"275.00","total_tax":"51.42","payment_method_title":"MBWAY","customer_note":""},{"id":1665075,"archive_key":"remote-8d20db3492-1665075","email_id":"19fcdcb02a8e5a77","items":[{"name":"Pistola de Impacto Brushless JBM 60064C 2x20V 4000Nm 1\"","sku":"JBM60064C","quantity":1,"gross":"1063.95"}],"shipping_title":"ENTREGA GRATUITA*","shipping_total":"0.00","fee_gross":"0.00","total":"1063.95","total_tax":"198.95","payment_method_title":"MBWAY","customer_note":""},{"id":1664733,"archive_key":"remote-8d20db3492-1664733","email_id":"19fcd418be0b2f19","items":[{"name":"Betoneira Elétrica SIRL PRO 170 160L 25kg","sku":"SIRLB.17420.0701","quantity":1,"gross":"451.00"},{"name":"Carro de Mão Profissional IRBAL Tenerife 85 L Roda Anti-furo","sku":"IRBMC.D.1.05.02.4.3.1","quantity":1,"gross":"50.00"}],"shipping_title":"ENTREGA GRATUITA*","shipping_total":"0.00","fee_gross":"0.00","total":"501.00","total_tax":"93.68","payment_method_title":"Até 6x sem juros","customer_note":""},{"id":1664730,"archive_key":"remote-8d20db3492-1664730","email_id":"19fcc0c6fe62e898","items":[{"name":"Kit de Sincronização para Motores 2.7 e 3.0 TD V6 Kroftools","sku":"KT1658","quantity":1,"gross":"33.00"}],"shipping_title":"LEVANTAMENTO EM CONDEIXA*","shipping_total":"0.00","fee_gross":"0.00","total":"33.00","total_tax":"6.17","payment_method_title":"MBWAY","customer_note":""},{"id":1664722,"archive_key":"remote-8d20db3492-1664722","email_id":"19fd1bfa19626696","items":[{"name":"Curvadora Manual p/ barra e tubo HOLZMANN URB30","sku":"HZMURB30","quantity":1,"gross":"307.00"}],"shipping_title":"ENTREGA GRATUITA*","shipping_total":"0.00","fee_gross":"0.00","total":"307.00","total_tax":"57.41","payment_method_title":"VISA / MASTERCARD","customer_note":""},{"id":1664721,"archive_key":"remote-8d20db3492-1664721","email_id":"19fc7cbe784b01c0","items":[{"name":"CHAVES COMBINADAS DE 6 A 28/30/32MM 25 PCS STILKER","sku":"SOD68641","quantity":1,"gross":"10.00"}],"shipping_title":"LEVANTAMENTO EM CACIA*","shipping_total":"0.00","fee_gross":"0.00","total":"10.00","total_tax":"1.87","payment_method_title":"MBWAY","customer_note":""},{"id":1664720,"archive_key":"remote-8d20db3492-1664720","email_id":"19fc6f99e303e4b1","items":[{"name":"Mala de Ferramentas JBM 37 Peças Polegadas Sextavadas 1/2\" e 1/4\"","sku":"JBM54106","quantity":2,"gross":"176.00"},{"name":"JOGO CHAVES SEXTAVADAS 5093XLBS FORCE","sku":"POWERED312240","quantity":2,"gross":"36.90"}],"shipping_title":"ENTREGA GRATUITA*","shipping_total":"0.00","fee_gross":"0.00","total":"212.90","total_tax":"39.81","payment_method_title":"MBWAY","customer_note":""},{"id":1664719,"archive_key":"remote-8d20db3492-1664719","email_id":"1a03d9aaa76f849b","items":[{"name":"Mala de Ferramentas JBM 37 Peças Polegadas Sextavadas 1/2\" e 1/4\"","sku":"JBM54106","quantity":2,"gross":"176.00"},{"name":"JOGO CHAVES SEXTAVADAS 5093XLBS FORCE","sku":"POWERED312240","quantity":2,"gross":"36.90"}],"shipping_title":"ENTREGA GRATUITA*","shipping_total":"0.00","fee_gross":"0.00","total":"212.90","total_tax":"39.81","payment_method_title":"VISA / MASTERCARD","customer_note":""},{"id":1664454,"archive_key":"remote-8d20db3492-1664454","email_id":"19fb3319678d696a","items":[{"name":"Escadote em Alumínio VITO Plus 5 Degraus","sku":"VIEAP5","quantity":1,"gross":"78.00"},{"name":"Andaime Semi-Profissional de Alumínio LIT 9m","sku":"LITA5001190","quantity":1,"gross":"1465.00"},{"name":"Plataforma p/ Andaime Semi-Profissional LIT Com Quadra 66x25mm 530x1980mm","sku":"LITA6001102","quantity":1,"gross":"379.00"},{"name":"Plataforma para Andaime Semi-Profissional LIT Sem Quadra 66x25mm 1.98m 16kg","sku":"LITA6001101","quantity":1,"gross":"341.00"}],"shipping_title":"ENTREGA GRATUITA*","shipping_total":"0.00","fee_gross":"0.00","total":"2263.00","total_tax":"423.16","payment_method_title":"Pagamento de Serviços no Multibanco","customer_note":"Avisar 24horas antes da entrega."},{"id":1664453,"archive_key":"remote-8d20db3492-1664453","email_id":"19fb32661aee6efd","items":[{"name":"Grua Desdobrável 1 Tonelada KROFTOOLS Hidráulica com Rodas Giratórias","sku":"KT4860","quantity":1,"gross":"249.00"}],"shipping_title":"ENTREGA GRATUITA*","shipping_total":"0.00","fee_gross":"0.00","total":"249.00","total_tax":"46.56","payment_method_title":"MBWAY","customer_note":""},{"id":1664299,"archive_key":"remote-8d20db3492-1664299","email_id":"19fa9b54262c73ae","items":[{"name":"AFIADOR DE CORRENTE 220W MGD MADER","sku":"MAD01006","quantity":1,"gross":"38.61"}],"shipping_title":"ENVIO PRIORITÁRIO (Apenas disponível para Toolhub | JBM | POWERED)","shipping_total":"10.00","fee_gross":"0.00","total":"48.61","total_tax":"7.22","payment_method_title":"Transferência Bancária","customer_note":""},{"id":1664294,"archive_key":"remote-8d20db3492-1664294","email_id":"19fa916db2eba566","items":[{"name":"Pinça Porta Tubos GRIZ PPT 1000L 1000kg","sku":"GRIZPPT1000L","quantity":1,"gross":"357.00"}],"shipping_title":"ENTREGA GRATUITA*","shipping_total":"0.00","fee_gross":"0.00","total":"357.00","total_tax":"66.76","payment_method_title":"Até 6x sem juros","customer_note":""},{"id":1664293,"archive_key":"remote-8d20db3492-1664293","email_id":"19fa91018368e1a7","items":[{"name":"Pinça Porta Tubos GRIZ PPT 1000L 1000kg","sku":"GRIZPPT1000L","quantity":1,"gross":"357.00"}],"shipping_title":"ENTREGA GRATUITA*","shipping_total":"0.00","fee_gross":"0.00","total":"357.00","total_tax":"66.76","payment_method_title":"Até 6x sem juros","customer_note":""},{"id":1664292,"archive_key":"remote-8d20db3492-1664292","email_id":"19fa9738f4a744f0","items":[{"name":"Suporte Grua para Big-Bag ND 1000kg 1500x1500mm","sku":"ND3049-SA","quantity":1,"gross":"478.00"}],"shipping_title":"ENTREGA GRATUITA*","shipping_total":"0.00","fee_gross":"0.00","total":"478.00","total_tax":"89.38","payment_method_title":"VISA / MASTERCARD","customer_note":""},{"id":1664291,"archive_key":"remote-8d20db3492-1664291","email_id":"19fa8e327c057a05","items":[{"name":"TORNO DE BANCADA FIXO 200MM CHEMITOOL","sku":"CHT20010000200","quantity":1,"gross":"173.43"}],"shipping_title":"ENTREGA GRATUITA*","shipping_total":"0.00","fee_gross":"0.00","total":"173.43","total_tax":"32.43","payment_method_title":"Pagamento de Serviços no Multibanco","customer_note":""},{"id":1664267,"archive_key":"remote-8d20db3492-1664267","email_id":"19fa84da83ba4a18","items":[{"name":"ESCORAS / ITs METÁLICOS EXTENSIVEIS SIRL - P.04000.1015","sku":"SIRLP.04000.1015","quantity":5,"gross":"86.10"}],"shipping_title":"ENVIO (Portugal Continental)","shipping_total":"6.50","fee_gross":"0.00","total":"92.60","total_tax":"16.10","payment_method_title":"MBWAY","customer_note":""},{"id":1664266,"archive_key":"remote-8d20db3492-1664266","email_id":"19fa73f4d6ded72f","items":[{"name":"Chave para Bucha do Eixo SAF Euro Toolhub 140mm 32/46mm","sku":"TR9494","quantity":1,"gross":"117.00"}],"shipping_title":"ENVIO (Portugal Continental)","shipping_total":"10.50","fee_gross":"0.00","total":"127.50","total_tax":"21.88","payment_method_title":"Pagamento de Serviços no Multibanco","customer_note":"tefonar quando forem entregar"},{"id":1664258,"archive_key":"remote-8d20db3492-1664258","email_id":"19fa474fd59488d6","items":[{"name":"KIT ADAPT.P/JOGO MANOMETRO BOMBA INJECÇÃO DIESEL KROFTOOLS","sku":"KT1682","quantity":1,"gross":"13.53"},{"name":"Bomba Manual para Extração de Óleos e Diesel JBM 32L/min","sku":"JBM52428","quantity":1,"gross":"40.00"}],"shipping_title":"ENVIO PRIORITÁRIO (Apenas disponível para Toolhub | JBM | POWERED)","shipping_total":"15.00","fee_gross":"0.00","total":"68.53","total_tax":"10.01","payment_method_title":"Pagamento de Serviços no Multibanco","customer_note":""},{"id":1664244,"archive_key":"remote-8d20db3492-1664244","email_id":"19fa061b1a8196fd","items":[{"name":"CINTA DE AMARRAÇÃO 7,5T 15M COM ROQUETE ARRIMUP","sku":"SOD19471","quantity":1,"gross":"25.83"}],"shipping_title":"ENVIO (Portugal Continental)","shipping_total":"7.00","fee_gross":"0.00","total":"32.83","total_tax":"4.83","payment_method_title":"MBWAY","customer_note":""},{"id":1664241,"archive_key":"remote-8d20db3492-1664241","email_id":"19fa00fce8b766e8","items":[{"name":"CARRO ARMAZÉM EXTENSÍVEL AÇO 250KG MADER","sku":"MAD87663","quantity":1,"gross":"62.20"}],"shipping_title":"ENVIO (Portugal Continental)","shipping_total":"7.00","fee_gross":"0.00","total":"69.20","total_tax":"11.63","payment_method_title":"MBWAY","customer_note":"Ligar para 918933821 quando perto!"},{"id":1664232,"archive_key":"remote-8d20db3492-1664232","email_id":"19f9f1744c75958d","items":[{"name":"KIT DE DESMONTAGEM DO TABLIER TOOLHUB","sku":"TR6511","quantity":1,"gross":"25.83"}],"shipping_title":"ENVIO (Portugal Continental)","shipping_total":"11.00","fee_gross":"0.00","total":"36.83","total_tax":"4.83","payment_method_title":"Transferência Bancária","customer_note":""},{"id":1664230,"archive_key":"remote-8d20db3492-1664230","email_id":"19f9e44b10a45edc","items":[{"name":"Kit de Sincronização STILKER para VAG 1.4-1.6-2.0 CR TDI","sku":"SOD71525","quantity":1,"gross":"31.00"}],"shipping_title":"ENVIO (Portugal Continental)","shipping_total":"7.00","fee_gross":"0.00","total":"38.00","total_tax":"5.80","payment_method_title":"MBWAY","customer_note":""},{"id":1664221,"archive_key":"remote-8d20db3492-1664221","email_id":"19f9b369626d6a25","items":[{"name":"Régua Vibrante Elétrica KOMPAK KP-SCE-E 230 V","sku":"GRVKP-SCE-E","quantity":1,"gross":"199.26"}],"shipping_title":"ENTREGA GRATUITA*","shipping_total":"0.00","fee_gross":"0.00","total":"199.26","total_tax":"37.26","payment_method_title":"Até 6x sem juros","customer_note":""},{"id":1664132,"archive_key":"remote-8d20db3492-1664132","email_id":"19f92b581bf139b0","items":[{"name":"Serra de Sabre CAT DX5120 30mm 1200W","sku":"CAT2069","quantity":1,"gross":"107.13"}],"shipping_title":"ENTREGA GRATUITA*","shipping_total":"0.00","fee_gross":"0.00","total":"107.13","total_tax":"20.03","payment_method_title":"MBWAY","customer_note":""}],"unsupported":[1665364,1665356]}
JSON
, true );

if ( ! is_array( $payload ) || empty( $payload['orders'] ) ) {
    fwrite( STDERR, "archive-doc-page3=invalid-payload\n" );
    exit( 1 );
}

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

    $source_items = (array) ( $data['items'] ?? array() );

    if ( ! $source_items ) {
        $errors[] = "#{$order_id}: documento sem produtos.";
        continue;
    }

    $tax_basis = (float) ( $data['fee_gross'] ?? 0 );
    foreach ( $source_items as $source_item ) {
        $tax_basis += (float) ( $source_item['gross'] ?? 0 );
    }

    $declared_tax = (float) ( $data['total_tax'] ?? 0 );
    $tax_left     = $declared_tax;
    $components   = count( $source_items ) + ( (float) ( $data['fee_gross'] ?? 0 ) > 0 ? 1 : 0 );
    $component_no = 0;
    $line_items   = array();
    $expected_qty = 0;

    foreach ( $source_items as $item_data ) {
        $component_no++;
        $sku      = (string) ( $item_data['sku'] ?? '' );
        $quantity = max( 1, absint( $item_data['quantity'] ?? 1 ) );
        $gross    = (float) ( $item_data['gross'] ?? 0 );

        if ( $component_no === $components ) {
            $item_tax = round( $tax_left, 2 );
        } else {
            $item_tax = $tax_basis > 0 ? round( $declared_tax * ( $gross / $tax_basis ), 2 ) : 0.0;
            $tax_left -= $item_tax;
        }

        $item_net   = round( $gross - $item_tax, 2 );
        $product_id = '' !== $sku && function_exists( 'wc_get_product_id_by_sku' )
            ? absint( wc_get_product_id_by_sku( $sku ) )
            : 0;

        $expected_qty += $quantity;

        $line_items[] = array(
            'id'           => 0,
            'name'         => (string) ( $item_data['name'] ?? '' ),
            'product_id'   => $product_id,
            'variation_id' => 0,
            'quantity'     => $quantity,
            'tax_class'    => '',
            'subtotal'     => wc_format_decimal( $item_net, 2 ),
            'subtotal_tax' => wc_format_decimal( $item_tax, 2 ),
            'total'        => wc_format_decimal( $item_net, 2 ),
            'total_tax'    => wc_format_decimal( $item_tax, 2 ),
            'taxes'        => array(),
            'sku'          => $sku,
            'price'        => wc_format_decimal( $item_net / $quantity, wc_get_price_decimals() ),
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
        $component_no++;
        $fee_tax = $component_no === $components
            ? round( $tax_left, 2 )
            : ( $tax_basis > 0 ? round( $declared_tax * ( $fee_gross / $tax_basis ), 2 ) : 0.0 );
        $fee_net = round( $fee_gross - $fee_tax, 2 );

        $order['fee_lines'][] = array(
            'id'         => 0,
            'name'       => 'Pagamento na entrega',
            'tax_class'  => '',
            'tax_status' => 'taxable',
            'total'      => wc_format_decimal( $fee_net, 2 ),
            'total_tax'  => wc_format_decimal( $fee_tax, 2 ),
            'taxes'      => array(),
            'meta_data'  => array(
                array(
                    'key'   => '_cv_recovered_from_email',
                    'value' => (string) ( $data['email_id'] ?? '' ),
                ),
            ),
        );
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
        'batch'         => 'page3-20261005-v1',
    );

    $saved = CVLOA_Archive::archive_batch( array( $order ), true );

    if ( is_wp_error( $saved ) ) {
        $errors[] = "#{$order_id}: " . $saved->get_error_message();
        continue;
    }

    $check = CVLOA_Archive::read_order( $archive_key );

    if ( ! is_array( $check ) ) {
        $errors[] = "#{$order_id}: falhou a releitura.";
        continue;
    }

    $actual_items = (array) ( $check['line_items'] ?? array() );
    $actual_qty   = 0;
    $actual_skus  = array();

    foreach ( $actual_items as $item ) {
        $item = (array) $item;
        $actual_qty += max( 0, (int) ( $item['quantity'] ?? 0 ) );
        $actual_skus[] = (string) ( $item['sku'] ?? '' );
    }

    $expected_skus = array_map(
        static fn( array $item ): string => (string) ( $item['sku'] ?? '' ),
        $source_items
    );

    sort( $actual_skus );
    sort( $expected_skus );

    if (
        count( $actual_items ) !== count( $source_items )
        || $actual_qty !== $expected_qty
        || $actual_skus !== $expected_skus
    ) {
        $errors[] = "#{$order_id}: linhas/SKU/quantidades não correspondem ao documento.";
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

    if ( abs( (float) ( $check['total_tax'] ?? 0 ) - $declared_tax ) > 0.01 ) {
        $errors[] = "#{$order_id}: IVA total não corresponde ao documento.";
        continue;
    }

    $updated++;

    echo sprintf(
        "updated-archive-order=%d;items=%d;quantity=%d;shipping=%s;total=%s\n",
        $order_id,
        count( $source_items ),
        $expected_qty,
        (string) ( $data['shipping_title'] ?? '' ),
        (string) ( $data['total'] ?? '0.00' )
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
        'unsupported_without_source' => array_map(
            'absint',
            (array) ( $payload['unsupported'] ?? array() )
        ),
    ),
    false
);

echo sprintf(
    "archive-doc-page3=success;updated=%d;unsupported=%d\n",
    $updated,
    count( (array) ( $payload['unsupported'] ?? array() ) )
);
