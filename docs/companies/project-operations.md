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

Esta vertical ainda não inclui orçamento/faturamento de tempo, kanban, anexos,
portal de projetos, tarefas avulsas, dependências, importação de projetos legados,
recorrência, notificações ou integrações externas. Esses itens são código faltante,
não homologação externa. Os gates dos consumidores legados continuam fechados.
