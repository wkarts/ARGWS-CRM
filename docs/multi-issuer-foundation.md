# Fundação cadastral de emitentes (opt-in)

## Resultado e limite obrigatório

Uma instalação e **um banco**, com várias empresas independentes no cadastro. Este módulo é uma fundação preparatória, **não um CRM com isolamento multiemitente concluído**. Faturas, despesas, pagamentos, contratos, projetos, tarefas, arquivos, relatórios, integrações e portal continuam com a semântica legada. Não iniciar a operação financeira de uma segunda empresa nesta fase.

O recurso aparece em Configurações → Recursos pelo catálogo `application/config/argws_resources.php`. Ativar `issuers` é uma escolha administrativa explícita. A instalação do código, sozinha, não ativa o módulo. O módulo `multiple_companies` existente trata empresas de clientes/contatos e não representa empresas emitentes; ambos continuam independentes.

`IssuerPolicy::assertOperationalScope($issuerId)` e `issuers_assert_operational_scope($issuerId)` sempre falham com `issuer_rollout_blocked` para qualquer ID válido. Não há opção, configuração, permissão administrativa ou parâmetro de requisição que libere a operação nesta fase. Este gate é contrato para futuros consumidores; não se deve anunciar que intercepta todos os fluxos existentes.

## Incluído

- Cadastro independente: razão social, CPF/CNPJ válido/normalizado, situação cadastral; ID inteiro positivo estável.
- Configuração própria inicia em `{}`. Não copia opções globais, integrações, credenciais, sequências fiscais, contas ou dados financeiros.
- Equipe vinculada explicitamente por empresa, com `view`, `manage_clients`, `share_clients`. Administradores gerenciam o catálogo e as associações, mas não têm acesso cadastral cruzado implícito.
- Preferência cadastral de empresa padrão por profissional. Somente vínculo ativo é selecionável; revogação/inativação limpa ou invalida o padrão, sem escolher outra empresa automaticamente.
- Adoção explícita, por administrador autorizado na empresa, de um cliente canônico já existente (`clients.userid`). Cria uma empresa proprietária e exatamente um vínculo inicial.
- Compartilhamento cadastral explícito com uma ou mais empresas, usando o mesmo cliente, sem duplicá-lo. A empresa proprietária é sempre preservada. O destinatário precisa de acesso de gerenciamento; somente a empresa proprietária controla os vínculos.
- Revogação de compartilhamento por substituição atômica dos destinos; lista vazia significa privado. A empresa proprietária pode revogar qualquer destinatário, inclusive inativo ou sem acesso atual do operador; somente destinos mantidos/adicionados precisam de acesso de gerenciamento. Os IDs atuais ficam visíveis no formulário antes da substituição. Revogar todos os destinos continua possível mesmo após corrupção de identidade no legado.
- Checagem de conflito CPF/CNPJ entre todos os clientes legados e registros adotados antes de registrar ou compartilhar. Não mescla nem escolhe automaticamente um cliente em conflito.
- Auditoria por evento e IDs técnicos, sem CPF/CNPJ, razão social, valores, credenciais ou payloads.
- Tela administrativa e formulários somente POST, com token de sessão próprio mesmo quando o CSRF global do legado está desligado.

## Fora do escopo / não concluído

- Isolamento de leitura/escrita de todos os módulos core, jobs, cron, PDFs, downloads, relatórios e portal.
- Criar clientes no core, migrar/adotar toda a base automaticamente, deduplicar ou resolver conflitos de identidade.
- Clonar dados financeiros, configurações globais ou integrações entre empresas.
- Credenciais Asaas, `account_id` ou vínculo financeiro de faturas. Uma integração futura deve usar `issuer_id` inteiro positivo, conta remota imutável e ambiente explícito; nunca inferir o emitente a partir de um cliente compartilhado.
- Alteração de `Cron_model`, `Invoices_model`, `modules/asaas`, migrations core ou schema inicial legado.
- Ativação automática, merge, deploy, uso de dados reais ou configuração de segredos.

## Banco de dados

O módulo 1.0.1 possui `migrations/101_version_101.php`, derivada de `App_module_migration`. Todo DDL base é aplicado pela migration; `install.php` somente encaminha ao executor nativo e confirma `installed_version`. A instalação e reativação partem da baseline 1.0.0; upgrades existentes usam o mesmo runner. Ver [convenção e recuperação de falhas](module-migrations.md).

