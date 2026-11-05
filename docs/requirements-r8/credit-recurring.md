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
| Número, data, cliente, status, projeto, referência, total, saldo restante | Oito colunas na mesma ordem, mais os campos personalizados reais autorizados. Nas novas emissões, referência e projeto são lidos dos metadados confirmados. Projeto é uma referência tipada à empresa e ao cliente; registros históricos sem vínculo não recebem projeto presumido. |
| Pesquisa, paginação, ordenação, opção Todos | Contagens e seleção SQL explícitas por empresa; busca literal, ordenação com allowlist, desempate por ID e paginação antes da hidratação do leitor financeiro. `length=-1` não é reduzido silenciosamente a cem. |
| Filtros número, referência, data, total, saldo, status e ano | Builder original, filtros salvos no contexto de empresa e regras vinculadas, sem transformar nomes de campo recebidos em SQL. |
| Campos personalizados da nota de crédito | Catálogo real de `credit_note`, autorização por empresa, colunas extras e filtros. A emissão também grava os campos autorizados. Um campo capturado como privado permanece privado mesmo se a definição atual for relaxada; a pesquisa não revela seu valor. |
| Seleção da linha e alternância tabela/preview | `init_credit_note`, `#credit_note`, ID oculto e rota original `get_credit_note_data_ajax`. |
| Nova nota | Rota original `/credit_notes/credit_note`, formulário contábil com clientes, catálogo, moedas e projetos autorizados; POST no writer canônico. |
| Links já publicados do destino | `/credit_notes?credit=ID`, `?source=company&credit=ID`, `list_credit_notes/ID` e alias `?pdf=1` continuam funcionando. |

A listagem utiliza somente notas com proprietário explícito e snapshot canônico
em `company_credit_documents`, como exige `CompanyCredits` na base recente. Ela
não associa notas `issuer_id IS NULL` à empresa principal por suposição. A ausência
de leitura/edição dessas notas no adapter canônico é uma pendência de cobertura,
não uma migração ou exclusão de dados. A atribuição desses registros requer uma
regra de propriedade verificável, sem criar outra arquitetura de empresas.

Para documentos anteriores à extensão, `reference_no` continua vindo da projeção
existente de `creditnotes`; não foi reescrito nem incluído retroativamente em outro
hash. Para novas emissões com `native_metadata`, a referência integra o hash de
entrada, é confirmada na projeção nativa antes do commit e validada na leitura.
O vínculo `native_snapshot.project` guarda tipo, ID, revisão e nome capturado;
`creditnotes.project_id` permanece zero, evitando confundir a chave canônica com
uma chave de projeto legado. A relação de Projetos para Créditos usa essa
identidade exata e não todos os documentos do mesmo cliente.

### Formulário e campos persistentes

O editor usa a estrutura contábil da referência: cabeçalho em duas colunas,
cliente/projeto, resumo e modal de endereços, data/moeda/referência, notas
administrativas e campos personalizados, seleção de catálogo, grade de itens,
quantidade/horas, tributos, desconto, ajuste, totais, nota ao cliente, termos e
barra inferior de ações. Mantém as classes e identidade da versão recente.

| Campo real da 4.0.2 | Implementação e limite verificado |
| --- | --- |
| Cliente, moeda, data | Dados autorizados e roundtrip real pelo writer. Trocar cliente limpa seleção de projeto e endereços anteriores antes da consulta contextual. Perfil compartilhado não fornece endereço proprietário de outra empresa. |
| Número/prefixo e alteração de numeração | Numeração canônica atribuída ao salvar. O editor não envia número manual. As rotas globais `validate_number` e `update_number_settings` não alteram documento canônico. |
| Projeto | Seleção de projeto da mesma empresa e cliente com permissão de Projetos; tipo, revisão e nome confirmados dentro da transação. Sem permissão, não são publicados nomes ou opções. |
| Referência, nota administrativa e termos | Metadados opcionais versionados, persistidos e confirmados. Termos aceitam até 10.000 bytes, o limite da identidade documental existente; excesso é rejeitado antes de alocar a numeração. Nota administrativa não entra no PDF nem no DTO financeiro público. |
| Endereços de cobrança e entrega, país, apresentação de entrega | Modal com todos os campos; perfil próprio pode preencher os controles. País deve existir; nome do país e campos escolhidos ficam capturados na emissão. Flags de entrega são preservadas no preview e PDF. |
| Descrição, quantidade, preço, tributos | Grade editável antes da emissão, adição/remoção, ordenação de itens e múltiplos tributos. Servidor mantém seu cálculo e validações existentes. |
| Descrição longa, unidade, quantidade/horas | Dados paralelos às linhas financeiras, sem mudar o formato consumido pelos leitores antigos; snapshot, projeção nativa, preview e PDF. |
| Campos personalizados da nota e dos itens | Dez tipos reais: input, number, textarea, select, multiselect, checkbox, datepicker, datepicker_time, colorpicker e link. Labels/opções escapadas; listas usam nomes de formulário com `[]`; links são reconstruídos como âncoras seguras. Tipos, opções, flags e revisões são validados novamente na gravação. |
| Selecionar item cadastrado | Catálogo próprio ou legado comprovadamente permitido à principal, com descrição longa, unidade, tributos, preço da moeda e valores personalizados autorizados. Não exige permissão de edição de catálogo ou de Faturas. Precisão de preço incompatível com o contrato atual é recusada com 409, sem arredondamento silencioso. |
| Desconto fixo/percentual antes/depois do tributo, ajuste | Valores e modo existentes preservados. A prévia usa `BigInt`, arredondamento por linha e rateio pelo maior resto; servidor recalcula antes de persistir. |
| Nota do cliente | `credit[note]`, com persistência pelo contrato atual. |

