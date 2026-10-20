## Atualização 449 — estado no início do cronômetro, por empresa

Núcleo **4.4.9/449** NOOP; BB **1.2.3/123** e Asaas **1.5.3/153** sem alteração desde447. `task_timers` próprio, default desligado inclusive na principal, transição A fazer→Em andamento na mesma operação de início em tarefas de projeto/avulsas, GUI e API. Parar mantém estado; retry não reaplica política. Configuração, autoridade, linha e timer reconfirmados após a última auditoria. Não modifica dinheiro ou opção global. A composição449 também corrige o horário duplicado da auditoriaLOCAL, com regressão determinística de travessia de segundo e validação estrita preservada.

448 acrescentou prioridade e acompanhamento próprios, recuperados e validados; 449 não os substitui. Ver `task-timer-settings.md`, `pr45-validation-449.md` e resultados por incremento. SHA e checks efetivos ficam na PR45; aprovação anterior não certifica o SHA seguinte. A reprodução remota447 confirmou mensalHTTP422, diária200, cerca de1,6s e2MiB; a rejeição de domínio exata ainda será investigada. Ver pr45-calendar-ci447.md.

**Contrato integral aberto:** auto-stop e outras regras de timers, recorrência operacional de tarefas, demais settings/consumidores, reversão da nova base tarifária e demais componentes/comandos bancários ainda exigem código/provas. Documentos API18/19 e CNAB400 e homologação real permanecem dependências externas específicas. Não se declara isolamento universal.

## Atualização 448 — prioridade e auto-follow próprios

Núcleo **4.4.8/448**, BB **1.2.3/123** e Asaas **1.5.3/153** preservados. Prioridade1–4, default próprio2, edição explícita e cópias revisadas; auto-follow opt-in somente do criador de tarefas de projeto, sem herdar seguidores e sem reinscrever após retry. Configuração própria inclusive na principal, duas tabelas auxiliares sem backfill, GUI e API existentes pelo mesmo writer. Ver `task-priority-settings.md` e `pr45-validation-448.md`.

O trabalho447 de diário LOCAL/restauração/fundos e tarifa já está publicado em c07a03a2. A falha remota446 do calendário continua sem causa reproduzida; fontes de diagnóstico foram preservadas. Timers e demais consumidores ainda requerem seus próprios contratos. SHA e CI efetivos são registrados na PR45, sem transferir resultados históricos.

## Atualização 447 — composição recuperada e validação por origem

Núcleo **4.4.7/447**, Banco do Brasil **1.2.3/123**, Asaas **1.5.3/153** preservado. Pai remoto conferido: `b47d1666f7bb788bf29ead9c9aa03ff3f36221d1`. Publicação e CI efetivos são registrados na PR45; os parágrafos anteriores sobre trabalho somente local são históricos e substituídos apenas para as frentes abaixo depois da confirmação do push.

| Requisito | Implementação | Evidências | Limite |
|---|---|---|---|
| Diário LOCAL sem caixa e restauração | CompanyLocalNoncash*, CompanyFinancialJournal, leitores canônicos | local_noncash_v3, faults, concurrency, readers, API e HTTP | Declaração local não autentica baixa ou estorno bancário; não fabrica pagamento ou crédito |
| Fundos depois da restauração | Pagamento manual e crédito nativos com referência independente | funds_v3, funds_faults, recurring, API read races e PDF/CSV | Reversões locais preservam os registros e não executam operação externa |
| Tarifa CNAB240 | ReviewedTariffReceipt, controller e interface de revisão | tariff SQL, 56 falhas finais, 23 mutações de replay e seis corridas | Integral nominal e tarifa positiva; outros ajustes zero; sem reversão dessa nova base |
| Memória do teste PHP8.1 | Liberação da trace SQL após a fase de 233 asserções | Controller com 255 asserções em 128 MiB, MySQL e MariaDB | Correção do harness; sem aumento de limite nem mudança no produto |
| Calendário CI | Resposta/status/URL, screenshot e logs preservados e redigidos | Corpo e asserções originais preservados, HTTP fresh e upgrade recuperados | A falha remota446 não foi reproduzida; diagnóstico não equivale a causa corrigida |

Detalhes: `pr45-recovery-447.md`, `pr45-resume-20261020.md`, `validation-447-http-final-v2.md`, evidências recuperadas e resultados atuais por motor. Prioridade/auto-follow seguem em candidato separado; timers, recorrência de tarefas, demais consumidores e componentes bancários ainda exigem implementação própria. CNAB400 e API18/19 continuam com os documentos exatos descritos em `banco-brasil-remaining-contracts.md`. Nenhuma homologação, merge ou implantação é declarada.

## Atualização446 — configurações, calendário e cadastro nativo

Conteúdo deste incremento: núcleo4.4.6/446NOOP, BB1.2.2/122 eAsaas1.5.3/153 intocados. Pai remoto0a10a15022ac8a93c75b3b557253ffc508f4d8a2. SHAs publicados eCI são registrados naPR45; nenhuma aprovação histórica substitui o resultado doHEAD efetivo. Esta atualização supera os estados locais/pendentes dessas três frentes nos parágrafos históricos abaixo.

