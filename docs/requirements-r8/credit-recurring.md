# R05 e R07 — Faturas recorrentes e Notas de crédito

## Bases e alcance desta evidência

Referência inspecionada: `v4.0.2`, commit
`aa58b832b3c5ed04a599caacba125b056c081ba7`. Destino efetivo encontrado na `main`:
4.8.8, commit `3cd2ac577a969840c94b019f602cb69d710c0e78`, posterior à 4.8.7
informada no requisito. As alterações desta frente não pertencem à 4.0.3.

A referência mantém controllers/views globais e modelos financeiros diferentes
dos leitores, snapshots e movimentos canônicos por empresa do destino. Reutilizar
os modelos globais por trás das views recolocaria consultas e alterações fora do
contexto capturado. Foram adaptadas as superfícies originais aos serviços reais
do destino. Os limites abaixo impedem declarar equivalência integral com 4.0.2.

## R07 — inventário e adaptação

### Lista, filtros e navegação

A referência real usa `admin/credit_notes/manage.php`, `table_html.php`,
`credit_note.php` e `credit_note_preview_template.php`. A lista e o contêiner de
preview originais foram reutilizados. `NativeCompanyCreditDirectory`,
`NativeCompanyCreditTable`, `NativeCompanyCreditRules`, `NativeCompanyCreditColumns`
e a factory substituem a consulta global por dados autorizados da empresa.

| Elemento observado na referência | Comportamento entregue pelo adapter |
| --- | --- |
| Número, data, cliente, status, projeto, referência, total, saldo restante | Oito colunas na mesma ordem, mais os campos personalizados reais autorizados. Projeto permanece vazio quando o documento canônico não fornece esse vínculo; não é inferido a partir de outro documento. |
| Pesquisa, paginação, ordenação, opção Todos | Contagens e seleção SQL explícitas por empresa; busca literal, ordenação com allowlist, desempate por ID e paginação antes da hidratação do leitor financeiro. `length=-1` não é reduzido silenciosamente a cem. |
| Filtros número, referência, data, total, saldo, status e ano | Builder original, filtros salvos no contexto de empresa e regras vinculadas, sem transformar nomes de campo recebidos em SQL. |
| Campos personalizados da nota de crédito | Catálogo real de `credit_note`, autorização por empresa, colunas extras e filtros. O formulário de emissão ainda não grava esses campos: leitura na tabela não é alegada como edição completa. |
| Seleção da linha e alternância tabela/preview | `init_credit_note`, `#credit_note`, ID oculto e rota original `get_credit_note_data_ajax`. |
| Nova nota | Rota original `/credit_notes/credit_note`, com clientes e moedas autorizados. O POST mantém o writer e o contrato publicados do destino. |
| Links já publicados do destino | `/credit_notes?credit=ID`, `?source=company&credit=ID`, `list_credit_notes/ID` e alias `?pdf=1` continuam funcionando. |

A listagem utiliza somente notas com proprietário explícito e snapshot canônico
em `company_credit_documents`, como exige `CompanyCredits` na base recente. Ela
não associa notas `issuer_id IS NULL` à empresa principal por suposição. A ausência
de leitura/edição dessas notas no adapter canônico é uma pendência de cobertura,
não uma migração ou exclusão de dados.

`reference_no` é lido da projeção existente de `creditnotes`; o writer atual não
oferece esse campo como entrada e ele não integra o snapshot monetário de
`CompanyCredits`. Esta entrega não afirma ter implementado edição de referência
nem que a referência esteja protegida pelo hash financeiro. Projeto também não
ganha um vínculo sintético para preencher a coluna.

### Documento, ações e cálculo

O preview conserva painel, abas de documento, faturas creditadas e reembolsos,
botões de PDF e expansão. A aba de histórico mantém os movimentos adicionais
auditados que já existem no destino. Aplicação e reembolso usam modais; correções
usam os estornos explícitos do ledger. Todos os valores são escapados e a seleção
de faturas considera a mesma empresa, cliente, moeda, permissão e posição atual.

