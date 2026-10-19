# Matriz objetiva da PR45

Escopo integral em andamento. Este incremento usa núcleo4.2.7/427,
BB1.1.3/101–113 e Asaas1.5.1/151, sobre o incremento426 publicado em
`4c5dd23b31836f2bd562980c4e075e9017910c29` (18 execuções/16 workflows aprovados).
A [validação427](pr45-validation-427.md) separa composição/InnoDB, HTTP e CI do novo
HEAD. A evidência426 permanece em [validação426](pr45-validation-426.md).
Todos os passos históricos permanecem preservados. Sem merge, deploy, tag ou release manual.

A prova425 está em [validação425](pr45-validation-425.md): fonte congelado,
instalação/upgrade nativos, aplicação real mysqli, MariaDB e Chromium. Os resultados
locais não equivalem às matrizes remotas de outro SHA nem a homologação bancária.
Inventários orientam revisão; contagens lexicais não medem bugs ou cobertura.

| Requisito | Implementação incorporada até427 | Prova e limite |
|---|---|---|
| Empresa principal/master/equipe | Identidade real do instalador, master diferente de1, wizard, grants/default por recurso e seletor409 | Bootstrap/upgrade/concorrência/HTTP; legado ambíguo usa console+senha; recuperação totalmente gráfica depende de autoridade independente |
| Clientes privados/opt-in | Diretório positivo, compartilhamento explícito somente da identidade, contatos e sessões próprios | A/B/principal/NULL, concorrência e navegador; não compartilha histórico financeiro |
| Settings/SMTP/templates | Identidade, data/hora, imagens próprias, preferências de fatura, prefixo, templates e SMTP próprios | Settings A/B, HTMLPurifier e aba obsoleta; SMTP capturado, entrega externa não exercitada; demais integrações exigem contrato próprio |
| Portal | Login/reset, faturas emitidas/PDF/anexos publicados, orçamentos/propostas aceitos/convertidos com permissões nativas | Sessão, owner, permissão, revogação e bytes privados; sem documentos rascunhos ou acesso por identidade compartilhada |
| Faturas/recebimentos | Itens, tributos, descontos, ajustes, numeração, snapshots, PDF, edição/cancelamento elegíveis, anexos e notificação | Writer e saldo sob lock, idempotência e auditoria; manual não contorna origem BB/Asaas |
| Créditos/refunds/estornos | Emissão, aplicação/desaplicação, refund/reversão e reversão manual parcial/final preservando originais | Suítes créditos/leitores/concorrência e Chromium; registro local não prova movimentação bancária |
| Vendas/conversão/recorrência | Orçamentos/propostas, decisões, conversão retomável no writer de fatura; agenda mensal e worker por ator/origem | Snapshot e guard sob lock; âncora mensal31; anexos/envios desses documentos integram426 |
| Projetos/tarefas | Projetos, tarefas/comentários/checklists/timers, marcos/DAG/seguidores/arquivos/Kanban, tarefas avulsas | Serviços/controladores/concorrência/Chromium; API operacional integra426 |
| Despesas | Categorias, despesas/referências próprias, tributos, arquivos, edição/cancelamento auditados | A/B/principal/legado e navegador; importação/exportação por lote integra426 |
| Contratos/tickets | Documentos, cópia/PDF, conversas e referências próprias | Serviços, controllers reais, arquivos privados427 e navegador; portal operacional permanece pendente |
| Relatórios/API financeira | Posição atual por moeda e CSV auditado; quatro scopes de API vinculados a empresa/ator | API/locks/revogação/PDF e Chromium; diário/períodos e moeda imutável integram426; posição atual não é fechamento histórico |
| Asaas por origem | Contas/credenciais/ambientes, cobrança, consulta, inbox, recebimento nominal Pix/boleto, worker opt-in e despesa de tarifa explícita | Suítes writer/protocolo/concorrência, native149 e HTTP; transporte simulado; remoção remota150 e pagador privado/cartão hospedado avulso151; sem parcelas/assinaturas/antecipação |
| BB API | OAuth, reserva/outbox, registro, consulta/prova de conta, pedido/confirmação de baixa, crédito16 integral, alteração de vencimento confirmada, receptor mTLS observacional | MariaDB240, webhook229, TLS local e Chromium425; nenhum OAuth/registro/homologação BB real; encargos/parciais/estornos financeiros continuam técnicos pendentes |
| CNAB240 | Originação, reserva API/CNAB, geração/download/retry, envio manual declarado, retorno/revisão/vínculo e crédito assistido parcial e componentes427 | Principal em caixa separado de descontos/abatimentos/encargos agregados; tarifas/IOF/outros e estorno próprio pendentes; upload não autentica banco |
| CNAB400 | Parser CBR643 e candidato CBR641 | Operação indisponível: BRL85–86, juros herdados/contrato e picture CBR64326–39; trailer já resolvido; amostras aceitas/retornos não fornecidos |
| Homologação externa | Nenhuma realizada em API/240/400/Asaas/SMTP | Depende de autorização, canal/conta/convênio/carteira e acesso próprios; não esconde código ainda faltante |

