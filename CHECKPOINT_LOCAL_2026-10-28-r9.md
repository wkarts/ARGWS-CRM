# Checkpoint local r9 — 2026-10-28

## Incremento concluído

As rotas oficiais de Estimativas e Propostas agora admitem a composição nativa para empresa principal e secundária. `NativeCompanySalesAdminHttp` foi conectado aos métodos de listagem, formulário, PDF e vencimento nos controllers oficiais, mantendo no mesmo adaptador as operações de criar, editar, copiar, converter, alterar status, portal e anexos.

O escopo de empresa reconhece essas ações como superfície nativa compartilhada e rejeita métodos fora do catálogo com `native_sales_method_invalid`. O teste de composição cobre o contrato do adaptador, controllers, rotas e escopo.

## Evidências

- `node --test tests/native_sales_composition.test.cjs`: 2 pass, 0 fail.
- `node --test 'tests/*.test.cjs'`: 226 total, 223 pass, 3 skip, 0 fail.
- `python3 tests/company_ui_surface_inventory.py`: PASS; 16 views e 6 controllers oficiais inventariados; nenhum controller/view `Company_*` ativo.
- `python3 scripts/validate-local.py`: 4 pass; 3 blocked por runtimes ausentes. Evidência estruturada em `.continua-ai/evidence/local-validation-r9/local-validation.json`.

## Pendências honestas

- As configurações nativas de vendas (defaults, sequências, opções e concorrência) ainda precisam de um adaptador oficial completo.
- Projetos, Tarefas, Clientes, Faturas, Despesas, Equipe e parte de Chamados ainda têm ações antigas fora da cobertura nativa total catalogada.
- PHP, Docker/MySQL, Chromium, Ruby e Mix não estão instalados no ambiente atual; os gates correspondentes não podem ser declarados aprovados.
- CNAB 400 está implementado para preparação, geração candidata, leitura e conciliação local, mas segue `operational=false` até layout/documentação, transporte e homologação do Banco do Brasil.

## Próxima ação

Implementar o adaptador nativo de configurações de Estimativas/Propostas e, em seguida, repetir os gates funcionais em ambiente com os runtimes necessários. Este checkpoint é local; não houve push, merge ou deploy.

O pacote completo r9 e seu manifesto são gerados fora da árvore, em `/recovered`, para preservar o código sem artefatos de empacotamento.
