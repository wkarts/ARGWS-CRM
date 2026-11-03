# Checkpoint local r6 — convergência de recorrência, drafts/cópias e equipe

Data: 2026-10-28 UTC

## Implementado

- A rota oficial `admin/invoices/recurring` é a única UI executável para recorrência de faturas em empresa principal e selecionadas.
- As rotas oficiais `admin/invoices/drafts` e `admin/invoices/copy` passaram a ser nativas também para a empresa principal e concentram os adaptadores `NativeCompanyInvoiceDraftHttp` e `NativeCompanyInvoiceCopyHttp`.
- `Company_invoice_recurring`, `Company_invoice_drafts`, `Company_invoice_copies` e suas views paralelas foram reduzidos a tombstones inertes; os aliases antigos não possuem admissão nativa.
- A equipe usa apenas `admin/staff`, com `Staff`, `NativeCompanyStaffTable`, `NativeCompanyStaffMember` e endpoints nativos. `Company_staff` e sua view paralela foram removidos do inventário ativo.
- Links internos, browsers de rascunhos/cópias/recorrência e testes de rotas usam os endereços oficiais.
- O inventário estático foi reconciliado após as remoções.

## Validação local

- `node --test tests/*.test.cjs`: 219 aprovados, 3 skips explícitos por PHP ausente, 0 falhas (222 testes).
- `python3 tests/company_ui_surface_inventory.py`: PASS — 82 views ativas, 42 controllers/endpoints ativos.
- `python3 tests/ci/test_native_company_focused.py`: PASS — 10 testes.
- `python3 scripts/validate-local.py`: 4 gates aprovados, 0 falhas, 3 bloqueios de ambiente (PHP, Mix/node_modules e Ruby); evidência em `.continua-ai/evidence/local-validation-r6/local-validation.json`.

## Limitações honestas

PHP, Docker/MySQL, Chromium de integração, Ruby e `node_modules/.bin/mix` não existem neste workspace. Portanto, runtime HTTP/browser, upgrade histórico, build Mix, lint PHP e YAML Ruby ainda precisam ser executados no CI pinado. CNAB 400 continua dependente de layout documental e homologação do Banco do Brasil; as famílias `Company_*` restantes seguem pendentes de migração funcional completa.

Nada foi publicado no GitHub.