| Requisito | Implementação/arquivo | Teste | Estado/limite |
|---|---|---|---|
| Defaults de tarefas por empresa | CompanyTaskPreferences/CreationDefaults; Settings; dois writers eAPI existente |5suítes porSQLmotor,8corridas×2waiters, ChromiumA/B/principal | Estado inicial eautoatribuição; prioridade/seguidores/timers adicionais continuam pendentes |
| Calendário por empresa | CompanyCalendarPreferences/Read/Sources/Presentation; controller/view |5suítes porSQLmotor,6corridas×2waiters,21fontes criadasGUI,16preferências,5visões, ACL/revogação | Somente7fontes canônicas privadas, sem legado/Google/reminders |
| Cadastro principal500 | App_Router::locate reserva admin/issuers nativo |29CI/MXchecks + registryGUI200A/B/principal/master17 | Módulo arquivado preservado, rota pública404 |
| Tracking446 | native_resume_446 eApp real | body2748/executor1479, ignored/failed/readback/retry/replay; upgradeHTTP445→446 | SemDDL/backfill; módulos intocados |
| CI BB nativo120/121→122 | teste snapshotSchema conserva metadata real e canonicaliza charset redundante | REDMySQL→GREENMySQL/Maria, todosguards anteriores preservados | Correção apenas do teste; não é o defeitoSQLite já resolvido445 |
| Recorrência publicada439/440 | CompanyInvoiceRecurring/Dates/Modes/Discovery, canonicalinvoice/draftwriter | Guias eprovas439/440 preservados; não repetidos como implementação ausente | day/week/month/year,0ilimitado limitado porinvocação, notificação opt-in, principalquitada,rascunho/retry |
| LOCAL sem caixa/restauração/fundos posteriores | finance-v3 novo, nãoV2recuperado | SQL/fault/races/API em validação independente | Local; não incluído446, não concluído |
| TarifaCNAB240 | pacote novo reconstruído em revisão |3REDs confirmados de fullsource/audit; correção eprovasnovas emcurso | Local; pacoteanterior não é publicável |
| API18/19 efinanceiro400 | banco-brasil-remaining-contracts.md | campos/documentos primários eadapters específicos | Pendentes; auxiliares400 observacionais443 não são financeiro400 |

Evidências e falhas qualificadas: [recuperação446](pr45-recovery-446.md) e[validação446](pr45-validation-446.md). Todos380comandosEmpresas e79smokes anteriores preservados. operational_ready dosinventários permanece false; não há declaração de isolamento universal ou de conclusão integral. Semmerge/deploy.

---

## Atualização445 — reparo de CI antes das próximas verticais

Core4.4.5/445 NOOP; BB1.2.2/122 eAsaas1.5.3/153 intocados. Dois defeitos de fixtures444 corrigidos: SalesV4 schema estrangeiro próprio e BBnegativo emSQLite real com restauração doenv. Evidências em pr45-recovery-445.md epr45-validation-445-ci.md. O status remoto do SHA publicado está naPR45; este documento não antecipa16aprovações.

| Requisito | Implementação/arquivo | Prova | Estado do incremento |
|---|---|---|---|
| CI Empresas1049 | tests/companies/sales_mutations_foreign_keys.php | RED→GREEN MySQL/Maria,22variantes;SQLite20 | Implementado445 |
| CI BB SQLite | tests/banco_brasil_own_number_sqlite.php | DSN herdado→SQLite70; positivoMySQL699 | Implementado445 |
| Trackingcore445 | migrations/445_version_445.php;tests/companies/native_ci_445.php | App real, failed/ignored/readback/retry/replay; histórico444igual | Implementado445 |
| Defaults tarefas/calendário | snapshot recuperado candidate445 | ProvasHTTP445 anteriores preservadas; precisa reconciliação446 | Local, fora deste commit445 |
| Registry principal500 | roteamento/bootstrap em investigação | logundefinedissuer_core_ready preservado | Pendente técnico |
| LOCAL sem caixa/restauração/fundos posteriores | scripts/contratoNoncash preservados | V2incompleto; fontepodada, novasprovasnecessárias | Reimplementação em curso |
| CNAB240 com tarifa | novo basis/fonte segregados | manualjun2026p31; matrizes emconstrução | Local, não publicado |
| IOF/outros/estornosparciais/API18–19/CNAB400financeiro | docs/banco-brasil-remaining-contracts.md | contratos eadapters pendentes | Não concluído |

# Incremento444: ordem de respostas por empresa

Pai publicado57a80667e47e203bcb4e388fa3670824bb4bb4e1(443). Fonte444núcleo4.4.4/NOOP, BB122 eAsaas153inalterados. OsseisarquivosprodutivosdeTicketpreferencesconservamoshashesdo componente revisado, native1992/executor1464, cinco execuções finais e143PythonPASS. Fonte18548/7d9a5f3eae930bd609d66aa3ec6073154cd7f79a53c790481f3366139865efba. HTTPfinalfresh42,01s/upgrade56,32sPASS,27tabelashistóricas e18534runtimehashesintactos no relatório próprio; publicação/CI associados naPR. Ver `pr45-recovery-444.md`.

# Incremento443: revisões de vendas e CNAB400 observacional

Pai publicado `7dd715fc371c66d4c95ebc342d02c4391b50869f`, núcleo4.4.2/442, BB121 eAsaas153. Doze testes repetidos contra o SHA publicado passaram, com18539 arquivos produtivos semalteração. CI442 em acompanhamento, sematribuir aele os16sucessos436.

