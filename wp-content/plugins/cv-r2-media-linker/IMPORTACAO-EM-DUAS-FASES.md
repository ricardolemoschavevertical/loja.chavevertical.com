# Importação em duas fases (CV R2 Media Linker 2.4.0)

## Objetivo
Acelerar a transferência de produtos de `chavevertical.com` para `loja.chavevertical.com` sem processar imagens durante o primeiro percurso. O modo de imagens já existente é usado depois para adicionar a imagem principal, galeria e imagens das variações.

## Utilização (começar em staging)

1. Fazer cópia de segurança da base de dados e confirmar que não há outra importação a decorrer.
2. Abrir **WooCommerce → Importação REST + R2 → Importação**.
3. Clicar em **Testar REST**. A preparação de termos/atributos é opcional; **não cria categorias**, apenas verifica as existentes. Pode criar etiquetas, marcas e atributos.
4. Selecionar **1.ª fase — importar produtos sem imagens**, escolher um lote pequeno (5–10 no primeiro teste) e iniciar. Esperar pelas fases de produtos e relações.
5. Verificar SKU, slugs, preços, stock, categorias e variações no destino. Se alguma categoria não existir pelo slug/ID mapeado, a linha falha e não é gravada (ver mensagens de erro); **nunca se cria uma categoria**.
6. Depois, selecionar **2.ª fase — associar imagens** (o modo `images_only` que já existia), iniciar e deixar processar. O modo de imagens só modifica anexos/galerias dos produtos já existentes no destino.
7. Verificar imagens principais, galerias e variações em 5–10 produtos, incluindo produtos que já tinham imagem e produtos novos.

## O que mudou

- Adicionado `products_no_images`, modo upsert que mantém o estado (publicado/rascunho/pendente/privado), slugs, SKU, stock, atributos, variações, metadados SEO e relações, mas não executa associação de imagens.
- No modo sem imagens, os campos `images` (produtos) e `image` (variações) não são solicitados à API de origem, quando possível.
- Atualizações de produtos existentes **preservam a imagem principal e a galeria atuais** na primeira fase; imagens de variações existentes também não são alteradas.
- **Categorias existentes apenas:** só se associam categorias com ID de origem previamente mapeado ou slug exato existente. Não há criação/alteração de `product_cat` em nenhum dos modos. Uma categoria ausente causa erro antes de gravar o produto, para evitar atribuições incorretas.
- O antigo modo `images_only` permanece disponível e usa a função que já existe no plugin para reutilizar imagens R2.
- Checkpoints e retoma por produto continuam a funcionar em cada fase.

## Limitações / segurança

- Os dois percursos são **independentes e iniciados manualmente**; a segunda fase não começa automaticamente.
- A importação rápida continua a depender do desempenho do WooCommerce e das variações/atributos, e não garante uma velocidade fixa.
- Uma primeira fase com produtos publicados pode mostrar produtos sem imagem temporariamente. Fazer a migração em staging ou numa janela controlada.
- Produtos com categorias em falta aparecem nos erros. Corrigir o mapeamento/categorias manualmente e voltar a correr a primeira fase; como é upsert, os SKU existentes são atualizados.
- A fase de imagens pode recorrer ao mecanismo de fallback existente do `cv-r2-media-linker` caso uma imagem não esteja no R2.
- Não executar importações concorrentes; o estado de importação é partilhado.
- Após verificar os testes e os logs do servidor, aplicar a alteração pelo processo de deploy normal. **Esta branch não está aplicada automaticamente à loja.**

## Testes mínimos antes de produção

- Produto simples novo com 2 imagens: primeira fase cria sem associar imagens; segunda associa.
- Produto já existente com imagem diferente: primeira fase preserva-a; segunda atualiza.
- Produto variável com imagem por variação: nenhuma imagem nova na primeira fase; associação na segunda.
- Categoria que existe pelo mesmo slug: usa o ID do destino.
- Categoria inexistente: produto não é gravado e aparece erro; a categoria não é criada.
- Repetir a primeira fase e segunda fase para verificar idempotência e retoma.
