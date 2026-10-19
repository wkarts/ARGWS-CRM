# 427: contrato de componentes e inventário de saldos

O incremento427 integra o escritor nativo, a fonte final fechada `BancoBrasil\ReviewedComponentsReceipt`, o saldo único e os leitores privados. A prova por ambiente está em [validação427](pr45-validation-427.md). Não é homologação bancária.

## Representação acordada

- `invoicepaymentrecords.amount` continua sendo somente principal recebido em dinheiro, nunca desconto, abatimento ou encargo.
- `company_bank_receipt_components` conserva o recibo de composição imutável, fonte CNAB240, chave canônica do pagamento, proprietário, moeda congelada, payload/hash, hash da evidência de origem e metadados.
- A redução da dívida é `cash_principal + discount + abatement`. O helper único de saldo deduz componentes sem caixa além dos pagamentos e créditos reais. Nenhum crédito fictício, `credit_id=0` ou mudança do nominal da fatura.
- Exemplo local: nominal100, pago95, encargo agregado5, desconto10 → principal em caixa90, extinção sem caixa10, dívida extinta100, encargo não fiscal5, líquido observado95. Não chamar 100 de recebimento nem inventar a separação multa/juros.
- O perfil inicial exige tarifas/IOF/outras despesas/outros créditos zero e observa líquido igual ao pago. Essa é uma restrição de suporte; não comprova uma equação geral de líquido/tarifas. Campos ausentes/desconhecidos não se tornam zero. O schema admite líquido nulo para um contrato futuro explícito; o DTO CNAB atual exige o campo existente e a restrição inicial.

## Serviço e composição

`CompanyBankReceiptComponents($db,$prefix)` não abre transação e não liquida sozinho. Todos os métodos exigem `IssuerTransaction` e os participantes de `tables()`. A origem é conferida antes da hidratação. `capturedInOwned(issuer,actor,invoice,key)` retorna o DTO persistido ou null, permitindo replay sem recalcular o saldo anterior contra o saldo já reduzido. Replay por outro operador autorizado conserva autoria original. `prepareInOwned(...,dto,lockedBalanceCents)` confere o snapshot anterior contra o saldo do helper. `recordInOwned(...,payment,key,dto)` confere recibo nativo canônico, principal em caixa, projeção de status, empresa/moeda e grava componentes+auditoria na mesma transação. Modo nativo: `bb_cnab240_components_reviewed`.

`forInvoiceInOwned(issuer,actor,invoice)` retorna mapa payment_id→composição verificada. `reductionsInOwned(...)` soma desconto+abatimento. Não usar este último sozinho como substituto da conferência de pagamentos: o helper central exige um componente válido para cada pagamento com o novo modo, recusando componente ausente, fonte/prova divergente e duplicidade. Não permitir reversão manual de recebimento do provedor; uma reversão bancária futura requer seu próprio evento e recibo.

`CompanyInvoiceBalance` é a única origem de total/recebido/crédito/extinções/saldo, com autorização `invoices/view`, recibos de pagamento, reversões manuais anteriores e prova de créditos nativos. Não exigir `credit_notes/view` de um usuário autorizado apenas a ler a fatura; verificar referências internamente sem hidratar uma nota de crédito não autorizada.

## Consumidores integrados e contrato de revisão

