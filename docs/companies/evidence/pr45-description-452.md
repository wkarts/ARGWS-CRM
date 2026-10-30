# Operação multiempresa e integrações por origem — PR45

Branch `feat/banco-brasil-billing-cnab240` → `main`. Base confirmada: `aa58b832b3c5ed04a599caacba125b056c081ba7`.

## Estado publicado e CI

**HEAD:** `543ff96fe2a49ceef48d8595a58e8400befc6266` · **árvore:** `8690b0a058282369db3d3a9c2448d2d6d0aaf6a6`  
**Núcleo 4.5.2 / migration 452 · Banco do Brasil 1.2.3 / 123 · Asaas 1.5.3 / 153.**

Histórico contíguo desta retomada: 446 `b47d1666` → 447 `c07a03a2` → 448 `73250bfc` → 449 `c93d0ae4` → 450 `7762a62f` → 451 `6aa0cfa6` → 452 acima. Árvores remotas idênticas às composições validadas; atualização fast-forward com expectedSHA e force=false.

**CI452 do HEAD: 16/16 workflows distintos aprovados, nenhum ativo**, conferidos em 2026-10-20 21:58:19 UTC. Resultados vinculados ao SHA `543ff96fe2a49ceef48d8595a58e8400befc6266`, sem transferir aprovações de outro commit.

