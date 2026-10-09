# Catálogo local / CSV — CV R2 Media Linker 2.5.0

## Para que serve
Este módulo é uma **opção adicional** ao importador REST que já existia. Congela um catálogo em JSONL privado no **servidor da loja de destino** e importa a partir desse ficheiro, sem repetir pedidos REST durante a gravação de produtos. Os bytes das imagens não são descarregados durante a preparação: o ficheiro guarda apenas as referências às imagens no R2 (e as variações).

URL de administração:
`/wp-admin/admin.php?page=cv-r2-rest-import&tab=local`

**NÃO cria nem altera categorias WooCommerce.** As categorias têm de existir previamente no destino e corresponder por ID de origem previamente associado ou slug único; no CSV a coluna categorias usa slugs existentes separados por `|` (opcionalmente caminho `categoria-pai > categoria-filha`). Um produto com categorias em falta é recusado antes da gravação.

## Origem A — site original por REST

1. Abrir o separador **Catálogo local / CSV**.
2. **Preparar catálogo REST**. O plugin pede páginas WooCommerce `products` e, para produtos variáveis, descarrega também `variations` neste passo.
3. Esperar por **Catálogo pronto**. O estado é guardado página a página. Se o navegador fechar, **Retomar operação**.
4. **Importar produtos sem imagens**. O plugin cria/atualiza os produtos e variações a partir do ficheiro, guarda progresso a cada produto, respeita o slug original e usa apenas as categorias existentes. No final resolve upsells, cross-sells e produtos agrupados para os produtos importados nesta execução.
5. Verificar resultados e eventuais linhas rejeitadas.
6. **Associar imagens mais tarde**. O plugin consulta as referências já guardadas no ficheiro, reutiliza anexos R2 existentes ou regista-os com o mecanismo do `cv-r2-media-linker`. Não reimporta preços, descrições nem categorias.

## Origem B — CSV do Windows

1. Carregar um ficheiro `.csv` (até 250 MB, dependente dos limites PHP).
2. Confirmar os cabeçalhos e a pré-visualização. Selecionar as colunas a utilizar. **SKU obrigatório**; **Nome e Slug** obrigatórios para produtos novos.
3. Preparar o ficheiro privado JSONL em lotes de 300 linhas, com retoma.
4. Importar produtos sem imagens.
5. Se a coluna `Imagens` foi escolhida, associar imagens posteriormente.

Regras CSV: campos não selecionados e células vazias **não alteram** os campos atuais. O valor literal `0` não é tratado como vazio. Os SKUs identificam produtos existentes. Novos produtos são criados em **rascunho** por defeito, salvo se a coluna Estado for selecionada. Apenas produtos simples ou produtos-pai variáveis; **variações por CSV não são implementadas** — para importar variações, utilizar a origem REST.

Campos disponíveis: SKU, nome, slug, tipo, estado, descrições, preços, gestão de stock, quantidade, disponibilidade, peso, dimensões, categorias, URLs HTTPS de imagens (separadas por `|`) e Rank Math (título, descrição, palavra-chave).

As URLs de imagens do CSV devem estar no domínio de origem ou no domínio R2 público configurados no plugin, em HTTPS. A fase de imagens nunca descarrega um URL arbitrário de um domínio externo fornecido no CSV.

## Armazenamento / segurança

- JSONL: `<CVR2_TRANSFER_DIR>/catalog-<id>.jsonl` (UUID hexadecimal).
- CSV temporário: `<CVR2_TRANSFER_DIR>/csv-<id>.csv`.
- Pasta por defeito neste alojamento: `/home/chavevertical-loja/private-data/chavevertical/product-transfer`.
- É possível escolher outra pasta **fora do document root**, em `wp-config.php`:
  `define( 'CVR2_TRANSFER_DIR', '/path/privado/para/catalogos' );`
- É obrigatório que o PHP-FPM tenha permissão de escrita. Não guardar ficheiros em `wp-content/uploads`, nem usar URLs públicas.
- Pedidos administrativos AJAX exigem nonce WordPress e permissões `manage_woocommerce` + `edit_products`; operações são serializadas com `flock`.
- Checkpoints em options persistem depois de cada página preparada ou produto gravado. Reexecução idempotente procura produtos por identidade SKU/ID de origem/slug, conforme o modo.
- O botão **Eliminar ficheiro local e estado** elimina o JSONL selecionado e o CSV atualmente carregado; **não elimina produtos WooCommerce**.

## Testes de aceitação antes de importar o catálogo completo

1. Loja staging, snapshot REST pequeno e comparação de SKU, slug, preço e estado.
2. Produto variável com 2 variações, produto agrupado e upsells/cross-sells: validar a importação sem chamadas REST adicionais na fase de gravação.
3. Produto existente com imagem principal e galeria: fase de produtos conserva-as; fase de imagens altera-as conforme R2.
4. Produto com categoria inexistente: resultado **Erro**, sem criação de categoria.
5. CSV de 3 linhas; mapear SKU/Nome/Slug/Stock e confirmar que a descrição e categoria existentes ficam intactas.
6. CSV com domínio externo na coluna imagens: a fase de imagens rejeita a URL.
7. Parar o browser a meio de cada fase e retomar; confirmar ausência de duplicação de produtos.
8. Confirmar permissões do diretório privado e espaço livre antes de preparar ~35 mil produtos.

## Atenção

A velocidade de **gravação WooCommerce** continua condicionada à base de dados, plugins, índices e número de variações/atributos. O ganho vem sobretudo de eliminar a latência de rede durante a importação e separar imagens. A criação do snapshot REST continua a requerer a leitura da origem.

O sistema é manual: preparar, importar produtos, rever relatório e só depois importar imagens. Não começa a importar em segundo plano quando não houver um separador ativo. A fase de imagens segue o comportamento R2 já existente, incluindo o possível fallback de importação da origem quando o ficheiro não se encontra no bucket.
