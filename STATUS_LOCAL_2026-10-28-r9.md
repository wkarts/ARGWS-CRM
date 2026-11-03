# Relatório de status — ARGWS-CRM local r9

**Data:** 2026-10-28  
**Escopo:** implementação e validação local, sem subir alterações ao GitHub.

## Resultado desta rodada

O incremento r9 fechou a composição oficial dos fluxos principais de Estimativas e Propostas. A empresa principal e as empresas secundárias passam pelos controllers oficiais, pelo escopo nativo e por `NativeCompanySalesAdminHttp`. A superfície cobre index/listagem, formulário, PDF e vencimento, mantendo criação, edição, cópia, conversão, estados, portal e anexos no adaptador oficial.

## Matriz de conclusão

| Área | Situação local | Evidência ou limite |
|---|---|---|
| Sistema paralelo de empresas | Concluído localmente | 0 controllers `Company_*` ativos e 0 views `Company_*` ativas; tombstones inertes |
| Composição especializada | Concluído localmente | 38 operações especializadas em trait oficial |
| Estimativas e Propostas — fluxo principal | Concluído nesta rodada | Rotas e controllers r9 usam `NativeCompanySalesAdminHttp` |
| Estimativas e Propostas — configurações | Pendente | Defaults, sequências e opções ainda precisam de adaptador oficial completo |
| Projetos e Tarefas | Parcial | Rotas-base oficiais existem; ainda há ações antigas fora do catálogo nativo total |
| Clientes, Faturas, Despesas e Equipe | Parcial avançado | Núcleos oficiais e composição existentes; cobertura de todas as ações ainda não encerrada |
| Chamados | Parcial avançado | Listagem e ações oficiais existentes; integrações/ações auxiliares ainda dependem de fechamento |
| Portal, arquivos e permissões | Parcial avançado | Cobertura presente nos adaptadores existentes; falta prova funcional completa em runtime |
| Banco do Brasil / CNAB 400 | Implementação local protegida | Preparação, geração candidata, leitura e conciliação presentes; `operational=false` sem homologação documental e transporte real |
| Validação de runtime | Bloqueada externamente | PHP, Docker/MySQL, Chromium, Ruby e Mix ausentes neste ambiente |
| CI e duplicidades | Contratos locais aprovados | 4 gates Python pass; CI remoto não foi executado nem alterado |

## Validação executada

- `node --test tests/native_sales_composition.test.cjs` — 2 pass, 0 fail.
- `node --test 'tests/*.test.cjs'` — 226 total, 223 pass, 3 skip, 0 fail.
- `python3 tests/company_ui_surface_inventory.py` — PASS; 16 views e 6 controllers oficiais.
- `python3 scripts/validate-local.py` — 4 pass, 0 fail, 3 blocked; relatório em `.continua-ai/evidence/local-validation-r9/local-validation.json`.

## Bloqueios e próximos incrementos

Os bloqueios de PHP/DB/Chromium/Ruby/Mix são indisponibilidade do ambiente e não foram mascarados como aprovação. A homologação do CNAB 400 exige documentação/layout e testes reais do Banco do Brasil. O próximo incremento de código é o adaptador nativo das configurações de Estimativas/Propostas, seguido pela migração das ações remanescentes de Projetos, Tarefas, Clientes, Faturas, Despesas, Equipe e Chamados.

## Entrega

O pacote completo desta árvore é `ARGWS-CRM-LOCAL-COMPLETO-R9-2026-10-28.zip`; a conferência final deve usar o manifesto ao lado do pacote. Este relatório, o checkpoint e o código são locais. Não houve push, merge ou deploy.
