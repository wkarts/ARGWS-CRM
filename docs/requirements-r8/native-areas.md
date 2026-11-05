# Revisão 8 — áreas nativas e utilidades (R10/R11)

## Base e alcance da evidência

Referência funcional: tag `v4.0.2`, commit `aa58b832b3c5ed04a599caacba125b056c081ba7`.
Destino conferido antes da alteração: `main`, **4.8.8**, commit
`3cd2ac577a969840c94b019f602cb69d710c0e78`. A 4.8.7 informada no requisito
não era mais o tip da MAIN. A frente 4.0.3 não foi usada como base.

Este documento cobre Assinaturas, Despesas, Contratos, Projetos, Tarefas, Suporte,
Leads, Pedido de orçamento, Utilitários e os cinco módulos que acrescentam
utilidades reais: Goals, Backup, Surveys, Exports e Einvoice. API, Connect API,
Webhooks, Manager, Conector, Chat e Ajuda online constam em
[integrations.md](integrations.md). Vendas tem inventário próprio.

**Há implementação funcional e testes reais de serviços/controladores/views nesta
PR; não há declaração de equivalência integral de R10 ou de isolamento integral de
R11.** As diferenças concretas que impedem essa conclusão estão na seção de
pendências. Preservar um bloqueio existente de uma rota sem adaptador não entrega
a funcionalidade pedida para aquela empresa.

O inventário reproduzível de métodos públicos, chamadas literais de views e hashes
está em [native-areas-inventory.json](native-areas-inventory.json). O arquivo distingue
`identical`, `changed` e `new_since_402`; separa também o delta desta PR do que já
existia na 4.8.8. Igualdade de um arquivo não prova o comportamento do sistema que
o carrega. Views chamadas por expressão/hook exigem inspeção de seus provedores;
as camadas PHP próprias dos cinco módulos foram listadas separadamente, sem
confundir bibliotecas vendorizadas com recursos de navegação.

## Navegação e recursos encontrados

A origem do catálogo principal é
`application/services/NativeAdminMenuCatalog.php`; os módulos acrescentam suas
entradas nos próprios hooks `admin_init`. A visibilidade depende de permissões,
ativação de módulos e disponibilidade dos adaptadores da empresa. Os nomes
abaixo identificam rotas reais, sem criar equivalentes por semelhança.

| Área ou entrada | Rota original | Recursos reais identificados e preservados |
| --- | --- | --- |
| Assinaturas | `subscriptions` | Tabela, criação/edição, plano Stripe, quantidade, cliente/projeto, moeda, duas taxas Stripe, termos, cancelamento, retomada, sincronização, e-mail e exclusão. É distinto de faturas recorrentes. |
| Despesas | `expenses` | Lista/preview, totais, cadastro/edição, categoria, cliente/projeto, moeda, dois impostos, modo de pagamento, referência, observação, anexos, recorrência, faturamento, cópia, PDF, importação e ações em lote. A base recente acrescenta compras e trilhas financeiras próprias. |
| Contratos | `contracts` | Lista/filtros/resumos/gráficos, contrato, conteúdo/modelos, tipo, cliente/projeto, valor e datas, PDF, envio, assinatura/declaração, renovação, cópia, notas, comentários e anexos. Contrato próprio recente acrescenta snapshot, portal privado e controles de emissão. |
| Projetos | `projects` | Lista/tabela, cadastro/edição, visão geral, tarefas, membros, marcos/Kanban, discussões/comentários, arquivos externos/privados, visibilidade, notas, horas/timers, Gantt, despesas e relações comerciais. Faturamento por tempo/fixo, tarifas, cópia revisada e regras do portal são recursos recentes preservados. |
| Tarefas | `tasks` | Lista/Kanban, formulário/modal, status/prioridade, relações, responsáveis/seguidores, checklist e templates, comentários/anexos, lembretes, tags, timers, horas e estatísticas, cópia, ações em lote, faturamento e exclusão. Recorrência, cópias revisadas e tarefas independentes recentes permanecem. |
| Suporte | `tickets` | Lista por status, abertura/detalhe, mensagem/resposta/anexo, prioridade/departamento/serviço, atribuição e cliente/contato, respostas predefinidas, status, edição de mensagens/configurações, bloqueio de remetente e operações em lote. AI/Connect/Connect API são entradas recentes separadas. |
| Leads | `leads` | Lista/Kanban e detalhe, status/fontes, atividades/notas, proposta relacionada, importação/exportação, anexos, perdido/lixo, conversão em cliente, formulário público e integração de e-mail. A lista de 13 colunas, modal e Kanban foram adaptados aos registros próprios; inbox, importação, configuração e automação recentes continuam no workspace. Campos/ações ainda sem correspondência e propriedade dos históricos estão separados em NA06. |
| Pedido de orçamento | `estimate_request` | Lista e detalhe da solicitação, status, responsável, tags, campos do formulário, conversão em proposta/orçamento/cliente, formulários de captação e seus status. Configurar formulários não equivale a ter solicitações operáveis por empresa. |
| Mídia | `utilities/media` | Gerenciador elFinder e `media_connector`; volumes/pasta e URL pública do modelo legado. |
| Exportação PDF em lote | `utilities/bulk_pdf_exporter` | Formulário com recursos existentes, período/status e geração/exportação de PDFs pelo exportador legado. |
| Calendário | `utilities/calendar` | Calendário, fontes e preferências, leitura de eventos, criação/alteração, exclusão e lembretes. Feed e endpoints recentes usam adaptadores próprios por empresa. |
| Anúncios | `announcements` | Lista, criação/edição, leitura, exclusão e fechamento de anúncio. |
| Registro de atividade | `utilities/activity_log` | Tabela de atividade global e limpeza administrativa do log legado. |
| Registro de e-mail de tickets | `utilities/pipe_log` | Tabela de processamento do piping e limpeza administrativa do log legado. |
| Tarefas pessoais | `todo` | Lista pendente/concluída, leitura, criação/edição, alternância de estado, ordenação e exclusão. Incluída por ser utilidade real acessível pelo shell, mesmo sem ser subentrada fixa de Utilitários. |

