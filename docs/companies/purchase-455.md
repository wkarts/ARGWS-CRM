# Compras por empresa — incremento 455

## Limite desta entrega

Este incremento implementa uma cadeia própria de compras: fornecedor, requisição, cotação, pedido, recebimento parcial ou final, documento do fornecedor, despesa canônica, título a pagar, declaração de quitação externa e reversão dessa declaração. Há formulários administrativos, convite de fornecedor, arquivos privados, PDF de pedido, posição de contas a pagar, CSV e API de escopos separados.

O módulo legado `purchase` 1.5.0 possui outros fluxos internos ainda pendentes. Seus controllers, tabelas, portal antigo e jobs não são reutilizados nem considerados adaptados por estarem bloqueados na secundária. O inventário e as pendências internas estão descritos no fim deste documento. Os gates nativos 455 e a publicação são registrados pela integração da PR 45; os testes candidatos executados sobre a versão 454 são identificados separadamente da migração nativa 455.

## Uso pela equipe

1. Selecione a empresa no seletor nativo e abra **Compras**. Em **Configuração**, escolha prefixo, moeda base e taxas explícitas. Para a moeda base, a taxa deve ser `1.000000`. O prefixo vale para novos documentos; números crescem por empresa e tipo, sem reinício mensal.
2. Cadastre um fornecedor dessa empresa. Seu código pode ser usado na cotação e no convite. Alterações cadastrais afetam operações futuras; o pedido conserva os dados capturados quando foi emitido.
3. Crie uma requisição com até 50 itens, quantidade em até três casas decimais, unidade, necessidade e moeda. Aprove-a com a capacidade específica de aprovação.
4. Registre a cotação recebida ou crie um convite para que o fornecedor a envie. Compare os totais da própria requisição e emita um pedido a partir de uma cotação válida. O pedido captura fornecedor, itens, preços, tributo, identidade da empresa, configuração e taxa de câmbio.
5. Registre somente as quantidades recebidas naquele momento. Deixe em branco itens que ainda não chegaram. A soma não pode exceder o pedido; uma revisão desatualizada é recusada.
6. Selecione os recebimentos ainda não faturados, informe o número do documento do fornecedor, datas, categoria da despesa e eventual anexo. O fechamento atômico cria um documento imutável, uma despesa canônica e um título a pagar.
7. Para um pagamento já realizado fora deste fluxo, registre **Declaração de quitação externa** com valor, data, referência e confirmação expressa. Para corrigir a declaração, acrescente sua reversão inteira com motivo. Essas ações não acionam banco ou devolução de fundos.

O recebimento é evidência física de compras. Nesta versão ele não atualiza os estoques de módulos legados. A interface informa essa limitação. Pedidos só podem ser cancelados antes de qualquer recebimento; uma requisição com pedido emitido não volta implicitamente ao estado anterior.

## Contrato financeiro

Cada documento seleciona um conjunto exclusivo de recebimentos do mesmo pedido e fornecedor. Um recebimento não pode compor dois documentos. Se houver recebimentos posteriores, outro documento e outro título devem ser criados; o título anterior não é recalculado nem aumentado.

A alocação da base usa diferenças de arredondamento cumulativo por item. Por exemplo, três recebimentos de `0.333`, `0.333` e `0.334` para uma unidade de preço `0.01` recebem bases de 0, 1 e 0 centavos, totalizando exatamente o centavo do pedido. Um documento com base total zero não cria obrigação vazia; esses recebimentos podem ser agrupados com outros do mesmo pedido.

O tributo é uma taxa percentual explícita para a cotação, aplicada e arredondada uma vez por documento fechado. A soma dos tributos de documentos parciais pode diferir da estimativa do pedido. Não há motor tributário, validação fiscal, vários tributos por item ou documento fiscal emitido por este fluxo. Quantidades e valores são tratados como inteiros escalados; entradas com exponencial, sinal negativo, zeros não canônicos ou precisão excessiva são recusadas.

`CompanyExpenses::createInOwnedTransaction()` é o único escritor do custo. Seu evento `expense_recorded` pertence ao diário financeiro existente. O AP contém o vínculo imutável `empresa → fornecedor → pedido → documento → despesa`, além das alocações para os recebimentos e hashes dos snapshots. A declaração de quitação e sua reversão escrevem somente eventos próprios de AP. Não criam eventos paralelos de custo, caixa, recebimento bancário, baixa de contas a receber ou `local_noncash`.

