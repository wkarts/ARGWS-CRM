# Inventário de apresentação nativa por empresa

## Escopo e evidência

Baseline inspecionado: `5cb2c7124e8650c1b8b2fb65e2f1c370cb6e742a` (4.7.0).
Referência visual: release `v4.0.2`, commit `aa58b832b3c5ed04a599caacba125b056c081ba7`.
Este inventário descreve **superfície e critérios de revisão**, não declara migração visual concluída nem homologação de produção.
Os caminhos abaixo foram extraídos dos controllers e templates reais. Métodos de download/exportação podem retornar bytes em vez de HTML.

Contrato do pedido: uma apresentação CRM; Empresas em grid; seletor acima do perfil; wizard modal **somente para criar**; configuração cotidiana nas telas normais da empresa ativa. Não há downgrade de serviços, migrations ou regras financeiras.

## Controllers (49)

Prefixo público habitual `/admin/`; respeitar `CompanyRequestScope::adminUrl` para instalações com admin URI customizado.

| Rota | Controller | Métodos públicos (sem construtor) | View(s) raiz | Revisão de apresentação/fluxo |
|---|---|---|---|---|
| `company_ai` | `application/controllers/admin/Company_ai.php` | index | `admin/companies/ai` | Consulta/resultado primeiro; manter ações específicas autorizadas. |
| `company_api_tokens` | `application/controllers/admin/Company_api_tokens.php` | index | `admin/companies/api_tokens` | Agrupar configuração/operações/histórico; preservar cada formulário e confirmação. |
| `company_asaas` | `application/controllers/admin/Company_asaas.php` | index | `admin/companies/asaas` | Agrupar configuração/operações/histórico; preservar cada formulário e confirmação. |
| `company_assets` | `application/controllers/admin/Company_assets.php` | index | `admin/companies/assets` | Formulário contextual normal; sem wizard de empresa. |
| `company_calendar` | `application/controllers/admin/Company_calendar.php` | index | `admin/companies/calendar` | Formulário contextual normal; sem wizard de empresa. |
| `company_certificates` | `application/controllers/admin/Company_certificates.php` | index | `admin/companies/certificates` | Formulário contextual normal; sem wizard de empresa. |
| `company_client_imports` | `application/controllers/admin/Company_client_imports.php` | index | `admin/companies/client_imports` | Fluxo específico de revisão/confirmação; não converter em CRUD genérico. |
| `company_clients` | `application/controllers/admin/Company_clients.php` | index, lookup | `admin/companies/clients` | Lista primeiro; criar/editar por ação explícita; detalhes preservam permissões e revisão. |
| `company_connect_api` | `application/controllers/admin/Company_connect_api.php` | index | `admin/companies/connect_api` | Agrupar configuração/operações/histórico; preservar cada formulário e confirmação. |
| `company_context` | `application/controllers/admin/Company_context.php` | index, select | `admin/companies/context` | Seletor sidebar; POST explícito; destino seguro e rotação contextual. |
| `company_contracts` | `application/controllers/admin/Company_contracts.php` | index | `admin/companies/contracts` | Lista primeiro; criar/editar por ação explícita; detalhes preservam permissões e revisão. |
| `company_credits` | `application/controllers/admin/Company_credits.php` | index | `admin/companies/credits` | Lista primeiro; criar/editar por ação explícita; detalhes preservam permissões e revisão. |
| `company_expense_copies` | `application/controllers/admin/Company_expense_copies.php` | index | `admin/companies/expense_copies` | Fluxo específico de revisão/confirmação; não converter em CRUD genérico. |
| `company_expenses` | `application/controllers/admin/Company_expenses.php` | index | `admin/companies/expenses` | Lista primeiro; criar/editar por ação explícita; detalhes preservam permissões e revisão. |
| `company_google_mail_oauth` | `application/controllers/admin/Company_google_mail_oauth.php` | index | `admin/companies/google_mail_oauth` | Formulário contextual normal; sem wizard de empresa. |
| `company_hr` | `application/controllers/admin/Company_hr.php` | index, file, pdf, export, mine, mine_file | `admin/companies/hr`<br>`admin/companies/hr_mine` | Agrupar configuração/operações/histórico; preservar cada formulário e confirmação. |
| `company_imports` | `application/controllers/admin/Company_imports.php` | index | `admin/companies/imports` | Fluxo específico de revisão/confirmação; não converter em CRUD genérico. |
| `company_invoice_copies` | `application/controllers/admin/Company_invoice_copies.php` | index | `admin/companies/invoice_copies` | Fluxo específico de revisão/confirmação; não converter em CRUD genérico. |
| `company_invoice_drafts` | `application/controllers/admin/Company_invoice_drafts.php` | index | `admin/companies/invoice_drafts` | Fluxo específico de revisão/confirmação; não converter em CRUD genérico. |
| `company_invoice_recurring` | `application/controllers/admin/Company_invoice_recurring.php` | index | `admin/companies/invoice_recurring` | Lista primeiro; criar/editar por ação explícita; detalhes preservam permissões e revisão. |
| `company_invoices` | `application/controllers/admin/Company_invoices.php` | index | `admin/companies/invoices` | Lista primeiro; criar/editar por ação explícita; detalhes preservam permissões e revisão. |
| `company_leads` | `application/controllers/admin/Company_leads.php` | index, file, export | `admin/companies/leads` | Lista primeiro; criar/editar por ação explícita; detalhes preservam permissões e revisão. |
| `company_master` | `application/controllers/admin/Company_master.php` | index | `admin/issuers/master_resolution` | Recuperação administrativa nativa, não confundir com criar empresa. |
| `company_microsoft_mail_oauth` | `application/controllers/admin/Company_microsoft_mail_oauth.php` | index | `admin/companies/microsoft_mail_oauth` | Formulário contextual normal; sem wizard de empresa. |
| `company_onboarding` | `application/controllers/admin/Company_onboarding.php` | index | `admin/companies/onboarding` | Modal exclusivamente de criação; rascunho/revisão/retomada/idempotência. |
| `company_post_refund` | `application/controllers/admin/Company_post_refund.php` | index | `admin/companies/post_refund` | Fluxo específico de revisão/confirmação; não converter em CRUD genérico. |
| `company_post_refund_manual` | `application/controllers/admin/Company_post_refund_manual.php` | index | `admin/companies/post_refund_manual` | Fluxo específico de revisão/confirmação; não converter em CRUD genérico. |
| `company_project_billing_rates` | `application/controllers/admin/Company_project_billing_rates.php` | index | `admin/companies/project_billing_rates` | Formulário contextual normal; sem wizard de empresa. |
| `company_project_copies` | `application/controllers/admin/Company_project_copies.php` | index | `admin/companies/project_copies` | Fluxo específico de revisão/confirmação; não converter em CRUD genérico. |
| `company_project_extras` | `application/controllers/admin/Company_project_extras.php` | index | `admin/companies/project_extras` | Fluxo específico de revisão/confirmação; não converter em CRUD genérico. |
| `company_project_fixed_billing` | `application/controllers/admin/Company_project_fixed_billing.php` | index | `admin/companies/project_fixed_billing` | Fluxo específico de revisão/confirmação; não converter em CRUD genérico. |
| `company_project_portal_rules` | `application/controllers/admin/Company_project_portal_rules.php` | index | `admin/companies/project_portal_rules` | Formulário contextual normal; sem wizard de empresa. |
| `company_projects` | `application/controllers/admin/Company_projects.php` | index | `admin/companies/projects` | Lista primeiro; criar/editar por ação explícita; detalhes preservam permissões e revisão. |
| `company_purchases` | `application/controllers/admin/Company_purchases.php` | index | `admin/companies/purchases` | Agrupar configuração/operações/histórico; preservar cada formulário e confirmação. |
| `company_pusher` | `application/controllers/admin/Company_pusher.php` | index | `admin/companies/pusher` | Agrupar configuração/operações/histórico; preservar cada formulário e confirmação. |
| `company_recurring` | `application/controllers/admin/Company_recurring.php` | index | `admin/companies/recurring` | Lista primeiro; criar/editar por ação explícita; detalhes preservam permissões e revisão. |
| `company_reports` | `application/controllers/admin/Company_reports.php` | index | `admin/companies/reports`<br>`admin/companies/ledger` | Consulta/resultado primeiro; manter ações específicas autorizadas. |
| `company_sales` | `application/controllers/admin/Company_sales.php` | index | `admin/companies/sales` | Lista primeiro; criar/editar por ação explícita; detalhes preservam permissões e revisão. |
| `company_staff` | `application/controllers/admin/Company_staff.php` | index | `admin/companies/staff` | Lista primeiro; criar/editar por ação explícita; detalhes preservam permissões e revisão. |
| `company_standalone_billing` | `application/controllers/admin/Company_standalone_billing.php` | index | `admin/companies/standalone_billing` | Fluxo específico de revisão/confirmação; não converter em CRUD genérico. |
| `company_standalone_tasks` | `application/controllers/admin/Company_standalone_tasks.php` | index | `admin/companies/standalone_tasks` | Lista primeiro; criar/editar por ação explícita; detalhes preservam permissões e revisão. |
| `company_task_billing` | `application/controllers/admin/Company_task_billing.php` | index | `admin/companies/task_billing` | Fluxo específico de revisão/confirmação; não converter em CRUD genérico. |
| `company_task_copies` | `application/controllers/admin/Company_task_copies.php` | index | `admin/companies/task_copies` | Fluxo específico de revisão/confirmação; não converter em CRUD genérico. |
| `company_task_recurring` | `application/controllers/admin/Company_task_recurring.php` | index | `admin/companies/task_recurring` | Lista primeiro; criar/editar por ação explícita; detalhes preservam permissões e revisão. |
| `company_tasks` | `application/controllers/admin/Company_tasks.php` | index | `admin/companies/tasks` | Lista primeiro; criar/editar por ação explícita; detalhes preservam permissões e revisão. |
| `company_ticket_connect` | `application/controllers/admin/Company_ticket_connect.php` | index | `admin/companies/ticket_connect` | Agrupar configuração/operações/histórico; preservar cada formulário e confirmação. |
| `company_tickets` | `application/controllers/admin/Company_tickets.php` | index | `admin/companies/tickets` | Lista primeiro; criar/editar por ação explícita; detalhes preservam permissões e revisão. |
| `company_time_batches` | `application/controllers/admin/Company_time_batches.php` | index | `admin/companies/time_batches` | Fluxo específico de revisão/confirmação; não converter em CRUD genérico. |
| `company_time_billing` | `application/controllers/admin/Company_time_billing.php` | index | `admin/companies/time_billing` | Fluxo específico de revisão/confirmação; não converter em CRUD genérico. |