Fonte443: SalesV4 eCNAB400auxV2, núcleo4.4.3/443, BB122/1.2.2 (mínimo4.4.3), Asaas153. Fonte18546/ad7e932e5efcd5479a90c31773ad9fbb43a514d0dd610f8aa3caba4b292fb15f; revisão/migrations/SQL nativos aprovados; validaçãoHTTP em fechamento no relatório próprio. A publicação e CI são associados externamente na descrição daPR, sem autorreferência no commit. Ver `pr45-recovery-443.md`.

# Matriz objetiva da PR45

## Composição442 — cópia de despesas, AI própria e referênciaBB

Núcleo4.4.2/442, BB1.2.1/121, Asaas1.5.3/153. Migration442 acrescenta exatamente duas tabelas (cópias de despesas e requestsAI) e um índice composto de pai; BB121 valida os schemas existentes semDDL. A cópia revisada usa o writer nativo e confirma recibos/diário após a última auditoria; AI por empresa oferece resumo/sugestão de chamado com configuração criptografada e resultado privado por solicitante; Seu NúmeroBB usa comando/outbox tipado e confirmação posterior por consulta de conta, sem alterar a fatura.

Fonte produtivo congelado:18.539arquivos, SHA25647b35c58b8c2f916d0ef1e8d4aebe0a454494bd77041f68c01f079f03d13143b. Nativebody1430/executor195,12execuções compostas finais e143Python passaram. A matriz própria de cópiasV3 passou14SQLite/16MariaDB; AIS4 passou7/8 e seis corridas com dois waiters; BBV2 tem sete suítes MariaDB, contrato/SQLite/render e revalida cada fronteira de I/O. Contadores são cumulativos, não somáveis. Instalação, upgrade e HTTP finais têm relatório próprio; o SHA publicado/CI devem ser conferidos na descrição atual daPR.

Não declarar contrato integral concluído: SalesV3 tem pacote e provas locais, ainda exige integração nativa/HTTP/publicação; perfilLOCALnão-caixaV1 foi rejeitado por revisão dirigida (última confirmação e ciclo posterior à restauração), com V2 em implementação. Outros consumidores/settings e comandos/perfis bancários continuam discriminados. Auxiliares400 observacionais estão em implementação separada; financeiro400 segue bloqueado por campos/documentos específicos, não apenas homologação. Consulte os guias [cópia de despesas](expense-copies.md), [AI](../COMPANY-AI-RECOVERY.md) e [Seu Número](../banco-brasil-own-number.md).

## Composição441 — API privada de despesas

Core4.4.1/441, BB120 e Asaas153. API de despesas/categorias/arquivos com sete scopes explícitos e writer nativo, revisão/idempotência/limites e confirmação tardia do download.11execuções compostas e instalação/upgrade/HTTP A/B passaram; native441NOOP/tracking/replay e143Python provados. Consulte [recuperação441](pr45-recovery-441.md) e [HTTP441](pr45-validation-441.md). Publicação eCI são vinculados aoSHA efetivo daPR. Demais componentes ainda em recuperação não são anunciados como concluídos.

## Retomada de20/10/2026 — composição439 recuperada e validada localmente

Remoto inicial confirmado `e3ffef4a66231562d232d0e6b545c49898d0f548`, núcleo4.3.6/436, BB1.1.6/116 e Asaas1.5.2/152. O CI desse SHA terminou com16workflows distintos aprovados; as falhas históricas abaixo não são a falha atual. A retomada não refez esse reparo deCI.

A composição439 recupera os passos437–439, BB117–119 e Asaas153. Todos os arquivos produtivos do congelamentoS1 foram conferidos por hash. Os154 testes novos não publicados que não sobreviveram foram explicitamente identificados; provas `resume_439` foram recompostas e executadas. Dois defeitos encontrados nesta retomada foram corrigidos: veto a envelope de token não criptografado noConnectAPI e normalização dos campos reais de itens no formulário compartilhado de vendas/recorrência. Não se atribuem os resultados antigos aos fontes corrigidos.

Consulte [recuperação439](pr45-recovery-439.md) para testes, instalação/tracking/replay, fonte congelada e limites. Até publicação eCI do próprio SHA, esta composição não é resultado remoto. Os patches440 são preservados em composições separadas; quitação/rascunhos, cópias de projetos/tarefas, ciclo financeiro pós-refund, notificações de suporte e novos parâmetrosBB exigem integração e prova próprias. CNAB400 continua indisponível pelos três insumos bancários específicos documentados. Código faltante não é homologação externa.

## Histórico publicado até436

