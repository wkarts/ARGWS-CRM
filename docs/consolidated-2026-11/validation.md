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

### Continuação do CI após80d64d28

O gate native-ui detectou o novo navegador público do boleto sem classificação
no inventário de aliases. Classificado explicitamente como portal, mantendo
administração com o driver nativo e sem inserir navegação administrativa no
contato. Os9 testes do driver passaram.

Despesas: o redirect após categoria perdia workspace=1, levando à lista sem
formulário de criação. Corrigido no controlador; duas verificações adicionais
POST/GET passaram em MySQL após125 verificações da cadeia existente. O fixture
aplica a migration real de descrição de categoria antes desse caso novo.
Vendas: o waiter do callback passa a capturar rejeição imediatamente para não
encobrir uma falha inicial do formulário ao fechar o navegador; nenhuma
verificação de resposta, autorização ou valores foi removida. Causa original
do formulário autenticado permanece em investigação.

### Contrato histórico BB143 e regressões finais locais

O unit8.1 do run42242247269 recusou o header143 porque o mapa explícito
do teste de retomada ainda terminava em142. Acrescentado143→1.4.3/core4.9.2
sem substituir o runtime ou alterar as expectativas históricas. Os17 comandos
PHP da suite unitária passaram em PHP8.3, incluindo195 checks de retomada.
Três handshakes TLS locais com certificados sintéticos também passaram usando
runtime PHP já iniciado; a tentativa anterior com inicialização de um container
por chamada excedeu o timeout5s, que foi preservado. Nenhuma conexão bancária.

Vendas:53 verificações adicionais controller/view/POST em MySQL passaram, após
36 de formulário e52 de views. As bordas de framework são simuladas; não resolve
por si só a falha autenticada anterior. Os41 testes Node de transporte,
preferências e navegação passaram no runtime com PHP.

CI do SHAfb51040f: BB shard0 passou em MySQL (job127976973837) e
MariaDB (job127976973868), run42242247269. Ambos executaram os30 checks
novos do portal e o upgrade real120/121/122→143. O workflow agregado ainda
falha pela expectativa de versão unitária corrigida neste commit, não por
esses shards. Localmente, transferência canônica de itens Propostas/Orçamentos
passou10 checks adicionais de falha/recuperação em MySQL após36 do formulário.

### Erro autenticado de Orçamentos localizado na fronteira HTTP

Run42242247862/job127979982747 (fb51040f) mostrou HTTP503 ao abrir o
formulário original de Orçamento. O waiter corrigido agora preserva esse erro
inicial. A causa interna ainda não foi determinada: o controlador captura a
exceção e o provisionamento descartável não habilitava logs. Adicionado evento
limitado a espécie engine/runtime, arquivo e linha, sem mensagem, trace ou
request; teste com mensagem sensível simulada confirma503 e ausência do texto
na resposta/log. Dois checks passaram em MySQL após53 do controlador.

Somente o app-config gerado pelo smoke descartável habilita log_threshold1.
O teste coleta por regex exclusivamente o evento limitado, sem publicar logs
brutos. Isso fornece diagnóstico para a próxima rodada; não declara o503
corrigido nem altera as expectativas de sucesso do formulário.

CI fb51040f/settings, job127979982820, avançou pelos rascunhos autenticados
e falhou na contagem do cabeçalho de recorrências: DOM7 vs8. A view original
contém8 colunas e marca Ano como not_visible; DataTables remove esse TH do DOM.
Corrigida a leitura para o modelo DataTables, exigindo8 colunas e confirmando
que somente o campo Ano esperado está oculto. Nenhuma coluna de produto foi
removida ou tornada visível para satisfazer o teste. Execução autenticada nova
ainda pendente.

## Orçamentos/Propostas: helpers de status ausentes no contexto nativo

Reprodução local adicional do HTTP503 observado no job sales-pdf: o bootstrap
selado de `AdminController` não carrega os helpers legados de Orçamentos e
Propostas. As views originais os chamavam; o teste de view fornecia substitutos
que ocultavam a ausência. Retirados os dois substitutos, o teste MySQL falhou
com `Call to undefined function format_estimate_status()` em `estimate.php:19`.

