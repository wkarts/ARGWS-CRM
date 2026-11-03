# Precisão tributária nativa preservada

Correção factual: `install/database.sql` define `taxes.taxrate` e
`item_tax.taxrate` como `DECIMAL(15,2)`. A coluna de quatro casas do catálogo
scoped novo não autoriza ampliar esse contrato fiscal.

`NativeCompanyTaxRate::exact` aceita uma representação com até quatro casas
somente quando é exatamente representável com duas: `5.0000` vira `5.00`;
`1.2345` é rejeitado, nunca truncado nem arredondado. O POST original de fatura
normaliza o wire `nome|taxa` antes do cálculo canônico. O normalizador de escrita
do catálogo aplica a mesma regra; leitores de taxas e itens recusam valores
incompatíveis existentes com `native_tax_precision_review_required`, sem DML
corretivo. IDs, autoridade, hashes de documentos históricos e algoritmo sem
marcador permanecem preservados. A serialização do DTO de catálogo continua
com quatro casas, mas só representa taxas nativas exatas de duas casas.

Executado: PHP 8.4/SQLite, `tests/companies/native_invoice_tax_precision.php`
(POST original + writer real, total, rejeição sem efeitos e leitura sem reparo),
e regressão `tests/companies/native_expense_catalog.php`. Isso não comprova
MySQL, navegador ou a conclusão do formulário completo de despesas. Duas taxas
independentes e seu snapshot continuam parte do trabalho de despesas; este
patch não introduz expansão de precisão nem altera schema histórico.
