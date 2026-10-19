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
notificações/SMTP desses domínios, arquivos de contrato/suporte, portal de contratos
ou chamados, abertura por e-mail, automações/SLA, importação/exportação e API pública.
São funcionalidades técnicas pendentes, não homologação externa. Nenhum envio
externo, credencial real, assinatura ou acesso de produção é usado pelos testes.