Guias: [faturas](invoice-operations.md), [créditos](credit-operations.md),
[vendas](sales-recurring-operations.md), [Asaas](asaas-operations.md),
[settings](settings-operations.md) e [API](report-api-operations.md).

A falta de homologação não elimina pendências técnicas. Não aplicar eventos sem
origem, evidência canônica e writer transacional; não atribuir ao banco uma prova local.

## Incremento427 — composição financeira, checkout e arquivos

| Fluxo | Implementação e prova | Limite |
|---|---|---|
| Componentes CNAB240 | Fonte final fechada, writer, recibo único, DTO/hash, helper de saldo e schema427; provas MariaDB/concorrência | Restrito a tarifas/IOF/outros zero, líquido igual ao pago e principal positivo; sem API parcial ou reversão bancária |
| Saldos/leitores/guards | Pagamentos, créditos, fatura/PDF/portal/API/relatórios/diário conferem a mesma posição | Extinção sem caixa não é recebimento ou crédito disponível; corrupção bloqueia nova mutação |
| Asaas151 | Pagador privado, intenção única, recuperação sem POST cego, cartão hospedado avulso e publicação explícita no portal | RECEIVED com creditDate ocorrida; CONFIRMED/AUTHORIZED/antecipação/parcelas/escrow não lançam; provedor simulado |
| Checkout legado | Pré-autoload recusa faturas de empresa nomeada antes de helpers/gateways; NULL conserva o caminho legado | Não cria um atalho público para a empresa selecionada |
| Arquivos operacionais | Contratos/chamados com BLOB privado, PDF/PNG normalizado, quotas, integridade, hide e auditoria | Upload não publica no portal; sem URL pública ou purga |

Próximos requisitos técnicos independentes: portal operacional e API de seus
subrecursos, reversão canônica de componentes e demais consumidores sem adaptação.
Recuperação gráfica legada depende do registro de provisionamento e cadeia de
verificação confiáveis ainda ausentes; escolher esse caminho não fornece a prova.

## Incremento426 — fluxos transversais e integridade

| Fluxo | Implementação e prova | Limite |
|---|---|---|
| API operacional | Oito scopes adicionais, A/B/principal, cinco domínios, guards/locks e tokenusage na mesmaTX; MySQL/MariaDB e HTTP | Não amplia scopes de credenciais antigas |
| Vendas/arquivos/envios | PDF/PNG privados, publicação por arquivo, SMTP/fila com origem/revisão/ator | SMTP simulado; nenhum PDF público |
| Importação/exportação de despesas | CSV/JSON preview, referências/revisão, commit atômico, retry e CSV privado |100linhas/256KiB; nenhuma adoção do legado |
| Moeda/diário/relatórios | ISO congelado, writers/eventos nominais, período/corteUTC/watermark/CSV; coverage não oculta órfãos | Upgrade é observação explícita; não reconstrói fatos históricos ausentes |
| Asaas150 | Remoção remota própria, intenção durável/DELETEúnico/consulta de confirmação, contas/ambientes separados | Transporte simulado; fatura local preservada |
| BB112 | Perfil declarado de juros diários fixos e consulta observacional de movimentos, outbox/GUI e vetos adversos | Não é aplicação financeira de encargos nem homologação |

