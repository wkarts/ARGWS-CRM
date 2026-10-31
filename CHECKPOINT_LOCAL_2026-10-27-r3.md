# Checkpoint local r3 — 2026-10-27

## Escopo entregue neste incremento

Este checkpoint continua a convergência local da PR 52 sem publicar no GitHub,
sem merge e sem deploy.

- Removida a execução duplicada de `tests/native_header_overflow.test.cjs` do
  workflow `native-company-experience.yml`. O contrato de header agora tem um
  único proprietário: `native-header-layout.yml`.
- O teste de header mantém os três casos que renderizam PHP quando o runtime
  existe e agora registra `skip` explícito fora do container quando PHP não está
  instalado. Isso evita transformar `spawnSync php ENOENT` em uma falsa falha de
  código; o gate continua obrigatório no CI que fornece PHP.
- Adicionado `scripts/validate-local.py`, que executa os contratos disponíveis,
  registra `passed`, `failed` e `blocked` sem mascarar dependências ausentes e
  grava `.continua-ai/evidence/local-validation-r3/local-validation.json`.

## Validação executada

- `python3 scripts/validate-local.py` — 4 gates passaram: Node, inventário UI,
  contrato focado e propriedade de workflow; 3 gates ficaram bloqueados por
  `php`, `node_modules/.bin/mix` e `ruby` ausentes.
- `node --test tests/*.test.cjs` — 219 passaram, 3 ficaram `skipped` pelo
  runtime PHP ausente, 0 falharam.
- `python3 tests/company_ui_surface_inventory.py` — PASS, 88 views e 47
  controllers/endpoints catalogados.
- `python3 tests/ci/test_native_company_focused.py` — PASS, 10 testes.
- `node --test tests/native_header_overflow_runtime.test.cjs
  tests/native_header_overflow.test.cjs` — 5 passaram, 3 ficaram `skipped`, 0
  falharam.
- A checagem de ownership encontrou exatamente
  `.github/workflows/native-header-layout.yml` para o teste de header.

## Estado funcional honesto

O código ainda não satisfaz o contrato integral de fechamento da aplicação. Há
40 controllers `Company_*` executáveis e views especializadas que ainda não
foram migrados para adapters oficiais equivalentes. A validação PHP/8.1/8.5,
MySQL/MariaDB, Chromium, build Mix e a homologação bancária CNAB 400 não podem
ser provadas neste workspace sem os runtimes e o ambiente bancário. O Banco do
Brasil permanece com geração CNAB 400 vinculada à fatura, parser, reconciliação
e baixa assistida com revisão humana; não há baixa automática homologada.

Este pacote é a entrega local completa do estado atual e das correções feitas
neste ciclo, mas não deve ser interpretado como aprovação final da PR 52.

Arquivo fonte: `ARGWS-CRM-local-complete-2026-10-27-r3.tar.gz`.

## Próxima ação determinística

Executar o mesmo `scripts/validate-local.py` no container CI da matriz PHP,
depois continuar a migração de uma família completa de controllers paralelos
com testes HTTP e browser antes de remover suas rotas/views.