## Templates que compõem diretamente o shell isolado

A lista inclui referências por `__DIR__`, não somente `APPPATH`. `calendar.php` e `project_portal_rules.php` também compartilham o shell.

- `application/views/admin/companies/ai.php`
- `application/views/admin/companies/api_tokens.php`
- `application/views/admin/companies/asaas.php`
- `application/views/admin/companies/assets.php`
- `application/views/admin/companies/calendar.php`
- `application/views/admin/companies/certificates.php`
- `application/views/admin/companies/client_imports.php`
- `application/views/admin/companies/clients.php`
- `application/views/admin/companies/connect_api.php`
- `application/views/admin/companies/context.php`
- `application/views/admin/companies/contracts.php`
- `application/views/admin/companies/credits.php`
- `application/views/admin/companies/expense_copies.php`
- `application/views/admin/companies/expenses.php`
- `application/views/admin/companies/google_mail_oauth.php`
- `application/views/admin/companies/hr.php`
- `application/views/admin/companies/hr_mine.php`
- `application/views/admin/companies/imports.php`
- `application/views/admin/companies/invoice_copies.php`
- `application/views/admin/companies/invoice_drafts.php`
- `application/views/admin/companies/invoice_recurring.php`
- `application/views/admin/companies/invoices.php`
- `application/views/admin/companies/leads.php`
- `application/views/admin/companies/ledger.php`
- `application/views/admin/companies/microsoft_mail_oauth.php`
- `application/views/admin/companies/onboarding.php`
- `application/views/admin/companies/post_refund.php`
- `application/views/admin/companies/post_refund_manual.php`
- `application/views/admin/companies/project_billing_rates.php`
- `application/views/admin/companies/project_copies.php`
- `application/views/admin/companies/project_extras.php`
- `application/views/admin/companies/project_fixed_billing.php`
- `application/views/admin/companies/project_portal_rules.php`
- `application/views/admin/companies/projects.php`
- `application/views/admin/companies/purchases.php`
- `application/views/admin/companies/pusher.php`
- `application/views/admin/companies/recurring.php`
- `application/views/admin/companies/registry.php`
- `application/views/admin/companies/reports.php`
- `application/views/admin/companies/sales.php`
- `application/views/admin/companies/staff.php`
- `application/views/admin/companies/standalone_billing.php`
- `application/views/admin/companies/standalone_tasks.php`
- `application/views/admin/companies/task_billing.php`
- `application/views/admin/companies/task_copies.php`
- `application/views/admin/companies/task_recurring.php`
- `application/views/admin/companies/tasks.php`
- `application/views/admin/companies/ticket_connect.php`
- `application/views/admin/companies/tickets.php`
- `application/views/admin/companies/time_batches.php`
- `application/views/admin/companies/time_billing.php`
- `application/views/admin/emails/company_scoped.php`
- `application/views/admin/settings/company_scoped.php`