O editor deixou os fieldsets resumidos e passou a usar a grade contábil com
cliente/data/moeda, itens, tributos, desconto, ajuste, totais, nota e barra inferior
de ações. O contrato do writer não foi ampliado para aceitar campos que seriam
descartados. A prévia local calcula centavos com `BigInt`, respeitando o contrato
atual de arredondamento, tributos por item e rateio do desconto pelo maior resto.
O servidor recalcula tudo antes de gravar. A flag existente `no-calculate-total`
impede que o observador compartilhado de formulários sobrescreva essa prévia
com o cálculo em ponto flutuante de outro contrato. O subtotal por item no preview
PHP passa pelo calculador monetário validado; não há multiplicação insegura no HTML.

| Campo/ação real da 4.0.2 | Mapeamento atual e limite |
| --- | --- |
| Cliente, moeda, data | Controles de emissão vinculados aos dados autorizados; roundtrip pelo writer existente. |
| Número/prefixo e alteração de numeração | Numeração canônica atribuída ao salvar. O editor mostra essa condição e não envia número manual. `validate_number` e `update_number_settings` globais não foram habilitados para modificar documento canônico. |
| Descrição, quantidade, preço, tributos | Linhas editáveis antes da emissão; adição/remoção, múltiplos tributos e total exato. |
| Descrição longa, unidade, campos personalizados do item, apresentação de quantidade/horas | Ausentes no contrato atual de `CompanyCreditWriter`; não foram apresentados como campos persistentes nem descartados silenciosamente. Exigem extensão explícita e compatível do snapshot. |
| Desconto fixo/percentual antes/depois do tributo, ajuste | Valores e modo existentes preservados. O cálculo financeiro do destino permanece; não foi trocado pelo contrato de Faturas para obter semelhança visual. |
| Nota do cliente | `credit[note]`, com persistência pelo snapshot atual. |
| Projeto, referência, nota administrativa, endereços editáveis, termos, campos personalizados da nota | Não há roundtrip de emissão implementado para esses campos. Endereços e termos apresentados no documento são os snapshots reais existentes, sem edição presumida. |
| Aplicar a faturas | Operação `apply` existente, mesma empresa/cliente/moeda, referência e razão auditadas. |
| Remover aplicação | Operação `unapply` existente, com referência ao movimento pai e limites do saldo. Não é exclusão física do histórico. |
| Reembolso, editar/excluir reembolso | Registro local `refund` e correção `refund_reverse`; mantém evidências e autoria. Não aciona banco/provedor. Edição/destruição legada do histórico não foi reintroduzida. |
| Anular/reabrir | `void` apenas nas condições existentes do writer, sem histórico impeditivo. `mark_open` global não foi autorizado para reabrir uma nota imutável. |
| Editar/excluir nota emitida | Solicitação de edição de ID existente é recusada com 409 após leitura autorizada. Não se elimina documento ou movimento para imitar o legado. |
| Criar nota a partir de fatura | `credit_note_from_invoice` da referência não recebeu adapter; não se presume conversão equivalente. |
| Enviar por email, anexos, excluir anexo | Os métodos legados existem, mas não há adapter validado nesta frente. Nenhum botão de envio ou anexo foi adicionado sem contrato correspondente. |
| PDF: abrir, baixar, imprimir | Reutiliza `CompanyCredits::pdf`, identidade e snapshots atuais, com autorização e auditoria. Alias antigo e rota original continuam disponíveis. |

Essas diferenças são conflitos de contrato e cobertura, e não defeitos financeiros
novos atribuídos ao usuário. A política de crédito emitido imutável é incompatível
com simplesmente habilitar os métodos globais de editar, apagar ou reabrir. A PR
preserva o contrato recente; equivalência completa de ações e campos de R07
continua pendente da definição e implementação dessas extensões.

## R05 — inventário e adaptação

A referência usa `Invoices::recurring`, `Invoices::table?recurring=1` e as views
`admin/invoices/recurring/list.php`, `list_template.php`, `table_html.php` e
`filter_params.php`. A tabela possui oito colunas: número, valor, ano, cliente,
frequência, ciclos restantes, data da última fatura filha e próxima data. Existem
filtros de ano/agente, ações de nova fatura/retorno e preview ao lado da tabela.

Essas views agora atendem a entrada nativa de Recorrências. A classe de tabela
`recurring-invoices` tem preferência de ordenação própria, evitando misturar as
oito colunas com a preferência da tabela de Faturas. O preview usa o layout de
painéis da base recente e os controles reais das séries.

