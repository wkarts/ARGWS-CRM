# Leads privados — contrato do incremento 454

## Estado verificável deste recorte

Esta implementação foi **reconstruída** sobre o HEAD 452 `543ff96fe2a49ceef48d8595a58e8400befc6266`, depois da perda do executor. Os testes antigos do incremento não publicado não são evidência desta árvore. O recorte preserva o modelo de Leads nativo e seus registros; não amplia a permissão para o controller legado na empresa secundária.

As quatro suítes próprias de domínio, worker/inbox, integridade e concorrência passaram em MariaDB 10.11.14/PHP 8.3.6 e MySQL 8.0.46/PHP 8.1.2: **54 + 20 + 30 + 18 = 122 verificações por runtime**, com processos PHP independentes, waiters SQL observados e memória de 128 MiB. A suíte finita de API passou com **29 verificações por runtime**, primeiro numa cópia candidata e depois na **árvore principal com os globais 454 integrados**, sobre o commit 453 `83571303d46cdf5398cd9bd854085e628942ac60`. O teste do trait HTTP cobre mais **33 verificações por runtime**, incluindo `lead_assignees` somente GET/HEAD com 405/Allow nos demais verbos, corrigido após RED de 28/33. Essas provas de serviço e trait não substituem o roteamento HTTP real.

Na mesma rodada integrada, `task_recurring_api.php` preservou 76 verificações próprias de recorrência e 36 da base operacional em cada runtime. Os resultados estão nos rótulos `leads454-integrated-api-maria` e `leads454-integrated-api-mysql81`; a preservação nativa de contato/portal já havia passado nos dois motores.

O fluxo real de `tests/companies/leads_browser.cjs` **passou no HTTP nativo após o upgrade 453→454**, em Chromium 156.0.8078.47, com empresas A=2, B=3 e principal=1. A execução de 285,435 segundos cobriu dez grupos de cenários positivos e negativos, sem erro JavaScript, incluindo cadastro/edição/Kanban, conversão, arquivos, CSV, caixa interna, permissões restritas e API. O snapshot final conservou os Leads legados e os registros financeiros. As telas desktop e móvel foram inspecionadas e estão no pacote de evidências.

Um segundo teste, `tests/companies/leads_bearer_http.cjs`, passou com **58 verificações HTTP sem sessão autenticada**: cada requisição recebe um contexto novo, com jar vazio e cabeçalho Cookie vazio. Verificou leitura, escrita, arquivo privado, empresa estrangeira, método/Allow, revogação e preservação do legado. As 29 linhas da tabela nativa de sessões mantiveram o mesmo SHA256 antes e depois: `5db5bf66bfcd8e23013c3c943caa6c26f19288d6aa4f03653e346a0108481a43`. O cookie de CSRF que o CodeIgniter emite nativamente em GET/HEAD não foi reenviado e não é sessão nem autoridade bearer. A primeira tentativa havia confundido qualquer Set-Cookie com sessão; sua falha anterior à primeira mutação e a correção da hipótese do teste foram preservadas. Nenhum comportamento do produto foi removido para fazer o teste passar.

O integrador concluiu o upgrade nativo e a comparação de pós-condições com **926 verificações, 298 tabelas históricas preservadas e nove tabelas novas**, mantendo master 17/principal 1; as alterações nativas de login, rastreamento e marcador estão qualificadas na prova. A instalação nova 454 também concluiu o instalador e o browser nativo original. A suíte específica de Leads acima rodou no fixture atualizado, não foi repetida no fixture de instalação nova. O ambiente HTTP é PHP-S com o adaptador local de comandos do harness; esta prova não afirma build ou execução da imagem FrankenPHP/Docker. Os resultados, os hashes dos fontes e as limitações estão em `docs/companies/evidence/leads-454.json`; o SHA publicado deve ser conferido pelo integrador na PR.

## Operações positivas e limites

