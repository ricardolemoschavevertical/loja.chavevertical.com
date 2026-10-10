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


## Estado real da instalação — 10/10/2026

A instalação em produção é **passiva / sem ativação**. Verificações atuais:
- Plugins novos `cv-cloudflare-ai-enricher` **v0.3.0** e `cv-mcp-bridge` **v0.3.0** adicionados ao repositório; deploy separado em `.github/workflows/cv-ai-mcp-install-inactive.yml` para os copiar para `wp-content/plugins`, mantendo `status=inactive`.
- Cloudflare Worker `cv-ai-enricher` **v0.3.0** publicado em `https://cv-ai-enricher.chavevertical.workers.dev` com Workers AI, D1 `cv-ai-enricher-db` (jurisdição EU), Queue `cv-ai-enricher-jobs` e DLQ `cv-ai-enricher-dlq`.
- Worker tem token secreto próprio em **Cloudflare Secrets**; não está gravado no GitHub. A mesma credencial **ainda não foi emparelhada com o WordPress**.
- `ENABLE_AI_JOBS=false`, `ENABLE_PRODUCT_APPLY=false` na Cloudflare; `CV_CFAI_ALLOW_PRODUCT_APPLY` e `CV_MCP_BRIDGE_ALLOW_WRITES` não definidos no WordPress: as escritas continuam bloqueadas por múltiplas camadas.
- `/health` responde 200 após configuração; sem token, `GET /v1/lists` responde 401. Os endpoints dos novos plugins WordPress respondem 404 enquanto estes estão inativos (esperado).
- As categorias são sempre validadas pela classe `CV_Core_Catalog_Rules` na taxonomia `product_cat`, nunca por JSON estático.
- O MCP legado **não foi desativado nem substituído**.
- Após a validação do deploy, guardar no WP apenas `cv_cfai_settings.worker_url`, sem guardar token na BD. O token deverá ser emparelhado por canal seguro (segredo de configuração, nunca em GitHub) durante a futura ativação controlada.

**Atenção:** o conjunto de ferramentas do MCP novo v0.3.0 é deliberadamente limitado nesta instalação inicial e não substitui integralmente a API MCP antiga. A normalização e aprovação de produtos não foi testada de ponta a ponta porque os novos plugins WordPress permanecem desativados por pedido explícito.

Os ficheiros antigos v0.2.0 distribuídos anteriormente por ZIP são uma versão preliminar; a versão instalada passivamente no repositório é a v0.3.0 com mecanismos adicionais de falha segura.
