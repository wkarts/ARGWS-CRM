# Portal operacional por empresa

Este incremento atende o §5 do contexto de continuidade: consumidores positivos
operacionais, relações indiretas/arquivos e novos documentos/operações do portal
com permissão nativa, ownership de cliente/empresa e isolamento dos subrecursos.
Ele usa a sessão privada existente e a rota nativa `company_portal/index/<empresa>`.
Não cria token público, não amplia escopos da API e não usa modelos/hooks legados.

## Permissões comprovadas no código nativo

`application/helpers/clients_helper.php::get_contact_permissions()` declara:

| Domínio | ID nativo | short_name |
|---|---:|---|
| Contratos | 3 | contracts |
| Chamados | 5 | support |
| Projetos | 6 | projects |

`Clients.php::contracts()`, `projects()` e `project()` verificam os mesmos nomes e
filtram o cliente autenticado. A implementação nova conserva esses IDs, sem usar
`permission_id=1` de faturas e sem chamar o helper de hooks global na superfície
selada. `CompanyPortal::invoiceIdentity` é a identidade genérica já existente;
ela não verifica permissão financeira. O leitor operacional acrescenta o grant
3/5/6 correspondente e exige cliente privado com owner explícito desta empresa.

## Operação gráfica

Na página administrativa do contrato, chamado ou projeto:

1. Autorize o contato privado do próprio cliente com a permissão nativa específica.
   Exige `customers/view`/`customers/edit` e `view` do domínio. Contatos recebidos por
   compartilhamento de identidade não ficam disponíveis.
2. Publique explicitamente o registro. Exige `view`+`edit` do domínio e revisão do
   próprio pai. Grant do contato sozinho não publica conteúdo anterior.
3. Publique cada anexo separadamente. Publicação do registro não publica arquivos.
   Ocultar o arquivo pelo fluxo privado existente também impede bytes no portal.
4. Em chamados, publique cada resposta interna desejada separadamente. Mensagens
   da equipe permanecem internas até a ação explícita; nenhuma resposta histórica
   é divulgada automaticamente.
5. O contato entra no portal da sua empresa e vê listas paginadas e detalhes dos
   registros publicados do seu cliente. Contratos ativos/cancelados com snapshot
   numerado oferecem PDF; rascunhos nunca aparecem. Projetos oferecem descrição,
   datas e estado; tarefas/timers/marcos/seguidores ficam fora desta publicação.

Revogar grant, desativar contato/cliente/empresa, alterar senha/e-mail/owner,
retirar publicação do pai/arquivo/resposta ou ocultar arquivo vale na próxima
operação. Sessão e cadastro compartilhado não conferem acesso a outra empresa.
Aceite/assinatura eletrônica, abertura/resposta pelo cliente, notificações externas,
portal de tarefas e publicação de marcos são pendências técnicas separadas.

## Persistência e controle antes dos bytes

`CompanyPortalOperationsSchema` acrescenta publicação por pai e por arquivo em
cada domínio, publicação individual de respostas e recibos duráveis. A migration
nova adiciona índices únicos compostos às tabelas fonte de arquivos/respostas,
sem reescrever schemas históricos ou adotar dados legados. FKs compostas ligam
publicação ao arquivo/resposta e ao pai da empresa; novos registros começam sem
publicação. Upgrade/replay não insere concessões ou visibilidade automaticamente.

`CompanyOperationPublication` grava revisão compartilhada do pai, visibilidade,
auditoria e recibo na mesma transação. Chaves idempotentes com conteúdo divergente
conflitam; retry de publicação antiga não ressuscita estado retirado posteriormente.
`CompanyContactOperationAccess` altera somente o ID nativo correspondente, com
revisão do contato/grant e auditoria, sob lock e revalidação de ACL/owner privado.

`CompanyPortalOperations` valida sessão, contato ativo, empresa, cliente privado,
permissão específica, estado e publicação antes de hidratar detalhes. Filhos são
consultados por chave persistida mais pai/empresa; parâmetros de tela não conferem
origem. Listas usam paginação por chave e não agregam dados globais.

Download entrega apenas arquivo ativo e explicitamente publicado, confere
hash/tamanho/tipo e confirma auditoria antes dos bytes. PDF de contrato renderiza
o snapshot autorizado em TCPDF e confirma novamente grant, publicação, revisão e
hash do snapshot depois de renderizar. Falha de auditoria ou revogação durante
espera impede bytes. Anexos usam download com sandbox/nosniff; nenhuma rede externa
é executada. Disponibilização no portal não representa assinatura ou aceite.

## Evidência preparada e observada

- `portal_operations.php`: serviços reais com PDO, A/B/principal, grants3/5/6 versus1,
  publicação de registro/arquivo/resposta, PDF real, revogação, rascunho, compartilhado,
  NULL legado, recibo antigo e rollback tardio de auditoria/recibo.
- `portal_operations_controller.php`: controllers administrativos e público reais,
  templates, CSRF/contexto, editor de grants, publicação, detalhe, arquivo e PDF,
  interno não publicado, IDs cruzados e falha antes dos bytes.
- `portal_operations_concurrency.php`: 18 cenários com dois processos observados
  esperando o lock MySQL/MariaDB: retry/stale/staff revogado e leitura após retirar
  grant nativo/publicação ou desativar contato, nos três domínios.
- `portal_operations_browser.cjs`: Chromium/HTTP/MySQL da fixture descartável;
  contato existente, editor nativo/publicação, multipart, listas/detalhes, anexos/PDF,
  privacidade de mensagem interna, revogação e IDs cruzados em A/B.

Observado inicialmente em SQLite: serviço215 e controllers104 cumulativos;
regressões contracts_controller79 e projects_controller56. MariaDB10.11.14: serviços218, controllers106 e concorrência167 (18 cenários)
passaram em cópia isolada, assim como o browserA/B real. Instalação/tracking e
HTTP do composto428 estão em [validação428](pr45-validation-428.md). Não há homologação
bancária ou entrega externa relacionada a esta funcionalidade.

Integração: migration nativa 428 preparada deve chamar
`CompanyPortalOperationsSchema::ensure` após todos os schemas de projetos e anexos
427. Preservar tabela/bytes/auditoria em rollback; nunca apagar dados por downgrade.

## Escrita de suporte430

Abertura e resposta pelo contato com permissão nativa5, origem persistida e sem
personificar funcionários estão descritas em `portal-support-write.md`. Mensagens
de equipe continuam internas até publicação individual. Sem novos scopes API.