## Incremento417 — editor monetário completo no recorte de faturas

Código: `CompanyInvoiceAmounts`, `CompanyInvoices`, controlador/formulário nativo.
Campos existentes em invoices/item_tax; migration417 é marcador NOOP, sem DDL e
sem alterar migrations históricas. Gates de outros consumidores permanecem fechados.

| Requisito | Teste e resultado local | Limite |
|---|---|---|
| Múltiplos tributos, desconto fixo/percentual antes/depois dos tributos, ajuste com sinal | PHP8.3.6: `tests/companies/invoice_amounts.php`,27 checks; exemplos de centavos/overflow/inválidos | Regras descritas no guia de faturas; não é cálculo fiscal nacional completo |
| Persistência/edição/recebimento A/B, snapshot, replay pré417 e rollback | `tests/companies/invoices.php`,59 checks acumulados em SQLite | SQLite não prova locks/engine |
| Portal/PDF após ampliação | `tests/companies/portal_invoices.php`,85 checks acumulados, PDF TCPDF real | Regressão local sem SMTP/banco externo |
| Tracking nativo | `tests/native_migration_tracking.php`,12 checks em SQLite | Prova de tracking, não instalação universal |
| Políticas de release/inventário |43 testes Python aprovados | Gate estático não substitui operação |
| Editor monetário concorrente/revogação e GUI A/B/PDF/aba obsoleta | Testes ampliados em invoice_concurrency.php e companies_invoices_browser.cjs | MariaDB10.11.14 local:83 checks acumulados de fatura/edição/recebimento/concorrência e89 de portal/revogação; MySQL8/Chromium aguardam CI do novo HEAD |

Nenhuma chamada real de banco/SMTP, merge ou deploy neste incremento.

## Incremento418 — imagens e preferências de faturas por empresa

`admin/company_assets` usa renderer isolado, contexto/CSRF, settings/view+edit,
revisão concorrente e ownership pela empresa atual. PNG/JPEG até512 KiB são
validados por dimensão/pixels e convertidos para PNG, removendo metadados/conteúdo
adicional. Registro privado no JSON da empresa, sem URL pública ou caminho físico.
Download confirma ACL e auditoria transacional antes dos bytes. Não há fallback
para imagens de outra empresa ou uploads globais. Assinatura é imagem, não A1/GovBR.
Novas faturas capturam logo/assinatura; documentos antigos conservam seu snapshot.
Definições/Faturas oferece prefixo e condições próprias, validados e escapados.
Migration418 é NOOP; não altera schema, tracking ou migrations históricas.

| Requisito | Execução local PHP8.3.6 | Limitação |
|---|---|---|
| Upload, preview, remoção, download, A/B/principal, ACL, auditoria/falha, imagem inválida/oversized | company_assets.php:24 checks em SQLite e MariaDB10.11.14 | Sem HTTP local; o novo teste Chromium pertence ao novo HEAD |
| Escritores concorrentes e revogação aguardando lock | asset_concurrency.php:32 checks acumulados em MariaDB10.11.14 | MySQL8/PHP8.1/8.5 no CI |
| Snapshot/PDF real após troca/remoção de imagens | invoices.php:63 checks acumulados; invoice_concurrency.php:87 em MariaDB | Sem certificado digital |
| Portal/PDF e revogação durante lock | portal_invoice_concurrency.php:93 checks acumulados em MariaDB | Sem banco/SMTP externos |
| Prefixo/condições A/B, batch inválido e escape | settings_model_controller.php:134 checks acumulados em SQLite | Demais configurações não declaradas continuam bloqueadas |

