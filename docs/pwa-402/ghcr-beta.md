# GHCR — ciclo beta 4.0.3

## Decisão e escopo

Wallace autorizou **uma única tag de uso**, `ghcr.io/wkarts/argws-crm:4.0.3`,
mutável durante o ciclo beta. Builds aprovados substituem seu apontamento;
cada conteúdo continua identificado pelo digest e SHA. Não há tag adicional
`:develop-beta`, nem escrita em `:latest`, `:develop`, `:4` ou `:4.0`.
Não confundir 4.0.3 beta mutável com uma versão estável imutável.

A PR #57 foi mesclada externamente antes desta infraestrutura. A PR de continuação
parte de `b693149f22b1681fdc38ebd941f0b8a8c9a4ab1e`, que já contém 4.0.3.
Esta entrega configura publicação futura; **nenhuma imagem, tag Git, release ou
deploy foi publicada/executada por este trabalho**. Merge continua não autorizado.

## Gatilhos e validação

- PR para `develop-beta`: validações, build e smoke linux/amd64 e linux/arm64;
  push:false, sem login GHCR ou packages:write nos jobs executados.
- Push em `develop-beta`, após integração futura autorizada: validação e preflight,
  build por digest das duas arquiteturas, smoke e promoção somente de `:4.0.3`.
- Sem gatilho por Git tag ou workflow_dispatch. Sem configuração nos canais main
  ou develop. Sem criação de Git tags/GitHub releases ou imagens de dependências.
- A versão é validada contra VERSION, constante, migration e distribuição.
  O publicador exige exatamente 4.0.3; encerrar o ciclo requer revisão explícita.

Gates: testes de versão/empacotamento/publicador, tracking nativo descartável,
branding, HTTP PWA e Chromium precedem os builds. O smoke lê versão/migration,
manifesto e ícones na imagem, com entrypoint PHP e rede desabilitada, sem iniciar
provisionamento nem banco. Falha em qualquer arquitetura impede promoção da tag.
Builds de push podem deixar conteúdo sem tag caso o smoke falhe; não publicam
um novo apontamento da tag de uso antes da aprovação dos dois smokes.

## Política de versão para esta infraestrutura

A autorização de manter 4.0.3 no ciclo beta é explícita. O gate agora distingue
somente o delta de infraestrutura allowlisted (`BETA_INFRA_PATHS`) com base
`develop-beta`, versão base/head 4.0.3, arquivos regulares e metadados/distribuição/
migrations históricos byte a byte e modo inalterados. Não é bypass por pasta ou
extensão. Produto, assets, módulos, mudanças mistas, exclusões, symlinks, outras
bases e versões continuam no contrato de incremento original.

A CLI recebe o nome real da base via evento GitHub. Sem `--base-ref develop-beta`,
o contrato de release continua estrito, assim como o planner estável. O teste
apresenta casos positivos e negativos; o gate continua executando coerência,
preservação histórica e tracking. Não é uma validação de publicação estável.
AGENTS.md e CONTRIBUTING.md registram a regra autorizada.

## Mutabilidade, concorrência e rastreabilidade

O publicador só aceita ausência com HTTP 404 + MANIFEST_UNKNOWN. Auth, rate limit,
proxy, timeout, erro de digest ou outro erro bloqueiam. Uma imagem existente sem
proveniência beta esperada é preservada e bloqueia a escrita. Uma imagem beta
anterior só pode ser substituída se seu SHA for ancestral do novo SHA aprovado.
Retry do mesmo SHA preserva o digest existente, mesmo após interrupção posterior
à escrita; não troca a tag por um rebuild diferente do mesmo commit.

O HEAD remoto é conferido antes das escritas; build obsoleto falha. Uma releitura
da tag detecta alterações concorrentes antes da escrita. Runs de push beta são
serializados sem cancelamento; PRs têm grupo separado. Nunca reverte tags após
uma corrida/falha: verifica o estado e exige retry consciente.