Além dos 49 controllers, `Issuers::index` escolhe `admin/issuers/manage` para principal e `admin/companies/registry` para contexto isolado; `Settings` usa `admin/settings/company_scoped`, e `Emails` usa `admin/emails/company_scoped` em contexto secundário. `Company_master` é exceção deliberada: usa `admin/issuers/master_resolution` e shell nativo de recuperação. Fragmentos incluídos pelas views (itens, discussões, arquivos, identidade e ações) não são páginas independentes e não devem receber um segundo shell.

## Divergências comprovadas no baseline

- `companies/layout_head.php` substitui sidebar nativa por navegação plana; `company-shell.css` recria shell com grid e overflow próprios. `layout_tail.php` não inicializa pipeline legado (correto para isolamento), mas isso não justifica outra linguagem visual.
- `companies/invoices.php` empilha documento, recebimento, edição, listagem e emissão. `invoices/manage.php` / `invoices/list_template.php` da 4.0.2 separam toolbar, filtros, grid e detalhe.
- Lista e criação também coexistem em clientes, contratos, créditos, despesas, leads, projetos, vendas, equipe, tarefas e chamados. Alterar só CSS do shell não completa esses fluxos.
- Integrações possuem múltiplos formulários de ações distintas: Asaas, Connect, RH e Compras exigem agrupamento sem eliminar ações/revisões. A contagem literal de `form_open` não inclui necessariamente formulários em fragmentos.
- `settings/company_scoped.php` usa navegação horizontal própria, diferente da navegação lateral por categorias em `settings/all.php` da referência.
- `Company_onboarding` já possui backend de etapas e frontend; `canCreate` é master-only no modelo nativo. Ausência de botão não autoriza transformar admin genérico em master.

