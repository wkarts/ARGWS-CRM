# Base Asaas1.5.6 — adaptação ao CRM multiempresa

Referência solicitada: `wkarts/asaas-payment-module-perfex-crm` no commit
`c3f169e7f571d70dc2accf2fce67bf7b7e5749c7`. Materializada por `git archive` em
checkout de referência separado. O pai confirmou equivalência dos diretórios
funcionais do ZIP1.5.6 e ausência dos testes no ZIP; essa equivalência não foi
recalculada localmente. O repositório separado não recebe alterações.

[Inventário completo por arquivo](asaas-156-file-inventory.json) compara a fonte
com o CRM em `fe7532e1`:486 iguais,53 diferentes,112 somente na fonte,88 somente
no CRM. Inclui vendor, testes e documentos; portanto as contagens não representam
funcionalidades. O inventário é o baseline anterior à adaptação, não seu resultado.

## Contratos e destinos

| Recurso da fonte | Arquivos/contrato da fonte | Destino/proteção existente no CRM | Trabalho necessário |
|---|---|---|---|
| Configuração em oito subguias | `assets/js/asaas-settings.js`, `views/settings/*`, `helpers/asaas_settings_helper.php` | `native_asaas.php`, CompanyPreferences e contexto selado | Adaptar apresentação e parâmetros por empresa/ambiente; preservar revisões e chaves fora do DOM |
| Boleto/Pix e reabertura de cobrança vencida | Checkout, Asaas_gateway, AsaasAdapter; GET de cobrança existente não é edição | CompanyService, CompanyInstruments, origem imutável e publicação no portal | Comparar critérios e parâmetros sem substituir origem por referência ambígua |
| Cartão avulso/parcelado | AsaasCardCheckout/Api, card_helper/pricing/receipts, `views/cartao.php` | CompanyCardPayments atualmente por token e sem parcelamento completo | Adaptar oferta, captura única, resultado incerto, identidade do portal e baixa somente do principal |
| Taxas e antecipação na oferta | Pricing helper; empresa absorve por padrão; antecipação `none`; inteiros e composição congelada | Perfil atual só habilita cartão | Persistência e revisão por conta/empresa/ambiente; consumidor real da configuração e apresentação da oferta |
| Recorrência de cartão | AsaasRecurring/Store, Recurring, gateway separado, hooks/schema/helper | Recorrência de faturas e journal de reajustes já próprios | Autorização explícita do contato, token cifrado vinculado ao escopo, cutoff, revisão, revogação, fila e lock; não usar existência de recorrência como consentimento |
| Ciclo de faturas | lifecycle_helper, InvoiceTransaction, hooks de criação/edição/cancelamento/reativação/remoção | CompanyInvoiceEdits/ManualSettlements/RecreationSuccessors e writers nativos | Manter intenções duráveis, histórico, falhas/consulta e serialização; não copiar transações globais que ignorem o writer multiempresa |
| Manual integral/parcial | Política receiveInCash/remover; parcial não quita tudo | Manual168/169 com política explícita e prova canônica | Conferir opções/apresentação e sequência; não ativar política por default herdado |
| Conciliação sem webhook | AsaasReconciliation e receipt helpers; recuperação por externalReference e identidade comprovada | CompanyReconciliation e origem imutável | Adaptação de consulta/listagem/associação conservadora por conta e ambiente; nunca escolher primeiro resultado |
| Monitor local/remoto e lote | Monitor, monitor schema/JS/views; faturas/webhooks/API, GET payments paginado, comparar/conciliar por fatura | Painéis nativos de origens/eventos/jobs | Paginação, filtros, resultados por item, parada após request, sem retry cego; revalidar ACL em cada ação |
| Logs e diagnóstico | diagnostics_helper, views/logs/settings/logs e assets | Logs/eventos escopados e redigidos | Não expor arquivo global, API key ou dados de outra empresa no fallback de arquivo |
| Webhook | callback/webhook helper; cabeçalho oficial e compatibilidade legada | WebhookSecurity, tokens por escopo, idempotência e prova remota | Preservar autenticação obrigatória e roteamento por conta/ambiente; não importar exceção sem token da fonte |
| SDK | composer lock0.2.64 @1708316 | vendor0.2.61 @b45ec5c | Atualizar dependência distribuída, preservar adaptadores e classmap CRM; sem alteração na SDK remota |
| Instalação/upgrade | migrations110–156, recorrência156 | migrations110–171 com outros significados | Preservar todas; diferenças143–156 comprovadas por hash; acrescentar novos passos, nunca substituir números |

