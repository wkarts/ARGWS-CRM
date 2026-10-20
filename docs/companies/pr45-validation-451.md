# Validação 451 — parada automática própria dos cronômetros

Núcleo **4.5.1/451**, NOOP oficial contíguo ao publicado450 `7762a62f4313e2f4ff30ab05093a8240eb77bda6`. Banco do Brasil **1.2.3/123** e Asaas **1.5.3/153** permanecem inalterados. SHA publicado e checks efetivos estão na PR45; resultados anteriores não certificam o SHA seguinte.

## Contrato e implementação

`CompanyTaskTimerStopPreferences` mantém `auto_stop_tasks_timers_on_new_timer` em nó próprio `task_timer_stop_preferences`, OFF por ausência inclusive na principal, sem herança de opção global. Grupo visual **Parada do cronômetro anterior** em Configurações, `group=task_timer_stop`, revisão otimista, CSRF/contexto e permissões atuais. O nó449 de transição de estado e seu contrato estrito permanecem independentes.

Os dois writers existentes compõem o encerramento e o novo início na mesma transação nativa. `CompanyTaskAutoStop` só encerra o único timer do mesmo ator e da mesma empresa, entre tarefas de projeto e avulsas. Autoridade, responsável/projeto ativo, referências e fences de faturamento do objeto anterior são verificadas independentemente; a política não concede permissão. Mesmo o master não modifica silenciosamente um timer de outra empresa: deve encerrá-lo no contexto proprietário.

A parada conserva o estado anterior, grava somente o intervalo até a mesma fronteira temporal capturada do novo início e incrementa uma vez a revisão anterior. Não arredonda horas, conclui tarefa, cria fatura, crédito ou recebimento. Um novo request na mesma tarefa já ativa continua recusado; o recibo original reexecutado não para/reabre nada, inclusive após mudar a política. Mais de um timer ativo preexistente é recusado, sem escolher arbitrariamente um deles.

O frame anterior, prioridade, seguidores, projeto, história completa dos timers e auditoria específica são confirmados até a última escrita. As provas do450 contra timer extra e mutações iniciais permanecem. Participantes financeiros condicionais das duas modalidades entram no lock/transaction existente; o bearer continua com o scope nativo `tasks:write`, que já cobre as duas modalidades, e sua validação final permanece ativa.

## Provas executadas

- **20 suítes SQL por motor**, MySQL 8.0.46 e MariaDB 10.11.14, PHP 8.3.6 / 128 MiB, InnoDB/binlog ON, usuário sem SUPER. Calendário e suas seis corridas, transições/faults/concorrência anteriores, novo auto-stop/Settings/concorrência, App451, faturamento/timesheets/tarefas avulsas/lotes/projeto fechado, LOCAL clock e recorrência financeira.
- **Quatro comandos adicionais PHP 8.1.2 / 128 MiB em MySQL**, no mesmo servidor vivo antes da parada: Settings, integridade/positivos, concorrência e runner451. Não houve reabertura de datadir para essa prova.
- Settings real:43 checks adicionais/177 cumulativos, A/B/principal, enum/overpost/revisão, falha final de metadata/JSON/grant, rollback e retry.
- Auto-stop:102 checks adicionais/328 cumulativos; quatro pares de modalidades nas três empresas, OFF próprio apesar de globalON, repetição após mudança de política, recusa entre empresas e bearer. Integridade acrescenta30/358 cumulativos: permissão do projeto anterior, outro ator, projeto encerrado/horário futuro, metadata e SQL de parada ignorado, projeto/seguidores alterados. **14 testemunhas SQL reais** nesse conjunto, com rollback exato e retry; nenhuma falha de driver conta como veto do serviço.
- Concorrência:74 adicionais/300 cumulativos, **dez cenários com dois waiters InnoDB observados**: revisão de Settings, mesmo recibo, alvos distintos/modalidades distintas, OFF confirmado durante espera, revogação de ator/edição/projeto/token e projeto anterior encerrado. Duas operações distintas podem concluir serialmente; o último timer fica ativo e os anteriores fechados uma vez, sem falsificar um conflito.
- Runner451 sobre450 genuíno: NOOP, marcador falho/ignorado/readback, retry/replay, todas as linhas/metadados preservados e preferência nova ausente/OFF sem backfill. O contador do executor é cumulativo; não representa milhares de casos independentes.
- **Fresh HTTP 451: 15 etapas PASS**, incluindo o writer original do portal antes do calendário original completo; tarifa240, LOCAL/restauração/fundos/PDF/API/CSV, defaults, prioridade, timers anteriores e auto-stop. **18.572 arquivos de runtime / 18.586 de produção** iguais antes/depois.
- **Upgrade HTTP 450→451 genuíno PASS**: blobsGit450 conferidos,315 tabelas históricas preservadas, nenhuma nova, BB123 igual. Calendário com0canônico, timer anterior e auto-stop Chromium A/B/principal;12 pares de modalidades, CSRF403, contexto/revisão409, foreign409, recibo e finanças/opções inalteradas. Sem reset ou semeadura de marcador/schema.
- **143 testes Python**,30,692 s;20 lints PHP/CJS/shell. Manifesto produtivo e fontes de testes congelados nas suítes finais. Inventários regenerados continuam bloqueados/operational_ready=false; não são certificação universal.

A primeira tentativa do novo teste de falhas consultou um cache de existência de tabelas e tentou criar duas vezes a testemunha MyISAM. O auxiliar passou a criá-la uma vez. Outra expectativa exigia um código de veto específico quando o frame nativo já havia recusado a alteração; a lista final aceita somente vetos nativos conhecidos e continua exigindo testemunha real, rollback de todas as linhas e retry. Essas tentativas não contam como aprovação e não motivaram aumento de limite/timeout ou mudança de regra do produto.

O harness HTTP local utiliza o instalador, App runner, controllers e GUI reais, mas um adaptador restrito da CLI Docker para PHP-S/MariaDB locais. A prova do workflow real de containers é separada e deve ser conferida no SHA publicado. Os arquivos físicos sintéticos de upgrade foram arquivados e verificados por SHA256 antes da liberação de espaço; nenhum dado de produção foi usado.

## Documentação bancária e limites

Revisão primária de 20/10/2026 em `docs/banco-brasil-remaining-contracts.md`: CBR641 de junho/2024, posições85–86/nota30, e juros herdados/nota10; CBR643 de julho/2023, trailer26–39/picture9(013)v99. As dúvidas específicas continuam abertas; a consulta atual do endpoint OpenAPI não ficou acessível e a captura3.2.2 anterior permanece histórica. Não se inventou código BRL, isenção, largura/escala ou identidade/semântica de crédito API18/19.

Auto-stop próprio está entregue neste incremento; regras adicionais de timers, recorrência operacional de tarefas e demais configurações/consumidores continuam pendências técnicas. Recorrência de faturas/rascunhos e seu predicado canônico já foram entregues e preservados. Reversão da nova base tarifária, composição/IOF/outros/parciais e comandos/adaptadores restantes também precisam código/provas próprios. Documentos bancários específicos e homologação autorizada são dependências externas distintas. Nenhum merge, deploy, tag, force-push ou operação financeira real.

Evidências: `evidence/resume451-sql-current.json` e `evidence/resume451-http-current.json`. A descrição da PR vincula o SHA efetivamente publicado aos checks, sem transferir aprovação entre incrementos.