O formulário usa agora `NativeCompanySalesTable::statusHtml`, com os mesmos
rótulos e cores da listagem nativa, escaping de texto e sem hooks globais.
Os caminhos legados permanecem com seus helpers. O teste exige ausência das
funções legadas e confere cada rótulo de status na criação/edição de ambas as
views. A execução SQL/controlador após a correção passou (36 verificações de
formulário, 75 de views, 53 de controlador e 2 de redação de diagnóstico),
incluindo a ampliação final dos asserts de status.
Isso não substitui a execução autenticada de navegador nem comprova resolução
de todos os erros posteriores do fluxo de Vendas.

### Continuação autenticada de 6/11: resposta AJAX e contratos PIGE

No run `42250706058`, SHA `3debbcf4937fe3bc4ba61847f46133001fcaa59a`,
o formulário original de Orçamento abriu e o POST respondeu 200. O passo seguinte
falhou ao ler o corpo AJAX após a navegação (`Network.getResponseBody`). O mesmo
problema apareceu em `projects`. Um servidor HTTP sintético com Chromium
reproduziu a perda; o driver agora observa o corpo com o buffer durável do CDP,
sem substituir respostas ou alterar o DOM. A regressão verifica sucesso, ID,
destino, preview e repetição exata da requisição. Passou localmente e foi incluída
no prepare do workflow; não equivale a concluir o fluxo autenticado de Vendas.

No Banco do Brasil, o teste tentava usar os botões de canais na guia API, embora
pertençam à guia Conta. O driver abre a guia correta; o teste da view PHP real em
Chromium confirma a visibilidade desses controles. Despesas passa a selecionar a
categoria pelo dropdown visível nativo, conservando o valor e os asserts SQL.
O helper de checklist aguarda e verifica o GET de atualização do modal antes de
recarregar e testar persistência. Essas alterações aguardam nova execução HTTP.

PIGE: 85 testes do backend de referência e um teste adicional do payload de
preparação do CRM passaram; contrato, SHA, comandos e limites estão registrados
em `pige-discovery.md`. A referência permanece sem alterações.

O erro 503 ao registrar tarifa Asaas continua em investigação: o escritor passou
em MySQL com os schemas de categorias, formulário, meios e billability instalados
(157 verificações cumulativas, provedor simulado). O controlador passa a registrar
somente localização de código e classe engine/runtime para distinguir o erro do
ambiente HTTP. Não registra mensagem, argumentos, payload ou segredos.

Ainda falharam no mesmo run: botão de tipo em Contratos, abertura de Nova
recorrência e persistência do detalhe de checklist. Não são marcados como
resolvidos por estes testes locais. Cartão continua aguardando decisão específica;
SDK pausado, sem merge, deploy ou operações financeiras reais.

### Seletor e modal de tipo de Contratos

A view `contracts/native_form.php` com os CSS publicados reproduziu o botão de
novo tipo invisível: o ancestral `.input-group` tinha `display:none` porque a
classe `select-placeholder` permanecia após renderizar o Bootstrap-select.
Os handlers `rendered.bs.select` e `loaded.bs.select` existiam apenas em main.js,
que não é o bootstrap do contexto multiempresa. Foram movidos, sem duplicação,
para `NativeAdminWidgets.initSelects`, compartilhado pelos dois shells.

O teste `tests/browser/native_contract_selects.cjs` executa a view PHP real,
assets publicados e lifecycle compartilhado. Passou com dropdown visível, abertura
e fechamento do modal, acesso mobile e ausência de erros JS; chamar init duas
vezes não duplica o registro. É teste de apresentação, não CRUD autenticado.
O teste autenticado também passa a usar os dropdowns reais de cliente/projeto,
sem `force:true`. `npm run production` recompilou main.min.js; versão4.9.2 mantida.
Recorrências agora verifica separadamente a existência, visibilidade e destino
da criação antes de aguardar navegação, para não mascarar a falha original.

Correção da evidência de captura: o prepare de `72999fe5` demonstrou que
`Network.enable(enableDurableMessages)` não resolve a perda no navegador fixado
pelo CI. Esse resultado substitui a inferência do teste local anterior. O driver
passa a pausar somente a resposta real do endpoint salvo, ler o corpo via
`Fetch.getResponseBody` e liberar com `Fetch.continueResponse`, sem informar
status/headers/body substitutos. A pausa introduz atraso de observação; não é
teste de concorrência. Mantidos POST200, JSON, destino, preview e replay exato.
A regressão inclui também o bloqueio de destinos externos usado no smoke.