Uma despesa originada em Purchase não pode ser editada ou cancelada pelo CRUD genérico de despesas, pois isso a faria divergir do documento e do AP. O veto é ativado somente quando a tabela própria de AP existe e contém o vínculo persistido. A busca considera o ID global da despesa: uma origem AP com empresa inconsistente também bloqueia a edição e exige revisão, em vez de conceder permissão pela inconsistência. O vínculo é conferido antes da alteração e novamente depois do último guard e recibo da API. A ausência do schema Purchase preserva o comportamento anterior de despesas; esse caminho possui prova positiva separada.

Declarações não podem exceder o saldo, preceder o documento ou ser retrodatadas antes de eventos já registrados. Reversões apontam para uma declaração original do mesmo título, possuem o mesmo valor e não podem ser repetidas. O saldo e o token de revisão são derivados do histórico imutável, incluindo a posição histórica por data.

Moedas suportadas por esta cadeia: BRL, USD e EUR. A taxa declarada tem até seis casas decimais e é capturada no pedido. Nenhuma conversão implícita é feita no AP ou no relatório; totais são separados por moeda. A taxa não é uma confirmação do câmbio bancário efetivo.

## Autoridade, origem e isolamento

O serviço requer vínculo ativo e capacidade da empresa. Um administrador legado sem vínculo não recebe acesso à secundária. A autoridade master já reconhecida e a empresa principal permanecem preservadas; não há nova designação de master nesta entrega.

| Capacidade | Operações |
|---|---|
| `purchases:view` | Consultar os próprios fornecedores, requisições, pedidos, documentos, AP e arquivos permitidos |
| `purchases:create` | Criar fornecedor, requisição e cotação registrada pela equipe |
| `purchases:edit` | Editar fornecedor e administrar anexos |
| `purchases:approve` | Aprovar/cancelar requisição, emitir/cancelar pedido e fechar documento |
| `purchases:receive` | Registrar recebimento físico |
| `purchases:pay` | Declarar ou reverter quitação externa |
| `purchases:portal` | Emitir, listar e revogar convites de fornecedor |
| `purchases:report` | Relatório de AP e exportação CSV |
| `purchases:settings` | Prefixo e câmbio explícito da empresa |

Fechar um documento também exige `expenses:view` e `expenses:create` na mesma empresa. A aprovação de compra não concede essas capacidades implicitamente.

Todos os IDs são reconferidos com o proprietário e a cadeia de origem dentro da transação. FKs próprias usam `issuer_id` com o ID do pai, e a despesa nativa é validada por empresa e hash de origem. Não existe adoção automática de fornecedores, ordens ou despesas legadas com `issuer_id=NULL`.

O serviço usa o bloqueio e o dono de transação do núcleo. Não comita transação externa do chamador; seus participantes devem ser InnoDB. Autorização, credencial, perfil do convite e evidências são revalidados no final. Recibos de comando vinculam empresa, ator, operação, payload, origem API/convite, tipo e ID do resultado. O replay valida o objeto e a cadeia pertinente, em vez de confiar apenas em `result_json`.

As conferências finais incluem linhas individuais e conjuntos: eventos AP, recebimentos, alocações, evidência financeira, auditorias canônicas, IDs de páginas e consultas de relatório. Isso impede que uma inserção na última auditoria da API produza uma resposta com saldo ou coleção anterior. Os escritores só substituem um conjunto esperado pelos membros expressamente planejados pela operação. Os testemunhos guardam hashes, evitando reter cópias sucessivas dos BLOBs privados no limite de 128 MiB.

## Portal do fornecedor e arquivos

O convite é restrito a uma empresa, uma requisição e um fornecedor persistidos. Seu segredo aleatório aparece uma vez; somente SHA256 é armazenado. O link copiado coloca o segredo no fragmento; o navegador o remove e troca por uma sessão através de POST. A sessão contém uma identidade assinada, sem o segredo original. Expiração, revogação, fornecedor ativo, requisição e autoridade atual de quem emitiu o convite são conferidos em cada operação.

Há até 20 convites ativos por requisição e validade de 1 a 30 dias. O replay da emissão devolve o ID existente e `secret=null`. Uma resposta de emissão perdida não dispara envio automático nem reconstrói o segredo. A equipe pode emitir outro convite e revogar o anterior pela lista.