## Diferenças que impedem sobreposição literal de arquivos

A fonte usa opções globais e mantém compatibilidade de callback sem token quando
não há segredo salvo; a documentação também descreve máscara visual da API key.
O contrato atual do CRM exige isolamento empresa/conta/ambiente, autenticação e
não entregar API key salva ao navegador. Essas proteções precisam permanecer
na adaptação. A versão1.5.6 define o comportamento funcional solicitado; não é
um motivo para apagar proteções ou histórico específicos do CRM.

As migrations143–156 têm bytes distintos. Particularmente156 instala recorrência
na fonte, mas corresponde a outra entrega já existente no CRM. A dependência
SDK avança por172 NOOP, preservando171 e anteriores; os schemas da adaptação
precisarão de novos passos aditivos próprios, com testes de upgrade.

## Primeiro delta: SDK da fonte

O lock da fonte fixa `argws/asaas-sdk-php` v0.2.64, commit
`170831639665082c3bf50422877942b04a4a5315`; as outras12 dependências de execução
conservam versões/referências iguais. Consumidos os bytes `src` dessa SDK e seu
lock/metadata; os mapeamentos Composer do módulo CRM permanecem e recebem somente
a nova classe WebhookToken. Não foram copiados mapeamentos para classes da fonte
que ainda não existem no CRM. Nenhuma alteração no repositório SDK ou Perfex.

Teste com Client/PaymentService/Guzzle reais e transporte MockHandler verifica
lock/installed/autoload, endpoints payWithCard/payWithCreditCard distintos e um
único POST diante de500. Não usa PAN/CVV ou token real e não acessa a rede.
Runner MySQL nativo:307 verificações, falha de tracking172 conserva171, retry/replay
e zero tentativa de cartão criada. Callback13 e histórico154/155 (48/101) passaram.
Isso não comprova checkout, parcelamento ou recorrência adaptados; seguem abertos.

Nenhuma configuração financeira ativada, credencial criada, cobrança, merge ou
deploy. O rollout integral continua bloqueado até implementação e validação das
linhas acima. O fluxo completo da fonte ainda não foi transplantado/adaptado.

## Parametrização e simulação por conta — módulo1.7.3/173

Os helpers de validação e cálculo foram consumidos byte a byte da fonte1.5.6.
A guia Cartão incorpora os16 parâmetros de parcelas, taxas, repasse e antecipação
estimada. A configuração pertence a empresa/conta/ambiente; revisões da conta e
do formulário são revalidadas sob lock. Salvar exige settings.edit e CSRF;
simular exige settings.view. Leitura sem configuração usa defaults da fonte
(empresa absorve, antecipação não incluída), sem inserir registros nem ativar
consentimento. Alteração de ambiente/hash armazenado é recusada.

Migration173 adiciona somente `asaas_company_card_pricing` com prefixo nativo.
Chave primária issuer_id/account_row; FKs para empresa e conta; ambiente, JSON
canônico/hash, revisão e data. Sem seed/backfill ou alteração em faturas/Produtos.
JSON e hashes são da configuração financeira, não de cartão ou credenciais.
Auditoria guarda ator/empresa/conta/evento/data. O mutex nativo serializa
salvamentos; não há rede sob transação. Duração/locks em produção não medidos.

Evidências:
- `tests/asaas_card_pricing_reference.php`:92 cenários/333 asserts da fonte,
  usando agora os helpers distribuídos no CRM.
- `tests/companies/asaas_card_pricing.php`:15 checks novos SQLite/MySQL além de172
  da cadeia, com revisão, empresa, ambiente/hash inválido, rollback de auditoria,
  defaults e prova de nenhuma alteração de fatura/chamada externa.
- `tests/companies/asaas_card_pricing_controller.php`:8 checks novos em ambos
  motores, controller/view reais com requests sintéticos, CSRF/replay/ACL/A+B.
- `tests/companies/asaas_card_pricing_concurrency.php`:dois processos e esperas
  MySQL observadas, um único salvamento por revisão; revogação durante espera
  nega ambos. Não é teste de carga.
- Runner nativo MySQL:313 checks, falhas DDL/tracking173 preservam172, retry/replay,
  nenhum parâmetro automaticamente salvo. Cleanup inclui nova tabela antes da FK.
