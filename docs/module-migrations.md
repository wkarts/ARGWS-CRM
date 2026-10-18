# Convenção de migrations dos novos módulos CRM

Todo módulo novo instalável introduzido nesta evolução começa na versão `1.0.1`, com a primeira migration `101_version_101.php`. Não se cria migration `011`, `100` nem versões artificiais anteriores para preencher a sequência. Todo módulo deve conter `migrations/` e uma migration PHP versionada, mesmo quando `up()` seja intencionalmente vazio. O arquivo e a classe devem corresponder à versão do cabeçalho: `1.0.1` → `101_version_101.php` → `Migration_Version_101 extends App_module_migration`.

`install.php` não executa DDL nem chama um serviço de schema diretamente. Ele encaminha a instalação ao executor nativo `App_module_migration`, que aplica `up()` e registra `installed_version` em `modules`. Upgrades usam o mesmo executor. As migrations do módulo não pertencem ao namespace de versões das migrations core.

## Ativação e sequência

O framework registra a versão do cabeçalho antes de chamar o hook de ativação. Para não pular a primeira migration, o instalador de `issuers` estabelece a baseline `1.0.0`, chama `to_latest()` e confirma a versão final. A versão inicial migrável é `1.0.1` (101). Reativação reaplica as migrations aditivas e repetíveis, sem apagar dados ou escolher uma empresa padrão. Código mais antigo não pode rebaixar uma versão instalada mais nova.

Depois de 101, a próxima migration do módulo é 102 (`1.0.2`). Evitar lacunas: o executor verifica continuidade das migrations pendentes. Não trocar 101 por 110 nem registrar manualmente uma versão como concluída antes de executar seu schema.

Falha no DDL ou na confirmação de tracking interrompe a ativação. O MySQL pode manter parte de um DDL já aplicado; o marcador pendente e as operações idempotentes permitem retry, sem prometer rollback transacional de DDL. `down()` da fundação rejeita remoção de propriedade, vínculos e auditoria. Emitentes torna-se essencial em 102; seu backend recusa desativação e desinstalação. Outros módulos podem ter política diferente, sempre preservando os dados conforme seu contrato.

`CREATE TABLE IF NOT EXISTS` preserva tabelas existentes, mas não repara um schema preexistente incompatível. Os testes validam o schema produzido pelo código e o replay esperado; não constituem auditoria estrutural universal de instalações antigas. Uma instalação com tabelas alteradas exige inspeção e correção autorizada antes de ativar o módulo.

## Validação

- `tests/test_module_migration_convention.py` aplica o contrato a `issuers` e `pige_bridge` quando presentes. PRs independentes não dependem da instalação do outro módulo. O gate exige que a primeira migration seja 101 e aceita posteriores contíguas, como 102; rejeita início em 011 ou 100. Não exige arquivos para versões anteriores a 101 que nunca existiram. O gate não dispensa revisão de migrations nem audita automaticamente todos os módulos legados.
- `tests/issuers_migration.php` executa o `App_module_migration` real: instalação nova, reativação, upgrade até a versão do cabeçalho, no-op, falha de DDL, tracking não confirmado, rejeição de downgrade e preservação de dados sintéticos.
- CI usa MySQL 8 em banco descartável e verifica as sete tabelas InnoDB e cinco relacionamentos de chave estrangeira. SQLite local traduz somente DDL para exercitar o fluxo; não constitui evidência MySQL.
- Nenhuma dessas verificações ativa operação financeira multiemitente ou autoriza deploy.