O formulário transporta o formato de datas usado na renderização e converte
campos personalizados para o formato canônico. Uma alteração posterior da
preferência da empresa não troca dia e mês do formulário já aberto. Esse campo
não concede autoridade nem modifica preferências. A validação do contexto segue
o token capturado; respostas assíncronas antigas de cliente, moeda ou catálogo
são canceladas/ignoradas e não repõem dados na tela de outra seleção.

A flag existente `no-calculate-total` impede o observador compartilhado de
sobrescrever a prévia monetária com outro contrato em ponto flutuante. O subtotal
por item no preview PHP vem do calculador validado, sem multiplicação insegura no
HTML. Os dados adicionais não alteram saldo, movimentos, desconto ou estado.

### Persistência, compatibilidade e dados privados

`CompanyCreditMetadata` normaliza uma entrada opcional `native_metadata.version=1`
no JSON existente de `company_credit_documents.items_json`. O envelope separa
metadados de cabeçalho, referência tipada de projeto, metadados paralelos dos itens
e frames dos campos personalizados com hash das definições. Nenhuma coluna nova,
DDL ou transformação de documento histórico foi necessária nesta extensão.

A entrada normalizada integra o hash de idempotência já existente. O
`native_snapshot` derivado guarda nomes e flags confirmados durante a transação;
ele fica fora do hash de entrada e recebe vínculo próprio no evento auditado
`company_credit_metadata_captured`. Repetir a mesma emissão não recaptura dados
após renomear projeto ou mudar definições. Documento antigo sem metadados mantém
os bytes da entrada, o hash, a chave de repetição e o DTO anteriores.

Uma prova privada, restrita ao trecho ativo de `InvoicePaymentWriter`, vincula
instância, conexão, empresa, ator, crédito e linhas recém-criados. Só essa prova
permite salvar campos personalizados de criação nos namespaces `credit_note` e
`credit_note_item`. Ela é removida em `finally`. O serviço relê cabeçalho, recibo,
linhas, valores personalizados e auditoria antes do commit; falha de persistência
ou alteração pelo banco desfaz documento, sequência, valores e movimentos. A
edição genérica de campos personalizados não contorna a imutabilidade emitida.

`CompanyCredits::get` e os leitores financeiros públicos conservam seu formato.
Os métodos administrativos separados acrescentam somente a apresentação
permitida. PDF intersecta permissões atuais e flags capturadas: nota
administrativa e campos privados/não publicados não são serializados. Projeto
é mostrado apenas no contexto administrativo autorizado; sua publicação no PDF
não foi presumida a partir de uma opção global. Teste com TCPDF e `pdftotext`
confirma conteúdo público e ausência dos marcadores privados no PDF real.

### Documento e ações

O preview conserva painel, abas de documento, faturas creditadas e reembolsos,
botões de PDF e expansão. A aba de histórico mantém os movimentos adicionais
auditados do destino. Aplicação e reembolso usam modais; correções usam os
estornos explícitos do ledger. Seleção de fatura exige a mesma empresa, cliente,
moeda, permissão e posição atual.

| Ação real da 4.0.2 | Mapeamento e pendência |
| --- | --- |
| Aplicar a faturas | `apply`, mesma empresa/cliente/moeda, referência e razão auditadas. |
| Remover aplicação | `unapply`, com referência ao movimento pai e limites do saldo; histórico conservado. |
| Reembolso, editar/excluir reembolso | `refund` e `refund_reverse`, com evidências e autoria; sem chamada de banco/provedor. A edição/destruição de histórico é incompatível com o ledger atual. |
| Anular/reabrir | `void` nas condições existentes, sem histórico impeditivo. `mark_open` global não reabre uma nota imutável. |
| Editar/excluir nota emitida | Edição de ID existente retorna 409 após leitura autorizada. Conflito real entre edição destrutiva legada e documento emitido imutável; requer decisão de produto antes de mudar esse contrato. |
| Criar nota a partir de fatura | `credit_note_from_invoice` ainda não tem adapter. É pendência de implementação/validação do contrato, não decisão do usuário inferida pela ausência do campo. |
| Enviar por email, anexos, excluir anexo | Métodos legados preservados; adapters contextuais ainda não implementados/validados nesta frente. Esses fluxos não são contados como equivalentes. |
| PDF: abrir, baixar, imprimir | `CompanyCredits::pdf`, identidade e snapshots atuais, autorização e auditoria; alias antigo e rota original mantidos. |

