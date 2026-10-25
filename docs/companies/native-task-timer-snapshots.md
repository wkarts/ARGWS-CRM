# Metadados do cronômetro nativo

`NativeCompanyTaskTimerDetailsSchema::ensure()` é um helper aditivo da migração
474. Não cria outro domínio de tarefas ou duração: `source_kind` discrimina os
cronômetros canônicos de projeto, avulsos e em branco, mantendo seus IDs.
A constraint `PREFIXntd_issuer` tem nome explícito curto. Não há preenchimento
retroativo nem alteração dos registros antigos na instalação/reexecução.

O início de um cronômetro em branco captura `NativeCompanyStaffRates` na mesma
transação. Taxa desconhecida continua `null` com origem `unconfirmed`; ausência
histórica de snapshot continua declarada como ausente. A atribuição do cronômetro
transfere a taxa capturada, não a taxa atual. A nota pertence à fonte nativa: no
cronômetro em branco permanece na coluna original, sem cópia divergente; o timer
atribuído preserva a nota fornecida no ato.

Somente prova privada emitida pelo escritor canônico durante sua transação pode
criar um snapshot. Uma transação externa ou objeto arbitrário não autoriza
reprecificar um timer antigo. O frame da operação confirma os metadados após a
auditoria. Leitura Staff usa `forStaffTimesheet(issuer, actor, subjectStaff,
sourceKind, timerId)` com `staff.view` e vínculo ativo, não concede `tasks.view`.

Validação executada: PHP 8.4/SQLite, captura, rotação posterior, transferência,
origem histórica desconhecida, A/B, permissões distintas, prova falsa e storage
incompatível. MySQL/migração 474 integral e ações HTTP/Chromium continuam
pendentes na composição. Este helper não significa que todo o fluxo
`timer_tracking`/edição/exclusão já está ligado ao controller original.

## Native assigned-timer writer

`CompanyTasks::nativeTimer` and `CompanyStandaloneTasks::nativeTimer` operate on the same canonical timer rows. They keep the historical `activity()` singleton contract unchanged. Native auto-stop OFF permits several distinct assigned timers; ON stops the actor's prior mutable timers in the same company in the same transaction. Another company's active timer is never stopped. Exact task/note revisions, assignment, policy hash, financial fences, captured rate and durable request are revalidated. Audit records identify the action and affected task sources; notes and rates are not copied into audit metadata.

This checkpoint does **not** admit `Tasks::timer_tracking`: legacy/blank automatic stops still require their distinct original contract, and the original modal/header transport must carry the native revisions and request key before admission. The final route must not be advertised as complete based on this writer alone. PHP 8.4/SQLite tests cover the actual native writer, multiple timers, note CAS, replay, journal rollback and post-audit revocation; MySQL and authenticated browser validation remain required.

## Original header transport (local wiring, admission still pending)

The original `Tasks::timer_tracking` now has a contextual branch for owned task start/stop and blank start/assignment. It uses the same response keys and original header partial, checks CSRF, canonical source IDs, task/note revisions and a durable request key. Shared `timer_action` supplies those facts from the original DOM. The actual picker retains the server's typed revision. A failed network request preserves its request key; it does not silently create another command. Original extracted function hashes remain intact after removing the explicitly reviewed contextual delta, with separate executable tests for that delta.

**Not complete/admitted:** original task-detail refresh (`single_task=true`), manager stop and legacy mutation branches are rejected *before* mutation until their corresponding adapters exist. They are not replaced by fake task HTML or a permissive global model. The header and endpoint must both call `NativeCompanyTimerPolicy::canStartBlank`; the one-line presentation composition patch is delivered to the integrator. A browser round trip and the missing branches are required before exposing this as complete native timer behavior.