## Guardas inegociáveis

1. `CompanyRequestScope` resolve identidade/contexto **antes** de hidratação e rejeita rotas não isoladas em empresa secundária. Não chamar `init_head`, hooks financeiros ou modelos globais por conveniência visual.
2. `CompanyContext::select` exige POST do controller, token contextual, CSRF e vínculo atual; rotaciona nonce/CSRF. Troca de empresa não pode reaplicar POST/formulário antigo.
3. Filtrar autorização antes de montar linhas, contagens, busca ou paginação. Não consultar catálogo global e apenas esconder no DOM.
4. Manter `_company_context`, CSRF por recurso, `revision`, `request_key`, escopo de arquivo e destino fixo seguro. Novos fragments/JSON/endpoints precisam do mesmo isolamento e allowlist explícita.
5. Não substituir serviços financeiros por handlers nativos não isolados. Origem bancária imutável, confirmação local versus remota, snapshots, créditos/estornos e recibos continuam intactos.
6. Não copiar SMTP, certificado, API ou conta bancária entre empresas. Componentes de apresentação não devem logar/renderizar segredos ocultos.
7. Modal de nova empresa deve testar Escape/fechar/retomar, submissão dupla, erro, revisão obsoleta, outra aba e conclusão idempotente. Dismiss não cria empresa nem faz alteração silenciosa de contexto.
8. Links/recursos indisponíveis não podem ser habilitados só para parecerem com a 4.0.2; mostrar explicação em vez de conceder permissão.

## Cobertura existente e execução necessária

