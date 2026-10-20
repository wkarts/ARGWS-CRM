# Cronômetros e estado da tarefa por empresa

Em Configurações, **Cronômetros de tarefas** (`settings?group=task_timers`) controla somente `timer_started_change_status_in_progress`: desligado por padrão, inclusive na empresa principal. Não lê nem altera a opção global legada. Exige `settings.view/edit` atuais e revisão própria.

Quando habilitado na empresa da tarefa, o início explícito do cronômetro muda **A fazer** para **Em andamento** na mesma operação nativa. Serve tanto para tarefa de projeto quanto avulsa, GUI e bearer existentes. Parar registra o tempo e conserva o estado; comentário, criação, cópia e leitura não disparam essa transição. Conclusão continua exigindo a ação e os requisitos próprios.

A exclusividade já existente de um cronômetro ativo por colaborador permanece: iniciar em outra empresa é recusado enquanto houver um aberto. Não há parada automática de cronômetros de outra empresa. O recibo original torna o retry idempotente, mesmo se a política tiver mudado depois.

Preferência, tarefa, prioridade, referência, ator, permissão, timer e resultado conhecido são reconfirmados após auditoria/recibo antes do commit. Revogação ou alteração final faz rollback integral. Nenhuma preferência reescreve tarefas, timers históricos, opções globais ou dados financeiros.

A migration449 oficial é NOOP: não muda schema nem preenche JSON de empresas antigas. O registro novo nasce apenas no salvamento autorizado. Parada automática, outras regras de timers e recorrência operacional de tarefas permanecem pendências técnicas próprias; recorrência de faturas já tem contrato separado.
