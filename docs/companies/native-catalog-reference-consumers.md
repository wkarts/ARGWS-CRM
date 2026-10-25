# Consumidores de referências do catálogo nativo

Inventário de código para a composição 4.7.4. Não é prova de execução nem autorização para ignorar isolamento.

## Estado e decisão de segurança

**Revisão:** a mistura de catálogo na principal foi descartada antes da publicação. A principal usa somente tabelas nativas históricas; secundárias usam owned. Nenhuma dessas tabelas owned existia no main abbf6c7. O inventário abaixo permanece para adapters named, não como justificativa para criar sidecar.

- Criação/edição/cópia/exclusão de itens históricos usa o modelo nativo real com principal comprovada, ACL, transação e revisão.
- Os leitores mantêm identificadores históricos na principal e namespaces owned nas secundárias, sem catálogo shadow da principal.
- Referências cruzadas de catálogo não são criadas; nenhum sidecar foi publicado nem há conversão silenciosa para imposto zero.
- Um sidecar autoritativo só pode ser introduzido com resolução de todos os consumidores abaixo, incluindo processos sem sessão e módulos que fazem SQL direto.
- API/cron devem fornecer identidade explícita; não podem herdar contexto da última sessão de interface.

## Inventário literal de consumidores

| Arquivo | Funções e linhas |
|---|---|
| `application/services/NativeCompanyCatalogLegacy.php` | `saveItem:114`, `saveItem:115`, `deleteItemInOwned:131` |
| `application/models/Recurring_rules_model.php` | `create_from_catalogue:308` |
| `application/models/Invoice_items_model.php` | `get:92`, `get:93`, `get:94`, `get_grouped:119` |
| `application/views/admin/tables/invoice_items.php` | `file:25`, `file:26`, `file:27` |
| `application/controllers/admin/Proposals.php` | `proposal:187`, `proposal:192`, `get_invoice_convert_data:417`, `get_invoice_convert_data:422`, `get_estimate_convert_data:452`, `get_estimate_convert_data:457` |
| `application/controllers/admin/Projects.php` | `get_invoice_project_data:1022`, `get_invoice_project_data:1028` |
| `application/controllers/admin/Invoices.php` | `invoice:400`, `invoice:405` |
| `application/controllers/admin/Invoice_items.php` | `index:33`, `manage:83`, `manage:93`, `manage:101`, `add_group:169`, `update_group:180`, `delete_group:191`, `delete:212`, `bulk_action:240`, `search:261`, `get_item_by_id:275`, `copy:306`, `copy:308` |
| `application/controllers/admin/Estimates.php` | `estimate:154`, `estimate:159` |
| `application/controllers/admin/Credit_notes.php` | `credit_note:148`, `credit_note:154` |
| `modules/service_management/models/Service_management_model.php` | `sm_get_grouped:1255`, `get_item_v2:1290`, `get_item_v2:1291`, `get_item_v2:1292` |
| `modules/service_management/views/subscription_services/subscription_services_table.php` | `file:77`, `file:79` |
| `modules/service_management/views/products/product_table.php` | `file:74`, `file:76` |
| `modules/purchase/models/Purchase_model.php` | `pur_get_grouped:10009`, `get_item_v2:10416`, `get_item_v2:10417`, `get_item_v2:10418` |
| `modules/purchase/controllers/Vendors_portal.php` | `add_update_quotation:616` |
| `modules/purchase/controllers/Purchase.php` | `estimate:931` |
| `modules/api/models/Api_model.php` | `get_table:138` |
| `modules/accounting/models/Accounting_model.php` | `get_data_custom_summary_report_by_customer:18848`, `get_data_custom_summary_report_by_income_statement:19281`, `get_data_custom_summary_report_by_employees:19705`, `get_data_custom_summary_report_by_vendors:20085`, `get_data_custom_summary_report_by_product_service:20182`, `get_data_custom_summary_report_by_income_statement_recursive:20923`, `get_data_custom_summary_report_by_balance_sheet:21396`, `get_data_custom_summary_report_by_balance_sheet_recursive:21825` |
| `modules/accounting/controllers/Accounting.php` | `item_automatic_table:4673` |
| `modules/accounting/views/report/details/custom_summary_report_by_vendors.php` | `file:106`, `file:245` |
| `modules/accounting/views/report/details/custom_summary_report_by_income_statement.php` | `file:106`, `file:254` |
| `modules/accounting/views/report/details/custom_summary_report_by_employees.php` | `file:107`, `file:246` |
| `modules/accounting/views/report/details/custom_summary_report_by_customer.php` | `file:106`, `file:244` |
| `modules/accounting/views/report/details/custom_summary_report_by_balance_sheet_summary.php` | `file:111`, `file:253` |
| `modules/accounting/views/report/details/custom_summary_report_by_balance_sheet.php` | `file:106`, `file:253` |

## Provas exigidas para eventuais mudanças futuras de referências

1. Itens históricos/owned na principal e duas empresas secundárias com IDs locais coincidentes.
2. CRUD/importação/cópia/exclusão em lote com grupo e dois impostos de lineages distintas, revisão e rollback.
3. Editor de faturas/propostas/orçamentos/créditos e faturamento de projetos consomem o mesmo resolver.
4. Recorrência, API, contabilidade, compras e serviços: nenhum JOIN bruto interpreta referência contextual como ausência ou taxa zero.
5. Exclusão/alteração de imposto/grupo verifica tanto referências históricas quanto owned e sidecar, se criado.
6. Sem renumerar IDs, copiar/adotar histórico, consultar empresa alheia ou emitir cobrança em leitura.
7. MySQL real, matriz PHP e navegação desktop/tablet/mobile continuam obrigatórios.
