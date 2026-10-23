# Inventário auditável — superfícies administrativas por empresa

## Escopo e natureza da evidência

Baseline de código: `5ebcd8da`. Inventário **estático**, não captura de tela nem
prova de execução PHP, permissão, responsividade, carregamento de assets ou visual.
São 98 views e 54 controllers/endpoints; nenhum valor de configuração, token,
certificado ou dado de produção foi consultado.

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

## Contratos e gaps

- 52 páginas internas: par `admin/companies/layout_head.php` / `layout_tail.php`.
  O shell usa assets públicos nativos; não deve chamar `init_head` e hidratar
  menus/hooks globais num contexto empresarial isolado.
- 2 páginas legadas (`issuers/manage`, `issuers/master_resolution`): par nativo
  `init_head` / `init_tail`, com contexto/autorização a verificar no runtime.
- 40 fragments: herdaram layout da página proprietária; adicionar shell próprio
  geraria duplicação. Seus proprietários estão no manifesto.
- Onboarding: documento do modal same-origin com CSS nativo e script de contexto;
  não deve duplicar sidebar/header. Exige teste próprio de modal, fechamento e foco.
- **Gap explícito:** `admin/issuers/certificate_link.php` não tem chamador literal
  localizado em application/modules. Não atestar renderização/uso; investigar antes
  de reativar/remover. A rota ativa `company_certificates` usa `certificates.php`.
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
| Contexto/empresas | company_context, issuers, onboarding | 1280×900 e 390×900; header/sidebar/logo/avatar, seleção explícita, modal, cancelar/voltar/reabrir/foco |
| Configuração sensível | Google/Microsoft OAuth, certificados, API tokens | botões/controles nativos, segredos mascarados; nenhum connect/revoke/upload automático; ACL view/edit |
| Financeiro | Asaas, despesas, faturas/rascunhos, créditos | tabelas responsivas, validação campos, POST ownership/CSRF, sem API financeira real |
| Projetos/tarefas | projects, standalone_tasks, project_portal_rules | includes condicionais, tabulação, datas/selects/checkboxes e estados vazios/cheios |
| Pessoas/serviços | clients, leads, staff, hr, tickets | formulários e grid, botões destrutivos, dados sintéticos; sem envio de e-mail |
| Operações especializadas | purchases, calendar, reports, imports, billing | overflow, tabelas largas, scroll local e controles já estilizados preservados |
| Páginas legadas | master_resolution, issuers/manage | shell nativo, permissões e retorno; nunca consumir autorização real |
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
| `admin/companies/assets.php` | company-shell | 0/2/0/0/0 |
| `admin/companies/billing_invoice_current.php` | partial | 0/0/0/0/0 |
| `admin/companies/calendar.php` | company-shell | 0/0/4/5/0 |
| `admin/companies/certificates.php` | company-shell | 0/0/0/0/0 |
| `admin/companies/client_identity_fields.php` | partial | 0/0/0/0/0 |
| `admin/companies/client_imports.php` | company-shell | 0/2/0/0/0 |
| `admin/companies/clients.php` | company-shell | 0/0/0/0/0 |
| `admin/companies/connect_api.php` | company-shell | 0/14/0/0/0 |
| `admin/companies/context.php` | company-shell | 0/0/0/0/0 |
| `admin/companies/contract_discussion.php` | partial | 0/2/0/0/1 |
| `admin/companies/contracts.php` | company-shell | 0/7/0/0/0 |
| `admin/companies/contracts_fields.php` | partial | 0/0/0/0/0 |
| `admin/companies/credit_movement_fields.php` | partial | 0/0/0/0/0 |
| `admin/companies/credits.php` | company-shell | 0/9/0/0/0 |
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
| `admin/companies/imports.php` | company-shell | 0/2/0/0/0 |
| `admin/companies/invoice_adjustment_fields.php` | partial | 0/0/0/0/0 |
| `admin/companies/invoice_copies.php` | company-shell | 0/1/0/0/0 |
| `admin/companies/invoice_drafts.php` | company-shell | 0/5/0/0/0 |
| `admin/companies/invoice_drafts_editor_script.php` | partial | 0/0/0/0/0 |
| `admin/companies/invoice_item_fields.php` | partial | 0/0/0/0/0 |
| `admin/companies/invoice_recurring.php` | company-shell | 0/3/3/3/0 |
| `admin/companies/invoices.php` | company-shell | 0/0/4/0/0 |
| `admin/companies/layout_head.php` | shell-part | 0/0/0/0/0 |
| `admin/companies/layout_tail.php` | shell-part | 0/0/0/0/0 |
| `admin/companies/leads.php` | company-shell | 0/0/2/1/0 |
| `admin/companies/leads_fields.php` | partial | 0/0/0/0/0 |
| `admin/companies/ledger.php` | company-shell | 0/1/0/0/0 |
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
| `admin/companies/registry.php` | company-shell | 0/0/0/0/0 |
| `admin/companies/registry_grid.php` | partial | 0/0/0/0/0 |
| `admin/companies/reports.php` | company-shell | 0/0/0/0/0 |
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
| `admin/issuers/master_resolution.php` | native-shell | 0/0/0/0/0 |
| `admin/issuers/staff_access.php` | partial | 0/0/0/0/0 |
| `admin/settings/company_scoped.php` | company-shell | 0/0/0/0/0 |
| `admin/settings/includes/company.php` | partial | 0/0/0/0/0 |

