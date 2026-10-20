# Descrição histórica da PR45 no HEAD446

Captura integral antes da atualização447. Datas, CI, versões e pendências abaixo são históricos; o estado vigente está na descrição atual da PR45 e nos relatórios do incremento publicado.

# PR45 — operação multiempresa e integrações por origem

## Branch e título
`feat/banco-brasil-billing-cnab240` → `main`.
**feat(crm): amplia operação multiempresa e integrações por origem**

## Contexto e objetivo
Retomar a partir do remoto436 aprovado, reconciliar o trabalho437–440 recuperado e entregar fluxos próprios por empresa com writers canônicos, migrations nativas e provas atuais. O contrato integral continua aberto.

## Estado publicado
- **HEAD446:** `b47d1666f7bb788bf29ead9c9aa03ff3f36221d1`, árvore `5a49b5b731abbce3b18581badd19cc6aac9c3d90`, pai `0a10a15022ac8a93c75b3b557253ffc508f4d8a2`.
- **445:** `61efacb959d589f04a3a3f3ff66e8591a2b3c90f` publicou os dois reparos de fixtures CI444; follow-up `0a10a15022ac8a93c75b3b557253ffc508f4d8a2` atualizou exclusivamente o inventário gerado.
- **444:** `004c334c451707e19abcd68bf66a793f547116fb`, árvore `80e0359ea53e55e62c2975fc4d64ff7953db2950`, pai443 abaixo.
- **443:** `57a80667e47e203bcb4e388fa3670824bb4bb4e1`, árvore `2ca6d80af0679b76dd40c135d21b3790091e68f3`, pai442 abaixo.
- **442:** `7dd715fc371c66d4c95ebc342d02c4391b50869f`, árvore `1b0c3e82c3a0dab3c38bbe932603a0f5dad0dbaf`, pai441 abaixo.
- **441:** `132d0f732821396ca1feb12a805abe066bda253d`, árvore `263c12825ea68c25930918d141fbc676e9fa4259`, pai440 abaixo.
- **440:** `77ce06ccc977e7c9410f252ca71c7b06d34279e3`, árvore `b894b6b5f139cbc7facd67b018b7603aae532caf`.
- **439:** `b9d7a2f74881e7f0faf01aa4b9f8e0365309a148`, pai remoto anterior `e3ffef4a66231562d232d0e6b545c49898d0f548`.
- Núcleo **4.4.6/446**, BB **1.2.2/122** (mínimo4.4.3), Asaas **1.5.3/153**. Main `aa58b832b3c5ed04a599caacba125b056c081ba7` preservada. CAS expected0a10, sem force; HEAD remoto confirmado.
- **CI444 terminal: 16 workflows, 14 sucessos e2 falhas.** Empresas: schema `asaas_test` ausente. BB: negativo SQLite herdando DSN SQL. Ambos reparados445 com RED→GREEN e cobertura MySQL/MariaDB/SQLite preservada.
- **CI61efacb9:** identificou inventário desatualizado (corrigido0a10) e nova falha do filho BB nativo no MySQL. Diagnóstico reproduziu linhas históricas iguais e diferença de representação de charset no SHOW CREATE após a injeção de collation. **446 corrige somente esse teste**, mantém todos os guards e acrescenta comparação exata de todas as colunas information_schema.
- **CI0a10:** última consulta tinha13 sucessos/3 em execução; provas pós-publicação das três suítes-alvo passaram nos dois motores com19.681arquivos Git intactos. Não se transfere aprovação histórica para446.
- **CI446:** 16 workflows distintos iniciados; última consulta1 aprovado e15 em execução/fila. **18 suítes pós-publicação por motor passaram**, ligadas aos19.725arquivos exatos do commit, sem qualquer drift. Gates core/módulos doSHA completo passaram antes de mover a branch.
- Sem merge, deploy, force-push, tag, release manual, transmissão bancária real ou mudança em outro repositório. O reparo deCI436 já resolvido não foi repetido.

