# Prioridade e acompanhamento próprios por empresa

Em Configurações, **Prioridade e acompanhamento de tarefas** (`settings?group=task_operations`) possui revisão própria, requer `settings.view/edit` e não herda opções globais, inclusive na empresa principal.

- Prioridade: 1 Baixa, 2 Média, 3 Alta ou 4 Urgente. Na ausência de configuração, usa 2. Um valor explícito na tarefa prevalece; uma edição sem prioridade mantém a atual.
- Acompanhamento automático: desativado inicialmente. Quando habilitado, inclui somente o criador autorizado de uma nova tarefa de projeto no canal canônico. Tarefas avulsas não têm esse canal.
- Alterar a preferência não reescreve tarefas ou inscrições antigas. Retry após deixar de seguir retorna o resultado original e não reinscreve o colaborador.
- Cópias revisadas de tarefa/projeto preservam a prioridade selecionada na fonte e não herdam seguidores, tempo, arquivos ou cobrança. A política do destino inclui somente o novo criador quando autorizada.

A migration448 acrescenta duas tabelas auxiliares de prioridade, com chave composta por empresa e tarefa, FKs restritivas e sem backfill. Tarefas históricas sem linha auxiliar continuam em prioridade2. Schema parcial/corrompido ou ambas as tabelas ausentes após o marcador448 falham de forma explícita.

GUI, serviços, APIs existentes e cópias usam os mesmos writers. Permissões, referências, configurações e frames são reconfirmados após auditoria/recibo; falhas fazem rollback. O calendário segue com seu próprio contrato de leitura. Timers e recorrência operacional de tarefas exigem suas próprias adaptações; esta preferência não os habilita.