| Operação | Contrato e autorização | Evidência própria |
|---|---|---|
| Cadastro, lista, consulta e edição | `issuer_id` persistido; `leads.view` mais create/edit; referências de status, fonte e responsável revalidadas na empresa; revisão inteira e recibo de idempotência | `leads.php`, `leads_concurrency.php` |
| Status e fontes | Até 50 de cada por empresa; revisão; nome único por empresa; status open/won/lost com tipo imutável; ordem do Kanban; inativação recusa referência ativa | `leads.php` |
| Kanban | Colunas dos status da empresa; mover com revisão e recibo; até 50 resultados por página, filtro e paginação explícitos | SQL e interação real A/B/principal em `leads_browser.cjs` |
| Arquivar/restaurar | `leads.delete`; preserva histórico; cancela avisos pendentes; conversão torna identidade de origem imutável | `leads.php` |
| Notas | Até 200 por lead, 4.000 bytes UTF-8 cada; texto literal; empresa, pai e ator persistidos | `leads.php`, `leads_integrity.php` |
| Conversão | Status ativo do tipo ganho; leads.view/edit + customers.view/create; compositor canônico `CompanyClients::createInOwnedTransaction`; novo cliente privado e novo contato inativo na mesma transação | `leads.php`, `leads_integrity.php` |
| Anexos | PDF até 2 MiB ou PNG/JPEG normalizado segundo `CompanyAssets`; MIME real, assinatura/hash/tamanho; blob SQL privado; máximo histórico de 20 arquivos/20 MiB, inclusive ocultos; download attachment/nosniff; pai e empresa obrigatórios | `leads.php`, `leads_integrity.php` |
| CSV | UTF-8, cabeçalho exato, até 256 KiB/100 linhas; prévia de 15 minutos por empresa/ator/origem API; hash e revisões de configuração; confirmação atômica; exportação até 1.000 resultados com proteção de fórmula | `leads.php`, `leads_integrity.php`, `leads_api.php` |
| Avisos internos e lembretes | Atribuição opt-in; responsável autorizado; data/hora com fuso IANA explícito, rejeitando lacuna/sobreposição DST; origem de ator e token persistida; entrega e leitura revalidam autoridade | `leads_worker.php`, `leads_api.php` |
| Worker | Descoberta com cursor persistido e rotação entre passes limitados; cada entrega possui transação SQL própria; falha de integridade fica pendente; revogação suprime; concorrência entrega uma vez | `leads_worker.php`, `leads_concurrency.php` |
| API | Oito endpoints finitos e nove scopes; bearer determina empresa/ator; origem é revalidada na transação e no worker; sem CRUD genérico legado | `leads_api.php`, `leads_http_methods.php`, browser real e 58 verificações adicionais de `leads_bearer_http.cjs` sem sessão |

O contato convertido conserva nome completo em `firstname`, `lastname` vazio, e-mail e telefone do lead. Começa com `active=0`, `is_primary=0`, senha/reset/verificação nulos, sete preferências de e-mail desligadas e nenhum token de portal. Não há envio. Dados de descrição, status/fonte, responsável, notas e arquivos continuam no lead associado aos IDs do cliente/contato. Duplicidade fiscal usa o escritor canônico de clientes; duplicidade de e-mail é verificada na empresa do contato. Qualquer conflito impede toda a conversão, incluindo cliente, contato, ownership, recibos e auditorias.

Cada mutação grava recibo globalmente único e auditável, com ação, empresa, ator, hash de intenção e identidade resultante. As revisões impedem sobrescrita de formulário antigo. Leituras finais comparam os campos esperados depois do último recibo e da auditoria API, além de revalidar permissões e origem. Ignorar uma gravação, alterar um campo em trigger tardio, criar compartilhamento implícito ou ativar o contato provoca rollback. SQL externo sem o compositor não é admitido como transação própria.

## Dados e instalação

`CompanyLeadsSchema` adiciona nove tabelas, sem converter, renumerar ou copiar o legado:

- `company_lead_stages`, `company_lead_sources`, `company_leads`;
- `company_lead_notes`, `company_lead_files`, `company_lead_imports`, `company_lead_requests`;
- `company_lead_notices` e `company_lead_notice_cursor`.

Os vínculos de lead/status/fonte/nota/arquivo/aviso usam FKs compostas com empresa. O cursor possui somente progresso técnico, uma linha id=1, inicializada em zero; não contém conteúdo nem autorização de negócio. Sua atualização não adota a identidade do job. O job tem sua própria origem persistida e a revalida antes de entregar.

`CompanyLeadsSchema::ensure($db,$prefix)` deve ser chamado na migration 454 e no bootstrap de instalação nativa, com `CompanyApiSchema` já instalado e tabelas nativas de clientes/contatos presentes. A verificação recusa engine, coluna, collation, índice, FK ou regra de cascata divergente. Não execute DDL por request nem tente corrigir schema de modo silencioso durante uma operação.

## Composição finita integrada e provada

Os pontos abaixo foram compostos pelo integrador na árvore 454 e exercitados nos gates descritos. São a lista de revisão da integração; não autorizam ampliar rotas ou declarar concluídos os consumidores legados ainda pendentes.