As subentradas de Suporte são derivadas do catálogo real de status; não foi
inventada uma lista fixa. Configurações relacionadas encontradas: departamentos,
prioridades, serviços, respostas predefinidas, status e filtros de spam de suporte;
fontes/status/formulários/integração de e-mail de Leads; tipos de contratos,
categorias de despesas e formulários/status de pedidos de orçamento.

### Módulos que acrescentam utilidades

| Módulo e menu | Recursos próprios encontrados | Comparação e contexto de empresa |
| --- | --- | --- |
| Goals — `goals-tracking`, `goals`, posição 24, permissão `goals.view` | Lista/tabela/widget, criação/edição de objetivo, tipo/período/meta, avaliação do progresso, responsáveis/notificações e exclusão. Ação rápida `goals/goal`. | As views do inventário coincidem com 4.0.2; o modelo recente filtra alguns cálculos de clientes por `ClientIssuerScope`. Isso não transforma o armazenamento global de objetivos nem todos seus cálculos em contratos próprios de empresa. |
| Backup — `utility_backup`, `backup`, posição 29, administrador | Listagem/download/exclusão dos arquivos; backup manual; frequência, hora e retenção de backups automáticos; execução pelo `Backup_module`. | Controller e view coincidem com 4.0.2. O módulo copia o banco configurado e usa `BACKUPS_FOLDER` e opções globais. Um backup global não pode ser apresentado como exportação da empresa selecionada. |
| Surveys — `surveys`, posição 26, permissão `surveys.view` | Editor de pesquisa; perguntas, caixas e opções; ordem; estado; participação pública por id/hash; resultados; envio e histórico; listas de e-mail, campos das listas, inclusão/remoção/importação de destinatários. Ação rápida `surveys/survey`. | Controllers/model/views inventariados coincidem com 4.0.2. Usa pesquisas, perguntas, resultados, listas e filas globais. Não foi atribuída propriedade fictícia a esses dados. |
| Exports — `csv-export`, `exports`, posição 11, administrador | CSV de clientes, contatos, Leads, Despesas e Pagamentos; períodos predefinidos ou intervalo; colunas/campos personalizados; leitura em lotes. Hook `csv_export_features` permanece. | A biblioteca registra cinco tipos próprios. Clientes/contatos têm filtros legados de propriedade; pagamentos filtram faturas; despesas filtram a fatura vinculada, permitindo despesas sem fatura. Esses filtros parciais não equivalem à exportação de todos os registros próprios das empresas. |
| Einvoice — `einvoice_module_bulk_export`, `einvoice/export`, posição 11, permissão `einvoice_module.bulk_export` | Modelos XML; edição/validação/salvamento; variáveis; saída individual de fatura/nota de crédito; exportação em lote por período/status. Configurações e templates são recursos relacionados, não a rota do menu de exportação. | A exportação de faturas aplica `InvoiceIssuerScope::applyLegacy`; placeholders de empresa usam opções globais. Templates/saídas e notas de crédito precisam de comprovação de contexto completo antes de abrir o módulo para empresas nomeadas. |

Nenhum destes cinco módulos foi alterado funcionalmente por esta frente. Os
recursos permanecem no código; sua disponibilidade em empresas secundárias não é
considerada entregue apenas porque a rota está catalogada.

## Alterações implementadas

### Contratos: lista original sobre fontes autorizadas

`NativeCompanyContractIndex`, `NativeCompanyContractTable`,
`NativeCompanyContractRules` e a factory apresentam a view original
`admin/contracts/manage` e a tabela original. Mantêm as nove colunas funcionais:
ID, assunto, cliente, tipo, valor, início, fim, projeto e assinatura. Campos
personalizados acrescentam somente as definições reais autorizadas. Datas com
hora usam `_dt`; datas simples continuam em `_d`.

A projeção reúne contratos próprios da empresa e, **somente na empresa principal**,
os contratos históricos permitidos pela regra existente de relação com cliente.
Os IDs próprios usam `NativeCompanyResourceId`; um ID bruto legado não é
reinterpretado como contrato próprio. Busca, paginação, ordenação, tipos, anos,
resumos e gráficos derivam da mesma fonte. Filtros salvos passam pelo armazenamento
escopado e por compilação de operadores/colunas admitidos, com valores vinculados.
A integração compartilhada registra `contracts` em filtros e preferência de
ordenação; não restaura consultas globais do `App_table`.

O nome de cliente congelado no contrato permanece legível quando o vínculo é
revogado; o nome atual privado e seu link deixam de estar disponíveis. O gráfico
de valor agrega somente a moeda base e valores efetivamente presentes. Valor
não registrado permanece ausente. A declaração administrativa de assinatura é
exibida separadamente da assinatura efetiva do cliente.

Na empresa principal, o botão original continua atendendo a criação histórica
permitida; a criação com o portal recente permanece disponível. Registros próprios
abrem o detalhe emitido existente; a ação de exclusão leva aos seus controles
explícitos, sem transformar um link GET em mutação. Renovações, notas, arquivos,
discussões, PDF e entregas não foram removidos para forçar igualdade de telas.

### Despesas: formulário original com contratos financeiros atuais

