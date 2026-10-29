# Status de fechamento da continuidade — PR 52

> Registro histórico. Para o pacote posterior ao baseline `1de21abe`, consulte
> [a consolidação local da PR 52](pr52-local-consolidation-2026-10-29.md) e
> [a matriz completa das ações oficiais](pr52-official-action-matrix.json).
> As pendências e provas abaixo se referem ao checkpoint descrito neste arquivo.

Data da consolidação: 2026-10-28. Base preservada: `a5ba730aa853c4dcae780faa35d59a74ab6f27bd`; o head anterior da PR também permanece na ancestralidade.

## Entregue no pacote local

- Os 141 basenames `company_*` do baseline foram renomeados para nomes canônicos ou removidos. A árvore paralela `application/views/admin/companies/` e seus tombstones não fazem parte do índice final. O mapa completo está em `prefix-migration-2026-10-28.md`.
- Controllers públicos, views, módulos, fixtures, testes, catálogos, workflows e referências de CI usam os arquivos oficiais. Os aliases históricos continuam finitos e direcionam para os controllers canônicos; não há segunda implementação de empresa.
- Settings da empresa principal usa os mesmos adapters oficiais das secundárias, preservando ACL, CSRF, revisão e isolamento.
- Projetos tem delete e copy compostos no controller oficial, com revisão, chave idempotente e bloqueios de faturamento/tarefas pendentes.
- Estimates e Proposals usam o adapter oficial para tabela, pipeline, relações e mutações admitidas pelo catálogo nativo.
- API, portal, HR, compras, worker recorrente e operações possuem nomes canônicos com aliases históricos reatendidos pelo mesmo escopo de segurança.
- Uploads de invoices/clients têm proteção Apache e a regra de bloqueio também existe no front controller.
- Migrations de filename-only foram adicionadas para hr_payroll, multiple_companies e purchase; o release core foi gerado para `4.7.9` com migration aditiva `479`.
- A verificação física `scripts/check_native_prefixes.py` retorna zero violações no índice e tem cobertura de teste sintética case-insensitive.

## Banco do Brasil e provedores

API, CNAB240, perfis por empresa, leitura de retorno e conciliação idempotente continuam isolados por empresa. O CNAB400 agora valida a origem da fatura, largura/layout, reservas e reconciliação observacional com trilha idempotente, mas mantém `operational=false` até contrato documental e homologação bancária comprovados. Nenhum envio bancário ou baixa financeira foi inventado.

Asaas e Efí continuam condicionados aos contratos existentes e aos limites de transporte; não foi presumida homologação externa.

## Validação executada

- `python3 scripts/check_native_prefixes.py`: **PASS**, zero violações no índice.
- `python3 tests/native_ui_surface_inventory.py`: **PASS**, 16 views e 6 controllers inventariados.
- `python3 -m unittest -q tests/test_native_prefix_gate.py tests/test_invoice_attachment_storage.py`: **PASS**, 6 testes.
- Contratos Efí: **PASS**, 5 + 5 + 5 + 2 testes.
- `python3 tests/banco_brasil_bridge125_static.py`: **PASS**, 5 testes.
- `node --test` dos contratos JavaScript canônicos: contratos puros passam; casos que executam PHP são bloqueados porque este ambiente não possui `php`.
- `python3 scripts/validate-local.py`: contratos estáticos passam; `node-contracts`, PHP, Mix e Ruby ficam explicitamente **BLOCKED** quando o runtime não está instalado. A evidência local é regenerada pelo próprio script.

## Bloqueios externos registrados

Este workspace não possui PHP, Docker, MySQL, Chromium, Ruby ou Mix. Portanto não é possível declarar aqui renderização PHP, upgrade MySQL, browser real, build Mix ou homologação CNAB400. Esses gates permanecem obrigatórios no container/CI oficial da PR e não foram convertidos em falso verde.

## Entrega Git

As alterações devem ser publicadas como uma única commit normal, linear, na branch da PR 52, sem merge, force-push, reset destrutivo ou deploy. A descrição da PR precisa referenciar este relatório, o mapa de prefixos e os bloqueios externos acima.