## Controllers: dispatch literal e rotas públicas

Métodos listados não significam GET autorizado: conferir guardas, verbs e runtime.

| Controller | Views literais | Métodos públicos |
|---|---|---|
| `application/controllers/Argws_provisioning.php` | Sem view literal; endpoint não visual | `complete_installation`, `apply_migrations`, `authorize_company_master` |
| `application/controllers/admin/Company_ai.php` | `admin/companies/ai` | `index` |
| `application/controllers/admin/Company_api_tokens.php` | `admin/companies/api_tokens` | `index` |
| `application/controllers/admin/Company_asaas.php` | `admin/companies/asaas` | `__construct`, `index` |
| `application/controllers/admin/Company_assets.php` | `admin/companies/assets` | `index` |
| `application/controllers/admin/Company_calendar.php` | `admin/companies/calendar` | `index` |
| `application/controllers/admin/Company_certificates.php` | `admin/companies/certificates` | `index` |
| `application/controllers/admin/Company_client_imports.php` | `admin/companies/client_imports` | `index` |
| `application/controllers/admin/Company_clients.php` | `admin/companies/clients` | `index`, `lookup` |
| `application/controllers/admin/Company_connect_api.php` | `admin/companies/connect_api` | `index` |
| `application/controllers/admin/Company_context.php` | `admin/companies/context` | `__construct`, `index`, `select` |
| `application/controllers/admin/Company_contracts.php` | `admin/companies/contracts` | `index` |
| `application/controllers/admin/Company_credits.php` | `admin/companies/credits` | `index` |
| `application/controllers/admin/Company_expense_copies.php` | `admin/companies/expense_copies` | `index` |
| `application/controllers/admin/Company_expenses.php` | `admin/companies/expenses` | `index` |
| `application/controllers/admin/Company_google_mail_oauth.php` | `admin/companies/google_mail_oauth` | `index` |
| `application/controllers/admin/Company_hr.php` | `admin/companies/hr`, `admin/companies/hr_mine` | `index`, `file`, `pdf`, `export`, `mine`, `mine_file` |
| `application/controllers/admin/Company_imports.php` | `admin/companies/imports` | `index` |
| `application/controllers/admin/Company_invoice_copies.php` | `admin/companies/invoice_copies` | `index` |
| `application/controllers/admin/Company_invoice_drafts.php` | `admin/companies/invoice_drafts` | `index` |
| `application/controllers/admin/Company_invoice_recurring.php` | `admin/companies/invoice_recurring` | `index` |
| `application/controllers/admin/Company_invoices.php` | `admin/companies/invoices` | `index` |
| `application/controllers/admin/Company_leads.php` | `admin/companies/leads` | `index`, `file`, `export` |
| `application/controllers/admin/Company_master.php` | `admin/issuers/master_resolution` | `index` |
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
| `application/controllers/admin/Company_reports.php` | `admin/companies/ledger`, `admin/companies/reports` | `index` |
| `application/controllers/admin/Company_sales.php` | `admin/companies/sales` | `index` |
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
| `application/controllers/admin/Issuers.php` | `admin/companies/registry`, `admin/issuers/manage` | `__construct`, `index`, `save`, `lookup` |
| `application/controllers/admin/Smtp_oauth_google.php` | Sem view literal; endpoint não visual | `token` |
| `application/controllers/admin/Smtp_oauth_microsoft.php` | Sem view literal; endpoint não visual | `token` |
| `modules/pige_bridge/controllers/Bridge.php` | Sem view literal; endpoint não visual | `requests` |