## Escopo incluído
**446:** defaults próprios de criação de tarefas (estado e autoatribuição), incluindo principal, formas eAPI existente; calendário privado com16preferências, sete fontes canônicas, cinco visualizações, ACL antes deIDs/contagens e reconfirmação após render; cadastro nativo de Empresas com29testesCI/MX eGUI master17/A/B/principal. A correção adicional do teste BB preserva dados/metadados/guards, sem alterar módulo ou runner.

Empresas/master/equipe/grants/defaults e contexto obsoleto; clientes privados/compartilhamento explícito/contatos/portal; faturas, créditos, pagamentos, arquivos e relatórios próprios; projetos/tarefas/despesas/contratos/tickets e APIs adaptadas anteriormente.

437–439 acrescentam discussões privadas de projeto, faturamento avulso/lotes de horas, recorrência de faturas, cópias de fatura, API financeira, refund integral declaradoAsaas e novo recebimento manual posterior, MicrosoftSMTP/OIDC, ConnectAPI/pareamento/filas e Pusher privado.

440 acrescenta:
- Rascunhos revisáveis e emissão explícita, com API opt-in e nenhum número antes de emitir.
- Recorrência fatura/rascunho, condição explícita de fatura principal quitada e descoberta durável justa sob limites. Principal não significa automaticamente filho do ciclo anterior.
- Cópias revisadas de projeto/tarefa, sem duplicar tempo, arquivos, publicação, caixa ou reivindicação de faturamento.
- Reversão LOCAL do novo manual pós-refund, cancelamento posterior separado e nova emissão independente.
- Avisos de suporte por Connect com política por empresa e fila durável.
- BB119 abatimento/endereço/primeiro desconto/reversão integralAPI16 declarada; BB120 janela explícita após vencimento e ciclo de alteração/cancelamento/primeira atribuição do desconto.

441 acrescenta aAPI privada de despesas/categorias/arquivos: sete scopes opt-in, writers canônicos, revisão/idempotência, isolamento por empresa e confirmação final dos bytes após auditoria. Migration441NOOP; não altera histórico. CI441 terminal14/2 conforme acima.

442 acrescenta cópia revisada de despesas (diário canônico e IDs de driver normalizados), configuração/consentimento/outbox/dispatcherAI próprios por empresa e PATCH/consulta confirmatória do SeuNúmeroBB. Últimas fronteiras após auditoria, privacidade, estados uncertain e nenhum retry cego foram validados. BB121 valida quatro schemas sem DDL;442 acrescenta duas tabelas e um índice no pai.

443 acrescenta edição revisada e cópia independente de orçamentos/propostas, recibos/numeração/tributos/PDF canônicos e duas tabelas nativas. Inclui validação exata de FKs V4 após o REDnativo que identificouconstraintCASCADEextra. BB122 acrescenta importação **observacional** de auxiliares4005/01e5/10 sobperfilpróprio declarado, pai/bindingoriginais preservados, deduplicação semântica e texto bruto escapado. Não habilita financeiro/remessa400. As duas GUIs passaram nas basesfresh eupgrade442→443.

444 acrescenta a ordem de respostas de tickets por empresa (`asc|desc`), revisão e confirmação após auditoria, consumida no suporte administrativo e portal privado. Principal usa sua configuração própria; ausência conservaASC semherdar global. Todasas opções globais e cinco tabelasfinanceiras ficaram iguais durante as operações testadas. Migration444NOOP; BB122/Asaas153 inalterados.

445 acrescenta somente os dois reparos de fixtures e o NOOP oficial445. Preserva todos os379 comandos anteriores de Empresas e acrescenta o teste real do runner445 (2349checks +1464no subprocessoApp em MySQL/MariaDB). Sem alteração funcional de módulos, schemas ou dados.

