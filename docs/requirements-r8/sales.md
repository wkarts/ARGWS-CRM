# Vendas — inventário, adaptação e evidências da revisão 8

## Bases efetivamente inspecionadas

- Referência: tag `v4.0.2`, commit `aa58b832b3c5ed04a599caacba125b056c081ba7`.
- Destino: `main`, commit `3cd2ac577a969840c94b019f602cb69d710c0e78`, versão real **4.8.8**. A referência informada 4.8.7 não foi tratada como a base do código.
- Entrega em preparação: núcleo 4.8.9, nova migration 489. Nenhuma migration histórica é substituída.
- A frente 4.0.3 não foi usada como base nem integrada por esta adaptação.

O arquivo `sales-inventory.json` registra hashes dos arquivos de views/controllers das duas bases e os métodos públicos de cada controller. É inventário estático reproduzível, não prova de equivalência visual ou de que cada método está autorizado no destino. A autorização real continua sendo definida por `CompanyRequestScope`, `NativeCompanyRoutes` e pelos serviços de cada recurso.

## Todo o menu real de Vendas

O helper de menu da 4.0.2 e o `NativeAdminMenuCatalog` do destino têm as mesmas seis entradas principais. Faturas recorrentes é um subfluxo de Faturas, não um sétimo filho declarado pelo helper. O módulo Asaas acrescenta uma entrada real, que também existe na referência. A exibição depende dos módulos instalados e das permissões reais; a simples existência de um registro no código não comprova que um módulo esteja ativo numa instalação.

| Entrada | Rota | Referência real | Conteúdo/fluxos inventariados |
| --- | --- | --- | --- |
| Propostas | `proposals` | `Proposals.php`, `views/admin/proposals/` | Lista, filtros salvos, split preview, pipeline, formulário, sujeito/assunto, vínculo Lead/Cliente, projeto, moeda, datas, responsável, endereço/contatos, comentários, conteúdo, itens, taxas, descontos/ajuste, PDF, notas, anexos, atividades, envio/lembrete, assinatura/aceite, cópia, conversão em orçamento/fatura, situação e exclusão. |
| Orçamentos | `estimates` | `Estimates.php`, `views/admin/estimates/` | Lista, filtros salvos, estatísticas e totais por moeda/ano, split preview, pipeline, cliente/projeto, faturamento/entrega, numeração, datas, moeda, referência, agente de vendas, situação, tags/campos personalizados, itens/taxas/descontos/ajuste, notas internas/cliente/termos, PDF, notas, anexos, envio/lembrete, assinatura/aceite, cópia e conversão em fatura. |
| Faturas | `invoices` | `Invoices.php`, `views/admin/invoices/` | Lista/filtros/contadores/totais, preview, formulário, rascunhos, emissão, numeração, cobrança de despesas/tarefas, mesclagem, projetos, recorrência, pagamentos/estornos, créditos, anexos, lembretes, comunicação, PDF, cópia, cancelamento/reabertura. |
| Pagamentos | `payments` | `Payments.php`, `views/admin/payments/` | Tabela, busca/filtros e detalhe/comprovante, vínculo à fatura e ações financeiras conforme a origem. A falha relatada pertence somente a esta entrada; sua reprodução/correção tem evidência própria. |
| Notas de crédito | `credit_notes` | `Credit_notes.php`, `views/admin/credit_notes/` | Lista/filtros, preview, formulário, itens, saldos, aplicação em faturas, estornos, anexos, PDF, envio, cópia e ações de situação. A adaptação é descrita em `credit-recurring.md`. |
| Itens | `invoice_items` | `Invoice_items.php`, `views/admin/invoice_items/` | Lista e tabela, modal de criar/editar, grupos, duas taxas, unidade, preços por moeda, campos personalizados, busca/seleção para documentos, cópia, exclusão, ações em lote, importação/simulação/amostra CSV. |
| Asaas: origem e pendências | `asaas/payment_origins` | `modules/asaas/asaas.php`, `controllers/Payment_origins.php` | Consulta das origens de pagamento e reconciliação explícita. Entrada de posição 99, condicionada à administração do módulo; não foi apagada nem confundida com Pagamentos. |

