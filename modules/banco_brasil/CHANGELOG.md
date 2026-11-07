# 1.4.4

- Emissão CNAB automática: espécie recusada pelo perfil gera erro de entrada com rollback integral, em vez de erro genérico de armazenamento. Os códigos aceitos pelo gerador permanecem inalterados.
- Migration144 NOOP; nenhum backfill, transmissão, publicação ou pagamento automático.

# 1.4.3

- Publicação explícita de boletos CNAB240/400 no portal, usando contato nativo e revisão do instrumento; revalida o responsável, saldo e origem.
- Revisar/publicar/ocultar sem congelar remessa, criar cobrança ou registrar pagamento. Impressão com nonce sob a CSP do portal.
- Migration143 NOOP preserva101–142 e usa o ledger124 sem backfill. Requer núcleo4.9.2 para a entrega pelo portal.

# 1.4.0

- Boleto CNAB400 imprimível a partir da preparação e do arquivo validados antes da transmissão.
- Usa origem/pagador do registro400, com saldo, empresa e ACL revalidados; não marca download nem aceite.
- Migration140 NOOP; preserva139 e todo o histórico. Agrupamento400 permanece pendente.

# 1.3.9

- Boleto CNAB240 imprimível a partir de origem reservada, sem transmissão ou registro implícito.
- Revalida empresa, ACL, snapshot, saldo e cancelamento; impressão não congela lote.
- I25 e linha digitável do convênio7/carteira17; fator reiniciado em2025. Validação bancária permanece necessária.
- Migration139 NOOP;138 e histórico preservados.
