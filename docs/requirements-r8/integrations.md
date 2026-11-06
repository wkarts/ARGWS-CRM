# R10/R11 — integrações e Ajuda online

## Bases e método

Referência funcional: `v4.0.2`, commit `aa58b832b3c5ed04a599caacba125b056c081ba7`.
Destino inspecionado: `main` 4.8.8, commit `3cd2ac577a969840c94b019f602cb69d710c0e78`.
A versão 4.8.7 informada no requisito não foi usada para substituir essa base real.

O [inventário verificável](integrations-inventory.json) registra arquivos, métodos
declarados, rotas literais, hooks, opções e hashes Git das views nas duas bases.
Métodos auxiliares e classes base estão identificados: a lista de métodos não é
uma afirmação de que cada método é um endpoint público. Ativação dos módulos,
configuração de provedores, respostas remotas e equivalência visual em navegador
não são inferidos desse levantamento estático.

## Áreas reais, correspondências e preservação

| Área | Recursos encontrados | Correspondência 4.0.2 → 4.8.8 |
| --- | --- | --- |
| API (`modules/api`) | Administração de usuários/chaves e guia; REST de contatos, contratos, créditos, clientes, campos personalizados, orçamentos, despesas, faturas, itens, leads, marcos, pagamentos, projetos, propostas, equipe, tarefas e tickets; login e gerenciamento de chaves | Seis views com os mesmos blobs; correções recentes de propriedade de clientes/faturas/equipe são preservadas. API usa contratos e autenticação próprios, não é Connect API. |
| Webhooks (`modules/webhooks`) | Cadastro, edição, cópia, ativação, debug, logs/detalhes/limpeza, fila/exclusão de agendamento e executor cron; hooks de contatos, leads, faturas, tarefas, projetos, propostas, tickets, pagamentos, equipe, contratos, orçamentos e calendário | Nove views com os mesmos blobs; eventos, payloads, cron e filas preservados. |
| Connect API / Manager | Configuração, teste, listagem/criação de instâncias, conexão e estado, reinício, logout, exclusão, envio de teste e acesso a token conforme capacidade | Módulo inteiro idêntico nas bases comparadas; três views idênticas. |
| Connect API / Conector | Perfil de instância, vínculo com Manager, teste, conexão/pareamento, estado, reinício e envio de teste; gateway SMS e gatilhos de faturas/pagamentos | Módulo inteiro idêntico nas bases comparadas; duas views idênticas. |
| Connect API / Chat | Interface normal e app; troca de instância; conversas/mensagens/poll/eventos em tempo real/watchdog; busca, status, envio de texto/mídia/contato, download de mídia, nova conversa, atribuição, exclusão, sincronização; configuração de webhook, diagnósticos e PWA | Cinco views idênticas. Preservada correção recente de escopo do contato no contexto CRM. Recepção de webhook continua separada, com seu segredo e processamento durável. |
| Connect API nativa por empresa | Perfil privado, validar/revogar configuração; intenção e confirmação de pareamento por QR; reinício com intenção/observação; telefone de contato, fila e envio de avisos de faturas; política/avisos de tickets e workers | Adições posteriores à referência. Todos os recursos permanecem; não são substitutos integrais dos três módulos acima. Arquivos/métodos estão no inventário complementar. |
| Ajuda online | `Knowledge_base` do núcleo: artigos, grupos, kanban, ordem/cor, leitura/pesquisa, feedback e autorização por contexto | A entrada real é traduzida por `als_kb`. Não se trata de `wiki`. A base recente já adaptava os templates originais a artigos/grupos da empresa; a interação kanban foi corrigida nesta alteração. |

Os 24 arquivos de controllers da API incluem duas classes base. O inventário
registra 119 métodos declarados no conjunto, não 119 contratos REST. Webhooks
possui três arquivos/16 métodos; Manager um/13; Conector um/nove; Chat dois/29.

## Correções implementadas

### Administração da API

`Api::api_management()` obtinha e renderizava os usuários e seus tokens sem a
verificação administrativa já existente nas demais ações de gerenciamento.
A ação agora exige `is_admin()` antes de ler dados ou renderizar. O bloqueio tem
retorno explícito, mesmo se `access_denied()` não interromper a execução.

O módulo passa de **2.0.3 para 2.0.4**, com a nova migration nativa
`204_version_204.php` NOOP. Tokens, schema, rotas, formato das respostas REST e
as views não são modificados.

### Autorizações de Webhooks

O controller administrativo não verificava suas capacidades antes das ações.
As capacidades declaradas pelo módulo passam a ser verificadas diretamente:

| Ação | Permissão exigida antes de qualquer efeito |
| --- | --- |
| Listagem, logs, detalhes do log, tabela da fila | `view` |
| Cadastro | `create` |
| Edição, estado ativo, estado de debug | `edit` |
| Exclusão, limpeza de logs, exclusão da fila | `delete` |
| Cópia | `view` e `create` |

A base possui os namespaces históricos `WEBHOOKS` e `webhooks`. O guard aceita
a capacidade exata em qualquer um deles, sem transformar `view` em edição e sem
gravar novas permissões. Cada método para imediatamente após a negação.

O módulo passa de **1.2.7 para 1.2.8**, com `128_version_128.php` NOOP. Não há
alteração nos métodos HTTP, nomes de ações, eventos, payloads, filas ou views.

### Ajuda online

No kanban recente, ordenar artigos/grupos ou mudar a cor recarregava a página
após sucesso. A referência preserva a interação no próprio quadro. A resposta
confirmada agora atualiza as revisões locais, os contadores e os atributos do
modal de edição sem recarregar. A próxima interação usa a revisão atual.

Uma resposta malformada, incompleta ou referente a uma revisão já ultrapassada
não sobrescreve os tokens atuais. O lote é validado integralmente antes de
alterar o DOM. A alteração não muda o payload legado nem remove a revisão
otimista exigida pelo serviço da empresa.

## Limites reais de R11 que exigem decisão

**Manager, Conector e Chat da instalação não podem ser atribuídos a empresas por
inferência.** O Manager aceita `scope=all`, que inclui instâncias remotas fora de
seu namespace. Sua tabela identifica a instância por nome, sem dono `issuer_id`.
O Conector aceita credencial/nome de instância configurados e também consome o
Manager. As conversas, mensagens, filas e eventos do Chat usam essa instância.

Decisão necessária antes de reatribuir esses recursos: quais instâncias e
históricos pertencem a cada empresa e quais são operações administrativas da
instalação compartilhada? Trocar silenciosamente `all` por `module`, atribuir
tudo à empresa principal ou substituir o Manager pelo perfil privado recente
alteraria contratos ou perderia recursos. Esta PR conserva esses recursos e a
barreira existente: os módulos sem adapter não são habilitados por mera adição
de rota para empresas secundárias.

**Webhooks e API legados também exigem um mapeamento explícito de propriedade.**
As tabelas globais de eventos, fila e tokens não ganham uma associação presumida
com o seletor administrativo. A API financeira nativa do núcleo tem contratos
distintos; não se modifica a autenticação externa do módulo API para imitar uma
sessão de navegador. As correções de ACL acima tratam defeitos reproduzidos no
controller, não comprovam isolamento integral de integrações.

O perfil Connect API nativo já possui titularidade explícita e preserva seus
recursos de QR, reinício, avisos e workers. Ele continua separado dos contratos
do Manager e Chat. Não foram enviados textos, webhooks ou solicitações reais a
provedores durante esta verificação.

## Evidências executadas

PHP 8.3 local, SQLite nos fixtures existentes e Node 24. Os comandos abaixo
foram executados com o PHP disponível no ambiente de trabalho.

| Comando/cenário | Resultado observado |
| --- | --- |
| `php tests/integration_admin_authorization.php` | 81 verificações aprovadas; executa os métodos reais com dependências inertes. Nega antes de ler tokens/alterar modelos, exige a capacidade correta, preserva payloads permitidos. |
| Mesmo teste com controllers extraídos da base anterior | Falha reproduzida: `api_management denies the missing capability`, antes de aplicar a correção. |
| `node --test tests/native_company/knowledge_kanban.test.cjs tests/native_company/knowledge_ui.test.cjs` | Cinco testes aprovados: ordem, cor, contadores/revisões, rejeição de resposta inválida/atrasada, payload legado e superfície existente. |
| Dez scripts `tests/native_company/knowledge_*.php` | controller, feedback, inflight_lineage, mutations, order, presentation, public, read, schema e write aprovados. |
| `php tests/companies/legacy_module_readers.php` | 25 verificações aprovadas. |
| `php tests/client_issuer_api.php` | 20 verificações aprovadas. |
| `php tests/native_navigation_parity.php` | Aprovado. |
| `php -l` dos controllers API/Webhooks e da view de artigos | Sem erros de sintaxe. |

O teste Node usa uma simulação de DOM/jQuery para executar os handlers reais da
view; não é teste de navegador nem comparação por screenshot. Homologação
visual lado a lado, MySQL de instalação/upgrade, marcador real das migrations,
PWA e interações externas continuam pendentes de ambiente apropriado/CI. Não
se declara AC50–AC64 integralmente atendidos por esse inventário e esses testes.