Todas as tabelas usam o prefixo configurado mais `issuers_` e InnoDB:

| Tabela | Papel / integridade |
| --- | --- |
| `registry` | Emitentes, chave primária ID, CPF/CNPJ único; configurações vazias por criação |
| `staff` | PK empresa/profissional, flags por empresa, FK do emitente |
| `staff_defaults` | Uma preferência por profissional; FK composta exige vínculo existente |
| `clients` | Um registro por cliente canônico; proprietário; documento normalizado único quando presente |
| `client_links` | PK cliente/empresa, FKs para o cadastro canônico e emitente |
| `audit` | Eventos técnicos e IDs, sem valores pessoais |
| `lock` | Linha única que serializa as mutações do módulo |

O instalador é aditivo/idempotente, com `CREATE TABLE IF NOT EXISTS`; não altera tabelas legadas, não copia opções e não executa backfill. As referências a staff/clientes legados são revalidadas na aplicação, pois o core não mantém FKs a estas novas tabelas. Exclusões/inativações externas nunca concedem acesso e não são sincronizadas automaticamente.

As mutações usam transações, bloqueio `SELECT ... FOR UPDATE` da linha do módulo e índices únicos. Compartilhamento verifica tudo antes de substituir vínculos; qualquer erro de escrita/auditoria desfaz a transação. O teste de concorrência em MySQL cria duas empresas simultâneas com o mesmo documento e exige uma única criação.

DDL MySQL possui commits implícitos: uma falha de ativação pode deixar tabelas vazias parcialmente criadas. Corrigir a causa e repetir a ativação é seguro; não marcar a instalação como transacional. Nunca apagar tabelas existentes para recuperar.

## Identidade de clientes e privacidade

