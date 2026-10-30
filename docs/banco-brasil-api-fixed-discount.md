# Primeiro desconto fixo no registro API BB

O perfil local permite registrar o primeiro desconto de valor fixo até uma data, junto do perfil isento, juros diários ou juros mensais explicitamente contratados. Não aplica desconto financeiro à fatura. O recebimento API com desconto utilizado continua exigindo revisão; o nominal e o saldo não mudam pelo registro ou consulta da oferta.

## Contrato primário

OpenAPI BB Cobranças versão3.2.2, origem https://api.externo.bb.com.br/docs/v1/openapi/17395705/2, SHA256 da cópia primária `6767b098b13bc00e42700d80435a73e1f6ff4d31d7a28647926209a58d5f00d3`.

- `RequisicaoRegistroBoletos.desconto`: tipo1, `valor` numérico decimal e `dataExpiracao` no formato dd.mm.aaaa. Tipos2/3 e segundo/terceiro desconto não fazem parte deste perfil.
- `RespostaDetalhamentoBoleto`: `codigoDescontoTitulo`, `valorDescontoTitulo`, `dataDescontoTitulo`, `percentualDescontoTitulo` e os códigos/valores/percentuais de segundo/terceiro desconto permitem conferir a configuração observada. O código, valor e data devem corresponder; percentuais e demais ofertas devem ser explicitamente zero. Campos ausentes não confirmam a oferta.
- A confirmação exige também a consulta independente da conta/convenção/ambiente já implementada. HTTP201 sozinho não confirma a oferta configurada.

## Implementação

O usuário declara separadamente o contrato de desconto e precisa da capacidade `banco_brasil.api_discount`. Juros continuam exigindo `api_charges`; desconto em perfil isento não concede ou requer essa autorização de juros. Empresa, conta, ambiente e fingerprint do perfil permanecem imutáveis. Perfis anteriores conservam os mesmos JSON e hashes.

Os centavos são normalizados antes da chave idempotente e serializados como número JSON exato sem float. Os limites locais são conservadores: valor positivo e menor que o nominal; data real entre emissão/hoje e vencimento. Esses limites são política deste perfil, não limites universais atribuídos ao banco. Antecipar o vencimento para antes do prazo da oferta é vetado sem enviar PATCH, preservando o payload imutável.

A emissão reutiliza outbox, número exclusivo, marcador de envio, estado incerto e consulta do título. Antes de reservar, retomar e enviar, a permissão específica é revalidada; revogação ou rotação de credenciais não autoriza um POST novo por retry. A interface exibe valor/data confirmados pela consulta validada da conta e distingue oferta de recebimento financeiro.

## Integração e limites

Este patch diferencial depende do incremento de juros mensais. O coordenador deve registrar `api_discount` no header/catálogos de capacidades e serializar as versões necessárias. Não há nova migration nem alteração de contrato de recebimento neste patch.

Os testes usam PHP/MariaDB/HTTP/Chromium reais e transporte BB sintético injetado. Isso prova execução local, isolamento e protocolo observado pelo código; não é homologação bancária, contratação do convênio ou chamada real à API BB.
