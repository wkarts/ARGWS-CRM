# Contratos e suporte por empresa

As rotas `admin/company_contracts` e `admin/company_tickets` usam o renderer selado,
serviços próprios e tabelas aditivas. Os controllers e modelos genéricos legados
continuam fechados para empresas secundárias. Nenhum registro NULL é adotado.

## Contratos

A interface cria rascunho com cliente autorizado, nome, texto simples e vigência.
O cliente fica fixo após criar. Texto/datas são editáveis enquanto rascunho. Ativar
atribui número sequencial da própria empresa e preserva snapshot de texto, vigência,
identidade da empresa e identidade do cliente. Os números são consumidos somente
com commit confirmado; cancelar conserva número e snapshot. Copiar cria novo
rascunho independente, sem copiar número, ativação ou aceite.

Ativação é registro interno: não representa assinatura eletrônica, consentimento
do cliente ou assinatura com certificado. O PDF usa somente o snapshot autorizado
em TCPDF, como texto, sem HTML dinâmico ou recursos externos. Rascunhos não têm PDF.
Após renderizar, empresa, ator, estado e revisão são revalidados e a auditoria é
confirmada antes de entregar bytes. Contrato cancelado preserva PDF identificado
como cancelado. Mudanças posteriores no cliente/empresa não reescrevem snapshots.

## Suporte

A interface cria chamado vinculado a cliente autorizado e responsável com grant
`tickets/view` nesta empresa. Assunto/mensagem inicial são imutáveis; mensagens
posteriores são append-only (até500 por chamado), com ator e data. Estado/prioridade/
responsável podem ser atualizados com revisão. Estados disponíveis: aberto,
resolvido e encerrado. Nova mensagem requer aberto; reabrir é ação explícita,
sem apagar a conversa anterior. Mensagens são internas à equipe nesta vertical.

Buscar assunto ou mensagem inicial aplica `(assunto OR mensagem)` sob o predicado
obrigatório da empresa. Listagens usam paginação por chave, sem contagem global.
Cliente compartilhado compartilha apenas identidade; nenhum contrato/chamado/
conversa da outra empresa fica visível.

## Autorização e consistência

`contracts/view` ou `tickets/view` e `create`/`edit` correspondentes são explícitos
por empresa. Criação e alterações que consultam cliente exigem `customers/view` e
cliente ativo próprio ou explicitamente compartilhado. Responsável de chamado
precisa de grant no destino. O administrador mestre segue sua regra já existente.
Papéis globais comuns não concedem acesso a empresas futuras.

Mutação obtém lock global, revalida identidade/ACL, verifica revisão, confirma a
escrita e grava auditoria+recibo na mesma transação. A repetição do mesmo pedido
retorna o recurso persistido; mesma chave com outro conteúdo/ator/empresa conflita.
Falha no contador, snapshot, auditoria ou recibo impede confirmação parcial.

Uma migration nativa nova chama `CompanyContractsSchema::ensure($db,$prefix)`.
Ela cria `company_contracts`, `company_contract_counters`,
`company_contract_requests`, `company_tickets`, `company_ticket_replies` e
`company_ticket_requests`, validando tipos/defaults/nullability/collations/engine,
índices completos e FKs, incluindo ownership composto dos filhos. A criação não
corrige silenciosamente schema incompatível. Rollback operacional preserva essas
tabelas e seu histórico; não apagar dados para voltar código.

## Testes e limites

- `tests/companies/contracts_operations.php`: PDO real SQLite/MySQL; A/B/principal,
  privado/compartilhado, número/snapshot/PDF, cópia/cancelamento, mensagens/estados,
  busca OR, ACL/revogação/IDs cruzados/NULL e rollback de contador/auditoria/recibo.
- `tests/companies/contracts_controller.php`: controllers e templates PHP reais,
  POST/GET, CSRF, contexto, estado/revisão, PDF real/auditoria antes de bytes e XSS;
  framework HTTP/session substituído para teste de integração local.
- `tests/companies/contracts_concurrency.php`: MySQL/MariaDB descartável com dois
  workers observados no lock; recibo igual converge, revisão velha perde e revogação
  durante espera impede ativação ou resposta. SQLite não prova essas propriedades.
