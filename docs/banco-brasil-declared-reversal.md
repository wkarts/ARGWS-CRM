# BB114 — reversão externa integral declarada

Esta operação registra um fato confirmado pelo operador fora do CRM. Não solicita
transferência, refund ou estorno no Banco do Brasil e não autentica o documento
apresentado. Está limitada ao recebimento assistido240 com componentes427.

Na seção Retornos, selecione o recibo composto e confirme explicitamente que o
estorno externo ocorreu e que todo o principal extinguido foi restabelecido.
Informe devolução integral do pago original, principal integral, data não futura
nem anterior ao crédito, referência independente e motivo. Exemplo: caixa90,
desconto7, abatimento3, encargos5 e pago95 exige devolução95 e restabelecimento100.
Uma reversão de um recibo parcial restabelece a parcela de dívida daquele recibo.

Ação POST `admin/banco_brasil/declare_external_reversal` exige contexto de aba,
CSRF, view/reconcile/external_reverse BB, invoices/view e payments/delete. A empresa
vem do pagamento/fatura persistidos. Conta/ambiente/contrato/layout/snapshot, recibo,
revisão independente original e prova da composição são conferidos sob lock.
Origem conflitante, evento adverso, registroAPI equivalente ou corrupção impedem
mutação; ocorrência44 nunca é usada como comando genérico de devolução.

Chave canônica única por pagamento e referência independente única por escopo.
Retry idêntico preserva autor e recibo; conteúdo divergente conflita. Confirmação,
reversão append-only, status, journal e revisõesBB são uma transação. Falha tardia
não deixa escrita parcial. O pagamento, componentes e eventos originais permanecem
idênticos; não há nota de crédito fictícia ou desconto tratado como dinheiro.

A interface/fatura/PDF/portal/API/posição/CSV/diário separam original, reversão e
valor efetivo. Encargos e extinções sem caixa têm pernas próprias. A magnitude do
principal restabelecido não é saída de caixa. Não se infere saldo da conta bancária.

Migration428 cria company_bank_receipt_reversals; BB114 registra NOOP funcional.
Backup e suspensão de capacidade são o rollback operacional futuro; não apagar
recibos/tabelas ou reabrir envio apagando histórico. Instalação/upgrade/replay,
154 checks da fonte final e182 com concorrência passaram em MariaDB10.11.14.
HTTP/CI da composição está em [validação428](companies/pr45-validation-428.md).
Reversão parcial, API/outros provedores e homologação externa não estão incluídos.
