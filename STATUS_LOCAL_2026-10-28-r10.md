# Relatório de status — ARGWS-CRM local r10

**Data:** 2026-10-28  
**Escopo:** implementação e validação local, sem subir alterações ao GitHub.

## Resultado

O r10 concluiu localmente a superfície oficial de Estimativas e Propostas em duas camadas: documentos e configurações. As empresas principal e secundárias usam controllers, views e serviços oficiais. As configurações agora estão admitidas por `NativeCompanySettingsController` para `settings?group=estimates` e `settings?group=proposals`, com campos completos, defaults por empresa, numeração própria para estimativas, ACL, CSRF, transação e revisão otimista.

## Matriz de conclusão

| Área | Situação local | Evidência ou limite |
|---|---|---|
| Sistema paralelo de empresas | Concluído localmente | 0 controllers `Company_*` ativos e 0 views `Company_*` ativas |
| Composição especializada | Concluído localmente | 38 operações especializadas no trait oficial |
| Estimativas e Propostas — documentos | Concluído localmente | `NativeCompanySalesAdminHttp` cobre fluxos principais, mutações, portal e arquivos |
| Estimativas e Propostas — Settings | Concluído localmente | grupos admitidos; views oficiais, defaults, sequência, ACL, CSRF, transação e revisão |
| Projetos e Tarefas | Parcial | ainda há ações antigas fora do catálogo nativo total |
| Clientes, Faturas, Despesas e Equipe | Parcial avançado | núcleos oficiais existem; todas as ações ainda não foram migradas |
| Chamados | Parcial avançado | listagem e ações oficiais existem; auxiliares ainda dependem de fechamento |
| Portal, arquivos e permissões | Parcial avançado | adaptadores presentes; prova HTTP completa depende de runtime |
| Banco do Brasil / CNAB 400 | Implementação local protegida | preparação, geração candidata, leitura e conciliação; `operational=false` sem homologação real |
| Validação de runtime | Bloqueada externamente | PHP, Docker/MySQL, Chromium, Ruby e Mix ausentes |
| CI e duplicidades | Contratos locais aprovados | 4 gates Python pass; CI remoto não foi executado nem alterado |

## Validação executada

- `node --test tests/native_sales_settings_composition.test.cjs` — 2 pass, 0 fail.
- `node --test tests/native_sales_composition.test.cjs` — 2 pass, 0 fail.
- `node --test 'tests/*.test.cjs'` — 228 total, 225 pass, 3 skip, 0 fail.
- `python3 tests/company_ui_surface_inventory.py` — PASS; 16 views e 6 controllers oficiais.
- `python3 scripts/validate-local.py` — 4 pass, 0 fail, 3 blocked; evidência em `.continua-ai/evidence/local-validation-r10/local-validation.json`.

## Bloqueios e próximos incrementos

Os bloqueios de PHP/DB/Chromium/Ruby/Mix são indisponibilidade do ambiente e não foram mascarados como aprovação. CNAB 400 exige documentação/layout e testes reais do Banco do Brasil. O próximo incremento de código é migrar as ações remanescentes de Projetos, Tarefas, Clientes, Faturas, Despesas, Equipe e Chamados para adapters oficiais e então executar os gates reais.

## Entrega

O pacote completo desta árvore é `ARGWS-CRM-LOCAL-COMPLETO-R10-2026-10-28.zip`; confira o manifesto ao lado do pacote. Este relatório, o checkpoint e o código são locais. Não houve push, merge ou deploy.