Escopo integral em andamento. O incremento436 foi publicado no SHA
`9b114e9808ad92d02530977b0c67ffe09d2da1b1`: núcleo4.3.6/436,
BB1.1.6/101–116 e Asaas1.5.2/152. Seu primeiro CI encerrou16workflows:
12aprovados e4falhas de fixtures, registradas na [validação436](pr45-validation-436.md).
O alerta nativo que falhava no435 passou; a correção mantém a validação original.
O reparo subsequente corrige expectativas de versão, identidade do instalador,
contexto real da viewPDF e dados sintéticos de duração, sem alterar produção nem
migrations. O CI do reparo pertence ao seu próprioSHA; os resultados locais não
são apresentados como aprovação remota ou como conclusão integral daPR.
A [validação429](pr45-validation-429.md) registra30/30 execuções HTTP/Chromium
em uma rodada limpa, instalação/upgrade/tracking e bytes funcionais conferidos.
O CI428 terminou12sucesso/4falhas por bootstrap e orquestração, corrigidos429 com
RED/GREEN. CI429 terminou15sucesso/1falha de parser do teste, corrigida430. CI430 terminou15sucesso/1falha1419 na infraestrutura do MySQL descartável, corrigida434 com prova CLI/binlogON/nonSUPER. A [validação430](pr45-validation-430.md) registra31/31 HTTP; a [validação434](pr45-validation-434.md) registra35sucessos em36tentativas e todas as migrations431–434 reais. CI do novo SHA é acompanhado separadamente. O427 passou25/25 execuções HTTP numa rodada limpa e16 workflows distintos
concluíram com sucesso no próprio SHA; [evidência427](pr45-validation-427.md) preservada.
Todos os passos históricos permanecem preservados. Sem merge, deploy, tag ou release manual.

A prova425 está em [validação425](pr45-validation-425.md): fonte congelado,
instalação/upgrade nativos, aplicação real mysqli, MariaDB e Chromium. Os resultados
locais não equivalem às matrizes remotas de outro SHA nem a homologação bancária.
Inventários orientam revisão; contagens lexicais não medem bugs ou cobertura.

| Requisito | Implementação publicada436 | Prova e limite |
|---|---|---|
| Empresa principal/master/equipe | Identidade real do instalador, master diferente de1, wizard, grants/default por recurso e seletor409 | Bootstrap/upgrade/concorrência/HTTP; master já identificado é preservado sem novo provisionamento; somente identidade legada ainda ambígua exige resolução confiável |
| Clientes privados/opt-in | Diretório positivo, compartilhamento explícito somente da identidade, contatos e sessões próprios | A/B/principal/NULL, concorrência e navegador; não compartilha histórico financeiro |
| Settings/SMTP/templates | Identidade, data/hora, imagens, preferências e tipografia PDF, arquivos de projeto, templates, SMTP e GoogleOAuth próprios | Settings A/B/principal, HTMLPurifier, snapshots antigos e aba obsoleta; GoogleOAuth/SMTP capturados; Microsoft em integração437, outras integrações continuam inventariadas |
| Portal | Login/reset, faturas emitidas/PDF/anexos publicados, orçamentos/propostas aceitos/convertidos com permissões nativas | Sessão, owner, permissão, revogação e bytes privados; inclui marcos/apontamentos/Gantt e arquivos privados de projeto436; discussões de projeto em integração437 |
| Faturas/recebimentos | Itens, tributos, descontos, ajustes, numeração, snapshots, PDF, edição/cancelamento elegíveis, anexos e notificação | Writer e saldo sob lock, idempotência e auditoria; manual não contorna origem BB/Asaas |
| Créditos/refunds/estornos | Emissão, aplicação/desaplicação, refund/reversão e reversão manual parcial/final preservando originais | Suítes créditos/leitores/concorrência e Chromium; registro local não prova movimentação bancária |
| Vendas/conversão/recorrência | Orçamentos/propostas, decisões, conversão retomável no writer de fatura; agenda mensal e worker por ator/origem | Snapshot e guard sob lock; âncora mensal31; anexos/envios desses documentos integram426 |
| Projetos/tarefas | Projetos, tarefas/comentários/checklists/timers, marcos/DAG/seguidores/arquivos/Kanban, tarefas avulsas | Serviços/controladores/concorrência/Chromium; API operacional426, faturamento por tarefa/timer e preço fixo436; lote e projeto horário integral continuam técnicos pendentes |
| Despesas | Categorias, despesas/referências próprias, tributos, arquivos, edição/cancelamento auditados | A/B/principal/legado e navegador; importação/exportação426, faturamento435 e agenda436; valor originalmente declarado e estado atual da fatura separados |
| Contratos/tickets | Documentos, cópia/PDF, conversas e referências próprias | Serviços, controllers reais, arquivos privados427 e portal operacional428, com publicação explícita e grants3/5/6 |
| Relatórios/API financeira | Posição atual por moeda e CSV auditado; quatro scopes de API vinculados a empresa/ator | API/locks/revogação/PDF e Chromium; diário/períodos e moeda imutável integram426; posição atual não é fechamento histórico |
| Asaas por origem | Contas/credenciais/ambientes, cobrança, consulta, inbox, recebimento nominal Pix/boleto, worker opt-in e despesa de tarifa explícita | Suítes writer/protocolo/concorrência, native149–152 e HTTP; callback antecipado recuperado pelo worker436; outbox/refund152 observacional; financeiro integral declarado em integração437; sem parcelas/assinaturas/antecipação |
| BB API | OAuth, reserva/outbox, registro, consulta/prova de conta, pedido/confirmação de baixa, crédito16 integral, alteração de vencimento confirmada, receptor mTLS observacional | MariaDB/concorrência/TLS local/Chromium; ofertas435 e componentes integrais436; sending interrompido testado com SIGKILL real436. Estorno API em integração437; estados18/19/tarifas/IOF seguem fechados por semântica não demonstrada; nenhum BB real |
| CNAB240 | Originação, reserva API/CNAB, geração/download/retry, envio manual declarado, retorno/revisão/vínculo e crédito assistido parcial e componentes427 | Principal em caixa separado de descontos/abatimentos/encargos agregados; vínculo de retorno à mesma origem API436 e disputa canônica real; tarifas/IOF/outros/reversão parcial seguem fechados; reversão integral declarada428 preserva originais; upload não autentica banco |
| CNAB400 | Parser CBR643 e candidato CBR641 | Operação indisponível: BRL85–86, juros herdados/contrato e picture CBR64326–39; trailer já resolvido; amostras aceitas/retornos não fornecidos |
| Homologação externa | Nenhuma realizada em API/240/400/Asaas/SMTP | Depende de autorização, canal/conta/convênio/carteira e acesso próprios; não esconde código ainda faltante |