- Chromium local autenticado/PHP8.3.36/MySQL8.4: runner nativo173 pela manutenção,
  formulário original salva HTTP200, simulação100.00→103.59 HTTP200. Rede externa
  bloqueada no navegador, conta e credenciais sintéticas. O teste revelou e
  corrigiu leitura indevida de result_array em INSERT/UPDATE do driver real.
- Driver A/B autenticado existente ganhou salvar/simular em cada empresa; sua
  execução completa no CI do SHA final permanece pendente.12 contratos Node
  passaram. Callback13 e históricos154/155:48/101 passaram.

**Limite funcional:** estes parâmetros alimentam a simulação administrativa.
A cobrança existente por token mantém seu valor original; não usa esta simulação
para aumentar preço. Oferta congelada, captura/parcelamento e alocação somente do
principal ainda precisam de integração. Não há coleta PAN/CVV nem armazenamento
em cookies, sessão, logs ou navegador; decisão de trânsito continua pendente.
Não confundir simulação de antecipação com solicitação ao Asaas.

Rollout somente após revisão/autorização: backup e runner nativo, confirmar173 e
schema, testar salvar/reabrir/contas autorizadas. Em falha, suspender configuração
e corrigir adiante; não reduzir marcador ou apagar histórico. Reversão de view
não desfaz configurações já salvas. Sem publicação de release, merge ou deploy.

## Histórico local paginado — módulo1.7.4/174

Monitoramento nativo em Meios de pagamento > Asaas lê cobranças vinculadas,
webhooks e jobs já existentes. Carrega sob consulta explícita GET, sem SDK,
credencial, chamada remota ou lançamento financeiro. Filtros de conta e estado,
cursor por ID decrescente,50 registros por página; novos eventos não deslocam
registros para páginas já visitadas. ACL exige settings.view e invoices.view,
revalidada após leitura. Conta/ambiente são derivados do registro autorizado.
Somente campos permitidos são enviados à view; nenhum payload bruto ou segredo.

Migration174 NOOP, preservando173.16 testes novos por SQLite/MySQL: fronteira50/3,
sem sobreposição, A/B, conta estrangeira, filtros inválidos, ACL, SQL de cada
lista, view escapada, controller422 e carregamento sob demanda. Runner nativo
MySQL317 checks, tracking174 falho conserva173, retry/replay. Chromium autenticado
local: manutenção174, listas GET e formulário original200; filtro array422.
Driver A/B do CI foi ampliado; execução integral final pendente.12 contratos Node,
callback13 e históricos154/155:48/101 passaram.

Limites explícitos: lista apenas origens vinculadas, não todas as faturas Asaas
legadas sem vínculo. Não implementa ainda lista remota paginada, associação de
cobranças legadas ou lote de comparar/conciliar da fonte. Logs globais/arquivos
não são usados como fallback por falta de escopo verificável. O monitor mostra
estado local e explica que evento processado não comprova pagamento atual.
Nenhuma migration/dado/provedor externo alterado por leitura; rollback de view
não exige remoção de schema. Nenhuma homologação de provedor foi realizada.

## Consulta remota e fila de conciliação — módulo1.7.5/175

