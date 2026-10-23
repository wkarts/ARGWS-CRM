# Base dedicada de validação (não é imagem do produto)

## Conteúdo e contratos

`docker/ci` é o **único contexto Docker**: Dockerfile, dois manifests npm e dois
smokes. Nenhum código CRM, configuração de ambiente, certificado, banco, token ou
segredo é copiado para a imagem. A matriz conserva PHP 8.1 e 8.5. Ela inclui as
extensões atuais pdo_mysql/mysqli/gd com JPEG/mbstring, Python, Poppler, Git e Node22.
O lockfile npm dedicado foi gerado pelo npm, contém Playwright **1.55.1** e seu
Chromium pareado; não instala as centenas de dependências frontend da aplicação.

- `NODE_PATH=/opt/ci-browser/node_modules`
- `PLAYWRIGHT_BROWSERS_PATH=/opt/ci-browsers`
- `argws-ci-smoke` exige `EXPECTED_PHP=8.1` ou `8.5`, verifica extensões/JPEG,
  ferramentas e DOM sintético com rede bloqueada. Timeout de browser permanece30s.
- Banco MySQL não entra na imagem: cada job/shard usa serviço descartável separado.
- Os testes da PR e do push pós-merge devem executar novamente, cada um em seu
  contexto de código. Compartilhar a imagem não reutiliza resultados de testes.

## Build, teste e publicação

O workflow `ci-validation-base.yml` compila/testa ambos PHP em mudanças dos inputs
relevantes. O job build tem apenas `contents: read`, `push: false`, nenhum login ou secret.
Também aceita dispatch para atualizar bases/pacotes de sistema por segurança.
Ele registra SHA do código, hashes dos inputs e ID da imagem realmente construída.
**ID local de imagem não é digest GHCR de manifest**, portanto não deve ser usado
como se fosse referência remota publicada.

Package publicado: `ghcr.io/wkarts/argws-crm-ci-php`, separado de
`argws-crm`. Formato das tags: `php8.1-<hash-inputs>-<run-id>-<attempt>` e
`php8.5-<hash-inputs>-<run-id>-<attempt>`. O sufixo de execução evita sobrescrever uma tag
quando a base/pacotes de segurança mudarem com os mesmos inputs de texto.
Após publicação autorizada e smoke do artefato publicado, registrar os **digests
remotos reais** e consumir `ghcr.io/wkarts/argws-crm-ci-php@sha256:...` nos jobs.
Não consumir `latest` nem inventar digest antes do build/push.

A visibilidade **pública** foi autorizada para permitir pull em PRs sem credenciais.
O GitHub pode criar packages privados por padrão; é preciso verificar/configurar
visibilidade explicitamente antes de depender disso. O workflow não configura visibilidade automaticamente; confirmar pull anônimo depois
do push. A publicação foi autorizada para este package público de ferramentas,
mas não foi executada localmente. Não há `pull_request_target`.

## Publicação concluída e bootstrap removido

O run [40033441190](https://github.com/wkarts/ARGWS-CRM/actions/runs/40033441190)
publicou as duas imagens a partir de `b6c023bec16a1289c0f658b9eb4e6cbc486dbd6d`.
Os recibos autênticos estão em [validation-images.json](validation-images.json):

- PHP8.1: `sha256:c6246e2740c225e7db4f0db2b8ba8657fb72cb9638efdedcfe1a8853fc081a14`
- PHP8.5: `sha256:d050af83a621363bdc79e5868f85d7203e8b5fb03be6cd37fbb7c41500894677`

A interface do [package](https://github.com/users/wkarts/packages/container/package/argws-crm-ci-php)
foi conferida pelo integrador e informa que ele é **público**. Isso não substitui
um pull anônimo: esse teste de consumo continua pendente no CI. Os recibos originais
mantêm `visibility: must-verify-anonymous-pull` para não inventar essa evidência.

A exceção temporária da branch e a autorização por mensagem de commit foram
removidas. Fluxo permanente: main/pull_request só compilam/testam os inputs;
publicação exige `workflow_dispatch` com `publish=true` (padrão false), no repositório
exato. O job publish depende de todos builds, carrega o mesmo tar testado do run e
repete smoke antes de login. Somente esse job possui `packages: write`. Não existe
publicação por push, nem `pull_request_target`, deploy ou alteração de produção.

## Atualização e rollback

Rebuild por Dockerfile/manifest/lock/smokes/workflow, atualização explícita por
segurança ou nova minor PHP. Resolver e registrar digest das bases efetivamente
usadas no build final. Bases e pacotes apt são fontes móveis durante a compilação;
a imutabilidade oferecida ao consumidor é o digest da imagem final revisada.
Rollback troca somente o digest CI por um anterior validado; nunca imagem/VPS do
produto, banco ou configuração de produção.

## Evidência e limites

No run39996938632, setup levou aproximadamente um minuto, enquanto MySQL levou
87m14s no PHP8.1. Esta imagem só reduz setup repetido (~1% daquele caminho crítico).
Sharding isolado e observabilidade da suíte são alterações separadas que conservam
os comandos/cenários. Não prometer divisão linear do tempo antes da medição.

Local: Docker/PHP indisponíveis. Build/smoke/publicação foram executados pelo CI
no run documentado acima; testes estáticos locais não substituem essa evidência.
Consumo anônimo e a suíte completa com os digests fixados ainda precisam passar.
Nenhum deploy, release do produto ou merge foi executado.
