# Continuidade definitiva — ARGWS-CRM / PR 45

## 1. Objetivo e natureza deste documento

Este é o handoff autocontido que substitui o MD antigo e seus adendos como orientação de continuidade. Preserva os relatórios históricos como evidência; não manda apagá-los. O objetivo é concluir a operação multiempresa no escopo explícito abaixo, preservando as funcionalidades já entregues, e distinguir implementação interna de configuração, documentos externos e homologação.

**Diagnóstico:** a base multiempresa e numerosos fluxos próprios estão implementados. A operação integral de todos os módulos do CRM ainda não está concluída. Há funcionalidades positivas, funcionalidades parciais e rotas legadas deliberadamente bloqueadas na empresa secundária. Um bloqueio seguro não equivale à disponibilidade do recurso.

**Limite desta revisão:** leitura estática de código, testes existentes, documentação e metadados/estado do CI. Não foi executada uma nova auditoria dinâmica, instalação, upgrade, suíte, operação financeira ou homologação. Os testes históricos descritos pela PR são evidências publicadas pelo autor; a consulta do status remoto é verificação distinta. Não interpretar este documento como certificado de ausência de vulnerabilidades ou prontidão de produção.

## 2. Ponto de retomada reconfirmado

- Repositório: [wkarts/ARGWS-CRM](https://github.com/wkarts/ARGWS-CRM).
- PR: [45](https://github.com/wkarts/ARGWS-CRM/pull/45), aberta e não merged.
- Branch: `feat/banco-brasil-billing-cnab240`; destino `main`.
- HEAD: `543ff96fe2a49ceef48d8595a58e8400befc6266`.
- Base informada pela API: `aa58b832b3c5ed04a599caacba125b056c081ba7`.
- Versões publicadas: núcleo **4.5.2 / migration 452**, BB **1.2.3 /123**, Asaas **1.5.3 /153**.
- Reconferência: **21/10/2026 00:24 UTC**, correspondente a **20/10/2026 21:24 America/Bahia**.
- CI consultado por esse SHA: **16 workflows distintos, última execução de cada um success, nenhum ativo**. A resposta contém 20 execuções: duas canceladas antigas de gates de versão, substituídas por execuções verdes, e execuções verdes adicionais. Não chamar 20 execuções de 20 workflows.
- A ferramenta retorna a primeira página dos runs disparados por pull request. A conclusão refere-se ao conjunto retornado e aos 16 workflows identificados, não a qualquer execução externa ao GitHub.
- CI crítico: [Empresas](https://github.com/wkarts/ARGWS-CRM/actions/runs/39493469643), [Stacks/HTTP/Chromium](https://github.com/wkarts/ARGWS-CRM/actions/runs/39493470075), [BB](https://github.com/wkarts/ARGWS-CRM/actions/runs/39493469693).
- Últimos gates de versão: [núcleo](https://github.com/wkarts/ARGWS-CRM/actions/runs/39504586840), [módulos](https://github.com/wkarts/ARGWS-CRM/actions/runs/39504587051).
- Relatório-base: [docs/companies/pr45-validation-452.md](https://github.com/wkarts/ARGWS-CRM/blob/543ff96fe2a49ceef48d8595a58e8400befc6266/docs/companies/pr45-validation-452.md).
- Inventários mantêm `operational_ready=false` / rollout blocked. O contrato integral permanece aberto.

Antes de trabalhar, reler HEAD remoto e o estado real do ambiente de execução. Se o SHA mudou, comparar a mudança e atualizar esta fotografia; não reaplicar patches sobre uma base presumida nem transferir o verde de 452 ao próximo commit.

## 3. Contrato de continuidade e limites operacionais

1. Continuar na mesma PR e branch. Não abrir uma implementação paralela nem substituir o projeto.
2. Preservar funcionalidades, dados, identidade visual, instalação/distribuição, arquivos históricos, Asaas, notificações e os fluxos BB já entregues.
3. **Sem merge, deploy, force-push, tag ou operação bancária real por este documento.** Publicação futura exige a autorização aplicável ao trabalho; este handoff não amplia permissões.
4. Atualização remota, quando autorizada, deve ser fast-forward e conferir o SHA esperado. Não descartar commits concorrentes.
5. Seguir versionamento nativo sequencial: depois de 452, o próximo incremento funcional do núcleo será 453/4.5.3 se o HEAD continuar 452. Reconciliar o próximo número se houver publicação posterior.
6. Não modificar, renomear ou remover migrations publicadas; criar o próximo passo oficial, inclusive NOOP de verificação quando apropriado. Não incrementar módulos intocados arbitrariamente.
7. Manter mecanismos de contexto, ACL, origem, transações, idempotência, auditoria, fencing e rollback. Não liberar uma rota retirando a barreira antes de adaptar seus consumidores.
8. Não elevar memória/timeout, remover asserções ou trocar erro esperado só para deixar CI verde. Investigar causa e preservar testes originais.
9. Não registrar segredos, credenciais, tokens, certificados privados ou dados financeiros reais em documento, fixture, log ou PR.
10. Não considerar contrato integral encerrado enquanto houver fluxo dentro do escopo classificado como parcial, bloqueado, faltante ou não verificado, salvo exclusão explícita aprovada pelo usuário e documentada como mudança de escopo.

### Requisitos do usuário a preservar na continuidade

- O master legítimo de uma instalação já existente deve manter seu acesso após o upgrade, sem uma nova prova burocrática. A recuperação excepcional de legado sem identidade confiável é um procedimento distinto: não autoriza impor novamente esse procedimento ao master já reconhecido nem alterar o fluxo normal de acesso. Incluir regressão de upgrade com master existente e prova de manutenção de identidade, vínculos e permissões.
- A empresa inicial é a empresa principal do cadastro. Preservar sua identidade, seus dados e suas configurações no upgrade; não criar outra principal nem substituir silenciosamente a existente.
- Oferecer as mesmas configurações funcionais por empresa, com valores independentes e consumidores efetivamente isolados. Configurações estritamente globais de infraestrutura devem ser identificadas e justificadas separadamente, sem servir como atalho para herança operacional entre empresas.
- O compartilhamento de clientes é opcional e explícito. A ausência de consentimento mantém clientes privados; compartilhar identidade não compartilha automaticamente documentos financeiros, caixa, arquivos restritos ou integrações.
- Integrações de novas empresas começam vazias, sem herdar segredos, contas, tokens, certificados ou credenciais de outra empresa. A configuração posterior deve ser própria e autorizada.

Esses itens são requisitos de aceite da continuidade. Não alteram a classificação do código atual nesta revisão.

## 4. Como ler a matriz

- **Implementado:** caminho positivo concreto no código e/ou teste existente. Não significa implantação validada.
- **Parcial:** cobre um recorte, mas não toda a função pretendida.
- **Bloqueado:** acesso/efeito impedido deliberadamente; segurança presente, função indisponível nesse contexto.
- **Faltante:** ausência concreta do caminho operacional necessário, sustentada por writer/worker/documentação atual.
- **Não verificado:** esta revisão não examinou o caminho completo; não pode ser tratado como concluído nem como bug comprovado.

As categorias podem coexistir em subfluxos do mesmo módulo. Não contar milhares de candidatos lexicais de inventário como milhares de bugs. Não usar matrizes antigas como prova de ausência quando o código atual contém o adapter.

## 5. Matriz do núcleo e operação multiempresa

| Módulo/fluxo | Estado no 452 | Evidência e limite | Aceite para continuidade |
|---|---|---|---|
| Cadastro/edição de empresas | Implementado | Issuers scopedIndex/scopedSave; valida documento, protege principal, transação. Criar exige master; editar exige autoridade própria. | Criar A/B, impedir documento duplicado e desativação indevida; atualizar com concorrência, rollback e auditoria. |
| Master | Implementado, instalação legada condicionada | Master explícito, ativo e administrador. Admin global sozinho não é master; resolução legada usa autorização temporária por console e senha atual. | Fresh e legado sem master têm procedimento seguro e documentado; nunca promover admin implicitamente. |
| Wizard | Implementado com recorte | Rascunho por ator, retomada, revisão otimista, conclusão transacional/idempotente. Não configura SMTP/certificados/credenciais; privacidade inicial somente clientes privados. | Fluxo completo, recusa de revisão antiga/revogação, falha em cada etapa e retry sem duplicata; configuração posterior clara. |
| Seletor/contexto | Implementado | Empresas autorizadas, empresa padrão, token vinculado e rotação com CSRF. | Duas abas, troca A/B, principal, token antigo, logout/revogação e contexto de escrita; zero vazamento ou fallback. |
| Equipe/Staff | Parcial | Vínculos pelo wizard e Staff na principal; Staff nativo na secundária recebe 403. Perfil e ACL não são uma transação conjunta e podem produzir aviso parcial. | Definir e entregar gestão autorizada por empresa sem perder vínculos anteriores; tratar atomicidade/compensação de perfil+ACL com resultado inequívoco. |
| Clientes/contatos | Implementado em superfícies próprias | Compartilhamento explícito/revogação possui teste positivo. Métodos legados bloqueados não significam ausência do fluxo próprio. | Privado por padrão, compartilhamento explícito só de identidade autorizada, sem compartilhar caixa/documentos/segredos; revogação imediata em GUI/API/portal. |
| Settings por empresa | Parcial | Catálogo exato: identidade, SMTP, apresentação de email, documentos/PDF, data/hora, preferências selecionadas de tarefas/timers/suporte/arquivos. | Inventariar cada opção e seu consumidor; decidir global de infraestrutura versus empresa. Provar leitura e gravação no consumidor, não apenas formulário. |
| Tags e painel Cronjob legados | Bloqueado na secundária | Grupos tags/cronjob não estão no catálogo isolado e Settings responde 403. Workers próprios existem separadamente. | Adaptar o que é opção por empresa; manter infraestrutura global com autoridade explícita. Não herdar silenciosamente opções globais. |
| Projetos, tarefas e operações próprias | Implementado por recortes; cobertura integral não verificada | Rotas próprias e API cobrem operações delimitadas; prioridade/default/follow/timers/auto-stop já entregues. | Matriz por ação: criar, editar, listar, copiar, arquivos, seguidores, marcos, dependências, faturar, cancelar/excluir onde aplicável. Não inferir todas as ações da existência de um controller. |
| Recorrência de tarefas | Faltante | Writers de tarefas de projeto e avulsas rejeitam campos recorrentes; cron legado lê tasks; worker próprio não processa essas tarefas. | Entregar GUI/API/modelo/worker por empresa, calendário/ciclos/fuso explícitos, ACL, claims/replay, concorrência e recuperação; nunca usar cron legado sem isolamento. |
| Recorrência financeira | Implementado por recortes | Worker próprio de contratos/despesas/faturas; faturas com emissão/rascunho e notificação opt-in. | Preservar calendário e retomada; provar A/B/principal e falta de saldo/autoridade. Quitação exigida pode ser da fatura principal escolhida, não do filho do ciclo anterior: expor essa semântica claramente. |
| Calendário | Implementado, correção 450 preservada | Responsável zero legítimo vindo do portal já é tratado; original passa no 452. | Manter regressão diária/mensal, domingo/segunda, portal antes do calendário, A/B/principal e filtros/ACL; não reabrir falso diagnóstico de lentidão. |
| Faturas/créditos/despesas e posição financeira | Implementado por recortes | Writers/leitores próprios, PDF/portal/API/CSV e fontes canônicas. Não equivale ao módulo contábil inteiro. | Testar cruzamento de documentos, pagamentos, créditos, estornos, origem e exportações; revisar operações não cobertas antes de afirmar integralidade. |
| Relatórios | Parcial | CompanyReports fornece posição financeira atual e CSV. Todos os relatórios contábeis/fiscais legados não foram auditados. | Inventário de relatórios, filtros e exportações; dados, anexos e totais somente da empresa autorizada. |
| API própria | Implementado, catálogo limitado | Financeiro e operacional próprios; operações e subrecursos exatos, token persistido e autoridade revalidada. Não é CRUD universal nem autoriza API legada genérica. | Matriz endpoint×verbo×scope×empresa, negativas e positivas, expiração/revogação e concorrência. Validar cada ação requerida ausente separadamente. |
| Portal próprio | Implementado em superfícies lidas | Login/reset, documentos, projetos/tarefas, suporte, comentários/anexos, discussões, Gantt e timesheets. Paridade universal com portal legado não verificada. | Identidade contato/cliente/empresa, links públicos, recuperação, download e revogação; testar fluxo completo com contato sem privilégios internos. |
| Jobs/cron/workers | Parcial/configuração não verificada | Workers próprios existem; recorrência CLI, notificações/Connect/Pusher com configuração própria. Agendamento e entrega na instalação real não comprovados. | Agendamento documentado, lease/claim, backoff seguro, falha/reinício/concorrência, origem persistida, revogação e ausência de reenvio cego. |
| Notificações/Connect/Pusher/email | Preservar; cobertura integral não verificada | Há filas/adapters e hooks; Pusher cron é opt-in. Fila gravada não prova transporte externo. | Roteamento, templates, destinatário, segredo por empresa, opt-in, replay e uncertain; prova local de transporte simulado e externa apenas autorizada. |
| Asaas | Preservado no 452; nova revisão funcional integral não executada | Versão 153, workflows de origem/remoção e callback verdes. Não atribuir automaticamente cobertura de todas as ações/configurações. | Regressão origem/conta/ambiente, callback sem sessão, deduplicação, remoção segura, pagamentos/refunds e não regressão de UI; chamada real somente autorizada. |
| UI/catálogo de pagamentos | Preservado; paridade integral não verificada | Workflow Interface verde; menu próprio expõe fluxos delimitados. | Preservar layout/identidade, campos e ajuda; revelar indisponibilidades honestamente, testar navegação, acessibilidade básica e erro recuperável. |
| Leads/Kanban nativo | Bloqueado na secundária | AdminController fora da allowlist de CompanyRequestScope. | Vertical própria de leads: ownership, fontes/status, Kanban, conversão, anexos/importação/notificações/API/cron e ACL. |
| Compras/fornecedores (purchase) | Bloqueado na interface administrativa secundária | Controller de módulo fora da allowlist. Portal do fornecedor e todos os jobs não foram auditados. | Adaptar cadeia fornecedor→pedido→recebimento/documento/pagamento, relatórios/exportações e acessos públicos/jobs pertinentes; não apenas menu. |
| RH/organograma (hr_profile) | Bloqueado na interface administrativa secundária | AdminController fora da allowlist. Não houve auditoria completa dos demais submódulos RH. | Definir dados de identidade global versus vínculo por empresa, ACL sensível, organograma, relatórios/arquivos/jobs e módulos de RH efetivamente instalados. |
| Demais módulos legados | Não verificado individualmente; rota admin não permitida será bloqueada | Não houve auditoria exaustiva de accounting, fleet, finance, products, purchase submódulos, einvoice e demais consumidores. Inventário lexical não certifica isolamento. | Enumerar módulos realmente presentes, superfícies admin/públicas/API/jobs, contratos e testes; nenhum “todos prontos” baseado só em bloqueio ou CI. |

### Evidências de código da matriz

- Empresas: [application/controllers/admin/Issuers.php:114-L162](https://github.com/wkarts/ARGWS-CRM/blob/543ff96fe2a49ceef48d8595a58e8400befc6266/application/controllers/admin/Issuers.php#L114-L162); [application/services/issuers/IssuerRegistry.php:186-L241](https://github.com/wkarts/ARGWS-CRM/blob/543ff96fe2a49ceef48d8595a58e8400befc6266/application/services/issuers/IssuerRegistry.php#L186-L241).
- Master: [application/services/issuers/IssuerMasterResolution.php:46-L99](https://github.com/wkarts/ARGWS-CRM/blob/543ff96fe2a49ceef48d8595a58e8400befc6266/application/services/issuers/IssuerMasterResolution.php#L46-L99).
- Wizard: [application/services/CompanyOnboarding.php:110-L143](https://github.com/wkarts/ARGWS-CRM/blob/543ff96fe2a49ceef48d8595a58e8400befc6266/application/services/CompanyOnboarding.php#L110-L143); [application/views/admin/companies/onboarding.php:36-L52](https://github.com/wkarts/ARGWS-CRM/blob/543ff96fe2a49ceef48d8595a58e8400befc6266/application/views/admin/companies/onboarding.php#L36-L52).
- Contexto: [application/services/CompanyContext.php:69-L102](https://github.com/wkarts/ARGWS-CRM/blob/543ff96fe2a49ceef48d8595a58e8400befc6266/application/services/CompanyContext.php#L69-L102).
- Equipe: [application/services/issuers/IssuerStaffAccess.php:135-L158](https://github.com/wkarts/ARGWS-CRM/blob/543ff96fe2a49ceef48d8595a58e8400befc6266/application/services/issuers/IssuerStaffAccess.php#L135-L158); [application/controllers/admin/Staff.php:63-L87](https://github.com/wkarts/ARGWS-CRM/blob/543ff96fe2a49ceef48d8595a58e8400befc6266/application/controllers/admin/Staff.php#L63-L87); [tests/companies_onboarding_browser.cjs:108-L113](https://github.com/wkarts/ARGWS-CRM/blob/543ff96fe2a49ceef48d8595a58e8400befc6266/tests/companies_onboarding_browser.cjs#L108-L113).
- Compartilhamento positivo de clientes: [tests/companies_onboarding_browser.cjs:140-L155](https://github.com/wkarts/ARGWS-CRM/blob/543ff96fe2a49ceef48d8595a58e8400befc6266/tests/companies_onboarding_browser.cjs#L140-L155).
- Settings: [application/services/CompanySettingsCatalog.php:11-L35](https://github.com/wkarts/ARGWS-CRM/blob/543ff96fe2a49ceef48d8595a58e8400befc6266/application/services/CompanySettingsCatalog.php#L11-L35); [application/controllers/admin/Settings.php:244-L250](https://github.com/wkarts/ARGWS-CRM/blob/543ff96fe2a49ceef48d8595a58e8400befc6266/application/controllers/admin/Settings.php#L244-L250).
- Grupos legados concretos: [application/views/admin/settings/includes/tags.php:2-L26](https://github.com/wkarts/ARGWS-CRM/blob/543ff96fe2a49ceef48d8595a58e8400befc6266/application/views/admin/settings/includes/tags.php#L2-L26); [application/views/admin/settings/includes/cronjob.php:8-L62](https://github.com/wkarts/ARGWS-CRM/blob/543ff96fe2a49ceef48d8595a58e8400befc6266/application/views/admin/settings/includes/cronjob.php#L8-L62).
- API operacional: [application/services/CompanyOperationsApi.php:12-L46](https://github.com/wkarts/ARGWS-CRM/blob/543ff96fe2a49ceef48d8595a58e8400befc6266/application/services/CompanyOperationsApi.php#L12-L46); [application/controllers/Company_operations_api.php:17-L39](https://github.com/wkarts/ARGWS-CRM/blob/543ff96fe2a49ceef48d8595a58e8400befc6266/application/controllers/Company_operations_api.php#L17-L39); [tests/companies/operations_api.php:5-L29](https://github.com/wkarts/ARGWS-CRM/blob/543ff96fe2a49ceef48d8595a58e8400befc6266/tests/companies/operations_api.php#L5-L29).
- API financeira: [application/controllers/Company_api.php:14-L65](https://github.com/wkarts/ARGWS-CRM/blob/543ff96fe2a49ceef48d8595a58e8400befc6266/application/controllers/Company_api.php#L14-L65); [application/services/CompanyApiScope.php:18-L25](https://github.com/wkarts/ARGWS-CRM/blob/543ff96fe2a49ceef48d8595a58e8400befc6266/application/services/CompanyApiScope.php#L18-L25).
- Portal: [application/controllers/Company_portal.php:108-L158](https://github.com/wkarts/ARGWS-CRM/blob/543ff96fe2a49ceef48d8595a58e8400befc6266/application/controllers/Company_portal.php#L108-L158); [application/services/CompanyPortal.php:25-L76](https://github.com/wkarts/ARGWS-CRM/blob/543ff96fe2a49ceef48d8595a58e8400befc6266/application/services/CompanyPortal.php#L25-L76).
- Tarefas sem recorrência: [application/services/CompanyTasks.php:20-L22](https://github.com/wkarts/ARGWS-CRM/blob/543ff96fe2a49ceef48d8595a58e8400befc6266/application/services/CompanyTasks.php#L20-L22); [application/services/CompanyStandaloneTasks.php:34-L36](https://github.com/wkarts/ARGWS-CRM/blob/543ff96fe2a49ceef48d8595a58e8400befc6266/application/services/CompanyStandaloneTasks.php#L34-L36); [application/models/Cron_model.php:470-L483](https://github.com/wkarts/ARGWS-CRM/blob/543ff96fe2a49ceef48d8595a58e8400befc6266/application/models/Cron_model.php#L470-L483).
- Worker e faturas recorrentes: [application/controllers/Company_recurring_worker.php:5-L10](https://github.com/wkarts/ARGWS-CRM/blob/543ff96fe2a49ceef48d8595a58e8400befc6266/application/controllers/Company_recurring_worker.php#L5-L10); [application/services/CompanyInvoiceRecurring.php:23-L70](https://github.com/wkarts/ARGWS-CRM/blob/543ff96fe2a49ceef48d8595a58e8400befc6266/application/services/CompanyInvoiceRecurring.php#L23-L70); [tests/companies/invoice_recurring_modes.php:15-L24](https://github.com/wkarts/ARGWS-CRM/blob/543ff96fe2a49ceef48d8595a58e8400befc6266/tests/companies/invoice_recurring_modes.php#L15-L24).
- Pusher: [application/services/CompanyPusherCron.php:7-L10](https://github.com/wkarts/ARGWS-CRM/blob/543ff96fe2a49ceef48d8595a58e8400befc6266/application/services/CompanyPusherCron.php#L7-L10).
- Posição financeira: [application/services/CompanyReports.php:6-L48](https://github.com/wkarts/ARGWS-CRM/blob/543ff96fe2a49ceef48d8595a58e8400befc6266/application/services/CompanyReports.php#L6-L48).
- Leads: [application/controllers/admin/Leads.php:12-L51](https://github.com/wkarts/ARGWS-CRM/blob/543ff96fe2a49ceef48d8595a58e8400befc6266/application/controllers/admin/Leads.php#L12-L51).
- Compras: [modules/purchase/controllers/Purchase.php:7-L35](https://github.com/wkarts/ARGWS-CRM/blob/543ff96fe2a49ceef48d8595a58e8400befc6266/modules/purchase/controllers/Purchase.php#L7-L35).
- RH: [modules/hr_profile/controllers/Hr_profile.php:7-L52](https://github.com/wkarts/ARGWS-CRM/blob/543ff96fe2a49ceef48d8595a58e8400befc6266/modules/hr_profile/controllers/Hr_profile.php#L7-L52).
- Barreira compartilhada: [application/services/CompanyRequestScope.php:18-L26](https://github.com/wkarts/ARGWS-CRM/blob/543ff96fe2a49ceef48d8595a58e8400befc6266/application/services/CompanyRequestScope.php#L18-L26), [application/services/CompanyRequestScope.php:56-L106](https://github.com/wkarts/ARGWS-CRM/blob/543ff96fe2a49ceef48d8595a58e8400befc6266/application/services/CompanyRequestScope.php#L56-L106). A exceção de módulo BB não libera outros módulos.
- Pendências atuais: [docs/companies/pr45-validation-452.md:27-L31](https://github.com/wkarts/ARGWS-CRM/blob/543ff96fe2a49ceef48d8595a58e8400befc6266/docs/companies/pr45-validation-452.md#L27-L31); [docs/companies/boundary-inventory.json](https://github.com/wkarts/ARGWS-CRM/blob/543ff96fe2a49ceef48d8595a58e8400befc6266/docs/companies/boundary-inventory.json); [docs/companies/consumer-register.json](https://github.com/wkarts/ARGWS-CRM/blob/543ff96fe2a49ceef48d8595a58e8400befc6266/docs/companies/consumer-register.json).

### Inventário integral ainda a executar

A matriz acima reúne exemplos e superfícies efetivamente lidos, mais itens explicitamente não verificados. Ela não é uma auditoria exaustiva de todos os módulos. O objetivo continua integral; o executor deverá produzir um inventário completo e rastreável antes de declarar encerramento.

Para cada módulo instalado e fluxo requerido, registrar:

1. Rotas administrativas, públicas, portal, callbacks e endpoints API, com verbos, autenticação, origem de empresa, escopos e ações permitidas.
2. Tabelas e relacionamentos lidos/escritos, chaves de empresa, vínculos compartilháveis, índices, caches, transações e efeitos de migrations/upgrade.
3. Configurações: formulário, armazenamento, defaults, herança permitida ou proibida, segredo associado e todos os consumidores efetivos.
4. Consumidores: controllers, serviços, modelos, hooks, cron, filas/workers, relatórios, importações/exportações, PDFs, anexos, notificações e integrações.
5. Cada ação operacional, inclusive criar, editar, listar, consultar, copiar, cancelar, excluir quando cabível, recuperar e processar em background; não apenas a página inicial.
6. Estado por ação, evidência positiva e negativa A/B/principal, teste e resultado por SHA; dependências externas e limitações remanescentes.

Critério de aceite: cobertura rastreável de todas as superfícies requeridas, nenhum consumidor sem classificação e nenhuma ação anunciada como utilizável sustentada somente por uma recusa de acesso. A allowlist só pode ser ampliada por rota/ação após adaptar e provar toda a respectiva cadeia de consumidores; nunca liberar módulos por curinga ou retirar a barreira global para satisfazer o inventário.

### Correção de comunicação já identificada

A conclusão do wizard ainda anuncia genericamente compartilhamento/operações financeiras como pendentes. Isso contradiz fluxos próprios positivos atuais. Atualizar o texto a partir da matriz real; não remover o compartilhamento existente nem usar o texto antigo como justificativa para reimplementar tudo. Fonte: [application/views/admin/companies/onboarding.php:17-L19](https://github.com/wkarts/ARGWS-CRM/blob/543ff96fe2a49ceef48d8595a58e8400befc6266/application/views/admin/companies/onboarding.php#L17-L19).

## 6. Banco do Brasil: três trilhas independentes

Fonte atual consultada: [docs/banco-brasil-remaining-contracts.md](https://github.com/wkarts/ARGWS-CRM/blob/543ff96fe2a49ceef48d8595a58e8400befc6266/docs/banco-brasil-remaining-contracts.md). Esse arquivo contém histórico; prevalecem os recortes mais recentes. A síntese abaixo não é nova verificação dos manuais públicos nem homologação.

### 6.1 Implementação existente a preservar

- Emissão e consultas nos perfis explícitos; pedidos de baixa e alterações já implementadas com confirmação posterior.
- Outbox/claim/lease, origem/conta/ambiente e recuperação de sending/uncertain sem reenvio cego.
- Abatimento, endereço operacional, primeiro desconto e prazo em perfis permitidos; Seu Número operacional com histórico fiscal preservado.
- CNAB 240: geração/download e conciliação assistida nos recortes documentados; retorno 240 sobre origem API com proveniência própria.
- Recorte 447 de tarifa 240: ocorrência 06, carteira 17 simples, pagamento integral nominal, principal bruto separado de tarifa e líquido. Exemplo documentado 105,53−2,53=103,00. Não cria despesa fiscal nem autoriza todas as composições.
- API 16 LOCAL: declaração explícita de extinção sem caixa, restauração append-only, correlação posterior independente com pagamentos/créditos nativos. Não é pagamento fictício nem autenticação de estorno bancário.
- Auxiliares observacionais CNAB 400, registros 5/01 e 5/10, preservando bytes/proveniência; não são vertical financeira 400.
- Webhook observacional local: não transforma recebimento de webhook em pagamento.

### 6.2 Código interno ainda parcial/faltante

- Reversão da nova base tarifária 240: correlação original, principal devolvido, tarifa devolvida/retida e equação de débito.
- Composição da tarifa com juros/desconto/abatimento; IOF/outros e demais parciais/adapters/equações específicas.
- Alteração nominal com revisão financeira/fiscal, estados e provas.
- Protesto/sustação/cancelamento/negativação: pedidos/outbox/máquinas de estado e confirmação independente.
- Vertical operacional/financeira CNAB 400 após esclarecimentos documentais: origem, lote, snapshot, remessa, retorno, recibo e dinheiro.
- Efeito financeiro API 18/19 depende de contrato inequívoco de identidade/composição; observação não é lançamento.
- Transporte automatizado de arquivos só após protocolo/canal/recibo contratados.

Essas lacunas não devem ser descritas como “falta só homologar”. Cada entrega exige writer canônico, origem imutável, ACL, equação, estados, idempotência, concorrência, rollback, GUI/API/worker e prova de leitor.

### 6.3 Documentos externos indispensáveis

| Dependência | Esclarecimento necessário | Proibição enquanto faltar |
|---|---|---|
| CBR 641 BRL | Código de moeda nas posições **85–86** aplicável ao contrato/conta/convênio/carteira/variação | Não presumir branco, 00 ou 09. |
| Juros herdados 400 | Cadastro efetivo da taxa ou isenção inequívoca do beneficiário | Não supor que omissão/zero desativa herança. |
| CBR 643 total | Posições **26–39**: 14 posições versus picture `9(013)v99` de 15 dígitos; confirmar largura, escala e totalizadores | Não escolher escala por heurística. |
| API 18/19 | ID estável por crédito; valorCreditoCedente individual/acumulado; datas/componentes/reversões | Não usar delta, cursor ou hash como identidade bancária. |
| PATCH juros/multa | Campos tipo/valor/taxa/data e domínios corrigidos ou exemplo normativo inequívoco | Não copiar semântica da emissão para PATCH. |
| Transporte | Endpoint/protocolo/autenticação/recibo contratados | Não inventar SFTP/API a partir do leiaute. |

Referências primárias registradas no documento: [CBR641, junho de 2024](https://www.bb.com.br/docs/pub/emp/empl/dwn/Doc2627CBR641Pos7.pdf), [CBR643, julho de 2023](https://www.bb.com.br/docs/pub/emp/empl/dwn/Doc2628CBR643Pos7.pdf), [catálogo BB](https://www.bb.com.br/site/pro-seu-negocio/aplicativos-leiautes-de-arquivos/). O endpoint OpenAPI não estava acessível na revisão documental publicada; captura histórica não comprova especificação atual. O trailer CBR 641 já foi resolvido: não reabrir essa pendência antiga.

### 6.4 Homologação real

Permanece **não realizada**. Requer autorização específica, documentos/contrato, ambiente/credenciais seguros e provas reais por empresa/conta/convênio/carteira/ambiente, incluindo HTTPS/mTLS e recibos quando pertinentes. Testes locais, fixtures e CI não substituem aceite bancário. Avançar código independente dos documentos sem fabricar contratos ou solicitar segredos na PR.

## 7. Não repetir reparos já entregues 445–452

- Preservar 445/446 como base publicada; não reconstruir por memória ou reverter para contornar falhas.
- 447: memória do teste PHP 8.1 corrigida liberando histórico SQL entre fases; manter 255 asserções e 128 MiB. LOCAL e tarifa 240 já entregues nos limites descritos.
- 448: prioridade 1–4, default 2, cópias preservando prioridade e auto-follow opt-in.
- 449: política de status ao iniciar timer e relógio único do LOCAL, com auditoria/retry.
- 450: calendário com responsável 0 legítimo do portal e integridade concorrente de timers. HTTP 422 antigo não é prova de bug no 452.
- 451: auto-stop da mesma empresa/ator, entre modalidades, OFF por ausência, sem autoridade para parar timer de outra empresa.
- 452: composição completa das tabelas participantes nas cópias e reparo do auxiliar de diagnóstico do calendário; manter exigência transacional.
- Primeiro commit 452, `5f2b59a`, falhou inventário; HEAD `543ff96` é ajuste documental de três arquivos, sem nova mudança funcional. Não repetir migration 452 nem atribuir falhas 451 ao HEAD 452.
- As revisões 449–452 são passos sem alteração de schema (NOOP) nativos; não criar estruturas duplicadas por supor que cada incremento adicionou tabela.

Histórico remoto: 446 terminou com 14/16; 447 e 449, com 15/16; 450, com 16/16; 451, com 14/16; 452, com 16/16. Esses números são históricos por SHA, não um saldo transferível. A descrição atual da PR detalha causas e links; preservar os relatórios originais.

## 8. Sequência proposta de implementação

### Etapa 0 — reconciliar e congelar a base

Confirmar HEAD/árvore/versões, dirty state e trabalho concorrente; ler instruções do repositório; identificar comandos canônicos. Mapear inventário atual em rotas admin, públicas, API, jobs, modelos, opções, tabelas e exportações. Produzir lista exaustiva de módulos instalados, sem prometer que a matriz estática deste documento já é essa auditoria.

### Etapa 1 — fechar lacunas transversais do núcleo

Resolver gestão de equipe no contexto secundário e resultados parciais de perfil+ACL; corrigir textos do wizard; mapear cada setting e seu consumidor; manter infraestrutura global explicitamente separada. Provar nova instalação e upgrade antes de ampliar a allowlist.

### Etapa 2 — completar tarefas/recorrência/jobs

Entregar recorrência operacional das duas modalidades de tarefa e regras adicionais identificadas de timer; reutilizar writers/claims sem repetir auto-stop 451. Definir calendário/fuso/ciclos/finalização e comportamento em revogação/falha. Instrumentar operação dos workers sem dados sensíveis.

### Etapa 3 — adaptar módulos legados por vertical completa

Priorizar Leads, Compras/fornecedores e RH/organograma; inventariar os demais antes de decidir ordem. Para cada vertical, completar GUI, persistência, ACL, arquivos, API/portal quando aplicáveis, jobs, relatórios, exportações e configuração. Só então admitir as rotas correspondentes. Não liberar todos os módulos por curinga.

### Etapa 4 — fechar paridade e preservação

Completar ações requeridas ainda ausentes nas APIs/portal/relatórios e configurações. Executar regressões Asaas, UI, notificações, faturas/créditos, compartilhamento, arquivos e integrações por origem. Remover avisos obsoletos, mas conservar avisos verdadeiros.

### Etapa 5 — avançar BB em paralelo por dependência

Implementar pendências internas independentes; manter bloqueadas apenas as operações que exigem documento ausente. Quando documento chegar, registrar fonte/versão/perfil e entregar adapter com provas específicas. Homologação é etapa distinta e autorizada.

### Etapa 6 — comprovar e encerrar o contrato

Executar matriz integral na composição final, publicar evidências vinculadas ao novoSHA quando autorizado, aguardar checks terminais e resolver falhas. Atualizar inventário/status apenas com evidência. Se permanecer qualquer bloqueio, declarar precisamente qual; não escrever “concluído integralmente”.

## 9. Plano mínimo de aceitação reproduzível

### Ambientes e dados

- Instalação nova pelo instalador/App/controller reais; upgrade de banco genuíno 452 para próxima versão, sem simular upgrade criando banco já no estado final.
- Manter compatibilidade declarada, incluindo PHP 8.1 e a matriz do CI; MySQL 8 e MariaDB onde suportados, InnoDB e usuário sem SUPER nas provas pertinentes.
- Dataset: principal, empresas A/B, master, administradores não master, colaboradores restritos, usuário sem vínculo, contato de portal A/B e tokens com scopes distintos.
- Fixtures sintéticas; nenhuma credencial real. Registrar versões/runtime e limites de cada prova.

### Isolamento e autoridade

- Toda ação positiva em A deve ter negativa equivalente cruzada em B: IDs, filtros, downloads, anexos, PDFs/CSV, links públicos, tokens, jobs e caches.
- Testar seletor, aba antiga, CSRF, revisão desatualizada, expiração/revogação de token, mudança de responsável/projeto e revogação durante espera por lock.
- Principal não pode ser fallback silencioso para origem ausente. Master não torna toda mutação entre empresas válida.
- API/portal/job derivam empresa da origem persistida apropriada, nunca de sessão administrativa acidental.
- Não aceitar apenas 403 como teste de um recurso anunciado disponível: cada fluxo precisa de prova positiva autorizada.

### Transações, concorrência e recuperação

- Falha antes/depois de cada escrita/auditoria, SQL ignorado, readback divergente, rollback de todas as linhas, retry/replay e recibos únicos.
- Concorrência real com waiters observados quando a proteção depende de locks; duas operações independentes podem serializar corretamente.
- SIGKILL/interrupção de worker e recuperação durável; sending/uncertain sem reenvio cego; nenhuma duplicação de pagamento/documento/timer.
- Cópias devem incluir todos os participantes transacionais; manter regressão 452.

### Instalação e upgrade

- Snapshot e restauração verificáveis; preservar linhas, metadados, opções, arquivos e histórico.
- Migrations sequenciais, retry/replay e marcador falho/ignorado/readback; não reescrever migrations antigas.
- Novas opções seguras por ausência, sem herança de segredo e sem backfill financeiro/autorização não aprovado.
- Provar instalador fresco e upgrade genuíno separadamente; não contar ambos pelo mesmo banco resetado.

### Integrações e consumidores

- Asaas/BB/Connect/Pusher/email: empresa, conta, ambiente, destinatário, origem e segredo corretos; callbacks sem dependência de sessão.
- Consumidores de settings precisam provar efeito real. Tela salva sem uso no worker/PDF/API é entrega incompleta.
- Recorrência: calendário/fuso, ciclos, vencimento, condição financeira, bloqueio/revogação, execução simultânea e retomada sem avanço indevido.
- Portal: autenticação/reset, contato desativado, documento/anexo alheio, URL reaproveitada e fluxos positivos completos.
- UI: preservar navegação e identidade; mensagens claras de indisponibilidade e erro parcial; nenhum botão anuncia sucesso sem confirmação.

### Evidência e gates

- Relatório por comando: SHA, ambiente, precondições, resultado, duração, erro exato, limites e artefato sanitizado.
- Distinguir testes adicionais de contadores cumulativos de includes.
- Manter testes originais e acrescentar regressões RED/GREEN para causas reais; não alterar expectativa para mascarar falha.
- Conferir workflow_id distintos e última execução por SHA, com matrizes/jobs críticos; cancels substituídos não viram falhas atuais, e falhas atuais não somem no agregado.
- CI verde, inventário coerente, validação funcional e homologação são quatro evidências diferentes.

## 10. Definição de pronto e saída esperada

A conclusão interna exige:

- [ ] Inventário completo dos módulos/fluxos realmente no escopo, incluindo superfícies públicas e jobs.
- [ ] Nenhum fluxo requerido dependendo apenas de bloqueio 403 para ser chamado de implementado.
- [ ] Núcleo, equipe, clientes, settings, operações, APIs, portal e recorrências completos no escopo acordado.
- [ ] Módulos legados requeridos adaptados por vertical, com prova positiva e negativa A/B/principal.
- [ ] Asaas, UI, notificações e funcionalidades 445–452 preservadas.
- [ ] BB com implementação interna classificada por operação; documentos/homologação claramente separados.
- [ ] Fresh e upgrade genuínos, ACL, concorrência, recuperação e integrações simuladas aprovados na composição final.
- [ ] Versões/migrations sequenciais; mesma PR/branch; sem alteração destrutiva de histórico.
- [ ] CI do SHA final terminal e aprovado, sem transferir resultado de outro commit.
- [ ] Documentação e inventários refletem a realidade; todos os limites e exclusões explícitos.

Ao entregar cada incremento, informar: versão/SHA, o que mudou, o que foi preservado, lacunas restantes, testes realmente executados, CI pendente ou terminal e bloqueios externos. O termo “multiempresa integral pronta” só cabe após fechar o escopo inteiro, não após somar adapters ou obter 16/16.

Este handoff foi escrito localmente para orientar continuidade. Não publica GitHub, não altera código e não autoriza merge/deploy ou transação externa.

