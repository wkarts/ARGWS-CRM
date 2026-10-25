# Mesclagem nativa de chamados — checkpoint de implementação

O endpoint original `Tickets::bulk_action` executa o modo de mesclagem com revisão de todos os chamados selecionados, contexto/CSRF e nonce. O código não admite a rota por si só. Os outros modos do lote, a composição completa da página de detalhe e a mesclagem junto ao formulário de configurações ainda estão pendentes.

## Dados e autoridade

- Reutiliza `company_tickets`, `company_ticket_replies`, `company_ticket_files`, o catálogo nativo e o recibo de comando existente.
- A mensagem inicial e as respostas são copiadas preservando autor, contato/cliente, data e formato comprovados. Contatos não viram colaboradores e uma cópia não fabrica um recibo de envio do portal.
- Os anexos são copiados e associados à resposta correspondente. Os registros/bytes originais permanecem preservados.
- O filho recebe o marcador `[MERGED]` e o estado real `closed` do catálogo. Continua legível com sua ACL, mas os writers protegidos recusam novas alterações. O principal recebe o estado escolhido.
- Todos os filhos e o principal devem pertencer à mesma empresa e ao mesmo cliente. Para chamados sem cliente, o endereço de remetente deve coincidir exatamente. Ao contrário do legado, a mesclagem não concede transferência de audiência entre clientes/caixas. Um consentimento específico e verificável exigiria contrato adicional; não é inferido da permissão geral de edição.

## Estrutura nova no passo 474 ainda não publicado

`NativeCompanyTicketMergeSchema::ensure` deve ser executado depois dos schemas originais de chamados, arquivos e replies, antes de persistir 474:

1. `company_ticket_merges`: vínculo imutável filho/principal, ator, CAS de origem, nonce e manifesto das cópias.
2. `company_ticket_merge_replies`: autoria e proveniência de cada resposta copiada.
3. `company_ticket_merge_files`: associação explícita do arquivo copiado à resposta e ao arquivo original.

O recibo antigo `company_ticket_requests` só contém ID de resultado e hash de entrada; não comporta essas relações/provas. Nenhuma migration histórica ou seed foi editada. São verificadas colunas, engines, índices e FKs; `file_key` mantém o charset/collation ASCII do armazenamento existente. A validação MySQL/MariaDB real continua necessária.

## Testes e limites

Executados em PHP 8.4/SQLite: cópia de corpo/resposta/anexo, autoria de contato real, retenção dos originais, CAS/replay, rollback por trigger de auditoria, audiência diferente negada, filho mesclado não reaberto e cópia adulterada recusada no replay. O controller original foi invocado contra serviços e banco reais com spies apenas nos limites de contexto/HTTP. Os testes de metadados usam doubles explícitos, não substituem MySQL.

Não executados: MySQL/MariaDB, navegador ou SMTP. Não houve ativação de portal/publicação automática das cópias, deploy ou alteração de produção. GUI integral e testes de todos os consumidores ainda são critérios de conclusão, não uma promessa de paridade já alcançada.

## Atomic settings composition

`settingsAndMerge` reuses the actual settings/Tags/custom-field writers and merge copying inside one issuer transaction and one durable command. The original `update_single_ticket_settings` endpoint accepts the existing comma-separated `merge_ticket_ids` only together with an exact `merge_revisions` map; it never silently obtains a fresh child revision on behalf of an old form. The original form's merge control stays disabled until its read-only preflight and full detail view are wired. A stale child or failed audit rolls back settings, tags, custom fields, copies, source closure and receipts together. The same-client / exact email-only audience restriction remains enforced after applying settings.

Executed locally: `ticket_settings_merge.php` and `ticket_settings_merge_endpoint.php` with PHP 8.4 / real SQLite. MySQL/MariaDB, HTTP bootstrap and browser remain pending.

## Original merge-control preflight

The original settings field is now connected to a read-only preview on the same ticket detail route. It returns actual child IDs, subjects and CAS proofs only after the same ownership, department, audience and mutable-source checks. The existing Settings JavaScript shows a plain-text human confirmation and only then posts those exact proofs with the original form. Cancellation or a mismatched proof sends no mutation. This is tested with the original controller + SQLite and the original JavaScript function in Node; full detail-page bootstrap and real browser validation are still pending and route admission remains the integrator's gate.