CPF e CNPJ numérico/alfanumérico são normalizados removendo somente formatação aceita, convertendo letras para maiúsculas e validando dígitos verificadores. CNPJ alfanumérico usa os valores ASCII menos 48 conforme o [manual técnico da Receita Federal/SERPRO](https://www.gov.br/receitafederal/pt-br/centrais-de-conteudo/publicacoes/documentos-tecnicos/cnpj/manual-dv-cnpj.pdf); o teste inclui o exemplo oficial `12.ABC.345/01DE-35`. Isso valida o formato e DV, não a existência ou situação fiscal. Clientes sem documento podem ser adotados privadamente, mas compartilhamento exige documento válido. Documento do cliente alterado no core após a adoção causa bloqueio `issuer_client_identity_changed`; requer revisão administrativa antes de qualquer mudança. Não copiar o cliente para contornar a validação.

A consulta de conflitos varre o campo legado `clients.vat`, que não possui índice normalizado. Isso prioriza consistência nesta etapa administrativa de baixo volume. Planejar uma coluna normalizada/índice e captura dos eventos de escrita do core antes de rollout de alto volume. A trava serializa consumidores deste módulo, **não escritores legados de clientes**; uma alteração concorrente fora dele pode acontecer. Essa é outra razão para não anunciar isolamento ou deduplicação end-to-end.

“Privado” descreve somente a lista deste módulo. O cliente continua existindo no core e segue as permissões legadas em outras telas. A interface mostra esse limite de forma permanente.

## Contratos para consumidores

Carregar o helper `issuers/issuers` somente com o módulo ativado. Ele fornece:

- `issuers_can_access($issuerId, $staffId, $capability = 'view'): bool`.
- `issuers_default_for_staff($staffId): ?int`: retorna `null` quando ausente/inválido; não transforma preferência em origem financeira.
- `issuers_assert_operational_scope($issuerId): void`: sempre lança `issuer_rollout_blocked` nesta entrega.

O modelo oferece `get($issuerId)` vinculado ao usuário autenticado, `can_access`, `available`, `default_for_staff` e `assert_operational_scope`. O serviço independente `IssuerRegistry` recebe o driver DB do CodeIgniter e prefixo. Nunca receber `actor_id` do POST; o controller sempre resolve a sessão.

O cadastro de empresas pode continuar crescendo sem liberar rotas financeiras. `issuer_id = NULL` em integrações que explicitamente preservem um modo legado é distinto de qualquer emitente cadastrado; `0`, ID de cliente e `company_id` não são substitutos válidos. Nenhuma resolução neste módulo busca configuração global em caso de configuração ausente da empresa.

## Testes

Implementados:

- `tests/issuers_registry.php`: validações reais, SQL funcional, ACL/IDOR, padrão, revogação, isolamento cadastral, cliente canônico, conflitos, alteração de identidade, rollback injetado, não alteração de finanças e auditoria.
- `tests/issuers_controller.php`: restrição POST, token próprio, formato de entrada, despachos e ator de sessão.
- `tests/issuers_concurrency.php`: dois processos MySQL concorrentes, um sucesso e um conflito.
- `.github/workflows/issuer-foundation.yml`: PHP 8.1 e 8.5, MySQL 8.0 descartável, sintaxe, controller, suíte funcional e concorrência. Senha declarada é somente de serviço efêmero com dados sintéticos.

Execução local possível sem aplicação configurada:

```sh
php tests/issuers_registry.php # PDO SQLite em memória, somente dados sintéticos
php tests/issuers_controller.php
```

Para banco **descartável de testes**, configure `ISSUERS_TEST_DSN`, `ISSUERS_TEST_USER`, `ISSUERS_TEST_PASSWORD`; a primeira suíte usa o mesmo schema e SQL MySQL da implementação. Então rode também `php tests/issuers_concurrency.php`. Os testes criam tabelas de prefixo aleatório `test_*`, sem acessar tabelas de produção. Nunca apontar o DSN para produção.

Evidência local inicial: PHP 8.5.10 via PHP-WASM, 126 verificações funcionais SQLite e 29 verificações do controller aprovadas; lint de todos os PHP do módulo aprovado. SQLite não comprova locks/DDL MySQL. Validação visual integrada e MySQL local não foram executados neste ambiente; verificar o resultado do CI para o commit publicado.

## Roteiro manual em homologação descartável

1. Ativar Emitentes e confirmar o aviso permanente de fundação, sem seletor global do CRM.
2. Criar duas empresas sintéticas; confirmar configurações vazias e ausência de cópia de dados/credenciais.
3. Vincular explicitamente o administrador e dois profissionais com permissões diferentes. Alterar o ID da URL/POST para empresa não autorizada: acesso deve ser negado.
4. Escolher um padrão, revogar o vínculo ou inativar a empresa: padrão não pode continuar efetivo. Reativar não escolhe automaticamente um padrão.
5. Adotar um cliente sintético existente em uma empresa. A outra lista deve ficar vazia; o core permanece com a semântica legada sinalizada.
6. Compartilhar com outra empresa autorizada; conferir mesmo `userid`, sem fatura/pagamento/documento copiado. Remover destinos adicionais e verificar privacidade cadastral.
7. Criar conflito CPF/CNPJ em homologação: vincular/compartilhar deve falhar sem merge e sem alteração parcial. Revogar todos os destinatários deve continuar possível.
8. Repetir envio, Voltar/Avançar, trocar empresa selecionada e tentar formulário com sessão expirada/token ausente: sem duplicação nem mutação sem token.
9. Não realizar cobrança real. O gate operacional deve continuar bloqueando todos os emitentes.

## Implantação futura e rollback

Esta PR é draft para revisão; nenhuma ativação ou implantação é autorizada por ela. Em uma futura implantação autorizada, fazer backup verificado, revisar permissões e conflitos em homologação, aplicar o código sem ativação automática, ativar o módulo somente após aprovação do cadastro preparatório e executar o roteiro acima. Não migrar clientes ou finanças em massa.

Rollback não destrutivo: desativar o módulo e reverter seus arquivos. Manter as tabelas e a auditoria para eventual reativação/revisão. Não há uninstall destrutivo. Tabelas, vínculos ou registros não são removidos automaticamente. A ausência de mudanças nos fluxos core evita dependência operacional nova.

Para liberar mais de um emitente operacional futuramente: primeiro mapear e implementar isolamento de todos os objetos/processos financeiros, definir propriedade dos legados, validar portal/relatórios/arquivos/jobs/webhooks por empresa, revisar permissões e referências imutáveis de conta/ambiente, testar fuga cruzada e concorrência em MySQL e só então alterar o gate em uma PR independente.
