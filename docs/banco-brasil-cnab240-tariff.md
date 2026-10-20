# Recebimento CNAB240 integral com tarifa positiva

A fonte `BancoBrasil\ReviewedTariffReceipt` registra o principal integral pago pelo cliente, a tarifa deduzida e o líquido bancário como valores separados. O pagamento nativo recebe o principal integral; a tarifa é observação bancária e não gera despesa fiscal automática. O upload continua observacional e exige vínculo explícito com a origem e confirmação independente do operador.

## Contrato primário e recorte

Manual BB de cobrança CNAB240, junho de 2026, `CNAB240SegPQRSTY.pdf`, página 31, Segmento U posições 93–107: o líquido é o valor pago pelo sacado menos a tarifa de cobrança. A exceção descrita para boleto descontado não faz parte deste recorte. Cópia primária consultada: 1.037.238 bytes, SHA256 `cbe4d93359eedd6d46c577d465423e05b6c8ea3accef415097cf9d5192c7af74`, origem `https://www.bb.com.br/docs/pub/emp/empl/dwn/CNAB240SegPQRSTY.pdf`.

São obrigatórios CNAB240 T/U ocorrência 06, carteira 17 simples, BRL, origem gerada e transmissão manual declarada, valor nominal da origem igual à fatura, saldo integral intacto, valor pago igual ao nominal, tarifa positiva inferior ao pago e líquido exatamente igual ao pago menos tarifa. Juros/encargos, desconto, abatimento, IOF, outras despesas e outros créditos devem ser explicitamente zero. Datas de ocorrência/crédito devem ser válidas, não futuras e coerentes com a origem. Um centavo divergente exige revisão.

A tarifa vem de T199–213, o pago de U78–92 e o líquido de U93–107. Não se deduz uma tarifa implícita nem se calcula um líquido de campos ausentes. O DTO captura o hash do contrato primário e a página. O novo basis é `bb_cnab240_full_nominal_less_tariff_v1`, source `bb_cnab240_tariff` e modo `bb_cnab240_tariff_reviewed`. Os basis CNAB240 local e API16 anteriores conservam suas equações e modos.

## Autoridade e integridade

A capacidade opt-in `banco_brasil.reconcile_tariff` soma-se a invoices/view, payments/create e banco_brasil/reconcile. A origem API não usa esta fonte. A interface exige duas confirmações explícitas, motivo e referência independente. POST, CSRF e contexto atual da empresa são verificados antes da gravação canônica.

A mesma transação verifica a conta, empresa, origem e snapshot, layout e vínculo, arquivo e payload original, ator e concessões atuais, fatura e itens/tributos, cliente/propriedade, recibo nativo, componentes e diário. A prova inclui todos os vínculos e linhas do lote, snapshots e declarações contratuais, os bytes regenerados da remessa e a declaração de envio manual. O hash desse conjunto integra a evidência persistida do recebimento: reprocessar uma confirmação antiga não pode adotar uma origem alterada entre requisições. Depois da última auditoria, imagens esperadas independentes confirmam todos os campos das novas provas de origem, revisão e auditoria, além da identidade, dos valores financeiros e das auditorias financeiras do núcleo. Falha tardia reverte tudo. Replay exato revalida autoridade e prova atual, preserva o ator original e não duplica caixa. Outro crédito concorrente não pode consumir novamente o saldo integral.

PDF interno e portal privado apresentam principal, tarifa e líquido por DTO sanitizado. Relatórios e CSV mantêm a tarifa observacional separada das despesas. A coluna de tarifa é acrescentada ao fim do CSV atual. O diário conserva principal integral e líquido bancário em métricas distintas.

## Limites e informação bancária pendente

A reversão deste novo recebimento permanece bloqueada: é necessário documento primário do BB que identifique o estorno da tarifa e do crédito, a chave do movimento de origem e a equação entre principal devolvido, tarifa devolvida ou retida e líquido debitado. Não se presume que as regras de reversão integral dos basis antigos definam esses campos. Recebimentos parciais, outras rubricas ou origens API permanecem fora desta fonte.

Não houve chamada externa ao banco, uso de credenciais reais ou homologação. As provas SQL usam PHP 8.3, MariaDB 10.11 e MySQL 8 reais com retornos sintéticos; o teste Chromium adicional requer o ambiente HTTP nativo e sua execução deve ser registrada separadamente. Não confundir validação local com validação do SHA publicado: a composição versionada e a publicação pertencem à integração serial da PR.
