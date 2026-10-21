# Alterar endereço operacional do pagador por API

Contrato primário: OpenAPI Cobranças3.2.2 capturado em19/10/2026, https://api.externo.bb.com.br/docs/v1/openapi/17395705/2, SHA256 `6767b098b13bc00e42700d80435a73e1f6ff4d31d7a28647926209a58d5f00d3`. `RequisicaoAlterarBoleto.indicadorAlterarEnderecoPagador=S` seleciona o objeto `alteracaoEndereco`; todos os demais indicadores permanecem `N`.

| Intenção PATCH | Campo de detalhamento GET |
|---|---|
| enderecoPagador | textoEnderecoSacadoCobranca |
| bairroPagador | nomeBairroSacadoCobranca |
| cidadePagador | nomeMunicipioSacadoCobranca |
| UFPagador | siglaUnidadeFederacaoSacadoCobranca |
| CEPPagador (inteiro) | numeroCepSacadoCobranca (número) |

O perfil local `bb_api_registered_address_patch_v1` aceita os cinco campos completos, limites de endereço/bairro/cidade compatíveis com a emissão atual, UTF8 válido sem controles, UF brasileira e CEP de oito dígitos. Não normaliza caixa, não translitera nem trunca texto. CEP é enviado como inteiro JSON e o retorno numérico é comparado com oito dígitos, conservando zeros iniciais na projeção operacional. Nome, CPF/CNPJ e identificação do título não integram a instrução.

Há nova capacidade `api_amend_address`, além de visualizar/editar a fatura, empresa selecionada e CSRF. A intenção contém confirmação do contrato, referência e motivo; seu fingerprint conserva a origem, perfil e ambiente originais. Exige consulta NORMAL recente e comprovada pela conta com os cinco campos observados, sem história financeira/parcial/adversa ou outra instrução pendente. Endereço desconhecido não é inferido do payload de emissão. Campos opcionais ausentes em respostas de perfis antigos continuam compatíveis com consulta observacional; impedem essa nova instrução.

A resposta200 do PATCH apenas registra o pedido. Uma consulta independente do detalhamento e da conta deve confirmar todos os cinco campos para produzir `confirmed`; quatro campos ou endereço anterior não bastam. Resposta perdida permanece incerta, e repetir a mesma intenção/chave não repete PATCH. Antes do marcador de envio, permissões e versão da credencial são revalidadas. O fluxo preserva a recuperação já aceita de claims de leitura expirados; nunca aposenta claims de mutadores enviados.

Nenhum endereço/identidade fiscal da fatura, payload/snapshot/perfil original ou saldo financeiro é modificado. A interface distingue endereço operacional solicitado/observado no banco do documento fiscal. A fonte financeira canônica exige confirmação tipada, fingerprint, conta, ID da instrução e os cinco campos normalizados da consulta: um crédito16 com instrução pendente não constitui uma exceção. A instrução não lança pagamento, abatimento nem nota de crédito.

A operação durável `amend_addr` cabe no esquema atual; não há DDL novo. O coordenador integra a capacidade nos catálogos e a rota exata `amend_api_address` no CompanyRequestScope, sem ampliar permissões antigas. ModuleRelease e migrations permanecem sob integração serial.

Os testes usam SQL/PHP/HTTP/Chromium reais e protocolo determinístico injetado somente em fixtures de banco descartável. O controller positivo executa o método real e o serviço canônico com adapters de request/redirect. Não existe transporte selecionável por parâmetro HTTP de produção. Nenhuma credencial, pedido real ao BB, autenticação bancária externa ou homologação foi usada. Alteração de endereço não resolve os contratos de multa, parciais18/19 ou CBR641/643.
