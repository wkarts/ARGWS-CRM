# Projetos e tarefas publicados no portal — núcleo433

Este recorte consulta o store próprio `company_projects/company_tasks`. Não lê a
flag legada `tasks.visible_to_client`, configurações globais de projetos ou tarefas
avulsas. Instalação e upgrade não atribuem clientes/empresas nem publicam registros
existentes. O editor usa a permissão existente `projects/edit`; revisar tarefas ou
seus filhos exige também `tasks/view`. Não há nova concessão nativa inventada.

## Autorização de consulta

Cada chamada recebe a sessão assinada do contato e o ID da empresa explícito. A
fonte final `CompanyPortal` é construída pelo leitor no mesmo banco e prefixo,
com a chave da instalação; nenhum contato é convertido em funcionário. A sessão
é revalidada, o contato deve manter exatamente uma permissão nativa6 e pertencer
ao cliente privado do projeto. Compartilhar cadastro não compartilha contato.
O projeto deve estar publicado e em estado consultável. Só então as regras e
publicações da tarefa permitem hidratar os campos do próprio store.

| Regra persistida | Conteúdo liberável após publicação individual |
| --- | --- |
| `view_tasks` | Lista e detalhe de tarefas publicadas na revisão atual |
| `view_task_comments` | Texto e data dos comentários publicados; sem identidade interna do autor |
| `view_task_checklist_items` | Texto e conclusão dos itens publicados |
| `view_task_attachments` | Metadados e download de anexos publicados e ativos |
| `view_task_total_logged_time` | Soma dos segundos encerrados e publicados, sem timer aberto ou horas internas |
| `view_team_members` | Somente nome do assignee atual, ativo e autorizado em projetos e tarefas da empresa; sem IDs, diretório global ou contato |

Os seis valores começam falsos por ausência de linha, sem backfill. As demais
regras desta superfície dependem de `view_tasks`. Flags desligados impedem o
SELECT da fonte correspondente; o DTO não contém aquele campo, contagem ou
resumo indireto. A lista pagina somente tarefas publicadas e não hidrata filhos.
Não expõe marcos, seguidores, dependências, finanças, timesheets completos ou
identidade de equipe como consequência da permissão6.

## Decisões explícitas e imutabilidade das fontes

O editor dedicado é `admin/company_project_portal_rules?project=ID&task=ID`.
Possui contexto selado, CSRF nativo e token próprio, revisão de projeto/regras e
recibo durável da operação. Repetir a chave preserva a decisão original sem
ressuscitá-la sobre regras posteriores; payload diferente conflita. Erro de
persistência/auditoria ou perda de grant no fechamento desfaz a alteração.

A publicação de uma tarefa registra sua revisão e hash dos campos consultáveis.
Uma edição, comentário ou operação do writer que avance a revisão torna a tarefa
privada até nova revisão/publicação. Publicar essa nova revisão não publica seus
novos filhos. Cada comentário, índice de checklist, anexo e timer encerrado tem
uma decisão própria e hash da fonte; alteração da fonte exige revisão. Arquivos
ocultos deixam de aparecer, mesmo que uma decisão antiga de publicação permaneça
no histórico. Não se alteram os bytes originais de arquivos, comentários ou horas.

Downloads exigem todos os mesmos pais, sessão, permissão, regra e publicação,
validam PDF/PNG, tamanho e SHA-256 e confirmam auditoria antes de retornar bytes.
`confirmFile` permite revalidar o arquivo e seu hash imediatamente antes da
entrega HTTP. O adapter usa cabeçalhos privados e disposition segura existentes.
Revogação no fechamento também impede retornar DTO já hidratado ou nome de equipe.

## Schema aditivo e integração do núcleo

`CompanyProjectPortalRulesSchema::ensure($db,$prefix)` cria somente:

- `company_project_portal_rules`;
- `company_portal_project_tasks`;
- `company_portal_task_children`;
- `company_project_portal_requests`.

Acrescenta o índice único `company_tasks.portal_task_origin` em
`(issuer_id,project_id,id)`, usado pelo FK composto que impede publicar uma tarefa
sob outro projeto da mesma empresa. O validador confirma colunas, engines,
índices e FKs; não corrige schema divergente silenciosamente.

A composição433 registra a rota dedicada exata no request scope com a mesma
proteção nativa dos controllers de projetos e acrescenta o link no projeto. O
controller público chama o reader após o retorno de
`CompanyPortalOperations::detail`, sem aninhar transações. A partial
`company_portal/project_tasks` entrega lista, detalhe e o POST privado do arquivo.
O controller confirma novamente a publicação/hash antes de enviar os cabeçalhos e
bytes. Não muda as APIs ou as conversas de contratos432.

A migration nativa433 e os metadados de release permanecem responsabilidade da
integração da raiz: chamar o helper aditivo após os schemas anteriores, preservar
tracking nativo e validar o pacote final. O helper não toma decisões de publicação.

O leitor possui transações independentes; não chamá-lo dentro de TX externa:

```php
$tasks = new CompanyPortalProjectTasks($db, $prefix, $installationSecret);
$page = $tasks->page($session, $issuer, $project, $after);
$detail = $tasks->detail($session, $issuer, $project, $task);
$file = $tasks->file($session, $issuer, $project, $task, $fileKey);
$tasks->confirmFile($session, $issuer, $project, $task, $fileKey, $file['content_hash']);
```

A partial recebe `project_tasks`, `project_task`, `project_id`, closures
`task_url` e `tasks_page_url`, `task_file_url` e `portal_csrf`. O POST de download
usa `action=project_task_file` e IDs explícitos; o controller aplica CSRF e o reader
antes dos cabeçalhos/bytes. Não colocar payload de tarefa no HTML após uma
exceção de permissão ou referência.

## Provas e limites

As suítes novas exercitam serviços, bancos descartáveis, controller e templates
PHP reais. SQLite traduz somente DDL; MariaDB executa engine, FKs, auditoria,
rollback e duas conexões/processos realmente observados aguardando o lock.
Cobrem A/B/principal, sessão antiga, grants/flags/pai/tarefa/arquivo revogados,
publicação individual, total de tempo sem horas internas, equipe autorizada,
soft-hide, replay, colisão de chave, revisão obsoleta, paginação e preservação dos
bytes das tabelas nativas legadas e da fatura com ownerNULL.

`project_portal_rules_schema.php` prova o helper DDL, replay e FK real, não o
tracking da migration433 ainda em composição. Os testes do controller adaptam somente o framework HTTP/contexto. A suíte
`project_portal_tasks_browser.cjs` também exercita navegador, login nativo e
sessão de contato reais em servidor descartável: A/B, seis regras, publicação,
revisão nova, bytes privados, ambos os CSRF, stale e revogação do grant nativo. O
fixture CLI aceita somente o banco de browser descartável e cria empresas e
contatos pelo writer canônico; o teste não é um endpoint público. Nenhuma escrita de tarefa pelo contato, flag de
criação/edição/comentário/upload434 ou assignee escolhido pelo contato é liberado.
Nenhum transporte externo ou homologação bancária integra este recorte.
