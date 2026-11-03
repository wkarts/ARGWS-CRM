# Inventário auditável — superfícies administrativas por empresa

## Escopo e natureza da evidência

Inventário atualizado em 2026-10-26 a partir da árvore do PR #52. É **estático**, não captura de tela nem prova de execução PHP, permissão, responsividade, carregamento de assets ou visual.
São 87 views ativas e 46 controllers/endpoints ativos; nenhum valor de configuração, token, certificado ou dado de produção foi consultado. Dois fragments substituídos pelas rotas oficiais permanecem apenas como tombstones inertes por causa da sincronização do workspace (`admin/companies/leads_fields.php` e `admin/settings/company_scoped.php`) e são recusados pelo inventário caso voltem a declarar classe, ação, dispatch ou markup. Os controllers e páginas `Company_clients`/`Company_leads` foram removidos fisicamente.

O manifesto `tests/fixtures/company_ui_surfaces.json` registra caminho, contrato,
justificativa, proprietários literais, métodos públicos e contagem de marcação
literal (table/button/input/select/textarea). O teste
`python3 tests/company_ui_surface_inventory.py` descobre novamente o escopo;
novas views/controllers, novos dispatches, páginas sem shell pareado e alterações
de controles exigem revisão explícita. Inclui endpoints sem UI para que adicionar
uma view a callback/bridge não passe despercebido. Não há exclusão por conter
segredos: OAuth, certificados, tokens de API e Asaas são inventariados.

Contagens de tags são aproximações lexicais: PHP que gera HTML precisa fixture de
renderização. Hidden/radio/checkbox/file/submit/button/reset/color/range não são
candidatos automáticos a `form-control`; devem conservar semântica própria.
Classes ausentes no PHP não provam defeito se o shell normaliza o controle.
Componentes head/tail não têm contagem congelada: são auditados aqui, mas sua
implementação evolui sob os testes específicos de fidelidade do shell.

## Convergência de navegação

O shell empresarial agora aponta para os controladores oficiais do CRM nas áreas de Vendas, Despesas, Projetos, Suporte e Relatórios: `admin/invoices`, `admin/expenses`, `admin/projects`, `admin/tickets` e `admin/reports`. Calendário e configurações usam `admin/utilities/calendar` e `admin/settings`. Os aliases `company_*` continuam isolados apenas nas operações especializadas que ainda não possuem adapter nativo equivalente; eles não são destinos do menu principal.

## Contratos e gaps

- 46 páginas internas: par `admin/companies/layout_head.php` / `layout_tail.php`.
  O shell usa assets públicos nativos; não deve chamar `init_head` e hidratar
  menus/hooks globais num contexto empresarial isolado.
- 1 página legada (`issuers/manage`): par nativo
  `init_head` / `init_tail`, com contexto/autorização a verificar no runtime.
- 40 fragments: herdaram layout da página proprietária; adicionar shell próprio
  geraria duplicação. Seus proprietários estão no manifesto.
- Onboarding: documento do modal same-origin com CSS nativo e script de contexto;
  não deve duplicar sidebar/header. Exige teste próprio de modal, fechamento e foco.
- **Gap explícito:** `admin/issuers/certificate_link.php` não tem chamador literal
  localizado em application/modules. Não atestar renderização/uso; investigar antes
  de reativar/remover. A configuração de certificados usa o grupo oficial `admin/settings?group=certificates`; não existe controller ou view paralela de certificados.
- Asaas: duas tabelas sem classe em `companies/asaas.php`, uma em
  `companies/asaas_refunds.php`; dependem da normalização genérica do shell.
  Não adicionar DataTables nem alterar linhas, IDs, forms, ACL ou dados.
- Botões e controles sem classe aparecem em diversas famílias, incluindo OAuth,
  API tokens, IA, RH e faturamento; a correção genérica precisa atingir todos
  dentro de `.company-settings-card`, sem exigir `data-company-workspace`.
- PIGE bridge (`modules/pige_bridge/controllers/Bridge.php`) e provisionamento
  (`Argws_provisioning`) são endpoints não visuais. Não criar shell HTML em JSON,
  callbacks ou respostas de instalação para satisfazer superficialmente este gate.

## Matriz de validação proposta — ainda não executada por este inventário

