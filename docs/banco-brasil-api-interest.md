# API BB: perfil de juros diários fixos

O contrato primário é a OpenAPI3.2.2, catálogo17395705/2, SHA256
`6767b098b13bc00e42700d80435a73e1f6ff4d31d7a28647926209a58d5f00d3`.
`RequisicaoRegistroBoletos.jurosMora` define tipo1 como valor por dia de atraso,
tipo2 como taxa mensal e tipos0/3 como dispensa/isenção. Este incremento implementa
somente tipo1, BRL fixo por dia, sem inferir taxa mensal, capitalização ou padrão
do beneficiário. A definição de multa possui descrições divergentes internas;
esta ampliação não as resolve por suposição nem habilita multa.

Na GUI, declare um perfil separado com política “Valor fixo em BRL por dia de
atraso”, referência contratual e confirmação explícita. A declaração continua
sendo `operator_declaration_not_bank_verification`. Exige BB Gerenciar e a nova
permissão BB Declarar e emitir juros API (`api_charges`). Perfis anteriores isentos
mantêm definição/hash e semântica; a nova seleção “Isento” também os reutiliza.
A faixa de numeração compartilhada entre API/CNAB não é reiniciada pelo novo
perfil e credenciais continuam próprias por perfil/ambiente.

Na emissão, informe o valor fixo diário positivo em BRL, com no máximo duas casas
e limitado ao nominal do boleto. Essa é uma limitação conservadora deste recorte,
não uma alegação de limite bancário universal. Perfil isento rejeita valor de
juros; perfil diário rejeita ausência/zero/excesso. Tanto emitir quanto retomar o
envio exigem a nova permissão, revalidada antes do marcador de envio. Quantias são
normalizadas antes do hash idempotente e emitidas como número JSON exato, sem
conversão por ponto flutuante. Não se envia texto livre de juros na mensagem para
contornar o campo estruturado.

Consulta de um título com juros diários exige tipo1, valor fixo igual ao payload
imutável e percentual zero no detalhamento, além das provas já existentes de
conta, título, ambiente, convênio, snapshot e paginação. Resposta insuficiente ou
divergente permanece incerta; não redefine o contrato original.

A fatura conserva o nominal. A emissão com política de juros não habilita lançar
encargos recebidos no financeiro. `ApiCreditedReceipt` continua exigindo todos os
ajustes zerados; juros recebidos ficam em revisão, sem serem ignorados para fechar
a fatura e sem serem somados ao nominal de forma oculta. A escrituração de encargos
recebidos, tarifas, multas, parciais e estornos bancários continua pendência técnica
separada, com ledger e evidência próprios; não é chamada de “homologação”.

A OpenAPI descreve estados18 e19 e o total já recebido em pagamentos parciais,
mas não determina claramente se `valorCreditoCedente` representa último crédito ou
acumulado. O retorno de movimento documentado fornece campos úteis e paginação,
mas não um identificador inequívoco do movimento individual. Essas lacunas de
semântica/deduplicação precisam ser resolvidas antes de atribuir autoridade
financeira aos parciais API; códigos ou diferenças de saldo não serão usados como
um identificador financeiro inventado.

Testes: `tests/banco_brasil_api_interest.php` valida contrato e dinheiro exato;
`tests/banco_brasil_api_interest_mysql.php` compõe tabelas nativas, perfil/payload,
consulta, replay, recusa em perfil isento/sem permissão e preservação do financeiro
quando há juros recebidos. O transporte bancário é simulado. Os resultados
executados devem ser registrados no HEAD efetivo. Homologação real por
conta/convênio/variação permanece externa.

Upgrade112 é aditivo de comportamento e usa migration nativa NOOP, sem editar os
passos101–111. Não há backfill ou reinterpretação de perfis antigos. Rollback
operacional futuro deve retirar a permissão de juros e preservar os perfis/origens
e instruções já enviados; voltar ao código anterior não cancela juros no banco.
Não foi feito merge, deploy ou operação bancária real.

## Consulta de movimentos pela origem

`query_api_movements` usa o endpoint oficial
`POST /convenios/{id}/listar-retorno-movimento`, com OAuth
`cobrancas.boletos-info`, conta/agência/carteira/variação da origem persistida,
janela explícita de até31 dias e até100 registros por página. A primeira posição
é1; o cursor seguinte não é calculado por suposição. A documentação chama o campo
de registro “registro ou página”, por isso a continuação e o último registro são
mostrados como observações, sem prova de ausência ou varredura completa.

A operação exige BB Consultar API e faturas Visualizar, grava intenção antes de
HTTP, revalida autorização/credencial antes do envio e usa chave/conteúdo/ator
consistentes. A resposta conserva somente os registros do título exato, com
conta/agência/convênio/controle/nominal coerentes. Campos de outros pagadores não
são persistidos; a projeção mantém nominal, recebido, tarifa, juros, desconto,
abatimento, IOF, despesas, créditos e lançamento/direção distintos. Nenhum campo
ou hash é apresentado como um identificador bancário universal de movimento.

A GUI exibe essas páginas. Movimentos adversos44/46/93 e naturezas de cheque a
compensar vetam novos créditos API/CNAB, inclusive nas representações20/17 do
mesmo título/conta/ambiente. A operação permanece observacional: não baixa fatura,
não reconhece tarifa/juros como despesa/receita e não reverte crédito anterior.

Testes adicionais: `banco_brasil_api_movements.php` (contrato/exatidão/filtragem)
e `banco_brasil_api_movements_mysql.php` (outbox SQL/owner/replay/adversidade).
O roteiro Chromium BB também declara os perfis isento e diário em A/B e mostra
provas produzidas pelo serviço com transporte simulado. Não há chamada bancária
real na fixture CLI ou no roteiro.