A biblioteca Playwright1.55.1 do lockfile foi instalada localmente. O download do
Chromium Headless Shell140.0.7339.186 retornou HTTP403 `Domain forbidden` nos
endpoints oficiais tentados pelo instalador; nenhuma restrição foi contornada.
A verificação local usa essa biblioteca com `/usr/bin/chromium`; o binário exato
permanece dependente do CI. Os 23 testes Node de lifecycle/assets/header/driver
passaram no container com PHP (zero skips). Inventário de isolamento atualizado
para o diagnóstico Asaas, mantendo estado unreviewed e rollout bloqueado.

### Diagnóstico autenticado após ddc85e10

O run 42259518504 confirmou no Chromium fixado os regressivos de seletor/modal de Contratos e captura do POST de Vendas. O grupo sales-pdf passou POST/JSON/ID/redirecionamento e falhou ao aguardar o preview; providers continua com HTTP 503 na criação explícita da tarifa. Não são fluxos homologados. O driver registra apenas status do preview e localização JS, e o coletor publica somente a classificação sanitizada de PHP, sem mensagens de exceção ou valores de requisição.

O teste BB procurava `#allowed_payment_modes`, ID ausente no select original. Corrigido para `#invoice-form [name="allowed_payment_modes[]"]`, com assert de um único select antes de conferir opções. Não altera gateway, permissões ou o formulário de produção. Reexecução autenticada ainda necessária.

Validação local desta instrumentação: seis testes Python de ambiente/redação passaram; regressão Chromium do POST com navegação passou usando Playwright 1.55.1 e Chromium instalado; sintaxe Node e diff sem erros. Nenhuma verificação de preview, idempotência ou segregação foi removida.

### Ordem de inicialização dos seletores nativos

O mesmo run ddc85e10 falhou em Contratos (botão do modal oculto) e Despesas (categoria oculta). Reproduzido localmente com a view PHP real de Contratos, CSS publicado e Bootstrap: se o auto-init de window.load antecede `NativeAdminWidgets.initSelects`, registrar `rendered.bs.select` dentro desse método perde o evento. O fixture imediato anterior não cobria essa ordem. Movidos os três handlers originais para o carregamento do script compartilhado, antes do auto-init. A inicialização/validação continua única; nenhum controle foi forçado a aparecer no teste.

Regressão Chromium/PHP passou nas ordens imediata e tardia, com abertura do modal e viewport móvel. Quatro testes de lifecycle passaram; mais 23 testes Node com PHP 8.5 passaram sem skips. O teste autenticado ainda precisa ser repetido. Banco do Brasil e ausência do link de criação de recorrências continuam com verificações e diagnósticos explícitos, sem presumir causa comum.

A tentativa local de HTTP completo usou cópia descartável e schema `argws_http_debug_ci`. MySQL 8.4 recusou FK histórica que o MySQL 8.0 do CI aceita; somente a conexão dessa cópia recebeu o modo de compatibilidade, sem alteração no repositório ou nas verificações. O provisionamento terminou, mas a tentativa HTTP retornou corpo vazio e não é evidência de fluxo autenticado. Ao faltar disco, foram removidos exclusivamente os containers debug criados nesta etapa, preservando cópia, schema e logs; os testes PHP posteriores usaram o runtime oficial Composer já disponível, menor. Não houve limpeza global ou alteração de dados de produção.

### Reprodução autenticada do preview de Orçamento e do link de recorrências

Em cópia descartável do CRM com PHP 8.3.36 extraído da imagem local existente, MySQL8.4 e Chromium, foram criados administrador, empresa e cliente sintéticos pela interface. Após preencher os dados obrigatórios da empresa, o POST de Orçamento retornou200 e o destino `/admin/estimates/list_estimates/34359738369` retornou500: `estimate_status_color_class` inexistente em `estimates/quick_stats.php:13`. A correção anterior do formulário não cobria esse resumo. O helper de apresentação escopado agora fornece a mesma cor para badges e estatísticas, preservando os cinco estados originais sem carregar hooks/modelos legados.

Depois da correção, a mesma página retornou200, a tabela200 e o preview200. Uma nova criação pelo driver original passou POST/JSON/ID, destino, preview e replay exato. Aceite explícito e conversão pelo navegador produziram uma única fatura nativa de100.00, sem chamadas a provedores. Estes são fluxos autenticados locais com dados sintéticos; não substituem a suíte A/B completa do CI nem homologação financeira. O link original de PDF também retornou200, assinatura `%PDF-` e texto extraído com o orçamento convertido, empresa/cliente sintéticos, linha e total100.00.