Relatórios de vendas são filhos de **Relatórios**, e Pedido de orçamento é uma área própria do menu. Sua ausência como filhos de Vendas não os remove do escopo transversal R10/R11. Interfaces de Asaas, Efí e Banco do Brasil continuam sendo recursos adicionais da base recente; esta frente não substitui seus writers, credenciais, ledger ou contratos por código da 4.0.2.

## Propostas e Orçamentos: lista e navegação

A base 4.8.8 já guardava as views originais, mas os controllers multiempresa desviavam as rotas para um workspace compacto comum. A adaptação volta a compor `manage.php`, `list_template.php`, as estatísticas e as views de pipeline originais usando DTOs da empresa ativa. A identidade/PWA/shell atual permanece carregada pelo caminho atual de apresentação.

| Aspecto | Referência observada | Implementação desta etapa |
| --- | --- | --- |
| Propostas — colunas | Número, assunto, destinatário, total, data, validade, projeto, tags, criação, situação, campos personalizados ativos da tabela | `NativeCompanySalesRows` emite as dez colunas base na ordem correta; campos personalizados são lidos com origem e empresa explícitas. |
| Orçamentos — colunas | Número, total, impostos, ano, cliente, projeto, tags, data, validade, referência, situação, campos personalizados | Onze colunas base; ano continua disponível para a tabela, totais tributários vêm do cálculo canônico ou dos valores históricos conservados. |
| Filtros Propostas | Assunto, total, subtotal, data, validade, assinado, vencido, tipo de vínculo, responsável, situação, ano e campos personalizados | Catálogo `NativeCompanySalesTable` e compilador `NativeCompanySalesRules`; valores vinculados em SQL, sem expressão SQL recebida do navegador. |
| Filtros Orçamentos | Número, referência, total, subtotal, data, validade, enviado, faturado, assinado, vendedor, situação, ano e campos personalizados | Mesmo catálogo nativo, com definições atuais da empresa verificadas novamente; filtros salvos seguem `NativeCompanyFilters`. |
| Split preview | Abertura sem sair da lista, colunas reduzidas e expansão | Rotas originais `get_proposal_data_ajax`/`get_estimate_data_ajax` leem a origem autorizada; `native-admin-sales.js` cancela respostas superadas e verifica o contexto antes de aplicá-las ao DOM. |
| Pipeline | Colunas próprias de cada módulo, busca, ordenação e carregar mais | Readers de pipeline existentes foram preservados; seleção lista/pipeline fica vinculada à empresa na sessão. Não se afirma paridade de drag/drop nesta etapa. |
| Notas | Ler e adicionar dentro do documento | Rotas originais delegam ao serviço `NativeCompanyAuxiliaryUi` depois da confirmação do documento e de POST/CSRF para escrita. |
| Totais Orçamentos | Cinco situações, seletor de moeda/ano | Soma por moeda selecionada e ano dentro da união autorizada; não combina valores de moedas diferentes. |
| Histórico da principal | IDs antigos e vínculos já existentes | União explícita de legado autorizado e documentos privados, com IDs canônicos distintos. A empresa secundária nunca adota um ID cru histórico. |
| Formulário nesta etapa | Views originais distintas de Propostas e Orçamentos | As rotas `proposals/proposal` e `estimates/estimate` compõem as views originais para criar/editar documentos próprios, com os writers canônicos e metadados tipados descritos abaixo. O histórico da principal conserva o workspace de revisão permitido. |

O preview novo preserva acesso aos recursos recentes de revisão/cópia auditada, anexos privados e notificações para o portal. Ainda precisa de comparação visual real e de cobertura de todos os recursos laterais da referência; os testes de HTML não substituem essa comparação.

## Propostas e Orçamentos: formulários e gravação real

`NativeCompanySalesOriginalHttp`, `NativeCompanySalesOriginalForm` e `NativeCompanySalesOriginalInput` conectam as views e o formato de POST originais aos serviços de Vendas existentes. O controller exige contexto selado, método admitido, CSRF nativo e coincidência entre o ID da rota e o ID submetido. A renderização é reautorizada antes da resposta; o formulário de edição carrega revisão, motivo e confirmação exigidos pelo contrato atual.

