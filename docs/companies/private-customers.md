# Diretório de clientes privados no núcleo

O renderer isolado `admin/company_clients` utiliza `CompanyClients` para criar, consultar,
editar a identidade própria, buscar e paginar, compartilhar explicitamente e revogar.
Ele exige `customers/view` e a capacidade específica de cada ação. O master continua
sujeito ao proprietário: nem ele edita um cliente compartilhado pela empresa destino.
Não carrega Clients_model, hooks, contatos, anexos, totais ou histórico financeiro.
O cadastro legado permanece separado; não há adoção automática.

Criar recebe chave aleatória de envio e grava recibo durável na mesma transação do
cliente, propriedade e auditoria. Repetição retorna o ID original; conteúdo, ator ou
empresa divergentes recebem conflito. A migration 406 é aditiva, verifica InnoDB,
PK integral, tipos, collation e FK e suporta replay. Edição e compartilhamento da UI
exigem fingerprint do estado consultado, revalidado sob lock. Aba obsoleta recebe409.
Busca usa parâmetros, escapa curingas e aplica visibilidade antes de retornar dados.

O destino recebe somente userid, nome, documento, estado ativo e owner. Só o dono
vê os links de compartilhamento. Adicionar exige create no destino; revogar não
exige permissão renovada no destino. CPF/CNPJ duplicado globalmente, inclusive
legado formatado, é recusado sem revelar proprietário. Sem documento, identidades
distintas são permitidas. Campos extras de ownership são recusados.

A UI usa CSRF nativo e token próprio, além do token HMAC de CompanyRequestScope
validado antes da hidratação. O wizard oferece as permissões deste diretório;
privacidade inicial continua sempre privada. O cadastro não compartilha por padrão.

Testes executáveis: `tests/companies/clients.php` (PDO SQLite/MySQL),
`tests/companies/client_concurrency.php` (somente MySQL com dois writers/locks
observados), `tests/companies_onboarding_browser.cjs` (CRM HTTP/MySQL/Chromium
na stack descartável). Resultados efetivamente executados são registrados na PR;
a existência do teste não comprova sua execução no SHA final.

**Limites técnicos:** contatos/portal privado e recuperação completa, finanças,
arquivos, relatórios e demais consumidores ainda precisam dos respectivos fluxos
positivos. Esses caminhos legados permanecem fechados às identidades privadas.
Este diretório não representa conclusão integral do multiempresa nem da integração BB.

## Histórico das barreiras anteriores (evidências dos respectivos incrementos)

# Clientes privados: continuação local

`CompanyClients` executa criação, edição, consulta por ID, paginação e alteração explícita dos vínculos de compartilhamento no cadastro nativo. Recebe empresa e ator obrigatoriamente; não consulta sessão, papel global, empresa padrão ou configurações financeiras.

O serviço exige a capacidade nativa de Clientes (`customers`) na empresa. Editar uma identidade compartilhada exige ser sua proprietária. A nova capacidade `share` é apresentada no catálogo nativo de permissões; acrescentar um destino exige também `create` no destino. O proprietário pode revogar vínculos sem depender de permissão atual no destino. Compartilhar não duplica clientes nem transfere contatos, sessões, anexos ou finanças.

Mutações utilizam a transação própria e o lock do núcleo, verificam persistência e gravam auditoria sem nome/CPF/CNPJ. Falha de auditoria reverte cliente e ownership. CPF/CNPJ duplicado, inclusive com formatação em cadastro legado, retorna conflito genérico antes da criação; não expõe a identidade da outra empresa nem faz adoção implícita. O serviço não possui envio de notificações ou hooks de módulos.

## Estado de ativação

Ainda não há rota pública/autoload de controller que invoque este serviço. O bloqueio de adoção legado permanece. Antes de habilitar a interface, é obrigatório adaptar e testar leitores diretos do núcleo e módulos, portal e contatos, exportações, pesquisas, eventos/jobs e arquivos. Escrever ownership não torna consultas SQL legadas automaticamente isoladas. A prevenção de duplicidade também depende da adaptação dos escritores legados concorrentes antes da abertura da rota.

## Evidência

`php tests/companies/clients.php`: 34 verificações locais PHP-WASM/PDO SQLite passaram em 2026-10-18. Exercitam A/B, leitura e escrita diretas, paginação, conflito de identidade, compartilhamento/idempotência/revogação, ausência de finanças, ACL, rollback e transação externa. O mesmo arquivo foi adicionado à matriz MySQL do workflow Empresas no núcleo, mas **não foi executado em MySQL nem publicado**. SQLite não comprova locks InnoDB ou concorrência real.

Esta continuação fica separada do checkpoint de publicação 05e4b54a e não altera seu payload congelado.