| Família | Representantes reais | Desktop/mobile e invariantes |
|---|---|---|
| Seleção/empresas | company_selection, issuers, onboarding | 1280×900 e 390×900; header/sidebar/logo/avatar, seleção explícita, modal, cancelar/voltar/reabrir/foco |
| Configuração sensível | Google/Microsoft OAuth, certificados, API tokens | botões/controles nativos, segredos mascarados; nenhum connect/revoke/upload automático; ACL view/edit |
| Financeiro | Asaas, despesas, faturas/rascunhos, créditos | tabelas responsivas, validação campos, POST ownership/CSRF, sem API financeira real |
| Projetos/tarefas | projects, standalone_tasks, project_portal_rules | includes condicionais, tabulação, datas/selects/checkboxes e estados vazios/cheios |
| Pessoas/serviços | clients, leads, staff, hr, tickets | formulários e grid, botões destrutivos, dados sintéticos; sem envio de e-mail |
| Operações especializadas | purchases, calendar, reports, imports, billing | overflow, tabelas largas, scroll local e controles já estilizados preservados |
| Página legada | issuers/manage | shell nativo, permissões e retorno; nunca consumir autorização real |
| Bridge/provisionamento | rotas JSON/protocolo | content-type/schema/auth/idempotência em fixtures; screenshot não se aplica |

Cobertura de **todas** as views PHP deve ser ampliada por fixtures de renderização:
para cada página completa do manifesto, montar DTO sintético do controller,
renderizar empty/populated e readonly/edit quando aplicável; seus includes devem
ser alcançados com cada branch habilitada. Fragments sem DTO próprio herdam fixture
do owner; owners condicionais não equivalem a cobertura executada. Falhar warnings
PHP, IDs duplicados, forms aninhados e ausência do par de shell. Reutilizar fixtures
existentes em `tests/companies/*fixture.php`, sem importar configuração real.

Os testes específicos `company_shell_presentation`, `company_shell_controls` e
`company_shell_fidelity` são trabalho paralelo do shell. Sua existência não prova
que foram executados nem que cobrem todos os caminhos deste manifesto. As capturas
reais devem vir do CI descartável após assets carregados; capturas sintéticas devem
ser rotuladas como tal. Este inventário não produz screenshots.

## Views: contrato e controles candidatos ao padrão genérico

`T/B/I/S/A` = table/button/input/select/textarea literais sem classe (inputs especiais
excluídos). Zero não prova ausência de HTML gerado dinamicamente.