| Recurso real do destino | Preservação e adaptação |
| --- | --- |
| Série com payload, contexto, configuração e transporte imutáveis | `CompanyInvoiceRecurring::directory()` lê os documentos com o mesmo validador canônico de `document()`, dentro de uma transação pertencente ao serviço. Não existe reader paralelo que confie apenas no JSON. |
| Listagem resumida limitada a cem | A lista principal usa todas as séries autorizadas, contagem, busca, ordenação e paginação efetivas; o contrato público resumido `page()` continua intacto. |
| Datas e frequência | Datas ancoradas e unidade/intervalo vêm da configuração validada; não foram criadas periodicidades novas. Série concluída/cancelada não anuncia nova emissão. |
| Ciclos finitos e ilimitados | Restante calculado a partir de ciclos configurados/concluídos; ilimitado continua explícito. |
| Valor do documento da ocorrência | `CompanyInvoiceLines` calcula com o contrato financeiro gravado, inclusive o contrato nativo e linhas gratuitas. Não usa `float`. |
| Última fatura filha | Exibe a data real da fatura canônica vinculada, lida sob a mesma transação; revisão posterior de um rascunho pode mudar a data em relação à ocorrência. Um rascunho ainda privado não vira uma fatura para preencher a coluna. |
| Agente comercial | Somente `native_fields.sale_agent` realmente gravado e membro autorizado; criador da série não é presumido como agente. |
| Emitir ou criar rascunho privado por ciclo | Modo existente conservado, inclusive revisão/edição posterior do rascunho e emissão explícita. |
| Condição de principal quitada | Principal original declarada, da mesma empresa/cliente/moeda. Não é substituída pela fatura do ciclo anterior. |
| Notificação opt-in e contato/transporte | Checkbox e contratos existentes preservados. Fila não é apresentada como comprovação de entrega. |
| Executar, retomar ocorrência, pausar, retomar série, cancelar | Operações atuais mantêm contexto, ator, revisão, idempotência, auditoria, fila e vínculos. O adapter de lista não invoca worker. |
| Falha depois de gravar rascunho/fatura e antes do vínculo | A leitura e o fluxo de retomada continuam nos serviços canônicos; não se cria outro documento para resolver visualmente a pendência. |
| Nova série | Link `Nova recorrência` abre `invoices/recurring?new=1` com todos os recursos atuais do formulário. POST e redirecionamento existentes permanecem. |

### Identidades e rotas

`/invoices/recurring/ID` já representa **ID de série** na versão recente. A
referência antiga usava o contexto de faturas. Não se reutiliza esse número como
ID de fatura: a tabela identifica `Série #ID` e o novo fragmento é
`GET/HEAD /invoices/recurring_preview/ID`. As faturas de ocorrências mantêm seus
links canônicos `list_invoices/ID`; rascunhos usam `drafts?draft=ID`.

`POST /invoices/table?recurring=1` recebe somente a estrutura DataTables de oito
colunas e os filtros reais de ano/agente. Contexto de empresa/CSRF são validados
antes da consulta. Query de recorrência inválida é recusada pelo adapter, sem
fallback para o modelo global. O preview não aceita POST.

### Contratos distintos ainda pendentes

A referência também contém o link `recurring_rules`, o controller
`Recurring_rules` com `index/create/view/preview/apply` e as views
`list/form/view/preview/result`. Esse é um recurso global distinto dos serviços
de séries canônicas, assim como os campos legados de recorrência em `invoices`.
O link permanece no fluxo legado; não é habilitado no fluxo nativo enquanto não
houver autorização/contexto validados para esse contrato. Os arquivos e dados
desse recurso foram preservados. Ele não foi apagado, convertido em uma série
canônica ou contado como equivalente testado.

O formulário detalhado de séries mantém os recursos recentes, que não têm
correspondência de dados direta com a edição de uma fatura recorrente na 4.0.2.
Não se declara que seu formulário, o editor de regras globais ou faturas legadas
tenham sido integralmente restaurados no contexto multiempresa. A adaptação de
views entregue cobre o diretório e a navegação sobre as séries efetivamente
suportadas pelo destino; a conciliação dos outros contratos continua pendente.