## Fora do resultado publicado e pendências reais
Ledger LOCAL sem caixa, restauração e recebimento/crédito posterior estão na nova implementação V3, ainda local e em validação. O V2 perdido não foi recuperado por hashes: scripts, contratos e logs sobreviventes foram preservados, e a reconstrução é identificada explicitamente.

Tarefas e calendário foram reconciliados e publicados446. Permanecem técnicos: prioridade/seguidores automáticos/regras adicionais de tarefas e consumidores/settings ainda sem adaptação própria. Não se apresenta o inventário lexical como certificado de isolamento universal.

A conciliação CNAB240 com tarifa está em revisão2 local. A revisão independente encontrou três falhas reais de confirmação da fonte/auditoria no pacote anterior; a correção e nova matriz estão em curso e não integram446. IOF/outros, reversões parciais e demais comandos ainda requerem adapters/estados/provas próprios.

API18/19 precisa de **identificador estável por crédito** e definição **individual/cumulativa de valorCreditoCedente**, além de componentes/datas/reversões; não calcular delta presumido entre consultas.

Financeiro/outbound CNAB400 segue indisponível. Faltam os documentos aplicáveis à conta/convênio/carteira/variação:
1. Código BRL em **CBR641 posições85–86**.
2. Isenção inequívoca ou **parâmetros de juros herdados**.
3. Errata/explicação do **CBR643 posições26–39:14 posições versus9(013)v99**.

O trailerCBR641 já foi resolvido. Auxiliares observacionais443 não equivalem à vertical financeira400. Mesmo com os insumos, código operacional e provas400 ainda serão necessários.

## Recuperação e decisões técnicas
O snapshot anterior candidate445 foi preservado integralmente (19.743arquivos). Seus defaults/calendário foram reconciliados no446 após o445 ser reservado primeiro aos reparos de CI. O roteamento de Empresas agora reserva admin/issuers ao controller nativo; MX não despacha o módulo arquivado. Não houve require artificial no menu.

A produção439S1 foi recuperada por manifesto.154 testes novos não publicados não sobreviveram; os testes `resume_439` são reconstruções explícitas e não equivalência alegada ao snapshotS4 perdido. Migrations e comandos publicados foram preservados. Patches440 foram compostos aditivamente, com um escritor por arquivo e provas por domínio e composição.

Novas correções verificadas: rejeição de envelope Connect não criptografado; contrato canônico de linhas no formulário/PDF de vendas; checkbox de recorrência e justiça da descoberta limitada. Rascunhos/recibos/outbox/auditoria usam origem tipada e transações; permissões, ownership e NULL legados não são adotados automaticamente.

## Alterações e arquivos
**Adicionados:** migrations437–446, BB117–122, Asaas153; serviços/schemas/controllers/views próprios dos componentes; testes recompostos e provas em `docs/companies/evidence/`.

**Modificados:** `CompanyInvoiceRecurring*`, `CompanySales*`, `CompanyCreditWriter`, `CompanyInvoiceBalance`, APIs/scopes/rotas finitas e catálogos; serviços/controlador/viewsBB; configurações/transportes próprios; metadados de versão/distribuição; workflows e smoke com comandos adicionais.

**Removidos:** nenhuma funcionalidade ou migration histórica foi removida. O teste histórico controller439 permanece junto da variante440.

## Banco de dados
445 e446 são NOOPs nativas oficiais. Tarefas/calendário persistem JSON revisionado próprio no registro da empresa, semDDL/backfill nem grants automáticos. As656migrations históricas do pai foram preservadas byte/modo. BB122 eAsaas153 permanecem intocados446.

437/438/439 acrescentam11/15/12 tabelas.440 acrescenta exatamente11 por oito helpers:Drafts2, RecurringModes1, RecurringDrafts1, RecurringDiscovery1, ProjectCopies2, TaskCopies1, ManualReversal1, TicketConnect2. O protótipo antigo de16 tabelas não foi apresentado como composição completa.

