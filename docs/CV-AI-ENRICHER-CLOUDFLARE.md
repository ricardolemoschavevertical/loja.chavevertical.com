# CV AI Enricher — Cloudflare (fase 1)

Criados novos componentes independentes do WooCommerce Gemini Enricher 4.10.2:

- Plugin WordPress `cv-cloudflare-ai-enricher`: painel de listas, leitura de produtos e aplicacao de propostas revistas.
- Plugin WordPress `cv-mcp-bridge`: novo endpoint MCP isolado para ChatGPT/agentes, sem reaproveitar o endpoint legado.
- Cloudflare Worker `cv-ai-enricher`: Workers AI + Queues + D1 EU.

Os ficheiros de implementacao e os ZIP de instalacao foram preparados e validados no pacote entregue nesta conversa. Este registo no GitHub documenta a migracao **sem instalar nem ativar plugins automaticamente**.

Infraestrutura Cloudflare criada:
- D1 `cv-ai-enricher-db` (ID `cab9dcd9-9d1c-46df-b765-c74cb9cf3e9c`), tabelas `lists`, `list_items`, `jobs` e indices.
- Queue `cv-ai-enricher-jobs`.
- Dead letter queue `cv-ai-enricher-dlq`.

**Pendente antes de producao:** subir os ficheiros completos deste pacote ao repo, publicar Worker via Wrangler, adicionar segredo `CV_AI_TOKEN`, apontar o plugin WooCommerce ao Worker e testar uma lista com um produto. A publicacao de resultados no WooCommerce requer confirmacao manual e verifica a data de alteracao do produto. O token do MCP e separado (`CV_MCP_BRIDGE_TOKEN`). Nao desativar MCP legado ate paridade funcional.

Ao mudar loja.chavevertical.com para chavevertical.com, atualizar URL de origem do Worker e URL do endpoint MCP.


## Regra oficial de categorias (10/10/2026)

A fonte de verdade das categorias é a **taxonomia product_cat atual** do WooCommerce em loja.chavevertical.com, não o ficheiro `categorias_llm_compact.json`. O Core 0.2.3 fornece:

- `CHAVE VERTICAL > Categorias autorizadas`: seleção de ramos autorizados independentemente para **MCP** e **AI Enricher**. Vazio = todas as categorias vivas, exceto Predefinido.
- `GET /wp-json/cv-catalog/v1/categories?scope=mcp` ou `scope=enricher`: leitura da árvore real, incluindo categorias vazias, IDs, pais e caminhos, sem cache entre pedidos.
- `POST /wp-json/cv-catalog/v1/validate`: validação autenticada de categorias ou de um produto, assinatura para deteção de alterações na hierarquia.
- Validação de categorias inexistentes, ramos não autorizados e `Predefinido`, com nove testes automatizados no GitHub Actions.

Este núcleo já está no `main` pelo PR #7 e passa pelo deploy de plugins internos do repositório. Os plugins **CV AI Enricher** e **CV MCP Bridge** da versão 0.2.0 usam a classe central `CV_Core_Catalog_Rules`, mas estão separados do deploy automático e ainda necessitam de configuração/instalação. O antigo plugin **Gemini Enricher 4.10.2** mantém o seu mecanismo de categorias até à migração; a instalação do Core por si só não altera esse plugin nem modifica produtos.

Ao criar propostas com o Worker, a versão da taxonomia aplicada a cada produto é assinada e validada novamente no WordPress antes da escrita. O Worker nunca cria nem atribui categorias fora da árvore.
