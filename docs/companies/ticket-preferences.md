# Ordem das respostas de suporte por empresa

Componente reconstruído a partir da composição442 e integrado no núcleo4.4.4/444 por NOOP nativa, sem alteração de BB122/Asaas153. Matrizes locais e executor nativo passaram; HTTP, SHA publicado eCI são registrados separadamente em `pr45-recovery-444.md`, `pr45-validation-444.md` e naPR. Sem merge, deploy ou chamadas externas.

O grupo `admin/settings?group=ticket_preferences` oferece somente `ticket_replies_order`: `asc` (mais antigas primeiro) ou `desc` (mais recentes primeiro). A configuração muda a apresentação no suporte administrativo próprio e no detalhe do chamado no portal. Não altera mensagens, autoria, estado, atribuição ou permissões. As mensagens internas continuam ausentes do portal até publicação individual explícita.

Ausência de configuração própria mantém `asc`, inclusive na empresa principal, sem ler/copiar `options.ticket_replies_order` ou `company_options.ticket_replies_order`. O legado conserva sua opção e seus consumidores. Os demais campos de tickets, departamentos, importação por e-mail, notificações, anexos, tasks, calendário, Google, SMS, leads, subscriptions e gateways não são liberados por esta vertical.

## Contrato de persistência e autoridade

`CompanyTicketPreferences` mantém o nó próprio `issuers_registry.settings_json.ticket_preferences`:

```json
{"format":1,"revision":1,"ticket_replies_order":"desc"}
```

O nó exige shape exato, formato1, revisão inteira positiva até2147483647 e enum de strings minúsculas. JSON/nó inválido falha fechado. O formulário usa revisão0 quando ausente; revisão antiga gera409. Mesmo valor com revisão atual não escreve nem duplica auditoria. Replay de revisão antiga continua409, sem prometer recibo durável de formulário.

`page` exige `settings.view`; `save` exige `settings.view` e `settings.edit`, ator e empresa ativos e instalação com autoridade resolvida. A transação canônica trava identidade/ACL, confere revisão, grava somente o nó próprio preservando os demais dados, confirma JSON completo, grava e confirma auditoria e repete confirmação de configuração e ACL após a última escrita. Não registra valores, nomes de opções ou segredos no audit. Gatilho tardio que altera configuração, empresa, ator ou grants impede sucesso e desfaz o conjunto.

Leitores de tickets usam origem explícita e transação já pertencente ao domínio. O helper não consulta sessão ou empresa padrão e não concede acesso ao chamado/contato. O leitor administrativo mantém sua ACL. O portal mantém contato, permissão nativa5, ownership e publicação de pai/filho; depois da última auditoria reconfirma a configuração e o conjunto/bytes das respostas publicadas antes de retornar. Campo `ORDER BY` deriva exclusivamente de duas constantes internas ASC/DESC.

Não há nova tabela, DDL, backfill, versão, migration ou workflow neste pacote. O responsável da composição adiciona o passo nativo próprio, inclusive NOOP quando aplicável. Outros serviços e escritores financeiros permanecem preservados.

## Validação e limites

- `tests/settings/ticket_preferences.php`: serviços e controller/view reais com I/O de framework adaptado, PDO SQLite e MariaDB. A/B/principal, default sem herança, ator restrito, enum/overposting, revisão409, SQL/auditoria503, retry, dados corrompidos e cinco variantes AFTER-audit, numeric strings do mysqli e opções globais intactas.
- `tests/settings/ticket_preferences_readers.php`: leitores administrativos, sessões privadas de contato e escritor real de publicação. Ordem asc/desc em A/B/principal, ausência de mensagens privadas, IDs/sessões cruzados, último audit alterando preferência, publicação ou body e rollback integral.
- `tests/settings/ticket_preferences_concurrency.php`: apenas MariaDB/InnoDB; três cenários com dois processos efetivamente observados esperando o lock: mesma revisão, ator revogado e grant revogado. Uma revisão/auditoria no vencedor; nenhum efeito dos editores revogados.
- `tests/settings/ticket_preferences_browser.cjs` + fixture de inspeção somente leitura: preparados para futuro HTTP integrado. Criam chamados/respostas e administram grants/publicações/configuração por GUI, verificam ordem do portal, contexto/revisão obsoletos409 e invariância financeira. Devem rodar depois do onboarding/portal nativo de teste, com A/B e contatos sintéticos. No overlay original não haviam sido executados comoHTTP. Na composição444, as duas suítes completas passaram emfresh eupgrade443→444, incluindo funcionárioB nativo e revogação atual; ver o relatórioHTTP444. Lint sozinho não é prova de navegador.

Fixtures não são suites independentes. Contagens cumulativas incluem regressões importadas e não devem ser somadas como casos novos. SQLite não demonstra locks InnoDB. Os testes locais não comprovam o SHA futuro publicado nem transporte externo.
