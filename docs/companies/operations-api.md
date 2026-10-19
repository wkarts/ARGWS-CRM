# API operacional por empresa

A superfície nativa `/company_operations_api` usa somente credenciais `crmco_`
criadas na GUI de API da própria empresa. Cookies, sessão do administrador,
seletor, parâmetro `issuer_id` e tokens do módulo API legado não são autoridade.
Empresa e funcionário são resolvidos do registro persistido da credencial.

## Endpoints e escopos

| Endpoint | GET lista/detalhe | POST |
|---|---|---|
| `/company_operations_api/projects[/id]` | `projects:read` | `projects:write` |
| `/company_operations_api/tasks[/id]` | `tasks:read`; lista exige `?project_id=…` | `tasks:write` |
| `/company_operations_api/standalone_tasks[/id]` | `tasks:read` | `tasks:write` |
| `/company_operations_api/contracts[/id]` | `contracts:read` | `contracts:write` |
| `/company_operations_api/tickets[/id]` | `tickets:read` | `tickets:write` |

Cada escopo é explícito na emissão/rotação. Tokens já emitidos conservam a lista
persistida; acrescentar scopes ao catálogo não os amplia. Escopo não substitui ACL:
writers/readers continuam exigindo recurso/ação/owner, cliente e referências
próprias. Tarefas vinculadas exigem também `projects/view`; avulsas não exigem.
Os scopes `*:write` agrupam as permissões create/edit/view correspondentes, além
`customers/view` nos domínios que consultam cliente. A GUI mostra apenas scopes
compatíveis com os grants atuais. `company_api/manage` continua obrigatório.

Authorization: `Bearer crmco_<id>_<segredo>`. POST exige `Content-Type:
application/json` e `Idempotency-Key` de 64 hexadecimais. Corpo:

```json
{"action":"create","input":{"client_id":23,"responsible_id":7,"name":"Projeto","description":"Texto simples","start_date":"2026-10-19","due_date":"2026-11-19"}}
```

Atualizações usam URL com ID e `revision` inteiro retornado pelo GET. Ações:

- Projetos: `create`, `update` com o contrato do writer gráfico.
- Tarefas vinculadas/avulsas: `create`, `update`, `comment` (`input.body`), `start`,
  `stop`. Iniciar/parar não aceita conteúdo adicional no input.
- Contratos: `create`, `update`, `activate`, `cancel`, `copy`.
- Suporte: `create`, `reply` (`input.body`), `change`
  (`input.assignee_id`, `input.status`, `input.priority`).

O token de origem não é gravado no domínio nem devolvido ao cliente. O recibo é
namespaced por empresa/funcionário/rota/ação, preservando retry após rotação para
mesmo funcionário. Mesma chave com conteúdo ou revisão divergente conflita;
nenhum replay dispensa a autenticação atual. GET usa paginação por `after`;
contratos/suporte admitem `search`, sempre sob o filtro da empresa.

## Revalidação e transações

Os dois storages operacionais aceitam callback opcional exclusivamente de veto.
A ausência do callback mantém os fluxos GUI existentes; sua presença não concede
capacidade ou ownership. A API fornece guard que trava a identidade/ACL e o token,
revalida scopes/expiração/estado antes e depois do trabalho e registra uso/auditoria
na transação do próprio writer/reader. Não há transação externa que adote ou
confirme uma transação de domínio. Os stores validam também as tabelas do token
como InnoDB quando recebem guard.

Listas ficam sob a mesma fronteira de guard. Detalhes, comentários e timers são
hidratados somente após revalidação. Se token/grant for revogado ou expirar durante
espera pelo lock, a operação é recusada sem escrita parcial ou conteúdo na resposta.
Nenhum hook, e-mail, transporte externo ou chamada bancária é disparado.

A preparação da rota ocorre antes de sessão ou providers. Uma indicação antecipada do caminho suprime apenas o catálogo de módulos opcionais no pre-system; ela não concede identidade, escopo ou acesso. A verificação exata posterior do router/URI continua obrigatória. Apenas os
cinco nomes nativos e um ID canônico opcional entram na superfície. Diretórios
admin, módulos, sufixos extras, IDs ambíguos e rotas legadas não a ativam.
A configuração de CSRF exclui somente esses endpoints bearer exatos, preservando
as rotas GUI. HEAD autoriza a leitura e suprime o corpo. Erros usam 401, 403, 409, 422,
405 ou 503 sem incluir SQL, token, corpo recebido ou dados de outro proprietário.

## Evidência e limites

- `operations_api_scope.php`: preparação exata e rejeição de rotas/IDs/CLI/módulos.
- `operations_api_bootstrap.php`: autoload real sem modelos/sessão/providers, candidato malformado ou alias com 404 antes da hidratação, veto pre-system dos módulos e contratos estáticos de rotas/CSRF; não exige app-config de produção.
- `operations_api.php`: serviços/writers reais em PDO, A/B/principal, todos os
  domínios positivos, replay, pai/filho cruzado, NULL legado, scopes read-only,
  expiração, revogação e rollback da auditoria de API.
- `operations_api_controller.php`: controller real, bearer/legacy/HEAD/método/
  Content-Type, leitura positiva e IDs/parentes cruzados.
- `operations_api_concurrency.php`: MySQL/MariaDB descartável, dois consumidores
  observados no lock, mesmo recibo e revogação de token/grant/expiração durante
  espera tanto para escrita quanto leitura.

Esses endpoints não expõem credenciais, arquivos públicos, imports em lote,
notificações, conversão de tarefas entre projetos ou bypasses financeiros. A API
de faturas/relatórios existente continua em sua superfície própria. Uploads e
PDFs operacionais permanecem nas rotas gráficas autorizadas até adaptação específica.