| Prova remota do HEAD | Resultado e evidência |
|---|---|
| Empresas — PHP 8.1.34 | PASS; teste original de memória com **255 asserts**, cópias com schemas completos e executor452. [Job](https://github.com/wkarts/ARGWS-CRM/actions/runs/39493469643/job/118872345187) |
| Empresas — PHP 8.5.11 | PASS; os mesmos **255 asserts**, regressão do bloco exato de logging e executor452. [Job](https://github.com/wkarts/ARGWS-CRM/actions/runs/39493469643/job/118872345309) |
| Stacks / HTTP / Chromium | PASS; calendário original, códigos seguros nativos, portal sem responsável, cópias de projeto/tarefa, tarifa240, LOCAL V3, defaults/prioridade/follow/timers/auto-stop. [Job](https://github.com/wkarts/ARGWS-CRM/actions/runs/39493470075/job/118872348519) |
| Banco do Brasil | PASS; quatro jobs: unitários8.1/8.5 e integrações MariaDB/MySQL. [Workflow](https://github.com/wkarts/ARGWS-CRM/actions/runs/39493469693) |
| Preservação / política | Inventários, faturas/cliente, recorrências, Asaas/callback, Interface, Containers, Deployer e versões aprovados no mesmo SHA. Os inventários continuam **blocked / operational_ready=false**. |

Trechos conferidos nos logs do HEAD: PHP8.1 e8.5 completaram o controller original com255 checks; o bloco exato de logging completou13 checks em cada versão. No Stacks, `Company calendar Chromium PASS`, `Calendar native diagnostic log PASS` (códigos permitidos, sem conteúdo/segredos), regressão `unassigned0`, tarifa240 e LOCAL V3 passaram. Os runners452 confirmaram falha/SQL ignorado/readback, retry/replay e histórico preservado. Contadores de includes permanecem cumulativos, não milhares de casos independentes. Homologação bancária real não foi realizada.

Histórico separado: 446 terminou14/16; 447 e449 terminaram15/16, apenas Stacks falhou. **450 concluiu16/16**, incluindo Empresas8.1/8.5, Banco do Brasil e Stacks com o calendário original. **451:** terminal 14/16; Empresas/PHP8.1 falhou com `issuer_owned_transaction_required` (PHP8.5 cancelado pelo fail-fast); Stacks falhou no POST da cópia de projeto (503 em vez de303). Banco do Brasil e Interface passaram. O erro de cópia encontrado em Empresas451 é corrigido no452; sua investigação e prova RED/GREEN estão abaixo. [Stacks450](https://github.com/wkarts/ARGWS-CRM/actions/runs/39480218915), [Empresas450](https://github.com/wkarts/ARGWS-CRM/actions/runs/39480219242), [Empresas451](https://github.com/wkarts/ARGWS-CRM/actions/runs/39485295822).

O primeiro commit452 `5f2b59a26a7fa3065ee9c12a661b3ebd6d60fe13` apontou manifestos de fronteiras desatualizados no CI. O HEAD acima é um **follow-up apenas documental de três arquivos**, que registra a revisão específica da cópia como **partial** e regenera o inventário geral. Foram executados 13 testes unitários pertinentes, os dois comandos de conferência positivos e os dois gates de prontidão negativos (continuam bloqueados). **Todos os 18.587 arquivos de produção são byte-idênticos ao primeiro commit452 validado**; implementação, testes, workflow, versões e migrations permanecem iguais. A mesma release da PR continua 4.5.2/452; não há nova correção funcional ou novo passo nativo. Os 16 workflows desse novo SHA foram conferidos separadamente e terminaram aprovados.

**O contrato integral permanece aberto.** Implementação, testes simulados, checks do SHA e homologação bancária são estados distintos. Não há merge, deploy, tag, force-push ou operação financeira real nesta tarefa.

## Problemas corrigidos

**Memória, incremento 447.** A instrumentação do teste retinha 89.678 consultas após as 233 asserções do pré-requisito. A liberação da trace na fronteira da fase conserva todas as 255 asserções e a gravação das consultas seguintes, com 128 MiB. Não era recursão do produto; não houve aumento de limite ou retirada de cobertura.

**Calendário, incremento 450, preservado no 451.** O writer do portal cria legitimamente tarefas aguardando atribuição, com `assignee_id=0`, e o cenário original vence em 1º/11. O mês de A começa domingo e termina em 31/10; o de B começa segunda e inclui 1º/11. A hidratação exigia um ID positivo para esse zero e gerava `issuer_invalid_id` / HTTP 422.

A reprodução executou o browser original do portal e confirmou A diária/mensal 200, B diária 200/mensal 422, com fonte canônica e log seguro. O artefato CI449 mostra mensal 422 em 1,650 s, pico 2 MiB, sem OOM; o código de domínio não estava nesse artefato. O timeout esperando tabela era consequência da página de erro, sem evidência de carregamento lento, perda de permissão ou seletor inadequado.

Somente zero inteiro/string de `project_tasks` vira ausência interna de responsável na leitura. Fonte, datas, ACL, filtros, only-assigned, seletor e timeout foram preservados; IDs negativos continuam inválidos. O calendário original completo passou depois do portal original nas instalações novas 450 e 451. Regressão nova cobre A/B/principal, domingo/segunda, dia real, ator/empresa cruzados e invariância das fontes.

**Integridade de timers, incremento 450.** Ensaios SQL reais demonstraram alteração de prioridade antes da captura e segundo timer ativo depois da auditoria aceitos no 449. Agora o frame anterior ao INSERT, a história completa da tarefa e o único timer ativo do ator nas duas modalidades são confirmados até a última escrita. Nove testemunhas reais, rollback exato e retry.

**Composição de cópias, incremento452.** O escritor de tarefas passou a exigir também as tabelas financeiras de tarefas avulsas para o auto-stop451. O compositor de cópia de projeto fornecia apenas as tabelas de projeto; com todos os schemas nativos instalados, a transação recusava a escrita com `issuer_owned_transaction_required`. O compositor usa agora o mesmo conjunto completo de participantes. A implementação publicada451, com somente o diretório dos requires adaptado, reproduziu exatamente o erro; cópias projeto/individual e schemas completos436–440 passam após a correção. A proteção transacional permanece exigida.

**Diagnóstico do calendário, incremento452.** O auxiliar449 carregava o app-config protegido sem definir BASEPATH: PHP encerrava com status0 antes de ativar o logger. Isso foi corrigido e agora se executa o bloco exato do smoke em processos novos com o guard nativo real:13 checks, PHP8.1/8.3, base estrangeira e threshold conflitante recusados, byte-idempotência. O calendário original passa e um verificador READ ONLY confirma os códigos seguros nos logs nativos, sem retornar conteúdo/credenciais. Não se afirma que os logs antigos449 tinham sido ativados.

## Validação adicional452

- **36 comandos SQL PASS:** 12 em MariaDB 10.11.14/PHP 8.3.6 e 24 em MySQL 8.0.46 (12 PHP 8.3.6 + 12 PHP 8.1.2 no mesmo servidor vivo). Todos com 128 MiB, InnoDB/binlog ON e usuário sem SUPER.
- Quatro composições completas de cópias com schemas nativos436–440; prioridade/recibos, 9 + 11 cenários de concorrência com dois waiters observados, integridade do auto-stop e App452 desde451 genuíno, marcador falho/ignorado/readback, retry/replay e dados/metadados preservados.
- **Fresh HTTP452: 10 etapas PASS**, incluindo portal original antes do calendário original, defaults e diagnóstico de códigos nativos. **Upgrade HTTP451→452 PASS:** 315 tabelas históricas preservadas, nenhuma nova, BB123 igual; App/CSRF real, requisição inválida com log confirmado, calendário com zero canônico READ ONLY e browser de cópias completo.
- O browser de cópias agora seleciona explicitamente o responsável nas duas tarefas preparatórias. A configuração própria preservada tinha autoatribuição OFF e a validação HTML bloqueava o envio antes do POST. Nenhuma asserção foi removida; as preferências não foram alteradas. A suíte foi retomada no mesmo banco já atualizado, sem repetir ou resetar o upgrade. O primeiro startup do clone físico exigiu apenas relocação dos caminhos absolutos do índice binlog; todos os arquivos haviam sido restaurados e verificados por SHA256.
- **143 testes Python, 27,014 s; 11 lints; gates de núcleo/módulos no SHA publicado.** Produção452: 18.587 arquivos / 18.573 de runtime sem drift. Do451, 18.578 arquivos produtivos existentes estão idênticos; somente sete metadados, compositor de cópia e novo NOOP mudam em runtime. Testes, CI e documentação adicionais estão no commit.
- [Validação452](https://github.com/wkarts/ARGWS-CRM/blob/543ff96fe2a49ceef48d8595a58e8400befc6266/docs/companies/pr45-validation-452.md) e [evidências452](https://github.com/wkarts/ARGWS-CRM/blob/543ff96fe2a49ceef48d8595a58e8400befc6266/docs/companies/evidence/resume452-current.json).

No451 também executei novamente o teste original de memória em PHP8.1.2/MySQL8.0.46,128MiB: **255 asserts PASS, pico98.570.240 bytes (94,004MiB)**,44,85s. Os18.586 arquivos produtivos permaneceram iguais. Isso é evidência do451; o CI452 executa sua própria suíte.

## Entregas funcionais447–451 preservadas

- **447 — diário LOCAL sem caixa:** declaração integral própria por observação API16 válida, sem pagamento/crédito fictício. Restauração append-only reabre a dívida. Manual e crédito nativos posteriores usam evidência/correlação independentes, incluindo aplicação, desaplicação e reversões locais pelos writers canônicos.
- **447 — tarifa CNAB240:** recorte integral nominal, bruto 105,53 / tarifa 2,53 / líquido 103,00, com principal e tarifa separados, fonte, auditorias, recibo e replay selados. Não fabrica despesa fiscal nem libera outros componentes ou reversão dessa nova base.
- **447 — leitores e recorrência financeira:** GUI, bearer com sete scopes, PDF, portal, relatórios/CSV e diário datado seguem a posição canônica. LOCAL ativo satisfaz principal quitado; restauração bloqueia sem avançar claim/data; fundos independentes reabilitam a recorrência existente.
- **448 — prioridade e acompanhamento próprios:** enum 1–4, default próprio 2, edição omitida conserva, cópias revisadas preservam prioridade. Auto-follow opt-in somente do criador autorizado de tarefas de projeto, sem herdar seguidores ou reinscrever por retry.
- **449 — política de estado ao iniciar timer:** OFF por ausência inclusive na principal; início pode mover A fazer → Em andamento na mesma transação, projeto/avulsa/GUI/API. Parar conserva estado; política/ator/token/linha são reconfirmados depois da auditoria e retry não reaplica.
- **449 — relógio LOCAL:** registro e auditoria usam o mesmo created_at capturado. RED determinístico de declaração/restauração com SQL SLEEP 1,1 s e testemunha real; GREEN com igualdade estrita, rollback e retry. Nenhuma tolerância ou regra financeira relaxada.
- **450 — calendário e integridade:** reparos descritos acima.
- **451 — auto-stop autorizado por empresa:** nova configuração visual “Parada do cronômetro anterior”, grupo `task_timer_stop`, OFF por ausência e sem herança global. Os dois writers encerram o único timer anterior do mesmo ator e da mesma empresa ao iniciar outro, entre projeto/avulsa, com fronteira temporal única e rollback conjunto. A tarefa anterior mantém o estado. Autoridade, projeto/responsável atuais e proteções de faturamento do objeto anterior são verificadas independentemente. A política não autoriza parar um timer de outra empresa, mesmo para o master. Mesmo recibo não cria outro intervalo; novo request na tarefa já ativa continua recusado. Não arredonda, conclui tarefa ou gera cobrança.

Preservados 445/446, cadastro/router nativo de Empresas, master/equipe/contexto, clientes/contatos/portal, faturas/créditos, demais operações próprias, arquivos/APIs/filas, notificações, Asaas e configurações visuais/administrativas. A descrição antiga completa permanece em `docs/companies/evidence/pr45-description-446.md`, como histórico.

## Recuperação, arquivos e banco

447 recuperou 116 paths com manifesto e vinculou 18.574 arquivos produtivos à composição validada. V3 é reconstrução explícita; não se anuncia recuperação byte-exata do NoncashV2 ou dos 154 testes perdidos.

447 cria três tabelas LOCAL, com PK/FK/charset restritivos e sem backfill financeiro ou de autorização. BB123 é NOOP de verificação dos quatro participantes existentes. 448 cria duas sidecars de prioridade, PK/FK compostas e sem backfill. **449, 450, 451 e 452 são NOOPs oficiais**, sem nova tabela/coluna. O upgrade HTTP450→451 preservou 315 tabelas históricas e BB123. Preferências JSON nascem somente no salvamento autorizado; o nó449 e seu contrato estrito permanecem independentes do novo nó451.

Migrations publicadas não foram modificadas, renomeadas ou removidas. Passos nativos contíguos; módulos intocados não receberam incremento arbitrário. Principais paths451: `CompanyTaskAutoStop`, `CompanyTaskTimerStopPreferences`, dois writers, `CompanyTaskTimerTransitions`, Settings/Catalog/view, migration451, metadados, workflows/smoke, testes próprios, inventários e relatórios. Nenhum arquivo histórico foi removido.

## APIs, configuração, segurança e desempenho

Escritores canônicos, empresa/ator/scopes explícitos e autoridade reconfirmada após locks e auditorias. O bearer continua usando `tasks:write`, que já cobre as duas modalidades; o auto-stop não concede nova autoridade. CSRF/contexto/revisão permanecem na GUI; a exceção LOCAL continua restrita à rota API numérica exata.

Origem/conta/ambiente próprios, segredos cifrados/hashed e nenhum fallback entre empresas. Sending/uncertain não autoriza retry cego. A/B/principal/restrito, revogações durante espera/render, proveniência, falhas finais, concorrência e rollback foram executados nos recortes descritos. Logs limitados/redigidos sem credenciais ou conteúdo financeiro.

Inventários lexicais continuam bloqueados, operational_ready=false; não certificam isolamento universal. Volume/locks e leitura histórica exigem avaliação no ambiente futuro de implantação.

## Testes executados

**Incrementos anteriores:** 447 teve 26 comandos por motor, Asaas/pagamento/refund, ACL/concorrência, leitores/recorrência e runners reais; 448, oito suítes por motor, 13 corridas × dois waiters e fresh/upgrade genuínos; 449, 15 comandos por motor, LOCAL/clock e cronômetros, fresh de 12 etapas e upgrade448→449; 450, 14 suítes por motor, fresh de 14 etapas e upgrade449→450. Relatórios e tentativas qualificadas de cada incremento permanecem.

**Provas históricas da composição 451:**

- **20 suítes SQL por motor:** MySQL 8.0.46 e MariaDB 10.11.14, PHP 8.3.6 / 128 MiB, InnoDB, binlog ON e usuário sem SUPER. Calendário/ACL/fronteiras, transições/integridade/concorrência anteriores, Settings/auto-stop/races, App451, faturamento/timesheets/tarefas avulsas/lotes/projeto fechado, LOCAL clock e recorrência.
- **Quatro comandos PHP 8.1.2 / 128 MiB adicionais em MySQL**, no mesmo servidor vivo antes da parada: Settings, integridade/positivos, concorrência e runner451. Não são o runtime do CI.
- Settings real: 43 checks adicionais / 177 cumulativos. Auto-stop: 102 / 328; integridade acrescenta 30 / 358, incluindo permissão do projeto anterior, outro ator, projeto encerrado/horário futuro, metadata, SQL ignorado, projeto/seguidores. **14 testemunhas SQL reais**, rollback completo e retry.
- Concorrência: 74 adicionais / 300 cumulativos, **dez casos com dois waiters InnoDB observados**. Duas operações distintas podem concluir serialmente; o último timer fica ativo, sem inventar conflito. Mesma chave cria um único intervalo.
- App runner451 sobre450 genuíno: NOOP, marcador SQL falho/ignorado/readback, retry/replay, linhas/metadados preservados e preferência nova ausente/OFF sem backfill.
- **Fresh HTTP451: 15 etapas PASS**, incluindo portal original antes do calendário original completo, tarifa240, LOCAL/restauração/fundos/PDF/bearer/CSV, defaults, prioridade, timers e auto-stop.
- **Upgrade HTTP450→451 genuíno PASS:** blobs Git450 conferidos, 315 tabelas preservadas, nenhuma nova, BB123 igual; calendário com zero canônico e timers anteriores passaram. Auto-stop no Chromium cobre A/B/principal e 12 pares de modalidades, CSRF403, revisão/contexto409, recusa entre empresas, recibo e finanças/opções inalteradas.
- **18.586 paths produtivos / 18.572 runtime sem drift**, fontes de testes congeladas. **143 testes Python em 30,692 s; 20 lints PHP/CJS/shell**; inventários regenerados; gates de núcleo/módulos/histórico aprovados no commit final.

Contadores de includes são cumulativos, não somados como casos independentes. O harness local usa instalador/App/controllers/GUI reais com PHP-S/Chromium/MariaDB e adaptação restrita da CLI Docker; o workflow real de containers é prova separada.

Falhas de auxiliares estão qualificadas nos relatórios: cache de existência da tabela testemunha, expectativa de um veto mais específico quando o frame nativo já recusava a mudança, snapshot binário sem perda em base64 e metadados de login separados da captura de opções. Continuaram obrigatórios testemunha real, rollback de todas as linhas e retry. Não houve aumento de memória/timeout ou supressão de cobertura. A falha de reabertura do datadir MySQL450 após testes concluídos não foi contada como prova PHP8.1; a prova451 ocorre antes da parada do servidor.

**Documentos:** [validação451](https://github.com/wkarts/ARGWS-CRM/blob/6aa0cfa6adfc9da395728fafaa5dd4c6eb0829fb/docs/companies/pr45-validation-451.md), `task-timer-auto-stop.md`, guias de prioridade/timers, matriz/status, relatórios447–450 e `docs/banco-brasil-remaining-contracts.md`. Evidências finais: `docs/companies/evidence/resume451-{sql,http}-current.json`; anteriores preservadas.

## Pendências técnicas e dependências externas

**Código/provas ainda pendentes:** recorrência operacional de tarefas, regras adicionais de timers e demais configurações/consumidores; reversão da nova base tarifária, composição/IOF/outros/parciais e demais comandos/adaptadores/estados específicos. Não são chamados de mera homologação. Auxiliares CNAB400 observacionais não equivalem à vertical financeira/remessa.

**Documentos bancários indispensáveis, reconferidos nas fontes primárias:**

- **CBR641, posições85–86:** confirmação do código BRL aplicável ao contrato. O manual público de junho/2024, nota30, discrimina dólar/euro sem informar BRL; não presumir branco,00 ou09.
- **Juros herdados:** a nota10 desse manual confirma o uso da taxa padrão do beneficiário quando não se informa valor. Falta comprovar o cadastro efetivo da conta/convênio/carteira/variação ou sua isenção inequívoca.
- **CBR643, posições26–39:** o manual de julho/2023 mantém 14 posições versus picture9(013)v99 de 15 dígitos. Falta errata de largura/escala e totalizadores.
- **API18/19:** ID estável por crédito; semântica individual/acumulativa de `valorCreditoCedente`; datas/componentes/reversões. O endpoint OpenAPI atual não ficou acessível nesta revisão; a captura anterior é histórica. Não usar delta, cursor ou hash como identidade bancária.

Links primários e notas estão no documento de contratos restantes. A errata solicitada do MD usa **CBR641 85–86 e CBR643 26–39**, sem as referências compactadas antigas.

**Homologação real não realizada:** exige documentos, contrato, acesso e autorização específicos. Nenhuma operação bancária real foi executada.

## Compatibilidade, validação manual, implantação e rollback

Fluxo normal de build/distribuição mantido, sem skip-release ou alteração de develop. Recomenda-se conferir A/B/principal/restrito em criação/cópia/restauração/fundos/recorrência, PDF/CSV/diário/arquivos, CSRF/contexto obsoleto/revogação/retry/proveniência e os perfis bancários contratados.

**Nenhum deploy ou merge nesta tarefa.** Futura implantação autorizada exige backup verificado de banco/arquivos/configuração, pacote canônico, runners nativos e smoke por empresa. Rollback coordenado suspende writers/workers e preserva evidências; não apaga recibos nem reutiliza NossoNúmero, sem downgrade destrutivo.

## Checklist

- [x] 447–452 publicados na mesma branch, árvores validadas e passos contíguos; 439–446 preservados.
- [x] Causas de memória e calendário reproduzidas, com cobertura e limites preservados.
- [x] Instalação nova, upgrade genuíno, permissões/isolamento, concorrência, recorrência, runners e fluxos bancários simulados executados.
- [x] Auto-stop próprio, configuração gráfica, idempotência e proteção entre empresas implementados e testados.
- [x] Errata bancária e lacunas documentais precisas; implementação separada de homologação.
- [x] CI terminal do HEAD: 16/16 workflows, matrizes PHP8.1/8.5 e logs críticos conferidos.
- [ ] Contrato integral e pendências técnicas restantes.
- [ ] Documentos específicos e homologação externa autorizada.
- [ ] Merge/deploy — não executar.