CI do417 (`5d0f41a3`): fluxos MySQL8/PHP8.1/8.5, BB MySQL/MariaDB e stack
HTTP/Chromium aprovados. Dois gates falharam por inventário desatualizado;418
regenera os inventários conservando entradas alteradas como pendentes. Não reutiliza
essa execução como prova do código418 nem muda rollout global para liberado.

## Incremento419 / BB109 — crédito API explícito e canônico

`ApiCreditedReceipt` é a segunda fonte fechada autorizada no writer nativo; uma
implementação arbitrária da interface continua negada. `settle_api` exige contexto,
POST/CSRF, BB/view+reconcile, invoices/view e payments/create. Usa somente origem
imutável e a última consulta validada com prova da conta, até5 minutos no primeiro
lançamento, estado16 CREDITADO, natureza normal/apresentação, liquidação elegível,
data de crédito já ocorrida, nominal/pago/líquido integrais e todos os ajustes zero.
6 LIQUIDADO não basta; parcial/cheque/cartório/ajustes/dados ausentes ficam em revisão.
Recibos usam identidade bancária estável, independentemente de ator/chave da UI ou
nova consulta idêntica; pagamento, status, recibo e auditorias confirmam juntos.
Contradição materializada não é apagada por consulta posterior; nenhum estorno é
executado por uma observação adversa. Provas financeiras cruzadas API/CNAB impedem
nova aplicação do mesmo título. SQL/JSON ambíguos não são normalizados para sucesso.

Nova tabela aditiva bb_api_receipts: PK por origem, unicidades de recibo/pagamento,
FK para origem/consulta, InnoDB/utf8mb4_bin, tipos/nullability/defaults conferidos.
Migration109 idempotente; falha de tracking preserva108 e retry completa109.419 é
marcador core NOOP. Migrations históricas não foram alteradas. Módulo requer4.1.9.
A correção de `imagedestroy` substitui a chamada obsoleta por liberação do objeto,
sem reduzir os asserts de conteúdo/concorrência no PHP8.5.

| Prova local PHP8.3.6/MariaDB10.11.14 | Resultado | Limite |
|---|---|---|
| Native module101–109/instalação/upgrade/replay/falha tracking, API/outbox/criptografia |160 checks acumulados | Transporte BB simulado |
| Crédito A/B, repetição/nova consulta, revisão por juros/parcial/cheque, data/valor/prova ausente, auditoria/proveniência e conflito imutável |301 checks acumulados incluindo suítes anteriores | Não somar como301 cenários novos |
| Concorrência de dois créditos, crédito versus manual e revogação aguardando lock |331 checks acumulados incluindo as suítes anteriores | Banco local real, provedor simulado |
| Regressão da conciliação CNAB240 assistida/concorrência |129 checks acumulados | Evidência sintética, sem autenticação bancária real |
| Asaas writer/OI |84/88 checks separados | Sem homologação Asaas/produção |
| Novo fluxo Chromium |Fixture usa serviços reais com transporte injetado; GUI real confirma/retry via writer | Resultado apenas após CI do novo HEAD |

OpenAPI oficial baixada novamente em19/10/2026, versão3.2.2, SHA256 permanece
6767b098b13bc00e42700d80435a73e1f6ff4d31d7a28647926209a58d5f00d3.
A semântica documenta6 LIQUIDADO e16 CREDITADO; somente16 é elegível neste recorte.
Alterações de título, webhook mTLS, encargos/parciais/estornos e demais perfis são
pendências técnicas. CNAB400 continua candidato bloqueado pelos insumos contratuais
listados no guia400. Não há homologação API/240/400 nem autorização de merge/deploy.

## Incremento420 — leitura fresca no teste HTTP do crédito

