# Validação local — 6/11/2026

Runtime descartável PHP8.3.36 com GD/PDO SQLite/PDO MySQL. Banco MySQL8.4 em
rede Docker isolada; credencial apenas efêmera de teste. Chromium do pacote
Debian + Playwright1.56.1. Nenhuma credencial/URL de produção foi usada.

## Executados, aprovados

```sh
php tests/native_migration_tracking.php
php tests/companies/asaas_checkout_views.php
php tests/companies/asaas_secret_controller.php
php tests/companies/asaas_preferences_controller.php
php tests/companies/asaas_card_settings.php
php tests/companies/native_settings_controller.php
php tests/native_company/presentation.php
php tests/native_navigation_parity.php
php tests/pige_bridge_admin.php
php tests/banco_brasil_admin.php
php tests/banco_brasil_cnab240.php
php tests/banco_brasil_cnab400.php
php tests/banco_brasil_store.php
node tests/browser/asaas_settings.cjs
node tests/pige_bridge_settings.browser.cjs
```

Asaas navegador: view PHP real, HTTP local, jQuery e Bootstrap distribuídos,
guias/teclado/deep links, campo inválido, parâmetros de cartão sandbox/produção,
CSRF/contexto/destino preservados e chave API não hidratada. O framework/dados
são fixtures, não login/instalação autenticada. Sem chamadas externas.
PIGE navegador: desktop/mobile, busca/paginação, cancelamento, revisão,
limpeza de token, consentimento obsoleto e escape. PHP administrativo testa
fronteiras; não há HTTP financeiro autenticado.

MySQL real, com `ASAAS_TEST_MYSQL_DSN/USER/PASSWORD` e
`ISSUERS_TEST_DSN/USER/PASSWORD` apontando exclusivamente para banco descartável:

```sh
php tests/asaas_payment_writer_mysql.php
php tests/companies/asaas_origin_policy.php
php tests/companies/asaas_explicit_reconciliation.php
php tests/companies/asaas_inflight_quarantine.php
```

Escritor:34 asserts, incluindo dois processos concorrentes, créditos e rollback.
As suites de origem/reconciliação usam serviços reais e provedor simulado. Não
somar contagens cumulativas impressas pelas dependências como testes distintos.
CNAB240:118; CNAB400:80; store:56; administrativo BB:100. Essas provas não
homologam convênio, boleto, agrupamento aberto ou baixa por retorno.

## Falhas de ambiente resolvidas / limitações

- Primeiro runtime sem GD: view falhou; instalado GD no container e suite passou.
- Download do Chromium Playwright: proxy recusou domínios CDN com403. Sem retry
  manual/bypass desses destinos; Chromium veio do repositório Debian permitido.
- SDK Composer: CA inicialmente ausente no container; corrigida montando bundle
  confiável da sessão. Downloads ZIP api.github.com foram bloqueados por403;
  não se repetiu essa operação. Testes SDK usam PHPUnit Debian, source SDK atual
  e Guzzle já disponível no vendor do CRM, com autoloader local explícito.
- Nenhuma negativa de auto-review ocorreu. As negativas acima são de rede.

## Não executados / não atendidos

- Homologação bancária BB por convênio e credenciais, API externa/cartão Asaas,
  descoberta bilateral PIGE e sessão autenticada completa da instalação.
- Reajuste em massa e corrida de lote BB até primeiro download: funcionalidades
  ainda ausentes, portanto não há teste aprovado desses requisitos.
- Comparação visual integral 4.0.2 versus base recente, todos AC01–64,
  PWA instalado e todos perfis/empresas.
- Upgrade491 sobre instalação real: marker NOOP e teste do runner não são
  comprovação de tracking/backup de uma instalação. Não houve deploy.

CI da PR deve ser consultado pelo SHA final; registrar o estado remoto na
entrega. Workflow adicionado executa o browser novo e configuração de cartão.

SDK, PR21, commit7deb034aab8792c7cd58f6128fee2e9656c269d6: PHPUnit11.5.19
executou19 testes/84 assertions do source atual contra Guzzle real com transporte
simulado. Três testes novos/18 assertions cobrem contratos de cartão, erros e
retry. CI do SDK deve confirmar PHPUnit10 e instalação Composer do projeto.