Regressão adicionada a `tests/companies/native_sales_table.php`: renderiza a view real de resumo, garante ausência do helper legado, cinco filtros, cores preservadas e recusa de status desconhecido. Passou em SQLite (69 checks do conjunto de tabela +8 novos) e MySQL (68+8), além dos pré-requisitos reais de origem, pipeline e mutações. O driver passou a exigir também HTTP200 da página de destino, evitando esconder outro500 atrás de timeout do preview.

Na mesma sessão autenticada, o link de recorrências estava visível e apontava corretamente para `recurring?new=1`, mas o ícone decorativo compunha o nome acessível “+ Nova recorrência”. Com `aria-hidden=true` somente nesse ícone, o teste exato encontra uma única “Nova recorrência”, mantendo os asserts de permissão, destino e visibilidade. Nenhuma ACL foi alterada. O fluxo completo de modos recorrentes permanece dependente da nova rodada CI.

### Tarifa Asaas: transação proprietária e versão1.7.1

O fluxo autenticado sintético reproduziu HTTP503 ao registrar a tarifa depois da consulta canônica e liquidação simuladas. O diagnóstico local delimitou `issuer_owned_transaction_required` à ausência de `asaas_company_manual_settlements` no conjunto da transação de `CompanyFeeExpense`: o veto de recebimento manual introduzido anteriormente passou a consultar essa tabela. Incluída a tabela quando instalada; nenhum guard ou lock foi removido. O teste de tarifas agora instala o schema manual real e falhou antes da correção tanto em SQLite quanto em MySQL, passando depois:39 checks específicos,158 cumulativos em cada motor.

Na mesma cópia HTTP, a ação original passou a retornar200, com uma única vinculação e uma despesa registrada de100centavos. O provedor foi simulado exclusivamente pela fixture CLI; nenhuma chamada ou cobrança real ocorreu. O módulo avança para1.7.1/migration171 NOOP, sem DDL/backfill/comando financeiro. Runner nativo MySQL:302 asserts, incluindo falha de tracking preservando170, retry e replay171. Callback:13; regressões históricas154:48 e155:101. Reverter apresentação/código não elimina a despesa já registrada; preservar os históricos e não reduzir marcadores instalados.

O run42266932516 do SHA452b1464 confirmou a exceção no resumo de Orçamento em `quick_stats.php:13`; antecede estas correções. Seus testes autenticados de faturas e créditos passaram antes da falha em Vendas. A cópia local usa MySQL8.4 em compatibilidade de FK com8.0 e Chromium instalado, portanto não substitui o CI da árvore publicada com os binários fixados. A verificação concorrente adicional exige subprocessos PHP com o mesmo carregador/bibliotecas extraídos; a primeira tentativa falhou antes dos workers alcançarem o lock, sem evidência de defeito de concorrência do produto.

Após ajustar somente o runtime extraído para os subprocessos herdarem carregador/extensões, a suíte original MySQL passou: parcial30 e integral35 checks adicionais, incluindo espera real de dois workers no lock InnoDB, idempotência e revogação de ACL. Não foi alterado o teste ou o código desses fluxos para obter o resultado.

### Busca de catálogo e percurso autenticado de reajustes

O teste autenticado adicional abriu Propostas, criou por formulário original, validou POST200, destino200, tabela200, preview200 e replay. Também criou uma série sintética, emitiu uma ocorrência local com envio desmarcado, pausou e retomou pela interface. Uma simulação de reajuste teve vínculo revisado explicitamente, preço100→125, relatório imprimível e confirmação aplicada ao journal futuro; consulta SQL manteve base e catálogo100 e próxima data2026-12-06. Não houve envio ou provedor real.

Essa investigação encontrou aviso PHP real e opções sem nome na busca de catálogo: `searchForInvoiceEditor` retorna `name`, mas o endpoint de reajustes lia `description`. Corrigido o mapeamento de saída, sem mudar contrato público, IDs ou permissões. `invoice_repricing_http.php` executa o handler com catálogo SQL real, converte avisos em erros, exige resposta mínima/nome/preço/ID público corretos para A/B e nega contexto não selado. Falhou antes da correção e passou em SQLite e MySQL:5 verificações novas depois das62 de reajustes e pré-requisitos em cada motor. São adapters sintéticos de request/session, não servidor HTTP.