DDL InnoDB, metadados/índices/FKs/charset conferidos, sem backfill de autorização, visibilidade, segredo, autoria ou caixa. 441 é NOOP nativa e preserva todos os schemas anteriores. BB120 e121 são NOOP nativas; Asaas153 permanece.442 cria exatamente company_expense_copies e company_ai_requests, mais o índice único expense_copy_parent em company_expenses(issuer_id,id). Sem adoção/backfill.443 cria company_sales_revisions ecompany_sales_copy_requests com5FKs exatas;BB122 éNOOP devalidação dos4participantes existentes, semnovoDDL. 444 éNOOP semDDL/DML eusaJSONexistente; não cria tabela/coluna/backfill. Os downs conservam evidências; rollback de dados não é automaticamente reversível.

## APIs, contratos e integrações
Bearer próprio com empresa/ator/scopes explícitos; cookies/sessão global não ampliam autorização. JSON/base64, quotas, pai/filho e bytes privados são validados. Uso do token confirma na transação do writer. Retries conservam recibos e conflitos; estados sending/uncertain não autorizam reenvio cego.

Asaas/BB mantêm conta/ambiente/origem/snapshot e confirmação posterior. PATCH ou perfil salvo não confirma operação nem altera o fiscal. Declarações locais não autenticam transferência/refund/estorno externo. CNAB240 mantém geração/download/envio manual declarado e conciliação assistida. WebhookmTLS observacional e protocolos locais não são homologação.

## Dependências e configurações
Núcleo/módulos/distribuição usam o gerador oficial e versões acima. Dependências Microsoft/Pusher estão fixadas e documentadas nos manifestos correspondentes. Configurar cada conta/empresa/ambiente e ativar somente capacidades contratadas; segredos por canal permanecem criptografados ou em hash.

O smoke usa credenciais sintéticas e CA/certificado gerados emruntime. ProxyTLS de teste aceita apenas sandbox sintético e não encaminha tráfego externo; nada disso altera a configuração produtiva.

## Segurança, privacidade, desempenho e observabilidade
Autorização antes de hidratação/bytes/efeitos e novamente após locks/auditoria/processamento. Grants e revogação concorrente, rollback e readback são testados. Paginação/quotas/lotes limitados e cursor durável evitam crescimento ou starvation sem controlar saldo por sessão. Logs/diagnósticos não carregam segredos; recibos e auditorias preservam proveniência.

Inventários são lexicais e continuam com consumidores não adaptados bloqueados; contagens não representam bugs nem cobertura universal. Locks, retenção e volume exigem avaliação no ambiente de implantação.

## Testes implementados e executados
**446 pré-publicação:**17suítes finais por motor MySQL8.0.46 eMariaDB10.11.14, execução serial (177,52s/112,17s),18.558arquivos semdrift;143Python36,128s;45checks estáticos. Native446body2748/App1479; cinco suítes Task e cinco Calendar, oito/seis corridas comdoiswaiters; router29 e regressõespertinentes. BBnativo instalação122/upgrades120 e121→122 passou nos dois motores após o reparo do teste.

**HTTP446:** instalação fresca e upgrade nativo real445→446, master17; registry/Task/Calendar/ACL restrita completos exit0; Task32,601s/30,988s, Calendar77,647s/79,348s.21fontesGUI/16campos/cinco visões, CSRF/revisão/contexto/revogação, witnesses reais e rollback/retry, opções e finanças intactas.286tabelashistóricas comparadas porlinha, deltas nativos documentados com binlog; probe final assertivoA/B/principal200.18.544runtime+14installerremovidos semdrift. REDs anteriores preservados, incluindo503 não reproduzido e crashMaria documentado; não atribuídos sem prova a quota ou concorrência. Harnessfinal tmpdirprivado/webrootefêmera. Relatório `validation-446-http.md` e `evidence/http-resume446.json`.