SDK CI final aprovado em7deb034aab8792c7cd58f6128fee2e9656c269d6:
[CI42149805472](https://github.com/wkarts/asaas-sdk-argws/actions/runs/42149805472)
e [Audit42149805461](https://github.com/wkarts/asaas-sdk-argws/actions/runs/42149805461).
O primeiro CI detectou tipos de arrays ausentes na fixture nova; corrigidos sem
reduzir gates. Composer/PHPUnit10 e matriz PHP8.1/8.2/8.3 passaram remotamente.

CI inicial CRM detectou inventário de isolamento desatualizado para os dois
controllers alterados. Reconciliado pelo writer oficial; auditoria local:
687 arquivos,658 unreviewed,29 partial,zero erros. Os11 testes de inventário e
armazenamento de anexos passaram. `operational_rollout` permanece `blocked`;
atualizar hashes/linhas não representa homologação de isolamento.

## Incremento 492 — reajustes

Ver [repricing.md](repricing.md) para testes novos, critérios e limites. SQLite,
MySQL 8.4 e Chromium passaram localmente, incluindo dois processos reais de
confirmação/emissão. Schema aditivo criado/repetido em banco descartável.
A falha de CI de b06b1851 foi localizada em `node-contracts`: `main.min.js` e
`tickets.min.js` divergiam da compilação reproduzível; outros gates pararam por
essa dependência. `npm ci --ignore-scripts --no-audit --no-fund` e Mix production
regeneraram os artefatos. Gates não foram removidos nem relaxados. CI do novo
SHA deve ser consultado depois do push.

## Banco do Brasil 1.3.7 — configuração nativa

Executados localmente: `php tests/banco_brasil_admin.php` (98 checks, além de118
codec/56 store), `php tests/banco_brasil_module_version.php` (54),
`php tests/banco_brasil_origination_mysql.php` (233 cumulativos em MySQL8.4,
instalação/upgrade/erro de tracking/repetição reais), `node tests/browser/bb_settings.cjs`
(view PHP real em Chromium com dados/HTTP sintéticos), lint PHP e node --check,
`python3 tests/banco_brasil_bridge125_static.py` (5 testes estáticos).
Não houve banco remoto nem homologação do convênio. Rotas dos browsers autenticados
foram atualizadas; sua execução completa permanece pendente.

CI do incremento de reajustes `05e8030038212dc959abaae5a928c0cd990d4ff7`:
[workflow de reajustes aprovado](https://github.com/wkarts/ARGWS-CRM/actions/runs/42153603040).
A primeira rodada executou os testes com sucesso, mas recusou a limpeza porque o
nome do schema não estava na lista segura do harness. O workflow passou a usar
`issuer_tests`, já permitido; a proteção de limpeza não foi alterada.

## Lotes abertos CNAB240 / módulo138

- MySQL8.4: `banco_brasil_open_batches_mysql.php` —251 verificações cumulativas, incluindo instalação/upgrade/replay/tracking, agrupamento, retry, encoder falho e arquivo legado.
- MySQL8.4: `banco_brasil_origination_concurrency.php` —260 verificações cumulativas; dois processos com espera observada no lock, emissão/download e revogação.
- PHP: module_version54; admin98, com codec118/store56 como pré-requisitos.
- Não é homologação bancária; CNAB400 e boleto não são validados por esses resultados.

## Seletores PIGE / módulo108

- `pige_bridge_settings_choices_mysql.php`:100 verificações cumulativas em MySQL8.4, incluindo writer anterior e controller disable; seleção exclui empresa/ambiente/conta incompatível, wallet não verificada e funcionário sem ACL.
- `pige_bridge_settings.browser.cjs`: view PHP e JS reais em Chromium, desktop/mobile, pesquisa/paginação, revisão, limpeza de token, troca explícita de provedor/conta/perfil e consentimento não ativado. Framework sintético, sem chamadas HTTP autenticadas.
- `pige_bridge_migration.php`:33 checks com runner nativo e adaptador de metadados. Não equivale ao MySQL de instalação.
- Descoberta bilateral por URL/credencial ainda não implementada; os seletores não são descoberta nem consentimento financeiro.

## Cartão 1.6.2

Resultados novos e sua natureza em [asaas-direct-card.md](asaas-direct-card.md).
MySQL: 227 assertions migration; 177 checks cumulativos serviços/concorrência.
Controller SQLite: 198; transporte: 19; Chromium view real aprovado.
Sem sandbox nem HTTP autenticado completo para pagamento direto.

## Inventário operacional após cartão e reajustes

O CI do SHA `0b17ec4f2fbf524e069014cd8d028f8aea509b3a` detectou inventário
operacional desatualizado no job `registry-shards (8.1, 1)` do run
42159425927. O gerador oficial reproduziu exatamente o hash esperado pelo CI:
`f74bee8a898c01c2571fe453f4739db64f67e573a4fe19280c9f0415fcbcd060`.
O inventário foi regenerado a partir do código atual (4808 arquivos, 38 módulos).
Estados `not_verified` e `operational_ready: false` foram preservados; atualizar
esse artefato não comprova isolamento, homologação ou prontidão operacional.

### Dependências reais do fixture de migration489

A falha de `native_sales_projection_mysql.php` no run42159426044 foi reproduzida
em MySQL8.4: erro1824 ao criar `company_leads`, pois o fixture de vendas não havia
criado a tabela nativa `contacts` referenciada pela FK. O fixture agora carrega a
definição de `install/database.sql` e mantém a FK ativa. O teste rejeita uma
referência de contato inexistente (SQLSTATE23000/1452). A execução corrigida passou
com DDL/replay, precisão monetária, rollback e preservação dos registros prévios.
Nenhuma migration ou restrição de produção foi alterada.

O inventário de fronteiras também foi regenerado:15296 arquivos, hash
`2abf33de533549c29eac9788b16e976c9c4e2185848fb69618543b37a56d10bc`, exatamente o
esperado pelo CI. Os três testes do gerador e seu `--check` passaram. As fronteiras
continuam classificadas como não verificadas; o inventário não prova isolamento.

### Adaptadores dos testes de controller BB

O run42159425980 falhou em MySQL/MariaDB porque `ExtensionRequest` não expunha
`get()`, utilizado pelo controller real para o destino fixo de configurações.
Os cinco adaptadores agora separam os parâmetros GET dos dados POST. O teste
histórico439 também passa a restaurar o marcador realmente instalado antes das
sondagens negativas, mantendo recusa de versões antigas/futuras e verificando o
marcador restaurado. Nenhum controle de produção foi relaxado.

Resultados locais MySQL8.4: `native_sales_projection_mysql.php` aprovado;
`banco_brasil_tariff_controller.php`289 checks cumulativos;
`banco_brasil_own_number_controller.php`864;
`banco_brasil_resume_439_controller.php`564;
`banco_brasil_resume_440_controller.php`564.
São controllers reais com requisição/sessão adaptadas e transporte bancário
simulado; não representam HTTP autenticado completo ou homologação bancária.
Os27 testes Python dos planos de CI registry/focused passaram.
`banco_brasil_api_window_discount_controller.php` também passou:1233 verificações
cumulativas em MySQL8.4, incluindo o ponto que falhava nos dois bancos do CI.

## Boleto CNAB240 /139 e banners core4.9.2

- MySQL8.4: `banco_brasil_cnab_boleto_mysql.php`277 checks cumulativos, incluindo instalação/upgrade/replay/falha de tracking até139.
- Concorrência MySQL: `banco_brasil_origination_concurrency.php`274 checks, processos reais aguardando lock para impressão/download e revogação.
- Admin100 e module_version54 checks PHP;5 verificações estáticas bridge125.
- Chromium renderiza HTML produzido pela suíte SQL: dimensões físicas, escala de todas as barras, zona de silêncio, linha sem corte, controles ocultos na impressão e nenhum recurso externo. Não é HTTP autenticado nem homologação bancária.
- `npm run production` recompilou sem diff nos assets; main/tickets e os outros quatro banners já eram4.9.2. Acrescentado gate de banner em `crm_integrations_contract.test.cjs`, incluído no node-contracts existente.8 testes Node passaram, sem afrouxar o gate.

## Correções do CI e privacidade do cartão após 473352dc

O CI do SHA473352dc confirmou `node-contracts`, incluindo reconstrução idêntica
com banners4.9.2; os gates não foram relaxados. Os jobs BB MariaDB e focused MySQL
reproduziram fixtures desatualizados: configuração400 movida para Settings e
`company_items` criado antes do grupo referenciado. A configuração continua
coberta no local novo e a FK permanece ativa. O teste da declaração de billability
usa agora o helper real no MySQL, preservando `ascii_bin`.

Após corrigir os fixtures, MySQL revelou dois defeitos na consulta de despesas:
comparação do ID textual com collations incompatíveis e número de fatura no UNION.
A consulta agora compara IDs legados por bytes e declara a collation da projeção
numérica; não altera schema nem dados históricos. Regressão: `5` encontra o meio,
`05` não é convertido silenciosamente no mesmo ID.

Executados localmente com as correções:

- `banco_brasil_cnab400_aux_controller.php`: 355 verificações cumulativas em MySQL.
- `native_expense_index_http.php`: MySQL e SQLite, 23 verificações de consulta e
  31 de controlador/DOM/SQL, além dos pré-requisitos. Framework/sessão/shell são
  spies explícitos: não é navegação autenticada completa.
- `asaas_card_payments.php`: 169 verificações cumulativas em MySQL com transporte
  simulado; token ausente de tentativas, provas, auditoria e logs após sucesso,
  timeout e revogação.
- `asaas_card_payment_controller.php`: 200 verificações cumulativas em SQLite;
  token não retorna ao DTO nem entra na sessão/logs após rejeição.
- `tests/browser/asaas_settings.cjs`: Chromium com view PHP real; cookies,
  localStorage e sessionStorage vazios após preencher token sintético e recarregar.
  O formulário não coleta PAN/CVV. Nenhuma cobrança real ou homologação PCI.

O Composer local continua bloqueado pelo HTTP403 no download oficial de Mustache;
nenhum gate foi retirado. O CI remoto conseguiu construir a imagem de smoke de
473352dc, mas seus grupos HTTP ainda estavam em execução nesta verificação.

## Boleto CNAB400 — módulo140

- `banco_brasil_cnab400_boleto_mysql.php`:335 verificações cumulativas MySQL8.4,
  incluindo instalação/tracking nativo até140, geração real a partir da preparação,
  snapshot, origem, saldo, isolamento, cancelamento, revogação e impressão sem
  mutações. Dois processos esperam no mutex real: impressão/download preservam
  código e hash; revogação enquanto aguardam impede ambos os documentos.
- `banco_brasil_admin.php`:104 verificações, incluindo POST/CSRF da impressão400;
  `banco_brasil_module_version.php`:54; contratos439/440:192; bridge125:5.
- Chromium: view produzida pelo teste SQL400, ficha190×105mm, I25 103×13mm,
  barras completas, linha digitável sem corte, aviso HOMOLOGAÇÃO—NÃO PAGAR e
  nenhuma requisição externa. Captura local inspecionada; não é HTTP autenticado.
- Plano SQL histórico preservado; novo teste400 em etapa adicional;14 testes do
  plano passaram. Nenhum envio ao banco, cobrança, registro ou homologação real.

A rodada bee529da foi interrompida pelo inventário de isolamento desatualizado.
Os geradores oficiais foram executados novamente e seus resultados serão incluídos
integralmente, sem alterar estados conservadores de prontidão.


## Leads e continuidade dos menus — correção posterior a968f6eea

O focused MySQL8.1 de968f6eea falhou no teste de metadados de leads. Reproduzido
em MySQL8.4: o fixture substituía o shell e não carregava a classe de tradução
usada pelo helper real. Ao repor essa dependência, foi encontrado um defeito de
produção: busca com acento comparava o padrão Unicode à coluna fiscal ascii_bin,
produzindo erro1270. A consulta agora converte somente essa comparação para
utf8mb4_bin, sem alterar schema, dados, escopo ou significado dos identificadores.

`native_lead_metadata.php` aprovado em MySQL8.4:53 verificações adicionais, mais
28 de diretório/filtro/board e31 do controlador original. Inclui busca por etiqueta
com acento e pelo identificador fiscal, criação/edição, replay, revisão obsoleta,
revogação, rollback, corrupção por trigger e isolamento. Shell/sessão são spies;
não é homologação autenticada. Diagnósticos SQL usados apenas em cópias temporárias
com dados sintéticos; não foram adicionados ao logging do produto.

O grupo HTTP banking ganhou uma verificação de menus nas empresas A/B, usando
login e shell reais da instalação descartável: Painel → BB operação → BB configuração
→ Definições → Painel → Definições. Compara a árvore autorizada antes/depois de
abrir o menu e entre rotas, preservando empresa. O JSON de evidência contém apenas
slugs e identificadores sintéticos, sem URLs com tokens nem valores de formulário.
Sintaxe Node e plano de104 invocações/11 grupos aprovados; execução autenticada
deste novo caso ainda pendente no CI. Não foi aplicada correção especulativa de menu.

A alternativa oficial de baixar a imagem968f6eea também ficou bloqueada: o
conector limita artefatos a512MiB (imagem aproximadamente1GiB), e `gh run download`
recebeu Forbidden. Nenhuma imagem foi obtida nem houve contorno da restrição.
A construção remota dessa imagem passou; isso não comprova os testes HTTP locais.

## Descoberta PIGE109 e navegação de despesas

A descoberta usa os GETs reais do PIGE360-self
`f47b8916b92231ac29b56862b133c9d2b2eec851`, revalidado no remoto.
Nenhuma alteração naquele repositório ou no SDK. Consulte
[pige-discovery.md](pige-discovery.md) para campos importados, limites e fluxo de
credenciais. Não cria a outra ponta, não recupera HMAC e não habilita consentimento.

Executados localmente em PHP8.3/MySQL8.4:
- 71 verificações de contrato, minimização, SSRF, DNS e respostas remotas, com
  transporte/DNS simulados explicitamente.
- 9 verificações adicionais do controlador com ACL/MySQL reais: empresa alheia,
  revogação durante resposta, módulo inativo/desatualizado, HTTPS de entrada e
  cabeçalho de proxy não confiável; conexão/auditoria preservadas e segredo ausente
  dos logs do recurso. Nenhuma chamada PIGE externa.
- Instalação limpa, repetição, upgrade108→109 e falha de tracking pelo runner
  nativo; configuração e seletores:100 checks cumulativos.35 checks do runner
  isolado. Migrations anteriores preservadas.
- Chromium desktop/mobile com view e JS reais, transporte simulado: preenchimento,
  limpeza de credencial, flags financeiras preservadas e resposta atrasada após
  cancelar/reabrir outra conexão descartada.
- 8 checks Python de inventário/configuração e sintaxe JavaScript aprovados.
  Isso não substitui teste HTTP/TLS bilateral autenticado.

CI de `b91a4734`: contratos Node/assets e preflight de navegador PHP8.1/8.5
aprovados. Focused8.1 falhou em `native_expense_navigation.php`: fixture antigo
substituía a view atual por DTO do workspace e não implementava a saída HTTP atual.
O teste agora usa a view/controlador/SQL originais compartilhados com a suíte de
índice. Preservado o gate que exige MySQL no entrypoint de índice HTTP em CI;
nenhuma verificação foi removida do manifesto.

Reprodução corrigida em SQLite e MySQL8.4:5 checks de navegação adicionados aos
31 checks de índice HTTP. Dois caminhos preservam o ID canônico no DOM; conflito
rota/query, troca de empresa e ID numérico são recusados antes de renderizar.
Shell, sessão e entrada HTTP são spies explícitos; não é navegação autenticada.

A sequência de20 comandos SQLite desde o ponto de falha até o fim do manifesto
também passou, com CI=true. O entrypoint MySQL foi executado deliberadamente sem
DSN e recusou a fase incorreta, conforme esperado. Os três inventários oficiais
foram regenerados; continuam declarando rollout operacional bloqueado.

## Monitor de CI — resposta incerta do GitHub

O run42170292011 de fb7e74b1 falhou com HTTP502 do GitHub no POST de cancelamento
de uma rodada antiga. O monitor agora consulta o estado do run sem repetir esse
POST: conclusão confirmada encerra o caso; estado ativo segue a espera e a
revalidação de PR/HEAD já existentes antes de eventual force-cancel. Falha de
transporte nesse último passo com estado ainda ativo continua sendo erro explícito,
não sucesso. GET tem até três tentativas apenas para falhas transitórias;
401/403 continuam erros sem retry. Nenhum escopo de cancelamento foi ampliado.

20 testes Python aprovados, incluindo sete de transporte/resultado incerto e os
limites existentes para outra PR, fork, push, release e dispatch. APIs simuladas;
nenhum cancelamento manual foi efetuado nesta verificação.

O CI PIGE109 passou em fb7e74b1. O gate de interface desse SHA detectou o inventário
estático de controles/rotas desatualizado: dois inputs, dois botões e um select
adicionados ao modal existente, mais a ação JSON discover. O delta foi conferido
contra a view e os testes Chromium já executados; inventário atualizado sem
afrouxar a comparação. Gate local aprovado:16 views/6 controllers. Um fragmento
sem proprietário resolvido continua explicitamente registrado como lacuna.

## CNAB400 agrupado — módulo141

Implementado agrupamento automático durante geração CNAB400, preservando o vínculo
individual de origem e arquivo-fonte. Primeiro download congela o arquivo conjunto;
novas emissões seguem para outro grupo. Declaração manual abrange todos os membros.
Detalhes e limites: [bb-cnab400-groups.md](bb-cnab400-groups.md).

MySQL8.4/PHP8.3:32 verificações adicionais do fluxo de grupos aprovadas, além dos
pré-requisitos reais de fatura/origem/migration/retorno. Cobrem emissões em datas
diferentes, arquivo com cabeçalho/trailer únicos, sequências, replay, nova emissão
após download, conta/convênio distintos, corrupção, ACL/empresa, rollback por
falha de auditoria, origem/snapshot/bytes individuais preservados e dois processos
concorrendo por geração/download com espera observada no mutex.

Regressão de boleto400:340 checks cumulativos aprovados, incluindo impressão
concorrente com download. Adaptador financeiro CNAB400 existente também passou;
não houve chamada bancária, autenticação de retorno real ou baixa de produção.
Migration141 passou pelo runner, com falhas de DDL/outer transaction/tracking,
retry e repetição, sem backfill.

Uma execução paralela inicial conflitou no nome fixo de trigger de um fixture
anterior; grupos foram reexecutados em outro schema descartável e passaram.
O conflito não foi apresentado como defeito do produto nem escondido por remoção
do teste. Inventários oficiais regenerados, mantendo rollout bloqueado.

## Emissão nativa e recorrente — BB142

MySQL8.4 descartável, PHP8.3: `banco_brasil_invoice_issuance_mysql.php`
passou30 verificações adicionais sobre a cadeia de fixtures reais: emissão240/400,
rollback integral por falha no ledger, retry, duas ocorrências por leiaute,
CNAB400 agrupado, recusa sem declaração, marcador antigo, canal desabilitado,
campos inválidos, operador restrito, revogação e concorrência de dois escritores
com espera observada no mutex. Nenhuma chamada bancária.

`banco_brasil_resume_443_module_tracking.php` passou os percursos reais de
instalação e upgrade120/121/122→142 com dados históricos, DDL/replay/falhas de
tracking. A falha do CIce2 era uma lista incompleta de tabelas adicionadas pela141;
o teste agora exige exatamente as tabelas141 e142, sem remover comparação de
schema, registros ou markers. Executado em MySQL8.4 (o rótulo antigo do teste
menciona MariaDB; MariaDB permanece dependente do CI).

Regressões: `banco_brasil_open_batches_mysql.php`276 cumulativas;
`banco_brasil_cnab400_groups_mysql.php`32 adicionais;
`banco_brasil_cnab400_boleto_mysql.php`349 cumulativas. O240 agora testa duas
faturas com datas distintas, além de datas de geração distintas, preservando as
datas individuais dos snapshots. A consulta inicial do teste usou uma coluna
inexistente; foi corrigida para ler o campo do JSON real, mantendo a asserção.

SQLite: `companies/native_invoice_form_post.php`239 cumulativas +4 verificações
novas do parser; sintaxe PHP dos formulários passou. O primeiro teste do parser
usou o formato curto em vez da chave pública do catálogo; corrigido para usar
`CompanySettingsFormats::dates()`, sem relaxar a validação do produto.
Módulo:54 checks; contratos194; inventário estático bridge125:5 testes.

A extensão de ACL do fixture inicialmente herdou o vínculo empresarial revogado
pelo teste anterior. O cenário passou a preparar explicitamente a associação do
operador sintético antes de testar concessão e revogação bancárias. Isso não
altera permissões de uma instalação real. Inventários oficiais regenerados;
rollout continua bloqueado. HTTP autenticado/browser142 e homologação bancária
não foram substituídos por esses testes locais.

Após o lock explícito da identidade e do marcador do módulo, a suíte142 foi
reexecutada:30 verificações adicionais aprovadas. Também passaram o controller
real da recorrência com CSRF/ator atual (130 cumulativas, SQLite) e o renderizador
da view original de fatura (217 cumulativas; shell sintético declarado).

Roteiro autenticado `banco_brasil_origination_browser.cjs` atualizado para acessar
configurações no endereço nativo atual e esperar lote aberto antes do primeiro
download. Foi acrescentado o fluxo pela nova fatura: escolher CNAB/configuração,
preencher pagador/fuso, salvar pelo formulário real, confirmar vínculo único e
imprimir antes de baixar, sem pagamento. Sintaxe Node passou; a execução integral
desse roteiro permanece no grupo banking do CI, sem alegação de aprovação local.

## Propostas, orçamentos e créditos — validação adicional

MySQL8.4/PHP8.3 descartáveis: `native_estimate_form_create.php` passou18
verificações adicionais, precedidas por17 de campos/persistência/conversão;
`native_credit_controller.php` passou118 cumulativas.
`native_portal_proposals_controller.php` passou18 verificações próprias, além
da cadeia de vendas/anexos244 cumulativas: listagem, número imutável, PDF, anexo
publicado, hash incorreto, contexto e acesso cruzado. Incluído na fase MySQL do CI.

A primeira execução MySQL de Propostas falhou porque o fixture criava a tabela
de anexos depois de aquecer o cache de metadados, mantendo esse cache ao simular
outra requisição. O fixture agora descarta apenas o cache de nomes de tabelas
antes do controller e exige explicitamente a tabela e o anexo real. As três
asserções de número/anexo/PDF foram separadas, sem remover cobertura. Nenhum
código de produção foi alterado por essa falha.

SQLite: listagem/ACL de propostas, decisão de orçamento e conversão automática
passaram, incluindo assinatura, PDF, corrupção, recuperação e corrida de grant.
Esses testes executam serviços/controllers com banco real e fronteiras sintéticas;
não equivalem a navegador autenticado nem comprovam toda a paridade com4.0.2.

## Cancelamento nativo Asaas163

`companies/asaas_invoice_cancellation.php`:22 verificações adicionais MySQL após167
cumulativas; corrida real entre dois processos:6 checks. Um DELETE e um recibo
local, sem repetir chamada em timeout ou rollback. Provedor simulado. Controller
nativo de cancelamento:145 cumulativas SQLite, incluindo fluxo manual e rascunho.
`asaas_module_migrations.php`:243 assertions no runner MySQL; `asaas_callback_version.php`:13.
A tentativa inicial do runner foi recusada por nome de schema fora da allowlist;
reexecutada em `asaas_test`, sem alterar guard. [Contrato e limites](asaas-invoice-lifecycle.md).

## Preparação bilateral PIGE110

Contrato:37 checks adicionais após71 da descoberta. Controller com MySQL:13
adicionais, preservando conexão local e empresa vizinha; CSRF/GET/confirmação/revisão
e resultado não confirmado cobertos. Provedor/DNS são simulados. Chromium desktop
e mobile aprovou formulário nativo, confirmação vazia, ausência do HMAC na view,
restrição da conexão habilitada e limpeza do JWT. Runner com metadados simulados:37.
Runner MySQL real passou instalação/repetição/upgrade e preservação na falha de schema.
Nenhuma credencial real, criação remota ou habilitação financeira. [Limites](pige-discovery.md).

CI204d: a suíte histórica155 ainda fixava o header162; atualizada para163 sem
alterar as migrations nem retirar comparações. MySQL155 reexecutado:101 checks
além dos46 de154. O census de ações nativas também foi regenerado pelo gerador
oficial após a mudança no controller; esse inventário é separado dos outros três.
O wrapper único local não rodou por ausência de Python no container PHP; suas
etapas foram executadas com Python no host e PHP no container, sem omitir o diff.

CI8c5645b7: o gate de superfícies nativas detectou corretamente o formulário e a
rota de preparação110 ausentes no inventário revisado. Atualizados somente os
controles literais e a rota pública do PIGE, com a evidência Chromium/MySQL acima;
nenhuma asserção do gate foi alterada. Reexecução local aprovada:16 views e6
controllers. Trata-se de inventário estático, não de homologação HTTP autenticada.

## Diagnóstico PIGE111

51 verificações adicionais de contrato e11 de controller com MySQL: escopo remoto
incompatível bloqueia POST, revisão antes/depois, falha de HMAC, timeout sem retry,
CSRF/GET/HTTPS, revisão local obsoleta, empresa sem permissão, conexão desativada,
revogação e preservação de segredo/consentimento. Nenhum provedor real foi chamado.
Runner111:39 verificações usando o executor real e metadados simulados; runner
MySQL real em schema descartável novo passou instalação, repetição, upgrade,
tracking e preservação na falha. Chromium desktop/mobile passou o modal com
botão habilitado somente na conexão ativa e JWT limpo no fechamento. A primeira
execução do teste consultava o campo antes do evento hidden.bs.modal; corrigido
para aguardar a limpeza, mantendo a asserção. Não é HTTP autenticado de produção.

CI4aa5: focused SQLite falhou em `banco_brasil_cnab400_preparation_sqlite.php`.
Reproduzido localmente: o fixture anterior à141 não criava envelopes/membros CNAB400;
após acrescentar as tabelas, a geração alcançou a validação information_schema,
indisponível no SQLite. O fixture agora declara os metadados desses dois objetos
pelo mecanismo opt-in de doubles já existente. O adaptador de teste respeita o
filtro COLUMN_NAME da consulta de FK. Não houve mudança no serviço/validador de
produção nem remoção das asserções de conteúdo, idempotência e rollback. O teste
SQLite completo passou; isso não comprova schema InnoDB. A suíte MySQL permanece
a evidência separada da instalação/agrupamento reais.
Reexecução MySQL: preparação/concorrência e32 verificações adicionais de agrupamento
passaram, após266 cumulativas da emissão e35 de rejeições de retorno. A cadeia
SQLite de `companies/contracts_controller.php`, que compartilha o adaptador de
metadados, passou. Sem transmissão ou homologação bancária.

## Intenção interna de recriação Asaas164

11 verificações adicionais de banco: replay, conflito de chave, empresa/revisão,
revogação, prova vencida, rollback e preservação de origem/eventos/status cancelado.
Ainda não testa criação de sucessor nem reativação, que não foram conectadas.
Runner MySQL:261 assertions passaram, incluindo falhas DDL/tracking e retry;
primeira execução encontrou apenas limpeza incompleta do fixture, corrigida para
remover a nova tabela antes de suas referências, sem desabilitar FKs.
Concorrência de intenções: cinco checks adicionais com dois processos MySQL,
bloqueio observado, resultado idêntico e uma única linha; nenhum arquivo de
chamada externa foi produzido. Incluído como filho da suíte de cancelamento no
manifesto focused, preservando seus testes anteriores.

## Recriação Asaas165

Executado `php tests/companies/asaas_origin_generations.php` em PHP8.3/MySQL8.4:
64 verificações adicionais de evolução de schema, rollback do sucessor,
preservação de histórico, recuperação de POST aceito cuja resposta se perdeu,
rollback do diário, envio único com dois processos MySQL reais e dois ciclos
completos cancelar/recriar. A API é simulada; não há homologação nem cobrança real.
`php tests/asaas_module_migrations.php`:277 verificações no runner nativo/MySQL,
incluindo165 interrompida na DDL/tracking e repetição. `native_invoice_reopen.php`
SQLite:170 cumulativas de writer/controller com doubles, preservando a reabertura
sem provider. Callback version:13 verificações; inventário UI16 views/6 controllers.

O CI de a5794fa9 passou nos workflows rápidos Asaas/PIGE/native-ui/recorrências e
containers, mas o monitor identificou falha real em `http (settings)` do run
42185070943, job127786957610: clique no input transparente do checkbox de crédito
interceptado por outra opção/toolbar. O teste agora usa o label nativo visível e
verifica o checked do input correspondente, mantendo actionability (sem force,
JS click ou alteração do DOM). A reprodução isolada com CSS real não reproduziu
a sobreposição; portanto esta alteração do driver ainda requer o CI autenticado
para confirmar, e não é apresentada como causa raiz ou correção de CSS.
O artefato4,6MB foi obtido como referência pelo conector, mas sua materialização
local falhou com URLError; não foi inspecionado visualmente.

O mesmo log HTTP comprovou instalação/upgrade e execução real do recurring_worker
no núcleo492 (223 checks) antes da falha de créditos. Isso amplia a evidência do
núcleo para aquele SHA, sem converter o shard inteiro nem a nova recriação165 em
homologação HTTP aprovada.

## Correção da rodada CI ecf053f4 / Asaas166

A rodada encontrou `payment_origin_is_immutable` em
`asaas_payment_origin_integration.php`: FakeDB dizia que qualquer coluna existia,
mas suas linhas pré165 não tinham active_slot/default1. ContactDb tinha o mesmo
contrato fictício. Esses dois fixtures agora declaram ausência da coluna165; não
houve relaxamento do seletor ativo de produção.
A inspeção também encontrou state()/attachRemote() do store legado escrevendo
por invoice_id sem limitar a geração. As escritas passam a usar active_slot=1
quando disponível; binding fixa o ID selecionado. Migration166 NOOP conserva165.
A suíte MySQL de gerações foi ampliada para verificar lookup e escrita legada
com preservação byte a byte de todos os predecessores.

Validação local da correção166: 66 checks MySQL de gerações/concorrência/falhas,
280 asserts MySQL de migrations e os 19 comandos PHP do job de contratos Asaas
passaram. Provedor simulado, sem cobrança real ou homologação externa.

Ampliação de segurança166: a mesma suíte passou com69 checks após acrescentar
revogação do operador durante o GET de cliente. Confirma zero POST, intenção
continuando prepared e fatura cancelada. Não é teste HTTP contra o Asaas real.

## Composição pós-reembolso167

Runner nativo de módulo em MySQL:283 assertions passaram, incluindo tracking167
com falha, retry e replay; nenhuma migration histórica foi alterada. Regressão
legada `post_refund_manual_cycle_boundaries.php`:245 checks cumulativos SQLite
passaram (não é homologação MySQL dessa suíte). Sintaxe dos arquivos alterados,
callback13 e inventário UI16 views/6 controllers passaram.

R01/R03/R06/R11: executados em MySQL os scripts
`native_portal_proposals_controller.php`, `native_estimate_form_fields.php`,
`native_sales_table.php` e `native_payments_table_http.php`; todos passaram.
Incluem18 checks de controller de propostas,175 cumulativos de histórico/lista
Vendas e16 de DataTables Pagamentos, mais a cadeia de campos/isolamento de
Orçamentos. Contadores de suites reutilizadas não são somados. Handlers com
fronteira de requisição simulada não comprovam browser/HTTP autenticado nem
paridade visual integral com4.0.2.

A primeira tentativa concorrente pós-reembolso falhou no bootstrap do worker:
CompanyPostRefundManual era carregada duas vezes. A captura dos erros dos dois
processos identificou a causa; require_once corrigiu o fixture sem alterar o
assert de dois waiters reais no InnoDB. A rodada seguinte passou29 verificações
adicionais de composição, sobre as69 de gerações: rollback do recibo de
cancelamento/pagamento, replay, cadeia não confirmada/órfã/de outra empresa,
prova adulterada, dois processos concorrentes e revogação durante espera.
Provedor exclusivamente simulado, sem cobrança ou reembolso externo.

Rodada final ampliada:30 checks adicionais pós-reembolso passaram em MySQL,
incluindo duas recriações sucessivas antes do reembolso/cancelamento local,
além das69 verificações de gerações. O manifesto focused executa essa composição
completa; a suíte anterior continua sendo executada como pré-requisito.

CI09369c1c:18 workflows passaram, inclusive Asaas. O monitor42192769035 encontrou
falha em registry-shards(8.1,5), job127809956843: `_l()` ausente no fixture
`sales_mutations_controller.php`, ao montar os rótulos nativos de Orçamentos.
O fixture passa a carregar o catálogo PT-BR real e sua fronteira de tradução,
com assert do rótulo de rascunho; produção e validações de ACL/CSRF não mudaram.
Os shards HTTP independentes ainda estavam em execução na inspeção; não há
aprovação agregada desse SHA.

A reprodução corrigida de `sales_mutations_controller.php` passou em MySQL:
294 checks cumulativos, incluindo controller oficial, renderer, revisão/cópia,
CSRF e ACL; o assert de tradução utiliza o catálogo PT-BR real. Nenhum gate ou
assert anterior foi removido. Inventários de isolamento e superfícies conferidos;
operational_rollout continua blocked pelas lacunas gerais já registradas.


## Continuação168: parcial manual e memória do focused

- `php -d memory_limit=128M tests/companies/asaas_partial_manual.php`:30
  verificações adicionais MySQL aprovadas sobre118 da cadeia de origem; chamadas
  Asaas simuladas. Formulário/ação de serviços reais, não sessão HTTP autenticada.
- `php -d memory_limit=128M tests/companies/asaas_post_refund_generations.php`:
  cadeia69 +30 verificações de sucessores/pós-reembolso passaram sob o limite do CI.
  A falha do job127894118241 em177b5887 era memória128MiB: o fixture retinha os
  traces SQL da cadeia já concluída. A correção libera apenas esse diagnóstico
  antes dos novos ciclos; mantém banco, asserts, concorrência e limite de memória.
- `php tests/companies/native_invoice_selected_payment.php`: regressão MySQL
  do formulário/meio manual/revisão/rollback/replay aprovada.
- `php tests/asaas_module_migrations.php`:286 verificações MySQL do runner,
  inclusive falha de tracking168, retry e replay. Migration168 NOOP.
- Inventário UI estático:16 views/6 controllers; não comprova navegador.

Sem merge, deploy, dados financeiros reais, SDK ou coleta de cartão.
Integral manual, exclusão/edição remota e paridade R01–R11 seguem incompletos.


## R07: opções de checkbox em linhas de crédito

Os cinco grupos HTTP do run42218007134/177b5887 pararam no mesmo clique em
`tests/companies_credits_browser.cjs`: opção de campo de item invisível. O CSS
nativo `td.custom_field label:not(.cf-chk-label)` oculta rótulos de coluna, mas o
renderer de Créditos não marcava as opções com a classe de exceção. Reproduzido
localmente com o PHP real + CSS compilado em Chromium: assertion de visibilidade
falhou antes da correção. Após acrescentar `cf-chk-label`, desktop1280 e mobile390
passaram com clique real, associação label/input e valores literais de FormData.

Teste novo: `tests/companies/native_credit_checkbox.browser.cjs`, incluído no CI
focused. O teste HTTP original permanece sem alteração/force/evaluate/remoção de
asserts. Componente visual comprovado; reexecução autenticada integral fica a
cargo do novo CI. Não altera saldo, banco, schema ou permissões; core492 preservado.


## Continuação169 — recebimento manual integral

- `tests/companies/asaas_full_manual.php`: MySQL8.4/PHP8.3, limite128MiB,
  35 verificações adicionais sobre118+30 anteriores. Cash/remove, parcial seguido
  de saldo integral, dunning antes do envio, resposta perdida, recuperação GET,
  rollback tardio, duas gravações concorrentes por operação, isolamento e ação
  nativa/replay sem HTTP novo. Transporte injetado simulado, sem cobrança real.
- `tests/companies/asaas_manual_controller.php`: oito verificações adicionais sobre
  179 anteriores; controlador e view reais com request/session sintéticos e
  MySQL. CSRF, confirmação, revisão, conta estrangeira e apresentação somente
  leitura. Não é aceitação HTTP autenticada.
- `tests/asaas_module_migrations.php`:292 verificações MySQL do runner nativo;
  falha DDL169, tracking, replay e ausência de políticas/comandos automáticos.
- CI do SHA2ba50126: interface nativa aprovada; monitor falhou por transporte
  incerto ao cancelar rodada antiga. HTTP avançou até créditos, onde o teste
  clicava opções já selecionadas pelo catálogo esperando seleção verdadeira.
  Expectativa corrigida para verificar estado inicial, alternância e restauração
  por clique real, sem force/DOM mutation/remover asserções de valores enviados.
  A correção de visibilidade do label anterior permanece necessária.

Os resultados não encerram R01–R11, exclusão/edição remota, cartão ou homologação.


## Continuação170 — edição remota e exclusão nativa

Executados em PHP8.3.36/MySQL8.4 descartáveis, transporte Asaas simulado:

- `tests/companies/asaas_invoice_edits.php`: 38 verificações adicionais sobre118. PUT com resposta perdida, falha local/rollback, recuperação GET sem reenvio, isolamento A/B, revogação, hash adulterado, duas sessões concorrentes (um PUT), revisões auditadas, cancelamento/recriação após edição e baixa pela nova origem.
- `tests/companies/asaas_invoice_edit_controller.php`: quatro verificações adicionais sobre187; controller/view reais com sessão/request adaptados, CSRF, confirmação e ausência de formulário sem autoridade. Não equivale a HTTP autenticado.
- `tests/asaas_module_migrations.php`:297 verificações pelo runner MySQL, incluindo DDL/tracking/repetição170 e limpeza da fixture respeitando FKs.
- Regressão `asaas_post_refund_generations.php`:69 verificações de gerações, mais30 pós-reembolso, sob128MiB; inclui disputas reais de escritores.
- `asaas_invoice_delete.php`:12 verificações adicionais sobre cancelamento/concorrência; exclusão nativa cancela preservando histórico e confirma remoção Asaas, não apaga a fatura fisicamente.
- Lint PHP dos arquivos alterados e23 testes Python de inventário/monitor passaram.

CIb45 confirmou focusedPHP8.1, node-contracts, preflight browser8.1/8.5, interface nativa, Asaas, recorrências e reajustes. Falhou em boundary-inventory desatualizado e nos grupos HTTP ao abrir Créditos (`init_btn_with_tooltips` ausente): helpers de preview estavam restritos ao bundle de Faturas. Inventário regenerado; correção compartilhada e nova rodada ainda exigem CI do próximo SHA. Não se converte a aprovação parcial em CI verde.


Correção R07 do preview: `native_credit_preview_helpers.browser.cjs` passou com o script inline real, jQuery e plugin de abas reais, sem carregar o bundle de Faturas, em1280/390px e RTL. Inicialização repetida não duplica os rótulos. Nove testes Node de dependências/lifecycle e o verificador de referências JS passaram. Teste autenticado mantém assert de ausência de pageerror; não foi afrouxado.


CI92278fa6: node-contracts e preflight Chromium8.1/8.5 passaram. O contrato remoto Asaas ainda esperava erro genérico ao rejeitar PUT de cobrança; atualizado para a rejeição específica de payload inválido, mantendo caminho/escopo restritos e acrescentando Pix válido, cartão recusado, data impossível, valor inválido e cliente extra. Os19 scripts do job `contracts` passaram localmente sem rede, incluindo contato2316, retry847, webhook171 e escritores84/44. Versão mínima do módulo170 ajustada para núcleo4.9.2; callback13 passou. A nova rodada precisa confirmar o SHA posterior, sem tratar922 como verde.


Ajuste do teste histórico154 após o mínimo170: o método nativo de compatibilidade rejeita457/458/491 e aceita492. Migração154 preservada:48 checks MySQL; migração155:101. CIdffaccad passou contratos8.1/8.5 e settings-ui, mas parou nesse assert antigo, corrigido sem alterar migrations publicadas. Download local oficial por Git (`--prefer-source`) obteve Mustache no commit do lockfile com CA do ambiente via secret de build; TLS e proxy mantidos. O build subsequente falhou por espaço insuficiente em `/var/cache/apt/archives` ao instalar extensões PHP. Não foi executado HTTP local dessa imagem. Removidos somente os dois caches de cópia de fontes desse build, preservando checkout, banco e imagens de testes.

CIffd4dbb7: workflow Asaas42233268954 aprovado, incluindo contratos8.1/8.5,
settings-ui e MySQL. Reajustes, calendário, interface nativa, node-contracts e
preflight Chromium8.1/8.5 também passaram. O job registry-shards8.1/1
127946237237 encontrou hash de fontes desatualizado no inventário de superfícies
operacionais. Regeneração oficial altera somente esse hash; nenhuma superfície
foi promovida a operacional. Os demais shards e HTTP permanecem diagnósticos
independentes; esta rodada não é verde.

O teste `asaas_invoice_edit_controller.php` foi ampliado e passou com oito
verificações adicionais sobre187 em MySQL8.4/PHP8.3: painel indisponível antes
do tracking170, leitura real do journal após instalação, CSRF, confirmação,
recusa de chave sem comando e ausência de intenção criada por essa recusa.
A fixture agora instala também a tabela nativa de comandos exigida pela
transação; não há relaxamento do guard de produção nem chamada ao provedor.

## Continuação BB143 — publicação CNAB no portal

`banco_brasil_cnab_portal_mysql.php`:30 verificações adicionais após354,
PHP8.3/MySQL8.4 sob128MiB. Duas sessões reais aguardam o lock e são recusadas
após revogação da permissão do contato. Publicar/ler/ocultar preserva bytes,
estados, números, faturas e pagamentos. A view PHP gerada nessa execução passou
em Chromium/HTTP sob CSP com nonce e clique real de impressão. Isso não equivale
a login HTTP no CRM. A extensão autenticada está no teste existente de emissão.

Regressão `banco_brasil_checkout_mysql.php` passou: cadeia API até679 checks e
publicação/isolamento existentes, transporte remoto simulado. Runner143 percorre
os passos anteriores e testa falha de tracking, retry e replay. Unitários:
54 checks de versão,118 CNAB240,56 store/transaction e107 administrativos.
[Contrato, alterações e limitações](bb-cnab-portal.md).

### Correção do percurso HTTP das guias Asaas

O run42237032492/job127961115223 do SHA5e981ecb falhou na inspeção de
preferências porque o teste não abria a guia Configuração introduzida na PR.
O teste agora clica a guia real antes de verificar visibilidade, ARIA, teclado e
valores; conserva todas as verificações. O percurso de credenciais, webhook,
transações e automação também abre as guias correspondentes, e o teste checkout
usa o identificador atual da lista de guias. Os20 testes do contrato do harness
passaram; a execução autenticada corrigida ainda depende do próximo CI.
