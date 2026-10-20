# Estado da composição442 — Banco do Brasil

O núcleo442/4.4.2 usa BB121/1.2.1. Este resumo substitui somente as indicações antigas de próximo trabalho abaixo; as capturas normativas anteriores permanecem históricas. Campos habilitados têm perfil/conta/convênio/carteira/variação explícitos e confirmação posterior por consulta, sem inferir recebimento financeiro.

| Fluxo | Implementação vigente | Limite |
|---|---|---|
| Juros diários/mensais de emissão | Perfil explícito, validado | PATCHjuros/multa ainda depende do contrato inequívoco dos campos |
| Abatimento, endereço operacional, primeiro desconto | BB117–119 | PATCH não altera a fatura nem confirma operação sem consulta |
| Janela de desconto após vencimento, alteração/cancelamento/primeira atribuição | BB120, somenteperfil explicitamenteS | Não amplia automaticamente perfil legado nem calcula crédito de recebimento |
| Reversão integral declarada API16 | BB119 | Declaração local não autentica estorno bancário |
| Seu Número operacional | BB121: alias tipado por origem, outbox e confirmação por consulta de conta; histórico fiscal preservado | Recorte local ASCII1–15; operação incerta não reenvia; não homologa o banco |
| Nominal, protesto/negativação | Adapter/outbox/estados e confirmação ainda incompletos | Não são apenas homologação |
| API18/19 | Efeito financeiro parcial não implementado | Faltam ID estável por crédito e semântica individual/cumulativa de valorCreditoCedente |
| CNAB240 | Remessa e conciliação assistida nominal/parcial/componentes existentes | Tarifas/IOF/outros e reversões parciais continuam exigindo adapters/equação próprios |
| CNAB400 | Codec candidato e parser, canal operacional indisponível | Faltam os três documentos abaixo e a vertical operacional própria |

InsumosCNAB400 indispensáveis: códigoBRL nas posições85–86CBR641; documento da conta/convênio/carteira/variação com isenção inequívoca ou parâmetros dos juros herdados; errata/explicaçãoBB do totalCBR64326–39 (14posições versus9(013)v99). TrailerCBR641 já resolvido. Não pedir nem registrar segredos naPR.

Nenhuma homologação externa é alegada. Provas locais e CI devem ser vinculadas aoSHA publicado.

---

## Histórico anterior preservado

# BB — implementação versus contrato e validação externa

Estado lido: composição436 e pacotes437 independentes, inclusive abatimento registrado agora congelado. Não é declaração de conclusão integral. Primário OpenAPI3.2.2 capturado: https://api.externo.bb.com.br/docs/v1/openapi/17395705/2, SHA256 `6767b098b13bc00e42700d80435a73e1f6ff4d31d7a28647926209a58d5f00d3`.