`NativeCompanyExpenseOriginalForm` e `NativeCompanyExpenseOriginalHttp` conectam
`expenses/expense` à view real `admin/expenses/expense.php`. O HTML conserva o
painel, ordem e campos originais, com catálogos, contexto e tokens da empresa:
nome, referência, categoria, data, valor, moeda, imposto 1, imposto 2, modo de
pagamento, cliente, projeto, declaração faturável, observação, campos
personalizados e opções de repetição.

O adapter mantém o perfil `native_expense_v1` já introduzido na base recente. A
taxa escalar permanece `NULL` quando existem os dois impostos independentes;
nenhum campo é convertido para uma soma arredondada de alíquotas. O caminho
comum de valores usa o cálculo racional atual e centavos finais. A edição pelo
formulário simples retido também preserva o perfil: transmite nomes, referência,
modo e a lista de impostos, e normaliza apenas a posição HTML da lista de string
para inteiro. Uma lista vazia tem marcador explícito, removido pelo decoder.

Gravação do pai, metadados, declaração faturável, campos personalizados e eventual
agenda de recorrência compartilham uma transação proprietária. O serviço existente
de despesas expõe a composição `changeInOwnedTransaction`; o agendador expõe
`createInOwnedTransaction` com os mesmos controles de esquema, autoridade,
referências, revisão e idempotência. Não foi criado um segundo escritor financeiro.
A prova privada de criação autoriza salvar campos personalizados apenas durante
esse pai recém-criado, dentro da mesma transação/objeto/empresa/ator. Um objeto
concluído não pode reutilizar a prova depois.

A recepção original usa uma chave idempotente e confirma a escrita antes de
responder com o ID canônico. Alterar o corpo sob a mesma chave é conflito. Falhas
na recorrência ou no recibo final desfazem pai, perfil, campos, lançamentos e
recibos desta tentativa. Despesas escalares antigas, despesas canceladas ou
vinculadas a faturamento reservado/emitido, e agendas ativas/pausadas conservam
seu fluxo específico recente; não há migração silenciosa desses contratos.

A troca de cliente chama o endpoint real
`expenses/get_customer_change_data/<id>?source=company`. O serviço valida o
cliente autorizado antes e depois da projeção, filtra projetos e contatos e
verifica suas permissões. O JavaScript limpa os destinatários anteriores antes
da resposta. O contexto explícito também força o adaptador na empresa principal;
o gate compartilhado admite somente GET/HEAD para essa leitura.

O anexo usa o serviço privado existente e suas regras de tamanho/formato, depois
de confirmada a despesa. Salvar a despesa e enviar o arquivo são duas operações
explícitas: falha no upload informa o erro e mantém link para o pai confirmado.
O cliente evita duplicação enquanto um envio está pendente; aceita JSON objeto
ou string; não informa sucesso silencioso de um comprovante rejeitado. Mensagens
e opções de contatos são inseridas como texto, preservando o escape.

Os controles recentes de cópia revisada, recorrência, declaração/faturamento,
comprovantes privados, histórico e categorias permanecem disponíveis. A view
original aponta para a gestão atual de categorias; o modal antigo contém
semântica de descrição que o catálogo próprio não possui, diferença registrada
como pendência em vez de descartar dados ou inventar um campo.

### Projetos: campos personalizados no índice

A projeção original já existente de Projetos tratava o ID canônico de um campo
personalizado como ID inteiro bruto. `NativeCompanyProjectPage` agora decodifica
a identidade com `NativeCompanyResourceId::decode('custom_field', ...)` nos dois
pontos que montam colunas e opções. O teste usa definição/valor reais e cobre
busca e ordenação pela coluna adicional. O ajuste evita o erro de faixa de ID;
não altera permissões nem formato dos registros.

Os adaptadores recentes de formulário, detalhe, membros, marcos, tarefas,
discussões, arquivos, horas, Gantt, cópia e faturamento foram inventariados e
preservados. Não são implementações novas desta PR. Ver a pendência relativa às
abas comerciais, cuja associação atual difere da referência.

### Leads: lista, modal e Kanban sobre o contrato próprio

`CompanyLeadsDirectory`, `NativeCompanyLeadHttp`, `NativeCompanyLeadInput` e a
factory de filtros conectam as rotas originais de índice, tabela, modal e Kanban
aos escritores e registros próprios de `CompanyLeads`. A view conserva a estrutura
do índice e as 13 posições de colunas da referência, os resumos por status, o modal
com perfil/anexos/notas/atividade e as classes do Kanban. Não executa o modelo
global antigo para preencher essas superfícies.

Nome, empresa, e-mail, telefone, responsável, status, origem e criação vêm de dados
reais. Valor, tags e último contato permanecem vazios enquanto a extensão opcional
de persistência desses campos não estiver concluída; não foram preenchidos com
zero ou datas presumidas. O inventário de todos os campos ausentes e o plano de
extensão estão em [leads-extension-plan.md](leads-extension-plan.md).

A tabela faz busca literal, paginação, ordenação, filtros e contagens na fonte da
empresa selecionada. O namespace de filtros salvos `company_leads` evita reaplicar
IDs de status/origem dos filtros legados. IDs de status e origem continuam sendo
os IDs próprios de catálogo; apenas a identidade do Lead usa o namespace canônico
`company_lead`. Preferências de ordenação respeitam a opção existente
`save_last_order_for_tables`.

O modal cria/edita pelos escritores existentes, mostra anexos privados e notas,
registra a atividade real e mantém arquivamento/restauração e conversão em cliente
privado. O contrato de conversão continua criando o contato inativo previsto na
base recente. Não substitui esse comportamento pela conversão legada com efeitos
externos diferentes. Revisão, idempotência, CSRF, vínculo de empresa e permissões
são verificados antes de operar. A renderização é armazenada em buffer e revalida
contexto/permissão antes de liberar HTML; revogação ou troca durante a renderização
descarta a resposta parcial.

