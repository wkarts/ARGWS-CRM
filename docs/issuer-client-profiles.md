# Perfil cadastral de clientes por emitente

## Estado deste checkpoint

Implementação local para composição com a evolução cadastral de emitentes. **A criação privada continua bloqueada em produção.** O caminho positivo existe no serviço, controller e view e é exercitado com prontidão interna somente em fixtures sintéticos. A factory real `Issuers_model::client_profiles()` chama `IssuerClientProfiles::forProduction()`, cuja prontidão retorna `false` sem opção, ENV, parâmetro HTTP ou configuração administrativa de contorno.

Consulta, edição e compartilhamento de **clientes já registrados e vinculados** usam ACL explícita. Isso não atesta privacidade end-to-end: ainda existem leitores globais de clientes fora desta fronteira. O gate financeiro `IssuerPolicy::assertOperationalScope()` permanece inalterado e fechado. Não há adoção de cliente legado neste novo serviço; o guard anterior de adoção continua independente.

## Interface e contrato

Em Emitentes, cada cliente da lista abre `/admin/issuers/client/{issuer}/{client}`. A rota usa o emitente da URL, nunca o padrão do profissional ou um campo de proprietário recebido no perfil.

- `view` permite consultar somente um cliente ligado ao emitente selecionado; administrador também precisa de membership.
- Apenas o proprietário com `manage_clients` recebe formulário de edição. Um destinatário compartilhado vê texto somente leitura, mesmo tendo `manage_clients` no destino.
- A tela explica que editar altera o cadastro único para todos os emitentes vinculados. Compartilhamento continua separado na lista.
- “Novo cliente privado” abre a tela com o motivo do bloqueio e sem formulário de envio em produção. Não existe botão para liberar o gate.
- A seleção de compartilhamento só mostra destinos disponíveis com `manage_clients`; a validação do serviço repete a exigência para cada destino recebido. Revogar destino inativo ou inacessível continua permitido ao proprietário.

`POST /admin/issuers/save_client` exige token de sessão próprio, inclusive quando o CSRF global estiver desligado. A ação é `create_client` ou `update_client`; o payload `profile` aceita apenas `company`, `vat`, `address`, `city`, `state`, `zip`. O ator vem da sessão, nunca do POST. Erro de storage registra somente uma mensagem técnica genérica, sem valores do cliente.

O perfil é uma substituição completa desses seis campos, com limites compatíveis com o schema core e validação de UTF-8/caracteres de controle. CPF/CNPJ usa `IssuerPolicy::taxId`, incluindo CNPJ alfanumérico. Documento é opcional enquanto privado e obrigatório se compartilhado. A projeção de leitura contém somente `userid`, esses seis campos e `owner_issuer_id`; novas colunas core não são expostas automaticamente.

## Escrita canônica e isolamento transacional

`create(owner, profile)` insere diretamente em `clients`, depois em `issuers_clients` e `issuers_client_links`, com owner explícito e apenas vínculo do owner por padrão. `Clients_model::add`, hooks globais, contatos, login, anexos, configurações financeiras e notificações não são utilizados.

`update(owner, client, profile)` exige owner e identidade registrada ainda consistente, verifica duplicidade e atualiza core e identidade normalizada no mesmo commit. Não altera owner, contatos, campos de cobrança/entrega, credenciais nem documentos financeiros. `share(owner, client, targets)` conserva o mesmo `client_id`; exige `share_clients` no owner e `manage_clients` em cada destino mantido/adicionado. `IssuerRegistry::shareClient()` delega a esse serviço, preservando a rota/assinatura anterior sem outro writer de vínculos.

Toda operação do serviço possui uma transação independente:

1. Confere InnoDB em `clients`, `staff`, `issuers_lock`, `issuers_registry`, `issuers_staff`, `issuers_clients`, `issuers_client_links` e `issuers_audit` no MySQL/MariaDB.
2. Recusa nesting CI3, transação PDO existente, autocommit desligado e commit encadeado. `SET TRANSACTION ISOLATION LEVEL REPEATABLE READ` também impede entrar em transação SQL externa não refletida pelo driver CI.
3. Adquire primeiro `issuers_lock.id=1`, igual às mutações de Registry, e bloqueia membership, staff, emitente e cliente envolvidos antes de agir.
4. Revalida ACL, cliente e prontidão de criação após a trava e antes de persistir.
5. Retorna ID/DTO somente depois de commit confirmado e de verificar o encerramento da transação. Falha no caminho que o serviço possui faz rollback; transação do chamador não é desfeita.

Duplicidade é rejeitada **antes de inserir ou substituir vínculos**. A checagem consulta apenas ID/CPF-CNPJ de candidatos core e identidade registrada, com normalização de formato e caixa. O erro é genérico, sem nome/ID do cadastro oculto, e nunca mescla registros. A varredura core é intencionalmente conservadora e usa `FOR UPDATE`; em bases grandes terá custo de leitura/trava que deve ser medido antes de abrir criação.

