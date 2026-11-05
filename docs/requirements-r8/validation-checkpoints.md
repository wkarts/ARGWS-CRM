# Checkpoints de validação da PR 61

Este registro vincula resultados a versões efetivamente executadas. Um resultado
de um checkpoint não é aprovação automática de código acrescentado depois.

## Primeiro conjunto publicado

- Base: `main` 4.8.8, `3cd2ac577a969840c94b019f602cb69d710c0e78`.
- Head executado: `bf0e2ce01b20ff4b635e96036af8bb9c762a69b0`.
- Gate focado: [run 42125848271](https://github.com/wkarts/ARGWS-CRM/actions/runs/42125848271).
- Resultado: preflight, contratos Node/build, Chromium e fases focadas PHP 8.1 e
  PHP 8.5 concluídos com sucesso. As fases PHP incluem MySQL real e serviços/SQL
  em SQLite descartável, conforme o manifesto daquele SHA.
- O gate HTTP completo [run 42125848624](https://github.com/wkarts/ARGWS-CRM/actions/runs/42125848624)
  encontrou erro Ajax no cenário comum `issuers_browser_smoke.cjs`: resposta 409
  de `tasks/table` durante a navegação/troca de empresa. As demais áreas desses
  shards não ficaram homologadas porque o cenário comum falhou antes delas.
- O monitor [run 42125848424](https://github.com/wkarts/ARGWS-CRM/actions/runs/42125848424)
  marcou a rodada como falha e conservou os shards diagnósticos independentes.

## Composição seguinte

Inclui formulários completos compatíveis de Propostas/Orçamentos, campos
opcionais de emissão de Notas de crédito, índice/modal/Kanban de Leads, transporte
de campos personalizados, relação exata de créditos ao projeto e cancelamento
de XHRs antigos antes da seleção. Build, testes de serviços/controladores/DOM e
contratos locais são registrados nos relatórios por área. A execução remota deve
ser associada ao novo SHA publicado; este arquivo não antecipa seu resultado.

Os cenários Chromium locais não foram executados: o ambiente bloqueou sockets e
a revisão automática recusou a escalada. O CI autorizado é o ambiente usado para
essas provas. Não há declaração de equivalência visual integral lado a lado,
PWA instalado, provedores externos reais, merge ou deploy.
