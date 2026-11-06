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