O serviço e o writer419 passaram nas matrizes MySQL/MariaDB. O primeiro teste
Chromium falhou procurando o form após a fixture CLI criar uma consulta: mudar
somente #bb-auth → #bb-billing não faz GET nem atualiza o DOM. O teste agora
recarrega o documento por HTTP antes de confirmar. Mantém os asserts de consulta
validada sem pagamento automático, formulário real, writer, recibo e retry.
420 é marcador core NOOP; BB permanece1.0.9/109 sem nova alteração funcional.
Resultado do navegador corresponde ao novo HEAD; não atribuir o CI419 ao420.

## Incremento421 / BB110 — identidade bancária entre representações

API usa20 dígitos (000+Nosso Número17); CNAB240 usa17. Os vetos de recibo já
aplicado e observação adversa agora comparam ambas as representações, mantendo
empresa/conta/ambiente/convênio. Nenhum dado histórico, payload, número reservado
ou comprovante é reescrito.110 é NOOP funcional nativo;421 é marcador core.

Testes reais em MariaDB10.11.14/PHP8.3.6: crédito API e concorrência344 checks
acumulados; CNAB assistido/concorrência131 acumulados. Incluem recibo CNAB17
bloqueando API20, recibo API20 bloqueando CNAB17 e observação adversa17 impedindo
crédito20 antes de efeito. Casos sem duplicidade continuam positivos.
A fixture de evidência cruzada é sintética, restrita ao banco descartável; não é
prova de autenticidade de upload ou homologação bancária. Resultado HTTP/Chromium
permanece vinculado ao SHA final, com recarga real após a fixture externa ao DOM.


## Incremento422 — notificação privada de faturas pelo portal

`CompanyInvoiceDelivery` fornece estado/destinatários e agenda uma mensagem por
requisição explícita em `admin/company_invoices`. Requer invoices/view+edit e
customers/view; contato ativo próprio, cliente ativo, fatura emitida e permissão
nativa1. Compartilhar identidade não libera o envio aos contatos do proprietário.
Template `invoice-send-to-client`, remetente, assinatura e transporte são resolvidos
na origem. Link só abre o portal autenticado; sem hash público, bearer ou PDF anexo.

Migration422 cria `company_invoice_deliveries`: PK de requisição ascii_bin, revisão
e hash do contato, fila única, índice origem/fatura, FK empresa/fatura e validação
InnoDB/tipos/defaults/índices. Recibo não mantém endereço de e-mail e sobrevive à
limpeza da fila. Gravação de fila/recibo/auditoria confirma junta; retry após limpeza
não cria outra mensagem. Default indevido/índice não único bloqueiam a migration.

O worker nativo reconhece o marcador interno, valida recibo, origem, ator, contato,
permissão e revisão de fatura antes do transporte e remove o marcador dos headers.
Mudança de contato/empresa/funcionário/permissão/fatura impede envio. Nenhuma rede
ocorre na transação. Aceite pelo transporte não comprova entrega na caixa ou leitura;
crash entre SMTP e atualização da fila mantém a limitação usual de entrega de e-mail,
sem garantia de exactly-once externo. A UI exibe fila/processamento/falha/aceite/arquivo.

| Execução | Resultado observado | Limite |
|---|---|---|
| PHP8.3.6/SQLite: invoice_delivery_worker.php |93 checks acumulados, incluindo63 de faturas anteriores | Transação SQLite não prova locks; SMTP capturado |
| PHP8.3.6/MariaDB10.11.14: invoice_delivery_concurrency.php |109 checks acumulados: migração/replay/default/índice, A/B/e-mail igual, contato compartilhado negado, worker real, idempotência/rollback e dois processos aguardando lock/revogação | Transporte final capturado, nenhum SMTP externo |
| Regressão company_mail_queue.php |78 de settings+14 adicionais de fila em SQLite | Preserva fila legada e origem sem sessão |
| companies_invoices_browser.cjs |Novo fluxo A/B: seleção explícita, fila real, retry, contato cruzado403 e aba obsoleta409 | Resultado HTTP/MySQL/Chromium pertence ao CI do HEAD422, não ao anterior |

