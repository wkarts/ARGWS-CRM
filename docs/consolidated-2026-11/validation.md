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