**446 pós-publicação:**18comandos/motor PASS MySQL8.0.46 eMariaDB10.11.14 contraSHA b47d1666f7bb788bf29ead9c9aa03ff3f36221d1/árvore5a49b5b731abbce3b18581badd19cc6aac9c3d90.19.725arquivos Git/modes ligados antes e depois, zero drift. ResultadosSHA256 MySQL948980a045f186639ad3e84db84a79b403902286222f3442059a463a1eb3a7ec eMaria bcb9dc9e7b042349969a5d10b30c0231cea498f5f6150a780a2365a67521049c. CI remoto continua independente e ainda não é anunciado como16/16.

PHP8.3.6, SQLite, MariaDB10.11.14/InnoDB/binlogON/usuáriosemSUPER e dois waiters reais por cenário; Chromium140/TCPDF/Poppler.

439:143 Python,266 lints e359/360 caminhos PHP publicados passaram localmente após reexecuções documentadas. O restante, writerAsaas específicoMySQL8, **passou no SHA439 remoto** com34 asserts e lock real de dois processos; também76native153 e46callback. Native439903 cumulativos e tracking7; HTTP instalação439 e upgrade436→439, A/B/principal/portal/recorrência/settings/operações/BB240,18.471 arquivosruntime sem diferenças.

440:143 Python e178 lints; nativebody1077 cumulativos/tracking9,11 tabelas exatas, falhasDDL/marker/metadados/retry/replay e histórico preservado. Provas compostas:Drafts7Maria/5SQLite; recorrência8Maria/6SQLite; operações25execuções; settings22Maria/12SQLite; financeiro15Maria; BBcontrato/SQL/controller/races e guards119sob120.

HTTP440: instalação e upgrade439→440 reais; todos sete novos fluxos gráficos e regressões pertinentes aprovados.18.508 arquivosruntime iguais ao congelamento18.522. POSTreview pós-refund usou cliente produtivo, TLS1.3/CA/SAN verificados e quatro GETsA/B no sink local; CA ausente negada antes de GET. Sem operação externa. O smoke mantém65 comandos439 e acrescenta sete440.

Contadores cumulativos não são somados. Falhas de autoria/infraestrutura e reexecuções são discriminadas nos relatórios. CI de cada SHA permanece separado dos testes locais.

441:143Python e39lintPHP/Node/shell finais; nativebody1251 cumulativos, executor203 e helpers históricos439/4408/10. Onze execuções do componente composto, reader65SQLite/Maria e corridas com sete cenários/dois waiters. HTTP instalação441/upgrade440→441 e API de despesas em ambos, dez baselines440 e recorrência no banco preservado. Primeira ativaçãoAsaas0→153 nativa, seis suítes originaisAsaas e ciclo pós-refundPOST/TLS reais passaram.18.512 hashesruntime preservados, fonte produtivo18.526. Smoke73comandos conserva72anteriores. Os gates de versão/migrations passaram no SHA132d0f7 exato antes da publicação. Detalhes em pr45-validation-441 e evidências; o loteDocker integral depende deCI.

442:143Python,81lints finais e12execuções compostas nofreeze18539arquivos/SHA25647b35c58b8c2f916d0ef1e8d4aebe0a454494bd77041f68c01f079f03d13143b, semmudanças de fonte. Nativebody1430/executor195; ativação/upgrade/replayBB12192; Expenseeffects163/types171/races138, AIfinal157/races34/transportTLS e BBactivity/window. HTTPfinal: instalação442/upgrade441→442 reais,15tabelashistóricasbyteiguais e18.525runtimehashesintactos; três novas GUIs nasduasbases, Ops439completo53.33s, baselinesAsaas/API16/CNAB240/ciclopósrefundTLS. Smoke76comandos preserva73anteriores. Gatescore/módulosPASS noSHA7dd715f exato antes de moverbranch. LoteCLI360 encerrado com360comandosPASS; doisarquivosmudaramduranteexecução, portanto não equivale aumsófreeze. A qualificação e asrepetiçõesestãodescritasabaixo. Detalhes pr45-validation-442 epr45-recovery-442.