## Checklist concreto antes de abrir a criação privada

Este é o trabalho restante deste gate, não um novo inventário geral de segurança:

- [ ] Exercitar nome/CPF/endereço sentinela privado nos fluxos de listagem/detalhe/resposta de tickets que fazem join direto de clientes em `Tickets_model`; impedir sua leitura sem vínculo explícito, incluindo administradores sem membership.
- [ ] Exercitar a listagem de projetos com join direto em `Projects_model` e os tooltips/eventos de contratos e projetos em `Utilities_model`; nenhuma identificação privada deve aparecer em agenda/busca/lista global.
- [ ] Compor os guards de writers financeiros e CSV de clientes/contatos do trabalho paralelo; repetir controle legado positivo e negativo privado no commit composto, sem presumir que este clone contém esses ajustes.
- [ ] Confirmar o comportamento de sessão/portal/autenticação sobre clientes previamente registrados, inclusive revogação, sessão antiga e recuperação de senha. Não ativar criação/adoção só porque o formulário administrativo passou.
- [ ] Confirmar preview/download genérico e acesso estático a uploads com cliente privado; o bloqueio de uma rota de download não prova o restante.
- [ ] Executar a suíte transacional e `issuer_client_profile_concurrency.php` em MySQL/MariaDB descartável com processos distintos; comprovar colisão do mesmo CPF/CNPJ e revogação de ACL enquanto outro processo aguarda a trava.
- [ ] Rodar navegador e sessão CI reais: proprietário, destinatário, view-only, administrador sem vínculo, POST forjado, voltar/cancelar, repetir envio e acesso após revogação. Só então decidir por revisão de código se a factory de criação pode mudar; não adicionar switch de contorno.

Esses itens não autorizam adoção automática do legado nem operação financeira multiemitente.

## Testes e evidência local

Testes adicionados:

- `tests/issuer_client_profiles.php`: serviço real com SQLite, CPF/CNPJ alfanumérico, criação interna positiva privada, projeção, duplicidade oculta, owner-only, compartilhamento/revogação, inativos, sentinelas financeiras e credenciais preservadas.
- `tests/issuer_client_profile_transactions.php`: transações CI3 reais com PDO/SQLite, falhas em cada escrita/commit, rollback, nesting, prontidão/ACL revalidadas sob trava; probes de protocolo/engine MySQL explicitamente simulados em SQLite.
- `tests/issuer_client_profile_controller.php`: controller/model/service/views reais com dependências HTTP controladas; CSRF, ator da sessão, edição produtiva autorizada, compartilhado somente leitura, escaping, seleção de destinos e bloqueio produtivo de criação.
- `tests/issuer_client_profile_concurrency.php`: teste MySQL implementado, **não executado localmente**; requer PHP nativo, PDO MySQL, `proc_open` e banco descartável. Não usa dados reais.

Execução local em PHP 8.1 WASM:

```sh
PHP=8.1 node /tmp/argws-php-runtime/node_modules/@php-wasm/cli/php-wasm.js tests/issuer_client_profiles.php
PHP=8.1 node /tmp/argws-php-runtime/node_modules/@php-wasm/cli/php-wasm.js tests/issuer_client_profile_transactions.php
PHP=8.1 node /tmp/argws-php-runtime/node_modules/@php-wasm/cli/php-wasm.js tests/issuer_client_profile_controller.php
PHP=8.1 node /tmp/argws-php-runtime/node_modules/@php-wasm/cli/php-wasm.js tests/issuers_registry.php
PHP=8.1 node /tmp/argws-php-runtime/node_modules/@php-wasm/cli/php-wasm.js tests/issuers_controller.php
```

Mensagens finais observadas: 74, 60, 31, 138 e 29 checks, respectivamente; sem Fatal/Warning. Os mesmos comandos também foram repetidos em PHP 8.5 WASM. O exit code do WASM sozinho não é prova; cada execução foi conferida pelo marcador final. Lint dos PHP alterados/novos e `git diff --check` também passaram.

Esses resultados não substituem servidor HTTP completo, sessão/navegador, MySQL real ou validação end-to-end. O caminho de concorrência deve ser executado em banco isolado com `ISSUERS_TEST_DSN`, `ISSUERS_TEST_USER` e `ISSUERS_TEST_PASSWORD` fornecidos pela infraestrutura de teste. Essas variáveis não parametrizam o produto.

## Integração, versão e reversão

Nenhuma migration, header ou schema foi alterado por este componente. Ele deve ser composto com o versionamento do módulo coordenado na branch principal de evolução; não publicar este commit isolado como atualização funcional sem a nova versão/migration nativa exigida pela contribuição do CRM. Migrations históricas não devem ser editadas.

Não houve push, PR remota, merge, deploy, alteração de dados reais nem liberação dos gates. Em homologação, reverter este componente requer manter as barreiras de leitura de clientes já privados. Não usar rollback de código como justificativa para tornar clientes privados legados globais. Nenhum cliente ou vínculo é apagado automaticamente pela reversão.
