# Checkpoint local r10 — 2026-10-28

## Incremento concluído

Os fluxos oficiais de Estimativas e Propostas agora cobrem tanto os documentos quanto as configurações. `NativeCompanySalesAdminHttp` permanece responsável por index/listagem, formulário, PDF, vencimento, criação, edição, cópia, conversão, estados, portal e anexos. `NativeCompanySettingsForms::availableGroups()` passou a admitir `estimates` e `proposals`, conectando o controller Settings às views oficiais, aos defaults isolados, à sequência por empresa, ACL, CSRF, transação e revisão otimista já implementados.

## Evidências

- `node --test tests/native_sales_settings_composition.test.cjs`: 2 pass, 0 fail.
- `node --test tests/native_sales_composition.test.cjs`: 2 pass, 0 fail.
- `node --test 'tests/*.test.cjs'`: 228 total, 225 pass, 3 skip, 0 fail.
- `python3 tests/company_ui_surface_inventory.py`: PASS; 16 views e 6 controllers oficiais; nenhum controller/view `Company_*` ativo.
- `python3 scripts/validate-local.py`: 4 pass; 3 blocked por runtimes ausentes. Evidência em `.continua-ai/evidence/local-validation-r10/local-validation.json`.

## Pendências honestas

- Projetos, Tarefas, Clientes, Faturas, Despesas, Equipe e parte de Chamados ainda têm ações antigas fora da cobertura nativa total catalogada.
- PHP, Docker/MySQL, Chromium, Ruby e Mix não estão instalados no ambiente atual; os gates correspondentes não podem ser declarados aprovados.
- CNAB 400 está implementado para preparação, geração candidata, leitura e conciliação local, mas segue `operational=false` até layout/documentação, transporte e homologação do Banco do Brasil.

Este checkpoint é local; não houve push, merge ou deploy.
