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

Na primeira release, sem tag-base anterior, o incremental usa o primeiro pai do commit de release como base. Para releases seguintes, ele compara com a tag estável imediatamente anterior.

Antes de atualizar, faça backup do banco, da configuração local e dos uploads. Preserve `application/config/app-config.php` e os dados enviados pelos clientes. Aplique o incremental sobre os arquivos existentes e execute a migration informada no painel; instale a imagem FrankenPHP com ARGWS_VERSION=X.Y.Z e Compose quando usar container.

Os pacotes não incluem credenciais, .env, configuração local, logs, cache ou uploads de clientes. Amostras genéricas e imagens de placeholder necessárias ao produto permanecem na distribuição.