Guias: [faturas](invoice-operations.md), [créditos](credit-operations.md),
[vendas](sales-recurring-operations.md), [Asaas](asaas-operations.md),
[settings](settings-operations.md) e [API](report-api-operations.md).

A falta de homologação não elimina pendências técnicas. Não aplicar eventos sem
origem, evidência canônica e writer transacional; não atribuir ao banco uma prova local.

## Incremento430 — abertura e resposta de suporte pelo contato

Contato próprio/native5 cria e responde no chamado publicado e privado,
com autoria distinta do staff, recibo/revisão/auditoria, limites e revogação sob lock.
Sem concessão financeira ou API genérica. [Validação430](pr45-validation-430.md).
Anexos enviados pelo contato seguem na integração431; não são funcionalidade430.

## Incremento429 — API de subrecursos operacionais

16 scopes novos explícitos para arquivos privados de projetos/tarefas/contratos/
chamados, marcos, dependências e seguir a si mesmo. Writers e uso do token são
transacionais; limites JSON/base64 prévios, bytes privados e quotas originais.
Scopes antigos não se ampliam e upload não publica no portal. [Validação429](pr45-validation-429.md).

## Incremento428 — portal operacional, reversão e preferências

| Fluxo | Implementação e prova | Limite |
|---|---|---|
| Portal operacional | Publicação explícita de contratos/chamados/projetos, arquivos e mensagens; grants nativos3/5/6, sessão e owner privados | Ainda não oferece criação/resposta do contato, anexos pelo contato ou assinatura de contrato |
| Reversão de componentes | Fonte final fechada, declaração independente de reversão externa integral, recibo/journal e saldo único; original nativo preservado | Não envia nem autentica estorno no BB; não converte ocorrência44 em refund; restrito aos recibos compostos427 |
| Relatórios/diário | Original/revertido/efetivo, pernas monetárias e não monetárias, cobertura e CSV próprios | Não é saldo bancário autenticado ou escrituração fiscal |
| Preferências de vendas | Prefixos/termos próprios de orçamento/proposta em snapshots, PDF/portal/notificação; principal com lote conhecido atômico | Demais settings desconhecidos e infraestrutura global conservam seus contratos |
| API fatura/bytes | Revalidação canônica após auditoria e após TCPDF, com comparação do DTO | Prova RED/GREEN token/ator/owner; revogação tardia não libera o documento autorizado anteriormente |

API dos subrecursos operacionais integra429. Criação/resposta de suporte pelo
contato integra430; arquivos do contato seguem431. Permissões do contato não viram
permissões de funcionário. Recuperação gráfica legada exige autoridade independente
original/registro verificável; o estado transitório do provisionador é removido no
sucesso e o marcador restante não prova identidade. Ver guia específico.

## Incremento427 — composição financeira, checkout e arquivos

| Fluxo | Implementação e prova | Limite |
|---|---|---|
| Componentes CNAB240 | Fonte final fechada, writer, recibo único, DTO/hash, helper de saldo e schema427; provas MariaDB/concorrência | Restrito a tarifas/IOF/outros zero, líquido igual ao pago e principal positivo; sem API parcial ou reversão bancária |
| Saldos/leitores/guards | Pagamentos, créditos, fatura/PDF/portal/API/relatórios/diário conferem a mesma posição | Extinção sem caixa não é recebimento ou crédito disponível; corrupção bloqueia nova mutação |
| Asaas151 | Pagador privado, intenção única, recuperação sem POST cego, cartão hospedado avulso e publicação explícita no portal | RECEIVED com creditDate ocorrida; CONFIRMED/AUTHORIZED/antecipação/parcelas/escrow não lançam; provedor simulado |
| Checkout legado | Pré-autoload recusa faturas de empresa nomeada antes de helpers/gateways; NULL conserva o caminho legado | Não cria um atalho público para a empresa selecionada |
| Arquivos operacionais | Contratos/chamados com BLOB privado, PDF/PNG normalizado, quotas, integridade, hide e auditoria | Upload não publica no portal; sem URL pública ou purga |

Próximos requisitos técnicos independentes: portal operacional e API de seus
subrecursos, reversão canônica de componentes e demais consumidores sem adaptação.
Recuperação gráfica legada depende do registro de provisionamento e cadeia de
verificação confiáveis ainda ausentes; escolher esse caminho não fornece a prova.

## Incremento426 — fluxos transversais e integridade

