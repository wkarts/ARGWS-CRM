# PR 52 — descrição local r8

Branch sugerida: `feat/native-company-closure-r8` (somente local; não há metadata Git nesta cópia).

Título: `feat(companies): compõe operações especializadas nos controllers nativos`

## Problema

A experiência de empresas ainda possuía controllers/views administrativos paralelos para operações especializadas. Isso mantinha URLs, menus e shells próprios, apesar de os serviços já terem isolamento e permissões por empresa.

## Implementação

- compõe 38 ações especializadas nos controllers oficiais de Projetos, Tarefas, Chamados, Faturas, Despesas, Configurações e Equipe;
- remove a execução dos `Company_*` e das views ativas em `admin/companies`, preservando tombstones inertes para upgrade;
- atualiza `CompanyRequestScope`, `NativeCompanyRoutes`, shell, menu e links internos para ações oficiais;
- mantém CSRF, revisão, idempotência, permissões e escopo do emitente nos serviços existentes;
- registra o fluxo CNAB 400 local sem declarar homologação bancária implícita;
- reduz a validação duplicada com um teste de composição e um inventário único de superfícies.

## Validação

- Node: 224 executados, 221 passed, 3 skipped por PHP ausente, 0 failed;
- inventário UI: PASS;
- focused CI/workflow ownership: PASS;
- PHP/Mix/Ruby: bloqueados porque os executáveis não existem neste ambiente;
- Docker/MySQL/Chromium e homologação CNAB 400: aguardam executor/homologação próprios.

## Escopo da entrega

Somente arquivos locais. Nenhum push, merge ou publicação foi realizado.
