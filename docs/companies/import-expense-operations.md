# Importação revisada e exportação privada de despesas

`admin/company_imports` é uma superfície selada própria. Não chama importadores
legados, não altera despesas existentes e não atribui à empresa ativa registros
legados sem origem. A criação usa exatamente `CompanyExpenses` dentro de uma única
transação do lote, com o mesmo normalizador, referências, numeração, recibos e
permissões do formulário individual.

## Arquivo e preview

Até 100 despesas/256 KiB em UTF-8; CSV de ponto e vírgula ou JSON. Cabeçalho CSV
exato e ordenado:

```text
category_id;client_id;project_id;currency;date;description;note;amount;tax_rate
```

JSON usa uma lista de objetos com esses campos. `category_id`, `currency`, `date`,
`description` e `amount` são obrigatórios. Cliente/projeto opcionais usam 0; nota
vazia e alíquota 0 são permitidas. IDs podem ser inteiros JSON; quantias/alíquotas
precisam ser strings decimais com ponto e até duas casas. Não converter vírgula,
notação científica, número JSON com fração ou arredondar entrada imprecisa.

CSV tem aspas duplas e escape por duplicação; aspas sem fechamento, conteúdo após
fechamento sem delimitador, cabeçalhos/colunas extras ou reordenados são recusados.
Fórmulas iniciadas por `=`, `+`, `-` ou `@`, inclusive após espaços/controles, são
recusadas. Não executar fórmulas, HTML, caminhos ou código vindo do arquivo.

Preview exige `imports/manage`, `expenses/view` e `expenses/create`. Todas as
referências são revalidadas antes de persistir o preview: categoria ativa própria,
cliente autorizado, projeto próprio ativo e pertencente ao cliente selecionado,
com os grants de cliente/projeto correspondentes. Uma linha inválida não deixa
preview parcial. O arquivo bruto não é armazenado; o lote conserva dados
normalizados, snapshots/revisões das referências, totais separados por moeda e
hash da origem. Preview é privado do mesmo ator/empresa, auditado e não cria
nenhuma despesa nem consome numeração. Expira em uma hora; até dez previews ativos
por ator/empresa podem aguardar confirmação.

## Confirmação atômica

A GUI mostra todas as linhas, categoria/cliente/projeto, base/tributo/total e
moeda antes de disponibilizar a confirmação. Cada lote tem revisão e chave de
confirmação explícitas. Mudança em categoria, identidade do cliente, projeto,
permissão ou conteúdo exige um novo preview. Moedas não são convertidas.

Sob o lock do núcleo, todas as linhas são revalidadas antes da primeira despesa.
Cada linha é emitida pelo writer já existente com chave canônica derivada do lote
e índice. Uma falha no segundo item reverte também o primeiro, sequências,
recibos e auditorias. O recibo final inclui IDs, totais e hash; o estado confirmado
só é retornado após readback. Replay com a mesma chave retorna os mesmos IDs e
confere seus recibos persistidos. Repetir com outra chave conflita; não recria o
lote. Perder a resposta não exige reenviar o arquivo: reabrir o lote mostra os IDs.

Nada nesta operação paga uma despesa ou confirma transação bancária. Despesas
vinculadas a provedores não são geradas a partir de arquivo genérico; continuam
exigindo prova/canal próprio. Não há adoção automática de registros NULL.

## Exportação

`expenses/view` autoriza listagem e CSV da própria empresa, sem exigir create.
A GUI pagina por chave até 100 registros e disponibiliza o CSV da página revisada.
A próxima página usa o cursor próprio; nenhum contador de outra origem é exibido.
Cabeçalhos distinguem moeda, base, tributo, total e estado. Canceladas permanecem
no histórico. Texto com prefixo de fórmula recebe apóstrofo; `fputcsv` protege
aspas, delimitadores e quebras de linha. São snapshots próprios, sem JOIN a
clientes/projetos de outra origem.

O CSV fica pronto em memória limitada à página e só é entregue após auditoria do
hash/escopo/cursor confirmada. Não gera URL pública ou arquivo no diretório web.

## Instalação e prova

A migration nova do núcleo deve executar `CompanyImportSchema::ensure` após os
schemas de projetos/despesas. A tabela aditiva `company_import_batches` valida
InnoDB, tipos, nullability/defaults, collations, índices integrais e FK de origem.
Preservar a migration histórica e a semântica do legado.

`tests/companies/imports_expenses.php` inclui despesas e arquivos e cobre preview,
CSV/JSON, valores/moedas, A/B/principal/NULL, IDs cruzados, revisão, expiração,
replay, fórmula, limites, auditoria e falha real por trigger no segundo item com
rollback do primeiro; exportação com neutralização de fórmula e paginação.
`tests/companies/imports_concurrency.php` observa duas confirmações reais esperando
no InnoDB, replay do mesmo lote e revogação enquanto aguardam. SQLite valida regras
com adaptador e não comprova locks/DDL. MySQL/MariaDB/HTTP/Chromium devem ser
executados na matriz do SHA publicado.

Limites: a vertical importa somente novas despesas locais. Importação de clientes,
faturas, créditos, demais documentos, XLSX e formatos arbitrários continua pendente
de código e contratos próprios. Nenhuma homologação externa é alegada.
