# R04/R08 — Faturas e Itens

## Referência e limites da comparação

A referência funcional é `v4.0.2`, commit
`aa58b832b3c5ed04a599caacba125b056c081ba7`; o destino é `main` 4.8.8,
commit `3cd2ac577a969840c94b019f602cb69d710c0e78`. Os arquivos e os métodos
encontrados nas duas bases constam de [sales-inventory.json](sales-inventory.json).
O levantamento abrange os recursos reais, não somente os nomes do menu.

Faturas já reutilizava as views originais com adaptadores para documentos da
empresa, enquanto preservava o caminho histórico da principal. Não se substitui
essa implementação pelo controller antigo: os writers recentes preservam
numeração, emissão, origem dos itens, pagamentos, créditos, auditoria e revisões.
Nenhuma operação bancária ou emissão real foi realizada durante esta revisão.

## Faturas: composição preservada e verificada

| Superfície | Contrato verificado |
| --- | --- |
| Lista e linhas | Controller e renderer reais; colunas, filtros, propriedades e origem dos documentos. |
| Formulário | GET e POST originais, view aninhada, itens, taxas congeladas e fronteiras do writer. |
| Preview | Dados, metadados, tags, campos personalizados, créditos aplicados e vínculo de recorrência dentro da empresa. |
| Pagamento pela fatura | Formulário original, método HTTP, CSRF e seleção do leitor pelo proprietário persistido. |
| Linhas e PDF | Renderização original de linhas opcionais; preservação dos leitores e cálculos recentes. |
| Recorrências | Adaptação específica descrita em [credit-recurring.md](credit-recurring.md); um ID de série não é um ID de fatura. |

Foram executados localmente, com PHP 8.3.6 e SQLite sintético, os dez scripts
abaixo. Todos encerraram com código zero e sem diagnóstico PHP de erro/warning:

| Script em `tests/companies/` | Resultado observado |
| --- | --- |
| `native_invoice_table_controller.php` | 140 verificações cumulativas. |
| `native_invoice_form_controller.php` | 248 verificações cumulativas. |
| `native_invoice_form_view.php` | 217 verificações cumulativas. |
| `native_invoice_form_post.php` | 239 verificações cumulativas. |
| `native_invoice_preview_data.php` | Empresa A/B/principal, permissões e leitura sem mutação. |
| `native_invoice_preview_metadata.php` | Tags, campos e revisão vinculados à empresa. |
| `native_invoice_preview_recurring.php` | Principal, ocorrência emitida e propriedade. |
| `native_invoice_preview_credits.php` | Propriedade, ledger e identidade do crédito aplicado. |
| `native_invoice_payment_controller.php` | GET autorizado e fronteiras de método/CSRF. |
| `native_invoice_items_renderer.php` | 86 verificações cumulativas de linhas opcionais. |

Os fixtures compartilham verificações anteriores: seus totais cumulativos não
representam conjuntos disjuntos e não devem ser somados como testes únicos.
Essas execuções usam serviços/SQL/views reais com dependências do framework
controladas; não são navegação HTTP autenticada de uma instalação completa.

## Itens: diferença reproduzida e correção

O controller recente recusava `length=-1`, enviado pela opção **Todos** da
tabela original, e o leitor ordenava por descrição independentemente da
ordenação pedida pelo DataTables. A busca considerava somente a descrição
curta. Esses comportamentos divergiam da interface que continuava sendo exibida.

A tabela agora interpreta o protocolo nativo por `NativeCompanyDataTableWire`.
O plano `NativeCompanyCatalogTableQuery` seleciona, filtra e ordena no banco
antes de aplicar o limite. As expressões de coluna são definidas no servidor;
texto, empresa e valores de campos personalizados são parâmetros vinculados.
Regex e expressões de ordenação arbitrárias são recusadas.

| Aspecto | Resultado da adaptação |
| --- | --- |
| Colunas | Seleção/ID, descrição, descrição longa, preço, imposto 1, imposto 2, unidade, grupo; campos personalizados autorizados seguem a ordem da view. |
| Ordenação | Coluna e sentido solicitados; valores monetários e campos numéricos ordenados numericamente; ID como desempate. |
| Busca | Colunas exibidas e campos personalizados autorizados; `%` e `_` no texto são literais, sem ampliar a consulta por wildcard. |
| Todos/exportação | Leitura em blocos de até 500 até completar a contagem, sem truncamento silencioso em 100 ou 500; páginas inconsistentes são recusadas. |
| Principal | Catálogo histórico, seus IDs, preços por moeda e referências permanecem no leitor legado autorizado. Não há adoção em tabelas privadas. |
| Empresas nomeadas | Itens, impostos, grupos e valores personalizados são ligados à mesma empresa e à origem correta. |
| Preferência | Identificador original `invoice-items` reconhecido por `NativeCompanyTableOrder`, respeitando `items/view`, funcionário, empresa e a opção existente de salvar ordem. |
| Integridade | Consulta sem escrita em itens/impostos/grupos; ACL e definição das colunas são verificadas novamente antes da resposta; JSON com `Cache-Control: no-store`. |

O formulário/modal existente, grupos, duas taxas, preços por moeda, cópia,
importação e identificação canônica foram preservados. A seleção de itens no
editor de Propostas/Orçamentos possui autorização própria, descrita em
[sales.md](sales.md); poder editar um documento não concede CRUD do catálogo.

### Evidência

`php tests/native_company/catalog_table.php` executa o controller original,
os leitores de catálogo e SQL real com requisição/resposta sintéticas. Foram
aprovadas **20 verificações adicionais**, incluindo:

- preços `2`, `20` e `1000.25`, com ordenação anterior à paginação;
- campos personalizados numéricos `2`, `20` e `100`;
- **505 itens** retornados por Todos, ultrapassando uma página de 500;
- descrição longa e busca literal por `%_`;
- empresa B sem os resultados privados de A;
- IDs históricos da principal preservados;
- negação de ator, CSRF, regex e expressão de ordenação inválidos;
- snapshot do catálogo idêntico antes/depois das leituras e das negações.

Também passaram os oito scripts de regressão `primary_catalog.php`,
`catalog_principal_native.php`, `catalog_invoice_editor.php`,
`invoice_catalog_lookup.php`, `catalog_client_currencies.php`,
`catalog_custom_creation.php`, `catalog_import_reference.php` e
`catalog_currency_identity.php`, todos em `tests/native_company/`.

A suíte nova está no manifesto SQLite e MySQL do CI para verificar a consulta
também no dialeto de produção. A inclusão no manifesto não equivale à execução:
o resultado remoto deve ser confirmado para o SHA final da PR.

## Aceite pendente

AC14–AC19 e AC39–AC44 exigem também a comparação lado a lado da 4.0.2 com o
destino, nas mesmas condições de dados/tela, além dos fluxos completos com
perfis reais e troca de empresa. Os testes acima comprovam as fronteiras e
cenários que executaram, sem declarar equivalência integral de todas as ações.
Marca/PWA são verificados separadamente em
[payments-and-company-context.md](payments-and-company-context.md) e
[baselines.json](baselines.json).
