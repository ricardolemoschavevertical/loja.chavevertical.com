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


## Atualização 10/10/2026 — WooCommerce como painel central (v0.4.0)

O painel de gestão foi implementado **no WordPress**, seguindo a organização funcional do WooCommerce Gemini Enricher 4.10.2. A Cloudflare não é o painel administrativo: executa a fila, chama o motor selecionado e armazena as listas/propostas na D1.

**Plugins em produção (ativos):**
- `CV AI Enricher — Cloudflare` v0.4.0, menu **WooCommerce → CV AI Enricher**, com separadores Painel, Motores e API Keys, Listas e Fila, ChatGPT/MCP, Cloudflare/Ligação.
- `CV MCP Bridge` v0.4.0, endpoint `/wp-json/cv-mcp/v1/mcp`, autenticação Bearer com hash em opções Woo e escrita desligada por defeito.
- A versão **4.10.2 do plugin original foi usada como referência funcional**, mas a verificação de plugins no WordPress não a encontrou instalada com o antigo slug. A importação de contas Gemini lê `wcge_db_keys` apenas quando o administrador clicar explicitamente. **Não há OAuth no novo Bridge** e ainda não existe paridade de todas as ferramentas antigas; não remover ligações ChatGPT existentes.

**Credenciais e modelo geridos no WooCommerce:**
- O administrador introduz temporariamente um **API Token Cloudflare limitado** para operações de segredo. O token não é guardado no WordPress nem no GitHub.
- Pode introduzir até 20 contas Gemini no formato do plugin antigo (`CHAVE | rótulo | free/paid`) ou importar os valores já existentes.
- As chaves são sincronizadas para Cloudflare `GEMINI_API_KEYS_JSON` (Secret). O WordPress guarda só metadados sem as chaves.
- O fornecedor, modelo, estratégia de rotação e ativação da fila são enviados como `CV_AI_CONFIG_JSON` (Secret); o Worker lê estas opções em tempo de execução.
- O emparelhamento WooCommerce ↔ Cloudflare é gerido no painel: o token partilhado é gerado automaticamente e guardado **cifrado** na opção `cv_cfai_worker_auth_cipher_v1`, usando libsodium e salts WordPress. Se os salts mudarem, será necessário emparelhar novamente.
- O token MCP é gerado no WooCommerce e guardado **apenas como SHA-256** (`cv_mcp_bridge_token_sha256`), exibido uma única vez.
- `CV_CFAI_ALLOW_PRODUCT_APPLY` e `CV_MCP_BRIDGE_ALLOW_WRITES` não estão ativados: a escrita de produtos pelo novo sistema mantém-se bloqueada. A fila Gemini só pode ser ativada no Woo após confirmação explícita.

**Cloudflare Worker v0.4.0:**
- `cv-ai-enricher.chavevertical.workers.dev` continua ligado a D1 `cv-ai-enricher-db`, Queues, DLQ e Workers AI.
- Suporta Gemini multi-conta via `generateContent` e rotação em quota/429/5xx, além de Workers AI.
- Os segredos existentes, nomeadamente `CV_AI_TOKEN`, foram preservados com `bindings_inherit=strict` na publicação.
- O Worker não devolve chaves Gemini no endpoint `/health`.

**Incidente de permissões corrigido:** o primeiro upgrade v0.4 criou um diretório de includes com permissões 0700 e ficheiros PHP 0600 por herdar `umask 0077` do backup; o PHP-FPM não os podia ler e o site devolvia HTTP 500. Foram corrigidos para diretórios 0755 e PHP 0644. Verificação final: página inicial 200, REST global 200, CV AI sem token 401, CV MCP sem token 401, ambas classes carregadas, plugins ativos. Workflow: `cv-ai-safe-permissions-reactivate.yml` (run `38065937066`). O atualizador GitHub foi corrigido para aplicar `umask 0022` aos ficheiros que o servidor deve ler.

**Pendente:** emparelhamento autenticado com Cloudflare a partir do painel e configuração das chaves Gemini pelo administrador; criação de app MCP no ChatGPT, com autenticação suportada pelo cliente; eventual migração de OAuth/paridade de ferramentas antigas após testes. Não foram aplicadas alterações a produtos.
