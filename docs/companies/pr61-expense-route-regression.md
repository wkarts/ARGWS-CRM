# PR61: contrato de seleção da rota de despesas

Reproduzido sobre `f2807b7dab5c00c7ea8765d863608b473d59e5b7`, conforme a
falha do run Integridade das stacks 42139187051 / job 127636304208.
`native_invoice_source_route.php` aprovava os 45 cenários de faturas e falhava
em `expenses/unknown` com um identificador de despesa própria no POST.

O incremento concorrente `fde9b5db` retirou a checagem conjunta de ação/verbo
de `ownedExpenseRoute`, para que verbos inválidos em despesas próprias fossem
negados antes de hidratar o legado. Entretanto, retirar também a checagem da
ação permitia ao seletor reconhecer ações desconhecidas apenas pelo ID.
Isso prova uma regressão de seleção, não execução bem-sucedida de rota desconhecida.

A correção consulta o catálogo existente para confirmar que a ação existe em
GET, HEAD ou POST antes de examinar os IDs. Não adiciona rota, verbo ou permissão.
A identidade própria continua sendo reconhecida independentemente do verbo
submetido; a admissão real continua verificando esse verbo e nega os inválidos
com 405. Restaurar cegamente o guard antigo faria essas requisições deixarem
o caminho de negação protegido e voltarem ao fluxo legado da principal.

O teste conserva `unknown => false` e amplia a rejeição de nomes desconhecidos
para sete verbos. A expectativa de seleção para `DELETE expense` passa a true,
mas exige simultaneamente que o catálogo NÃO admita DELETE. O teste real de
middleware verifica DELETE, PUT, PATCH e OPTIONS com ID próprio: 405 e nenhuma
hidratação de `expenses_model`. Não foi relaxado gate ou removida negação.

## Validação local

- Preflight completo da etapa que falhou: número legado 12, PDF 4, API router 47,
  callback de email 21, seleção de faturas 45 e seleção de despesas 76, aprovados.
- Contexto/bootstrap: 164 checks SQLite, incluindo os novos casos de 405.
- Índice HTTP de despesas: 31 checks adicionais de controller/DOM/SQL aprovados;
  suas fixtures anteriores também passaram. Cópia de despesas: 154 checks cumulativos.
- Contratos CI Python: 93 aprovados. Contratos Node da raiz: 252 aprovados.
- Contratos Node do índice de despesas: 10 aprovados. A primeira execução não
  encontrou PHP no PATH; a execução corrigida usou o runtime local explicitamente.
- Inventário UI e censo nativo preservados; inventários de isolamento, operacional
  e amplo atualizados pelos geradores oficiais, sem promover estados de revisão.

São fixtures locais PHP 8.4/SQLite e contratos, não homologação de produção,
execução MySQL/browser de toda a frente de Despesas ou aprovação remota do SHA.
Core 4.8.9/489 e PIGE 1.0.7/107 preservados. Sem provedor, merge ou deploy.
