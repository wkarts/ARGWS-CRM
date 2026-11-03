# Recorrência de faturas na rota oficial

A agenda de faturas usa exclusivamente `admin/invoices/recurring`, com o adaptador `NativeCompanyInvoiceRecurringHttp` e a mesma tela nativa para a empresa principal e para empresas selecionadas. O endereço legado `admin/company_invoice_recurring` foi retirado e permanece apenas como tombstone inerte para impedir reintrodução acidental.

O serviço `CompanyInvoiceRecurring` mantém séries em `company_invoice_schedules`, respeita o contexto atestado, ACL por empresa, CSRF nativo, revisão e chave idempotente. Rascunhos privados, emissão posterior, notificações opt-in e condição por fatura principal continuam no fluxo oficial; nenhum writer paralelo, pagamento, boleto ou assinatura bancária é criado.
