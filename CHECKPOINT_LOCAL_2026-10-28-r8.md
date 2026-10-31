# Checkpoint local ARGWS-CRM — r8 — 2026-10-28

## Entrega

A aplicação local foi recomposta para usar as rotas oficiais do CRM nas famílias especializadas. Controllers `Company_*` e views ativas em `admin/companies` ficaram zerados; os tombstones antigos são inertes e preservam o caminho de upgrade. Ações de projetos, tarefas, chamados, faturas, despesas, configurações e equipe agora são métodos dos controllers oficiais, com serviços, CSRF, revisão, permissões e isolamento já existentes.

Seleção/onboarding foi consolidada em `Issuers`. O catálogo de rotas, o middleware de contexto e o shell nativo foram atualizados para os caminhos canônicos. Os links internos não usam aliases `company_*`.

## Verificação

- `node --test 'tests/*.test.cjs'`: 224 testes executados, 221 passed, 3 skipped por ausência de PHP, 0 failed (a soma inclui o teste de composição r8).
- `python3 tests/company_ui_surface_inventory.py`: PASS — 16 views, 6 controllers; 0 Company controllers/views ativos.
- `python3 scripts/validate-local.py`: 4 passed; 3 blocked por runtimes ausentes, 0 failed.

## Limites comprovados

O executor não possui PHP, Docker/MySQL, Chromium, Ruby ou Mix. Portanto não é possível declarar renderização PHP, upgrade MySQL, browser responsivo ou build Mix executados aqui. CNAB 400 permanece local e fechado: geração ligada à fatura e revisão de retorno existem, mas `operational=false` até confirmação documental/homologação do Banco do Brasil.
