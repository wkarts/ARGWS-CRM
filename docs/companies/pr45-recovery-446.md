# Reconciliação446 — tarefas, calendário e cadastro nativo

Pai remoto confirmado `0a10a15022ac8a93c75b3b557253ffc508f4d8a2`, árvore `3903905fecb5a0ea799ed83bf84455fc40065cc6`. Esse pai contém integralmente439–445 e o inventário determinístico corrigido. O trabalho recuperado de candidate445 foi preservado em checkpoint com manifesto e reclassificado para446: o número445 foi empregado primeiro nos reparos de CI. Não se anuncia recuperação de fontes NoncashV2 perdidos nem equivalência a154testes perdidos.

Núcleo4.4.6/446, gerador oficial e migration NOOP; módulos BB1.2.2/122(mínimo4.4.3) eAsaas1.5.3/153 permanecem iguais. Nenhuma migration histórica editada, DDL/backfill ou concessão nova automática. A publicação e os resultados remotos são vinculados aoSHA efetivo naPR45; este documento não antecipa o resultado doCI.

| Requisito | Arquivo/implementação | Prova na composição | Limite |
|---|---|---|---|
| Preferências de criação próprias | CompanyTaskPreferences; CompanyTaskCreationDefaults; CompanyTasks/StandaloneTasks; Settings | Cinco suítes nos dois bancos, API existente, oito corridas com dois waiters; navegador A/B/principal | Somente estado inicial e autoatribuição. Prioridade, seguidores automáticos e regras adicionais de timers continuam pendentes |
| Calendário próprio | CompanyCalendarPreferences/Read/Sources/Presentation; Company_calendar; view própria | Cinco suítes nos dois bancos, seis corridas com dois waiters, fonte e ACL reconfirmadas após renderização | Sete fontes canônicas somente leitura; sem eventos pessoais, lembretes, departamentos ou Google |
| Cadastro principal/empresas nativo | App_Router::locate |29testes comCI/MXreais; GUI master17/principal/A/B | Reserva admin/issuers ao controller nativo, bloqueia rota pública do módulo arquivado; demais rotas delegadas aoMX |
| Upgrade445→446 | native_resume_446 e tracking + instalador realHTTP | App445 executado antes do446, marcador real reutilizado; falha/ignore/readback/retry/replay; histórico preservado | Preparação dos novos defaults antes da captura usa writer446, não é alegada como execução do binário445 |
| CI BB: comparação de schema | tests/banco_brasil_resume_443_module_tracking.php | REDMySQL→GREENMySQL/Maria; instalação e upgrades reais120/121→122 | Normaliza exclusivamente charset redundante utf8mb4/utf8mb4_bin emSHOWCREATE e adiciona comparação exata de TODAS as colunas information_schema; não altera produto/runner |

## Causa do cadastro500

MX_Router ainda descobria o controller de modules/issuers, retido como histórico, carregando helper arquivado e ocultando a função nativa issuer_core_ready. O router agora encaminha somente a rota administrativa exata ao controller nativo. Não se adicionou require no menu nem reescreveu o módulo arquivado.

## Causa adicional do CI BB no61efacb9

O erro do processo filho não era o teste negativoSQLite, já corrigido445. Ao injetar/restaurar collation, MySQL preserva noSHOWCREATE o texto explícito CHARACTER SET utf8mb4 junto de COLLATE utf8mb4_bin; as linhas continuavam idênticas. Reproduzido também emMySQL8.0.46. A comparação agora conserva todos os metadados reais das colunas e as demais partes doDDL, eliminando somente essa redundância textual. Os quatro testes de collation inválida, ausência deDDL durante o runner, marcadores falhos/ignorados e preservação de linhas permanecem exigidos. O erro/exit do filho passa a aparecer no log quando falhar.

## Preservação e validação

18.558arquivos de runtime/distribuição congelados, manifestoSHA256 `a5dc6861b4581fff84d070561e16d95074d449aa38f0c16eff4f583e057ac329`. Comparados por arquivo à composição independente HTTP, zero diferença. As17suítes finais executaram serialmente em cada motor, zero drift. Os380comandosPHP publicados do workflowEmpresas continuam na mesma ordem, com13novos comandos (total393). SmokeHTTP conserva os79entrypoints anteriores e acrescenta osdoiscomponentes. Osinventários reais foram regenerados, permanecendo operational_ready=false; inventário lexical não certifica isolamento universal.

Consulte pr45-validation-446.md e evidence/resume446-*. Nenhum merge, deploy, tag, forcepush, envio bancário ou chamada real de cobrança foi realizado.

## Continuação ainda necessária

LOCAL sem caixa/restauração/fundos posteriores é reimplementaçãoV3 independente ainda em validação. CNAB240tarifa possui pacote reconstruído, porém revisão encontrou confirmação incompleta de fontes/auditoria e exige correção antes de integração. API18/19 e CNAB400financeiro permanecem com contratos e software específicos discriminados em banco-brasil-remaining-contracts.md. Os demais consumidores/settings não adaptados continuam gates fechados; este incremento não reduz o contrato integral.