O Kanban pagina 25 registros por coluna e grava a mudança de status usando a
revisão atual. Reordenar dentro da mesma coluna é cancelado porque a persistência
de posição ainda não foi implementada nesse contrato; não informa uma ordenação
salva que desapareceria ao recarregar. Respostas atrasadas de leitura são
descartadas, formulários pendentes não duplicam envios e a troca de empresa
invalida requisições antigas. Nenhuma destas proteções substitui os controles do
servidor.

O workspace recente permanece em `leads?workspace=1`, com inbox, importação,
configuração, automação, paginação antiga e seus formulários. Queries antigas
continuam selecionando esse fluxo. A principal mostra seus registros próprios;
não adota os Leads históricos globais por coincidência de ID. Foram preparados
testes positivos MySQL e um fluxo Chromium em A, B e principal, mas a execução
remota deste delta ainda não foi confirmada nesta frente.

### Leads e Despesas: links de detalhe verificáveis

Leads agora interpreta `/leads/index/<id canônico de company_lead>` e valida a
coerência com `?lead=` ou `lead_id` enviado. Os links do board/inbox usam esse
formato. ID bruto, namespace de outro recurso ou dois IDs conflitantes não
abrem um registro presumido. O formato anterior de query continua atendido.
HEAD não devolve o corpo do detalhe.

`expenses/index/<id>` e `expenses/list_expenses/<id>` passam o parâmetro ao
controlador escopado, que cruza rota, query e corpo. O detalhe estrangeiro não é
hidratado. HEAD também não entrega conteúdo de página ou arquivo. Esses ajustes
restauram navegação direta. A restauração adicional de Leads está descrita acima;
a lista/preview/resumos de Despesas continua sendo a próxima implementação
compatível, sem declarar essa lacuna resolvida pelo formulário.

## O que já estava na base e foi preservado

Projetos, Tarefas, Suporte, Calendário, Anúncios e Tarefas pessoais já possuem
adaptadores para partes substanciais das views originais. O inventário de ações
no fim deste documento abrange seus caminhos reais. A existência de serviços e
de testes anteriores não é um resultado de homologação desta PR.

Para Tarefas: modal/formulários, Kanban/lista, checklist, responsáveis/seguidores,
comentários/anexos, lembretes, prioridade/status e timers continuam usando suas
políticas e revisões. Para Suporte: listagem/resumo, filtros, cliente/contato,
resposta/mensagem, arquivos, status/serviço/departamento, bloqueio e lote
continuam nos contratos recentes. Calendário conserva feed/eventos/preferências,
lembretes e autorizações. Anúncios/Todo conservam as views e operações próprias
via `NativeCompanyAuxiliaryUi`. Nenhum desses recursos foi removido ou substituído
por uma página genérica.

## Testes e natureza da prova

Os testes abaixo executaram localmente em **PHP 8.3**, usando SQLite sintético
descartável quando exigem banco. O teste portátil de Leads renderiza HTML real sem
usar banco. Os testes de UI PHP carregam o controller, serviços e view reais;
framework de sessão/transportes/shell pode ser substituído por spies conforme o
cabeçalho de cada teste. Não equivalem a um servidor HTTP completo. Testes Node
executam o script emitido pela view em VM com transportes/DOM simulados; não são
capturas de Chromium.

| Arquivo | Evidência obtida |
| --- | --- |
| `tests/companies/native_contract_index.php` | 27 verificações adicionais (incluindo JSON null versus texto literal null): união principal/histórica, exclusão de empresa alheia, filtros, custom fields, busca/ordenação, resumos/gráficos e ausência de valor inventado. Fixture auxiliar `native_contract_index_schema_fixture.php`. |
| `tests/companies/contracts_controller.php` | 74 verificações cumulativas do controller, incluindo render da listagem original e contratos atuais; pré-requisitos executados pelo próprio teste. |
| `tests/companies/contracts_primary_union.php` | 82 verificações cumulativas; autorização de índice/tabela principal com união escopada. |
| `tests/companies/native_contract_actions.php` | 32 verificações adicionais de ações reais e armazenamento; agora usa a apresentação original do índice. |
| `tests/companies/native_project_page_custom_fields.php` | 50 verificações cumulativas; inclui o fixture original `native_project_page.php` (49) e acrescenta definição/valor de campo personalizado real, coluna adicional, busca e ordenação. A regressão fica em script separado para preservar as oito colunas esperadas pelos testes filhos. |
| `tests/native_lead_navigation.php` | 12 verificações do parser real de identidade/rota. Não testa toda a base Leads nem a tela em browser. |
| `tests/native_lead_original_transport.php` | 31 verificações: views e tabela reais, serialização dos controles originais para o decoder, IDs/catálogos, escape, revisão, leitura/arquivo, 13 colunas, filtros vinculados e ausência de valores inventados. Shell, sessão e transporte são spies declarados. |
| `tests/native_company/lead_transport.test.cjs` | 6 testes do coordenador real: identidade, resposta obsoleta, troca de empresa, envio pendente único, falhas e geração criptográfica de chave. Não é execução de navegador. |
| `tests/companies/native_expense_navigation.php` | 5 verificações adicionais sobre 125 anteriores: detalhe por rota, colisão query/rota, ID bruto, outra empresa e HEAD. |
| `tests/companies/native_expense_custom_fields.php` | 13 verificações adicionais: pai e campos reais, recibo idempotente, campo obrigatório/estrangeiro, rollback tardio, prova de criação expirada, acesso B a recurso A negado. |
| `tests/companies/native_expense_original_form.php` | 21 verificações adicionais: mapeamento/catálogos, dois impostos, DTO, campos personalizados, edição/revisão, recorrência atômica, replay e rollback por erro real na gravação da agenda. |
| `tests/companies/native_expense_original_http.php` | 19 verificações adicionais: controller/view/fields helper reais, contexto e CSRF, JSON, HEAD, verbos recusados, cliente estrangeiro, XSS escapado, anexo forjado e contexto não selado. |
| `tests/native_company/expense_form.test.cjs` | 6 testes do script emitido: envio pendente único, objeto/string JSON, falhas, upload posterior à confirmação, erro persistente e limpeza de destinatários na troca de cliente. |
| `tests/companies/native_expense_fields_transport.php` | Render dos controles reais, serialização URL-encoded, decoder real e escritor financeiro; precisão decimal de dois impostos, texto escapado, posições canônicas, lista vazia e rollback de posição inválida. 9 verificações adicionais aprovadas no reteste local; CI MySQL ainda pendente. |
| `tests/companies/expense_recurring_atomic.php` | Regressão: 168 verificações cumulativas aprovadas. |
| `tests/companies/expense_recurring_controller.php` | Regressão: 204 verificações cumulativas aprovadas. |
| `tests/companies/expense_api_controller.php` | Regressão: 154 verificações cumulativas aprovadas. |
| `tests/companies/native_expense_form_profile.php` | Regressão do perfil financeiro existente: dois impostos, half-cent, cópia, conversão e filho recorrente; 89 verificações cumulativas no checkpoint anterior. |
| `tests/native_r8_routes.php` | Teste do gate real de rotas/contexto mantido pela frente compartilhada: principal/secundária, Contratos, Despesas e rotas de Vendas; resultados finais no relatório geral. |

