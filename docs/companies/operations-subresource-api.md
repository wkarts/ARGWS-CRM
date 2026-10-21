# Subrecursos da API operacional — incremento 429

Revisão2 §5 exige consumidores operacionais utilizáveis e isolamento também nas
relações indiretas e anexos. Este incremento adapta os writers já usados pela GUI;
não acrescenta SQL paralelo nem expõe scanners, downloads ou tokens legados.
Não exige DDL: o passo nativo 429 é NOOP, sobre schemas completos até 428.

A autenticação usa `Authorization: Bearer crmco_...` e issuer/staff persistidos.
Os 16 scopes novos precisam ser escolhidos ao criar ou rotacionar uma credencial.
Credenciais antigas conservam sua lista persistida, sem receber novos grants.
Permissões de equipe também são exigidas e revalidadas após esperar pelo lock.
Cookies administrativos ou de contato não autorizam estes endpoints.

| Caminho nativo, após `/company_operations_api/` | Scopes explícitos | Ações POST |
| --- | --- | --- |
| `project_files/{project_id}` | `projects:files:read`, `projects:files:write` | `upload`, `hide` |
| `task_files/{task_id}` | `tasks:files:read`, `tasks:files:write` | `upload`, `hide` |
| `contract_files/{contract_id}` | `contracts:files:read`, `contracts:files:write` | `upload`, `hide` |
| `ticket_files/{ticket_id}` | `tickets:files:read`, `tickets:files:write` | `upload`, `hide` |
| `project_milestones/{project_id}` | `projects:milestones:read`, `projects:milestones:write` | `save` |
| `task_dependencies/{task_id}` | `tasks:dependencies:read`, `tasks:dependencies:write` | `set` |
| `project_follow/{project_id}` | `projects:follow:read`, `projects:follow:write` | `set` |
| `task_follow/{task_id}` | `tasks:follow:read`, `tasks:follow:write` | `set` |

GET e HEAD retornam metadados/revisão; HEAD nunca envia corpo, inclusive em erro.
Arquivos usam GET/HEAD no caminho exato `..._files/{parent_id}/{file_key}`. IDs são
inteiros positivos; file_key contém exatamente 64 hexadecimais minúsculos. Sufixos,
rotas parciais e formatos inválidos encerram em 404 antes de hidratação legada.
As únicas exceções CSRF acrescentadas correspondem a esses caminhos bearer exatos.

POST requer `Content-Type: application/json`, `Idempotency-Key` de 64 hexadecimais
e objeto `{ "action": "...", "revision": 1, "input": { ... } }`. Revisão desatualizada
com chave nova retorna 409. Replay do mesmo pedido devolve o recibo durável sem
recriar arquivo oculto. Uma chave usada em outro pai conflita. A identidade do
recibo inclui empresa, ator, endpoint e ação; rotação preserva replay do mesmo
ator/empresa, mas revalida a nova credencial e suas permissões.

| Ação | `input` permitido |
| --- | --- |
| `upload` | `filename`, `content_base64` canônico |
| `hide` | `file_key` |
| milestone `save` | `name`, `due_date`, `done`, `milestone_id` opcional |
| dependencies `set` | `dependencies`, lista de IDs próprios do mesmo projeto |
| follow `set` | `following`, booleano; sempre o próprio ator da credencial |

Seguir exige permissão de leitura, assim como na GUI; não permite escolher outro
staff. Gravar arquivo/marco/dependência exige edição nativa do domínio. Dependências
mantêm validação de DAG, referências ativas e mesmo pai pela implementação original.

O corpo HTTP de arquivos é limitado a 3.000.000 bytes antes de JSON/base64; os demais
corpos a 262.144 bytes e profundidade JSON 32. Base64 permite no máximo 2.796.204
bytes codificados antes de decode e 2 MiB decodificados. O writer original aplica
PDF até 2 MiB, PNG/JPEG até 512 KiB/1600 pixels, normalização para PNG, hash, quotas
de 20 uploads/20 MiB por pai incluindo ocultos, revisão e ownership privado.
Multipart, mídia bruta e campos adicionais são rejeitados, sem rotas genéricas.

Download verifica empresa, pai, filho, visibilidade e hash antes de devolver bytes.
A credencial, expiração, revogação e grants são conferidos dentro da mesma transação
canônica que grava usage/auditoria, antes e depois do trabalho. Os participantes
MySQL incluem explicitamente tokens e token_requests; MyISAM falha fechado.
Falha tardia de recibo/auditoria desfaz BLOB, revisão, token last_used e usage.
Resposta usa MIME canônico, attachment, nosniff e CSP sandbox; erro nunca herda
headers PDF ou bytes privados. Não há transporte HTTP externo nesses adapters.

Upload pela API permanece privado no portal 428. Publicação pelo staff é explícita
por pai e arquivo; ocultação pela API também impede download de publicação existente.
Este incremento não acrescenta assinatura/aceite, notificações ou homologação bancária.

## Verificação

Testes específicos: `operations_subresources.php`, `operations_subresources_controller.php`,
`operations_subresources_bootstrap.php`, `operations_subresources_concurrency.php` e
`operations_subresources_browser.cjs`, com fixture própria `operations_subresources_setup.php`.
Cobrem A/B/principal/NULL, filhos trocados, limites, scopes antigos, read-only, replay,
rotação, falha tardia/rollback e compatibilidade com publicação 428. A suíte de
concorrência contém 32 cenários com dois processos observados esperando pelo lock
real: retries, expiração, revogação de token/grant e ocultação antes dos bytes.
Resultados executados e ambiente constam no arquivo de integração deste incremento;
script preparado não equivale a navegador, concorrência ou homologação executados.