| Fluxo | Implementação e prova | Limite |
|---|---|---|
| API operacional | Oito scopes adicionais, A/B/principal, cinco domínios, guards/locks e tokenusage na mesmaTX; MySQL/MariaDB e HTTP | Não amplia scopes de credenciais antigas |
| Vendas/arquivos/envios | PDF/PNG privados, publicação por arquivo, SMTP/fila com origem/revisão/ator | SMTP simulado; nenhum PDF público |
| Importação/exportação de despesas | CSV/JSON preview, referências/revisão, commit atômico, retry e CSV privado |100linhas/256KiB; nenhuma adoção do legado |
| Moeda/diário/relatórios | ISO congelado, writers/eventos nominais, período/corteUTC/watermark/CSV; coverage não oculta órfãos | Upgrade é observação explícita; não reconstrói fatos históricos ausentes |
| Asaas150 | Remoção remota própria, intenção durável/DELETEúnico/consulta de confirmação, contas/ambientes separados | Transporte simulado; fatura local preservada |
| BB112 | Perfil declarado de juros diários fixos e consulta observacional de movimentos, outbox/GUI e vetos adversos | Não é aplicação financeira de encargos nem homologação |

## Incremento417 — editor monetário completo no recorte de faturas

Código: `CompanyInvoiceAmounts`, `CompanyInvoices`, controlador/formulário nativo.
Campos existentes em invoices/item_tax; migration417 é marcador NOOP, sem DDL e
sem alterar migrations históricas. Gates de outros consumidores permanecem fechados.

| Requisito | Teste e resultado local | Limite |
|---|---|---|
| Múltiplos tributos, desconto fixo/percentual antes/depois dos tributos, ajuste com sinal | PHP8.3.6: `tests/companies/invoice_amounts.php`,27 checks; exemplos de centavos/overflow/inválidos | Regras descritas no guia de faturas; não é cálculo fiscal nacional completo |
| Persistência/edição/recebimento A/B, snapshot, replay pré417 e rollback | `tests/companies/invoices.php`,59 checks acumulados em SQLite | SQLite não prova locks/engine |
| Portal/PDF após ampliação | `tests/companies/portal_invoices.php`,85 checks acumulados, PDF TCPDF real | Regressão local sem SMTP/banco externo |
| Tracking nativo | `tests/native_migration_tracking.php`,12 checks em SQLite | Prova de tracking, não instalação universal |
| Políticas de release/inventário |43 testes Python aprovados | Gate estático não substitui operação |
| Editor monetário concorrente/revogação e GUI A/B/PDF/aba obsoleta | Testes ampliados em invoice_concurrency.php e companies_invoices_browser.cjs | MariaDB10.11.14 local:83 checks acumulados de fatura/edição/recebimento/concorrência e89 de portal/revogação; MySQL8/Chromium aguardam CI do novo HEAD |

Nenhuma chamada real de banco/SMTP, merge ou deploy neste incremento.

## Incremento418 — imagens e preferências de faturas por empresa

`admin/company_assets` usa renderer isolado, contexto/CSRF, settings/view+edit,
revisão concorrente e ownership pela empresa atual. PNG/JPEG até512 KiB são
validados por dimensão/pixels e convertidos para PNG, removendo metadados/conteúdo
adicional. Registro privado no JSON da empresa, sem URL pública ou caminho físico.
Download confirma ACL e auditoria transacional antes dos bytes. Não há fallback
para imagens de outra empresa ou uploads globais. Assinatura é imagem, não A1/GovBR.
Novas faturas capturam logo/assinatura; documentos antigos conservam seu snapshot.
Definições/Faturas oferece prefixo e condições próprias, validados e escapados.
Migration418 é NOOP; não altera schema, tracking ou migrations históricas.

| Requisito | Execução local PHP8.3.6 | Limitação |
|---|---|---|
| Upload, preview, remoção, download, A/B/principal, ACL, auditoria/falha, imagem inválida/oversized | company_assets.php:24 checks em SQLite e MariaDB10.11.14 | Sem HTTP local; o novo teste Chromium pertence ao novo HEAD |
| Escritores concorrentes e revogação aguardando lock | asset_concurrency.php:32 checks acumulados em MariaDB10.11.14 | MySQL8/PHP8.1/8.5 no CI |
| Snapshot/PDF real após troca/remoção de imagens | invoices.php:63 checks acumulados; invoice_concurrency.php:87 em MariaDB | Sem certificado digital |
| Portal/PDF e revogação durante lock | portal_invoice_concurrency.php:93 checks acumulados em MariaDB | Sem banco/SMTP externos |
| Prefixo/condições A/B, batch inválido e escape | settings_model_controller.php:134 checks acumulados em SQLite | Demais configurações não declaradas continuam bloqueadas |

CI do417 (`5d0f41a3`): fluxos MySQL8/PHP8.1/8.5, BB MySQL/MariaDB e stack
HTTP/Chromium aprovados. Dois gates falharam por inventário desatualizado;418
regenera os inventários conservando entradas alteradas como pendentes. Não reutiliza
essa execução como prova do código418 nem muda rollout global para liberado.

## Incremento419 / BB109 — crédito API explícito e canônico

