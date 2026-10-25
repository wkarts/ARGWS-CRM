# Tickets/add e ticket_change_data nativos — composição local

## Wiring (owner do controller principal)

No início de `Tickets::add($userid = false)`:

```php
if (CompanyRequestScope::nativeFor($this)) {
    require_once APPPATH.'services/NativeCompanyTicketPageController.php';
    (new NativeCompanyTicketPageController($this))->add($userid);
    return;
}
```

No início de `Tickets::ticket_change_data()` aplique o mesmo guard e
`(new NativeCompanyTicketPageController($this))->contactChange(); return;`.
Não muda as rotas nem o caminho legado. O owner central também deve admitir
ambos no bootstrap nativo e entregar os assets originais (incluindo tickets.js)
sem carregar app.js global. O bundle minificado é compilado centralmente.

## Contratos e dependências explícitas

- Base d47ad6af + ff9be347 (cherry-pick local b425192b) para `ticket_emails`
  real em NativeCompanyTicketContacts. Não inventa flag ou envia notificação.
- Commands/createNative persiste tags e custom_fields na mesma transação/receipt,
  mediante capability privada do writer. Campos obrigatórios/estrangeiros, falha
  SQL e alteração da origem durante a composição revertem a criação inteira.
- `_native_ticket_request` é nonce hex de 64 caracteres; POST repetido preserva
  receipt. Contexto/CSRF são verificados antes do comando; resposta usa 303.
- Upload presente é recusado **antes** de criar ticket. Não existe alegação de
  integração de anexos. O formulário informa essa limitação, IA e notificações.
- `render_custom_fields` deve usar o helper original com attestation central.
- `_l` e `html_purify` usam as mesmas funções de produção sem hooks globais no
  contexto atestado. render_input/select/textarea são os helpers originais.
  init_head/init_tail continuam responsabilidade da composição central; nenhuma
  árvore HTML ou renderer paralelo foi criado.
- Links KB usam somente artigos e grupos ativos, `staff_article=0`, autorização
  KB, URL pública oficial com issuer_id. Bootstrap público depende composição.
- Serviços inline são admin-only até o contrato central de permissão ser ampliado;
  não é alegação de paridade final com permissão inline de staff.
- Não há mudança de versão, migrations, seed, publicação, merge ou deploy aqui.

## Evidências

Executados com `/tmp/crm-php-runtime/bin/php` (PHP 8.4/PDO SQLite):

- `tests/native_company/ticket_add_page.php`: controller/projection reais, mesma
  view add e mesmo modal services, SQLite real (metadata MySQL double declarado),
  A/B, contato/flag email real, projetos autorizados, nonce/replay, 303,
  recusa upload, revogação e limite CSRF. Shell, transporte form e customfields são **boundary spies explícitos**.
  general_helper, fields_helper e template_helper são reais, com hooks/options
  globais que lançam erro no teste. Não comprova bootstrap HTTP/browser completo.
- `node --test tests/native_company/ticket_add_ui.test.cjs`: JSON objeto/string,
  descarte de resposta tardia de contato, link KB com textContent e issuer_id;
  não são testes de browser.
- PHP lint dos arquivos alterados, `node --check assets/js/tickets.js`, diff check.

Wiring add/contact no controller principal e writer de campos/tags estão
implementados e testados localmente. Pendentes: admissão central somente após
detail/lista e demais ações, minificação, anexos/notificações/IA, HTTP integrado,
browser e MySQL real. A criação não autoriza disponibilizar um redirect para uma
página de detalhe ainda incompleta.

# Pull Request

- **Branch:** feat/native-ticket-add-ui (local).
- **Título:** feat(tickets): render native company add with scoped data and contact adapter.
- **Descrição/contexto/objetivo:** preservar formulário nativo e trocar apenas
  projeções e dispatch; evitar hidratação de opções/modelos globais named.
- **Escopo:** dois adapters, projeção, mesma view/helper MIME e handlers JS;
  exclui demais handlers/controllers, migrations, release e publicação.
- **Arquitetura/APIs:** serviços nativos atestados, DTO real e receipt existente;
  rotas/contratos públicos preservados salvo recusa explícita de anexos.
- **Arquivos:** NativeCompanyTicketPageController.php,
  NativeCompanyTicketPresentation.php, tickets/add.php, tickets_helper.php,
  tickets.js, dois testes e este documento; nenhuma remoção.
- **Banco/configuração:** sem DDL/seeds/env novos; readers/writer existentes.
- **Segurança:** ACL/contact ownership, CSRF, idempotência, escape HTML, URL KB
  issuer-bound; limites de verificação acima.
- **Desempenho/observabilidade:** catálogo staff limitado pelo serviço existente,
  filtro de is_not_staff por IDs previamente autorizados; sem telemetria nova.
- **Compatibilidade/riscos:** legacy mantém caminho original; dependências acima
  impedem alegação de entrega integrada final.
- **Validação manual recomendada:** abrir add em A/B; alternar contato, nenhum
  contato, projeto, seleção KB; envio repetido e alteração de privilégio.
- **Build/release/deploy:** não realizados; composição central deve compilar
  tickets.min e verificar release antes de qualquer publicação autorizada.
- **Rollback:** reverter commit local antes da integração; sem dados migrados.
- **Checklist:** [x] escopo/isolamento [x] testes focados [ ] integração completa
  [ ] browser [ ] MySQL [ ] publicação autorizada.
- **Commit sugerido:** feat(tickets): adapt native add view and contact data.
- **Merge sugerido:** somente após gates de integração; não executado.
- **Versão sugerida:** não alterada nesta fatia; release sob owner central.
