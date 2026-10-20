# Crédito API16 integral por componentes

A fonte final `BancoBrasil\ApiComponentsReceipt` permite confirmar um crédito API integral com juros recebidos, desconto utilizado e abatimento concedido. Usa o escritor financeiro canônico e uma decomposição imutável própria da API. O fluxo nominal anterior e o basis CNAB240 permanecem separados. Não aplica parciais18/19 nem usa movimentos sem identificador como autoridade de dinheiro.

## Evidência primária e política local

OpenAPI BB Cobranças3.2.2, origem https://api.externo.bb.com.br/docs/v1/openapi/17395705/2, SHA256 da cópia primária `6767b098b13bc00e42700d80435a73e1f6ff4d31d7a28647926209a58d5f00d3`. `RespostaDetalhamentoBoleto` identifica os seguintes campos:

| Campo | Significado declarado no contrato |
| --- | --- |
| valorOriginalTituloCobranca | Nominal indicado no registro |
| valorPagoSacado | Valor pago |
| valorCreditoCedente | Líquido creditado ao beneficiário |
| valorJuroMoraRecebido | Juros recebidos |
| valorDescontoUtilizado | Desconto utilizado pelo pagador |
| valorAbatimentoTotal | Abatimento concedido |
| valorPagamentoParcialTitulo | Valores já recebidos em pagamentos parciais |

A OpenAPI não publica uma equação líquida universal nem a identidade individual necessária aos parciais. Este perfil aplica uma **equação local explícita**, com confirmação independente e referência/motivo do operador, apoiada nos significados acima. Não atribui uma fórmula não publicada ao banco:

- caixa principal = pago − juros recebidos;
- caixa principal + desconto utilizado + abatimento concedido = nominal integral;
- líquido observado = pago observado;
- caixa principal positivo e saldo local integral intacto;
- IOF, multa, reajuste, outros recebidos e agregado de parciais explicitamente zero.

Valores ausentes não viram zero. Qualquer centavo divergente, diferença líquido/pago, estado6 sem crédito16, natureza condicional, histórico parcial18/19, IOF/multa/outros, movimento adverso ou crédito de outro canal exige revisão. Não calcula juros ou o valor monetário de uma oferta percentual. Não deduz uma tarifa implícita da diferença entre pago e líquido. As posições `fees/iof/other_expenses/other_credits=0` são restrições deste basis, não prova externa de que o contrato bancário não possui tarifas.

## Identidade, autorização e atomicidade

O basis novo é `bb_api16_explicit_full_equation_v1`, o source armazenado `bb_api16` e o modo nativo `bb_api16_components_reviewed`. CNAB240 conserva `bb_cnab240_explicit_local_equation_v1` e seu modo anterior. A API usa a mesma chave canônica por título imutável da fonte nominal API, independente de ator, consulta ou interface, impedindo dois recebimentos entre as políticas. Uma política não adota um recibo antigo da outra.

A capacidade nova `banco_brasil.reconcile_api_components` soma-se a view/reconcile, invoices/view e payments/create. Autoridade CNAB de componentes não concede automaticamente autoridade API. A confirmação pede crédito independente, política local explícita, referência e motivo. A intenção persistida inclui esses dados na evidência; uma prova diferente para o mesmo crédito conflita.

O estado16 deve estar na consulta mais recente, validada, vinculada à conta/convenção/ambiente da origem, com prova independente de conta. Primeiro registro exige consulta recente. Replay revalida o acesso e a prova; uma nova consulta idêntica não gera nova chave financeira. Upload, webhook, cabeçalho HTTP, alteração de status e simples observação6 não substituem a fonte final.

Em uma única transação, o writer conserva caixa principal nativo, recibo canônico, componente imutável, status/saldo, diário e ledger do provedor. Desconto/abatimento não geram nota de crédito artificial; juros recebidos não se tornam principal da fatura. Falha tardia reverte tudo. PDF, portal autorizado, relatório/CSV e API usam o mesmo helper de posição; não há SQL financeiro paralelo.

## Escopo e integração

Diferencial após os incrementos mensal/fixo/percentual. A raiz deve registrar a nova capacidade nos catálogos/header e serializar versões. Não exige nova coluna/tabela: os campos textuais existentes armazenam source e basis próprios. O controller usa a rota já permitida `settle_api` com `receipt_policy=api16_components`; o servidor exige a capacidade adicional e não degrada para a política nominal se a entrada é desconhecida ou incompleta.

Estorno API por componentes, recebimento parcial, tarifa/IOF/outros líquidos, adaptações CNAB240 sobre uma origem API e outros PATCH continuam separados. O estorno externo428 permanece no recorte CNAB240 originalmente entregue; este incremento não o rotula como reversão API nem relaxa o veto adverso.

Testes com PHP/MariaDB/HTTP/Chromium reais e protocolo BB simulado são prova de código local. Não houve credencial real, chamada bancária, transação BB, merge, deploy ou homologação externa.
