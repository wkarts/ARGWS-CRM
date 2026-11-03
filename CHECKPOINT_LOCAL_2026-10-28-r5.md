# Checkpoint local r5 — convergência da recorrência de faturas

Data: 2026-10-27/28 UTC

## Implementado

- A rota oficial `admin/invoices/recurring` passou a ser uma ação nativa compartilhada para a empresa principal e para as empresas selecionadas.
- `NativeCompanyInvoiceRecurringHttp`, `CompanyInvoiceRecurring` e `admin/invoices/native_recurring.php` são agora o único caminho executável para criação, leitura, pausa, retomada, cancelamento, execução, rascunho privado, condição por fatura principal e notificação opt-in.
- O antigo `Company_invoice_recurring` e a view `admin/companies/invoice_recurring.php` foram reduzidos a tombstones inertes; não carregam classe, ação, view ou formulário.
- Os browsers de recorrência, o teste de controller e os inventários foram migrados para a rota canônica.
- A tela oficial não aponta mais para o alias histórico de recorrência.

## Validação local

- `node --test tests/*.test.cjs`: 219 aprovados, 3 skips explícitos por PHP ausente, 0 falhas (222 testes).
- `python3 tests/company_ui_surface_inventory.py`: PASS — 87 views, 46 controllers/endpoints.
- `python3 tests/ci/test_native_company_focused.py`: PASS — 10 testes.
- `python3 scripts/validate-local.py`: 4 gates aprovados, 0 falhas, 3 bloqueios de ambiente (PHP, Mix/node_modules e Ruby).

## Limitações honestas

PHP, Docker/MySQL, Chromium de integração, Ruby e `node_modules/.bin/mix` não existem neste workspace. Portanto, o runtime HTTP/browser, upgrade histórico, build Mix, lint PHP e YAML Ruby ainda devem ser executados no CI pinado. CNAB 400 continua dependente de layout documental e homologação do Banco do Brasil.

Nada foi publicado no GitHub.