O diretório valida todas as séries autorizadas sob a mesma transação, inclusive
seus vínculos de ocorrências. Isso mantém uma imagem consistente e evita misturar
revisões, mas tem custo proporcional ao número de séries e ocorrências. O teste
com 102 séries prova cobertura/paginação, não desempenho de produção. Não foi
criada projeção materializada sem planejamento de schema e reconciliação.

## Evidências locais e roteiro de CI

Os testes locais usam PHP 8.3, SQLite e Node 24. Os contadores são **cumulativos**
dentro de cada script, incluindo seus fixtures; não devem ser somados para
representar quantidade de cenários independentes.

| Execução local | Resultado |
| --- | --- |
| `php tests/companies/native_credit_directory.php` | 103 verificações cumulativas aprovadas: A/B/principal, filtros, saldo real, custom fields, busca literal, ordenação, ALL, integridade e ausência de mutação da nota. |
| `php tests/companies/native_credit_controller.php` | 117 cumulativas: controller real com fronteira CI simulada; tabela, escape, seleção original, preview/abas/modal, CSRF, ID estrangeiro, imutabilidade, refund e retry sem duplicação. |
| `php tests/companies/native_credit_form.php` | 131 cumulativas: DOM produzido pela view real, grade, nomes de entrada, campos suportados, número não submetido, totais e HEAD sem corpo. |
| `node --test tests/native_company/credit_editor.test.cjs` | Três testes aprovados: precisão além da multiplicação segura de Number, arredondamento/rateio, limites, duplicação de tributos e rejeição de entradas inválidas. |
| `php tests/companies/native_recurring_directory.php` | 107 cumulativas: cálculo99/zero, datas/ciclos, empresas, principal, filtros, hashes, ALL102 e página após registro100. |
| `php tests/companies/native_recurring_controller.php` | 143 cumulativas: controller real com fronteira CI simulada, oito colunas, ID tipado, preview parcial/controles, HEAD, CSRF e acesso estrangeiro. Inclui os testes de modos, principal, rascunho e retomada existentes. |
| `php tests/companies/native_invoice_calculation_recurring.php` | 91 cumulativas: emissão real do contrato de cálculo nativo e manutenção de linhas gratuitas. |
| `php tests/companies/invoice_recurring_modes_markup_440.php` | 133 cumulativas: controle real de opt-in `send`, rótulo, valor e ausência de marcação automática. |
| `node --check` dos assets e scripts browser alterados | Sintaxe aprovada. Isso não executa navegador. |

Os testes em `tests/companies/` usam o banco descartável configurável dos fixtures
existentes e estão preparados para o runner MySQL do repositório. Nenhum resultado
MySQL, instalação/upgrade, PWA ou comparação visual está sendo inferido do SQLite.

Scripts de navegador atualizados para o CI:

- `tests/companies_credits_browser.cjs`: criação pela nova rota, grade real,
  múltiplos itens/tributos, prévia exata, snapshot do editor e preview, PDF,
  aplicação/estorno/reembolso/estorno/anulação, saldo, contexto antigo e empresa B.
- `tests/settings_document_pdf_browser.cjs`: criação de crédito pela rota atual,
  mantendo a verificação existente dos documentos/identidade.
- `tests/companies/invoice_recurring_modes_browser.cjs`: diretório real de oito
  colunas, link de criação, preview HTTP de série, imagem do painel, A/B,
  principal, rascunho, retomada e PDF; nega fragmento de série estrangeira.
- `tests/companies/recurrence_resume_439_browser.cjs` e
  `tests/companies_sales_browser.cjs`: abertura do formulário em `?new=1`, sem
  trocar os POSTs ou o contrato de identificação de séries.

Chromium local permaneceu bloqueado pelo ambiente; a tentativa de execução com
elevação não foi autorizada. Não houve contorno. Esses roteiros precisam executar
no CI remoto antes de se afirmar sucesso em HTTP/MySQL/Chromium. As imagens
programadas são evidência do fluxo novo, não comparação visual lado a lado com a
4.0.2. AC20–25, AC33–38 e AC57–64 permanecem parcialmente pendentes pelos limites
descritos. Nenhum travamento foi atribuído a Crédito ou Recorrências; o relato do
usuário continua associado a Vendas > Pagamentos.
