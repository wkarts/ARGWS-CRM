# GHCR e pré-release — ciclo beta 4.0.3

## Contrato

A aplicação continua **4.0.3**. A única tag GHCR escrita é
`ghcr.io/wkarts/argws-crm:develop-beta`, substituindo o destino antigo `:4.0.3`.
Qualquer imagem `:4.0.3` existente é preservada, assim como `:latest`, `:develop`,
main/develop, tags estáveis, dados, PWA e identidade V2. Sem SaaS.

Uma única pré-release `v4.0.3-beta` recebe SHA atual, digest multiarch e link para
os fontes atuais. `prerelease: true` e `make_latest: "false"` são explícitos em
POST/PATCH. A tag Git anotada dedicada acompanha o SHA publicado; os downloads
Source code dessa pré-release acompanham o mesmo código da imagem. Consumidores
que exigem imutabilidade devem fixar SHA/digest, pois este é um canal beta contínuo.
Nunca mover tags estáveis. Criação da referência é atômica create-only; atualização
usa `git push --force-with-lease=refs/tags/v4.0.3-beta:OBJETO_ANTERIOR`, comparando o
objeto anotado remoto observado, não apenas o commit resolvido. Não usar force
sem lease nem PATCH force de refs pela API.

## Gatilhos e permissões

PR para develop-beta valida versão, regressões PWA/V2, build e smoke linux/amd64
e linux/arm64 sem login/publicação. Push em develop-beta executa os mesmos gates,
publica builds por digest, promove o índice e depois atualiza a pré-release.
Sem workflow_dispatch, gatilhos por tags ou alterações nos workflows estáveis.
Somente o job publish recebe contents:write; packages:write permanece nos jobs
de publicação. GH_TOKEN e GHCR_TOKEN recebem exclusivamente `${{ github.token }}`.
Não substituir por PAT/token externo: o evento release:published do deployer
não filtra beta em validate/build. O GITHUB_TOKEN de Actions impede o encadeamento
normal desses eventos em outros workflows. O script recusa execução de release
fora de Actions e tokens diferentes; essa checagem é um contrato operacional,
não introspecção criptográfica da origem do token.

## Proveniência, colisão e retry

Índice GHCR: versão 4.0.3, canal develop-beta, SHA e amd64/arm64 obrigatórios.
Somente 404 MANIFEST_UNKNOWN é ausência; auth, rate-limit e rede falham fechados.
Imagem sem proveniência bloqueia; SHA anterior precisa ser ancestral. O HEAD
remoto é reconferido antes da promoção. Retry do mesmo SHA reutiliza seu digest
já publicado mesmo se um rebuild produziu digests diferentes.

Tag Git: anotação JSON exata com publisher, repositório, canal, versão e SHA;
objeto commit, versão e ancestralidade conferidos antes de qualquer reutilização.
O objeto anotado novo é criado localmente por git mktag e promovido somente por
CAS; não depende de fetch de objeto órfão criado pela API.
Release: exige tag própria, prerelease publicada, nome/tag corretos, nenhuma
asset externa e marcador JSON com SHA/digest. Release estável ou sem marcador
nunca é adotada. SHA anterior deve ser ancestral da tag observada, que deve ser ancestral do novo.
Isso permite reconciliar uma interrupção após CAS da tag e antes das notas.
Mesmo SHA com digest divergente na release bloqueia para investigação.
A confirmação lê a referência Git real: `target_commitish` é apenas indicativo
quando a tag já existe, conforme a [API GitHub](https://docs.github.com/en/rest/releases/releases).

Antes da promoção GHCR, preflight GitHub detecta colisões. Antes da escrita da
release, releitura detecta mudanças desde o preflight. Criação de tag interrompida
pode deixar objeto órfão ou tag válida sem release; retry verifica e completa.
Se CAS ocorreu antes de falhar a atualização da release, Source code pode ficar
temporariamente à frente das notas. A execução falha, registra o digest da imagem
e exige retry para reconciliar; não declara publicação completa nesse intervalo.
POST/PATCH não têm retry automático: resposta incerta exige nova leitura. Após
escrita, release é lida e verificada. Falha de release após promoção GHCR preserva
a imagem; retry completa notas sem reconstruir/reapontar o mesmo SHA. Nunca há
rollback automático, exclusão ou alteração estável em resposta a falha.

Runs beta são serializados, sem cancelamento. GHCR e PATCH de release não oferecem
CAS nesta integração; releituras não são lock contra escritor externo. Não usar
publicadores concorrentes manuais nesse canal. O artifact beta-publication.json
(registro GHCR salvo antes da release e enriquecido com URL após confirmação)
é retido por 90 dias. Consumidores fixos usam `IMAGE@sha256:...`.

## Publicação sem merge: investigação em 2026-11-05

Base: 75b29a0bea50faebee251822f989ed7a40cb1bd9, PR58 já mesclada externamente.
Consulta GHCR anônima: :4.0.3 e :develop-beta ausentes (MANIFEST_UNKNOWN); nenhuma tag/release beta
na consulta GitHub. Estas observações não substituem preflight em execução.

Publicação atual está autorizada, merge não. Contudo beta-container.yml não existe
na default branch main (Contents API 404); adicionar dispatch somente na branch
da PR não disponibiliza o workflow na default. O ambiente não possui credencial
GitHub/GHCR de publicação configurada; gh auth status falhou e gh api recebeu
Forbidden em GET /repos/wkarts/ARGWS-CRM. O conector GitHub permite preparar PR,
mas não oferece dispatch nem criação de release; GET actions/workflows e GET
actions/workflows/beta-container.yml também foram recusados pelo conector como
endpoint não permitido (HTTPError 400). Não houve tentativa de POST dispatch sem
um workflow despachável/credencial disponível. Não houve publicação por este trabalho. É necessário um
caminho Actions autorizado e disponível na default com esse workflow, ou ambiente
com credenciais temporárias apropriadas e revisão dos efeitos release:published;
não alterar main nem mesclar a PR para contornar esse bloqueio.

## Versão, validação e rollback

Delta limitado à allowlist BETA_INFRA_PATHS com base develop-beta; VERSION,
constantes, distribuição, migrations, produto e assets permanecem inalterados.
O planner estável continua estrito. Validar:

```
python3 -m unittest tests/test_release_version.py tests/test_package_release.py tests/test_beta_container.py
bash scripts/check-release-version.sh --base BASE_SHA --head HEAD_SHA --base-ref develop-beta
```

CI mantém tracking PHP, branding, PWA HTTP/Chromium, build/smoke das duas
arquiteturas. Smoke usa PHP com rede desligada e sem inicializar banco. Mocks de
publicação não comprovam publicação real. Reverter configuração requer PR;
consumidores podem fixar digest anterior aprovado. Reapontar canal para versão
anterior exige autorização própria; não apagar imagens/tags nem reverter banco.

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