- `.github/workflows/deployment-integrity.yml`: `company_shell_browser.cjs`, `company_context_tabs_browser.cjs`, `company_identity_browser.cjs`, browser de identidade de clientes, Asaas e smoke real descartável `docker_provision_smoke.sh` / `issuers_browser_smoke.cjs`. O job preserva screenshots sintéticos.
- `company_shell_browser.cjs` no baseline fixa seletor no **header**, `h1` 20px e menu de rolagem própria. Atualizar a fixture/expectativas para o novo contrato sidebar; manter cobertura de submit explícito, desktop/mobile, overflow e asset loading. Não apagar teste para obter verde.
- `.github/workflows/issuer-foundation.yml`: inventário de limites, `issuers_onboarding.php`, `issuers_onboarding_concurrency.php`, `company_context.php` e testes de registry/ACL/master. Rodar novamente depois da composição.
- `.github/workflows/crm-ui-policy.yml`: contratos UI, marca/idioma e catálogo de pagamentos. `.github/workflows/native-header-layout.yml`: contrato layout nativo.
- `tests/companies/`: serviços e controllers por recurso. Rodar os suites dos controllers alterados e preservar baseline dos demais; testes estáticos não substituem browser.
- `scripts/inventory-company-boundaries.py --check` e `scripts/company-surface-inventory.php --check`: são inventários conservadores, não prova de isolamento completo. Não reclassificar recursos sem evidência para apagar avisos.
- Gates core/module version são obrigatórios mesmo para UI. Executar gerador oficial e preservar migrations históricas.

## Matriz mínima de aceite

Principal/secundária × desktop/mobile × master/gestor limitado/leitor/sem acesso, incluindo empresa inativa e vínculo revogado. Cobrir grid vazia/muitas linhas/busca sem resultado, modal encerrado/retomado, volta/avança do navegador, aba antiga após trocar empresa, permissão negada e erro de persistência. Para faturas: lista → detalhe → ação autorizada → retorno à lista, preservando request keys e revisões. Screenshots sintéticos não comprovam implantação nem autenticação na instalação real.

Este documento não contém dados de produção, credenciais ou autorização de deploy/merge.

## Catálogo de títulos e seleção inicial para enhancement progressivo

Leitura lexical do baseline, não DOM renderizado. `[dinâmico]` representa PHP. Os títulos abaixo **não são** uma allowlist automática: usar marcações explícitas para não promover títulos de registros/loops a navegação. O componente deve mover nós existentes (não clonar forms/IDs), preservar rótulos, erro/status visíveis, funcionar sem JS e selecionar detalhe quando há registro na query. Não esconder confirmação sequencial ou transformar dia de calendário em aba.

