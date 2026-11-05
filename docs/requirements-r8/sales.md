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
| Formulário nesta etapa | Views originais completas foram inventariadas | O formulário operacional da base recente continua disponível enquanto a integração da view original é concluída. Não se declara R01/R03 integralmente concluído por ter restaurado a lista. |

O preview novo preserva acesso aos recursos recentes de revisão/cópia auditada, anexos privados e notificações para o portal. Ainda precisa de comparação visual real e de cobertura de todos os recursos laterais da referência; os testes de HTML não substituem essa comparação.

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
| Mudar situação livremente ou converter Proposta em Orçamento | Estados/conversões canônicas atuais têm ações e limites explícitos | Não inventar alias de situação nem registrar uma conversão que não ocorreu. As ações compatíveis serão conectadas ao writer existente; diferenças continuam identificadas. |

## Evidências executadas neste ambiente

| Comando | Resultado observado | Alcance |
| --- | --- | --- |
| `php tests/companies/native_sales_table.php` | 69 verificações adicionais aprovadas, além de 175 de fonte/histórico e 20 de pipeline já incluídas | SQLite descartável real; projeção, cálculo decimal, replay, escrita/cópia/decisão/conversão, rollback, adulteração fora da página, empresa A/B/principal, filtros e posição das colunas. |
| `php tests/companies/native_sales_history_controller.php` | 7 verificações adicionais aprovadas sobre as 195 do fixture | Rotas originais e DTOs históricos, edição descritiva permitida, concorrência, PDF TCPDF real e negação entre empresas; borda CI sintética. |
| `php -l` nos arquivos de serviços, controllers e views de Vendas tocados/relacionados | 57 arquivos aprovados no checkpoint | Sintaxe PHP, sem afirmar comportamento em produção. |
| `node --check assets/js/native-admin-sales.js` | Aprovado | Sintaxe JavaScript. |

O teste `tests/companies/native_sales_projection_mysql.php` foi adicionado para executar a suíte com MySQL real, validar DDL/precisão e executar a classe real `Migration_Version_489::up()` duas vezes, comparando fonte, auditoria, projeção e faturas. **Não executado localmente**; exige banco descartável nomeado `issuer_*module_audit` e deve rodar no CI.

Testes de `CompanyRequestScope` das rotas novas são mantidos em `tests/native_r8_routes.php` na frente compartilhada. Seu resultado deve ser relatado a partir da execução correspondente, sem confundi-lo com os doubles de controller usados nesta suíte.

## Validação ainda necessária

Não houve sessão browser real com dados iguais nas duas versões, nem instalação MySQL local, SMTP ou operação bancária. O Chromium local foi bloqueado pela política do ambiente; não houve tentativa de contornar o bloqueio. Comparação visual lado a lado, PWA instalado/offline, navegação completa de todos os estados, drag/drop e recursos laterais de Propostas/Orçamentos continuam pendentes. Os resultados remotos do CI devem ser registrados pelo SHA efetivamente testado.

AC01–AC05, AC08–AC13 e AC45–AC49 não podem ser marcados como inteiramente validados apenas com este checkpoint. A PR em rascunho torna o resultado concreto para revisão/CI; não equivale à homologação integral dos requisitos.