## Fechamento dos consumidores legados

A continuação aplica predicados de ownership em contagens/somas genéricas e DataTables de clientes/contatos, incluindo os totais e agrupamento de condições OR. Seis consumidores receberam versões e migrations NOOP: Links Personalizados 1.2.0/120, Connect Chat 1.1.3/113, Contabilidade 1.3.5/135, Metas 2.3.1/231, Automação de Marketing 1.1.0/110 e Múltiplas Empresas 1.0.6/106. Isso protege as consultas alteradas, não certifica isolamento integral desses módulos.

O sincronizador legado de contatos por e-mail agora exclui fontes/destinos privados, inclusive quando a origem passa a privada entre leitura e escrita. Não se alteraram senhas reais: os testes usam valores sintéticos. A sincronização continua restrita ao comportamento legado; ela não é mecanismo de compartilhamento entre empresas do núcleo.

`legacy_module_readers.php`: 25 verificações; `legacy_shared_helpers.php`: 14 verificações. Os testes executam modelos/helpers reais com SQL SQLite e adaptadores de I/O explícitos. Os testes existentes `client_issuer*.php` e `portal_client*.php` também foram executados sem falhas reportadas no log.

O teste nativo `module_tracking.php` reproduziu sucesso indevido do executor quando o marcador não era gravado. `App_module_migration` agora retorna falha diante de update recusado ou readback divergente. Os seis upgrades e replays NOOP, preservação dos dados, falha e trigger que ignora gravação passaram 42 verificações SQLite. As suites existentes do ciclo de vida de API/Exports/eInvoice passaram 46/42/45 verificações. MySQL real continua pendente, inclusive concorrência; isso não demonstra execução em produção.

### Relações indiretas de tarefas

`TaskClientBoundary` cobre relações nativas customer/project/invoice/estimate/contract/expense/ticket/proposal por SQL e validação antes da mutação. Tarefas de faturas com origem de outra empresa não passam mesmo quando o cliente ainda é legado. Get/cópia/add/update, seletores de cobrança, Kanban, helpers genéricos, contadores do perfil e os pontos diretos de comentários/seguidores/assignees/checklists/status/exclusão receberam barreiras. Atualizações parciais usam a relação persistida, e hooks são revalidados antes de efeitos posteriores.

`tests/companies/task_relations.php` passou 88 verificações com SQLite e chamadas reais de rejeição no Tasks_model. Há casos positivos de relações legadas, propostas de leads e tickets/despesas sem cliente. Isso **não** constitui fluxo HTTP completo de tarefas. Relações de extensões, operações por ID de comentário/anexo/timer, consultas SQL customizadas, exportação e ciclos concorrentes ainda exigem revisão antes de disponibilizar o cadastro privado. Não se liberou a rota da segunda empresa.

Em 2026-10-18, a verificação local confirmou ausência de executáveis MySQL/MariaDB, Docker e PHP nativo. O Chromium instalado falhou no lançamento com `socket() failed: Operation not permitted`. O checkout contém application/vendor/autoload.php, mas não possui app-config de instalação. A ausência de MySQL e a restrição de sockets impedem validar o fluxo HTTP completo neste ambiente. Nenhuma prova de HTTP/browser/MySQL real foi produzida; os cenários de integração permanecem nas suítes sintéticas e na configuração de CI ainda não publicada.

Projetos, despesas e tickets também passaram a aplicar a propriedade do cliente no leitor principal, nos totais genéricos e em criação/alteração/cópia/exclusão selecionadas. Tickets verificam tanto userid quanto contactid: zerar o cliente não permite anexar um contato privado. Respostas e mesclagem de tickets validam a origem antes de efeitos. A política de tarefas reutiliza essa relação indireta. A suíte `customer_relations.php` executou mais 41 verificações sintéticas além das 88 de tarefas. Essas alterações ainda não cobrem todos os métodos secundários desses modelos, nem tornam hooks legados transacionais; a ativação operacional continua bloqueada.

A consulta de orçamentos em pipeline e a projeção de conversão lead→cliente também receberam predicados explícitos. Helpers de nome de projeto, marcos, relação cliente e título de tarefa verificam o registro antes de ler caches ou tabelas; projetos fixados filtram o cliente proprietário. A suíte adicional passou 48 verificações, incluindo consulta SQLite com o predicado produzido pelo pipeline real e preservação do nome legado em cache. O adaptador do pipeline captura a construção da query; isso não substitui um teste completo da página.

