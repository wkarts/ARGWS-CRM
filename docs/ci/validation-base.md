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

Proposta de package: `ghcr.io/wkarts/argws-crm-ci-php`, separado de
`argws-crm`. Proposta de tags: `php8.1-<hash-inputs>-<run-id>` e
`php8.5-<hash-inputs>-<run-id>`. O sufixo de execução evita sobrescrever uma tag
quando a base/pacotes de segurança mudarem com os mesmos inputs de texto.
Após publicação autorizada e smoke do artefato publicado, registrar os **digests
remotos reais** e consumir `ghcr.io/wkarts/argws-crm-ci-php@sha256:...` nos jobs.
Não consumir `latest` nem inventar digest antes do build/push.

A visibilidade **pública** é proposta para permitir pull em PRs sem credenciais.
O GitHub pode criar packages privados por padrão; é preciso verificar/configurar
visibilidade explicitamente antes de depender disso. O workflow não configura visibilidade automaticamente; confirmar pull anônimo depois
do push. A publicação foi autorizada para este package público de ferramentas,
mas não foi executada localmente. Não há `pull_request_target`.

Bootstrap temporário sem merge: somente push no repo `wkarts/ARGWS-CRM`, branch
`fix/crm-post-call-integration`, com mensagem de commit **exatamente**
`ci: publish approved validation bases`. O job `publish` depende de todos builds,
carrega o mesmo tar testado do run atual e repete smoke antes do login. Somente esse
job tem `packages: write`; GITHUB_TOKEN entra apenas no step de login. Nenhum segredo
é passado ao build. Tag inclui hash dos inputs, run-id e tentativa para não
sobrescrever. Recibo inclui digest GHCR real, SHA fonte e versão PHP.
**Remover o trigger da branch e a condição bootstrap após obter/pinar recibos.**
Fluxo permanente: main/pull_request só compilam/testam inputs; publicação exige
workflow_dispatch com `publish=true`. Não alterar branch protection nem fazer merge
para contornar a autorização. Não há deploy ou publicação da imagem do produto.

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

Local: Docker/PHP indisponíveis; build/smoke reais dependem do CI. Testes estáticos
não comprovam disponibilidade das tags base, bibliotecas ou funcionamento browser.
Nenhum push GHCR, deploy, release ou merge foi executado.