| Operação | Evidência primária | Situação real |
|---|---|---|
| Emitir isento/diário/mensal; primeiro desconto fixo/percentual | Registro.jurosMora/desconto + detalhamento tipo/valor/taxa/data | Implementado e testado; oferta não calcula recebimento. |
| Consultar título, conta e página de movimentos; pedir baixa; alterar vencimento | GET/PATCH/baixar/listar-retorno-movimento oficiais | Implementado; enviar pedido não confirma resultado final. |
| Recuperar sending interrompido | GET título + conta; claim/lease local durável | Implementado e comprovado com SIGKILL real; não reenvia POST/PATCH. |
| Incluir/alterar abatimento registrado | Flags e objetos abatimento/alteracaoAbatimento; GET valorAbatimentoTituloCobranca | Pacote congelado437: serviço, GUI, controller, races, fence e HTTP13 PASS. Sem lançamento financeiro automático. |
| Alterar endereço operacional do pagador | alteracaoEndereco: enderecoPagador/bairroPagador/cidadePagador/UFPagador/CEPPagador; GET cinco campos correspondentes | Código interno viável, próximo overlay. Não altera identidade/endereço fiscal da fatura. |
| Alterar/atribuir primeiro desconto ou sua data | desconto/alteracaoDesconto/alteracaoDataDesconto + GET codigoDescontoTitulo/valor/percentual/data | Código interno viável. Requer intenção e perfil de oferta efetiva tipados; não basta alterar o corpo PATCH porque o validador hoje preserva a oferta original. |
| Prazo após vencimento | alteracaoPrazo.quantidadeDiasAceite + GET quantidadeDiaPrazoLimiteRecebimento | Campo claro, mas origens atuais registram indicadorAceiteTituloVencido=N. Fluxo positivo requer novo perfil S explícito com numeroDiasLimiteRecebimento>0 e confirmação própria; não presumir que o PATCH muda N para S. Código interno possível nessa nova origem. |
| Juros PATCH | Schema juros atribui descrição de valor a tipoJuros, taxa a valorJuros e domínio de código a taxaJuros | Bloqueio documental exato para composição desse PATCH. Emissão mensal/diária usa outro objeto claro e já funciona. |
| Multa em emissão/PATCH | Descrições de tipo/valor/taxa/data se contradizem; tipo nominal do objeto e domínio divergentes | Bloqueio documental exato; não copiar semântica de juros. Código pode avançar após contrato primário corrigido/exemplo normativo inequívoco. |
| Alterar nominal/Seu Número | Campos PATCH existem; GET identifica título original | Contrato de transporte claro, implementação/modelo local ainda pendentes para revisão financeira/fiscal e alias tipado sem quebrar a origem/snapshot/recibo canônicos. Não chamar de homologação. |
| Protestar/sustar/cancelar protesto/negativar | Flags/objetos existem; confirmação de cada transição exige contrato específico | Pedido/outbox não implementados; requer máquina de estados e prova de transição independente. Não inferir cancelamento processado de simples sucessoHTTP ou quantidade de dias. |
| Crédito API16 nominal | Consulta16 integral com ajustes zero e conta | Implementado pelo writer canônico. |
| Crédito API16 integral com juros/desconto/abatimento | Valores distintos no detalhamento + equação local explícita API | Implementado436: caixa principal separado, componentes imutáveis, líquido=pago, ID canônico, races e HTTP. IOF/multa/outros zero no recorte. |
| Parciais API18/19 | Agregado parcial/crédito sem identidade inequívoca de movimento; movimento sem ID independente | Bloqueio contratual exato para dinheiro/deduplicação; observações implementadas. Não usar delta/cursor/hash como ID bancário. |
| Estorno integral externo declarado de componentes240/API16 | Prova independente do operador + recibo original imutável | Implementados428/pacote437. Não autenticam banco nem executam reembolso BB; parcial/automático não implementados. |
| Retorno240 sobre origem API | Manual240 NN17; API NN20=000+mesmos17; contrato por origem/conta/perfil | Implementado436 com contrato/fingerprint imutáveis, fontes finais e disputa financeira real na mesma origem/fatura; sem lote fictício. |
| CNAB240 geração/retorno, nominal e composição declarada | Manual junho2026 P/Q/T/U | Recorte atual implementado; caixa/extinção/juros separados; crédito individual explícito condicionado ao saldo. Tarifas/IOF/outros e novos perfis/segmentos continuam pendentes de código/equação específica. |
| Transporte automatizado de arquivo | Leiaute de arquivo não define protocolo/endpoint/recibo contratado | Bloqueio específico de contrato de transporte. Geração/download e declaração manual auditável já existem; não inventar SFTP/API. |
| Remessa400 BRL e financeiro400 | CBR64185–86 sem BRL confirmado; juros herdáveis/carteira/variação do convênio; CBR64326–39 com14 versus picture15 | Bloqueios documentais exatos mantidos. Após esclarecimento ainda haverá código de origem/lote/snapshot/retorno/dinheiro400 a implementar. Trailer9/brancos2–394/sequência395–400 já resolvido. |
| Auxiliares observacionais400 | Registros auxiliares primários CBR643 | Decodificadores são código interno possível, com perfil e fingerprint próprios; não resolvem BRL/picture nem autorizam dinheiro400. |
| Webhook mTLS | Cadeia/pin/TLS nativo e baixa operacional | Receptor local observacional implementado; ingresso real/cadeia/pin/entrega são configuração e validação externa. Nenhum pagamento por webhook. |

Homologação externa é separada por empresa/conta/convênio/carteira/ambiente: credenciais, contrato do cliente, HTTPS/mTLS reais, respostas/autorização/aceite e arquivos reais. Não substitui nenhuma implementação interna ainda listada, e testes locais não a comprovam.
