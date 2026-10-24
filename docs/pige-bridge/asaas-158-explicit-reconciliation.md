# Asaas158 — reconciliação explícita de origem manual-only

API interna: `CompanyService::reconcileExplicit(issuer, actor, origin, proofId, originReference, originGuard, guardTables=[]): int`. Retorna payment_id canônico. Não faz HTTP; proofId deve vir de observação explícita anterior. Guard é callback confiável que lança exceção ao negar e nunca concede ACL. Nenhum parâmetro público booleano libera cron/webhook.

Factory `CompanyReceivedReceipt::explicit` usa a mesma classe já allowlisted no writer, sem jobEvent/lease. O writer continua proprietário da transação única, saldo, payment, receipt idempotente, audit e journal. Guard tables propagam até a fronteira transacional. Antes/depois de claim e applied, verifica marker158, policy157 manual_only/reference/issuer, ACL invoices.view + payments.create + customers.view, guard e prova persistida/hash/conta. Erro tardio reverte pagamento, receipt e journal. Callback deve permitir invoice já quitado no retry; regras de saldo pertencem ao writer.

A policy não muda: enqueue, webhook, repair e worker seguem proibidos. Fonte nativa/default continua recusando manual_only. Retentativa explícita com mesma prova retorna o mesmo payment_id, sem efeito duplicado. A referência SHA256 associa a origem à integração; cliente/conta/prova continuam os registrados na origem imutável.

Apenas PIX/BOLETO, BRL e valor integral exato. Reutiliza regras canônicas de prova latest, RECEIVED, mesma revisão de conta, data válida, sem juros/multa/desconto/refund/chargeback/adversos, sem outro provedor, crédito ou recebimento parcial preexistente. Primeira aplicação conserva limite nativo de cinco minutos da prova. Tarifa do provedor pode ser observada como diferença gross/net, mas não é automaticamente uma despesa. Não existe reembolso, pagamento parcial, efeito acadêmico ou reemissão neste comando.

Migration158 é checkpoint nativo sem nova DDL e exige schema157 válido. Header1.5.8; checks históricos156/157 preservados. Rollback de código deve preservar dados e o bloqueio manual_only; não apagar política ou desfazer contabilização por SQL.

Testes implementados: `tests/companies/asaas_explicit_reconciliation.php` executa native writer com PDO descartável, idempotência, late guard rollback, zero HTTP, revogação, marker antigo, pending, stale e juros. Workflow Asaas inclui a fixture. PHP/MySQL não executados localmente. `python tests/asaas_origin_policy_static.py`:5 checks estruturais passaram; não substituem runtime financeiro. Sem chamadas reais ou ativação.