O portal permite consultar a requisição vinculada, enviar cotações, anexar evidências, ver os pedidos e recebimentos do próprio fornecedor, baixar o pedido PDF e consultar as obrigações correspondentes. Cotações e arquivos do portal registram origem `supplier_portal` e ator de criação 0, sem se fazer passar por um funcionário. Não há cadastro aberto, sessão de fornecedor legado ou enumeração genérica de compras.

PDFs e imagens ficam em armazenamento privado, sob a empresa e requisição. Limites: 20 arquivos e 20 MiB por requisição, contando ocultos; PDF até 2 MiB; imagens verificadas e normalizadas pelo componente existente. Nomes inválidos e travessia de caminho são recusados. Download verifica empresa, pai, visibilidade, bytes, MIME e hash e envia conteúdo como anexo, com `nosniff` e sandbox.

Publicar no portal é uma decisão explícita. Um arquivo sem fornecedor indicado, marcado como público no portal, é compartilhado com os convites daquela requisição. Arquivos ligados a documento ou declaração permanecem preservados e não podem ser ocultados pelo fluxo comum. A interface explica essas duas consequências.

Nenhuma ação envia SMTP. O convite é copiado para entrega pelo canal escolhido pela equipe. Isso é funcionalidade de convite e acesso; não é evidência de transporte de e-mail.

## Relatórios e documentos

A posição de AP lê os documentos fechados e seus eventos. O resumo por moeda exibido na GUI é explicitamente o **subtotal da página**, não o total geral. A consulta usa paginação por ID e posição em data informada. A exportação exige um período completo de até 5.000 títulos; acima disso, pede redução do período em vez de devolver um CSV truncado.

O CSV neutraliza células que poderiam ser interpretadas como fórmula. O pedido PDF usa snapshots de empresa, fornecedor, preços e recebimentos; não interpreta HTML remoto ou hooks de módulos. O PDF não é nota fiscal, confirmação bancária ou aceite eletrônico de contrato.

## API finita

Namespace: `company_purchases_api/index/<recurso>[/<id>]`. Métodos GET/HEAD consultam; POST recebe JSON e cabeçalho `Idempotency-Key` de 8 a 128 caracteres. Credencial somente no cabeçalho `Authorization: Bearer crmco_...`. Empresa e ator vêm do token persistido, sem sessão de funcionário e sem um `issuer_id` escolhido no payload.

| Escopo | Ações |
|---|---|
| `purchases:read` | Listas/detalhes, configuração atual, arquivos e PDF |
| `purchases:write` | Fornecedor, requisição, cotação, upload e ocultação de arquivo |
| `purchases:approve` | Aprovação/cancelamento, emissão/cancelamento de pedido |
| `purchases:receive` | Recebimento físico |
| `purchases:post` | Fechamento do documento, despesa e AP; exige também as capacidades de despesas |
| `purchases:pay` | Quitação externa manual e reversão |
| `purchases:portal` | Convites |
| `purchases:report` | Posição AP e CSV |
| `purchases:settings` | Configuração explícita |

A matriz `purchase-455-api-matrix.md` registra cada endpoint, verbo, ação, scope, empresa e revisão. Ela distingue contrato implementado de combinação efetivamente executada em cada camada de teste.

Recursos revisados: `suppliers`, `requests`, `quotes`, `orders`, `receipts`, `documents`, `payables`, `events`, `files`, `file_download`, `settings`, `reports`, `invites`, `order_pdf`, `export`. `file_download`, `order_pdf`, `reports` e `export` são somente leitura. Nenhuma ação de ordem bancária, cobrança AR ou transporte legado é aceita.

O escopo é validado no serviço, além do dispatcher. Um token com várias capacidades, resolvido para `purchases:write`, não pode chamar aprovação diretamente. Token revogado, expirado, sem origem persistida ou cujo ator perdeu a permissão atual deixa de funcionar inclusive no fim de uma transação.

O caminho bruto original precisa corresponder aos segmentos exatos do controller e recurso. São aceitos o subdiretório real da instalação e o front controller `index.php`. Alias, mudança de caixa, encoding alternativo, dupla barra e sufixos adicionais não autorizam hidratação de módulos. A exceção de CSRF do namespace bearer é finita; portal e GUI conservam CSRF nativo, dedicado e contexto nativo onde aplicável.

O componente nativo de segurança pode emitir seu cookie de CSRF em uma resposta da API. Esse cookie não autentica o cliente nem substitui o bearer. A prova da interface confere os nomes permitidos e a tabela de sessões inteira antes/depois. Uma prova de transporte separada cria um jar vazio para cada GET, HEAD e POST e testa explicitamente a negativa sem bearer, inclusive enviando somente o cookie de CSRF observado.

