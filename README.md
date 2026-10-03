# loja.chavevertical.com

Código personalizado da nova instalação WordPress/WooCommerce da CHAVE VERTICAL.

## Objetivo
- tema próprio muito leve, sem theme builder;
- identidade visual inspirada em `astro.chavevertical.com`;
- WooCommerce nativo;
- mínimo JavaScript e mínimo número de plugins;
- catálogo será sincronizado/importado numa fase posterior.

## Estrutura
- `wp-content/themes/chavevertical-lite/` — apresentação da loja.
- `wp-content/plugins/chavevertical-core/` — reservado a regras comerciais e integrações que não devem depender do tema.

Não inclui WordPress core, uploads, base de dados nem credenciais.

## ZIP e deploy automático

O workflow **Package and Deploy Chave Vertical Lite** valida o PHP e gera o ZIP instalável em cada push na branch `main` que altere o tema, o workflow ou `ops/deploy-theme.sh`. Também pode ser executado manualmente em **Actions → Run workflow**, na branch `main`.

O job `deploy` usa o runner `loja-chavevertical-codex1` já instalado no servidor `164.132.44.90`, como utilizador `codex1`. Não precisa de secrets SSH. Instala exatamente o ZIP validado em `/home/chavevertical-loja/htdocs/loja.chavevertical.com/wp-content/themes/chavevertical-lite/`, com permissões de leitura/escrita para o grupo da loja, e verifica o conteúdo copiado.

Antes de atualizar um tema existente, guarda um backup privado em `/home/codex1/backups/github-theme/`. Este diretório resolve para os backups da conta da loja. Os backups ficam na VPS e não têm eliminação automática. Para recuperar, extrair o backup escolhido e sincronizar apenas a pasta `chavevertical-lite` para o destino, preservando as permissões do grupo.

O deploy não ativa o tema nem altera WordPress core, plugins, uploads ou base de dados. A ativação é feita separadamente no WordPress. O ZIP fica disponível nos artefactos de cada execução durante 30 dias.

O workflow só executa no repositório original e na branch `main`; não tem triggers de pull requests. Como o repositório é público e o runner tem acesso à loja, não adicionar workflows que executem código de forks neste servidor. Quem pode alterar código em `main` tem acesso através do runner à conta da loja.
