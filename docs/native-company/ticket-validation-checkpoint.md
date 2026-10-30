# Tickets nativos: recibo local e composição pendente

## Evidência executada

Em 25/10/2026, PHP 8.4.26, PDO SQLite real. As doubles de metadata
information_schema/SHOW são explícitas; não provam MySQL ou MariaDB.

17 scripts PHP passaram:

```
tests/native_company/ticket_add_page.php
tests/native_company/ticket_assets.php
tests/native_company/ticket_bootstrap.php
tests/native_company/ticket_catalog.php
tests/native_company/ticket_catalog_controller.php
tests/native_company/ticket_catalog_delete.php
tests/native_company/ticket_catalog_schema.php
tests/native_company/ticket_catalog_status.php
tests/native_company/ticket_contacts.php
tests/native_company/ticket_create.php
tests/native_company/ticket_creation_fields.php
tests/native_company/ticket_customer_schema.php
tests/native_company/ticket_ids.php
tests/native_company/ticket_preferences.php
tests/native_company/ticket_read.php
tests/native_company/ticket_relation_paging.php
tests/native_company/native_translation.php
```

3 testes Node passaram com `node --test` nos arquivos
`ticket_add_ui.test.cjs` e `ticket_catalog_ui.test.cjs` do mesmo diretório.
São execução de handlers em VM, não navegador.

Os endpoints Misc têm provas compostas separadas:
`ticket_contact_endpoint.php` e `ticket_relation_endpoint.php`; requerem
NativeCompanyRelationEndpoints e NativeCompanyRelationDirectory da composição.
Não foram incluídos artificialmente na contagem de 17 scripts acima.

O teste `tests/companies/native_ticket_customer_mysql.php` foi implementado e
teve lint, mas **não foi executado contra MySQL**. Usa banco sintético dedicado,
tabelas próprias aleatórias, ownership de cleanup, evolução de nulabilidade,
fingerprint de todas as linhas, replay e FK real.

## O que está conectado

- Mesmas rotas e views de status/prioridade/serviços/respostas predefinidas.
- Mesmas rotas `tickets/add` e `tickets/ticket_change_data`, sem models globais.
- Mesmo formulário e helpers de input/select/textarea, tradução e purificação.
- Sender registrado por contato autorizado ou email explícito sem cliente falso.
- Catálogos referenciados com linhagem explícita e namespaces; não mapeia
  silenciosamente `Answered` para `resolved` ou `Medium` para `normal`.
- Tags/campos adicionais na transação original, prova privada de criação,
  idempotência, revalidação de ACL e rollback de efeitos parciais.
- Lookup de relações com SQL keyset e subject somente após autoridade.

## Limitações que impedem admissão/entrega completa

- Index/DataTable, single/detail, reply/status/settings/delete, lease de resposta,
  anexos, merge/bulk e bloqueio de remetente ainda precisam composição nativa.
- GET não deve gravar leitura/estado silenciosamente; futuros handlers de mutação
  precisam POST, contexto, CSRF e revisão do recurso.
- Anexos enviados são recusados **antes** do INSERT, não descartados com sucesso.
- Não há alegação de envio de email/notificação ou provedor IA scoped operacional.
- O lookup removeu o teto artificial de 1000, mas a varredura de muitos candidatos
  inacessíveis ainda tem custo linear de autoridade. Em fixture local, dois
  resultados caíram de 852 para 234 consultas; scan de 1104 recusados usou 28181.
  Isso não é benchmark de produção nem prova de performance aceitável final.
- Bootstrap HTTP, bundles finais, telas em navegador, MySQL/MariaDB e PHP 8.1/8.5
  continuam gates independentes. Nenhum teste deve ser retirado para contorná-los.
- Não publicar/admitir a criação isoladamente enquanto seu destino de detalhe e
  os contratos operacionais necessários não estiverem completos.

## Migration e compatibilidade

Core 474 inédito precisa executar, após os pais existentes:
CompanyContractsSchema, NativeCompanyTicketCustomerSchema,
NativeCompanyDepartmentSchema e NativeCompanyTicketCatalogSchema. Inicialização
pública do catálogo ocorre em migration e criação de empresa, nunca em GET.
A decisão de bootstrap utiliza a versão persistida em migrations. Históricos
473 em fixtures são explícitos; ausência de tabela nunca significa legado.
Principal comprovada conserva catálogos originais; nenhuma empresa secundária
herda configurações globais. Evolução de nulabilidade preserva o FK de cliente.

## Pull Request

Composição local na branch `feat/native-company-presentation`, a integrar pelo
responsável central, não uma PR publicada. Título técnico: **feat(tickets): reuse
native ticket forms and scoped lifecycle contracts**. Sem merge, deploy,
force-push, alterações de seed ou migrations históricas nesta frente.
