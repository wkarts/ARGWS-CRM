# API de compras 455 — endpoint, verbo, scope e empresa

A base de todos os caminhos da tabela é `company_purchases_api/index/`. As rotas são finitas; a existência de um caminho no catálogo não permite qualquer combinação de verbo, recurso e ação. `CompanyPurchasesApi` valida a combinação e o serviço reconfirma a capacidade específica.

A coluna empresa descreve o contrato de autorização implementado. Não é uma contagem de casos HTTP executados. As evidências SQL, de bootstrap e de navegador são registradas separadamente em `purchase-455-results.json` e nos gates integrados da PR.

| Caminho relativo | Verbo | `action` | Scope exigido | Empresa, origem e revisão |
|---|---|---|---|---|
| `suppliers` | GET / HEAD | — | `purchases:read` | Lista da empresa do token; cursor opcional `after`. |
| `suppliers/{id}` | GET / HEAD | — | `purchases:read` | Fornecedor da empresa do token; sem query adicional. |
| `requests` | GET / HEAD | — | `purchases:read` | Lista da empresa do token; cursor opcional `after`. |
| `requests/{id}` | GET / HEAD | — | `purchases:read` | Requisição da empresa do token; sem query adicional. |
| `quotes` | GET / HEAD | — | `purchases:read` | Lista da empresa do token; cursor opcional `after`. |
| `quotes/{id}` | GET / HEAD | — | `purchases:read` | Cotação da empresa do token; sem query adicional. |
| `orders` | GET / HEAD | — | `purchases:read` | Lista da empresa do token; cursor opcional `after`. |
| `orders/{id}` | GET / HEAD | — | `purchases:read` | Pedido da empresa do token; sem query adicional. |
| `receipts` | GET / HEAD | — | `purchases:read` | Lista da empresa do token; cursor opcional `after`. |
| `receipts/{id}` | GET / HEAD | — | `purchases:read` | Recebimento da empresa do token; sem query adicional. |
| `documents` | GET / HEAD | — | `purchases:read` | Lista da empresa do token; cursor opcional `after`. |
| `documents/{id}` | GET / HEAD | — | `purchases:read` | Documento da empresa do token; sem query adicional. |
| `payables` | GET / HEAD | — | `purchases:read` | Lista da empresa do token; cursor opcional `after`. |
| `payables/{id}` | GET / HEAD | — | `purchases:read` | Título AP da empresa do token; sem query adicional. |
| `events` | GET / HEAD | — | `purchases:read` | Lista da empresa do token; cursor opcional `after`. |
| `events/{id}` | GET / HEAD | — | `purchases:read` | Evento de quitação/reversão da empresa do token; sem query adicional. |
| `files/{request_id}` | GET / HEAD | — | `purchases:read` | Arquivos da requisição própria. |
| `file_download/{file_id}` | GET / HEAD | — | `purchases:read` | Bytes privados, hash e cadeia do arquivo próprio. |
| `invites/{request_id}` | GET / HEAD | — | `purchases:portal` | Convites da requisição própria, sem recuperar segredo. |
| `order_pdf/{order_id}` | GET / HEAD | — | `purchases:read` | PDF do pedido próprio e seus snapshots. |
| `settings` | GET / HEAD | — | `purchases:read` | Configuração da empresa do token, sem ID nem query. |
| `reports` | GET / HEAD | — | `purchases:report` | AP próprio; `from`, `as_of` e cursor opcional `after`. |
| `export` | GET / HEAD | — | `purchases:report` | CSV de AP próprio; `from` e `as_of`, sem cursor; até 5.000 títulos. |
| `suppliers` | POST | `create` | `purchases:write` | Novo fornecedor; sem ID ou `revision`. |
| `suppliers/{supplier_id}` | POST | `update` | `purchases:write` | Fornecedor próprio e revisão inteira atual. |
| `requests` | POST | `create` | `purchases:write` | Nova requisição; sem ID ou `revision`. |
| `requests/{request_id}` | POST | `approve` | `purchases:approve` | Requisição própria; revisão inteira e `input: {}`. |
| `requests/{request_id}` | POST | `cancel` | `purchases:approve` | Requisição própria sem pedido; revisão inteira e `input: {}`. |
| `quotes` | POST | `create` | `purchases:write` | `request_id` e `supplier_id` da empresa do token; sem ID ou `revision` externos. |
| `orders` | POST | `create` | `purchases:approve` | Cotação própria e `request_revision` atual no input; sem ID ou `revision` externos. |
| `orders/{order_id}` | POST | `cancel` | `purchases:approve` | Pedido próprio sem recebimentos; revisão inteira e `input: {}`. |
| `receipts/{order_id}` | POST | `create` | `purchases:receive` | Pedido próprio e revisão inteira atual; só quantidade física recebida agora. |
| `documents` | POST | `post` | `purchases:post` | Pedido, recebimentos, categoria e anexo próprios; cria uma despesa e um AP. |
| `payables/{payable_id}` | POST | `settle` | `purchases:pay` | AP próprio; `revision` é o token textual da posição; declaração externa confirmada. |
| `events/{event_id}` | POST | `reverse` | `purchases:pay` | Evento de declaração próprio; token atual da posição do AP, motivo e confirmação. |
| `files/{request_id}` | POST | `upload` | `purchases:write` | Requisição própria; base64 canônico, fornecedor opcional próprio; sem `revision`. |
| `files/{file_id}` | POST | `hide` | `purchases:write` | Arquivo próprio sem vínculo de evidência preservada; revisão inteira e `input: {}`. |
| `invites/{request_id}` | POST | `issue` | `purchases:portal` | Requisição e fornecedor próprios; validade de 1–30 dias; sem `revision`. |
| `invites/{invite_id}` | POST | `revoke` | `purchases:portal` | Convite próprio; revisão inteira e `input: {}`. |
| `settings` | POST | `save` | `purchases:settings` | Configuração própria; sem ID; revisão nula na criação e inteira na atualização. |

