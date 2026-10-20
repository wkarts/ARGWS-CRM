# Validação450 — calendário com tarefas do portal e integridade dos cronômetros

Núcleo **4.5.0/450**, NOOP gerado pelo executor oficial após449. Pai remoto conferido `c93d0ae4ca4ace234349244843b42456416a5271`; BB **1.2.3/123** e Asaas **1.5.3/153** preservados. SHA publicado/checks ficam na PR45; esta prova local não transfere aprovações de449.

## Causa do calendário

O timeout antigo esperando table era consequência de uma resposta de erro, não um seletor inadequado. O artefato449 confirmou mensal422 em1,650s, pico2MiB e semOOM. A rejeição de domínio não constava desse artefato remoto. A reprodução local usa a própria sequência original: o browser de portal cria tarefas nativas com assignee_id=0, aguardando atribuição, e vencimento1/11. O calendário A inicia domingo e termina31/10; B inicia segunda e inclui1/11. Depois desse writer, A diária/mensal200 e B diária200/mensal422; o log seguro registra issuer_invalid_id. A leitura hydrate exigia ID positivo para o0canônico.

Somente project_tasks com0inteiro/string viram assignee interno null na leitura. Não muda a fonte, datas, permissões, filtros, only-assigned, seletor ou timeout. ID negativo e avulsa inválida continuam recusados; nenhum responsável fictício é criado. A regressão SQL cria pela API canônica de contato e verifica A/B/principal, first-day0/1, dia real, filtro por responsável, ator/empresa cruzados e invariância das fontes.28checks adicionais/87cumulativos por motor.

## Integridade dos timers

Os ensaios adversariais reais mostraram dois gaps no449: uma trigger inicial podia alterar prioridade antes de capturar o frame, e uma trigger final podia inserir outro timer ativo. O450 captura o frame antes doINSERT, compara a história completa de timers da tarefa e exige exatamente o único novo timer ativo do ator nas duas modalidades. Nove testemunhas SQL reais,36checks adicionais/334cumulativos, rollback exato e retry. Conserva política própria, exclusividade, autoridades e ausência de auto-stop.

## Validação executada

- 14suítes SQL por motor:MySQL8.0.46/MariaDB10.11.14, PHP8.3.6/128MiB, InnoDB/binlogON, usuário semSUPER. Calendário, ACL/fronteiras e seis corridas; Settings/timers, nove corridas×dois waiters; faults de integridade; App runner450; faturamento/timesheets/atomicidade; LOCALclock e recorrência canônica.18.583arquivos produtivos sem drift.
- Quatro comandos adicionais PHP8.1.2/128MiB emMariaDB: portal/calendário0, faults timers, concorrência e runner450. Não são o PHP8.1.34 doCI.
- Instalação nova450:14etapas HTTP/Chromium, original portal writer antes do calendário original completo, tarifa240, LOCAL/restauração/fundos/API/PDF/CSV, defaults, prioridade e timers.18.569arquivosruntime/18.583produção iguais antes/depois.
- Upgrade genuíno449→450 sobre o banco que reproduzia o erro: blobsGit449 conferidos, runnerHTTP/CSRF e315tabelas históricas preservadas, nenhuma tabela nova, BB123igual. Calendário corrigido e timersGUI aprovados sem reset ou alteração dos responsáveis0.
- 143testesPython em29,459s;14lintsPHP/CJS/shell; inventários regenerados, ainda operational_ready=false. Gates de versões/histórico são conferidos no commit final.

O primeiro snapshot privado falhou em UTF8 binário e passou a etiquetar os bytes inválidos embase64, sem substituição destrutiva. O novo helper precisava definir APPPATH antes da config nativa. O primeiro confronto de opções incluía metadados do login; a captura final começa após login, mantendo todas as opções e linhas financeiras no confronto. O CJS final foi reexecutado no fresh e upgrade. A instalação completa já passara com a versão anterior da captura; os hashes e resultados por etapa distinguem as tentativas.

Uma tentativa adicional de reabrir o bancoMySQL, após14suítes aprovadas e parada limpa, encontrou redo faltante; não é contada como testePHP8.1 nem como falha do produto. As provas e o banco foram arquivados porSHA256 antes de liberar espaço. OPHP8.1adicional foi executado noMariaDB íntegro. O harness local usa PHP-S/MariaDB e um adaptador restrito de comandosDocker; não equivale ao workflow real de containers.

## Continuidade e limites

447 publicou diárioLOCAL/restauração/crédito e tarifa;448 prioridade/acompanhamento;449 política de timer e relógioLOCAL;450 corrige calendário/integridade acima. Migrações publicadas são imutáveis. Auto-stop, recorrência operacional de tarefas e demais settings/consumidores ainda são pendências de código/provas, sem certificação universal de isolamento. Reversão da nova base tarifária, IOF/outros/parciais e comandos/adaptadores restantes também permanecem abertos.

Documentos indispensáveis: CBR641 posições85–86 para códigoBRL; comprovação de juros herdados da conta/convênio/carteira/variação ou isenção inequívoca; CBR64326–39,14posições versus9(013)v99; API18/19 comIDestável do crédito e semântica individual/acumulada de valorCreditoCedente, datas/componentes/reversões. Não há delta presumido, operação financeira real, homologação, merge ou deploy.

Evidências: `evidence/resume450-{sql,http}-current.json` e `evidence/resume450-root-causes.json`.