`tests/companies/contracts_browser.cjs` teve seus seletores ajustados para a
listagem original. **Não foi executado localmente**. O ambiente local rejeita
sockets necessários a MySQL/Chromium; a CI remota é necessária para esses gates.

Preparados e incluídos no manifesto, com execução remota pendente neste delta:

- `tests/companies/native_lead_original_http.php`, exclusivo MySQL, inclui
  `native_lead_directory.php`: readers/filtros e controller/views/writers reais,
  empresas A/B/principal, histórico global preservado, criação/replay/edição,
  conversão, notas, paginação do Kanban, revisão, CSRF, HEAD e revogação de
  autorização/contexto durante a renderização. O fixture recusa SQLite; não há
  adaptação de dialeto que simule o contrato MySQL.
- `tests/companies/leads_browser.cjs`: conserva os cenários anteriores no
  workspace e acrescenta criação/edição/notas/anexo na lista/modal originais,
  arraste real entre colunas, leitura posterior do status, negações A/B e
  capturas desktop/mobile. Executar um teste de DOM Node não comprova esses
  cenários Chromium.

Após recuperar o runtime, foram repetidos os testes novos e regressões afetadas
com PHP 8.3.6, `display_errors=1` e `error_reporting=E_ALL`: Contratos (índice,
controller, união principal e ações), Projetos, identidade de Leads, navegação
de Despesas, formulário/HTTP, serialização decimal e recorrência/API de
Despesas. Todos terminaram com código zero, sem warning/notice/deprecated/fatal.
Os seis testes Node do script emitido também passaram; 59 arquivos PHP foram
verificados com `php -l`, e os scripts Node alterados passaram em `node --check`.
O teste filho `native_customer_project_table_scope.php` também passou após
isolar o cenário de campo personalizado, mantendo o controller e as negações de
escopo/CSRF. Esses retestes referem-se à árvore de trabalho deste checkpoint, não a um SHA
final remoto nem a uma execução de navegador.

Não houve acesso a banco real, e-mail real, Stripe real, fila produtiva, backup
produtivo ou mudança em produção. Não há alegação de SMTP entregue, liquidação
financeira ou homologação fiscal.

## Pendências que impedem equivalência integral