| Conteúdo/ação | Implementação e limite verificável |
| --- | --- |
| Orçamentos: cliente, projeto, número, datas, moeda, referência, agente, situação, faturamento/entrega, notas, termos, tags e campos do cabeçalho | Controles da view original, resolução por empresa e validação dos valores reais. Numeração, cliente, moeda e snapshot fiscal existentes permanecem preservados na revisão. Validade vazia continua opcional. Aceite/recusa usam as ações canônicas. |
| Propostas: assunto, destinatário, endereço, país, contato, responsável, comentários, apresentação de quantidade, projeto e campos próprios | A view de Propostas é mantida distinta da de Orçamentos. `native_proposal` conserva esses valores e sanitiza o conteúdo rico já existente. O conteúdo é preservado no formulário e exibido no preview; edição inline/autosave e sua composição no PDF ainda têm as limitações registradas abaixo. |
| Itens, impostos, quantidade/unidade, preço, opcionais, desconto e ajuste | Linhas independentes são calculadas por `CompanyInvoiceLines` com `native_sales_v1`. Linhas canônicas são posições do documento, sem fabricar IDs de `itemable`. O servidor confere totais enviados e rejeita divergências. O HTML de edição já carrega a ordem conhecida; o handler original a recalcula após reordenação. |
| Campos de itens, inclusive checkbox e multiselect | `NativeCompanySalesEditorItems` fornece definições/valores autorizados e sua revisão. O payload privado preserva valores tipados e posições; valores são validados novamente dentro da transação. A prévia não envia nomes sem uma linha aplicada. O clonador preserva múltiplas escolhas, caracteres literais, obrigatoriedade real, textarea e hiperlink. |
| Catálogo e preenchimento de cliente | Lookups de itens usam a permissão do documento, ID e revisão reais, sem conceder CRUD de Itens. `client_change_data`/`get_relation_data_values` retornam dados do perfil/contato próprios autorizados; um cliente compartilhado não recebe os dados privados de outra empresa. Respostas atrasadas são descartadas após mudança de seleção/contexto. |
| Criar, revisar, editar e copiar | `CompanySales`/`CompanySalesMutations` continuam responsáveis pela transação, concorrência, identidade, auditoria e replay. Tags e campos reais do cabeçalho são gravados atomicamente; os controles de revisão/cópia transportam metadados em JSON tipado para preservar `null`, listas e valores opcionais. Chamadores antigos sem metadados conservam seu contrato. |
| Converter em Fatura | `NativeCompanyEstimateSmartTransfer` atende os cabeçalhos compatíveis de ambos os documentos. `NativeCompanySalesItemTransfer` aplica os valores às linhas reais produzidas pelo writer de Faturas. Auditoria contém compromissos/hashes e IDs de definições, sem copiar conteúdo privado. Rollback tardio, recibo de recuperação e replay têm testes SQL próprios. |
| Privacidade e envio | DTOs genéricos/busca e portal não expõem metadados privados de cabeçalho/itens. A publicação de campos de Propostas no portal/PDF ainda não tem equivalência completa. Envio conserva o contrato atual de documento aceito e contato autorizado. |

O bundle compartilhado de formulário conserva uma única implementação dos cálculos e das ações de itens. Os eventos de opcionalidade passaram a ser carregados nesse bundle, também usado pelas views nativas que não carregam `main.js`. A prévia de faturamento/entrega escapa o texto antes de inserir quebras de linha. O transporte do formulário mantém controles desabilitados necessários, arrays repetidos, uma requisição pendente por vez e verifica o contexto antes de aplicar a resposta. Essas alterações têm testes de código executado e controles HTML reais; a validação de interação completa depende do browser no CI.

## Projeção monetária e migration 489

