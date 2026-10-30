# Reversão local do novo recebimento manual após reembolso

Este fluxo se aplica exclusivamente ao recebimento manual independente criado pelo
fluxo439 após a declaração de reembolso integral Asaas. Não altera nem reenfileira
a cobrança Asaas, seu recebimento original, o reembolso declarado, tarifas ou custos.
Nenhuma transferência bancária ou chamada ao provedor é enviada pela correção local.

Na revisão da mesma fatura, um operador atualmente autorizado pode selecionar
**Reverter localmente o novo recebimento manual**. O formulário mostra o valor
integral, exige data, nova referência, SHA256 de evidência independente, motivo e
confirmação própria. As proteções nativas de CSRF e contexto e o token particular
da revisão permanecem obrigatórios. O operador precisa de invoices/view, das
permissões atuais de revisão Asaas e payments/delete; payments/create não é
concedida implicitamente. Empresa B não pode consultar ou corrigir a origem A.

A fonte final `CompanyPostRefundManualReversalSource` autoriza somente esse recibo,
empresa, operador, revisão, valor integral e chave. O writer canônico reutiliza
`moveCompanyCredit(..., 'manual_reverse')`: grava a entrada positiva imutável,
projeta o saldo manual restante em zero e atualiza o estado da fatura. O recebimento
Asaas original e sua reversão externa permanecem intactos. O veto genérico por
origem de provedor e os guards da API financeira continuam em vigor.

A migration440 deve registrar `CompanyPostRefundManualReversalSchema::ensure` após
o schema439 de recibos manuais. A tabela aditiva vincula a nova declaração ao recibo
manual e à entrada canônica. As FK têm nomes determinísticos limitados para prefixos
longos. Não há backfill ou adoção de registros antigos. Falha em entrada, vínculo,
auditoria, journal ou confirmação reverte toda a transação. Repetir os mesmos dados
e chave retorna a entrada original; uma chave nova não reverte duas vezes.

Saldo, portal, PDF, API e relatórios conservam as magnitudes original, externa e
manual separadas. A referência tipada é validada antes da liberação: vínculo,
entrada, auditoria ou journal divergentes impedem a leitura. Sem schema440, o
leitor anterior permanece disponível e a nova ação fica desabilitada. O caminho
transitório do writer não é apresentado como leitura financeira confirmada.

Perfil implementado: uma reversão LOCAL integral do único novo manual439. Não
implementa reversão parcial, transferência remota, novo refund Asaas ou uma terceira
liquidação automática após essa correção. Esses fluxos não são homologação pendente;
continuam fora deste perfil de código. Homologação externa Asaas/bancária não foi
realizada. Os testes usam dados sintéticos e transporte Asaas simulado.

Testes novos: `post_refund_manual_reversal.php`, `_controller.php`, `_readers.php`,
`_concurrency.php` e `_browser.cjs`. A fixtureHTTP exige o schema440 já instalado,
sem configurá-lo manualmente no container. O resultado do navegador pertence à
execução integrada final; lint do CJS não constitui prova HTTP/Chromium.
