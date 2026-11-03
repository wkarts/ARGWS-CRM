# PR 52 — complementos de diagnóstico e inventário

Contexto: a PR foi recuperada na branch `fix/native-company-experience`; o checkpoint consolidado é `f11e52433021ce891139168ec729d03f49f5e166` e as reconciliações posteriores preservam esse commit.

## UI nativa: fixture Node e dependência real

O job `native-company-focused` sobre `9ae704c5` falhou no teste `contextual glue captures CSRF/issuer and sends no automatic provider mutation` com `ReferenceError: init_task_checklist_events is not defined`. A chamada existe no bootstrap nativo e o inicializador original permanece em `assets/js/native-tasks.js`, carregado antes de `native-company-admin.js`. O fixture passou a representar explicitamente essa dependência real, atestando a definição da função e a ordem dos scripts, sem remover a chamada nem afrouxar o contrato de ausência de solicitações automáticas.

## Tabela de tarefas: falha funcional ainda não corrigida

A suíte `Integridade das stacks` continua apresentando `DataTables warning: table id=tasks - Ajax error` em diferentes etapas. O smoke agora captura somente caminho fixo `/admin/tasks/table`, método, status e tipo básico da resposta para identificar a origem, sem revelar parâmetros, dados, cookies, tokens ou corpo de resposta. **Não há confirmação da causa nem aprovação desse fluxo.** O gate continua exigindo zero erros JavaScript.

## Inventários

O inventário operacional foi regenerado a partir das fontes PHP presentes e reproduz o hash de origem `f4a3cb499287df0a4ffb97957cb1ed8ddf67aecc40d72fa3c134964e9e8c4de2`. A prontidão permanece `false`, rollout `blocked` e nenhum método foi promovido a equivalente. O inventário de isolamento PHP foi atualizado separadamente com 676 fronteiras, zero diferenças e `operational_rollout=blocked`.

**Limite do checkout recuperado:** a exportação imutável exclui dependências reinstaláveis. O inventário *amplo* `scripts/inventory-company-boundaries.py` conta explicitamente essas dependências, por isso a saída sobre o snapshot incompleto contém 6.792 caminhos em vez de todos os arquivos da árvore GitHub. Essa saída **não** deve substituir `docs/companies/boundary-inventory.json` sem gerar o relatório no checkout completo. O CI sinaliza o manifesto amplo stale, e esta pendência continua bloqueante.

Não há evidência de homologação externa CNAB400; a operação permanece desativada. Sem merge, deploy, release, force-push ou limpeza remota de cache.