## Identidade e capacidades atuais

Empresa e ator vêm exclusivamente da credencial persistida. Não há query/body para mudar `issuer_id` ou `actor_id`. Uma referência válida da empresa B não é adotada por um token da empresa A. A principal segue as mesmas relações de origem; a condição de administrador legado não cria vínculo numa secundária.

| Scope | Capacidades atuais também exigidas |
|---|---|
| `purchases:read` | `purchases:view` |
| `purchases:write` | `purchases:view`, `purchases:create`, `purchases:edit` |
| `purchases:approve` | `purchases:view`, `purchases:approve` |
| `purchases:receive` | `purchases:view`, `purchases:receive` |
| `purchases:post` | `purchases:view`, `purchases:approve`, `expenses:view`, `expenses:create` |
| `purchases:pay` | `purchases:view`, `purchases:pay` |
| `purchases:portal` | `purchases:view`, `purchases:portal` |
| `purchases:report` | `purchases:view`, `purchases:report` |
| `purchases:settings` | `purchases:view`, `purchases:settings` |

Escopos não se incluem implicitamente: `write` não concede `approve`, `receive`, `post`, `pay`, `portal`, `report` ou `settings`. A credencial, o vínculo e as capacidades são revalidados dentro da transação e depois da auditoria de uso. Revogação ou expiração não depende de encerrar uma sessão do navegador.

## Transporte e respostas

Use `Authorization: Bearer crmco_...` no cabeçalho. POST exige JSON com somente `action`, `input` e, quando aplicável, `revision`; `Idempotency-Key` aceita de 8 a 128 caracteres em `[A-Za-z0-9_.:-]`. O token e a chave do comando vinculam o retry ao mesmo ator, empresa, recurso, ação e payload. Um replay com o mesmo input devolve o resultado confirmado; alteração de input não é uma nova operação sob a chave antiga.

GET e HEAD passam pela mesma autorização e conferência de origem. HEAD não devolve corpo. PDF, CSV e arquivo usam seus próprios tipos de resposta; os demais retornam o envelope JSON do controller. Downloads só aceitam o ID canônico, nunca caminho de arquivo enviado pelo cliente.

O caminho original deve coincidir com o controller/recurso reconhecidos. O subdiretório real e `index.php` são suportados; aliases, caixa alternativa, dupla barra, encoding alternativo e sufixos extras são recusados antes da hidratação legada. A exceção nativa de CSRF aplica-se somente aos caminhos bearer finitos; não inclui o portal nem os formulários administrativos.

A API pode receber o cookie nativo de CSRF emitido pela aplicação, mas ele não autentica nem substitui o bearer. A prova de transporte abre um contexto HTTP vazio para cada requisição autorizada e confirma negativa quando apenas esse cookie está presente. A tabela inteira de sessões nativas permanece idêntica durante essa prova.

## Semântica financeira e cobertura

`documents:post` é o reconhecimento do documento e de sua despesa/AP. `payables:settle` registra uma quitação já realizada externamente e declarada pela equipe; `events:reverse` corrige essa declaração. Nenhuma dessas ações envia ordem bancária, confirma um pagamento junto a provedor, devolve dinheiro ou baixa contas a receber.

`purchases_api.php` executa a cadeia positiva em A, B e principal, os nove scopes, autorização no serviço, arquivos/PDF/relatório, input de origem forjado, revogação/expiração e duas adulterações SQL tardias. `purchases_api_matrix.php` acrescenta 61 cenários por recurso/ação, com contraparte positiva e veto cruzado incluindo pais mistos e snapshot exato por negativa. `purchases_api_phantoms.php` prova que o conjunto de eventos/IDs também é reconferido após a última auditoria, inclusive detalhe, lista e página vazia de eventos. `purchases_expense_fence.php` prova o veto da despesa canônica inclusive quando o vínculo AP é inserido ou reassociado no recibo final da API de despesas. O navegador executa a cadeia HTTP real, restrições entre empresas, HEAD, documentos e bearer sem sessão; o teste de transporte isola cada jar; `purchases_events_http.cjs` confirma a disponibilidade real da lista/detalhe/HEAD de eventos e a negativa cruzada no front controller nativo. A matriz acima não afirma execução de todas as permutações possíveis de endpoint, verbo e identidade no navegador.
