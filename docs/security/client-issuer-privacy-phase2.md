# Privacidade de clientes por emitente: checkpoint local, fase 2

## Estado e contrato

Este checkpoint **não está pronto para publicação ou implantação**. O módulo cadastral isolado continua distinto da ativação de privacidade no core. Nenhuma operação financeira positiva por emitente é liberada aqui. O contexto legado continua `issuer_id IS NULL`; a existência de um proprietário ou vínculo do cliente nunca infere nem concede a propriedade de uma fatura.

O core legado passa a excluir clientes com registro em `issuers_clients`, mesmo se o módulo for desativado. Leitura positiva por emitente continua disponível apenas por APIs explicitamente scoped e com ACL viva. O cadastro legado novo continua legado neste checkpoint: a criação privada por padrão com UI e contrato completos ainda precisa ser implementada antes da liberação final.

## Proteções deste checkpoint

- Guards antes de custom fields, hooks, hashes, e-mail e mutações de clientes/contatos; impossibilidade de alterar IDs/pais pelo payload comum.
- Contatos, administradores, países, moeda, endereço, arquivos, grupos e vault nas rotas e modelos cobertos excluem clientes privados. Os modelos baixos de grupos e vault recebem guards próprios.
- Busca global de contatos, diretório e tabelas de contatos usam predicados obrigatórios fora de ORs opcionais.
- API de contatos usa a seleção protegida do modelo, não retorna senha/tokens de redefinição/verificação e não embute `clients.*`. O contato de uma URL aninhada precisa pertencer ao cliente informado.
- Busca de faturas legadas na API e no core retorna apenas `userid`, `vat` e nome da empresa do cliente. Endereços e dados financeiros já pertencentes à fatura são mantidos; os dados privados adicionais do cadastro não podem ser pesquisados por esse atalho.
- Fallback de identidade de fatura permanece DTO mínimo de fatura legada previamente autorizada. Extrato não tem fallback de cadastro inteiro: a rota recusa o cliente privado nesta fase.
- Upload/alteração de compartilhamento de arquivo e download de anexo de cliente cobertos rejeitam cliente privado antes de arquivo/SQL; destinatários de compartilhamento devem pertencer ao mesmo cliente. Downloads do portal verificam o mesmo cliente, visibilidade e compartilhamento do contato.
- Adoção nova do módulo é bloqueada quando o serviço de privacidade do core está presente. O guard está na Registry, não apenas na interface. A UI informa o bloqueio. Instalações apenas com o fundamento, sem esse serviço, mantêm seu contrato.

## Bloqueadores de rollout

1. **Portal/sessão ainda não scoped.** `App_clients_area_constructor` chama `get_contact()` e encerra a sessão quando o contato fica invisível. Não há bypass ambiente nem exceção baseada só na sessão. Implantar esta fase sobre clientes já adotados no módulo pode interromper o portal. Antes de rollout, concluir e testar um caminho explícito de sessão/autenticação/recuperação de senha ou preparar uma transição aprovada. Apenas bloquear novas adoções não resolve clientes já adotados.
2. **Leitores fora das fronteiras cobertas.** Há consultas diretas em tickets, projetos, calendários, exportações e módulos de terceiros. Busca e projeções de outros tipos de documento também precisam de revisão. O inventário permanece partial/unreviewed e não prova fechamento total.
3. **Arquivos fora da rota coberta.** Preview genérico por caminho e eventuais URLs estáticas de upload necessitam política de autorização/storage própria. Proteger `Download::file('client')` não fecha esses caminhos.
4. **Financeiro independente.** Faturas `NULL` não são transferidas com adoção/vínculo; extrato, duplicação, GDPR/delete e manutenção financeira precisam ser integrados com os patches financeiros e retestados.
5. **Execução real completa.** Ainda faltam MySQL/MariaDB, sessão real, browser, servidor de uploads e matriz PHP 8.1/8.5 nativa. O ambiente local usa PHP 8.5 WASM com SQLite.
6. **Concorrência.** Os guards legados não são uma implementação transacional de criação/adoção privada concorrente. Adoção fica bloqueada antes de transações; remover esse bloqueio exige desenho e testes de consistência entre ownership, writes e efeitos externos.

Pré-verificação de dados para a integração: contar clientes em `issuers_clients` e contatos que se relacionam a esses IDs. Resultado diferente de zero exige revisão de acesso/sessão antes de implantar; não apagar nem mover vínculos ou clientes automaticamente.

## Evidência local

Os testes executam os métodos de produção com adaptador de query builder PDO/SQLite e dados sintéticos. Não são testes de HTTP/framework completo ou prova de isolamento de toda a aplicação.

- `tests/client_issuer_scope.php`: predicados, ACL viva, revogação e DTO mínimo de fatura.
- `tests/client_issuer_model.php`: getters reais de cliente e contato.
- `tests/client_issuer_mutations.php`: negativos sem escrita/efeito, controle legado positivo e bloqueio de reparenting.
- `tests/client_issuer_api.php`: API e busca reais com prefixo não padrão; projeções e credenciais.
- `tests/client_issuer_controllers.php`: ações reais com dependências controladas, 404 antes de efeitos e guard de adoção.
- `tests/client_issuer_helpers.php`: helpers reais, caches obsoletos, arquivos/identidade e uploads antes de efeitos.
- `tests/client_issuer_tables.php`: execução SQLite dos predicados produzidos pelas views DataTables.
- `tests/api_issuer_scope.php`: regressão financeira existente, com schema sintético atualizado para o campo VAT real.

A suíte de composição `tests/invoice_issuer_recurring_lock.php` foi tentada e está bloqueada neste checkpoint: falta `application/services/RecurringInvoiceLock.php`, já ausente na base. O patch de recorrência precisa ser composto e essa suíte repetida; não foi considerada aprovada.

Resultado final local: 156 verificações nas sete suítes de clientes; 13 na API financeira, 145 no core de faturas, 14 em relatórios/manutenção e 29 no controller de emitentes. Os 145 checks do core também são repetidos como bootstrap de relatórios, não constituindo cobertura distinta. Lint dos 28 PHP alterados/novos passou.

O exit code do runtime WASM pode ser zero após Fatal; a verificação local exige a mensagem final `checks passed` e ausência de Fatal/Warning. CI nativo está descrito no workflow de clientes.

## Integração e reversão

Não aplicar este checkpoint sozinho. Compor com correções financeiras e portal numa branch de revisão; revisar os hunks compartilhados de `Clients_model`, `Misc_model`, `Api_model`, helpers e Registry. O patch não tem migration, não move dados nem publica credenciais.

Não usar uma reversão de código que volte a expor clientes previamente privados como estratégia automática. Se houver dados privados, a decisão de rollback precisa preservar a barreira ou restaurar o backup completo consistente mediante aprovação. Nenhum deploy, merge ou rollback foi executado neste checkpoint.

## Composição local com o stack financeiro

O checkpoint foi composto com a migration core383, barreiras384, writer de pagamentos/créditos e documentos102. A suíte de lock recorrente antes bloqueada passou8 checks nessa composição, e as sete suítes de clientes passaram156. Também passaram API13, core145, relatórios14, extrato21, busca13, seleção de export6 e helpers/documentos105. São evidências locais SQLite/protocolo simulado; MySQL e sessão/HTTP reais permanecem pendentes. Os guards de faturas e clientes nos helpers de upload/download e Misc foram preservados juntos. Os bloqueadores de portal e rollout acima continuam válidos.