| View | Contrato | T/B/I/S/A |
|---|---|---|
| `admin/companies/ai.php` | company-shell | 0/6/2/2/0 |
| `admin/companies/api_tokens.php` | company-shell | 0/3/0/0/0 |
| `admin/companies/asaas.php` | company-shell | 2/18/12/4/0 |
| `admin/companies/asaas_preferences.php` | partial | 0/0/0/0/0 |
| `admin/companies/asaas_refunds.php` | partial | 1/5/6/0/1 |
| `admin/companies/billing_invoice_current.php` | partial | 0/0/0/0/0 |
| `admin/companies/client_identity_fields.php` | partial | 0/0/0/0/0 |
| `admin/companies/connect_api.php` | company-shell | 0/14/0/0/0 |
| `admin/companies/credit_movement_fields.php` | partial | 0/0/0/0/0 |
| `admin/companies/credits.php` | company-shell | 0/9/0/0/0 |
| `admin/companies/efi.php` | company-shell | 0/0/0/0/0 |
| `admin/companies/expense_billing.php` | partial | 0/3/2/1/0 |
| `admin/companies/expense_copies.php` | company-shell | 0/2/0/0/0 |
| `admin/companies/expense_fields.php` | partial | 0/0/0/0/0 |
| `admin/companies/expense_recurring.php` | partial | 0/5/3/4/0 |
| `admin/companies/expenses.php` | company-shell | 0/8/0/0/0 |
| `admin/companies/google_mail_oauth.php` | company-shell | 0/4/0/0/0 |
| `admin/companies/hr.php` | company-shell | 0/19/0/0/0 |
| `admin/companies/hr_mine.php` | company-shell | 0/2/0/0/0 |
| `admin/companies/hr_records.php` | partial | 0/4/0/0/0 |
| `admin/companies/identity_fields.php` | partial | 0/0/0/0/0 |
| `admin/companies/identity_input.php` | partial | 0/0/0/0/0 |
| `admin/companies/identity_modal.php` | modal-document | 0/0/0/0/0 |
| `admin/companies/imports.php` | company-shell | 0/2/0/0/0 |
| `admin/companies/invoice_adjustment_fields.php` | partial | 0/0/0/0/0 |
| `admin/companies/invoice_copies.php` | company-shell | 0/1/0/0/0 |
| `admin/companies/invoice_drafts.php` | company-shell | 0/5/0/0/0 |
| `admin/companies/invoice_drafts_editor_script.php` | partial | 0/0/0/0/0 |
| `admin/companies/invoice_item_fields.php` | partial | 0/0/0/0/0 |
| `admin/companies/invoices.php` | company-shell | 0/0/4/0/0 |
| `admin/companies/layout_head.php` | shell-part | 0/0/0/0/0 |
| `admin/companies/layout_tail.php` | shell-part | 0/0/0/0/0 |
| `admin/companies/microsoft_mail_oauth.php` | company-shell | 0/5/0/0/0 |
| `admin/companies/onboarding.php` | modal-document | 0/0/0/0/0 |
| `admin/companies/operation_files.php` | partial | 0/2/0/0/0 |
| `admin/companies/portal_operation_admin.php` | partial | 0/4/0/0/0 |
| `admin/companies/post_refund.php` | company-shell | 0/2/0/0/1 |
| `admin/companies/post_refund_manual.php` | company-shell | 0/3/8/0/3 |
| `admin/companies/project_billing_rates.php` | company-shell | 0/1/1/3/0 |
| `admin/companies/project_contact_file_rules.php` | partial | 0/1/0/0/0 |
| `admin/companies/project_copies.php` | company-shell | 0/1/0/0/0 |
| `admin/companies/project_discussion_extras.php` | partial | 0/5/0/1/1 |
| `admin/companies/project_discussions.php` | partial | 0/5/1/3/2 |
| `admin/companies/project_extras.php` | company-shell | 0/7/0/0/0 |
| `admin/companies/project_fixed_billing.php` | company-shell | 0/3/7/3/1 |
| `admin/companies/project_gantt_rules.php` | partial | 0/2/1/0/0 |
| `admin/companies/project_milestone_rules.php` | partial | 0/4/2/0/1 |
| `admin/companies/project_portal_rules.php` | company-shell | 0/2/0/0/0 |
| `admin/companies/project_task_authorship.php` | partial | 0/0/0/0/0 |
| `admin/companies/project_task_write_rules.php` | partial | 0/1/0/0/0 |
| `admin/companies/project_timesheet_rules.php` | partial | 0/1/0/0/0 |
| `admin/companies/projects.php` | company-shell | 0/3/0/0/0 |
| `admin/companies/projects_fields.php` | partial | 0/0/0/0/0 |
| `admin/companies/purchases.php` | company-shell | 0/5/0/0/0 |
| `admin/companies/purchases_payable.php` | partial | 0/2/1/0/1 |
| `admin/companies/purchases_workspace.php` | partial | 0/11/5/0/0 |
| `admin/companies/pusher.php` | company-shell | 0/7/0/0/0 |
| `admin/companies/recurring.php` | company-shell | 0/3/0/0/0 |
| `admin/companies/registry_grid.php` | partial | 0/0/0/0/0 |
| `admin/companies/sales.php` | company-shell | 0/5/0/0/0 |
| `admin/companies/sales_extras.php` | partial | 0/3/0/0/0 |
| `admin/companies/sales_form_fields.php` | partial | 0/1/0/0/0 |
| `admin/companies/sales_form_script.php` | partial | 0/0/0/0/0 |
| `admin/companies/sales_mutations.php` | partial | 0/3/4/2/1 |
| `admin/companies/staff.php` | company-shell | 0/5/0/0/0 |
| `admin/companies/standalone_billing.php` | company-shell | 0/4/8/7/1 |
| `admin/companies/standalone_tasks.php` | company-shell | 0/4/0/0/0 |
| `admin/companies/task_billing.php` | company-shell | 0/4/9/5/2 |
| `admin/companies/task_copies.php` | company-shell | 0/1/0/0/0 |
| `admin/companies/task_recurring.php` | company-shell | 0/5/0/0/0 |
| `admin/companies/tasks.php` | company-shell | 0/4/0/0/0 |
| `admin/companies/tasks_fields.php` | partial | 0/0/0/0/0 |
| `admin/companies/ticket_connect.php` | company-shell | 0/0/0/0/0 |
| `admin/companies/tickets.php` | company-shell | 0/5/0/0/0 |
| `admin/companies/time_batches.php` | company-shell | 0/5/10/7/1 |
| `admin/companies/time_billing.php` | company-shell | 0/4/9/8/1 |
| `admin/emails/company_scoped.php` | company-shell | 0/0/0/0/0 |
| `admin/includes/company_context_script.php` | partial | 0/0/0/0/0 |
| `admin/includes/company_selector.php` | partial | 0/1/0/0/0 |
| `admin/issuers/certificate_link.php` | unresolved-fragment | 0/0/0/0/0 |
| `admin/issuers/manage.php` | native-shell | 0/0/0/0/0 |
| `admin/issuers/staff_access.php` | partial | 0/0/0/0/0 |
| `admin/settings/includes/company.php` | partial | 0/0/0/0/0 |
| `admin/settings/includes/company_identity_fields.php` | partial | 0/0/0/0/0 |
| `admin/settings/presentation_layout.php` | partial | 0/0/0/0/0 |
| `modules/pige_bridge/views/settings.php` | native-shell | 0/0/0/0/0 |

