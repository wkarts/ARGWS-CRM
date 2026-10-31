# Exclusão de clientes: limite atual

A rota de exclusão de clientes da empresa secundária é um escritor nativo
limitado: aceita somente `POST`, CSRF nativo, empresa proprietária, permissão
explícita e a revisão atual do cliente. O controlador nunca alcança a cascata
legada. Dependências financeiras, contatos, históricos, vínculos polimórficos,
importações confirmadas e relações desconhecidas fazem a operação falhar de
forma conservadora; o método `CompanyClients::deleteUnreferenced` executa a
remoção somente dentro dessa guarda e da transação da empresa.

`CompanyCustomerDeletionReferences` verifica, nas tabelas existentes, referências
diretas de contatos, chamados, contratos, documentos financeiros, despesas,
projetos, assinaturas, leads convertidos, cofre, privacidade, grupos, responsáveis,
compartilhamentos, documentos e registros nativos de portal; também verifica
vínculos nas 16 tabelas polimórficas com `rel_id` e `rel_type` (ou `relid` e
`fieldto`) declaradas no schema de instalação e os IDs dos resultados de
importações confirmadas. A lista
executável de tabela e coluna está na classe e precisa ser revisada a cada
evolução dos schemas. Importações inválidas bloqueiam a operação. Recibos de
criação são preservados para impedir reaplicação posterior.

As tabelas ou vínculos de módulos de terceiros não são conhecidos pela aplicação.
Antes de liberar a rota, inventariar os módulos instalados e seus campos, relações
polimórficas, JSON, arquivos externos, gatilhos e chaves estrangeiras; exigir que
seus escritores usem bloqueios compatíveis ou permaneçam desabilitados durante a
exclusão. Verificar também todas as referências first-party e as escritas legadas,
inclusive criação concorrente de histórico. O resultado sem referências dessa
classe isoladamente não constitui prova de segurança.

Os testes `native_customer_delete_references.php`,
`native_customer_delete_writer.php` e `native_customer_delete_guard.php` cobrem a
enumeração presente, rollback, isolamento, histórico, revisão/ownership/CSRF e
a ausência de fallback para a cascata legada; não validam integrações
desconhecidas ou concorrência real de todos os módulos.

## Evidência de concorrência

`native_customer_delete_race_mysql.php` reproduz em MariaDB, com duas conexões e
tabelas descartáveis InnoDB, uma escrita legada que consulta o cliente antes da
transação de exclusão e insere uma fatura depois de liberado o bloqueio de
intervalo. A inserção consegue produzir uma referência órfã. O teste usa uma
fatura sintética com índice em `clientid` e sem FK, conforme o schema de instalação;
é um modelo adverso, não uma prova de execução de um controlador específico.
Exige `ISSUERS_DELETE_RACE_ACK=disposable` e DSN MySQL explicitamente fornecido.
O resultado demonstra que os bloqueios atuais não bastam para liberar a rota.

## Módulos presentes na cópia

A revisão dos instaladores encontrou referências diretas em produtos
(`order_master.clientid`), contabilidade (`acc_account_history.customer`),
gerenciamento de serviços (`sm_orders`, `sm_contracts`, `sm_service_details`,
`sm_service_invoices`), mapeamento Asaas (`asaas_customers_map`) e automação de
marketing (`ma_*` com `client_id`). A enumeração interna agora bloqueia essas
referências quando as tabelas existem; campos ausentes em tabelas reconhecidas
fazem a operação falhar de forma conservadora. A versão anterior do mapeamento
Asaas pode usar `perfex_client_id`; até haver migração e validação explícitas,
essa variante também resulta em bloqueio. A enumeração também abrange os
pagadores por empresa do Asaas. `Order_model::add_order` grava `order_master`
diretamente e seu instalador declara índice de cliente sem FK; não está
comprovado que ele compartilhe a trava do escritor de exclusão.

Uma varredura textual dos arquivos PHP encontrou menções a clientes em 32 dos
38 diretórios de módulos. Essas menções incluem leituras, código de apresentação
e comentários, além de possíveis relações em dados; não constituem inventário
completo dos schemas nem provam quais módulos estão ativos. Também podem existir
módulos adicionais e dados de integrações no banco real. Não foi possível auditar
a instalação real nesta cópia: as relações indiretas, valores JSON e writers de
cada módulo precisam de verificação antes de qualquer liberação.
