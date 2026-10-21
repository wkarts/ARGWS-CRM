# Recorrência de tarefas próprias por empresa — incremento 453

## Origem desta entrega

Esta implementação foi reconstruída explicitamente a partir do HEAD remoto
`543ff96fe2a49ceef48d8595a58e8400befc6266` após a perda do executor anterior.
Os arquivos e resultados da tentativa anterior não foram recuperados e não são
tratados como evidência desta árvore. O relatório em
`evidence/task-recurring-453-results.json` identifica os comandos executados,
hashes e limites das novas provas. O SHA publicado deve ser registrado pelo
responsável pela composição final da PR, sem atribuir antecipadamente um SHA.

## Caminho operacional

Uma tarefa própria de projeto ou avulsa é escolhida como modelo. A agenda registra
empresa, ator original, modalidade, ID e revisão do modelo, texto, responsável,
projeto quando aplicável, checklist reaberto, prioridade e calendário. O contrato
é imutável; alterações posteriores na tarefa original não alteram a agenda.
Uma mudança de modelo ou calendário exige cancelar a agenda e revisar outra.

Cada ciclo cria uma tarefa com identidade nova pelo inseridor canônico da mesma
modalidade. O estado inicial é **A fazer**, o checklist começa desmarcado e a
prioridade é a capturada no modelo. Comentários, horas, anexos, dependências,
faturamento e seguidores do modelo não são copiados. O auto-follow opt-in do
criador continua sendo consumido na empresa do ciclo pelo writer existente.
Defaults de criação comum, timers e auto-stop 451 permanecem nos serviços
originais; a recorrência não inicia ou para timers.

A origem deve continuar pertencendo à empresa e, em tarefas de projeto, ao mesmo
projeto. O responsável capturado deve continuar autorizado. O projeto deve estar
ativo e o prazo gerado deve caber em seu intervalo. Na criação e na retomada,
primeiro e último prazo restante são conferidos. Um modelo sem responsável deve
receber uma atribuição autorizada antes de criar a agenda; não é atribuído
automaticamente ao master pelo job.

## Calendário e conclusão

| Campo | Contrato |
|---|---|
| `unit` | `day`, `week` ou `month` |
| `interval` | Inteiro de 1 a 120 |
| `first_date` | Primeiro dia civil de geração, `YYYY-MM-DD`, ano a partir de 2000 |
| `timezone` | Identificador IANA reconhecido pelo PHP, incluindo `UTC` |
| `cycles` | Quantidade finita de novas tarefas, de 1 a 120 |
| `due_days` | Prazo em dias civis após cada geração, de 0 a 3650 |
| `require_previous_done` | `0` ou `1`; condição descrita abaixo |

Datas usam aritmética gregoriana civil. A frequência mensal mantém o dia do mês
original e limita esse dia ao último dia de cada mês: 31/01 → 28/02 ou 29/02 →
31/03. Não soma uma duração fixa em segundos nem deriva o mês seguinte da data já
limitada de fevereiro. A conversão do relógio UTC para o fuso da agenda decide
quando o dia chegou. Mudanças de horário de verão não alteram a sequência de
datas ou a quantidade de ciclos. Dias historicamente pulados em um fuso não
colapsam duas posições do calendário; um processamento posterior pode alcançar
essas datas civis em atraso.

Com `require_previous_done=1`, o **primeiro** ciclo exige que a tarefa usada como
modelo esteja concluída. Os ciclos seguintes exigem a conclusão da **tarefa
criada no ciclo imediatamente anterior**. Checklist aberto ou timer ativo não
é tratado como conclusão. A condição não é financeira e não consulta faturas.

Uma execução processa no máximo um ciclo por agenda selecionada. Atraso no cron
permite recuperar ciclos em chamadas seguintes, preservando as datas originais.
O contador e `next_date` só avançam junto com a tarefa e seu recibo confirmados.
Depois de `cycles` gerações, o estado é `completed` e `next_date` é nulo.

