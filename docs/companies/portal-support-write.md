# Suporte escrito pelo contato — incremento430

O portal da empresa permite abrir e responder chamados sob a permissão nativa
`support`, ID5. A fonte é `Clients::open_ticket/ticket` e a lista nativa de permissões
em `clients_helper::get_contact_permissions`; a identidade usa contactid/userid da
sessão persistida. A permissão financeira1 não concede esse acesso. Não há autoridade
de funcionário no contato e não se herda sessão administrativa ou token API.

A abertura aceita somente assunto, mensagem e prioridade baixa/normal/alta. Empresa,
cliente e contato são revalidados sob lock, incluindo registro ativo e ownership
privado. Cadastros compartilhados, principal e legadoNULL não adotam recursos
alheios. A empresa principal funciona com cliente privado explicitamente vinculado.

O novo chamado fica aberto, sem responsável atribuído (`assignee_id=0`), como o
valor nativo de tickets `assigned DEFAULT0`. A equipe assume ou atribui um funcionário
com acesso à própria empresa pela GUI existente. O contato não pode escolher staff,
empresa, cliente, estado ou autor. O portal mostra o registro que o próprio cliente
acabou de criar, sem publicar qualquer registro anterior ou arquivo da equipe.

Responder exige pai próprio, publicado e aberto, mais revisão atual. A mensagem do
contato é registrada no histórico canônico e marcada para exibição ao próprio
cadastro; a origem é persistida separadamente e usa `actor_id=0`, nunca um staff
fabricado. Nome do autor é snapshot de até383 caracteres (dois nomes nativos191).
A GUI administrativa e o portal identificam claramente a autoria do contato.
Mensagens novas da equipe continuam internas até publicação individual pelo staff.

A transação inclui pai, resposta, origem, publicação, recibo durável e auditoria.
Uma mesma chave retorna o resultado anterior sem duplicar mensagem/ticket. Trocar
entrada conflita; revisão antiga com chave nova conflita. Retirar publicação ou
grant, desativar contato/cliente/empresa, alterar senha ou mover ownership impede
um formulário já aberto. Replay nunca republica pai ou resposta ocultados. Limites
persistidos:10 aberturas por contato/hora,100 chamados abertos por cadastro e500
respostas por chamado. Textos são UTF8 limitados a191/12000 bytes e renderizados
como texto. CSRF nativo e portal_csrf continuam obrigatórios; não há nova exceção.

O schema é aditivo: `company_portal_support_origins`,
`company_portal_support_replies`, `company_portal_support_requests`, mais índices
únicos compostos de origem em tickets e contatos. FKs asseguram issuer/pai/cliente
e contato; MySQL valida engine/colunas/PK/keys/FKs antes das operações. Dados antigos
não recebem autoria de contato nem novas publicações na migration.

Testes reais em PDO cobrem A/B/principal/NULL, ID cruzado, replay/stale, grant5 vs1,
origem, staff privado, rollback tardio e transação externa recusada. A concorrência
possui13 cenários com dois processos observados esperando pelo lock, incluindo
quota, senha/owner/publicação revogados em espera. Há testes dos controllers e
HTML reais, além de Chromium HTTP com abertura, resposta, atribuição, publicação
staff, encerramento, CSRF e revogação. Evidências/ambiente constam no arquivo de
integração do incremento. O fixture native430 usa as migrations reais integradas;
tracking do executor é validado separadamente, não inferido do body de up().

Ainda não há anexação pelo contato, reabertura pelo contato, e-mail de entrada,
SLA/automação ou notificações externas neste fluxo. Contratos não ganham assinatura,
aceite nem comentário público neste incremento. Nada disso é homologação bancária.