O filtro/ordenação por subtotal e impostos requer valores exatos antes da paginação SQL. A tabela aditiva `company_sales_projection` guarda `id`, `issuer_id`, hash do payload, hash da projeção, `subtotal` e `total_tax` em `DECIMAL(15,2)`, com índice `(issuer_id,id)` e FKs para documento e empresa. A fonte continua sendo `company_sales_documents`; a migration não emite documentos nem reserva números.

`NativeCompanySalesProjection::backfill()` recalcula a projeção com `CompanyInvoiceLines` em transação própria, verifica os hashes da origem, interrompe a transação inteira se a origem não puder ser comprovada e aceita replay. Os writers canônicos passam por `CompanySalesMutations::confirmBoundary()`, atualizando a projeção na mesma transação de criar, editar, copiar, decidir e vincular a conversão.

Cada consulta verifica presença, proprietário, hash e checksum da projeção com um JOIN de linhas pequenas, sem carregar todos os JSONs nem fazer uma consulta por documento. O cálculo completo é repetido apenas para os documentos efetivamente hidratados. Há verificação também da direção inversa de proprietário, para que uma projeção adulterada não seja aceita nem na empresa de origem nem na de destino. A validação global de checksum ainda é linear no número de documentos da empresa; não se apresenta esse mecanismo como custo constante.

## Conflitos reais preservados

Estas diferenças foram localizadas no código. Não são defeitos ou permissões concedidas implicitamente por este documento.

| Recurso da referência | Contrato atual | Tratamento |
| --- | --- | --- |
| Proposta vinculada diretamente a Lead | `CompanySalesBase::input/context` exige cliente real autorizado e captura destinatário/moeda/identidade para o documento | Não converter Lead em Cliente silenciosamente nem alterar o destinatário congelado. A equivalência desse vínculo exige decisão sobre o contrato. |
| Enviar proposta/orçamento em rascunho/aberto | `CompanySalesDelivery::queue` aceita documento aceito/convertido, contato autorizado e transporte próprio; envio é notificação do portal autenticado | Não recorrer a SMTP global, link público ou simular envio. Preservar a operação atual e registrar o conflito. |
| Edição financeira direta de qualquer documento | `CompanySalesMutations::edit` exige documento aberto, revisão, motivo e confirmação; cliente/moeda/identidade não mudam | Preservar concorrência e auditoria; documentos aceitos/convertidos e histórico financeiro não são regravados. |
| Excluir/apagar informações de aceite e assinaturas | A base recente conserva provas e decisões históricas | Não reintroduzir exclusão de prova por cópia do controller antigo. |
| Mudar situação livremente ou converter Proposta em Orçamento | Estados/conversões canônicas atuais têm ações e limites explícitos | As situações abertas compatíveis são preservadas. Aceite/recusa continuam sendo decisões próprias; conversão Proposta→Orçamento não é simulada. |
| Editar conteúdo de Proposta com autosave inline | `Proposals::save_proposal_data` é um writer legado global; a revisão canônica exige versão, motivo e confirmação | O conteúdo existente é conservado/sanitizado. O autosave legado não foi admitido como bypass do writer; definir a experiência de revisão é necessário antes de restaurá-lo. |
| Criar a partir de `estimate_request_id` | O destino precisa da prova do pedido e do vínculo de origem autorizado | Um POST sem a conversão de origem própria é rejeitado, sem gravar um orçamento sem vínculo. |

## Evidências executadas neste ambiente