Estados: `active`, `paused`, `cancelled` e `completed`. Pausar e retomar preservam
datas e contagem. Cancelar conserva o histórico e impede novas gerações. Não há
reinício silencioso de uma agenda encerrada, edição do contrato ou exclusão do
histórico. Há limite de 500 agendas ativas/pausadas por empresa e 1000 recibos de
controle por agenda, evitando leituras sem limite.

## GUI e API

A interface administrativa própria é `company_task_recurring/index`, com os
métodos GET/HEAD/POST admitidos explicitamente. As telas de tarefas próprias
oferecem acesso à agenda do modelo, criação, consulta e controles de estado.
O contexto selado de empresa, token do contexto, CSRF nativo e CSRF próprio são
verificados pelos controllers. Nenhuma rota de cron de tarefas legadas é liberada.

| Recurso da API nativa | Métodos | Scope | Ação |
|---|---|---|---|
| `company_operations_api/task_schedules` | GET/HEAD | `tasks:read` | Listar agendas de tarefas de projeto |
| `company_operations_api/task_schedules/{id}` | GET/HEAD | `tasks:read` | Consultar contrato e ciclos próprios |
| `company_operations_api/task_schedules` | POST | `tasks:write` | Criar agenda com modelo/revisão/calendário |
| `company_operations_api/task_schedules/{id}` | POST | `tasks:write` | `pause`, `resume`, `cancel` com revisão |
| `company_operations_api/standalone_schedules` | GET/HEAD/POST | mesmos scopes | As mesmas ações para tarefas avulsas |
| `company_operations_api/standalone_schedules/{id}` | GET/HEAD/POST | mesmos scopes | Consulta e controles da modalidade avulsa |

As rotas admitem somente esse catálogo finito. Listas usam cursor `after`; IDs de
outra empresa ou modalidade são rejeitados. A revisão da tarefa fonte é obtida
pelo leitor nativo `tasks/{id}` ou `standalone_tasks/{id}`.

Exemplo de corpo de criação, enviado com bearer próprio, `Content-Type:
application/json` e `Idempotency-Key` hexadecimal de 64 caracteres:

```json
{
  "action": "create",
  "input": {
    "source_id": 123,
    "source_revision": 5,
    "calendar": {
      "unit": "month",
      "interval": 1,
      "first_date": "2026-10-31",
      "timezone": "America/Bahia",
      "cycles": 6,
      "due_days": 3,
      "require_previous_done": 1
    }
  }
}
```

Controles usam `{"action":"pause","revision":1,"input":{}}`, trocando ação
e revisão quando apropriado. O corpo não aceita `issuer_id`, `actor_id`, token de
origem, campos legados de recorrência nem um modelo de tarefa arbitrário.

## Autoridade e credencial original

Leitura exige `tasks:view` e, para projetos, `projects:view`. Criar agenda e gerar
filhos exigem `tasks:view/create/edit`; pausar/cancelar exigem view/edit atuais.
Retomar exige que o solicitante seja o ator original, além das permissões de
criação. Mesmo o master não pode executar um job fingindo ser outro ator.

Uma agenda criada pela API guarda apenas `{token_id, scope}` no contrato, nunca
o segredo bearer. O job exige que esse token original continue ativo, não
expirado, pertencente ao mesmo ator/empresa, com `tasks:write`, e que as
permissões atuais correspondentes e `company_api:manage` continuem presentes.
`guardOriginInOwnedTransaction` é um veto dentro da transação já autorizada; um
ID de token isolado não autentica uma chamada pública nem concede privilégios.

Revogação, expiração, remoção de scope, perda de vínculo ou de permissão impedem
geração e retomada, sem avanço. Rotação não transfere a autorização do job para
a nova credencial. Uma credencial válida substituta pode ler e pausar/cancelar o
plano, mas não adotar sua origem. Agendas da GUI têm `api_origin=null` e dependem
do ator e dos vínculos persistidos, sem sessão administrativa no worker.

## Persistência e transações

A migration nativa 453 cria quatro tabelas adicionais:

| Tabela | Finalidade |
|---|---|
| `company_task_schedules` | Contrato imutável, ator/empresa, estado e calendário |
| `company_task_schedule_runs` | Recibo único por empresa/agenda/ciclo e prova do nascimento da tarefa |
| `company_task_schedule_requests` | Idempotência e resultado exato de criação e controles |
| `company_task_schedule_cursor` | Cursor de descoberta durável, limitado e sem autoridade operacional |

Não há DDL em requests, readers ou workers. O schema verifica colunas, nulidade,
defaults, collations, índices, FKs e InnoDB. A transação possui os participantes
das duas modalidades, prioridades, seguidores, recibos, origem API, ACL,
identidade, auditoria e fences de faturamento aplicáveis. Não adota uma transação
arbitrária do chamador. Schema incompatível fecha a operação antes do writer.

O claim é o lock transacional: tarefa, prioridade/auto-follow, recibo do ciclo,
auditoria e avanço do calendário são confirmados pelo mesmo COMMIT. Como não há
efeito de rede nessa execução, não há estado de envio externo nem reenvio incerto.
SIGKILL fecha a conexão e desfaz o conjunto não commitado; uma nova execução com
autoridade válida pode confirmar a mesma ocorrência uma vez. Índices únicos e
recibos auditados tornam a execução simultânea/repetida idempotente.

Os registros são relidos depois da última escrita e do último guard. A prova
inclui contrato/calendário, recibos anteriores, criança, prioridade, seguidores,
auditoria e preservação do modelo/parentesco/histórico. O estado atual da agenda
é reconstruído a partir da sequência contígua de recibos e ciclos; trocar apenas
um flag de estado não cria autorização para retomar.

## Worker e configuração de execução

O CLI existente `php index.php company_recurring_worker index` também executa
`CompanyTaskRecurring::processPending(gmdate('Y-m-d H:i:s'), 25)`. O bootstrap
continua exclusivo do CLI exato; acesso HTTP não obtém capacidade de job.

Cada chamada reserva no máximo 25 agendas de uma faixa finita de IDs. O cursor
avança também quando uma origem fica bloqueada, permitindo alcançar as empresas
seguintes; ao terminar a faixa, recomeça. Se o processo morrer depois da reserva,
as agendas ainda pendentes voltam a ser alcançadas na próxima passagem completa.
O cursor não altera a data ou a contagem de um ciclo e não autentica ninguém.

A saída registra somente quantidades `examined`, `completed`, `waiting` e
`blocked`. Um plano ativo pode continuar aguardando data/conclusão ou revisão de
autoridade. A frequência do agendamento CLI é configuração da instalação. As
provas locais não afirmam que o cron de produção foi instalado ou executado;
nenhum deploy foi autorizado por este incremento.

## Provas e limites

As suítes novas são `task_recurring.php`, `task_recurring_api.php`,
`task_recurring_integrity.php`, `task_recurring_worker.php` e
`task_recurring_concurrency.php`. Executam em banco MySQL/MariaDB real por
`ISSUERS_TEST_DSN/USER/PASSWORD`, com PHP 8.1/8.3 e 128 MiB. O teste de concorrência
exige observação dos waiters do engine; não substitui essa prova por sleeps.

Cobrem A/B/principal, ambas modalidades, calendário/fuso, meses e bissexto,
conclusão anterior, scopes/origem/rotação, revisão, replay, full-frame tardio,
falhas nas escritas, schema adulterado, cursor justo e limitado, concorrência
real, expiração durante espera e SIGKILL com retomada. Cada suíte informa o
número de asserções novas separado das 36 asserções da fixture anterior.

Instalação/upgrade, bootstrap HTTP/CSRF e o browser positivo são gates separados
da composição 453. O relatório de evidências deve registrar o resultado efetivo
de cada um; CI verde ou uma recusa 403 não substitui a execução positiva da GUI.
Não há dependência documental externa para esta recorrência local. A conclusão
desta vertical não conclui RH, Leads, Compras, todos os módulos legados, paridade
do CRM ou homologação bancária.