O teste autenticado existente de Vendas passa a executar revisão/simulação/confirmação de reajustes nas duas empresas, com leitura SQL de faturas, calendário/snapshot original e preço de catálogo; exige CSRF inválido recusado, journal único, retry idempotente e proposta estrangeira inacessível. Não remove etapas anteriores de Propostas, Orçamentos, recorrência ou portal. Sua execução completa no CI ainda está pendente; resultados do SHA anterior não validam esse novo percurso.

O novo helper de navegação foi executado no CRM local autenticado e passou integralmente: catálogo original, vínculo revisado, relatório100→125, confirmação explícita, CSRF inválido403, replay200 com um único journal e preservação SQL de faturas/calendário/base/catálogo. A integração desse helper na suíte A/B ainda aguarda CI. Nove testes do driver de workspace passaram; sintaxe JS/PHP e diff sem erros.

### Falhas autenticadas do SHA75fb7ac4: comandos e espécie CNAB

O run42272829451 avançou além do erro de Orçamentos: criação/preview e PDF passaram, mas sales-pdf e projects pararam no aceite. O teste procurava por role um botão ainda oculto no dropdown; o helper de abertura não conseguia descobri-lo. Os três testes de Vendas passam a usar `originalSales.command` já existente, que localiza o formulário por ação e abre o menu nativo antes do clique. Não força visibilidade nem altera o produto. CRM local autenticado reproduziu criação→PDF→menu→aceite com POST200; nove testes do driver passaram.

Banking passou as etapas anteriores e falhou no POST da emissão CNAB automática. Reproduzido em CRM/MySQL/Chromium local: espécie01 é recusada pelo gerador240 (`bb_title_profile_unsupported`) e a transação encapsulava isso como503. A fixture positiva usa agora02, já aceita pelo perfil existente. O participante bancário converte somente essa rejeição conhecida em erro de domínio, e o controller nativo retorna422; demais exceções conservam o tratamento anterior. Os códigos bancários aceitos não foram ampliados.

Banco do Brasil1.4.4/migration144 NOOP. A suíte MySQL real passou32 checks de emissão automática, incluindo duas sessões concorrentes e dois novos asserts de rejeição/rollback completo; cadeia de origem276 e arquivo de retorno35 também passaram. O runner exerceu falha/retry/replay de144 preservando143. Unitário de versão54, contratos reconstruídos196 e cinco checks estáticos passaram. Nenhuma transmissão ou operação bancária real.

CRM local autenticado confirmou rejeição422 da espécie01 sem alteração na contagem de faturas nem na sequência bancária; espécie02 criou a fatura8 pelo formulário original com replay idempotente. A requisição negativa preserva a moeda selecionada, que o submit nativo habilita antes da serialização, e os tokens originais. Sem dados ou transmissão reais.

O mesmo CI confirmou Asaas A/B: contas/credenciais, recebimento, tarifa como despesa nativa e worker opt-in. A etapa seguinte falhou porque o teste de remoção remota não abria a guia Transações; acrescentado clique na guia nativa. Tarefas recorrentes e cadastro inline de tipo de contrato ainda estão sob investigação; não declarar CI completo aprovado.

### Portal de Orçamentos — rodada4ec09a47

CI42279242212: projects/sales-pdf avançaram pelo aceite, conversão, recorrências e reajustes A/B, incluindo confirmação/replay e preservação SQL, e falharam somente no download privado do orçamento (HTTP500, CompanySalesPdf.php:13). Reproduzido em processo PHP novo carregando apenas TCPDF e o renderer: `CompanyInvoiceNativeFields` não era carregado pelo caminho do portal. O caminho administrativo e as suítes encadeadas mascaravam essa dependência. O renderer agora declara seu require próprio. Teste isolado produz PDFs reais de orçamento/proposta com campos nativos e recusa campo desconhecido:3 verificações, red antes/green depois. Incluído no manifesto de processos PHP independentes. ACL, sessão do contato, publicação, auditoria e revalidação antes da entrega não foram alteradas.

Evidência adicional de navegação local: ver [navigation-evidence.md](navigation-evidence.md). PIGE autenticado também passou Novo→Cancelar→Novo com campos temporários limpos após o evento de fechamento, zero POST de configuração e nenhum marcador sintético persistido em local/sessionStorage. O teste não cria conexões nem credenciais e não consulta o PIGE remoto.

A suíte de portal com publicação, ACL, sessão do contato, revogação e auditoria passou em SQLite e MySQL (171 verificações cumulativas por motor), com TCPDF real. O manifesto agora executa também o renderer em processo independente; plano de shards regenerado, entrada honestamente marcada sem medição pareada e14 testes do planner aprovados. O CI final ainda precisa repetir o download autenticado A/B.

