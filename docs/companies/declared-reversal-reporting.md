# Posição e histórico de estorno externo declarado — etapa 428

Esta etapa registra uma declaração independente do operador sobre um estorno externo integral de um recibo BB com componentes da etapa 427. Não executa nem autentica operação no Banco do Brasil. Confirmação, referência e motivo informados pelo operador não se transformam em identificação bancária autenticada. Não há suporte implícito a estorno parcial, a recibos genéricos antigos, a chargeback ou a ocorrência CNAB genérica.

## Contrato financeiro

Exemplo restrito: principal recebido em dinheiro **90,00**, desconto **7,00**, abatimento **3,00**, encargos agregados **5,00**, pago/líquido observados **95,00** e dívida extinta **100,00**. A declaração integral registra devolução de **95,00**, reversão de principal em dinheiro de **90,00**, reversão de extinções sem caixa de **10,00** e restabelecimento de dívida de **100,00**. Não cria nota de crédito. O pagamento nativo de 90,00, seu recibo, sua composição e os eventos originais continuam preservados.

`CompanyInvoiceBalance` é a única projeção consumida pelo relatório de posição. `received` é o principal efetivo após estornos; `received_original`, `received_reversed`, `received_manual_reversed` e `received_external_reversed` conservam a decomposição. Desconto e abatimento também possuem campos ativos, originais e revertidos. Um estorno externo integral não permite inferir que a conta bancária possui saldo zero.

`component_observations` mantém os totais **originais** dos recibos com composição. Os novos grupos `declared_reversals` e `active` têm contagens e totais próprios. Valores monetários de um grupo sem recibos são `null`, exibidos como “Não observado”; isso informa a ausência de recibos nesse grupo, e não saldo da conta bancária. Moedas não são convertidas. As bases de unidade monetária preservadas continuam explícitas. Os campos e as colunas anteriores permanecem disponíveis; os detalhes novos são aditivos.

## Diário e relatório por período

O evento imutável `bank_external_reversal_declared` possui magnitude igual ao principal da dívida restabelecido. O diário valida data, moeda/base, pagamento pai, recibo de reversão, payload exato e posição canônica na mesma transação. Sua chave deve ser exatamente `sha256('company-journal:external-bank-reversal:' . reversal_request_key)`; outra chave não pode repetir a mesma declaração. Todas as tabelas do helper participam da transação de eventos de pagamento, incluindo recibos, reversões, créditos e projeções nativas.

O relatório não soma a magnitude 100,00 como dinheiro. As pernas do exemplo são: recebimento nominal 90,00 preservado; reversão de recebimento 90,00; recebimento efetivo reduzido em 90,00; desconto/abatimento ativos reduzidos em 7,00/3,00; dívida restabelecida 100,00; encargos/pago/líquido revertidos declarados 5,00/95,00/95,00. As observações originais de encargos/pago/líquido continuam originais; os indicadores `*_after_declared_reversals` são projeções locais separadas. Não são fluxo de caixa bancário ou escrituração fiscal.

As pernas usam a data do estorno declarado, enquanto `recorded_at` permanece UTC. Um período contendo somente o dia do estorno pode ter efeito negativo em dinheiro e em extinções sem caixa. Corte UTC e marcador por empresa continuam fixos nas páginas. A cobertura atual inclui o domínio `bank_external_reversal`; uma fonte própria sem evento no corte é uma lacuna explícita, sem filtrá-la por um JOIN que a esconda.

O CSV privado do diário acrescenta `financial_legs_json`, `source_before_json` e `source_after_json`, permitindo distinguir magnitude e efeitos sem depender de um rótulo genérico. O CSV da posição acrescenta os totais originais/revertidos e os grupos de composição. Ambos confirmam auditoria antes de entregar bytes e revalidam autorização.

## Guardas e testes

Os guardas existentes de BB e Asaas consomem o helper compartilhado e sua lista dinâmica de participantes. O histórico de recebimento continua vetando nova emissão/cancelamento/alteração bancária quando essa operação exige ausência de movimento, mesmo depois de uma declaração ter reaberto toda a dívida. Não se apaga histórico para liberar novo envio. Evidência de reversão adulterada falha antes de uma decisão financeira.

Suites novas:

- `tests/companies/bank_reversal_reports.php`: posição, pernas por data, corte/marcador, moeda, cobertura órfã, chave canônica do diário, CSV/auditoria, guardas de autorização e origem A/B/principal/NULL.
- `tests/companies/bank_reversal_provider_guards.php`: guardas privados reais antes de envio BB, histórico preservado e prova adulterada.
- `tests/companies/bank_reversal_report_views.php`: renderização das views reais e escape de identificadores, com I/O de framework sintético.

Essas suites compõem `bank_reversal_writer_core.php`, de posse do domínio bancário, e dependem da tabela nova `company_bank_receipt_reversals` criada antes do primeiro escritor financeiro. O schema/DDL e a integração da fonte final pertencem ao pacote bancário 428. O diário genérico não necessita alterar eventos antigos ou adotar documentos sem empresa persistida.

Validação local: SQLite **196 verificações cumulativas** (160 do compositor + 27 de relatório/diário + 5 de guardas + 4 de views). Regressões na cópia isolada: recibo/pai 142, leitores/DTO/TCPDF 176, posição/CSV 114, diário/período 137, controller/CSV/renderer 161, guardas BB/Asaas 160, API 135 e writers/diário 139. Os testes reutilizam fixtures sintéticas e não comprovam homologação bancária, execução externa, provisionamento real da autoridade mestre, SMTP ou browser autenticado. MariaDB e a fonte final BB são validados separadamente na composição 428.