1. Migration/versões oficiais 454: criar/verificar as nove tabelas; preservar identidade do master e da principal; atualizar instalação nova, registro de consumidores e inventários sem marcar o contrato integral como encerrado.
2. `CompanyRequestScope`: permitir exclusivamente `company_leads/index`, `company_leads/file` e `company_leads/export` depois dos gates. A interface usa somente esses métodos.
3. Menu/layout: link `company_leads` sob `leads.view`; catálogo do recurso registra o fluxo próprio e mantém o legado secundário bloqueado.
4. `CompanyApiTokens::SCOPES`: adicionar os nove mapeamentos exatos de `CompanyLeadsApi::SCOPES`, sem ampliar scopes existentes. Incluir seus nove rótulos na tela de credenciais.
5. `Company_operations_api`: `require_once APPPATH.'services/CompanyLeadsHttpApi.php';` e `use CompanyLeadsHttpApi;`. O trait usa o `ready` privado existente; nenhuma sessão ou módulo fornece autoridade.
6. `CompanyOperationsApiScope`: `leads`, `lead_stages`, `lead_sources`, `lead_inbox`: dois segmentos ou mais um ID decimal 1..2147483647; `lead_files`: pai decimal obrigatório e chave opcional de 64 hex; `lead_imports`: coleção ou batch de 32 hex; `lead_assignees` e `lead_exports`: somente coleção. Não adicionar curingas.
7. Routes e exclusão CSRF da API: repetir esses formatos exatos. Escritas continuam exigindo bearer e Idempotency-Key. UI continua exigindo CSRF nativo, CSRF próprio e contexto de empresa selado.
8. Worker CLI nativo: chamar `(new CompanyLeads($db,$prefix))->processAllNotices(100)` num ramo explícito, sem endpoint público ou impersonação de ator por sessão. Registrar os contadores e considerar `failed>0` como trabalho pendente; não é envio externo.
9. Gates: `leads.php`, `leads_worker.php`, `leads_integrity.php`, `leads_concurrency.php`, `leads_api.php`, `leads_http_methods.php`, `leads_browser.cjs`, `leads_bearer_http.cjs`; preservação de contatos/portal e recorrência API, além de upgrade/fresh nativos. Resultados limitados às superfícies e cenários documentados.

### Endpoints e verbos

| Endpoint relativo a `company_operations_api/` | GET/HEAD | POST | Scope |
|---|---|---|---|
| `leads[/id]` | Lista/registro | create, update, move, note | leads:read / leads:write |
| `leads/id` | Registro | archive, restore / convert | leads:archive / leads:convert |
| `lead_stages[/id]`, `lead_sources[/id]` | Coleção | create, update | leads:read / leads:configure |
| `lead_assignees` | Responsáveis autorizados | Não disponível | leads:read |
| `lead_files/id[/key64]` | Metadados/download | upload, hide | leads:files:read / leads:files:write |
| `lead_imports[/batch32]` | Prévia própria por batch | preview, commit | leads:import |
| `lead_inbox[/id]` | Coleção do próprio ator | acknowledge | leads:notices |
| `lead_exports` | CSV dos contatos da empresa | Não disponível | leads:read |

Uploads API usam `content_base64` canônico com limites antes e depois de decodificar. Leituras não aceitam substituição de empresa/ator. A origem de um lote API inclui o token que preparou a prévia; outro token, inclusive substituto, exige nova prévia. Isso impede assumir silenciosamente a intenção de uma credencial revogada.

## Pendências que este recorte não oculta

O aviso implementado é a **caixa interna própria**. SMTP, IMAP/captura automática, web-to-lead público, disparos de campanhas/MA, Connect/WhatsApp, Pusher e notificações legadas não foram conectados a esses leads. Não existe envio pretendido a provedor que possa ser chamado de homologado. Implementar esses canais requer composição interna adicional por empresa; para os transportes também serão necessárias configuração e credenciais próprias, e prova externa autorizada. A falta da adaptação interna não deve ser rotulada apenas como falta de credenciais.

Tags/campos personalizados e automações que consultam `tblleads` continuam no legado e não passam a operar nos registros próprios por causa desta entrega. O CSV cobre os seis campos declarados, não restauração total de histórico, anexos ou metadados. A UI de Kanban é paginada, não afirma agregar todos os leads numa página. Nenhuma dessas limitações é resolvida por um 403 ou pelo verde das suítes deste recorte.

As versões e globals são integrados pelo responsável pela PR. Não houve merge, deploy, force-push ou chamada bancária neste trabalho.