## Controllers: dispatch literal e rotas públicas

Métodos listados não significam GET autorizado: conferir guardas, verbs e runtime.

| Controller | Views literais | Métodos públicos |
|---|---|---|
| `application/controllers/Argws_provisioning.php` | Sem view literal; endpoint não visual | `complete_installation`, `apply_migrations`, `authorize_company_master`, `original_administrator` |
| `application/controllers/admin/Company_ai.php` | `admin/companies/ai` | `index` |
| `application/controllers/admin/Company_api_tokens.php` | `admin/companies/api_tokens` | `index` |
| `application/controllers/admin/Company_asaas.php` | `admin/companies/asaas` | `__construct`, `index` |
| `application/controllers/admin/Company_connect_api.php` | `admin/companies/connect_api` | `index` |
| `application/controllers/admin/Company_credits.php` | `admin/companies/credits` | `index` |
| `application/controllers/admin/Company_efi.php` | `admin/companies/efi` | `index` |
| `application/controllers/admin/Company_expense_copies.php` | `admin/companies/expense_copies` | `index` |
| `application/controllers/admin/Company_expenses.php` | `admin/companies/expenses` | `index` |
| `application/controllers/admin/Company_google_mail_oauth.php` | `admin/companies/google_mail_oauth` | `index` |
| `application/controllers/admin/Company_hr.php` | `admin/companies/hr`, `admin/companies/hr_mine` | `index`, `file`, `pdf`, `export`, `mine`, `mine_file` |
| `application/controllers/admin/Company_imports.php` | `admin/companies/imports` | `index` |
| `application/controllers/admin/Company_invoice_copies.php` | `admin/companies/invoice_copies` | `index` |
| `application/controllers/admin/Company_invoice_drafts.php` | `admin/companies/invoice_drafts` | `index` |
| `application/controllers/admin/Company_invoices.php` | `admin/companies/invoices` | `index` |
| `application/controllers/admin/Company_microsoft_mail_oauth.php` | `admin/companies/microsoft_mail_oauth` | `index` |
| `application/controllers/admin/Company_onboarding.php` | `admin/companies/onboarding` | `index` |
| `application/controllers/admin/Company_post_refund.php` | `admin/companies/post_refund` | `index` |
| `application/controllers/admin/Company_post_refund_manual.php` | `admin/companies/post_refund_manual` | `index` |
| `application/controllers/admin/Company_project_billing_rates.php` | `admin/companies/project_billing_rates` | `index` |
| `application/controllers/admin/Company_project_copies.php` | `admin/companies/project_copies` | `index` |
| `application/controllers/admin/Company_project_extras.php` | `admin/companies/project_extras` | `index` |
| `application/controllers/admin/Company_project_fixed_billing.php` | `admin/companies/project_fixed_billing` | `index` |
| `application/controllers/admin/Company_project_portal_rules.php` | `admin/companies/project_portal_rules` | `index` |
| `application/controllers/admin/Company_projects.php` | `admin/companies/projects` | `index` |
| `application/controllers/admin/Company_purchases.php` | `admin/companies/purchases` | `index` |
| `application/controllers/admin/Company_pusher.php` | `admin/companies/pusher` | `index` |
| `application/controllers/admin/Company_recurring.php` | `admin/companies/recurring` | `index` |
| `application/controllers/admin/Company_sales.php` | `admin/companies/sales` | `index` |
| `application/controllers/admin/Company_selection.php` | Sem view literal; endpoint não visual | `__construct`, `select` |
| `application/controllers/admin/Company_staff.php` | `admin/companies/staff` | `index` |
| `application/controllers/admin/Company_standalone_billing.php` | `admin/companies/standalone_billing` | `index` |
| `application/controllers/admin/Company_standalone_tasks.php` | `admin/companies/standalone_tasks` | `index` |
| `application/controllers/admin/Company_task_billing.php` | `admin/companies/task_billing` | `index` |
| `application/controllers/admin/Company_task_copies.php` | `admin/companies/task_copies` | `index` |
| `application/controllers/admin/Company_task_recurring.php` | `admin/companies/task_recurring` | `index` |
| `application/controllers/admin/Company_tasks.php` | `admin/companies/tasks` | `index` |
| `application/controllers/admin/Company_ticket_connect.php` | `admin/companies/ticket_connect` | `index` |
| `application/controllers/admin/Company_tickets.php` | `admin/companies/tickets` | `index` |
| `application/controllers/admin/Company_time_batches.php` | `admin/companies/time_batches` | `index` |
| `application/controllers/admin/Company_time_billing.php` | `admin/companies/time_billing` | `index` |
| `application/controllers/admin/Issuers.php` | `admin/companies/identity_modal`, `admin/issuers/manage` | `__construct`, `index`, `save`, `lookup` |
| `application/controllers/admin/Smtp_oauth_google.php` | Sem view literal; endpoint não visual | `token` |
| `application/controllers/admin/Smtp_oauth_microsoft.php` | Sem view literal; endpoint não visual | `token` |
| `modules/pige_bridge/controllers/Bridge.php` | Sem view literal; endpoint não visual | `requests`, `diagnostics`, `invoices`, `invoice_status`, `charges`, `charge_status`, `charge_sync`, `charge_instruments`, `charge_reconcile` |
| `modules/pige_bridge/controllers/Settings.php` | `pige_bridge/settings` | `__construct`, `index`, `save`, `mapping`, `external` |

