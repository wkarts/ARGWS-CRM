# Esclarecimentos posteriores ao contrato recuperado

O arquivo contract.md permanece integral e sem alterações.

Em 6/11/2026 o usuário esclareceu que cartão deve seguir a documentação oficial,
com guia específica para parâmetros no Asaas em Meios de pagamento, sem impor
um modelo engessado. Indicou explicitamente:

- https://docs.asaas.com/reference/pagar-uma-cobranca-com-cartao
- https://docs.asaas.com/reference/comece-por-aqui
- https://docs.asaas.com/reference/pagar-uma-cobranca-com-cartao-de-credito

Pediu explorar a documentação e aplicar ao SDK o que não existir. Isso supera a
pergunta intermediária que limitava a escolha a portal versus operador; não há
resposta pendente obrigatória a essa pergunta. Não equivale a homologação ou
execução de cobrança real. O delta SDK está restrito ao suporte de cartão.

O usuário também informou que as PRs anteriores haviam sido publicadas. A
consulta remota confirmou PR62 merged e main769840ad; a nova branch foi
sincronizada mantendo o trabalho local e a sequência490→491.

## BB142 — emissão atômica por fatura e recorrência

Nova instrução opt-in nos formulários nativos: contrato declarado, fuso explícito,
pagador e declarações400. O escritor canônico gera a origem, boleto e lote na
mesma transação da fatura; a série reaproveita esse fluxo sem alterar faturas
anteriores ou Produtos. A migration142 cria somente o ledger de vínculo e
preserva141. Veja [contrato/evidências](bb-invoice-issuance.md).

CIce2 confirmou interface nativa, PIGE, Asaas e reajustes, mas o monitor detectou
falhas nas shardsSQL bancárias: a lista histórica de tabelas esperadas não
contemplava os envelopes141. Corrigida e validada com upgrades reais até142.
Não se declarou CI geral verde; a nova rodada deve validar o novo SHA.
