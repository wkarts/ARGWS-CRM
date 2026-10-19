# Tarefas do contato em projeto privado

Este incremento implementa os consumidores nativos conhecidos de criação/edição própria, comentário e upload de tarefa pelo contato, a partir de `Clients::project`, `Tasks_model::add` e `project_task.php`. Usa permissão nativa6 de projetos, cliente proprietário privado, empresa ativa, sessão e senha finais, projeto explicitamente publicado e flags específicas persistidas por projeto. Não expõe tarefas avulsas nem adota tarefas legadas, internas ou de outro autor.

As seis regras de leitura433 continuam com seus nomes e seu próprio schema. Quatro flags novas começam desativadas, em formulário separado de **Regras e publicações de tarefas no portal do cliente**:

| Flag nativa | Ação implementada |
| --- | --- |
| `create_tasks` | Criar tarefa própria em projeto ativo, sem funcionário personificado e sem responsável atribuído (`assignee_id=0`, status inicial `todo`). |
| `edit_tasks` | Alterar nome, descrição e prazo apenas de tarefa nova criada pelo próprio contato434, com origem persistida e estado canônico explícito `unbilled`. |
| `comment_on_tasks` | Acrescentar texto literal, com autoria de contato separada e `actor_id=0` na fonte canônica. Exige também `view_task_comments`. |
| `upload_on_tasks` | Anexar PDF/PNG normalizado e ocultar somente arquivo próprio, com origem, hash, quota e recibo. Exige também `view_task_attachments`. |

O formulário nativo de checklist do portal é somente leitura (`project_task.php`, bloco `view_task_checklist_items`), sem flag de mutação. O contato não recebe conclusão, mudança de responsável, alteração de checklist ou cronômetro. Campos e flags desconhecidos são recusados pelos serviços. A descrição e os comentários são texto literal, sem HTML executável. Limites: nome191bytes, descrição/comentário4000bytes, prazo dentro da vigência; 1000tarefas/projeto,500comentários/tarefa,20arquivos/20MiB por tarefa incluindo histórico oculto. PDF até2MiB; PNG/JPEG até512KiB/1600px normalizados pelo validador compartilhado.

## Resultado e audiência

Criar ou salvar leva a **Seu resultado privado**, com evidência imutável do envio e a versão atual própria para retomar. O projeto contém **Suas tarefas e resultados privados**, com paginação50, de modo que o autor não precisa guardar um endereço para continuar. O autor autenticado pode editar suas tarefas próprias não faturadas em sequência, comentar e anexar conforme as flags; isso não cria aprovação artificial repetida.

A tarefa própria e seus resultados ficam disponíveis ao autor e à equipe autorizada. Outro contato do mesmo cliente, ainda que com permissão6 e conhecendo IDs/keys, não recebe nome, resultado ou arquivo privado. Sua lista pública continua usando exatamente433: projeto publicado, flags de leitura, publicação da revisão atual da tarefa e publicação individual de comentários/checklist/arquivos/tempos. Uma mudança autorizada pode invalidar a revisão pública anterior; a equipe republica a versão para ampliar o público, sem bloquear a sequência privada do autor.

Ausência inicial de publicação é diferente de suspensão: se a equipe criar/alterar a decisão da tarefa para `visible=0`, o autor perde também escrita, resultado e download privados, sem apagar a evidência. Retirar somente a publicação de um arquivo para outros contatos preserva a audiência privada de seu autor. Ocultar o arquivo pelo autor retira todos os bytes e mantém o BLOB e a quota histórica. Reenvio de recibo antigo não restaura publicação ou arquivo oculto. Projetos concluídos/cancelados preservam leitura histórica privada autorizada; novas escritas exigem projeto ativo.

## Faturamento e origem

O gate nativo de edição exige autoria do contato e `billed=0`. No núcleo isolado anterior434 não há vínculo de tarefa a fatura: `CompanyInvoices`, seus writers de documentos/anexos, journal e despesas não consomem `company_tasks`/`company_task_*`, e o input de fatura não aceita um ID de tarefa para criá-lo. O writer434 registra `billing_state='unbilled'` **somente na origem das suas novas tarefas**, no mesmo commit da criação. Tarefas antigas não recebem essa inferência.

Estado `billed`, desconhecido, ausente ou origem de outro contato impede editar e usar a audiência privada do autor. Não existe toggle público/administrativo para voltar a `unbilled`. Faturamento de tarefas isoladas ainda não é um fluxo implementado. Qualquer futuro writer financeiro deverá, antes de abrir esse recurso, participar da mesma transação/lock canônico, provar empresa/projeto/tarefa e registrar a transição de `company_portal_task_origins.billing_state` junto com o vínculo financeiro; estados sem confirmação devem permanecer fechados. IDs de tarefas legadas e faturas coincidentes não são prova de vínculo.

## Persistência e prova

Cinco tabelas aditivas: `company_project_write_rules`, `company_portal_task_origins`, `company_portal_task_comments`, `company_portal_task_files`, `company_portal_task_requests`. FKs compostas provam tarefa/projeto/empresa, fonte de comentário/arquivo e cliente/contato. `result_json` preserva nome do autor e conteúdo do envio, inclusive se a versão atual ou o perfil mudar. A evidência registra se a audiência privada depende da origem de autor da tarefa e revalida esse vínculo; uma publicação pública não substitui origem revogada.

Escrita, origem, revisão, auditoria e recibo são atômicos. Recibo64hex vincula empresa, projeto, tarefa, contato, operação, revisão e conteúdo. Replay exato converge; conteúdo/pai/autor diferente conflita. Todos os participantes são verificados como InnoDB; nenhuma transação externa é emprestada. Leituras, downloads e confirmação imediatamente antes dos bytes revalidam sessão/senha, contato/cliente/empresa, proprietário privado, native6, flags, autoria, billing e suspensão sob o lock canônico. O recibo não é credencial. Não há resolução genérica de arquivo por caminho, abertura de leitor legado ou transporte externo.

Provas do overlay434: SQLite serviço142/recibos91/controllers175; MariaDB10.11 serviço145/recibos93/controllers177;39cenários de escrita com dois processos e36cenários separados de resultado/download/confirm-beforebytes. Chromium real PHP8.3/MariaDB A/B e segundo contato, formulários/gui de flags, criação/retomada, PDF/PNG multipart, publicação seletiva433, retirada/ocultação/suspensão, CSRF, stale e proprietário cruzado passaram. O integrador executa `native_434.php` com a migration real434; o protótipo HTTP usa ensure/replay aditivo sobre marcador427 e não comprova tracking434. Não houve homologação bancária, assinatura, faturamento de tarefa ou envio externo.

Isso fecha os consumidores nativos de tarefas descritos nesta página. Não declara completo todo o legado de projetos: discussões do projeto, arquivos gerais do projeto pelo contato, marcos/gantt/timesheets e outros consumidores dependem de suas próprias flags e contratos específicos.