### Impressão BB e categoria inline — rodada4ec09a47

Banking passou a emissão automática e encontrou403 ao imprimir. `CompanyRequestScope` conhecia os endpoints, mas `NativeCompanyRoutes` não admitia `print_cnab_boleto`/`print_cnab400_boleto`. Incluídos exclusivamente na lista POST protegida. Regressão real de admissão falhou antes e passou depois:172 verificações, incluindo as duas ações, CSRF ausente recusado, GET recusado e rota não revisada recusada. No CRM local autenticado, o formulário e o botão original retornaram200; o popup carregou o SVG do código de barras. Nenhuma transmissão, aceite ou pagamento foi criado.

Expenses parou antes do POST: `serializeArray()` entregava uma lista ao transporte protegido que aceita formulário serializado. Reprodução local mostrou `native_transport_data_invalid`; corrigido somente o chamador com `$.param(data)`. O transporte mantém contexto e CSRF obrigatórios. Modal original autenticado passou POST200, selecionou a categoria criada e preservou o valor previamente preenchido na despesa. Regressão executa handler e transporte reais, exigindo contexto, ambos CSRF, um único token nativo e texto literal preservado;12 testes Node combinados passaram.

Providers passou a remoção remota e alcançou Reembolsos; checkbox existia mas estava oculto pela guia. O driver abre explicitamente Reembolsos antes das três interações, mantendo asserts financeiros, de revogação e CSRF. Sintaxe verificada; percurso completo depende da próxima rodada CI. Não força visibilidade nem simula autorização pelo DOM.

Reembolsos foi reproduzido no navegador autenticado local: checkbox inicialmente oculto; abertura pela guia original; revogação explícita de autorização local POST200; guia reaberta com formulário visível. Fixture CLI usou somente transporte simulado. Nenhum POST financeiro externo; teste completo A/B de reembolso continua reservado ao CI.

### R01 — assunto e conteúdo de Proposta no PDF

Inspeção do renderer mostrou que `native_proposal.subject/content`, preservados no snapshot e na prévia, eram ignorados pelo PDF. A regressão em processo independente falhou antes da correção por ausência do assunto no texto extraído pelo `pdftotext`. O PDF agora inclui assunto e conteúdo do próprio snapshot, insere a tabela na posição de `{proposal_items}` e conserva a tabela ao final quando não há marcador. Não altera o snapshot, estado, valores ou permissões.

O conteúdo usa o mesmo HTMLPurifier do writer, com política específica de PDF que remove imagens e desabilita recursos externos. Links permitidos podem aparecer como anotações; não autorizam download. Imagens do conteúdo não são renderizadas; logo e assinatura já validados pelo pipeline existente permanecem separados. Não foi introduzido autosave, envio, coleta de assinatura ou edição de documento emitido.

Teste real TCPDF+pdftotext passou: assunto, conteúdo anterior/posterior aos itens, ordem do marcador, fallback sem marcador, rejeição de script/elemento tcpdf/imagem/URI file e separação de metadados de Proposta em Orçamento. A ausência de conteúdo próprio em snapshots históricos conserva o renderer anterior. Isso fecha esta omissão específica, não a paridade integral R01.

Suíte completa de preferências/PDF com fonte SQL real:436 verificações cumulativas SQLite e437 MySQL (129 específicas de PDF em cada motor), incluindo A/B/principal, formatos, orientação, replay/conversão e preservação de snapshots históricos. Regressão do sanitizador usado nas views nativas também passou. No CRM local autenticado, uma nova Proposta sintética criada pelo writer canônico retornou PDF200; o texto extraído confirmou assunto e corpo ordenado. Nenhum envio de email ou provedor. CI do próximo commit ainda pendente.

## Pix Automático — consulta administrativa 188

Consulta pontual por conta/ambiente na configuração nativa; somente GET e DTO mínimo. MySQL com transporte simulado, controlador CSRF/tipos, quatro cenários Chromium e migration188 (373 asserts) executados. Nenhuma autorização criada ou transação financeira. Adesão, jobs e webhooks Pix ainda não implementados; não declarar Pix Automático completo.

## Relatórios integrais da conta189

