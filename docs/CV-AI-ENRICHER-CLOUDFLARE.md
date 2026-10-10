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
