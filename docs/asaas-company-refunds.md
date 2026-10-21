# Reembolsos Asaas por origem: outbox e observação privada

Este incremento implementa pedido explícito, reserva, despacho único, consulta privada, histórico e GUI. Não lança estorno financeiro, não altera o pagamento nativo original, tarifas, despesas ou notas de crédito. `financial_state=not_posted` é intencional e visível. Uma resposta HTTP200 ou `PENDING` não significa valor devolvido.

## Contrato consultado em 2026-10-19

- https://docs.asaas.com/reference/refund-payment: POST `/v3/payments/{id}/refund`, valor explícito, Pix integral ou várias parciais e cartão recebido/confirmado. Tarifas não são devolvidas. A referência consultada não documenta chave de idempotência deste POST.
- https://docs.asaas.com/docs/estornos e https://docs.asaas.com/docs/refunds: percorrer todos os itens de `refunds`; somar apenas `DONE`, manter `PENDING`, excluir `CANCELLED`. Valor original da cobrança permanece preservado. E2E e comprovante são opcionais. O item documentado não fornece um `refund.id` unívoco.
- https://docs.asaas.com/reference/retrieve-refunds-of-a-single-payment: consulta específica de recuperação; este incremento consulta a cobrança pelo GET canônico já autorizado, sem polling/webhook novo.
- https://docs.asaas.com/changelog/novo-status-split-de-pagamento: a definição expressa de `REFUNDED` como integral é para **split**, e não foi extrapolada para status da cobrança.

O E2E não é tratado como identidade individual da devolução nem vínculo inequívoco ao POST. Hash de payload, descrição, índice do array ou diferença de valores também não recebem essa autoridade. Parciais concluídas permanecem observacionais (`partial_refund_identity_contract_required`), uma dependência contratual específica. Mesmo soma integral observada não lança financeiro: `full_refund_canonical_writer_required` identifica código ainda faltante, a ser entregue em incremento separado com fonte final e confirmação independente; não é homologação bancária.

## Autoridade e durabilidade

A permissão exige empresa exata, ator ativo com `invoices/view`, `invoices/edit`, `payments/delete` e `settings/edit`, mais concessão explícita desabilitada por padrão para ator/conta. A revisão da concessão é capturada e revalidada depois das leituras remotas e imediatamente antes de gravar o início do POST. Revogar e reabilitar não restaura intenção antiga. Conta, carteira verificada, ambiente, origem, fatura, moeda BRL e eventual recibo canônico ficam capturados em JSON/hash imutáveis. Nenhum registro com emissor NULL é adotado.

Não existe HTTP sob transação financeira. A reserva não envia reembolso; o início de envio é persistido antes do POST. Depois desse ponto, timeout, falha de auditoria ou persistência de resposta deixam `sending`/`uncertain`; repetição local retorna o mesmo pedido, nunca repete o POST. Consulta GET pode recuperar fatos observados. Uma resposta autorizada que chega após revogação é guardada apenas como fato mínimo, sem reabrir autoridade ou lançar financeiro.

Sem identidade individual contratada, qualquer intenção já enviada impede novo pedido para a mesma origem, mesmo com outra chave. Apenas cancelamento explícito de intenção **nunca enviada** libera uma nova revisão; todo histórico é preservado. Até uma observação cancelada no provedor não autoriza outro POST por inferência. Não há criação automática via webhook, chargeback, Pix genérico, split, parcelas, antecipação ou custódia. Perfis não contratados exigem revisão.

O consumidor canônico de recebimentos Asaas recusa prova antiga de RECEIVED enquanto houver pedido de reembolso ativo/enviado na mesma origem. Cancelamento local nunca enviado libera esse veto. Leituras privadas continuam disponíveis para conta desativada; novas chamadas remotas exigem credencial verificada, revisão e concessão atuais.

## Schema e integração

`Asaas\CompanyRefundSchema::ensure($db,$prefix)` é aditivo e fora de transação: `asaas_company_refund_grants`, `asaas_company_refund_requests`, `asaas_company_refund_observations`. Nenhum backfill ou opt-in implícito. Guard exige módulo >=1.5.2 e metadados completos. A migração152 oficial deve validar os schemas históricos CompanySchema, CompanyRemoteOperationsSchema e CompanyCheckoutSchema antes de criar este schema. Migrations<=151 não mudam. Header/migration são entregues pelo integrador, fora do patch funcional.

## Evidência automatizada

Todos os testes usam bases descartáveis e transporte Asaas simulado, nunca credenciais/rede de produção. Runner nativo real demonstra falhaDDL152 mantendo151, falha de tracking mesmo com DDL completa, retomada/replay e ausência de concessões. Os históricos148–151 permanecem exercitados. MariaDB/InnoDB testa waiters reais do lock: mesma reserva, chaves diferentes, despacho único e revogação. Controller/renderer reais e navegador exercitam A/B, ambos CSRF, formulários salvos revogados, revisão obsoleta, cancelamento local e repetição sem rede. Pagamentos/custos/diário/NULL preservados. Isso não representa homologação do Asaas ou movimentação financeira remota.