Os pontos de tarefas por ID de comentário/checklist/anexo/timer agora resolvem o pai antes da ação. Reordenação mista rejeita o lote antes de gravar; editar/reclassificar timer não permite trocar uma origem privada para tarefa legada ou usar adminStop/ID zero para escapar. A parada automática de timers e a leitura direta filtram a origem; timers legados sem tarefa continuam disponíveis. Exclusão legada usa prova privada e temporária da origem, removida em finally, para preservar a limpeza após apagar o pai. O parâmetro público force não concede essa prova. A suíte `task_children.php` exercitou o cascade real de tarefa/comentários com SQLite, preservou irmão privado, confirmou timer avulso e liberação da prova na falha de banco: 23 verificações adicionais. Não há comprovação de concorrência nem de rollback completo do cascade legado.

A revisão alcançou os leitores secundários de projetos (membros, configurações, arquivos, marcos, timesheets e discussões) e o envio de e-mail por projeto. Conversão de despesa privada para fatura e adoção de mensagens de suporte por contato privado são recusadas antes dos efeitos. A suíte de relações agora possui 65 verificações adicionais; ainda faltam endpoints secundários e revisão transacional completa.

O controller real de downloads recebeu bloqueio de ownership para tickets, despesas e tarefas. O preview por caminho resolve a raiz real e verifica também projetos/despesas/tickets/tarefas antes de abrir arquivos privados. `related_file_previews.php` passou 14 verificações adicionais com arquivos locais sintéticos: caminhos privados negados para anônimo/staff, caminhos legados preservados pelo filtro de ownership e rotas de download de despesa/tarefa negadas antes de filtros/output. Isso não audita integralmente a autorização histórica de previews legados nem o servidor HTTP; não foi testado em produção.

A proteção de projetos preserva agora o cascade legado com prova temporária da origem, liberada em finally. `project_cleanup.php` exercita a exclusão nativa do projeto e sua tarefa depois que o pai foi apagado; mantém a tarefa do projeto privado e não limpa referências de despesa privada. Arquivos por ID, edição, remoção e criação de anexo externo verificam o projeto antes dos efeitos. Foram 10 verificações adicionais. Uploads de projeto/tarefa/despesa validam a origem antes dos hooks; cinco verificações executaram negações sem efeito e entradas legadas vazias. Os fixtures são SQL e arquivos locais sintéticos, não um upload HTTP real.

Tickets também resolvem o pai de anexos/respostas antes de consultar ou remover. Informar um ticket legado junto com ID de resposta de outro ticket não autoriza a ação; vínculos de contato privados são recusados antes/depois do hook de resposta. Upload de ticket verifica o pai antes do hook. Os testes adicionais de relações passaram a 71 e os de upload a seis. A limpeza de projetos passou a 12 verificações, incluindo preservação de proposta privada e ticket com contato privado/cliente zerado. A triagem de mensagens recebidas por e-mail, outros métodos secundários e módulos não auditados ainda bloqueiam a liberação completa.

Despesas agora protegem também o vínculo com fatura de origem nomeada, mesmo se o cliente ainda for legado. A limpeza de projetos/despesas não altera esses vínculos nem recorrências privadas. O endpoint de remoção de comprovante valida a despesa antes do filesystem; o cascade legítimo mantém uma prova privada temporária para limpar o arquivo após remover o pai. `expense_cleanup.php` passou nove verificações adicionais com arquivos sintéticos e a exclusão nativa (somente os JOINs de exibição de despesa são adaptados no fixture). A suíte de relações passou a 73 verificações adicionais. Não houve baixa, cancelamento, emissão ou contato com provedor.

A API legada agora restringe as buscas de tarefas, projetos, tickets e despesas e os marcos de projetos antes de hidratar resultados. Predicados OR da busca e filtros customizados ficam agrupados dentro do limite de ownership. Busca textual usa o query builder com parâmetros; busca por tags usa o prefixo real e a tabela nativa taggables. Tickets retornam sua própria projeção, sem incluir hash/senha/chaves dos contatos vindos do JOIN. Contagens de tarefas de um marco também filtram tarefas privadas vinculadas indevidamente a um marco legado. A suíte `api_related_records.php` exercita os métodos reais em SQLite sintético: buscas legadas positivas, texto literal, tags, IDs diretos, tentativa de OR, caller rel_type=lead, ticket só com contato, despesa com origem de fatura nomeada e negações antes de hidratar anexos/gateways. Isso não comprova o dispatcher HTTP, autenticação do token API, concorrência MySQL ou os demais consumidores de SQL customizado. Os fluxos secundários permanecem fechados até completar essas fronteiras.

