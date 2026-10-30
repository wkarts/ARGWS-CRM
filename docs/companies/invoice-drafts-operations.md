# Rascunhos privados de faturas por empresa

O rascunho possui ID próprio em `company_invoice_drafts`. `invoice_id` permanece SQL NULL até uma emissão explícita confirmada. Criar, copiar, editar e cancelar um rascunho não criam fatura, número, lançamento no diário, cobrança de provedor ou envio. Não há publicação, PDF ou arquivo de rascunho no portal.

## Operação gráfica

Abra **Rascunhos privados de faturas** no menu do CRM. Selecione um cliente autorizado e a moeda; preencha datas, itens, descrições complementares, unidades, opcionais, tributos, desconto, ajuste e observação. O editor reaproveita os campos monetários de faturas. Salvar captura a revisão e a identidade/configuração atual da empresa.

O documento emitido oferece também **Preparar cópia como rascunho privado**. A cópia exige revisão atual, novas datas e confirmação dos valores persistidos. Uma origem cancelada continua identificada como cancelada. Origem, recebimentos, provider, arquivos, publicação, recorrência e envios permanecem preservados e não se transferem ao rascunho.

No rascunho aberto, **Emitir fatura numerada revisada** exige confirmação explícita. O serviço revalida a revisão, as permissões, o cliente, a moeda e a identidade atual sob lock, depois usa exclusivamente `CompanyInvoices::create`. Alterações de identidade/configuração exigem reabrir e salvar uma revisão atual. Rascunhos cancelados ou emitidos não voltam a ser editáveis. A fatura emitida conserva seus próprios fluxos de edição/cancelamento e provedores.

A emissão reserva uma chave canônica estável por empresa/rascunho. O vínculo, o recibo, o ID exato de auditoria e a fatura confirmam na mesma transação. Falha tardia reverte numeração, documento, itens, diário e projeção do rascunho; retry da mesma chave retorna o mesmo resultado. Uma chave canônica preexistente de outra origem não é adotada. Retry posterior não ressuscita uma fatura já cancelada. Após resposta incerta, o link **Conferir o recibo desta chave** e a lista privada permitem localizar a tentativa antes de iniciar outra.

## API opt-in

Tokens existentes não recebem novos escopos. A GUI de credenciais oferece `drafts:read`, `drafts:create`, `drafts:edit`, `drafts:cancel` e `drafts:issue`, cada qual revalidando os grants nativos atuais. A credencial permanece vinculada ao ator/empresa e não usa a seleção da sessão.

| Endpoint | Contrato |
|---|---|
| `GET/HEAD /company_api/drafts` | Lista privada com cursor `after`; sem body em HEAD. |
| `GET/HEAD /company_api/drafts/{id}` | Documento e ID de fatura nullable real, conforme estado. |
| `POST /company_api/drafts` | Payload canônico de fatura para criar rascunho privado. |
| `POST /company_api/draft_change/{id}` | `{revision,payload}`; revisão obrigatória. |
| `POST /company_api/draft_cancel/{id}` | `{revision}`; preserva histórico. |
| `POST /company_api/draft_issue/{id}` | `{revision}`; solicitação explícita de emissão. |

POST exige JSON objeto de até256KiB, `Authorization: Bearer` e `Idempotency-Key` de64hex. Apenas os caminhos de bearer enumerados são isentos do CSRF de formulário; GUI conserva CSRF nativo, token de contexto e CSRF dedicado. Valores monetários usam a validação canônica existente. A API retorna IDs próprios; não envia mensagens ou opera provedores.

## Persistência, upgrade e testes

Duas novas tabelas aditivas: `company_invoice_drafts` e `company_invoice_draft_events`. Eventos são imutáveis e ligados ao ID exato de auditoria. Header e evento conferem hashes, bytes e projeção; `origin_key` e `invoice_id` possuem unicidade, `invoice_id` é nullable. DDL usa InnoDB, PKs integrais, FKs e collations explícitas. O helper invalida o cache de tabelas depois da DDL e valida o schema em replay. Não há exclusão de dados ou downgrade destrutivo.

A integração deve acrescentar `CompanyInvoiceDraftsSchema::ensure` à nova migration440, depois dos passos publicados e antes dos schemas dos modos de recorrência que referenciam o ID do rascunho. O gerador oficial e o teste do executor nativo pertencem à composição final da raiz; a preparação deste componente não altera439 ou migrations publicadas.

Testes implementados: `invoice_drafts.php`, `invoice_drafts_controller.php`, `invoice_drafts_api.php`, `invoice_drafts_api_controller.php`, `invoice_drafts_atomic.php`, `invoice_drafts_native.php` e `invoice_drafts_concurrency.php`. Exercitam A/B/principal, colaborador restrito e revogação, origem NULL preservada, campos completos, numeração ausente antes da emissão, idempotência, vínculos/auditoria, fontes preexistentes recusadas, falhas tardias e19cenários com dois waiters SQL observados. SQLite é contrato; MariaDB real comprova InnoDB/FK/locks e metadata. Os contadores são cumulativos e incluem fixtures anteriores; não somar as saídas como cenários novos.

`invoice_drafts_browser.cjs` e `invoice_drafts_http_fixture.php` implementam GUI A/B/principal/restrito, CSRF nativo/contexto/dedicado, cópia/cancelamento, retry, stale, rollback, PDF real após emissão e credencial API configurada pela GUI. O fixture exige tracking nativo440 real e não avança o marcador nem cria os schemas novos. A execução HTTP/Chromium da composição final está pendente na entrega inicial deste componente; lint ou suíte PHP não são apresentados como esse resultado. Nenhuma homologação bancária, entrega SMTP ou transação externa integra esta prova.