| ID local de evidência | Diferença confirmada no código | Tratamento nesta PR e decisão necessária |
| --- | --- | --- |
| NA01 | O contrato próprio recente é emitido com snapshot imutável. Não possui o mesmo vínculo `project_id`, fim opcional e edição livre de conteúdo do contrato legado. | Lista original restaurada; emissão/portal/renovação recentes preservados. Decidir o fluxo compatível para alterar conteúdo/metadados após emissão antes de aproximar o detalhe antigo. Não transformar snapshot em texto mutável silenciosamente. |
| NA02 | A listagem/detalhe principal recente de Despesas continua seu workspace próprio; os índices/totais/exportações/preview do legado não têm uma projeção única cobrindo todos os registros próprios. | Formulário original e links recuperados; recursos recentes preservados. Falta portar a listagem/preview/resumos/importação/lote com vínculo financeiro e contexto corretos. |
| NA03 | Despesas escalares antigas e despesas com agenda/faturamento já vinculados não aceitam substituição por um perfil editável com dois impostos. A categoria própria tem nome/estado, sem descrição antiga. | Mantido fluxo recente desses contratos e gestão de categorias; o original atende os contratos compatíveis. Definir eventual migração explícita e conteúdo de categoria se desejado. |
| NA04 | `Subscriptions` conserva Stripe e tabelas legadas globais; não foi encontrado serviço/tabela própria de assinatura Stripe admitido para empresa nomeada. | Nenhuma equivalência presumida com faturas recorrentes. É necessário determinar propriedade dos planos/assinaturas/clientes e credenciais atuais antes de liberar operações nesse contexto. |
| NA05 | `Estimate_request` conserva solicitações legadas. Os serviços de formulários próprios cobrem configuração de captação, não o ciclo dos registros recebidos/conversão. | Recursos legados preservados. Falta contrato de propriedade e vínculo das solicitações, campos, atribuição e conversão. |
| NA06 | Lista/modal/Kanban próprios foram adaptados, mas campos opcionais e algumas ações antigas ainda não possuem persistência compatível; históricos globais, visibilidade pública e efeitos da conversão legada têm contratos distintos. | A extensão de campos é trabalho de implementação autorizado, detalhado em `leads-extension-plan.md`; não exige nova decisão só por faltar um campo. Propriedade/migração dos históricos e alteração de efeitos/permissões reais continuam exigindo definição antes de mudança. Workspace/inbox/importação/automação preservados; testes MySQL/browser deste delta pendentes. |
| NA07 | Notas de crédito agora usam o vínculo tipado capturado do projeto, sem inferência pelo cliente. A relação de Contratos ainda usa o cliente; `project_subscriptions` consulta `company_recurring` (faturas recorrentes), embora a referência seja Assinaturas. | `native_project_credit_relation.php` passou 11 checks adicionais: dois projetos do mesmo cliente, ausência de vínculo, A/B, permissões e preservação dos dados. MySQL deste delta pendente. Contratos segue em adaptação; distinguir Assinaturas de séries recorrentes sem remover recursos ou atribuir equivalência presumida. |
| NA08 | Mídia, exportação PDF em lote, activity_log e pipe_log conservam volumes/exportadores/logs globais; os clear endpoints legados esvaziam tabelas globais. | Não abrir consulta ou limpeza global como se fosse filtrada por empresa. Definir propriedade/migração dos arquivos e estratégia de logs/exportações; a trilha de auditoria própria recente não é prova de equivalência desses logs. |
| NA09 | Goals/Backup/Surveys/Exports/Einvoice mantêm operações globais ou filtros somente parciais. | Inventário completo das entradas e ações mantido, sem remoção. Decisão necessária para operações de instância, arquivos, filas, templates e dados históricos antes de expandir a admissão por empresa. |
| NA10 | Não houve comparação visual lado a lado com dados/telas iguais, nem browser real com troca A→B→A, nem concorrência MySQL local nesta frente. | Gates remotos e homologação visual continuam pendentes. Teste sintético de autorização não autoriza declarar AC54/57–64 integralmente cumpridos. |

Estes identificadores são achados locais de execução, **não novos requisitos R12**
e não ampliam o pedido. AC50/55 têm inventário e evidências desta frente, mas
cobertura documental não transforma as pendências de AC51/54 e R11 em aceite.
AC52/53 motivam preservar recursos/contratos novos e apresentar esses conflitos.
AC56 permanece respeitado: o travamento relatado em **Vendas > Pagamentos** não é
atribuído a estas áreas. O erro de ID de campo personalizado em Projetos foi
identificado no caminho específico e testado; não é extensão daquele relato.

## Inventário por arquivo e ação

As contagens abaixo referem-se ao conjunto controller/views/model/entrypoint
selecionado no JSON. Camadas adicionais próprias de módulos estão listadas em
`utility_resource_layers`. `changed` pode ser mudança anterior a esta PR; use
`changed_this_pr` para atribuir o delta correto. O JSON contém o caminho e hash
individual, permitindo verificar cada contagem após recuperar os objetos Git.


| Área | Arquivos | Iguais à 4.0.2 | Diferentes | Novos | Alterados nesta PR |
| --- | ---: | ---: | ---: | ---: | ---: |
| assinaturas | 7 | 7 | 0 | 0 | 0 |
| despesas | 22 | 11 | 3 | 8 | 4 |
| contratos | 15 | 7 | 4 | 4 | 4 |
| projetos | 59 | 27 | 15 | 17 | 0 |
| tarefas | 23 | 4 | 12 | 7 | 0 |
| suporte | 32 | 13 | 12 | 7 | 0 |
| leads | 24 | 12 | 4 | 8 | 11 |
| pedido_orcamento | 9 | 9 | 0 | 0 | 0 |
| utilitarios | 12 | 6 | 5 | 1 | 0 |
| anuncios | 5 | 1 | 4 | 0 | 0 |
| tarefas_pessoais | 4 | 1 | 3 | 0 | 0 |
| utilitario_goals | 10 | 8 | 2 | 0 | 0 |
| utilitario_backup | 5 | 5 | 0 | 0 | 0 |
| utilitario_surveys | 21 | 21 | 0 | 0 | 0 |
| utilitario_exports | 5 | 4 | 1 | 0 | 0 |
| utilitario_einvoice | 11 | 10 | 1 | 0 | 0 |

### Métodos públicos e views literais

A lista é exaustiva para os controllers abaixo. Métodos públicos identificam a superfície do controller; a admissão por verbo/contexto deve ser conferida em `NativeCompanyRoutes` e `CompanyRequestScope`, não inferida desta lista. Construtores não são ações de usuário.

#### application/controllers/admin/Subscriptions.php

Ações atuais: `cancel`, `create`, `delete`, `edit`, `index`, `resume`, `send_to_email`, `sync`, `table`.

Acréscimos desde 4.0.2: nenhum. Remoções observadas: nenhuma.

Views literais: `admin/subscriptions/manage`, `admin/subscriptions/subscription`.

#### application/controllers/admin/Expenses.php

Ações atuais: `add_expense_attachment`, `bulk_action`, `categories`, `category`, `convert_to_invoice`, `copy`, `delete`, `delete_category`, `delete_expense_attachment`, `expense`, `get_customer_change_data`, `get_expense_data_ajax`, `get_expenses_total`, `import`, `index`, `list_expenses`, `pdf`, `purchases`, `table`.

Acréscimos desde 4.0.2: `purchases`. Remoções observadas: nenhuma.

