# Boleto CNAB: contrato técnico localizado, implementação pendente

A impressão CNAB ainda não está implementada nesta PR. A configuração API e a
impressão de documento retornado pela API não comprovam impressão local CNAB.

Fontes oficiais consultadas em 6/11/2026:

- [Especificações técnicas BB, maio/2019](https://www.bb.com.br/docs/pub/emp/empl/dwn/Doc5175Bloqueto.pdf), anexo IX: convênio de sete posições, Nosso Número com convênio e sequência de dez posições; campo livre com seis zeros, Nosso Número e carteira. Sem DV próprio do Nosso Número neste perfil.
- [Cobrança BB](https://bb.com.br/site/pro-seu-negocio/cobranca-ou-boletos/): o fator de vencimento reiniciou em 1000 em 22/02/2025. Não usar a tabela histórica de datas do PDF para vencimentos atuais.

O manual define código numérico de44 posições, linha digitável, módulo11/módulo10,
código de barras intercalado2de5 e suas dimensões físicas. Exige validação bancária
dos boletos produzidos fora do banco; desenvolvimento e testes sintéticos não a
substituem.

A implementação deverá usar a origem e o Nosso Número já reservados, o snapshot
imutável da fatura e a identidade do beneficiário. Imprimir não pode fechar lote,
regenerar arquivo baixado, reservar outro número ou declarar registro no banco.
Deve revalidar empresa, ACL, integridade e elegibilidade financeira; recusar origem
estrangeira, cancelada ou com valor divergente. O primeiro download da remessa
continua sendo o único evento que congela o lote aberto.

Testes ainda necessários: vetor bancário independente, fator/valor/DVs, impressão
com dimensões físicas, sandbox claramente identificado, acesso cruzado/revogação,
concorrência com emissão/download e igualdade dos bytes congelados antes/depois
da impressão. A emissão automática pela fatura/recorrência e o CNAB400 continuam
lacunas separadas. Nenhum boleto foi transmitido ou homologado nesta investigação.
