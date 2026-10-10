# CV AI Enricher — Cloudflare / Chave Vertical

O Worker utiliza a D1 **cv-ai-enricher-db** em jurisdição EU e a Queue **cv-ai-enricher-jobs** (DLQ `cv-ai-enricher-dlq`). WooCommerce é a fonte oficial de produtos e da árvore `product_cat`.

**Worker de produção:** https://cv-ai-enricher.chavevertical.workers.dev

**Segurança:** `CV_AI_TOKEN` é um segredo Cloudflare. O valor não está no GitHub e não está emparelhado com o WordPress nesta instalação passiva. Para utilizar o Worker com plugins ativos, configurar um mesmo segredo em Cloudflare e WordPress através de um canal seguro, **nunca** num commit.

**Guardas por defeito:** `ENABLE_AI_JOBS=false`, `ENABLE_PRODUCT_APPLY=false` na Cloudflare; em WordPress, `CV_CFAI_ALLOW_PRODUCT_APPLY` e `CV_MCP_BRIDGE_ALLOW_WRITES` não definidos. Não há alterações automáticas a produtos.

Os plugins v0.3 são instalados no Woo por `.github/workflows/cv-ai-mcp-install-inactive.yml` **sem ativação**. A comunicação WordPress -> `/health` é verificada por `.github/workflows/cv-ai-woo-connectivity.yml`. O teste público de permissões usa `.github/workflows/cv-ai-worker-live-smoke.yml`.

### Próxima fase controlada
Emparelhar credenciais (Cloudflare e WordPress), testar API autenticada em ambiente de teste, ativar apenas a interface que passou nas verificações e preparar uma proposta de normalização sem a aplicar. Não desligar o MCP antigo enquanto a sua paridade funcional não estiver comprovada.

### Estrutura D1
As tabelas `lists`, `list_items`, `jobs` foram criadas na D1 e permanecem vazias. `schema.sql` documenta a estrutura para recuperação. Evitar reexecutar alterações destrutivas.