Views literais: `admin/expenses/expense`, `admin/expenses/expense_preview_template`, `admin/expenses/expenses_total_template`, `admin/expenses/import`, `admin/expenses/manage`, `admin/expenses/manage_categories`.

#### application/controllers/admin/Contracts.php

Ações atuais: `add_comment`, `add_contract_attachment`, `add_external_attachment`, `add_note`, `clear_signature`, `contract`, `copy`, `delete`, `delete_contract_attachment`, `delete_contract_type`, `delete_renewal`, `edit_comment`, `file`, `get_comments`, `get_notes`, `get_template`, `index`, `mark_as_signed`, `pdf`, `remove_comment`, `renew`, `save_contract_data`, `send_to_email`, `table`, `type`, `types`, `unmark_as_signed`.

Acréscimos desde 4.0.2: `file`. Remoções observadas: nenhuma.

Views literais: `admin/contracts/comments_template`, `admin/contracts/contract`, `admin/contracts/manage`, `admin/contracts/manage_types`, `admin/contracts/templates/`, `admin/includes/sales_notes_template`.

#### application/controllers/admin/Projects.php

Ações atuais: `add_discussion_comment`, `add_edit_members`, `add_expense`, `add_external_file`, `billing_rates`, `bulk_action_files`, `change_activity_visibility`, `change_file_visibility`, `change_milestone_color`, `copies`, `copy`, `delete`, `delete_discussion`, `delete_discussion_comment`, `delete_milestone`, `delete_note`, `discussion`, `discussions`, `download_all_files`, `edit_note`, `expenses`, `export_project_data`, `extras`, `file`, `fixed_billing`, `gantt`, `get_discussion_comments`, `get_invoice_project_data`, `get_pre_invoice_project_info`, `get_rel_project_data`, `get_staff_names_for_mentions`, `index`, `invoice_project`, `mark_as`, `mass_stop_timers`, `milestone`, `milestones`, `milestones_kanban`, `milestones_kanban_load_more`, `pin_action`, `portal_rules`, `project`, `remove_file`, `remove_team_member`, `save_note`, `staff_projects`, `table`, `time_billing`, `timesheet`, `timesheet_task_assignees`, `timesheets`, `update_discussion_comment`, `update_file_data`, `update_milestones_order`, `update_task_milestone`, `upload_file`, `view`, `view_project_as_client`.

Acréscimos desde 4.0.2: `billing_rates`, `copies`, `extras`, `fixed_billing`, `portal_rules`, `time_billing`. Remoções observadas: nenhuma.

Views literais: `admin/projects/_file`, `admin/projects/_milestone_kanban_card`, `admin/projects/gantt`, `admin/projects/invoice_project`, `admin/projects/manage`, `admin/projects/milestones_kan_ban`, `admin/projects/project`, `admin/projects/project_pre_invoice_settings`, `admin/projects/view`.

#### application/controllers/admin/Tasks.php

Ações atuais: `add_checklist_item`, `add_external_attachment`, `add_reminder`, `add_task_assignees`, `add_task_comment`, `add_task_followers`, `ajax_search_assign_task_to_timer`, `billing`, `bulk_action`, `change_milestone`, `change_priority`, `checkbox_action`, `checklist_command`, `checklist_visibility`, `copies`, `copy`, `delete_checklist_item`, `delete_reminder`, `delete_task`, `delete_timesheet`, `delete_user_unfinished_timesheet`, `detailed_overview`, `download_files`, `edit_comment`, `edit_reminder`, `gantt_date_update`, `get_billable_task_data`, `get_billable_tasks_by_customer_id`, `get_billable_tasks_by_project`, `get_staff_names_for_mentions`, `get_staff_started_timers`, `get_task_by_id`, `get_task_data`, `index`, `init_checklist_items`, `init_relation_tasks`, `kanban`, `list_tasks`, `log_time`, `make_public`, `mark_as`, `recurring`, `remove_assignee`, `remove_checklist_item_template`, `remove_comment`, `remove_follower`, `remove_task_attachment`, `save_checklist_assigned_staff`, `save_checklist_item_template`, `standalone`, `switch_kanban`, `table`, `task`, `task_single_inline_update`, `task_tracking_stats`, `tasks_kanban_load_more`, `time_batches`, `timer_tracking`, `unmark_complete`, `update_checklist_item`, `update_checklist_order`, `update_order`, `update_tags`, `update_task_description`, `update_timesheet`, `upload_file`.

Acréscimos desde 4.0.2: `billing`, `checklist_command`, `checklist_visibility`, `copies`, `recurring`, `standalone`, `time_batches`. Remoções observadas: nenhuma.

Views literais: `admin/tasks/_kan_ban_card`, `admin/tasks/checklist_items_template`, `admin/tasks/detailed_overview`, `admin/tasks/kan_ban`, `admin/tasks/manage`, `admin/tasks/started_timers`, `admin/tasks/task`, `admin/tasks/tracking_stats`, `admin/tasks/view_task_template`.

#### application/controllers/admin/Tickets.php

Ações atuais: `add`, `ai`, `block_sender`, `bulk_action`, `change_status_ajax`, `check_staff_replying`, `connect`, `connect_api`, `delete`, `delete_attachment`, `delete_predefined_reply`, `delete_priority`, `delete_service`, `delete_ticket_reply`, `delete_ticket_status`, `edit_message`, `get_predefined_reply_ajax`, `index`, `predefined_replies`, `predefined_reply`, `priorities`, `priority`, `service`, `services`, `status`, `statuses`, `ticket`, `ticket_change_data`, `update_single_ticket_settings`, `update_staff_replying`.

Acréscimos desde 4.0.2: `ai`, `connect`, `connect_api`. Remoções observadas: nenhuma.