## Schema e recuperação

`CompanyPurchasesSchema` acrescenta 13 tabelas: configuração, fornecedores, requisições, cotações, pedidos, recebimentos, documentos, alocações, títulos, eventos, arquivos, convites e recibos de comando. Não altera dados do Purchase legado. Pré-requisitos: núcleo de emissores/ACL/auditoria, schema de despesas e diário financeiro já presentes na versão 454.

`ensure()` exige transação independente. A validação compara colunas, ordem, tipos, nulabilidade, defaults, EXTRA, collation, engine, chaves/índices sem prefixo e FKs com schema e regras exatos. Falha ou DDL ignorado não equivale a schema instalado. O retry verifica o que existe e cria o que falta, sem recriar dados já presentes. O gate nativo 455 também deve provar tracking, fresh/upgrade, falhas nos 13 DDLs e preservação do histórico.

Recebimentos, AP e despesas pertencem à mesma transação quando necessários. Interrupção do processo após o INSERT real da despesa deixa o conjunto não comitado; um retry válido produz um único custo/AP. Gaps no `AUTO_INCREMENT` do banco após rollback são possíveis e não representam registros parcialmente persistidos. Os números comerciais são alocados por empresa com bloqueio.

Não há job novo neste incremento: numeração é monotônica, câmbio é explícito, vencimento de convite é verificado no uso e as operações são síncronas. Os quatro jobs legados inventariados continuam pendentes de adaptação, não são declarados implementados por essa decisão.

## Evidência e situação de validação

As provas novas usam MariaDB 10.11 e MySQL 8, PDO real, PHP 8.3/8.1 com 128 MiB e prefixos sintéticos. Não usam SQLite como substituto para locks, FKs, triggers ou DDL. Os relatórios de execução congelam hashes dos arquivos; o manifesto do recorte é identificado como subconjunto, não como uma árvore Git completa.

| Suíte própria | Contrato |
|---|---|
| `purchases.php` | Cadeia completa A/B/principal, recebimento parcial, AP/despesa, quitação/reversão, precisão e preservação legada |
| `purchases_surfaces.php` | Convite/sessão, portal, arquivos privados, PDF, relatório/CSV, fence de despesa e revogação |
| `purchases_integrity.php` | Falhas em sete pontos da composição, writes ignorados, auditoria tardia, rollback e replay |
| `purchases_concurrency.php` | Seis disputas observadas no banco e SIGKILL após INSERT real; nenhuma disputa simulada apenas por chamadas sequenciais |
| `purchases_schema.php` | Drift de metadata/FK, transação externa, DDL parcial/retry e despesas sem schema Purchase |
| `purchases_api.php` | API positiva A/B/principal, nove scopes, negativos de origem, arquivos/relatórios e triggers finais |
| `purchases_api_phantoms.php` | Inserção tardia de evento AP em get/page/report/export, detalhe/lista/lista vazia de eventos e coleção paginada; resposta antiga deve ser recusada |
| `purchases_api_matrix.php` | 61 cenários de origem por recurso/ação: detalhes, listas e filtros A/B, mutações cruzadas, pais mistos, arquivos/convites, categoria financeira e contraparte positiva |
| `purchases_expense_fence.php` | Origem AP inconsistente e inserção/reassociação no último audit/recibo; editar/cancelar deve reverter todos os efeitos, preservando despesas comuns com e sem schema Purchase |
| `purchases_scope.php` | Caminho bruto, base path, front controller, aliases e entradas malformadas |
| `purchases_route_catalog.php` | Execução real do catálogo de 32 rotas e negações, sem confundir comentário com código |
| `purchases_browser.cjs` | Formulários/portal/API reais em Chromium sobre instalação nativa, incluindo A/B/principal e conta ordinária |
| `purchases_events_http.cjs` | Lista/detalhe/cursor/HEAD de eventos AP em HTTP, positivos A/B, negativa cruzada e sessões nativas intactas |
| `purchases_mobile.cjs` | Regressão dirigida de sete telas em 390 e 320 px, texto longo preservado e nenhuma rolagem horizontal da página |
| `purchases_http_transport.cjs` | HTTP GET/HEAD/POST/replay com jar vazio por requisição, negativas somente com CSRF, token revogado e tabela de sessões intacta |

