# Alteração do primeiro desconto operacional API

Fonte primária: OpenAPI Cobranças3.2.2 capturado19/10/2026, https://api.externo.bb.com.br/docs/v1/openapi/17395705/2, SHA256 `6767b098b13bc00e42700d80435a73e1f6ff4d31d7a28647926209a58d5f00d3`. O contrato RequisicaoAlterarBoleto oferece `indicadorAlterarDesconto` e objeto `alteracaoDesconto`. O contrato local conservador `bb_api_registered_first_discount_patch_v1` altera somente o primeiro desconto já registrado, mantendo tipo1 fixo ou tipo2 percentual e segundo/terceiro descontos comprovadamente zero.

| Intenção | Campo PATCH | Campo GET independente |
|---|---|---|
| Tipo original1/2 | tipoPrimeiroDesconto | codigoDescontoTitulo |
| Centavos fixos | novoValorPrimeiroDesconto | valorDescontoTitulo |
| Taxa percentual exata | novoPercentualPrimeiroDesconto | percentualDescontoTitulo |
| Data limite | novaDataLimitePrimeiroDesconto | dataDescontoTitulo |

Todos os outros indicadores ficam N. O tipo é inteiro JSON; somente o campo de valor ou de percentual correspondente é enviado como número JSON decimal exato, sem float de cálculo. A data usa a representação bancária d.m.Y já suportada. Valor fixo deve ser positivo e menor que o nominal; percentual de0.01 a99.99, sem conversão em BRL. Data fica entre hoje/emissão e o vencimento bancário efetivo. Não habilita desconto por antecipação diária, cancelamento da oferta, troca de tipo nem segundo/terceiro desconto. Esses contratos não são presumidos da existência de campos no schema.

A capacidade nova api_amend_discount não é implicada por api_discount, api_amend, endereço ou abatimento. Visualizar/editar a fatura, empresa selecionada e CSRF também são obrigatórios. A intenção é tipada, versionada, imutável e vinculada ao hash da emissão, perfil e ambiente originais, com confirmação/referência/motivo explícitos. Exige consulta NORMAL recente, conta independente confirmada e ausência de história financeira/parcial/adversa e instruções pendentes.

PATCH200 confirma apenas o pedido. Consulta posterior exige tipo, valor ou percentual, data e demais ofertas zero, além de prova da conta original. Resposta perdida permanece incerta; retry da intenção não repete PATCH. A permissão e versão da credencial são revalidadas imediatamente antes de sending. Confirmação de leitura é atômica com o resultado/auditoria; claims de leituras expirados usam a recuperação aceita, sem apagar mutadores enviados.

A oferta operacional efetiva vem da instrução confirmada pela consulta, preservando o payload/snapshot/perfil da emissão. Uma nova intenção ainda não observada permite consultar a última oferta confirmada, sem confirmar a intenção nova. Divergência da última oferta confirmada exige revisão, sem regressar silenciosamente à oferta original. Alteração futura de vencimento preserva a janela da oferta efetiva, não apenas a primeira emissão. O fence financeiro exige o contrato tipado, fingerprint e consultas independentes tanto históricas quanto a consulta financeira atual. Pending/unknown e prova forjada continuam bloqueados.

A fatura conserva nominal e identidade fiscal; oferta não é desconto efetivamente utilizado. Nenhum pagamento, nota de crédito, abatimento ou redução financeira é lançado pela instrução. API16 usa sua fonte canônica de recebimento real já existente e sua equação/autoridade própria, sem inferir dinheiro da taxa ou desta oferta. Parciais18/19 e campos contraditórios de juros/multa continuam fora deste incremento.

Operação amend_disc cabe no varchar12 atual: nenhum DDL ou versão/migration é necessário neste patch diferencial. O coordenador integra api_amend_discount nos catálogos e a rota exata amend_api_discount no CompanyRequestScope. Testes usam PHP/MariaDB/HTTP/Chromium reais e protocolo bancário simulado somente em fixtures descartáveis; não houve credenciais, pedidos reais ao BB, autenticação externa ou homologação.
