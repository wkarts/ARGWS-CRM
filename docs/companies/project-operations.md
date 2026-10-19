# Projetos e tarefas por empresa

O renderer selado oferece `admin/company_projects` e `admin/company_tasks` para
operações novas, sem invocar modelos, hooks, notificações ou leitores legados.
As cinco tabelas `company_projects`, `company_tasks`, `company_project_requests`,
`company_task_comments` e `company_task_timers` são aditivas e não adotam registros
históricos com `issuer_id` NULL. Uma tarefa pertence obrigatoriamente a um projeto
da mesma empresa por FK composta; referências de outra empresa são recusadas.

## Fluxo utilizável

1. Abra Projetos, busque o cliente acessível à empresa e crie o projeto com nome,
   descrição, responsável autorizado, início e prazo. O cliente fica fixo após criar.
2. Abra as tarefas do projeto e crie tarefas com responsável e prazo dentro do
   projeto. É possível acrescentar até30 itens de checklist e alterar seus estados.
3. Registre comentários (até500 por tarefa) e inicie/pare seu cronômetro (até500
   sessões por tarefa). Há no máximo um cronômetro ativo por funcionário, mesmo
   ao alternar empresas. O tempo usa o relógio do servidor e não gera cobrança.
4. Pare todos os cronômetros e conclua todo o checklist antes de concluir a tarefa.
   Uma tarefa pode ser reaberta enquanto o projeto está ativo. O projeto só encerra
   quando todas as tarefas estiverem concluídas. Encerrado, permanece para consulta.

Permissões `projects/view` e a capacidade correspondente `create` ou `edit` são
explícitas por empresa. Tarefas exigem também `tasks/view` e `tasks/create` ou
`tasks/edit`. Criar/editar projeto exige `customers/view` e cliente ativo próprio ou
com identidade explicitamente compartilhada. O compartilhamento de cliente não
compartilha projetos. Os responsáveis precisam de acesso ao recurso e, no caso
de tarefas, também de `projects/view`. Não há concessão por papel global comum;
a regra existente do administrador mestre é preservada.

Cada mutação revalida identidade e ACL após obter o lock global, checa a revisão,
grava o conteúdo, confirma a escrita e grava auditoria e recibo idempotente na
mesma transação. O mesmo recibo com payload diferente conflita, e revisões antigas
não sobrescrevem edições, comentários ou timers novos. Falha na auditoria/recibo
reverte a operação inteira. Semântica de datas e estados não é delegada ao cliente.
A UI usa CSRF dedicado e o contexto selado do núcleo, escapa todo texto e não cria
URLs públicas ou tokens de acesso a conteúdo.

## Schema e integração

Uma nova migration nativa deve chamar `CompanyProjectsSchema::ensure($db,$prefix)`.
A criação é idempotente, preserva o histórico e valida schema/engine/collation,
nullability/defaults, índices completos e FKs compostas. Incompatibilidade existente
falha fechada. O rollback operacional consiste em voltar ao código anterior e
manter as tabelas/auditoria; não remover dados durante downgrade.

`CompanyRequestScope` deve incluir somente `company_projects/index` e
`company_tasks/index` no renderer isolado; não liberar controllers genéricos
`projects`/`tasks`. O menu inclui Projetos com ACL `projects/view`; tarefas são
acessadas a partir do projeto, já delimitadas pela origem.

## Evidência e limites

- `tests/companies/projects_operations.php`: serviços reais com PDO SQLite/MySQL;
  A/B/principal, referência pai/filho, cliente, responsável, checklist, comentários,
  timer, replay, revisão, revogação e rollback de auditoria/recibo. SQLite não prova
  locking InnoDB; o mesmo teste executa DDL nativo em MySQL/MariaDB.
- `tests/companies/projects_concurrency.php`: requer MySQL descartável; dois workers
  observados no lock real, recibo igual converge, revisões divergentes têm vencedor
  único e revogação durante espera impede ambos.
- `tests/companies/projects_browser.cjs`: exige a fixture HTTP/MySQL descartável da
  instalação/upgrade. Chromium percorre criação A/B, tarefa, comentário/checklist,
  timer e encerramento do projeto, além de stale/cross-company. Sem chamadas externas.

No incremento inicial, orçamento/faturamento de tempo, quadro, anexos, portal,
tarefas avulsas, dependências e automações ainda não estavam incluídos. A seção
seguinte registra a ampliação posterior de quadro/anexos/dependências/avulsas.
Os gates dos consumidores legados continuam fechados.

## Operações adicionais do incremento posterior

`admin/company_project_extras?kind=project&id=…` e `?kind=task&id=…` fornecem:
quadro em colunas com transições POST pelo writer de tarefas, marcos do projeto,
marcação pessoal de seguidor, dependências acíclicas entre tarefas do mesmo projeto
e anexos privados. Dependência aberta impede conclusão; um pré-requisito não pode
ser reaberto enquanto houver dependente concluído. Marcos pendentes impedem
encerramento do projeto. Datas de marcos/tarefas permanecem dentro do projeto.

Anexos usam BLOB imutável por pai e filho, PDF até2 MiB ou PNG/JPEG normalizado
(até512 KiB,1600 pixels), até20 uploads/20 MiB por recurso no histórico. Downloads
validam tamanho/hash/tipo/owner e confirmam auditoria antes dos bytes; preview
somente PNG regravado, com sandbox/nosniff. Ocultar conserva conteúdo e recibo;
retry antigo não ressuscita arquivo. Não há URL pública. Seguir é marcação pessoal,
não envio de notificações. O quadro não é hidratado sem `tasks/view`.

`admin/company_standalone_tasks` cria tarefas avulsas sem projeto implícito. Exige
somente ACL `tasks` na empresa, preserva checklist/comentários/revisão/cronômetro,
e mantém stores e recibos distintos. Há apenas um cronômetro ativo por funcionário
entre tarefas avulsas e vinculadas a projetos. Tarefas avulsas não adotam projetos
ou tarefas NULL do legado.

Migrations novas devem criar, em ordem, base `CompanyProjectsSchema`, adicionais
`CompanyProjectExtrasSchema` e avulsas `CompanyStandaloneTasksSchema`. Nenhuma DDL
da migration inicial é reescrita. Todos os writers do incremento exigem esses
schemas, sem fallback que libere conclusão quando tabelas de dependências faltam.

Testes adicionais:

- `projects_extras.php`: marcos, seguidores, DAG/ciclo, guards reais de conclusão/
  reabertura, arquivos/bytes/rollback/hide/replay e board sem grant de tarefas.
- `projects_extras_controller.php`: controller/renderer reais, CSRF/contexto e
  quadro usando o writer; `projects_extras_concurrency.php`: workers MySQL reais
  para ciclo concorrente, uploads idempotentes, stale e revogação em espera.
- `standalone_tasks.php`, `standalone_tasks_controller.php` e
  `standalone_tasks_concurrency.php`: operação A/B/principal/task-only, templates,
  CSRF, replay/stale/revogação e disputa de cronômetro entre ambos os domínios.
- `projects_extras_browser.cjs`: Chromium/HTTP/MySQL da fixture descartável; A/B
  percorrem marcos/seguir/dependências/quadro/upload multipart/download/ocultação e
  tarefa avulsa com checklist/comentário/cronômetro/conclusão.

Ainda são pendências técnicas faturar tempo, portal, importação/exportação,
recorrência e notificações destes domínios; assinatura/integrações externas não
foram executadas. Não confundir o novo quadro com compatibilidade já comprovada
com todos os hooks e módulos legados, que continuam no gate correspondente.
