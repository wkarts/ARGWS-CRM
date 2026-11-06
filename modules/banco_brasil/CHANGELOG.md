# 1.4.0

- Boleto CNAB400 imprimível a partir da preparação e do arquivo validados antes da transmissão.
- Usa origem/pagador do registro400, com saldo, empresa e ACL revalidados; não marca download nem aceite.
- Migration140 NOOP; preserva139 e todo o histórico. Agrupamento400 permanece pendente.

# 1.3.9

- Boleto CNAB240 imprimível a partir de origem reservada, sem transmissão ou registro implícito.
- Revalida empresa, ACL, snapshot, saldo e cancelamento; impressão não congela lote.
- I25 e linha digitável do convênio7/carteira17; fator reiniciado em2025. Validação bancária permanece necessária.
- Migration139 NOOP;138 e histórico preservados.