O HEAD anterior `b413fa7171f4d294488b35223c346d2f1ccf67df` passou nos16 workflows,
incluindo BB API A/B no navegador. Essa evidência não é reaproveitada para o422.
Anexos, créditos/refunds/estornos/recorrência, relatórios/APIs, demais consumidores,
Asaas positivo e ampliações BB continuam pendências técnicas, separadas da homologação.


## Incremento423 — anexos privados de faturas e portal

`CompanyInvoiceAttachments` armazena blobs imutáveis em tabela própria, sem caminho
no disco ou URL pública. `admin/company_invoices` aceita multipart com contexto de
aba/CSRF e invoices/view+edit. Listar/baixar/visualizar PNG exige invoices/view e
owner do pai e filho. Principal explícita funciona; NULL legado não é adotado.
PDF de até2 MiB é entregue como attachment com sandbox/nosniff; não é declarado
sanitizado ou certificado. PNG/JPEG até512 KiB usa o normalizador raster existente
(1600 pixels por dimensão/2 milhões de pixels), regravado como PNG. Tipos executáveis,
SVG, caminhos e arquivos além dos limites são recusados. Preview só de PNG regravado.

Cada upload possui chave durável, ator/empresa/pai/nome/mime/hash/visibilidade
conferidos no retry. Recibo cruzado é recusado antes de hidratar nome/conteúdo.
Conteúdo/hash/tamanho são conferidos na gravação e antes do download. Upload,
ocultação e downloads precisam confirmar auditoria; falha não libera bytes nem
muda estado parcialmente. Ocultação é idempotente e não apaga histórico; retry de
upload antigo não ressuscita arquivo. Até20 uploads/20 MiB por fatura, contando
ocultos. Os limites de contagem e20 MiB foram exercitados com conteúdo persistido.

Portal só lista arquivos com publicação explícita pelo operador, sessão/cliente/
empresa/permissão nativa1 e fatura emitida próprios. Lista lê metadados em lote e
nunca BLOBs. Download é POST com CSRF, valida pai+filho+visibilidade e registra contato
sem e-mail/bearer. Arquivo privado, oculto, rascunho, ID cruzado ou permissão revogada
não libera bytes. Compartilhar identidade não compartilha arquivos ou contatos.

Migration423 cria `company_invoice_attachments`, PK ascii_bin, metadados utf8mb4_bin,
mediumblob, FK empresa/fatura, índice origem/pai/estado; valida tipos/defaults/engine/
PK/índices/FKs. Default-public1 é rejeitado. Sem DDL em migrations históricas, sem
purga ou downgrade destrutivo. Versionamento BB permanece1.1.0/110.

| Prova local PHP8.3.6 | Resultado | Limite |
|---|---|---|
| invoice_attachments.php |134 verificações acumuladas em SQLite, incluindo89 de faturas/portal anteriores | Não somar suítes incluídas; não prova locks |
| invoice_attachment_concurrency.php |152 verificações acumuladas em MariaDB10.11.14: A/B/principal/NULL, ACL view/edit, portal, integridade/rollback/quotas reais e dois processos com espera observada/revogação | Banco real local; sem provedor externo |
| companies_invoices_browser.cjs |Novo fluxo A/B de multipart/retry, binário/preview, portal explícito/privado, ocultação/retry e409 de aba obsoleta | Resultado HTTP/MySQL/Chromium atribuído somente ao HEAD423 após CI |

O HEAD422 `804bf98ee708cd4c80ee31c47185f7864bb0f8c5` passou nas18 execuções dos16
workflows, incluindo notificação no CRM real. Essa passagem não valida o novo423.
Persistem créditos/refunds/estornos/recorrência, outros documentos/consumidores,
Asaas positivo e ampliações bancárias. Nenhuma homologação API/240/400 ou SMTP externo.