## Incremento 4.7.3 — navegação e editor existente

Evidência de escopo: imagens do usuário exibem `/admin/issuers`, modal de criação,
e destacam as etapas e subnavegação. As etapas agora usam `nav nav-tabs`; as três
subguias compartilhadas são Identificação, Endereço e Contato. São mantidas as
restrições das etapas futuras e o POST/revision do wizard. Não são botões de ação
substituídos: Consultar CNPJ/CEP, Salvar, Criar e Fechar continuam ações explícitas.

Novo documento inventariado: `admin/companies/identity_modal.php`, proprietário
`Issuers::index`, seletor GET `edit_modal=1`, ID existente na rota. Sem shell global,
com os mesmos assets públicos e `identity_fields.php` da criação. Não reutiliza
`begin`, `advance` ou `complete`; o POST `issuers/save`, ação `edit_identity`, usa
CSRF, actor autenticado, ACL de gestão e revisão canônica comparada dentro da
transação existente antes de escrever. ID inválido nunca vira criação. O editor
não altera situação, empresa selecionada, credenciais nem vínculos financeiros.

A revisão é um hash da identidade canônica e ID, não um número novo de schema;
nenhuma migração ou armazenamento paralelo foi introduzido. Uma edição stale é
recusada com409, sem gravar; deve ser reaberta para revisar os dados atuais.
Preferências, equipe e privacidade permanecem nas áreas existentes autorizadas,
não são falsamente apresentadas como seis etapas editáveis.

