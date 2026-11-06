# Boleto CNAB240 imprimível — módulo1.3.9/139

Implementada impressão local CNAB240 pelo menu operacional de lotes, com ID da
fatura pertencente ao lote. Disponível antes da transmissão e do primeiro download.
Origem reservada ou gerada, carteira17/convênio7, valor integral e moeda BRL.
O boleto distingue geração de registro não confirmado; sandbox informa NÃO PAGAR.
A configuração API e a impressão API não homologam este fluxo.

Fontes oficiais consultadas em 6/11/2026:

- [Especificações técnicas BB, maio/2019](https://www.bb.com.br/docs/pub/emp/empl/dwn/Doc5175Bloqueto.pdf), anexo IX: convênio de sete posições, Nosso Número com convênio e sequência de dez posições; campo livre com seis zeros, Nosso Número e carteira. Sem DV próprio do Nosso Número neste perfil.
- [Cobrança BB](https://bb.com.br/site/pro-seu-negocio/cobranca-ou-boletos/): o fator de vencimento reiniciou em 1000 em 22/02/2025. Não usar a tabela histórica de datas do PDF para vencimentos atuais.

O manual define código numérico de44 posições, linha digitável, módulo11/módulo10,
código de barras intercalado2de5 e suas dimensões físicas. Exige validação bancária
dos boletos produzidos fora do banco; desenvolvimento e testes sintéticos não a
substituem.

A implementação usa a origem e o Nosso Número já reservados, o snapshot
imutável da fatura e a identidade do beneficiário. Imprimir não pode fechar lote,
regenerar arquivo baixado, reservar outro número ou declarar registro no banco.
Revalida empresa, ACL, integridade e elegibilidade financeira; recusar origem
estrangeira, cancelada ou com valor divergente. O primeiro download da remessa
continua sendo o único evento que congela o lote aberto.

Testado em MySQL8.4:277 verificações cumulativas, vetor sintético fixo, limites de
fator/valor, acesso cruzado, revogação, alteração de saldo/cancelamento e bytes
congelados preservados. Concorrência real:274 checks, impressão/download e revogação
com dois processos aguardando o mutex. Chromium: ficha190x105mm, I25 103x13mm, barras
escaladas sem corte, zonas de silêncio, linha completa e nenhum recurso externo.
São dados sintéticos, não vetor homologado pelo banco nem HTTP autenticado completo. A emissão automática pela fatura/recorrência e o CNAB400 continuam
lacunas separadas. Nenhum boleto foi transmitido ou homologado nesta investigação.

Migration139 NOOP; histórico e dados preservados. O contrato de impressão não
altera a migration138 nem congela o lote. Testes de tracking/falha/replay até139
foram executados pelo runner nativo no fixture MySQL.

## Extensão CNAB400 — módulo 1.4.0/140

A preparação400 com arquivo gerado e validado pode produzir o mesmo documento
imprimível antes de qualquer download/transmissão. O serviço recupera Nosso Número,
valor, datas e identidade imutáveis da origem; nome/endereço/espécie/aceite vêm do
registro400 validado, sem novo payload do navegador. A declaração do operador
exigida pela geração permanece explícita e não equivale a homologação automática.
Preparação apenas reservada ainda não possui o registro completo do pagador e
não libera impressão. O documento continua indicando registro não confirmado.

A operação usa POST com contexto/CSRF, ACL revalidada sob mutex, saldo nativo,
origem ativa e snapshot corrente. Não altera lote, contadores, fatura, pagamentos
ou auditoria. Reimpressão não altera bytes da remessa. Migration140 é NOOP e não
reescreve139. Agrupamento400, emissão automática e portal do cliente permanecem
pendentes; esta extensão não os representa como concluídos.