| View | h2 na ordem do código | Query de seleção no controller |
|---|---|---|
| `ai` | Conteúdo exato da solicitação → Histórico privado desta pessoa → Resultado para revisão humana | `ticket`, `operation`, `result` |
| `api_tokens` | Token criado — copie agora → Criar token → Seus tokens nesta empresa → Endpoints disponíveis | Ver controller/fragmentos |
| `asaas` | Contas e ambientes → Credenciais protegidas → Webhooks locais → Emissão avulsa → Cobranças e recebimentos → Conciliação em segundo plano → Eventos e revisão | `invoice`, `refund_origin` |
| `assets` | [dinâmico] | `download` |
| `calendar` | [dinâmico] | Ver controller/fragmentos |
| `certificates` | [dinâmico] → Remover material armazenado | Ver controller/fragmentos |
| `client_imports` | Exportar identidades próprias → Lote [dinâmico] → Gerar preview obrigatório → Seus lotes recentes nesta empresa | `export`, `after`, `batch` |
| `clients` | [dinâmico] → Lista de clientes → Novo cliente privado | `client`, `after`, `search` |
| `connect_api` | Parear a instância própria por QR → Reinício da instância própria → Aviso privado de fatura | `after`, `invoice` |
| `context` | Sem h2 literal | Ver controller/fragmentos |
| `contracts` | [dinâmico] — [dinâmico] → Lista de contratos → Novo contrato | `contract`, `file`, `pdf`, `after`, `search`, `client_search` |
| `credits` | [dinâmico] → Notas da empresa → Emitir nota de crédito | `credit`, `pdf`, `after` |
| `expense_copies` | Cópia confirmada → Origem preservada → Conferir destino antes de registrar → Dados da nova despesa | `source`, `receipt` |
| `expenses` | DES-[dinâmico] — [dinâmico] → Lista da empresa → Registrar despesa → Categorias desta empresa | `expense`, `file`, `recurring`, `search`, `after` |
| `google_mail_oauth` | Recuperação de acesso do portal desta empresa | `state`, `code`, `error` |
| `hr` | Acesso de participante → Vínculos da empresa → [dinâmico] · [dinâmico] → Estrutura da empresa → Organograma por vínculo → Biblioteca de treinamentos → Orientações internas → Relatórios → Importar vínculos revisados → Arquivos privados deste registro | `section`, `employee`, `after`, `report`, `files_purpose`, `files_object`, `purpose`, `object`, `file`, `contract`, `assignment` |
| `hr_mine` | Avisos privados → Treinamentos → Orientações publicadas | Ver controller/fragmentos |
| `imports` | Exportar despesas próprias → Lote [dinâmico] → Gerar preview obrigatório → Seus lotes recentes nesta empresa | `export`, `after`, `batch` |
| `invoice_copies` | Origem [dinâmico] — [dinâmico] → Nova fatura [dinâmico] emitida | `source`, `receipt` |
| `invoice_drafts` | Copiar [dinâmico] para rascunho privado → [dinâmico] #[dinâmico] → [dinâmico] → Lista privada da empresa | `draft`, `copy_invoice`, `attempt`, `after` |
| `invoice_recurring` | Série [dinâmico] → Séries desta empresa → Nova série com a origem atual | `schedule` |
| `invoices` | [dinâmico] — [dinâmico] → Lista da empresa → Emitir fatura | `invoice`, `attachment`, `preview`, `pdf`, `after` |
| `leads` | [dinâmico] → Kanban e lista → Novo lead → Importar CSV → Minha caixa interna → Status e fontes desta empresa | `search`, `state`, `stage`, `lead`, `batch`, `after`, `notice_after` |
| `ledger` | Consulta preservada → Cobertura do histórico → Efeitos dos eventos originais capturados → Eventos desta página | Ver controller/fragmentos |
| `microsoft_mail_oauth` | Notificações de documentos desta empresa → Recuperação de acesso do portal desta empresa | `state`, `code`, `error` |
| `onboarding` | Recursos da empresa → Clientes privados por padrão → Confira antes de criar | `step` |
| `post_refund` | Fatura [dinâmico] | `invoice` |
| `post_refund_manual` | Fatura [dinâmico] | `invoice` |
| `project_billing_rates` | [dinâmico] | `project` |
| `project_copies` | Cópia confirmada → Origem: [dinâmico] | `source`, `receipt` |
| `project_extras` | [dinâmico][dinâmico] | `kind`, `id`, `file`, `preview` |
| `project_fixed_billing` | [dinâmico] | `project` |
| `project_portal_rules` | [dinâmico] | `project`, `task`, `after`, `discussion`, `milestone` |
| `projects` | [dinâmico] → Lista de projetos → Novo projeto | `project`, `after`, `client_search` |
| `purchases` | Numeração e câmbio → Fornecedores → [dinâmico] → Requisições → Nova requisição → Pedidos emitidos → Contas a pagar → Posição de contas a pagar | `tab`, `request`, `download`, `order_pdf`, `from`, `as_of`, `format`, `after`, `supplier` |
| `pusher` | Avisos de tarefas atribuídas a você → Enfileirar aviso ao responsável atual → Suas tentativas nesta empresa | `reader` |
| `recurring` | Série [dinâmico] → Séries desta empresa → Agendar nova série | `schedule` |
| `registry` | Cadastro de empresas → [dinâmico] → [dinâmico] | Ver controller/fragmentos |
| `reports` | Valores observados em recibos com composição → [dinâmico] | `report`, `export` |
| `sales` | [dinâmico] → Acesso no portal por contato → Documentos desta empresa → Emitir [dinâmico] | `kind`, `document`, `pdf`, `review`, `search`, `after` |
| `staff` | [dinâmico] → Colaboradores vinculados → Criar colaborador para esta empresa → Vincular identidade existente | `staff`, `search`, `after` |
| `standalone_billing` | [dinâmico] | `task`, `q`, `customer_after`, `after` |
| `standalone_tasks` | [dinâmico] — [dinâmico] → Lista da empresa → Nova tarefa avulsa | `task`, `after` |
| `task_billing` | [dinâmico] | `task` |
| `task_copies` | Cópia confirmada → Origem: [dinâmico] | `source`, `receipt`, `kind` |
| `task_recurring` | Agenda #[dinâmico] — [dinâmico] → Revisar agenda para [dinâmico] → Agendas [dinâmico] | `kind`, `schedule`, `source`, `after` |
| `tasks` | Projeto: [dinâmico] | `task`, `project`, `after` |
| `ticket_connect` | Sem h2 literal | `ticket` |
| `tickets` | #[dinâmico] — [dinâmico] → Lista de chamados → Novo chamado | `ticket`, `file`, `after`, `search`, `client_search` |
| `time_batches` | Lote preservado #[dinâmico] → Escolha um cliente próprio ativo → [dinâmico] · [dinâmico] | `origin`, `kind`, `client`, `timer_after`, `search`, `after` |
| `time_billing` | [dinâmico] | `origin`, `project`, `timer_after`, `after` |

