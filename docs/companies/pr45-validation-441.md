# PR45 — API de despesas441 em HTTP real

Instalação limpa441 e upgrade nativo440→441 passaram em MariaDB, HTTP e Chromium reais, com produção intacta. Manifesto de 18.526 arquivos: `a612e5cc6b35943f18b0d0cfa14179a6963948752f52935b0904ac3b507dbd88`. Os 18.512 arquivos das raízes web foram conferidos antes/depois de cada rodada sem diferenças;14 arquivos do instalador são ausentes dessas raízes.

| Execução | Resultado |
|---|---|
| Instalador441 real | tracking441, master17/sourceinstaller, CSRF inválido403 antes de criar tabelas |
| Shell/seleção de empresa/mobile | PASS em 14,58s |
| Portal A/B e contatos privados | PASS em 14,73s |
| API de despesas na instalação limpa | PASS em 17,60s |
| Upgrade nativo440→441 no banco financeiro preservado | PASS em 5,40s |
| API de despesas após upgrade | PASS em 21,55s |

O upgrade usou nova raiz441 e novo processoPHP sobre o banco440 existente, sem reset e sem simular atualização por marcador. Oito tabelas históricas de identidade, empresas, acesso, clientes, contatos e recebimentos permaneceram byte a byte iguais antes/depois do upgrade. Master17/sourceinstaller preservado. As ações posteriores do teste criaram apenas dados sintéticos novos pelas operações canônicas.

O mesmo CJS original passou nas duas bases: A/B/principal e funcionário restrito; emissão de tokens pela GUI com escopos explícitos; token antigo sem acesso novo; cookies do CRM sem autoridade naAPI; criação de categoria e despesa; revisão otimista; retry idempotente; rollback de falha tardia real de auditoria; limitesJSON/arquivos; PDF privado com bytes exatos e HEAD vazio; `attachment`, `nosniff` e CSP `sandbox`; referências forjadas e propriedade por empresa; ocultação de arquivo e cancelamento; custo criado pelaAPI exibido com escaping na interface nativa. O funcionário restrito mantém apenas a empresa própria e não herda acesso a cliente por receber um escopo de despesa.

A fixture exige migration441 nativa e valida os schemas antes do preparo; não instala schema nem altera tracking. O CJS e a fixture daAPI de despesas passaram sem correção; produção permaneceu intacta. As adaptações dos testes históricos estão descritas abaixo. Não houve chamada de provedor, banco ou SMTP. O CSRF do instalador/upgrade e a emissãoGUI de tokens mantiveram o pipeline nativo; aAPI exige Bearer e rejeita autorização baseada somente em cookies.

`tests/issuers_standalone_http_smoke.sh` conserva os72 comandos440 e toda a infraestrutura TLS local, acrescentando apenas `tests/companies/expense_api_browser.cjs` com ambiente descartável completo. O loteDocker integral não foi executado neste ambiente; as rodadas da tabela usaram HTTP e Chromium reais. A validação sintática e o teste de ambientes do script estão registrados junto à evidência estruturada.

Evidência: `docs/companies/evidence/http-resume441.json`. Comandos efetivos locais: `python test-runtime/run_resume_db.py --cwd "$PWD" -- python http-resume441/fresh441-1.py` e `python http-resume440/reuse-db.py python http-resume441/upgrade440-441-1.py`. Estes resultados vinculam os bytes locais congelados e precisam ser associados ao SHA testado no remoto na publicação; não afirmam que440 ou441 já foram publicados.


Compatibilidade dos fluxos440: no banco441 limpo, passaram novamente Invoices36,69s, Drafts26,62s, RecurrenceModes36,34s, ProjectCopies46,02s, TaskCopies41,68s, Connect14,24s, TicketConnect35,85s, BBorigination30,15s, BBwindow27,45s e o ciclo financeiro27,88s. A revisão financeira por POST gerou exatamente doisGET de pagamento e dois de carteira por TLS1.3 no provedor local sem forwarding; CA não confiável foi rejeitada antes de qualquerGET. Os18.512 arquivos permaneceram iguais antes/depois.

No banco preservado440→441, o CJS completo de recorrência também passou29,46s. O fixture herdado exigia440 literal; somente no441 ele agora lê o target confiável do config da própria fonte, exige DB===target exato, preserva transação somente-leitura e valida os cinco schemas. Linhas preexistentes de faturas, recebimentos, origensBB/Asaas e despesas foram comparadas antes/depois sem alteração, além dos novos registros esperados das operações.

A checagem remota fresca feita pelo coordenador encontrou CI439 terminal14success/2fail, incluindo fixtureAsaas152 diante de módulo153. A correção está restrita aos testes441: o header da releaseAsaas precisa coincidir com a maior migration nativa, com sequência/classes verificadas e target>=152; o runner real executa upgrade/replay e o readback deve ser exato. SchemaRefund152 e os pré-requisitos153 são validados semDDL. Módulo existente nunca passa pelo hook de ativação que reiniciaria tracking; primeiro registro ausente usa somente o adaptador de I/O do framework e o install.php nativo. Não houve alteração da produção nem dos testes439/440 publicados.

O original `asaas_refund_browser.cjs` passou10,89s sob441 já com153 instalado: concessão/revogação explícita, doisCSRF, aba obsoleta, dispatch salvo bloqueado antes de rede, cancelamento nunca enviado, retry sem reenvio e isolamentoA/B, sem alterar caixa/journal. Em outra instalação441 real, foi comprovado zero registroAsaas antes da primeira ativação e tracking153 depois do hook nativo. No mesmo banco preservado, passaram Invoices42,86s (pré-requisito fiscalGUI), credenciais/recebimentos/callbacks/tarifa/worker15,59s, remoção remota8,81s, checkout16,16s, refund9,56s, isolamento legado0,77s e manual/reversão15,11s. Os18.512 arquivos permaneceram iguais. A tentativa inicial omitiu o pré-requisito fiscal deInvoices e falhou com issuer_document_invalid_profile após ativação bem-sucedida; o mesmo banco foi reutilizado, semreset nem alteração de produção. O helper é copiado explicitamente pelosCJS noDocker; não depende de arquivos soltos do ambiente local.

Após a refatoração dos fixturesAsaas, o ciclo financeiro completo também passou27,12s no banco de primeira ativação: revisãoPOST real, doisGET de pagamento porTLS1.3, CA inválida rejeitada semGET e trackingAsaas1.5.3 em uma única linha ao final. Produção novamente sem diferenças. Os fixtures não sobrescrevem marcador de módulo existente; somente o runner nativo avança versões. Os testes históricos publicados439/440 foram mantidos intactos.

Validação final do script: `bash -n`, PHP/Node lint e `test_issuer_browser_environment.py`2/2PASS, com73 comandosNode e ambientes completos. Os baselines históricosAsaas originais permanecem na mesma ordem e recebem o novo helper por `docker cp` explícito. A evidência separa tentativas de diagnóstico dos grupos finais aprovados. O SHA remoto e seuCI fresco continuam sob responsabilidade da etapa de publicação; esta prova local não substitui esse vínculo.
