# Matriz finita — auxiliares nativos

Estado local de engenharia, não recibo de admissão de rota ou entrega integral. PHP executado localmente: 8.4 com PDO/SQLite; MySQL, matriz CI e navegador integrado ainda são necessários. A lista distingue código adaptado de paridade completa.

## Adaptadores existentes

| Controlador | Métodos públicos | Evidência / limite |
|---|---|---|
| Todo | index, todo, get_by_id, change_todo_status, update_todo_items_order, delete_todo_item | Serviço/controle, histórico + owned, revisão, ator, ordenação atômica, POST/CSRF. PHP real e handlers Node; navegador pendente. |
| Announcements | index, announcement, view, delete | Mesmo formulário/view e modelo histórico real; owned, revisão, editor sanitizado. PHP real; navegador pendente. Portal tem reader/dismissal por binding autenticado, integração do bootstrap pertence ao responsável pelo portal. |
| Currencies | index, manage, make_base_currency, delete, get_currency_symbol | Catálogo público, somente administrador da instalação comprovado, mesma view/modelo real, CRUD/base com transação/revisão de autoridade, preservação de preço histórico. PHP real positivo/negativo; DDL MySQL pendente. |
| Taxes | index, manage, delete, tax_name_exists | Principal pelo modelo histórico real; secundárias owned. Revisão, referência/default_tax e formulário nativo; sem referências cruzadas de catálogos. |
| Invoice_items | index, table, manage, import, add_group, update_group, delete_group, delete, bulk_action, search, get_item_by_id, copy | Rotas/views existentes: principal usa tabelas/modelos históricos, secundárias usam owned. Revisão/custom fields tipados, lote atômico, importação rejeita nomes ambíguos. Nenhum catálogo shadow na principal; importação/browser completo pendente. |
| Misc | get_currency, get_taxes_dropdown_template, format_date | Catálogos/formatos explícitos por empresa; não consulta opções globais no ramo nativo. |
| Misc | add_reminder, get_reminders, my_reminders, reminders, reminders_table, delete_reminder, get_reminder, edit_reminder | Recurso e destinatário revalidados, distinção all/mine nativa, histórico + owned, cron explícito e email com fence. PHP real; SMTP final capturado em testes de contato/reminder, sem rede. **SMS scoped ainda não implementado.** |
| Misc | add_note, edit_note, delete_note | Recurso + autor/administrador, revisão, histórico + owned, notas preservadas se autor removido. PHP real; mesmo partial de notas. |
| Misc | clear_system_popup, set_setup_menu_closed, set_setup_menu_open, dismiss_announcement | Estado de sessão/ator ou fonte própria, POST+CSRF no ramo nativo; JS compartilhado, sem GET mutador named. |
| Misc | search, remove_recent_search, set_notifications_read, set_notification_read_inline, set_desktop_notification_read, mark_all_notifications_as_read_inline, notifications_check | Wiring da fachada de header do responsável pela apresentação; storage/authority testados na composição central. Não é nova implementação de busca nesta frente. |

## Lacunas restantes por método