### Casos que exigem opt-in ou marcação específica

- **Calendário:** h2 em loop para dias/semana; somente barra de filtros e preferências são ações agrupáveis. Nunca construir uma aba por dia.
- **RH:** listas e estrutura com h2/h3 em loops, múltiplas tabelas e fragmentos; precisam agrupamento funcional explícito, não por cada nome de vínculo/treinamento.
- **Asaas/Connect:** conta/ambiente selecionado e estado da operação influenciam formulários. Não ocultar erro de credencial/operação em aba inativa, nem agrupar segredos e operação financeira indiscriminadamente.
- **Assistente:** conteúdo enviado, resultado e histórico fazem parte da mesma revisão humana. Não esconder o conteúdo exato da solicitação ao mostrar resultado.
- **Cópias / pós-reembolso / faturamento por horas:** são confirmação/revisão transacional, não CRUD simples; conservar evidência, revisão e confirmação no mesmo fluxo.
- **project_portal_rules:** h1 fora de `company-settings-card`; seletor restrito a cards não alcança a página. Preservar dependência entre publicação do projeto, filhos e permissões.
- **ticket_connect:** sem h2 literal; formulários vêm de fragmentos. Aplicação por heading não cobre ações.
- **registry / onboarding:** pertencem ao contrato separado de grid+modal; excluir da transformação genérica.
- **Lista + detalhe:** `invoices?invoice`, `clients?client`, `projects?project`, `sales?document` e outros seletores reais devem restaurar aba/painel do detalhe após redirect; não voltar cegamente à lista e ocultar confirmação/erros. Conferir parâmetros da tabela acima, sem adivinhar nomes.
- **Acessibilidade:** tabs requerem `role`, controles/labels únicos e estado selecionado consistente; ações de criação podem ser botões de abertura, não tabs sem semântica. Erros com `role=alert` e confirmação não devem ficar invisíveis.

## Implementação desta PR (4.7.1)

- Shell compartilhado recomposto com sidebar, identidade mínima e seletor acima do perfil. Não reintroduz `init_head`, `init_tail`, modelos globais ou hooks nos controllers selados.
- Grid Empresas compartilha catálogo já autorizado, paginação/busca/ordenação locais e situação. Wizard só no modal; configuração por empresa ativa + Definições. Certificado e recuperação do responsável continuam descobríveis com suas guardas.
- Navegação lista/criação/detalhes por painéis progressivos em clientes, contratos, créditos, despesas, faturas, leads, projetos, vendas, equipe, tarefas independentes, tarefas de projeto e tickets. Formulários são movidos, nunca clonados; operações, IDs, tokens e eventos permanecem. Fluxos especializados de revisão, aprovação, banco e Asaas preservam composição sequencial.
- Definições usa categorias laterais e ações no rodapé; nenhum campo/opção/permissão removido.
- Núcleo avança pelo gerador oficial 470→471 com migration NOOP; sem alteração histórica ou downgrade.

### Evidência e limites

Contratos locais Node e 43 testes Python de release/package/inventário foram executados. PHP não existe neste workspace e a instalação foi negada; Chromium local não iniciou por restrição de socket. Por isso testes PHP e browser reais são gates de CI, não evidência local. Fixtures novas são sintéticas e não equivalem à inspeção da instalação crm.argws.com.br. Nenhuma sessão de produção, credencial ou dado real foi usado.

A PR adiciona CI dedicado ao grid/modal/painéis/Definições e mantém suites existentes de contexto, segurança, provisioning e fluxos financeiros. O status terminal do SHA final é registrado na PR antes da entrega; não há merge/deploy autorizado.