`ApiCreditedReceipt` é a segunda fonte fechada autorizada no writer nativo; uma
implementação arbitrária da interface continua negada. `settle_api` exige contexto,
POST/CSRF, BB/view+reconcile, invoices/view e payments/create. Usa somente origem
imutável e a última consulta validada com prova da conta, até5 minutos no primeiro
lançamento, estado16 CREDITADO, natureza normal/apresentação, liquidação elegível,
data de crédito já ocorrida, nominal/pago/líquido integrais e todos os ajustes zero.
6 LIQUIDADO não basta; parcial/cheque/cartório/ajustes/dados ausentes ficam em revisão.
Recibos usam identidade bancária estável, independentemente de ator/chave da UI ou
nova consulta idêntica; pagamento, status, recibo e auditorias confirmam juntos.
Contradição materializada não é apagada por consulta posterior; nenhum estorno é
executado por uma observação adversa. Provas financeiras cruzadas API/CNAB impedem
nova aplicação do mesmo título. SQL/JSON ambíguos não são normalizados para sucesso.

Nova tabela aditiva bb_api_receipts: PK por origem, unicidades de recibo/pagamento,
FK para origem/consulta, InnoDB/utf8mb4_bin, tipos/nullability/defaults conferidos.
Migration109 idempotente; falha de tracking preserva108 e retry completa109.419 é
marcador core NOOP. Migrations históricas não foram alteradas. Módulo requer4.1.9.
A correção de `imagedestroy` substitui a chamada obsoleta por liberação do objeto,
sem reduzir os asserts de conteúdo/concorrência no PHP8.5.

| Prova local PHP8.3.6/MariaDB10.11.14 | Resultado | Limite |
|---|---|---|
| Native module101–109/instalação/upgrade/replay/falha tracking, API/outbox/criptografia |160 checks acumulados | Transporte BB simulado |
| Crédito A/B, repetição/nova consulta, revisão por juros/parcial/cheque, data/valor/prova ausente, auditoria/proveniência e conflito imutável |301 checks acumulados incluindo suítes anteriores | Não somar como301 cenários novos |
| Concorrência de dois créditos, crédito versus manual e revogação aguardando lock |331 checks acumulados incluindo as suítes anteriores | Banco local real, provedor simulado |
| Regressão da conciliação CNAB240 assistida/concorrência |129 checks acumulados | Evidência sintética, sem autenticação bancária real |
| Asaas writer/OI |84/88 checks separados | Sem homologação Asaas/produção |
| Novo fluxo Chromium |Fixture usa serviços reais com transporte injetado; GUI real confirma/retry via writer | Resultado apenas após CI do novo HEAD |

OpenAPI oficial baixada novamente em19/10/2026, versão3.2.2, SHA256 permanece
6767b098b13bc00e42700d80435a73e1f6ff4d31d7a28647926209a58d5f00d3.
A semântica documenta6 LIQUIDADO e16 CREDITADO; somente16 é elegível neste recorte.
Alterações de título, webhook mTLS, encargos/parciais/estornos e demais perfis são
pendências técnicas. CNAB400 continua candidato bloqueado pelos insumos contratuais
listados no guia400. Não há homologação API/240/400 nem autorização de merge/deploy.

## Incremento420 — leitura fresca no teste HTTP do crédito

O serviço e o writer419 passaram nas matrizes MySQL/MariaDB. O primeiro teste
Chromium falhou procurando o form após a fixture CLI criar uma consulta: mudar
somente #bb-auth → #bb-billing não faz GET nem atualiza o DOM. O teste agora
recarrega o documento por HTTP antes de confirmar. Mantém os asserts de consulta
validada sem pagamento automático, formulário real, writer, recibo e retry.
420 é marcador core NOOP; BB permanece1.0.9/109 sem nova alteração funcional.
Resultado do navegador corresponde ao novo HEAD; não atribuir o CI419 ao420.

## Incremento421 / BB110 — identidade bancária entre representações

API usa20 dígitos (000+Nosso Número17); CNAB240 usa17. Os vetos de recibo já
aplicado e observação adversa agora comparam ambas as representações, mantendo
empresa/conta/ambiente/convênio. Nenhum dado histórico, payload, número reservado
ou comprovante é reescrito.110 é NOOP funcional nativo;421 é marcador core.

Testes reais em MariaDB10.11.14/PHP8.3.6: crédito API e concorrência344 checks
acumulados; CNAB assistido/concorrência131 acumulados. Incluem recibo CNAB17
bloqueando API20, recibo API20 bloqueando CNAB17 e observação adversa17 impedindo
crédito20 antes de efeito. Casos sem duplicidade continuam positivos.
A fixture de evidência cruzada é sintética, restrita ao banco descartável; não é
prova de autenticidade de upload ou homologação bancária. Resultado HTTP/Chromium
permanece vinculado ao SHA final, com recarga real após a fixture externa ao DOM.


## Incremento422 — notificação privada de faturas pelo portal

`CompanyInvoiceDelivery` fornece estado/destinatários e agenda uma mensagem por
requisição explícita em `admin/company_invoices`. Requer invoices/view+edit e
customers/view; contato ativo próprio, cliente ativo, fatura emitida e permissão
nativa1. Compartilhar identidade não libera o envio aos contatos do proprietário.
Template `invoice-send-to-client`, remetente, assinatura e transporte são resolvidos
na origem. Link só abre o portal autenticado; sem hash público, bearer ou PDF anexo.

Migration422 cria `company_invoice_deliveries`: PK de requisição ascii_bin, revisão
e hash do contato, fila única, índice origem/fatura, FK empresa/fatura e validação
InnoDB/tipos/defaults/índices. Recibo não mantém endereço de e-mail e sobrevive à
limpeza da fila. Gravação de fila/recibo/auditoria confirma junta; retry após limpeza
não cria outra mensagem. Default indevido/índice não único bloqueiam a migration.