| Métodos | Dependência concreta / responsável a coordenar |
|---|---|
| Misc::get_relation_data | Router scoped implementado para customer/contact/staff (diretório real A/B, principal histórico, assignment, compartilhamento sem acesso a contato), invoice (reader canônico) e project (busca e seleção exata). Diretório real: 20 checks novos; endpoint: 7 checks com boundary spy. Composição pelos owners: Invoice 187 checks cumulativos e Project 56 cumulativos, com serviços/resolver/PDO reais e boundary doubles; não são prova HTTP/browser. tickets_contacts delega ao reader próprio Tickets (view/create + flag scoped + assignment), sem confundir permissão portal5. Contract/expense/lead agora usam readers reais existentes, com linhagem principal explícita (contract +7 e expense +7 checks SQLite; lead exige MySQL ainda não executado). Credit_note/estimate/proposal usam snapshots/numeração nativos e união histórica comprovada: 12 checks novos por tipo, PDO SQLite real e boundary de transporte double. Ticket delega ao reader Tickets com autoridade de departamento. Task é seleção exata pelo reader Tasks, não busca fabricada; composição real 24 checks cumulativos, incluindo 5 do endpoint e negação antes hidratação. Não herda loader global nem autoriza admissão integral. |
| Misc::upload_sales_file, add_sales_external_attachment | Faturas: adapter de files.id real, blob privado/externo, principal histórico e projeção474 implementados/testados em SQLite. Outros tipos de documento ainda precisam dispatch de seu owner. Admissão depende preview/JS/CI. |
| Misc::toggle_file_visibility | Faturas e clientes: POST/contexto/CSRF + revisão, adapter de origem explícito; função JS original compartilhada e sem GET fallback. Dependência do DTO/registro frontend integrado. |
| Misc::send_file | Faturas/clientes: fila FileMessage de origem explícita, destinatário digitado validado, anexos em memória/hash, fence de uso único. Hooks reais CI/PHPMailer testados com SMTP capturado; composição depende pacote FileMessage do owner clientes. |
| Misc::delete_sale_activity | Atividade financeira imutável não equivale a sales_activity nativo apagável; ainda falta classificação/prova de atividade administrativa removível e ACL nativa. Não apagar journal nem fabricar sucesso. |
| Misc::update_ei_items_order | Adapter native conecta writer CompanyInvoices::reorderItems com conjunto exato, CAS e audit; prova composta real pelo owner: 83 checks cumulativos. Handler save_ei_items_order é fonte única e recebe recibo revisionado. Principal histórico tem writer restrito a um parent comprovado, ACL persistida, todos os itens, preservação monetária e rollback (12 checks novos SQLite). Ainda depende dos atributos reais do preview e registro JS, não afirmar admissão por teste de serviço. |
| Misc::contact_email_exists | Rota ORIGINAL preservada, API CompanyNativeContacts::emailAvailable e client_id explícito; POST/contexto/CSRF e JSON no-store. Controller real + serviço/PDO: 5 checks; service revalida ACL e vínculo após consulta (6 checks adicionais pelo owner). Clientes JS voltou à rota Misc. |
| Misc::staff_email_exists | Unicidade global de identidade e visibilidade de colaborador. Ainda coordenar responsável Staff; não expor existência global sem autoridade explícita. |
| Misc::fetch_address_info_gmaps | Chave e autorização de transporte Google por configuração, sem herança global. |
| Misc::tinymce_file_browser | Ownership de mídia/arquivos e URLs de editor; não listar diretório global. |
| Misc::dismiss_cron_setup_message, dismiss_cloudflare_notice, dismiss_php_version_notice, run_cron_manually, change_maximum_number_of_digits_to_decimal_fields, change_decimal_places, convert_tables_to_innodb_engine, upgrade_232_database | Ações de instalação, não CRUD de empresa. Exigem owner comprovado, POST/CSRF e contrato operacional/DDL. Coordenação central Utilities/Settings. |
| Misc::access_denied, not_found | Resposta de erro nativa; verificar apresentação comum e não redirecionar para área paralela. Coordenação central. |

## Decisão de armazenamento do catálogo

O main `abbf6c7` não contém `company_items`, `company_taxes` nem `company_item_groups`. São tabelas novas desta 474 ainda não publicada. Criar uma segunda linhagem de catálogo na principal era uma complexidade artificial desta implementação, não um requisito nem dados históricos do usuário.

A principal continua criando/editando/copiando/excluindo nas tabelas e modelos nativos históricos; empresas secundárias usam armazenamento owned. Não há renumeração, cópia, adoção ou remoção de dados. Writers owned recusam a principal. Se a composição encontrar dados owned inesperados na principal, recusa explicitamente com necessidade de migração e preserva os registros.

A proposta de sidecar não foi ativada: classes e testes experimentais foram retirados antes da publicação; nenhuma tabela/linha existente foi apagada. As reservas purchase153/service_management101/accounting137 para essa proposta ficam liberadas, sem bump fictício.

`native-catalog-reference-consumers.md` continua útil para os adapters de módulos **named**, que não são habilitados por essa simplificação. Seus atributos de produto e writers financeiros ainda precisam de autoridade explícita; não herdam tabelas ou opções globais.
