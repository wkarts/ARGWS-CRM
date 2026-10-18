# Emitentes como recurso essencial

A versão 1.0.2 torna `issuers` obrigatório. A migration 101 já publicada não é alterada; 102 verifica a presença das tabelas, colunas essenciais, PKs, unicidade de documento, FKs, engine InnoDB e trava singleton antes de registrar a versão. Não cria uma empresa, não adota clientes, não copia configurações/credenciais globais e não atribui emitente a faturas históricas. A empresa principal e a operação legada continuam com suas configurações existentes. Os gates operacionais multiemitente permanecem fechados.

## Conclusão da instalação

- **Provisionamento do container:** o processo CLI interno autenticado pelo token temporário existente aplica as migrations core e carrega explicitamente o executor nativo de módulos. Emitentes só é marcado ativo depois da instalação e confirmação de schema e versão. Uma falha impede o provisionamento de anunciar conclusão.
- **Instalador web legado e upgrade web:** são duas etapas. Depois da criação/atualização do core, um administrador ativo autentica-se e conclui a configuração em `admin/essential_modules`, por POST com token próprio de sessão. O painel permanece bloqueado até a confirmação. A tela GET somente verifica e apresenta a ação; nunca instala nem ativa. O CSRF próprio é obrigatório mesmo se o CSRF global estiver desligado.
- Logout, autenticação/recuperação de senha e a tela de configuração não herdam o bloqueio do painel. A política não adiciona uma dependência de configuração às rotas legadas de callbacks, jobs ou portal. Essas rotas não executam ativação automática.

O módulo não pode ser desativado ou desinstalado, inclusive por chamada direta a `App_modules`, antes de hooks ou escrita. A lista de recursos mostra “Essencial — obrigatório” e não oferece esses comandos. O parâmetro de recuperação `skip_modules_load` continua útil para módulos opcionais; não retira Emitentes. Ocultar recursos por variável de ambiente também não remove essa obrigação.

## Falhas e recuperação

Antes de executar migrations durante ativação ou upgrade essencial, `active=0` é gravado e relido. O marcador inicialmente pré-gravado pelo mecanismo nativo não basta: `install.php` executa a sequência nativa e verifica a versão final. Só depois de `assertSchema` é permitido gravar `active=1`, que também é relido. A prontidão do painel verifica estado, versão e integridade estrutural mínima sem DDL. Logs não incluem SQL, credenciais nem dados de clientes.

Migrations parciais deixam a configuração pendente. Corrija a causa e repita o mesmo POST/provisionamento; a sequência é aditiva e idempotente. Versão instalada superior à disponível é recusada, sem downgrade. A verificação exige os índices e relacionamentos de segurança previstos e InnoDB no MySQL; não é auditoria universal de todos os tipos, collations ou dados previamente incompatíveis. Não altere manualmente o marcador ou a flag para ignorar falhas e não apague tabelas para tentar novamente.

## Testes

`issuers_migration.php` usa o executor real `App_module_migration` em instalação, upgrade 101→102, replay e falhas de DDL/tracking. `issuers_essential.php` exercita o backend real de ativação com armazenamento sintético, incluindo prewrite e proibição de desativar/desinstalar. `issuers_essential_controller.php` cobre autenticação, GET sem mutação, POST/CSRF obrigatório, falha e retentativa. `issuers_essential_upgrade.php` verifica o caminho controlado de provisionamento e a ausência de reconciliação no upgrade web legado. A workflow executa MySQL isolado em PHP 8.1 e 8.5; SQLite local traduz DDL e não substitui a evidência CI MySQL. O smoke Docker permanece requisito para o bootstrap completo.

### Custo das verificações

Os metadados estruturais são verificados uma vez por instância DB na requisição e mantidos apenas em WeakMap de memória. Estado ativo, versão instalada e trava singleton continuam relidos: o cache não concede acesso nem substitui ACL. Reconciliação e migration102 invalidam o cache de schema e o cache de tabelas do CI3 antes de revalidar. Não há cache persistente de prontidão ou permissões.