Executados:24 scripts de contratos do workflow Asaas em PHP8.3.36 (incluindo155 assertions novas dos20 tipos de relatório e falhas de exportação); MySQL8.4 com26 verificações adicionais de isolamento e permissões; controlador28 guards; runner nativo377 asserts; históricas154/155:48/101; seis cenários Chromium1280/390 de catálogo, ACL, escape e formulários. Sintaxe e diff verificados. Provedor simulado em todos os casos; nenhuma consulta a conta real. Inventários continuam `operational_ready=false`/rollout bloqueado, sem confundir inventário com homologação.

### Validação190 — Pix observacional (provedor simulado)

- `tests/asaas_module_migrations.php`:383 verificações MySQL; DDL parcial, tracking, retry e replay190.
- `tests/companies/asaas_pix_inbox.php`:57 verificações adicionais MySQL; autenticação, dedup/conflito, IDs iguais por conta, ACL, lease concorrente, revogação antes/depois de HTTP, instrução/autorização divergente, retry limitado, rollback de prova e ausência de efeitos financeiros.
- `tests/browser/asaas_automatic_pix.cjs`:4 cenários Chromium, permissões, POST, revisão/CSRF, escaping e responsividade390/1280.
- Contratos webhook171, callback-version13, callback-route29, webhook-route32 e protocolo remoto passaram localmente.
- CI do commit anterior3e087b8c: workflow Asaas concluído com sucesso; demais workflows ainda pendentes na consulta. Não representa CI do próximo SHA.
- Inventários continuam operational_ready=false/operational_rollout=blocked. Nenhum gate foi removido; inventário não é homologação R01–R11.

Controller Pix:4 guards adicionais aprovados (CSRF e revisão), fixture com migrations de origem/edição presentes; nenhuma chamada externa.

### Asaas191 — prova histórica e reembolso por parcela

- MySQL descartável, provedor simulado:33 verificações novas em `asaas_installment_refunds.php`, após suites reais de revisão/associação/recebimento. Captura do grupo, rejeição de grupo/número divergentes, único POST, prova corrompida/rehash, rollback tardio, duas conexões simultâneas com waiters InnoDB, revogação, declaração idempotente e preservação de recebimento irmão/bytes originais.
- Regressão `finance_resume_439_asaas.php`:192 verificações cumulativas aprovadas (ciclo de reembolso avulso e retomada).
- Runner nativo:387 verificações MySQL, incluindo191 falha de tracking/retry/replay e preservação190.
- Chromium:10 cenários de grupos desktop/mobile e permissões; link de reembolso aponta exclusivamente para a parcela recebida escolhida.
- Sintaxe PHP, callback-version13 e contratos de relatórios155 aprovados. Nenhuma homologação financeira ou chamada real.

### Asaas192 — contrato de autorização e identidade inicial

- 69 verificações PHP com transporte sintético: campos/estados inválidos, conciliação imutável, datas, identidade inicial sem externalReference, divergências, falta de retry e ausência de falsa baixa/consentimento.
- 391 verificações do runner nativo em MySQL descartável, incluindo falha/retry/replay192 sem reduzir191.
- Regressões: callback-version13, política de retry847 (middleware real com transporte sintético) e instrumentos/allowlist aprovados.
- CI Asaas do SHA anterior6429371300bd0f6913b1b251b046b11eadc53f2a aprovado. Não comprova CI deste incremento nem adesão financeira integrada.

### Asaas193 — reserva e recuperação integradas ao journal de faturas

- `tests/companies/asaas_pix_reservations.php`:31 verificações adicionais MySQL após a base financeira existente. Duas conexões aguardam o lock InnoDB: retry converge para uma origem e revogação bloqueia ambos. Cobertos rollback tardio, chave divergente, bloqueio de emissão comum/recebimento manual, conta estrangeira, contrato remoto divergente, revogação pós-GET, estado fora de ordem, prova corrompida e preservação de pagamentos/jobs/identidade da origem. Provedor inteiramente simulado, apenas GET.
- Runner nativo:395 verificações MySQL; migration154:48; migration155:101; callback-version:13. Falha/retry/replay193 preserva192.
- CI Asaas do SHA anterior3ee0699b5bde125c0c1a7aa7b84b4026fd636775 aprovado em contracts8.1/8.5, MySQL e settings-ui. Não representa aprovação do próximo SHA.
- A sequência local de33 scripts iniciada antes deste incremento continua separada: a árvore mudou durante sua execução, portanto não é usada como prova de um único SHA final. Sem testes HTTP/browser novos porque nenhuma rota/view de adesão foi exposta neste incremento.

### Asaas194 — recuperação automática, consentimento e claim