443:143Python e52lints finais,18546arquivosprodutivos/freezead7e932e5efcd5479a90c31773ad9fbb43a514d0dd610f8aa3caba4b292fb15f.15execuções finais compostas eBBnative122separadoPASS/zeroalterações; body4431626 eexecutor202; BBinstalaçãofresh122 e120/121→122reais50+69+69. SalesV4isolado15SQLite16Maria/composto15SQLite18Maria,54auditvariants517,8races2waiters278; matrizBB400V2 e6REDs independentescorrigidos. BBcompat14unitários+4SQL174/360/457/504PASS. HTTPfresh443+upgrade442→443,21tabelashistóricasbytesiguais/18532runtimehashesintactos; Sales92.28/90.56s e400aux28.36/28.48s, actorrestritoB/CSRF/revisão/rollbackreal/PDF/HTMLsemrequests; ciclosfinanceirosTLS32.13/31.47s. Smoke78preserva76anteriores;issuer374preserva361;BB110preserva100. Gatescore/módulosPASS noSHA57a8066 exato antesCAS. Não homologação nem operação externa.

Lote442encerrado:360/360comandosPASS, masexit1naasserçãodefontepois2arquivosmudaramduranteexecução; qualificação explícita emlocal442-360-qualified.json.12suítesafetadasrepetidasapóspublicação7dd715fPASS/Gitblobbindprod18539+entrypointsintactos. Sematribuir360aumsófreeze.

444:143Python(39.648s),17lints finais e5suítes compostas PASS nafonte18548/freeze7d9a5f3eae930bd609d66aa3ec6073154cd7f79a53c790481f3366139865efba, zeroalteração. Nativebody1992/App1464, settings187/readers78/races205(3casos2waiters); revisãoindependente35adicionaisSQLite/Maria. HTTPfresh42.01s/upgrade443→44456.32s,27tabelashistóricasbyteiguais/18534runtimehashesiguais. A/B/principal+StaffBGUI/revogaçãoatual/portalordem+privacidade,6lateauditscomwitnessreal/503rollback/retry,CSRF403/enum422/stalerevision409. O primeiro GET legadoSettings inicializa104/134 defaultsdegateway;binlog/reconstruçãoreadonlyprovamnenhumaopçãopreexistentealterada. RoteirofinalaqueceGETantesreferênciaecomparaTODASopçõessemexclusões. Smoke79preserva78;issuer379preserva374;BB110inalterado. Gatescore/módulosPASS noSHA004c334 exato antesCAS.

443pós-publicação:7suítescríticasrepetidascontraGitblobSHA57a8066PASS, fonte18546+entrypointssemalteração; evidência no444. Nenhumresultadoanteriorfoisubstituídoporestecheck.

Provas novas445: nativeNOOP/replay/App marker failed/ignored/readback/retry em MySQL8.0.46 eMaria10.11.14; installerbootstrap10/abort;143Python33.369s; quatroPHP lints; semSUPER e semnovosgrantsnoCI. Evidências `docs/companies/evidence/local-ci445-results.json`, `ci444-sales-cross-schema-results.json`; relatórios `pr45-recovery-445.md` e `pr45-validation-445-ci.md`. Gates dos commits61efacb9 e0a10a150 aprovados. Pós-publicação61: cinco suítesMySQL passaram com19681arquivos ligados aGit; duasfixtures-alvo também passaramMaria. Uma regressãoBBMaria falhou genericamente em uma execução, sem causa reproduzida (duasreprisesintactas e trêsdiagnósticas passaram699); oRED original permanece registrado, não classificado como corrigido. CI remoto e provas doHEADatual são acompanhados sem antecipar conclusão.