A extensão resolveu o roundtrip de criação dos campos opcionais compatíveis; a
sua antiga ausência no writer não é tratada como conflito que obrigue nova
autorização. Permanecem a implementação dos adapters indicados, a decisão sobre
imutabilidade pós-emissão e a atribuição de dados históricos sem proprietário.
Não há equivalência integral de R07 declarada.

### Consultas auxiliares admitidas

- `GET/HEAD credit_notes/client_change_data/{clientId}`: perfil e projetos próprios,
  sob permissões de cliente/crédito e, quando aplicável, projeto.
- `GET/HEAD credit_notes/items?q=...`: opções de catálogo no contexto autorizado.
- `GET/HEAD credit_notes/item/{itemId}?currency={id}`: item tipado, valores da moeda,
  tributos e campos personalizados permitidos. ID legado só para principal comprovada.

POST dessas consultas retorna 405. A admissão antes da hidratação do controller
usa o mesmo contexto nativo para empresa principal e secundária; consulta
estrangeira é recusada e HEAD não produz corpo. As rotas legadas equivalentes
não são abertas como fallback. A criação continua em POST com CSRF, contexto,
chave de idempotência e writer existentes.

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
| `php tests/companies/native_credit_form.php` | 132 cumulativas: DOM produzido pela view real, oito colunas base, metadados, modal, projetos/catálogo, número não submetido, totais e HEAD sem corpo. |
| `php tests/companies/credit_metadata.php` | 30 adicionais / 115 cumulativas: writer real, snapshot, hashes históricos, repetição, projeto A/B/revisão, prova de criação/ACL, privacidade, falha tardia e trigger SQL que altera referência com rollback completo, limite real de termos e PDF. |
| `php tests/companies/native_credit_metadata_form.php` | 39 adicionais / 171 cumulativas: DOM e controles submetidos reais, dez tipos de campos da nota/itens, opções com aspas/HTML literal, ordem/gaps, endpoints de cliente/catálogo e verbos, moeda/precisão, perfis compartilhados, data em formulário antigo, privacy na busca, repetição e conteúdo efetivo de PDF. |
| `php tests/companies/credits_reader.php` | 96 cumulativas: contrato financeiro público anterior, leitor e PDF privado preservados. |
| `php tests/companies/credit_pdf_revalidation.php` | 101 cumulativas: autorização e estado novamente confirmados antes de devolver PDF. |
| `node --test tests/native_company/credit_editor.test.cjs` | Oito testes: precisão, arredondamento/rateio, entradas inválidas e cinco cenários de consultas assíncronas com resposta substituída, troca de empresa/cliente/moeda, falhas e transporte ausente. |
| `php tests/companies/native_recurring_directory.php` | 107 cumulativas: cálculo99/zero, datas/ciclos, empresas, principal, filtros, hashes, ALL102 e página após registro100. |
| `php tests/companies/native_recurring_controller.php` | 143 cumulativas: controller real com fronteira CI simulada, oito colunas, ID tipado, preview parcial/controles, HEAD, CSRF e acesso estrangeiro. Inclui os testes de modos, principal, rascunho e retomada existentes. |
| `php tests/companies/native_invoice_calculation_recurring.php` | 91 cumulativas: emissão real do contrato de cálculo nativo e manutenção de linhas gratuitas. |
| `php tests/companies/invoice_recurring_modes_markup_440.php` | 133 cumulativas: controle real de opt-in `send`, rótulo, valor e ausência de marcação automática. |
| `node --check` dos assets e scripts browser alterados | Sintaxe aprovada. Isso não executa navegador. |

Os testes em `tests/companies/` usam o banco descartável configurável dos fixtures
existentes e estão preparados para o runner MySQL do repositório. Nenhum resultado
MySQL, instalação/upgrade, PWA ou comparação visual está sendo inferido do SQLite.

Scripts de navegador atualizados para o CI:

- `tests/companies_credits_browser.cjs`: criação pela nova rota e controles reais,
  seleção de cliente/projeto/catálogo, campos personalizados da nota/itens, opções
  literais, ordem de linhas, modal de cobrança/entrega, referência, termos,
  múltiplos tributos, prévia exata, snapshots do editor/preview, conferência SQL de
  vínculo/valores e conteúdo público/privado do PDF com `pdftotext`; preserva os
  fluxos de aplicação/estorno/reembolso/estorno/anulação, saldo, contexto antigo e B.
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
