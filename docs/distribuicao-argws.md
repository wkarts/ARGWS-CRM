# Distribuição ARGWS

## Canais e versionamento

O fluxo usa os branches `develop` e `main`:

- Pull requests fazem as validações e compilam a imagem FrankenPHP de teste sem publicá-la.
- Pushes em `develop` publicam a imagem multi arquitetura `ghcr.io/wkarts/argws-crm:develop`.
- A promoção de `develop` para `main` calcula a próxima SemVer automaticamente e publica a imagem estável no GHCR, os ZIPs e a release do GitHub.
- A primeira versão é o SemVer já configurado em `VERSION`; para esta primeira publicação, ela é `3.4.2` e a tag é `v3.4.2`.
- Depois da primeira tag, títulos de PR convencionais determinam o incremento automático: `feat:` gera minor, correções e os demais tipos geram patch, e `!:` ou `BREAKING CHANGE` gera major. Os rótulos `version:patch`, `version:minor` e `version:major` podem definir o incremento. Uma execução manual também aceita `auto`, `patch`, `minor` ou `major`.
- A migration do banco é versionada de forma independente. Uma release patch/minor/major não altera `migration_version` sem uma migration de schema correspondente.

Após cada release, o fluxo sincroniza os campos de versão de produto em `develop` sem substituir outras alterações e solicita uma nova publicação da imagem `:develop`.

## FrankenPHP e GHCR

`.github/workflows/container-publish.yml` valida as alterações e publica somente no canal de desenvolvimento. O fluxo da branch `main` fica centralizado em `.github/workflows/release-packages.yml`: calcula a versão, gera imagens por arquitetura, combina os manifestos para `linux/amd64` e `linux/arm64`, e cria as tags estáveis:

- `3.4.2` (versão completa)
- `3.4` (major.minor)
- `3` (major)
- `latest`
- `sha-<commit>`

Os valores de exemplo acima correspondem à primeira release; as próximas tags são calculadas pela versão planejada. Banco, chave de criptografia, URL, configuração e uploads específicos continuam fora da imagem.

Os builds usam caches separados por canal e arquitetura. `.github/workflows/build-cache-cleanup.yml` remove somente caches ARGWS CRM antigos, protege tags e execuções ativas e começa em simulação quando acionada manualmente. A limpeza de cache não remove tags estáveis nem imagens GHCR.

## Pacotes PHP e atualização

A release publica `ARGWS-CRM-X.Y.Z-full.zip`, `ARGWS-CRM-X.Y.Z-update.zip`, checksums SHA-256 e um relatório dos manifestos GHCR. O pacote completo serve para instalação nova; o incremental inclui arquivos novos/alterados desde a última tag SemVer e lista caminhos de código removidos em `REMOVED-FILES.txt`.

Na primeira release, sem tag-base anterior, o incremental compara com o commit legado registrado em `.github/first-release-base.txt`, que é a base do PR inicial de migração para ARGWS. Nas releases seguintes, compara com a tag estável imediatamente anterior. `.github/workflows/repair-release-assets.yml` permite reconstruir os pacotes de uma tag já publicada e substituir os anexos validados sem criar outra versão.

Antes de atualizar, faça backup do banco, da configuração local e dos uploads. Preserve `application/config/app-config.php` e os dados enviados pelos clientes. Aplique o incremental sobre os arquivos existentes e execute a migration informada no painel; instale a imagem FrankenPHP com ARGWS_VERSION=X.Y.Z e Compose quando usar container.

Os pacotes não incluem credenciais, .env, configuração local, logs, cache ou uploads de clientes. Amostras genéricas e imagens de placeholder necessárias ao produto permanecem na distribuição.


## Deploy por ambiente e dependências GHCR

O repositório contém stacks em deploy/develop e deploy/production. Elas combinam a aplicação FrankenPHP com MySQL/MariaDB persistente e mantêm o .env específico de cada instalação fora da imagem. As stacks passam as credenciais de banco do .env ao container. A imagem de produção não contém o diretório web install; o primeiro schema e o usuário administrador são criados uma única vez pelo provisionador PHP CLI, sem formulário público.

ghcr.io/wkarts/argws-crm-base:1-php8.3-bookworm espelha a base FrankenPHP do build. ghcr.io/wkarts/argws-crm-mysql:8.0 e ghcr.io/wkarts/argws-crm-mariadb:11.4 fornecem os bancos das stacks. Os workflows preservam tags existentes; sincronização semanal não substitui imagens. Atualizações deliberadas usam a execução manual refresh_existing=true.

O CRM declara MySQL/MariaDB. PostgreSQL e Redis não são dependências ativas configuradas ou testadas e não são adicionadas à stack.

O binário Rust argws-crm-deployer gera compose.yaml e .env localmente e preserva um .env existente. deploy/ e tools/argws-crm-deployer/ ficam fora dos ZIPs de aplicação. O binário é anexado como asset separado às releases.


## Retenção e limpeza de cache

Após uma publicação bem-sucedida em `develop` ou `main`, `actions-cache-retention.yml` verifica o SHA publicado, a branch e a ausência de workflows ativos. Remove somente caches do GitHub Actions ligados exatamente a essa branch que estejam sem uso há mais de 30 dias. Caches recentes, refs de PR, tags, releases e imagens GHCR são preservados. A execução manual gera relatório sem excluir por padrão; `apply=true` é explícito. Os builds usam Buildx com cache do Actions; os runners hospedados são descartados ao fim do job e não exigem `buildx prune`.