O worker nativo reconhece o marcador interno, valida recibo, origem, ator, contato,
permissão e revisão de fatura antes do transporte e remove o marcador dos headers.
Mudança de contato/empresa/funcionário/permissão/fatura impede envio. Nenhuma rede
ocorre na transação. Aceite pelo transporte não comprova entrega na caixa ou leitura;
crash entre SMTP e atualização da fila mantém a limitação usual de entrega de e-mail,
sem garantia de exactly-once externo. A UI exibe fila/processamento/falha/aceite/arquivo.

| Execução | Resultado observado | Limite |
|---|---|---|
| PHP8.3.6/SQLite: invoice_delivery_worker.php |93 checks acumulados, incluindo63 de faturas anteriores | Transação SQLite não prova locks; SMTP capturado |
| PHP8.3.6/MariaDB10.11.14: invoice_delivery_concurrency.php |109 checks acumulados: migração/replay/default/índice, A/B/e-mail igual, contato compartilhado negado, worker real, idempotência/rollback e dois processos aguardando lock/revogação | Transporte final capturado, nenhum SMTP externo |
| Regressão company_mail_queue.php |78 de settings+14 adicionais de fila em SQLite | Preserva fila legada e origem sem sessão |
| companies_invoices_browser.cjs |Novo fluxo A/B: seleção explícita, fila real, retry, contato cruzado403 e aba obsoleta409 | Resultado HTTP/MySQL/Chromium pertence ao CI do HEAD422, não ao anterior |

O HEAD anterior `b413fa7171f4d294488b35223c346d2f1ccf67df` passou nos16 workflows,
incluindo BB API A/B no navegador. Essa evidência não é reaproveitada para o422.
Anexos, créditos/refunds/estornos/recorrência, relatórios/APIs, demais consumidores,
Asaas positivo e ampliações BB continuam pendências técnicas, separadas da homologação.


## Incremento423 — anexos privados de faturas e portal

`CompanyInvoiceAttachments` armazena blobs imutáveis em tabela própria, sem caminho
no disco ou URL pública. `admin/company_invoices` aceita multipart com contexto de
aba/CSRF e invoices/view+edit. Listar/baixar/visualizar PNG exige invoices/view e
owner do pai e filho. Principal explícita funciona; NULL legado não é adotado.
PDF de até2 MiB é entregue como attachment com sandbox/nosniff; não é declarado
sanitizado ou certificado. PNG/JPEG até512 KiB usa o normalizador raster existente
(1600 pixels por dimensão/2 milhões de pixels), regravado como PNG. Tipos executáveis,
SVG, caminhos e arquivos além dos limites são recusados. Preview só de PNG regravado.

Cada upload possui chave durável, ator/empresa/pai/nome/mime/hash/visibilidade
conferidos no retry. Recibo cruzado é recusado antes de hidratar nome/conteúdo.
Conteúdo/hash/tamanho são conferidos na gravação e antes do download. Upload,
ocultação e downloads precisam confirmar auditoria; falha não libera bytes nem
muda estado parcialmente. Ocultação é idempotente e não apaga histórico; retry de
upload antigo não ressuscita arquivo. Até20 uploads/20 MiB por fatura, contando
ocultos. Os limites de contagem e20 MiB foram exercitados com conteúdo persistido.

Portal só lista arquivos com publicação explícita pelo operador, sessão/cliente/
empresa/permissão nativa1 e fatura emitida próprios. Lista lê metadados em lote e
nunca BLOBs. Download é POST com CSRF, valida pai+filho+visibilidade e registra contato
sem e-mail/bearer. Arquivo privado, oculto, rascunho, ID cruzado ou permissão revogada
não libera bytes. Compartilhar identidade não compartilha arquivos ou contatos.

Migration423 cria `company_invoice_attachments`, PK ascii_bin, metadados utf8mb4_bin,
mediumblob, FK empresa/fatura, índice origem/pai/estado; valida tipos/defaults/engine/
PK/índices/FKs. Default-public1 é rejeitado. Sem DDL em migrations históricas, sem
purga ou downgrade destrutivo. Versionamento BB permanece1.1.0/110.

| Prova local PHP8.3.6 | Resultado | Limite |
|---|---|---|
| invoice_attachments.php |134 verificações acumuladas em SQLite, incluindo89 de faturas/portal anteriores | Não somar suítes incluídas; não prova locks |
| invoice_attachment_concurrency.php |152 verificações acumuladas em MariaDB10.11.14: A/B/principal/NULL, ACL view/edit, portal, integridade/rollback/quotas reais e dois processos com espera observada/revogação | Banco real local; sem provedor externo |
| companies_invoices_browser.cjs |Novo fluxo A/B de multipart/retry, binário/preview, portal explícito/privado, ocultação/retry e409 de aba obsoleta | Resultado HTTP/MySQL/Chromium atribuído somente ao HEAD423 após CI |

O HEAD422 `804bf98ee708cd4c80ee31c47185f7864bb0f8c5` passou nas18 execuções dos16
workflows, incluindo notificação no CRM real. Essa passagem não valida o novo423.
Persistem créditos/refunds/estornos/recorrência, outros documentos/consumidores,
Asaas positivo e ampliações bancárias. Nenhuma homologação API/240/400 ou SMTP externo.