Há evidência RED/GREEN preservada, com os bytes anteriores e o mesmo teste antes/depois, para sete defeitos: conjunto AP alterado após o cálculo, alias bruto aceito, catálogo colocado em comentário pelo script candidato, veto incompleto da despesa, texto longo causando overflow horizontal no celular, lista de eventos anunciada mas recusada pelo serviço e detalhe de evento sem testemunho final do conjunto AP pai. Para o veto da despesa, quatro reproduções independentes comprovam a edição indevida com empresa AP inconsistente, reassociação após auditoria nativa e inserção/reassociação no recibo final da API. A revisão também identificou recibo de comando sem vínculo obrigatório ao ID e auditoria canônica capturada depois de adulterada; ambos foram corrigidos e receberam provas negativas, incluindo trigger SQL real. Não se apresenta uma execução RED preservada para esses dois achados quando ela não foi registrada.

Correções do harness foram separadas de correções do produto: cache compartilhada de `table_exists`, preservação do `insert_id` original no observer e ALTER de FK dividido para a sintaxe aceita por MySQL. Na interface, o prefixo sintético excedia o `maxlength=12`; passou a ser `HTTP_P`, mantendo o limite e a comparação exata. Um probe HTTP preservou o RED da hipótese de nenhum cookie: o único cookie emitido era o CSRF configurado, com as sessões inalteradas. As provas subsequentes distinguem esse cookie da autenticação, e o modo Docker cria os diretórios dos helpers antes de copiá-los. As expectativas de rollback, revogação e preservação não foram removidas.

**Validação própria concluída:** 292 verificações SQL distintas passaram em MariaDB 10.11/PHP 8.3 e MySQL 8.0/PHP 8.1, com limite de memória de 128 MiB. As sete suítes r3 abrangem 197 verificações; o veto final da despesa acrescentou 28, a matriz de origem acrescentou 61 e os leitores de evento acrescentaram 6. As execuções dirigidas r4 e r6 também repetiram as suítes relevantes anteriores, incluindo as 102 verificações cumulativas da API de despesas. Motores e repetições não aumentam a contagem de verificações distintas.

O browser completo r5, o transporte bearer com jar inicialmente vazio em cada requisição e a prova HTTP dirigida r6 de eventos passaram. A última prova reproduziu a falha HTTP 422 com o serviço anterior e passou com o mesmo teste após a correção do leitor. A regressão de responsividade verificou sete telas em 390 e 320 px sem overflow horizontal. O browser completo r5 e a prova dirigida r6 mantêm seus hashes e manifests separados; não se apresenta o browser r5 como execução integral dos bytes r6.

O arquivo `evidence/purchase-455-results.json` registra fontes, testes, resultados, casos RED/GREEN e limitações. A fixture própria de HTTP é um clone somente para teste da instalação nativa 454, com aplicação explícita do schema de compras; não constitui prova de migração nativa 455. As provas integradas de instalação limpa, upgrade, preservação histórica e publicação 455 são registradas separadamente pela integração da PR 45.

## Pendências internas e dependências externas

O inventário lexical `purchase-455-legacy-inventory.json` lista 4 controllers, 341 métodos públicos/implicitamente públicos, 1 método privado, 84 nomes de opções e 4 callbacks cron. Isso registra superfície para trabalho; não comprova ativação na instalação, autorização ou isolamento de cada método.

Pendências internas do Purchase legado, sem dependência documental externa que justifique adiamento: contratos e assinatura/aceite, catálogo e estoque/depósitos, devoluções e ajustes de documentos/créditos, recorrência de faturas de compra, contatos/cadastro/autenticação legados de fornecedores, templates e transporte de notificações, vários tributos por item/listas de preços, subrecursos de projeto e relatórios/mapeamentos contábeis legados. O fluxo 455 pode ser utilizado para o recorte descrito, mas não deve ser rotulado como cobertura integral desses subfluxos.

Callbacks legados pendentes: `reset_pur_order_number`, `reset_pur_request_number`, `recurring_purchase_invoice` e `pur_cronjob_currency_rates`. A numeração monotônica e a taxa explícita do fluxo novo não afirmam execução ou adaptação desses jobs.

Este recorte não depende de documentação bancária ou de um provedor externo. Qualquer ordem de pagamento, confirmação bancária ou devolução real exige uma implementação específica e a origem bancária apropriada; nada disso é inferido da declaração manual. As dependências externas restantes das frentes bancárias da PR 45 devem ser enumeradas no relatório geral, separadas destas pendências internas de Purchase.