Manifesto atualizado:100 views/55 controllers. A grade possui região de tabela
com foco por teclado/scroll interno; editor e wizard mantêm foco/retorno do modal.
Testes de browser usam a view PHP real com dados sintéticos em1280/768/390 e
verificam campos preenchidos, navegação sem POST, ação/ID/revision e Escape/foco.
A fixture de identidade cobre teclado, hash e Voltar em1440/768/390. Testes PHP
existentes ganharam CSRF/ACL/stale, rollback e contagem sem duplicação. Validação
local: contratos Node e inventário estático. PHP e browser não executados neste
ambiente; carregamento/renderização real dependem de CI. Nenhum screenshot real
ou dado do usuário foi incluído nos artefatos da fixture.

### Settings da empresa secundária — componente nativo compartilhado

O terceiro print mostra `/admin/settings?group=company`, não uma rota inexistente.
URL e dispatch autorizado permanecem iguais. O grupo `company` deixa de repetir
inputs numa implementação paralela e passa a usar
`settings/includes/company_identity_fields.php`, extraído do formulário nativo
`settings/includes/company.php`. Principal e secundária usam o mesmo `render_input`,
labels, ordem e nomes `settings[...]`; só o adaptador de valores muda. A secundária
recebe exclusivamente `$values` já projetado pelo controller e falha se faltar
campo; a partial não acessa `get_option`, hooks ou custom fields globais.

Mapa incremental de contratos (não alegação de equivalência completa):

| Grupo | Campos/ação scoped atuais | Reuso nesta entrega / limite |
|---|---|---|
| company | nome fiscal, CPF/CNPJ e11campos de perfil; salvar identidade canônica |13inputs nativos compartilhados; formato livre `company_info_format` e custom fields globais continuam apenas no principal, explicitamente sinalizados na secundária |
| localization | dateformat/time_format | catálogo/validação scoped preservados; renderer ainda específico |
| email | host/porta/criptografia/remetente/usuário/senha; limpar senha explícito | contrato de segredo preservado; não reutilizar formulário legado sem adapter de preservação/limpeza |
| email_content | cabeçalho/assinatura/rodapé HTML sanitizados | renderer scoped preservado; helper legado exige compatibilidade com sanitização |
| documents / sales_documents | prefixos e termos por tipo | renderer scoped preservado; não misturar opções globais de numeração/financeiro |
| document_pdf / sales_pdf | formato de papel por tipo | renderer scoped preservado; catálogo de formatos existente |
| pdf_typography | fonte/tamanho comrevision | manter revisão e whitelist de fontes antes extrair componente |
| project_files | permissão de ocultar arquivos próprios comrevision | contrato específico; sem ampliar permissões |
| ticket_preferences | ordem das respostas comrevision | contrato específico preservado |
| task_defaults / task_operations | status/responsável sugerido/prioridade/seguidor comrevision | controles existentes; não chamar callbacks globais |
| task_timers / task_timer_stop | mudanças explícitas de estado/parada comrevision | comportamento operacional existente, não implementar via aparência |

Não se renderiza `settings/all.php` em contexto secundário, pois seu `init_head`
e catálogo de callbacks globais não são componentes seguros para hidratação.
Esse limite não impede reuso de partials de campo: o grupo visível no print agora
comprova esse reuso, e os próximos grupos exigem seu próprio adapter contratual.
Manifesto incrementado para101views/55controllers. Teste PHP A/B usa o helper
nativo real, `get_option` global que lança erro, cobertura exata dos13names,
escape e ausência de mutação durante render. Execução PHP permanece pendenteCI;
contrato estático/inventário e sintaxe JS passaram localmente.


## Envelope compartilhado de Settings — 102 views / 55 controllers

A descoberta agora inclui `admin/settings/presentation*.php`, não apenas
`includes/company*.php`. Um teste negativo cria nomes
sintéticos `presentation_layout.php` e `presentation_future.php` e exige ambos
no censo: novos componentes não podem ficar silenciosamente fora do manifesto.

`admin/settings/presentation_layout.php` é `partial`, com owners reais
`admin/settings/all.php`. Seus controles
literais table/button/input/select/textarea são 0/0/0/0/0: os formulários continuam
pertencendo aos parents. A contagem efetiva após inclusão é **102 views e 55
controllers**. Esses números são cobertura estática, não prova visual ou PHP.