- `tests/asaas_pix_recovery.php`:10 verificações com leitores sintéticos; paginação, contratos duplicados, cliente divergente, ausência, limite e consistência.
- `tests/companies/asaas_pix_recovery.php`:8 verificações MySQL adicionais à suíte193; busca seguida de GET canônico, ausência/conflito sem reenvio, revogação entre consultas, retry por ID conhecido e preservação da reserva.
- `tests/companies/asaas_pix_consent.php`:45 verificações MySQL adicionais; contato/empresa/permite financeiro, email verificado, aceite estrito sem coerção, revisão obsoleta, rollback da auditoria, idempotência, ator revogado, autorização já observada impedindo POST novo e dois processos concorrentes aguardando lock real. Apenas um recebe claimed=true; revogação sob lock impede ambos. Executor de POST testado com provedor sintético em sucesso, timeout e revogação durante HTTP; retry nunca duplica e recuperação usa GET. Evidência técnica exclui QR e exception do provedor. Nenhuma operação bancária real.
- Runner:399 verificações MySQL;154/155:48/101; callback13. Sintaxe PHP e contrato de recuperação passaram localmente. Suites encadeadas não devem ter seus contadores cumulativos somados como cenários independentes.
- CI Asaas do anteriorc056bcf18ef612ce15755822ef669467e4e7f776 passou em contracts8.1/8.5, MySQL e settings-ui. UI/HTTP de adesão permanecem sem implementação e sem comprovação neste incremento.

## Incremento 195 — evidências locais

- `tests/companies/asaas_pix_portal_controller.php`: 23 verificações adicionais em MySQL e controller nativo, após a cadeia194. Revisão/checkbox/CSRF, 303, repetição, isolamento, cache privado e recuperação QR por transporte sintético; identidade revogada e expiração impedem entrega. Não é teste HTTP por socket.
- `tests/companies/asaas_pix_portal_browser.cjs`: template PHP real em Chromium desktop1280/mobile375; consentimento desmarcado, validação required, campos de revisão/CSRF, ausência de recursos de pagamento embutidos. Usa HTML gerado pelo controller, não sessão de produção.
- Runner nativo `tests/asaas_module_migrations.php`:403 verificações MySQL, incluindo tracking194→195, falha, retry e replay.
- `tests/companies/asaas_pix_initial_receipt.php`: escritor nativo + MySQL, duas conexões/processos concorrentes, GETs simulados; 21 verificações adicionais passaram, incluindo autorização REFUSED com recebimento comprovado, prova adulterada/obsoleta, falha tardia e repetição. Sem chamadas financeiras reais ou homologação bancária.
- QR permanece fora da rota de pagamento até integração dos fluxos operacionais/jobs; não se declara adesão ponta a ponta. Inventário mantém rollout bloqueado e lacunas explícitas.

## Incremento 196 — evidências locais e limites

- `asaas_pix_portal_enrollment.php`:13 verificações MySQL/controller adicionais; envio único, timeout/recuperação, ausência remota, QR privado e ausência de payload em sessão/journals. Transporte sintético, sem HTTP financeiro real.
- `asaas_pix_enrollment_browser.cjs`: Chromium1280/375 sobre HTML real gerado pelo controller; SVG local e copia/cola, sem requisições externas e sem overflow. Usa setContent, não uma sessão HTTP autenticada por socket.
- `asaas_pix_receipt_jobs.php`:28 verificações MySQL adicionais; callback deduplicado, valor do payload ignorado, ausência de backfill, isolamento, desativação durante GET, rollback tardio, retry idempotente e dois processos sob mutex real. Provedor simulado.
- `asaas_pix_inbox_controller.php`:8 verificações adicionais, incluindo CSRF nas quatro novas ações; testes existentes preservados.
- Runner nativo:407 verificações MySQL, incluindo falha/retry/replay196. Migrations154/155:48/101; callback13; todos os26 scripts de contratos sem rede do workflow Asaas passaram. Contadores de suites encadeadas não devem ser somados como cenários independentes.
- Regressão `asaas_checkout_portal.php`:24 verificações adicionais passaram (174 cumulativas) com instalação antiga. No CI195, registry-shards(8.1,1), job128336479450, falhou ao consultar active_slot antes do gate de versão. O aggregate cancelou a execução Asaas42349918085. A correção196 antecipa o gate; esses resultados locais não comprovam CI remoto196.
- Inventários continuam com rollout bloqueado; nenhuma homologação bancária, implantação, cobrança, transmissão ou operação real foi realizada.