| Consumidor | Integração427 |
|---|---|
| `InvoicePaymentWriter::companyReceiptLocked` | saldo único antes/depois; `prepare` antes de gravar, componente antes de confirmar status/recibo; idem provider/manual; retry integral |
| `CompanyCreditWriter::invoiceRemainder` / refreshInvoice / apply-unapply-manual_reverse | helper único, extinções nunca viram crédito disponível, reversão manual continua limitada ao canal manual |
| `CompanyInvoices` | participantes, fingerprint inclui composição/saldo ou veto equivalente, paymentsDTO explicita principal/encargo/desconto/abatimento, edição/cancelamento não ignora extinção |
| `InvoiceIssuerDocuments::readForPdf` | converter leitura para `IssuerTransaction`, incluir participantes do helper, devolver saldo verificado antes do DTO |
| `InvoiceIssuerDocumentData` / `Issuer_invoice_pdf` | nominal fiscal conserva valor; novo quadro financeiro separa principal recebido, extinções, crédito e a receber; encargos fora dos tributos/itens fiscais |
| GUI `admin/companies/invoices.php` | trocar “valor original/saldo” ambíguo por principal original/após estornos; exibir composição e saldo de dívida; não habilitar edição por ausência de cash quando existe extinção |
| `CompanyReports` / CSV / `CompanyApiService` | usar helper; received é principal, acrescentar extinções/encargo/gross/net observado e cobertura específica; unknown não zero; mesmo snapshot em JSON/PDF |
| `CompanyFinancialJournal` / `CompanyLedgerReports` | preservar receipt_recorded como principal; novo evento de componentes pode expor encargos/extinções observados separadamente, sem duplicar principal ou virar GL/fiscal/caixa bancário |
| `CompanyPortalInvoices` / portal index / downloads | mesmo DTO e labels privados, origem persistida antes de qualquer saldo; não usar leitor legado |
| BB `ReviewedComponentsReceipt`, `ReviewedReceipt`, `ApiCreditedReceipt` | fonte final allowlist exata; helper sob lock; retry capturado antes da matemática; mesma chave canônica previne duplicação entre perfil antigo/novo; eventos adversos mantidos |
| BB `Origination` e operações API/emissão/alterações | saldo único em checks de elegibilidade; nenhuma emissão com fatura já parcialmente extinta |
| Asaas `CompanyService` / `CompanyReceivedReceipt` | saldo e ausência de efeitos nativos não podem ignorar extinções; não conciliar outro provedor em fatura com origem BB |
| Esquemas, migration427, fixtures e versões | schema aditivo, sem backfill de NULL/legado; FK/engine/index confirmados; cleanup componente antes de payment/invoice/registry |
| API/GUI/CSV e testes de concorrência | parcial+desconto, quitação, excesso, crossA/B/principal/NULL, moeda alterada, replay entre atores/canais, falha tardia, dois waiters reais, corte/journal, página/arquivo privado |

Pesquisa complementar deve abranger todos os SELECT/SUM de `invoicepaymentrecords` e `credits` em consumidores nomeados, não apenas os locais acima. Rotas genéricas continuam fechadas para empresas positivas. CNAB400 e fórmulas de tarifas/IOF permanecem bloqueados por contrato externo explícito.

## Rota e contabilização local

`settle_components_event` é uma ação POST/CSRF com contexto e capability própria
`reconcile_components`. Exige conciliação BB, fatura/view e payments/create, política
de composição explícita e confirmação independente do crédito pelo operador. A
origem/snapshot/layout/conta/ambiente/contrato e os vetos adversos são revalidados
sob a transação canônica. Essa confirmação não autentica tecnicamente o upload.
A chave de recibo coincide com a do crédito240 antigo; alternar o perfil ou repetir
com outro ator autorizado não duplica pagamento. Retry usa a composição capturada.

O diário guarda principal e composição como fatos separados. Relatório, API, PDF,
fatura administrativa e portal distinguem caixa, extinção sem caixa, encargo
agregado, bruto e líquido observados. Valores desconhecidos não viram zero. O
helper confere o conjunto inteiro de recibos e pagamentos: um recibo cujo pai
diverge do pagamento invalida posição e nova mutação, mesmo com FKs individuais
válidas. Saldo de créditos nativos e reversões manuais é conferido no mesmo lock.

Migração427 cria os componentes sem backfill nem adoção de NULL; BB113 é marcador
nativo NOOP funcional, requer núcleo4.2.7 e preserva101–112. Asaas e BB verificam
o saldo na reserva e antes do envio, sem ignorar composição órfã ou corrompida.

Permanecem técnicos: reversão financeira própria deste recibo, crédito API parcial
com identidade individual estável, separação de juros/multa do agregado U18–32,
tarifas/IOF/outras despesas/créditos e a equação geral de líquido. Zero caixa não
é uma baixa suportada. O perfil atual não resolve contratos400 nem autoriza
transporte bancário. Todos os eventos de teste são sintéticos.
