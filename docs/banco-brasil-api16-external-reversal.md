# Estorno externo integral declarado de recibo API16 por componentes

Este fluxo registra a declaração independente do operador de que uma restituição externa integral já terminou e recompôs todo o principal do recibo. Não envia comando, não autentica uma restituição no Banco do Brasil e não transforma uma ocorrência de cheque devolvido em estorno genérico.

A fonte final específica `Api16ReviewedExternalReversal` exige o recibo API16 canônico original, seu pagamento, origem API/NN20, perfil, conta, ambiente, documento e composição imutáveis. O detalhamento original deve reconstruir os mesmos valores e data; observações posteriores contraditórias, parciais, eventos adversos ou outra origem/provedor continuam vetados. Operações de alteração/baixa ainda incertas exigem revisão mesmo quando anteriores à consulta; a declaração não supera esse bloqueio.

O operador confirma separadamente a restituição externa concluída e a recomposição integral, com data, referência e motivo. O valor devolvido é o pago total, que inclui juros recebidos; o principal recomposto inclui caixa, desconto e abatimento extintivos. Uma devolução parcial, valor de caixa no lugar do pago ou diferença de um centavo não satisfazem esse contrato integral.

A permissão nova `external_reverse_api_components` não é concedida implicitamente por `external_reverse`. Exigem-se também `reconcile`, `reconcile_api_components`, acesso à fatura e exclusão de recebimentos. O formulário usa a rota existente `declare_external_reversal`, com canal explícito `api16_components`; os recibos CNAB preservam sua fonte e seu contrato anteriores.

O writer canônico conserva as linhas originais de pagamento, composição e recibo API e acrescenta a reversão, sua prova e o diário na mesma transação. A base é `bb_api16_full_external_reversal_declared_v1`, distinta da declaração CNAB; a referência externa compartilha o namespace BB por conta/ambiente para impedir reutilização entre canais. A leitura apresenta original, revertido e vigente; desconto e abatimento voltam ao saldo sem criar nota de crédito fictícia. A repetição da mesma declaração reutiliza a chave e o autor originais; provas diferentes conflitam.

A evidência automatizada utiliza MariaDB, PHP, HTTP, Chromium e TCPDF reais com protocolo bancário injetado e dados sintéticos. Isso verifica o código local e sua integração, sem representar homologação bancária ou comprovação de uma restituição externa real.