| Comando | Resultado observado | Alcance |
| --- | --- | --- |
| `php tests/companies/native_sales_table.php` | 69 verificações adicionais aprovadas, além de 175 de fonte/histórico e 20 de pipeline já incluídas | SQLite descartável real; projeção, cálculo decimal, replay, escrita/cópia/decisão/conversão, rollback, adulteração fora da página, empresa A/B/principal, filtros e posição das colunas. |
| `php tests/companies/native_sales_history_controller.php` | 7 verificações adicionais aprovadas sobre as 195 do fixture | Rotas originais e DTOs históricos, edição descritiva permitida, concorrência, PDF TCPDF real e negação entre empresas; borda CI sintética. |
| `php tests/companies/native_sales_original_form.php` | 36 verificações adicionais aprovadas | SQL descartável: criação/edição/cópia, campos de cada documento, definição/valores de itens, tags, privacidade, conversão e replay. |
| `php tests/companies/native_sales_original_boundaries.php` | 45 verificações adicionais aprovadas | ACL e revogação, dados de outra empresa, status/envio proibidos, metadados concorrentes, localização, campos obrigatórios e rollback tardio. |
| `php tests/companies/native_sales_original_view.php` | 52 verificações adicionais aprovadas | Views aninhadas e helper de campos reais; DOM serializa controles efetivamente enviados, inclusive checkbox repetido e prévia sem names indevidos. Shell e helpers primitivos são seams explícitos, não prova visual. |
| `php tests/companies/native_sales_original_http.php` | 53 verificações adicionais aprovadas | Controllers/serviços/views reais, POST, respostas, contexto, cliente e catálogo em SQL. I/O do framework e escopo selado são seams explícitos. |
| `php tests/companies/native_sales_metadata_transport.php` | 16 verificações adicionais aprovadas | Review→HTML real→controles→controller→SQL para edição/cópia; preservação de metadados tipados/validade opcional e rejeição de JSON inválido ou duplicado. |
| `php tests/companies/native_sales_item_transfer.php` | 16 verificações adicionais aprovadas | Falha após emissão, recuperação, valor de destino adulterado, rollback por alteração de origem durante gravação dos campos e repetição sem auditoria duplicada. |
| `node --test tests/native_company/sales_original_transport.test.cjs tests/native_company/sales_form_shared.test.cjs tests/native_company/invoice_calculation_differential.test.cjs` | 24 testes aprovados | JavaScript efetivamente executado, contratos únicos do bundle compartilhado e comparação com o calculador PHP; os doubles de DOM/transporte são explícitos. |

As contagens adicionais acima não devem ser somadas como execuções independentes: os testes reutilizam fixtures/suítes anteriores. `native_estimate_form_fields.php` e `native_sales_table.php` foram reexecutados após a extensão, sem regressão observada.

O teste `tests/companies/native_sales_projection_mysql.php` executa a suíte com MySQL real, valida DDL/precisão e executa a classe real `Migration_Version_489::up()` duas vezes, comparando fonte, auditoria, projeção e faturas. O primeiro checkpoint passou nesse teste no CI remoto; não foi executado MySQL local. A composição posterior de formulários/metadados ainda depende de novo CI. O resultado remoto deve ser associado ao SHA registrado pela frente de publicação, sem estender a aprovação aos arquivos posteriores.

Os browsers existentes de Vendas, preferências, PDF, mutações e `operations_resume_439_sales_lines_browser.cjs` agora navegam e submetem os formulários originais através de `tests/helpers/native_sales_driver.cjs`. A suíte 439 cria definições reais de campos opcionais pelo writer da empresa, aplica checkbox/multiselect/textarea/link, verifica o payload gravado e os valores nas linhas reais da Fatura. O fixture decodifica IDs com `NativeCompanyResourceId`; não infere identidades numéricas. Esses browsers receberam verificação de sintaxe local; interação, screenshots e resultados reais desta composição aguardam CI.

Testes de `CompanyRequestScope` das rotas novas são mantidos em `tests/native_r8_routes.php` na frente compartilhada. Seu resultado deve ser relatado a partir da execução correspondente, sem confundi-lo com os doubles de controller usados nesta suíte.

## Validação ainda necessária

Não houve sessão browser real com dados iguais nas duas versões, nem instalação MySQL local, SMTP ou operação bancária. O Chromium local foi bloqueado pela política do ambiente; não houve tentativa de contornar o bloqueio. Comparação visual lado a lado, PWA instalado/offline, navegação completa de todos os estados, drag/drop e recursos laterais de Propostas/Orçamentos continuam pendentes. Os resultados remotos do CI devem ser registrados pelo SHA efetivamente testado.

AC01–AC05, AC08–AC13 e AC45–AC49 não podem ser marcados como inteiramente validados apenas com este checkpoint. A PR em rascunho torna o resultado concreto para revisão/CI; não equivale à homologação integral dos requisitos.
