# GHCR da linha develop-beta

O workflow `beta-container.yml` é independente dos canais existentes e não cria
Git tags, GitHub releases, deploys ou imagens de dependências.

## Decisão pendente e bloqueio preventivo

Wallace está definindo se 4.0.3 permanece fixa durante o ciclo beta e a política
para a tag versionada. A estratégia imutável abaixo é proposta conservadora,
NÃO decisão final. Nenhuma publicação está autorizada nesta PR.

Preflight e jobs de push/publicação exigem a variável de repositório
`ARGWS_BETA_PUBLICATION_ENABLED` igual a `true`; ausente ou diferente disso, não
executam. A variável não foi habilitada por este trabalho. Não habilitar até
concluir a decisão e revisar a implementação. Gates e builds de PR permanecem
ativos, sem login de escrita. Não há bypass da validação de versão.

A PR #57 foi mesclada externamente antes desta infraestrutura; esta continuação
parte do merge `b693149f22b1681fdc38ebd941f0b8a8c9a4ab1e`. A base já contém 4.0.3.
AGENTS.md e CONTRIBUTING.md exigem próximo ID inclusive para CI: manter 4.0.3
nesta PR deixa o gate do núcleo reprovado por contrato. O teste explícito de delta
somente CI confirma essa exigência. Não foi implementada exceção nem incremento
presumido. A decisão do ciclo beta precisa resolver esse conflito antes do merge.

## Eventos e nomes propostos (após autorização e habilitação)

- PR para `develop-beta`: validações e build/smoke em linux/amd64 e linux/arm64,
  `push: false`, sem login GHCR e sem permissão packages:write nos jobs de PR.
- Push em `develop-beta`, somente com publicação habilitada (inclusive futuro merge autorizado): validação, preflight,
  build por digest em ambas as arquiteturas, smoke de cada imagem e publicação.
- Sem gatilho por tag Git ou workflow_dispatch. Criar uma tag não publica o beta.
- A versão é lida de VERSION e validada contra migration, constante e distribuição.
  Inicialmente `ghcr.io/wkarts/argws-crm:4.0.3`.
- `ghcr.io/wkarts/argws-crm:develop-beta` aponta para o mesmo índice multiarch
  da versão aprovada. Nenhuma escrita em `:latest`, `:develop`, `:4` ou `:4.0`.

## Gates e preservação

Testes existentes de versão/empacotamento, contratos do publicador, tracking
nativo descartável, branding, HTTP PWA e Chromium precedem os builds. Os smokes
leem versão/migration/manifesto/ícones dentro de cada imagem, com entrypoint PHP
explícito e rede desabilitada, sem iniciar provisionamento ou acessar banco.

Os builds de push usam somente digests até os dois smokes passarem. Falha pode
levar a blobs/digests sem tags no GHCR; não promove a tag de versão ou canal.
A publicação usa somente artefatos do mesmo run/SHA/versão/arquitetura.

A tag versionada é consultada antes do build e novamente antes da criação:

- ausência só é aceita com HTTP 404 e código MANIFEST_UNKNOWN;
- autenticação, 403, rate limit, proxy, timeout, digest inválido ou outro erro
  bloqueiam, sem interpretar falha como ausência;
- tag existente de outro SHA, outro canal, sem proveniência esperada ou sem as
  duas arquiteturas bloqueia; não exclui nem substitui a imagem;
- retry do MESMO SHA com índice beta já aprovado reutiliza seu digest original,
  sem sobrescrever a versão, inclusive após interrupção anterior à promoção;
- develop-beta precisa continuar no SHA construído, antes de criar a versão e
  de promover o alias. Builds obsoletos não retrocedem o canal;
- pipelines beta são serializados por branch sem cancelamento de push. PRs usam
  grupo separado e podem cancelar validação anterior.

GHCR não oferece nesta integração uma criação condicional atômica de tags.
As releituras e serialização protegem os runs deste workflow, mas não constituem
lock contra um publicador externo/manual. Evitar escrita concorrente externa nas
mesmas tags. Os workflows estável/develop não foram alterados; colisões futuras
exigem próximo ID nativo aprovado, nunca exclusão ou force da imagem existente.

## Situação e execução

Na consulta anterior à implementação, `docker manifest inspect
 ghcr.io/wkarts/argws-crm:4.0.3` retornou `manifest unknown`, e não havia tag Git
v4.0.3. Isso é observação pontual: o workflow sempre consulta novamente.

A PR apenas configura a publicação futura. Não executar o publicador nesta etapa.
Builds e testes de PR não enviam imagens ao GHCR. O fluxo existente de main gera
imagens estáveis e releases; não é usado para este canal beta.

Dependências: imagem base FrankenPHP GHCR e Composer já previstas no Dockerfile,
Buildx, GITHUB_TOKEN do Actions com packages:write somente nos jobs de push.
Não provisiona nem atualiza imagens base/mysql/mariadb e não usa dados reais.

Testes locais: `python3 -m unittest tests/test_beta_container.py` (16 casos);
`bash -n scripts/smoke-beta-image.sh`. CI executa também os builds e smokes reais.
Não há homologação de publicação remota agora, por restrição expressa.

Rollback da configuração: reverter workflow/scripts antes de autorizar futuros
pushes. Não há alterações de banco. Reverter código não deve apagar imagens já
publicadas; qualquer promoção de digest anterior exigirá autorização específica.