Contratos legados receberam filtro antes da hidratação, inclusive anexos, comentários, assinaturas, renovações e alertas de vencimento. IDs de filhos são resolvidos pelo pai; a remoção de renovação valida o par renovação/contrato e usa o contrato correto. O cascade mantém prova privada temporária somente para limpar anexos após remover o pai. `contract_relations.php` executa leitura positiva, edição de comentário e exclusão nativa do contrato com metadados de anexo externo sintético; o provedor de merge fields é substituído por um mapa vazio apenas no teste de cascade. Preserva o contrato privado e arquivos de outros tipos. Upload/download/preview de contrato também verificam o pai antes de efeitos. Não foram feitos envio, assinatura real, renovação real ou acesso a arquivos de cliente; gráficos personalizados e outros leitores diretos ainda exigem revisão.

Os leitores principais nativos de propostas, orçamentos e notas de crédito filtram o cliente antes da hidratação de itens, reembolsos, anexos e contatos. Os agregadores genéricos reconhecem essas tabelas e aliases. `sales_document_readers.php` confirma listagens legadas positivas (inclusive proposta de lead), bloqueio por ID privado e agrupamento de OR; a seleção de faturas creditáveis recusa primeiro a nota privada. Ainda faltam os consumidores secundários de anexos/comentários, reembolsos, atividade, aplicação de crédito e gráficos com SQL próprio. Não é liberação das operações financeiras da empresa secundária.

Notas de crédito também recusam criação/edição/remoção de reembolso, saldo, aplicação e remoção de crédito e alteração/exclusão de nota privada antes dos efeitos. Reembolso exige pai persistido e o par informado precisa coincidir. A listagem de créditos de uma fatura exige também a nota de origem legada. `sales_document_readers.php` passou 25 verificações com leitura positiva de reembolso legado e negações sem writes; os testes existentes de faturas, créditos aplicados e manutenção continuaram passando. Isto não valida estorno remoto nem implementa novas decisões de baixa financeira.

Anexos de orçamento/proposta/nota de crédito verificam o tipo e o pai persistido. O upload genérico de vendas valida o documento antes do hook. Exclusões desses documentos carregam uma prova privada temporária de cleanup, sem permitir usar um ID de pai diferente. `sales_document_files.php` exercita os modelos reais para listagem legada/negação privada e o cascade nativo de nota de crédito com anexo externo sintético, incluindo remoção efetiva de metadados e preservação do irmão privado. O cascade completo de orçamento/proposta e vinculações financeiras históricas ainda exigem testes adicionais; não houve operação bancária.

A ordenação compartilhada de kanban valida o lote inteiro e usa parâmetros SQL. Para tabelas com adapter de ownership, tanto as posições informadas quanto o deslocamento dos demais cards recebem o filtro. `kanban_scope.php` executou reordenação positiva de orçamento/tarefa legados, manteve cards privados fora do deslocamento e recusou lote misto antes de alterar o status do orçamento. IDs repetidos, vazios ou malformados não geram writes. Não se afirma atomicidade/concorrência das duas atualizações históricas; outros tipos de módulo mantêm sua fronteira própria.

Comentários de propostas e ações secundárias de status/assinatura/atividade/envio/numeração de orçamentos agora verificam o pai antes dos efeitos. Os testes exercitam edição positiva de comentário legado e recusas privadas. Os callbacks de envio real e a navegação HTTP ainda não foram executados.

A troca de identidade do módulo legado `multiple_companies` deixou de confiar em `$_SESSION['all_clients']`. Origem e destino são consultados novamente, ativos, com par contato/cliente coerente e restritos a clientes legados; contato privado não enumera identidades legadas pelo mesmo e-mail. O menu usa formulário POST nativo (CSRF do framework) e escapa o nome; GET não muda autenticação. O controller exige alvo único, invalida caches e regenera a sessão quando suportado. `legacy_portal_switch.php` executou a troca positiva legada no controller real com sessão sintética, rejeição de cache forjado, duplicidade, revogação e contato movido. Não foi executada navegação HTTP/CSRF real. Essa troca antiga de contatos não equivale ao seletor nativo de empresas do staff; login/recovery e transporte por empresa ainda exigem validação integrada. A identidade privada autenticada própria de `PortalClientIdentity` não foi bloqueada indiscriminadamente.

As buscas próprias da API de propostas/orçamentos/créditos usam agora os mesmos filtros antes do resultado e query builder para texto/campos customizados. A busca textual de faturas também evita interpolação, mas preserva a regra financeira existente: fatura de origem legada não muda de empresa quando a identidade do cliente é adotada; sua projeção mínima permanece acessível ao escopo histórico. `api_sales_search.php` exercitou os quatro métodos reais, inclusive esse caso positivo de histórico e exclusão da fatura com emitente nomeado. Não foi aplicada uma restrição global por cliente que ocultasse silenciosamente a história financeira.
