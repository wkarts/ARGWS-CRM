# Parcelamentos Asaas: revisão por empresa e conta

Referência funcional: módulo1.5.7, commit e790c33ade267b73027c167b468f06309d5d61e6,
`AsaasReconciliation::expandInstallments`. Adaptador ARGWS: módulo1.8.1/181.

A API distingue o grupo de suas cobranças individuais:
- [Consultar parcelamento](https://docs.asaas.com/reference/recuperar-um-unico-parcelamento).
- [Listar cobranças do parcelamento](https://docs.asaas.com/reference/listar-cobran%C3%A7as-de-um-parcelamento).

## Implementado

Em Configurações > Meios de pagamento > Asaas > Monitor, informe a fatura,
conta/ambiente e ID de uma cobrança e use **Consultar parcelamento desta cobrança**.
O adaptador resolve `installment` na cobrança, consulta o grupo e seus membros
por GET sem body e sem alterar o Asaas ou o financeiro local.

A conta vem da empresa autorizada. Nenhum fallback para o gateway global ou
exigência de que empresas secundárias compartilhem a conta principal. Wallet,
cliente, referência da fatura, conta/revisão e saldo são verificados. ACL e estado
local são revalidados entre chamadas e antes de retornar o resultado.

O conjunto exige2–100 parcelas, IDs e números únicos, numeração completa, total
exato em centavos, mesmo cliente/meio/grupo, referências locais corretas e
vencimentos válidos. A parcela inicial deve coincidir com a projeção da listagem.
Respostas truncadas, divergência de centavos, estados adversos e saldo insuficiente
são rejeitados. A consulta atual exige a lista completa em uma resposta;
`hasMore=true` não é apresentado como revisão integral.

A grade apresenta cada ID, número, valor, vencimento e estado consultado. O digest
é específico da revisão de grupo e não autoriza o vínculo avulso. Não há botão
para confirmar vínculo ou recebimento nesta revisão. A apresentação escapa os
valores e não expõe credenciais. CSRF/contexto nativos permanecem obrigatórios.

## Lacuna financeira explícita

Esta entrega é consulta/revisão, não associação nem baixa de parcelamentos.
A origem ativa atual e o recibo canônico foram desenhados para cobrança única.
A próxima adaptação precisa de identidade imutável por membro, alocação de
principal e recibo por parcela, total protegido contra concorrência, operações
individuais e histórico de reembolso/recriação. Não reutilizar um ID de grupo
como ID de pagamento nem afrouxar a unicidade para simular suporte.

Grupos financeiros, ciclo das alocações parciais, cartão e paridade integral
R01–R11 continuam abertos. Isso é trabalho de código, não limitação externa
inventada. Nenhum recurso de coleta de cartão ou ampliação do SDK neste delta.

## Banco, operação e validação

Migration181 NOOP. Sem DDL, backfill, seed, cobrança, recebimento ou consentimento.
Futura implantação usa runner nativo e confirma marcador1.8.1; preservar backup e
histórico. Reverter a apresentação não exige apagar dados; não reduzir marcadores.

Runner nativo:345 assertions MySQL com falha de tracking, retry e replay.
A primeira execução de `asaas_installment_review.php` passou em63 verificações
adicionais MySQL após74 da associação,11 do lote,38 da edição e118 da base.
Provedor simulado, sem homologação financeira. A suíte foi ampliada com render
real da view e os guards do controlador; resultados finais registrados na PR.

Resultado final local:65 verificações adicionais MySQL na revisão/grade;
controlador real:8 guards após179 cumulativos das suítes antecedentes. Inclui
CSRF ausente, ID inválido e conta estrangeira. Transporte:12 verificações sem
rede recusam mutações de grupo, body em GET, host/caminho arbitrários e vazamento
do erro do provedor. Callback:13. Nenhuma cobrança ou baixa realizada.
