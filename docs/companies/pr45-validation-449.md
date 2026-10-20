# Validação 449 — política própria de estado no início do cronômetro

Núcleo4.4.9/449 NOOP oficial, sobre448; BB1.2.3/123 e Asaas1.5.3/153 sem mudanças. O commit448 foi preparado com árvore4835994e16f91706e0d454119f987e7978ce9c38 e pai447c07a03a2. SHA remoto efetivamente publicado e checks são registrados na PR45, sem transferir aprovação entre commits.

## Contrato e código

`CompanyTaskTimerPreferences` mantém um único bool estrito em JSON próprio e revisão otimista, OFF por ausência inclusive na principal. `CompanyTaskTimerTransitions` sela o resultado conhecido do início através da última auditoria/recibo. Os dois writers canônicos atualizam estado e revisão na mesma transação do timer; Settings e view próprios usam ACL atual, contexto e CSRF existentes. GUI e bearer consomem o mesmo writer. Parada não conclui tarefa; não existe auto-stop, herança global ou operação bancária externa.

## SQL e verificações estáticas

Validação dos cronômetros: oito comandos por motor, MySQL8.0.46 e MariaDB10.11.14, PHP8.3.6/128MiB, InnoDB/binlogON e usuário semSUPER.19lintsPHP/CJS, bash-n e143testesPython(27,193s) aprovados. Produção congelada:18.582paths, sem drift antes/depois dos comandos.

- Settings/controller/view:43checks adicionais/177cumulativos; A/B/principal, enum estrito, overpost, stale409, falha de auditoria e três mutações finais reais (metadata, JSON, grant), com testemunha MyISAM, rollback exato e retry.
- Writers/GUI-adapters/bearer:72adicionais/298cumulativos; início/parada/recibo, status/revisão únicos, global1 ignorado, empresa cruzada com timer aberto, duas modalidades e dez falhas reais após auditoria com rollback exato.
- Concorrência:44adicionais/270cumulativos; nove cenários, cada um com dois waiters InnoDB observados, alteração da política confirmada durante a espera, revisão concorrente e revogação de ator/permissão/token.
- Runner nativo real:449executa sobre448genuíno; NOOP, marcador falho/ignorado e readback persistido, retry/replay e todas as linhas/metadados preservados.1521checks no executor449; as provas compostas anteriores448/447 não são somadas.
- Regressão:controllers de prioridade, cópias, calendário concorrente(seis cenários) e defaults anteriores(oito cenários) aprovados nos dois motores.

A primeira nova prova de metadata usava UPDATE do próprio registro auditado em triggerAFTER, inválido no MySQL; foi corrigida para alteração BEFORE de NEW.metadata_json e continuou exigindo erro de confirmação do serviço, não um erro do driver. Um ensaio de grant tinha usado o master de instalação; a fixture foi corrigida para colaborador restrito, conservando o comportamento do master. Dados/logs falhos foram preservados e as suítes finais passaram integralmente.

## HTTP e proveniência

O upgrade real448→449 validou os blobs do Git448 e o runner HTTP com CSRF válido e403semCSRF; preservou315tabelas históricas e não criou tabelas novas. BB123 ficou igual. A captura histórica é READ ONLY; transações e preferências são criadas pelo aplicativo. Uma tentativa do novo teste usou o seletor inexistente task-edit-status; a view mantém task-status, e o roteiro foi corrigido sem alterar a interface.

A instalação nova449 passou CompaniesGUI, configuração BB17, tarifa240, LOCAL/restauração/fundos/PDF/bearer/CSV, defaults anteriores e calendário completo; prioridade e cronômetros também passaram. A conferência final detectou que o arquivo do novo teste havia sido atualizado durante a execução. Essa tentativa não é usada como prova final do roteiro atualizado; o hash de produção permaneceu íntegro. A suíte atual foi congelada e retomada nas mesmas bases, sem reinstalação, SQL de reset, reexecução da migration ou remoção de linhas.

No upgrade, somente o STOP nativo concluiu o início que o seletor incorreto havia interrompido. A preferênciaON e o estadoEmandamento foram preservados, assim como o único timer; não se zerou configuração. A suíte atual verifica tanto ausênciaOFF quanto preferência própria já salva. Os resultados definitivos e a ligação de produção/teste constam da evidênciaHTTP deste incremento.

## Limites

Resultados locais não equivalem a CI do SHA publicado nem a homologação bancária. A causa da falha remota446 do calendário ainda depende dos diagnósticos preservados; aprovações locais não a demonstram. Auto-stop, recorrência operacional de tarefas, demais consumidores/settings, componentes/comandos/reversão da nova tarifa e os documentos específicos API18/19/CNAB400 permanecem abertos. Inventários lexicais continuam sem certificar isolamento universal. Nenhum merge, deploy, force-push ou operação financeira real.

A cópia versionada do MD recebeu somente a errata bancária expressamente solicitada: **CBR641, posições85–86**, **CBR643, posições26–39**, trailerCBR641; hash do original preservado no cabeçalho. A fotografia antiga de HEAD/versões/CI continua identificada como histórica.

## Falha LOCAL de relógio encontrada na passagem completa

Um segundo ensaio fresh449 falhou na restauraçãoLOCAL B: saldo permaneceu0em vez100; base/logs foram preservados. Foi identificado um defeito real independente: noncashRow capturava created_at e noncashAudit fazia outro gmdate; a leitura canônica exige igualdade. O teste novo local_noncash_clock_v3 instala triggerSQL com SLEEP1,1s e testemunha não transacional antes/depois, sem relógio falso. Declaração e restauração falharam de forma determinística no código anterior(company_local_noncash_review), com rollback de todas as linhas verificado.

A correção passa o horário já capturado do registro à auditoria do mesmo evento. Mantém igualdade estrita, fingerprints, fonte, permissões e equações; não amplia tolerância nem altera valores ou histórico. Os dois casos passaram emMariaDB/MySQL com19checks adicionais/241cumulativos, mostrando avanço real de segundo, igualdade e retry. A composição449 foi congelada novamente depois do reparo; a regressão LOCAL completa e as provas HTTP finais são registradas separadamente.

CI447 revelou mensal422 e diária200 no mesmo contexto; ver pr45-calendar-ci447.md e evidence/calendar-ci447.json. A causa exata do domínio ainda não está confirmada. O smoke449 ativa o logger somente no banco sintético para preservar o código de rejeição; não modifica permissões, filtros ou timeout.

Validação HTTP definitiva após a correção de relógio: **fresh449 completo PASS**, com12etapasincluindo instalador, tarifa, LOCAL, defaults, calendário, prioridade e timers,18.568arquivosruntime e18.582deprodução iguais antes/depois, testes congelados. **Upgrade real448→449 PASS**,315tabelas preservadas, nenhuma nova, BB123semalteração e suíte gráfica de timers. O banco448 foi recuperado de arquivo físico verificado porSHA256, sem semear marcador ou schema. Evidência: evidence/resume449-http-current.json; RED→GREEN do relógio: evidence/resume449-clock.json.21lintsPHP/CJS e guarda do logger allow/deny também passaram.

**Composição final congelada:**15comandos SQL por motor passaram depois da correção do relógio, sem drift nos18.582paths. Incluem sete suítesLOCAL/clock/schema/falhas/concorrência/leitores/recorrência/API e os oito comandos de cronômetros/regressão/runner. O manifesto final temSHA256`968bb3a5e02c7a36b0cd8a9a73375e8eac278a849abe6db3cd1804ddd2ef0b2f`; resultados em evidence/resume449-sql-current.json. Contadores de includes permanecem cumulativos e não são somados.
