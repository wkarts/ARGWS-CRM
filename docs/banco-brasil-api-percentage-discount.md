# Primeiro desconto percentual no registro API

O perfil percentual registra uma oferta até data, preservando os perfis isento, diário, mensal e desconto fixo anteriores. A capacidade `banco_brasil.api_discount` autoriza ofertas de desconto explicitamente declaradas; juros continuam exigindo `api_charges`. O código não calcula o valor em BRL do desconto percentual e não aplica redução ou recebimento financeiro.

## Contrato primário

OpenAPI BB Cobranças3.2.2, origem https://api.externo.bb.com.br/docs/v1/openapi/17395705/2, cópia primária SHA256 `6767b098b13bc00e42700d80435a73e1f6ff4d31d7a28647926209a58d5f00d3`.

`RequisicaoRegistroBoletos.desconto` define tipo2 como percentual até data, com `porcentagem` numérica decimal e `dataExpiracao` dd.mm.aaaa. O detalhamento fornece código2 e `percentualDescontoTitulo`, permitindo confronto exato com a intenção imutável. O perfil exige `valorDescontoTitulo` zero e códigos/valores/percentuais de segundo/terceiro desconto explicitamente zero; omissão ou divergência exige revisão. A consulta independente da conta/convenção/ambiente continua obrigatória. Uma resposta201 não substitui essa prova.

## Política local e integração

Faixa local conservadora0.01–99.99%, com no máximo duas casas. Taxa100% é recusada para manter este perfil estritamente distinto de uma extinção integral sem caixa; isso não é alegação de limite universal do banco. Data real entre emissão/hoje e vencimento. Nenhuma regra de arredondamento monetário, capitalização ou composição de encargos é inferida.

O perfil novo tem fingerprint próprio. JSON contém porcentagem numérica exata sem float; 12.5 e12.50 possuem uma única chave idempotente. O perfil fixo não aceita taxa percentual e vice-versa. Conta/ambiente, outbox, marcador de envio, timeout incerto, rotação e revogação são preservados. A alteração de vencimento não pode reduzir a data para antes do prazo contratado da oferta.

Este diferencial depende dos incrementos mensal e fixo. Não inclui header, migrations ou versões; a integração serial é da raiz. A descrição da capacidade pode ser geral, `Declarar e emitir ofertas de desconto API`, sem concessão implícita a quem apenas emite títulos/juros. Tipos3, segundo/terceiro desconto e recebimentos com ajustes permanecem separados.

Os testes usam PHP/MariaDB/HTTP/Chromium reais e transporte BB simulado por injeção. Isso não equivale a homologação bancária ou a chamada real ao banco.