Consulta pontual por conta/ambiente, POST administrativo protegido que executa
somente GET remoto: carteira e `/payments?limit=50&offset=...`. Filtros de situação
e intervalo de pagamento/vencimento/criação seguem a fonte e o contrato oficial
[Listar cobranças](https://docs.asaas.com/reference/listar-cobrancas), consultado
em6/11/2026. Caminho/query canônicos, datas válidas, limites e ausência de corpo
são revalidados no transporte. Não há host escolhido pelo usuário, redirect,
retry automático ou consulta remota ao simplesmente abrir a tela.

A carteira deve corresponder à conta salva. Revisão, habilitação e permissões são
revalidadas entre as chamadas e após a resposta. Rede fora de transação SQL.
Resposta paginada incompleta/duplicada é recusada; DTO descarta clientes,
descrições, externalReference, cartões e demais campos brutos. Só exibe vínculo
quando já existe uma única origem com mesmo ID remoto/conta/empresa. Uma cobrança
sem vínculo continua sem associação, mesmo que sua referência pareça semelhante.

Fila visual de até20 origens explícitas; um item por POST, sequencialmente.
Comparar observa sem baixa. Conciliar exige confirmação e usa o escritor nativo
com prova canônica fresca. ACL de monitor também é conferida dentro da transação
de recebimento pelo adaptador Asaas autorizado; a allowlist do writer não mudou.
Retry preserva o pagamento canônico. Recusas ficam em revisão; resultado incerto
interrompe a fila sem repetir ou iniciar próximo item. Parar aguarda o item em
andamento; não aborta nem desfaz operação confirmada. Nenhum corpo/segredo é salvo
em browser storage. Migration175 NOOP; não cria novos consentimentos ou filas
financeiras persistentes. Recarregar não retoma automaticamente uma seleção.

Testes executados:
- Leitura remota:24 checks novos SQLite e MySQL, transporte simulado, com wallet,
  DTO mínimo, isolamento, caminho malformado, duplicidade e desativação durante GET.
- Conciliação por item:8 novos MySQL após38 de edição e118 da cadeia; comparar,
  PENDING recusado, recebimento único/retry, empresa estrangeira e ACL revogada,
  incluindo chamada direta ao writer sem settings.view. Fixture MySQL usa dois
  processos na cadeia de edição. O teste adicional tests/companies/asaas_batch_concurrency.php passou10 checks com dois
  writers de recebimento monitorado, mesmo pagamento no retry e revogação
  durante espera bloqueando ambos. Não é concorrência da chamada remota.
- Fila JS:5 testes de ordem, parada, resultado incerto, revisão e seleção inválida;
  12 contratos do driver original passaram. Controller/MySQL permanece separado
  da simulação de respostas da interface.
- HTTP autenticado local: runner175; confirmação ausente422 JSON e data inválida422
  antes de provedor. View/JS originais processaram resposta simulada de um item.
- Runner nativo MySQL321 checks, falha175 conserva174, retry/replay. Callback13 e
  históricos154/155:48/101 passaram. CI final ainda pendente.

Limites: lote opera origens já vinculadas e os critérios nominais conservadores
do escritor atual. Parcial remoto, CONFIRMED sem elegibilidade canônica,
parcelamentos com encargos e descoberta/associação legada permanecem para revisão;
não são convertidos em baixa integral. Não há captura de cartão, solicitação de
antecipação, cobrança real ou homologação Asaas. Ampliação da SDK não realizada.

## Observação explícita sem agendamento — módulo1.7.6/176

A revisão encontrou que a fila175 chamava `query`, que pode criar jobs para
webhooks PAYMENT_RECEIVED observados anteriormente. Um teste MySQL com esse
evento e automação já habilitada reproduziu o defeito: Comparar agendava um job.
Agora a fila usa `observe`, que preserva a prova canônica sem agendar. Conciliar
continua aplicando exclusivamente pelo writer explícito. A consulta automática
normal permanece inalterada; jobs previamente existentes não são cancelados.
Não se deve interpretar Comparar como suspensão da automação previamente aceita.

Teste antes/depois: a asserção de ausência de job falhou no175; no176 passaram11
checks de itens e10 de concorrência com dois processos MySQL. O controle positivo
verifica que `query` normal ainda agenda o evento. Runner nativo MySQL:325 checks,
falha de tracking176 conserva175, retry/replay. Migration176 NOOP.

O CI do f16e7077 também revelou fixture de view sem CompanySettingsRuntime ativo
(job128159186834/run42295392597). A view real exige esse contexto. O fixture passa
agora pelo runtime real da empresa sintética B, sem remover ACL da aplicação.

Associação legada e recebimento parcial ainda não implementados: a referência
AsaasReconciliation::discoverPayments exige hash único da fatura, conjunto remoto
não ambíguo, identidade do cliente e valor compatível com saldo/recibos existentes.
O CRM atual congela um total por origem e recebe uma cobrança nominal integral;
permitir apenas `valor <= total` não comprovaria o principal nem distinguiria
parcela, alteração remota ou baixa manual já existente. A adaptação exige journal
de vinculação revisada e componentes de recebimento idempotentes por origem,
sem modificar a origem histórica ou relaxar a normalização atual.

## Vínculo legado revisado e recebimento parcial — módulo1.7.7/177

Adaptação executável do contrato de recuperação da fonte: guia Monitoramento,
Conferir → revisar empresa/conta/ambiente/fatura/cobrança/valor/saldo → confirmar.
A conferência usa somente GET de carteira, lista por externalReference, cobrança
individual e identidade fiscal do cliente; nenhum POST financeiro. Contratos
oficiais consultados em6/11/2026: [listar cobranças](https://docs.asaas.com/reference/listar-cobrancas)
e [recuperar uma cobrança](https://docs.asaas.com/reference/recuperar-uma-unica-cobranca).

Só faturas com propriedade/documento nativos comprovados. Hash único; listagem
paginada com total consistente, limite100 por página/1000 total, sem IDs repetidos.
Exige um único candidato: até candidatos excluídos ou antigos exigem revisão
adicional, não são descartados silenciosamente. Wallet, pagador, referência,
meio, vencimento e valor são revalidados. Mapeamento legado global é somente
lido sob a fronteira de transação; conflitos de fatura/ID remoto são negados.
Nenhuma adoção de fatura com proprietário NULL, backfill ou alteração daquele mapa.

A confirmação repete a consulta e compara o digest da revisão, incluindo conta,
ator, snapshot da fatura, saldo e resposta mínima. Confirmação concorrente/retry
conserva uma origem. A origem/meta, prova `legacy_bind` e auditoria vinculada por
hash são gravadas atomicamente; falha de auditoria desfaz o conjunto. Referência
manual_only impede automação implícita. Callbacks e workers não obtêm autorização
financeira por esse vínculo. Histórico e prova inicial não são sobrescritos pela
consulta posterior. Migration177 NOOP; utiliza armazenamento nativo existente.

Conciliação explícita aceita o valor nominal congelado de uma cobrança Pix/boleto
RECEIVED, menor ou igual ao saldo corrente, usando o writer nativo e a chave
canônica por conta/ambiente/ID remoto. Não recorta o valor para caber no saldo.
Recebimento anterior permanece; saldo restante deixa a fatura parcialmente paga.
Líquido/tarifa observados não são somados ao principal nem lançados como despesa
implicitamente. Mudança do valor remoto após vínculo vira conflito, não nova
parcela. Retry retorna o mesmo pagamento. A verificação de vínculo/permissão é
repetida dentro da transação; nenhum dado de cartão é capturado.

Testes executados nesta etapa:
- 74 verificações novas MySQL no fluxo de vínculo: preview sem efeito, A/B sem
 rede, ambiguidade, mudança de valor, excesso sobre saldo, identidade divergente,
 revogação durante GET, rollback de auditoria, vínculo manual, replay sem rede,
 baixa parcial/retry e preservação de recebimento manual anterior.
- 42 verificações adicionais com dois processos MySQL: confirmações concorrentes
 compartilham origem, baixas parciais concorrentes compartilham pagamento e
 revogação durante espera impede ambos. Contagens incluem asserts de transporte.
- 5 guards do controller real por SQLite/MySQL: CSRF, confirmação, revisão inválida,
 tipos inválidos e conta estrangeira. Sem chamadas de provedor.
- View PHP real em Chromium: revisão conserva contexto/CSRF e exige checkbox.
 HTTP autenticado local: runner177, formulário nativo e guards422 de confirmação,
 revisão e conta estrangeira. O sucesso financeiro é coberto por SQL/transporte
 simulado, não por API real dentro do navegador.
- Runner MySQL329 checks, tracking177 falho conserva176, retry/replay. Callback13
 e histórico155 atualizado somente na expectativa do header177; versão histórica
 e migrações antigas preservadas. CI176 falhou no assert de header175 do callback;
 essa expectativa foi corrigida, sem remover a verificação core492.

Limites concretos: uma cobrança Pix/boleto por vínculo, não grupos de parcelas,
cartão, múltiplos candidatos, juros/descontos/antecipação ou cobranças com total
mutável. Vínculos revisados não entram nos writers antigos de edição, remoção,
reembolso ou recriação; essas ações exigem adaptação própria, em vez de aplicar
indevidamente o total da fatura ao valor parcial. As origens normais anteriores
mantêm seus fluxos. Essa cobertura não equivale à adaptação integral1.5.6 nem à
homologação Asaas. Não houve transação real, merge, release ou deploy.

Rollback: suspender uso da nova ação se necessário e corrigir adiante, preservando
origens, prova de revisão, auditoria, recebimentos e marcadores. Reverter somente
view não desfaz vínculos ou baixas já confirmadas. Não apagar registros para
"liberar" outra associação ou repetir um resultado incerto.
