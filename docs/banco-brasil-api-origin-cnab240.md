# Retorno240 de título originado pela API

O vínculo exige contrato declarado especificamente para a origem, perfil API, convênio, conta, ambiente e perfil240. O operador confirma que conferiu essa contratação por fonte independente e informa referência e motivo. Versão, fingerprint, identidade do título e ator são imutáveis. A declaração do operador não é autenticação nem verificação automática pelo banco.

A OpenAPI BB3.2.2 define Nosso Número20 por prefixo000, convênio7 e sequência10 (`RequisicaoRegistroBoletos.numeroTituloCliente`). O manual BB240 junho/2026 p.11 define a representação17 por convênio7 e sequência10, sem DV. A origem continua API, com NN20, payload e snapshot originais; a representação17 serve apenas à correspondência com T/U. Nenhum lote/remessa/transmissão é inventado para suprir a ausência de originaçãoCNAB.

Após registroAPI201 e consulta recente confirmada pela conta, importa-se o retorno240 pelo fluxo existente. O operador escolhe a origem API e declara o contrato, confere os identificadores e vincula o evento explicitamente. Importação, contrato e vínculo não criam recebimento. Documento, controle, vencimento confirmado, moeda, valor nominal, conexão e identidade17/20 devem coincidir. Contratos alterados/corrompidos, origens ambíguas e histórico adverso exigem revisão.

A capacidade contextual `banco_brasil.reconcile_api_return` é específica. Declarar exige também gestão, conciliação e visualização da fatura; política composta requer `reconcile_components`. Receber exige separadamente `payments.create` e as permissões de conciliação/composição. Revogar uma dessas permissões durante a espera por bloqueio impede o respectivo efeito financeiro.

Há dois perfis financeiros integrais:

- Nominal: recebido e líquido iguais ao nominal, todos os ajustes zero.
- Composição240: caixa principal = pago − encargos agregados; caixa principal + desconto + abatimento extinguem o nominal integral; líquido = pago; tarifas, IOF e outros valores zero. Requer confirmação independente adicional da equação. Mantém o basis240 e separa caixa, extinções sem caixa e observações de encargos; não calcula juros a partir de taxa contratual.

Ambos usam as fontes finais exatas e o writer canônico, na mesma transação de saldo, pagamento, recibo, componentes e diário. A chave do título API é comum ao fluxoAPI16 e ao retorno240: um canal não duplica o recebimento do outro. Todas as consultasAPI16 históricas contrapostas precisam coincidir nos valores/data e permanecer sem campos ambíguos; uma consulta normal posterior não apaga a divergência. Estado18/19, baixa operacional, cheque devolvido/pendente e outros eventos adversos não são superados pela declaração contratual.

A baixa mantém `bb_event_receipts` com a proveniência real do retorno; não fabrica um reciboAPI. A fatura, PDF, portal, API, relatórios e diário usam a composição canônica existente. Uma referência diferente não transforma retry em nova receita.

Fontes primárias preservadas: [OpenAPI BB](https://api.externo.bb.com.br/docs/v1/openapi/17395705/2), SHA256 `6767b098b13bc00e42700d80435a73e1f6ff4d31d7a28647926209a58d5f00d3`; [manual BB240](https://www.bb.com.br/docs/pub/emp/empl/dwn/CNAB240SegPQRSTY.pdf), SHA256 `cbe4d93359eedd6d46c577d465423e05b6c8ea3accef415097cf9d5192c7af74`. Esses contratos de formato não demonstram a contratação de retorno para cada conta; essa condição permanece explicitamente declarada pelo operador.

Reversão de recibos API/API→240 e reversões parciais ainda exigem fonte própria; o estorno declarado428 de origemCNAB não foi ampliado implicitamente. ParciaisAPI18/19, tarifas/IOF/outros líquidos, fórmulas adicionais e perfis400 não são autorizados por este adaptador. Transporte automático exige o contrato específico do canal. Nenhum teste local ou transporte injetado constitui homologaçãoBB ou validação de arquivo bancário autêntico.