- `tests/companies/contracts_browser.cjs`: HTTP/MySQL/Chromium reais na fixture de
  instalação/upgrade. A/B percorrem contrato→ativação→PDF→cópia/edição/cancelamento e
  suporte→mensagem→resolver→reabrir→responder→encerrar, stale e IDs cruzados.

Ainda faltam: assinatura/aceite, modelos e renovação automática de contratos,
notificações/SMTP desses domínios, escrita pelo cliente no portal, abertura por e-mail, automações/SLA, importação/exportação. A API operacional própria já fornece CRUD e ações explícitas destes domínios; ainda não expõe os novos arquivos na API. O portal operacional está descrito em `operational-portal.md`.
São funcionalidades técnicas pendentes, não homologação externa. Nenhum envio
externo, credencial real, assinatura ou acesso de produção é usado pelos testes.


## Anexos privados de contratos e chamados (incremento seguinte)

O critério de relações indiretas/anexos do documento de continuidade é atendido
nestes dois consumidores pela própria página de contratos/chamados. O formulário
multipart exige CSRF nativo, token dedicado e contexto atual da empresa. Não há
rota pública de arquivo, caminho de filesystem fornecido pelo usuário nem leitor
legado. Documentos com `issuer_id=NULL` não são adotados.

`CompanyOperationFiles` aceita somente os domínios fechados `contract` e `ticket`.
Cada domínio tem tabela própria e FK composta de empresa/pai. O serviço usa os
recibos, a revisão do pai e a auditoria da vertical existente, sob lock global e
revalidação de identidade/ACL. Upload e ocultação exigem `view`+`edit` do recurso;
listar/baixar exige `view`. O compartilhamento de cliente não compartilha anexos.

PDF até 2 MiB é entregue como download privado com `sandbox` e `nosniff`, sem
alegar sanitização ou análise antimalware. PNG/JPEG até 512 KiB são validados e
normalizados para PNG pelo componente existente. Preview é exclusivo do PNG
reescrito. Quotas incluem arquivos ocultos: até 20 uploads e 20 MiB por pai.
Download valida hash/tamanho/tipo e confirma auditoria antes dos bytes; HEAD não
retorna conteúdo. Falha de auditoria ou corrupção impede a entrega.

Contratos em rascunho/ativos e chamados abertos aceitam anexos. Cancelar contrato
ou encerrar chamado preserva a consulta dos arquivos anteriores. Ocultar conserva
o BLOB histórico e o recibo; replay do upload antigo não restaura a visibilidade.
Anexos de contrato ativo não alteram número, texto ou identidades do snapshot.

A migration nova chama `CompanyOperationFilesSchema::ensure` após o schema base de
contratos/suporte. `company_contract_files` e `company_ticket_files` são aditivas;
o validador recusa tipos, collation, índices, engine ou FK divergentes. Não editar
migrations 425/426. O rollback conserva tabelas/bytes/auditoria; não fazer purga.

Testes preparados:

- `operation_files.php`: serviços reais A/B/principal/NULL; download positivo,
  pai/filho cruzado, revisão/retry, corrupção, rollback de auditoria/recibo,
  ocultação sem ressurreição, quotas reais de contagem/bytes e estado elegível.
- `operation_files_controller.php`: controllers/templates reais, escaping,
  formulários, CSRF/contexto, bytes/HEAD/preview, falha da auditoria e ocultação;
  o teste rejeita um caminho local que não seja upload HTTP genuíno.
- `operation_files_concurrency.php`: dois processos MySQL/MariaDB aguardam lock
  real; idempotência, revisão concorrente e revogação em espera nos dois domínios.
- `operation_files_browser.cjs`: multipart genuíno via Chromium/HTTP/MySQL na
  fixture descartável; A/B, PDF/PNG, snapshot, download/preview/hide e stale.

Execução local inicial observada: SQLite serviço 129 e controller 103 checks
cumulativos, além da regressão dos controllers existente. MySQL/MariaDB e browser
estão preparados, ainda não executados neste patch. Não há chamada externa nem
homologação bancária relacionada a anexos internos.

O incremento de portal posterior mantém todos os registros e anexos privados por
padrão e oferece publicação explícita por registro/arquivo/resposta, com permissões
nativas3/5. Leia `operational-portal.md` para código, testes e limites atuais.
