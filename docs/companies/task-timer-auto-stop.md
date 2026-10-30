# Parada do cronômetro anterior por empresa

Em **Configurações → Parada do cronômetro anterior**, a opção começa desligada em cada empresa, inclusive na principal. Quando ligada, iniciar seu cronômetro encerra o anterior da **mesma empresa**, em tarefa de projeto ou avulsa, se suas permissões e as condições atuais da tarefa anterior permitirem.

A tarefa anterior conserva o estado. O intervalo termina no mesmo instante em que o novo começa. Não há arredondamento, conclusão automática ou cobrança. Um cronômetro de outra empresa deve ser encerrado nela; a opção da empresa ativa não concede acesso ou alteração na outra. Outro colaborador continua com o próprio cronômetro.

O salvamento exige permissão própria de configurações, contexto/CSRF e revisão atual. Não reaplica nada a tarefas existentes. Em falha, parada e início são desfeitos juntos; repetir a mesma operação confirmada não cria outro intervalo. O scope API `tasks:write` existente e as permissões do objeto anterior continuam obrigatórios.

Implementação451: `CompanyTaskTimerStopPreferences` e `CompanyTaskAutoStop`, dois writers canônicos e grupo `task_timer_stop`. NóJSON próprio separado da preferência449 de mudança de estado. Migration451 é NOOP: sem schema ou preenchimento automático de empresas. Regras e limites da validação: `pr45-validation-451.md`.