`beta-publication.json` registra imagem, digest/SHA novos e digest/SHA anteriores;
o workflow conserva esse artifact por 90 dias e imprime o registro no log.
Índice publicado contém annotations de versão, SHA e canal. Consumidores que
precisem conteúdo fixo devem usar `ghcr.io/wkarts/argws-crm@sha256:...`.
Digest não é garantia de retenção eterna pelo registry; não há rotina de limpeza
ou exclusão de imagens nesta configuração.

GHCR não oferece nesta integração uma escrita condicional atômica de tags.
Serialização/releituras protegem os runs deste fluxo, mas não são lock contra
publicador externo/manual. Não permitir escrita concorrente externa em :4.0.3.
Os workflows estável/develop permanecem inalterados.

## Evidências e operação

Consulta inicial: `docker manifest inspect ghcr.io/wkarts/argws-crm:4.0.3`
retornou `manifest unknown`; não havia Git tag v4.0.3. Observação pontual: o
workflow sempre consulta novamente antes de publicar.

Dependências: Dockerfile e bases FrankenPHP/Composer existentes, Buildx,
GITHUB_TOKEN packages:write somente nos jobs de push. Nenhuma credencial ou dado
real é incorporado; sem sincronização de bases/mysql/mariadb.

Testes: `python3 -m unittest tests/test_release_version.py tests/test_package_release.py tests/test_beta_container.py`;
actionlint dos workflows; `bash -n scripts/smoke-beta-image.sh`; CI executa builds
e smokes reais. Publicação remota não homologada por restrição expressa.

Rollback de configuração: reverter workflow/scripts antes de autorizar novos
pushes. Não apagar imagens. Reapontar :4.0.3 para digest anterior é promoção
manual que requer autorização específica; não há rollback de banco.

## Reconciliação visual com a 4.8.7

A PR 58 herda o PWA e a identidade V2 da PR 57, já mesclada em develop-beta.
Esses arquivos não reaparecem no diff da PR de infraestrutura. A comparação
com `1e5a4a1e64ef230f3f996ebe871dc3c20943829d` confirmou os mesmos bytes nos
logos/favicons de `assets/images/argws/` e em `assets/pwa/` (a licença tem apenas
normalização de espaço final). O commit V2 original foi `6a4e94b8`.

A auditoria encontrou uma omissão real: `install/html.php` usa `install/logo.png`,
que ainda era a imagem antiga. O arquivo agora usa os mesmos bytes V2 de
`assets/images/argws/logo-dark.png`, SHA-256
`6662059de4b99547756c8c7c8654ecf1f505220f9297efdc5723ce5b32d46cd9`.
Esse é o instalador legado distribuído no código/ZIP. O instalador Docker `/setup`
é outro caminho: já referencia `logo-light.png` V2; `install/` não entra na imagem.
Não se alterou o provisionamento, formulários, credenciais ou banco.

A conclusão deste porte mantém 4.0.3 por decisão explícita para o beta. O gate
aceita somente a transição exata de bytes antigos para os aprovados em
`BETA_V2_RESTORATIONS`, modo 100644, base e ancestral conferidos; não libera
arbitrariamente assets nem código funcional. Testes cobrem bytes incorretos,
preimagem divergente, symlink, exclusão, modo e outras branches. O planner de
release estável continua estrito. Este complemento não publica imagens/tags.

Os fluxos de login/aplicação usam os helpers originais e os logos públicos V2,
sem substituir personalizações explícitas da instalação. Não foram portadas
as funcionalidades e estruturas de contexto empresarial da 4.8.7. Instalar
um aplicativo PWA pelo navegador e provisionar o CRM são operações distintas.

O QA PHP/Chromium reproduziu transbordamento do PNG V2 de 2400px no instalador
legado. `install/html.php` limita apenas essa imagem a 320px, máximo 100% do
contêiner e altura automática, com texto alternativo. A exceção do gate inclui
somente a transição de hashes desta view para esse ajuste, sem liberar alterações
arbitrárias no instalador. Os fluxos/formulários permanecem iguais.
