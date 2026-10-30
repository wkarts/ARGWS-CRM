## Integração nativa446

Composto no núcleo4.4.6/446, migration NOOP oficial, BB122 eAsaas153 intocados. Testes SQL finais nos dois motores, API/controllers/concorrência eHTTP/Chromium em instalação fresca e upgrade445→446 constam de docs/companies/pr45-validation-446.md. O status da publicação/CI pertence aoSHA confirmado naPR45; não é inferido de provas locais. A descrição original do pacote abaixo permanece como histórico de autoria.

# Preferências próprias de criação de tarefas

Vertical independente baseada no núcleo444. Não contém versão, migration, DDL ou publicação.

## Escopo entregue

Grupo `task_defaults`, JSON próprio `task_creation_preferences` por empresa, inclusive principal. Somente `default_task_status` (`todo`, `in_progress`) e `new_task_auto_assign_current_member` (`0`, `1`). Sem nó próprio: `todo` e `1`, sem copiar opções globais ou `company_options`. Configuração exige `settings/view` e `settings/edit`, ator atual e empresa ativa. Revision otimista, auditoria e frame do JSON completo confirmado depois da última escrita.

Consumidores: `CompanyTasks::create`, `CompanyStandaloneTasks::create`, formulários de criação reais e `CompanyOperationsApi` existente, que chama os mesmos writers. Omissão de estado consome a preferência; omissão de responsável seleciona o ator atual somente quando a política permite. Responsável explícito mantém validação própria; null, zero e vazio continuam inválidos. Estado explicitamente null mantém o contrato anterior de normalização para `todo`, jamais a preferência `in_progress`. Valores explícitos válidos prevalecem. Criação nunca aceita `done`. `in_progress` é estado inicial sem abrir cronômetro, concluir checklist, lançar horas ou faturar.

A presença dos campos faz parte do novo hash. Consulta do recibo precede a leitura dos defaults: retry confirmado preserva tarefa e recibo originais mesmo após edição legítima das preferências. Alias limitado ao hash canônico anterior permite replay dos recibos antigos de criação `todo`, sem regravá-los. Replay continua exigindo ator/empresa/operação/hash e permissões atuais. Cópias revisadas permanecem com valores explícitos, `todo`, responsável revisado e nenhum timer/financeiro herdado.

## Limites reais

`default_task_priority` permanece fechado: os dois schemas, DTOs e writers próprios não possuem prioridade. Seguidores automáticos permanecem fechados: há writer de follow para projeto/tarefa, porém nenhum compositor atômico de criação e nenhum contrato/schema de seguidores para tarefas avulsas. Não há adoção de tabelas legadas. Arredondamento, timers automáticos e regras de faturamento ficam fora desta vertical. Outros grupos de configuração permanecem fechados.

## Evidências

Testes novos: `tests/settings/task_preferences.php` (Settings real, revisão, enum, ACL, lastaudit e preservação de opções); `task_preferences_consumers.php` (writers/API, A/B/principal, ator atual, omissão/null/override, hashes/recibos legados, rollback depois audit/receipt); `task_preferences_controller.php` (controladores e renderização reais); `task_preferences_copies.php` (cópias explícitas independentes); `task_preferences_concurrency.php` (oito cenários MariaDB com dois waiters reais observados).

`task_preferences_http_fixture.php` faz somente SELECT em banco sintético explicitamente confiável, com versão atual coerente >=444. `task_preferences_browser.cjs` fornece GUI A/B/principal, formulários dos dois consumidores, POST com omissões/retry, CSRF, contexto/revisão obsoletos e trigger sintético de lastaudit. Preparado e verificado sintaticamente; não executado em navegador pelo autor. HTTP composto, migrations e validação do SHA publicado pertencem ao integrador e permanecem pendentes até ocorrerem.

Os testes novos são reconstruídos explicitamente para esta vertical; não são apresentados como recuperação byte-exata de testes perdidos.