Views literais: `admin/tickets/add`, `admin/tickets/list`, `admin/tickets/predefined_replies/manage`, `admin/tickets/predefined_replies/reply`, `admin/tickets/priorities/manage`, `admin/tickets/services/manage`, `admin/tickets/single`, `admin/tickets/tickets_statuses/manage`.

#### application/controllers/admin/Leads.php

Ações atuais: `add_activity`, `add_external_attachment`, `add_lead_attachment`, `add_note`, `bulk_action`, `change_status_color`, `convert_to_customer`, `delete`, `delete_attachment`, `delete_form`, `delete_note`, `delete_source`, `delete_status`, `download_files`, `email_integration`, `email_integration_folders`, `export`, `file`, `form`, `forms`, `get_convert_data`, `import`, `index`, `kanban`, `lead`, `leads_kanban_load_more`, `mark_as_junk`, `mark_as_lost`, `save_form_data`, `source`, `sources`, `status`, `statuses`, `switch_kanban`, `table`, `test_email_integration`, `unmark_as_junk`, `unmark_as_lost`, `update_all_proposal_emails_linked_to_lead`, `update_lead_status`, `update_status_order`, `validate_unique_field`.

Acréscimos desde 4.0.2: `file`. Remoções observadas: nenhuma.

Views literais: `admin/includes/modals/reminder`, `admin/leads/_kan_ban_card`, `admin/leads/convert_to_customer`, `admin/leads/email_integration`, `admin/leads/formbuilder`, `admin/leads/forms`, `admin/leads/import`, `admin/leads/kan-ban`, `admin/leads/lead`, `admin/leads/manage_leads`, `admin/leads/manage_sources`, `admin/leads/manage_statuses`, `admin/leads/native`.

#### application/controllers/admin/Estimate_request.php

Ações atuais: `convert`, `delete`, `delete_form`, `delete_status`, `form`, `forms`, `index`, `save_form_data`, `status`, `statuses`, `table`, `update_assigned_staff`, `update_request_status`, `update_tags`, `view`.

Acréscimos desde 4.0.2: nenhum. Remoções observadas: nenhuma.

Views literais: `admin/estimate_request/estimate_request`, `admin/estimate_request/formbuilder`, `admin/estimate_request/forms`, `admin/estimate_request/manage_request`, `admin/estimate_request/manage_statuses`.

#### application/controllers/admin/Utilities.php

Ações atuais: `activity_log`, `bulk_pdf_exporter`, `calendar`, `clear_activity_log`, `clear_pipe_log`, `delete_event`, `get_calendar_data`, `media`, `media_connector`, `pipe_log`, `view_event`.

Acréscimos desde 4.0.2: nenhum. Remoções observadas: nenhuma.

Views literais: `admin/utilities/activity_log`, `admin/utilities/bulk_pdf_exporter`, `admin/utilities/calendar`, `admin/utilities/event`, `admin/utilities/media`, `admin/utilities/ticket_pipe_log`.

#### application/controllers/admin/Announcements.php

Ações atuais: `announcement`, `delete`, `index`, `view`.

Acréscimos desde 4.0.2: nenhum. Remoções observadas: nenhuma.

Views literais: `admin/announcements/announcement`, `admin/announcements/manage`, `admin/announcements/view`.

#### application/controllers/admin/Todo.php

Ações atuais: `change_todo_status`, `delete_todo_item`, `get_by_id`, `index`, `todo`, `update_todo_items_order`.

Acréscimos desde 4.0.2: nenhum. Remoções observadas: nenhuma.

Views literais: `admin/todos/all`.

#### modules/goals/controllers/Goals.php

Ações atuais: `delete`, `goal`, `index`, `notify`.

Acréscimos desde 4.0.2: nenhum. Remoções observadas: nenhuma.

Views literais: `goal`, `manage`.

#### modules/backup/controllers/Backup.php

Ações atuais: `delete`, `download`, `index`, `make_backup_db`, `update_auto_backup_options`.

Acréscimos desde 4.0.2: nenhum. Remoções observadas: nenhuma.

Views literais: `backup`.

#### modules/surveys/controllers/Participate.php

Ações atuais: `index`.

Acréscimos desde 4.0.2: nenhum. Remoções observadas: nenhuma.

Views literais: chamada por expressão ou outro mecanismo; consultar o controller e a camada de apresentação listada.

#### modules/surveys/controllers/Surveys.php

Ações atuais: `add_box_description`, `add_email_to_list`, `add_survey_question`, `change_survey_status`, `delete`, `delete_mail_list`, `import_emails`, `index`, `mail_list`, `mail_list_view`, `mail_lists`, `remove_box_description`, `remove_email_from_mail_list`, `remove_list_custom_field`, `remove_question`, `remove_survey_send`, `results`, `send`, `survey`, `update_question`, `update_survey_questions_orders`.

Acréscimos desde 4.0.2: nenhum. Remoções observadas: nenhuma.

Views literais: `surveys/all`, `surveys/mail_lists/list`, `surveys/mail_lists/list_view`, `surveys/mail_lists/manage`, `surveys/results`, `surveys/survey`.

#### modules/exports/controllers/Exports.php

Ações atuais: `index`.

Acréscimos desde 4.0.2: nenhum. Remoções observadas: nenhuma.

Views literais: chamada por expressão ou outro mecanismo; consultar o controller e a camada de apresentação listada.

#### modules/einvoice/controllers/Einvoice.php

Ações atuais: `export`, `output`, `template`, `validate_and_save`, `validate_template_ajax`.

Acréscimos desde 4.0.2: nenhum. Remoções observadas: nenhuma.

Views literais: `einvoice/bulk_export`, `einvoice/template`.

