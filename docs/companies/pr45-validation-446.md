# Validação da composição446

Resultados locais da composição congelada; oSHA publicado eCI atual são registrados naPR45, sem transferir aprovações históricas. Núcleo4.4.6/446NOOP, BB122 eAsaas153inalterados.

## CLI e banco

PHP8.3.6, MySQL8.0.46 eMariaDB10.11.14 descartáveis reais, binlogON, usuário nãoSUPER e dois waiters independentes observados nos testes de concorrência. Execução FINAL SERIAL:17comandos por motor, MySQL177,52s/Maria112,17s, todos exit0,18.558arquivos produtivos sem alterações. Arquivo evidence/resume446-final-composition.json contém comando, footer, duração, SHA do log e manifestodafonte; contadores cumulativos não devem ser somados.

- Defaults: preferências, consumidores/API, controllers, cópias e concorrência (oito casos).
- Calendário: preferências, leitores, controller, fronteiras e concorrência (seis casos).
- Native446: body2748checks cumulativos; App real1479, marcador445 obtido da execução nativa445, falha/ignore/readback/retry/replay.
- Regressões: operações/subrecursos244, ticketpreferencesreaders78, router29, SalesFK218/22variantes eSQLiteBB70.
-143testesPython passaram em36,128s; teste de bootstrap deixa de exigir App_Router vazio e conserva os guards da ordem global. O comportamento é comprovado pelos29testes reais de roteamento.
-44checks estáticos do conjunto inicial passaram. A correção adicional do teste nativoBB recebeu lint próprio antes da publicação.

## Reparo adicional somente de teste BB

O workflowBB do61efacb9 falhou nojob118685463683/run39439546503, filho deupgrade120→122. O logCI ocultava stderr dofilho. Reprodução diagnóstica MySQL8.0.46 mostrou linhas iguais e divergência exclusivamente textual SHOWCREATE nosquatrocamposalterados, comCHARSET redundante. LogsRED preservados em evidence/ci445-native-module-*. A correção normaliza apenas essa redundância e compara também todos os campos reais information_schema.COLUMNS. As rotas120/121→122, tracking/replay/NOOP/histórico passaram em ambos motores (evidence/resume446-bb-native-results.json). Não houve mudança em módulo/runner/migration por esse reparo.

## HTTP/Chromium

Instalação nativa fresca446/master17 e upgrade real445→446/master17 passaram nas quatro suítes completas de registry, Task, Calendar eACL restritaB. Tarefas:32,601s/30,988s; calendário:77,647s/79,348s. NativeinstallerCSRF403antesDDL, upgradeCSRFválido2,224s e286tabelashistóricas verificadas porlinha, incluindo deltas nativos deactivity/audits/options documentados porbinlog. Probe final assertivo A/B/principal200. Runtime18.544arquivosmais14arquivosdeinstallerremovidos, semdiferençaprodutiva. Ver [relatórioHTTP](validation-446-http.md) e[evidênciaJSON](evidence/http-resume446.json).

As tentativas anteriores com503não reproduzido, probe não assertivo eHTTP500comSIGSEGVdocumentado doMariaDB permanecem preservadas e qualificadas no relatório; não são apresentadas como aprovações. O harness final isola tmpdir porcluster ewebroot fora daárvore sincronizada. A hipótese decolisão entretemporários não é declaradacausa comprovada de todos osREDs. Nenhumaasserçãofoiretirada nempreferênciazeradaporSQL.

## SHA445 já publicado

O pai0a10a15022ac8a93c75b3b557253ffc508f4d8a2 foi testado depois da publicação: SalesFK218/22variantes, SQLiteBB70real e native4452349/App1464 em ambos motores,19.681arquivos doGit semdrift. Evidence/published445-inventory-source-bound-tests.json. CI remoto desseSHA continua independente. REDs genéricos Maria de suítes mais amplas no61efacb9 foram preservados e não reclassificados como sucesso pela prova específica do0a10.

## Limites

Não há homologaçãoBB/Asaas, e-mail externo, certificados reais ou movimento bancário. A inspeção lexical e a aprovação de testes delimitados não certificam todos os consumidores legados. Pendências técnicas e insumos bancários exatos continuam no status e nos contratos.