## Testes recomendados e validação manual
Acompanhar os16 workflows do SHA final, inclusive MySQL8/PHP8.1/8.5 e BBMySQL8.4/MariaDB11.4. Em A/B/principal/restrito: criar/revisar/copiar/converter, emitir rascunho, pagar principal e processar série, conferir PDF/CSV/diário, arquivos privados, retry, contexto obsoleto e revogação concorrente. Conferir que fontes canceladas/faturadas não são cobradas de novo. Homologação externa somente com autorização/contrato/casos e evidência específicos, sem segredos naPR.

## Compatibilidade, impactos e riscos
Históricos, snapshots e semânticaNULL preservados nos cenários executados. Nenhuma quebra intencional de fluxos adaptados; consumidores sem adaptação positiva continuam bloqueados. Financeiro e filas têm risco de concorrência/retomada e exigem preservar intenções/recibos, sem corrigir estado manualmente ou criar crédito fictício.

## Deploy futuro e rollback
Nenhum deploy nesta tarefa. Em aprovação futura: backup verificado de banco/arquivos/configuração; pacote canônico e versões contíguas; migrations pelo runner nativo; readback de tracking/metadados; smokeA/B e habilitação controlada.

Em falha: suspender operações/workers, preservar evidências e restaurar código+backup de forma coordenada. Não executar downgrade destrutivo, apagar recibos ou reutilizar NossoNúmero. Rollback contábil não é mero retorno de arquivoPHP.

## Build, release e distribuição
Fluxo normal de release preservado. Gates de núcleo/módulos passaram nos SHAs completos439,440,441,442,443 e444 antes da publicação; CI de builds/containers é conferido por SHA. Sem skip-release, mudança emdevelop, tag ou release manual.

## Documentação
Adicionados `pr45-recovery-446.md`, `pr45-validation-446.md`, `validation-446-http.md`, `company-calendar.md`, `task-creation-preferences.md` e evidências446; status e três inventários reais atualizados conservando operational_ready=false.

`docs/companies/pr45-status.md`, `pr45-recovery-439.md`, `pr45-validation-439.md`, `pr45-recovery-440.md`, `pr45-validation-440.md`, `pr45-recovery-441.md`, `pr45-validation-441.md`, `pr45-recovery-442.md`, `pr45-validation-442.md`, `pr45-recovery-443.md`, `pr45-validation-443.md`, `pr45-recovery-444.md`, `pr45-validation-444.md`, respectivas evidências, guias dos componentes e `docs/banco-brasil-remaining-contracts.md`. Contrato original preservado em `docs/companies/requirements/`. Resultados e limitações anteriores permanecem nos relatórios por incremento.

## Checklist
- [x]439,440,441,442,443 e444 publicados na mesma branch; árvores e pais conferidos.
- [x]Versões e migrations contíguas; históricos preservados.
- [x]Provas locais de código/SQL/concorrência/instalação/upgrade/HTTP440/441/442/443/444.
- [x]Testes e pendências documentados; APIs/configuração/segurança e rollback descritos.
- [ ]CI terminal dos16 workflows doSHA444 em acompanhamento;443BBunit8.1/8.5PASS;439/440/441 terminais14/2,442falhaBBregistrada e2longosemacompanhamento.
- [ ]Contrato integral e demais consumidores/settings/componentes.
- [ ]Insumos bancários específicos e homologação externa autorizada.
- [ ]Merge/deploy — não executar nesta tarefa.

## Commits, merge e versão
Commits efetivos439–446 listados acima. HEAD publicado b47d1666f7bb788bf29ead9c9aa03ff3f36221d1: núcleo4.4.6/446, BB1.2.2/122, Asaas1.5.3/153. Novas frentes somente após integração/prova dos próprios fontes. Merge e deploy não autorizados e não realizados.
