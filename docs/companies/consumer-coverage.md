# Registro auditável de consumidores de empresas

## Estado atual reconciliado em 19/10/2026

O histórico abaixo descreve etapas anteriores. `admin/company_clients` já está
roteado no renderer isolado com CRUD/busca/paginação/compartilhamento/revogação.
Portal privado já tem login/reset, templates/SMTP/fila por origem, permissão
nativa e lista/PDF de faturas emitidas. Faturas nomeadas já têm emissão, edição
elegível, cancelamento, PDF e recebimento manual;417 amplia tributos/descontos/ajustes.
422 acrescenta notificação ao contato privado com permissão, fila/recibo/auditoria
atômicos e revalidação antes do transporte; SMTP externo não foi executado.
Há execuções MySQL/concorrência/CRM HTTP/Chromium nos SHAs referenciados em
[pr45-status.md](pr45-status.md). Isso não libera todos os consumidores nem
comprova SMTP externo ou homologação bancária. O novo código tem resultado próprio
registrado por SHA; não reutilizar automaticamente resultados anteriores.

As guardas genéricas continuam fechadas. Os fluxos novos têm rotas/serviços
explicitamente autorizados, sem permitir modelos legados por prefixo.

## Histórico de investigação — não representa o estado atual


O registro é finito e reproduzível: `scripts/register-company-consumers.py` gera `consumer-register.json`. A opção `--full-output /tmp/company-consumers-full.json` inclui cada candidato com arquivo, símbolo, linha, hash e categoria. O resumo versionado evita incluir um artefato de vários megabytes. `consumer-evidence.json` associa somente os resultados efetivamente exercitados a símbolos concretos; mudar o arquivo invalida a evidência até nova execução/revisão.

## Como interpretar

- `boundary_tested`: o teste sintético citado exercitou apenas os resultados declarados. Não significa isolamento completo do método, arquivo ou módulo.
- `pending`: ainda não há prova válida registrada por símbolo. Pode haver proteção implementada ou testes anteriores ainda não classificados. Não equivale a um bug confirmado ou a uma estimativa de esforço.
- `full_operation_ready=false`: nenhuma classificação deste inventário libera a empresa secundária. Faltam validações transversais, MySQL/concorrência e HTTP.
- A descoberta é lexical e conservadora. Inclui falsos positivos e pontos de extensão. Dependências sob diretórios vendor/third_party/sdk, traduções e histórico de migrations são excluídos desta lista; suas interfaces continuam sendo responsabilidade dos consumidores. Não é um grafo completo de execução.

## Estados distintos

1. Preservação legada: listagens e pesquisas, edição de comentário, exclusões nativas de tarefa/projeto/contrato/despesa e limpeza de filhos foram exercitadas com registros legados sintéticos. Isso verifica que o filtro não inutiliza esses caminhos.
2. Bloqueio de acesso indevido: IDs privados, filhos de outro pai, OR de busca, referências indiretas, anexos e origens de fatura nomeadas foram negados nos métodos citados. Não implica que todos os consumidores equivalentes estejam cobertos.
3. Operação positiva na segunda empresa: `CompanyClients::create/get/page/update/setSharing` executados no serviço com empresa e ator explícitos, ACL e rollback. Esse serviço ainda não está roteado para uso porque leitores legados/portal continuam pendentes. Configurações de identidade e SMTP têm suítes próprias. O grupo Data e hora agora tem gravação pelo controlador nativo, renderização sintética do formulário, valores A/B independentes, rejeição atômica e execução dos helpers reais de apresentação e conversão. Não modifica timezone nem libera outras rotas operacionais.

## Bloqueadores ativos

- Orçamentos/propostas: leitura principal, negações de anexos/comentários/atividade/assinatura e alterações de pipeline já têm provas sintéticas delimitadas. Restam cascatas completas, relatórios, SQL próprios ainda não classificados e provas transversais antes de liberar operação secundária.
- Créditos: leitura principal e limites de reembolso/aplicação foram testados; falta a revisão integral de limpeza de filhos, status derivado, relatórios e concorrência financeira. Nenhuma política de baixa ou estorno remoto foi alterada.
- Portal: a troca legada de empresa agora usa POST e consulta fresca do par contato/cliente, preserva troca positiva e rejeita destinos privados, inativos ou ambíguos. Construtor e identidade própria têm provas sintéticas. Autenticação/autologin, recuperação de senha, transporte e rotas públicas ainda precisam de prova integrada; CSRF/HTTP/browser não foram exercitados.
- Módulos: cada consumidor de dados/configurações precisa aderir à empresa explícita; filtros de clientes legados implementados em alguns módulos não tornam o módulo inteiro multiempresa.
- Transporte: jobs, e-mails, callbacks, importações e notificações precisam conservar a origem explícita sem depender da empresa selecionada na sessão.
- Validação: MySQL real, corridas e navegação HTTP/browser permanecem pendentes no ambiente atual. PHP WASM/SQLite não substituem essas provas.

Os números por categoria devem ser lidos do JSON gerado para evitar uma segunda cópia desatualizada. São contagens de candidatos/provas registradas, não percentuais de conclusão do produto.

## Portas operacionais: o que está e não está liberado

| Caminho | Prova local efetiva | Estado operacional |
| --- | --- | --- |
| Seleção da empresa autorizada | Contexto e tokens em sessão sintética; revogação e troca de contexto | Rota prevista no núcleo; HTTP integrado pendente |
| Definições: identidade e SMTP | Controlador/modelo reais, persistência A/B, rollback e formulário renderizado em fixture | Permitido pelo guard secundário; envio SMTP real não executado |
| Definições: Data e hora | Controlador e helpers `_d`, `_dt`, `to_sql_date`, A/B e rejeições | Permitido no mesmo grupo de rotas; navegador pendente |
| Clientes privados e compartilhados | Serviço explícito de criação/leitura/edição/vínculos e rollback | Ainda sem liberação de rotas; adoção continua bloqueada |
| Propostas/orçamentos/créditos | Consultas legadas filtradas, negações de filhos privados, metadados e agregados delimitados | Segunda empresa continua bloqueada; não é emissão multiempresa concluída |
| Portal do cliente | Identidade própria e troca legada positiva com consulta fresca em fixture | Adoção de novos clientes privados depende de autenticação/recuperação/transporte integrados |
| Módulos e jobs | Adaptações pontuais com provas nominadas; e-mail com origem explícita | Sem autorização geral para módulos não adaptados nem processamento financeiro secundário |

As guardas `IssuerPolicy::assertOperationalScope` e `ClientIssuerScope::assertAdoptionReady` permanecem fechadas. Não devem ser removidas com base apenas na contagem de testes ou na existência do cadastro de empresas. A identificação do master legado continua dependente de decisão explícita; estes testes usam atores sintéticos, sem presumir o usuário 1.

### Próxima fronteira concreta do portal

A inspeção de `Authentication_model::login`, `set_password_email` e `forgot_password` ainda encontra seleção de contato por e-mail seguida de `row()`, sem um contrato explícito de empresa. O legado `multiple_companies` admite contatos com o mesmo e-mail, portanto trocar isso por uma negação indiscriminada de duplicados causaria regressão. A resolução da identidade de entrada e a origem do e-mail de recuperação precisam ser adaptadas juntas, conservando o legado. `autologin` e login por senha já revalidam o par ativo antes de estabelecer sessão, com provas sintéticas do método real. Cookies e senha são simulados nesses testes; controlador HTTP e browser continuam pendentes. Não se pode deduzir uma empresa atual de uma sessão administrativa para resolver esses acessos públicos.

Esses são bloqueadores de liberação, não evidência de exploração em produção. Nenhum endpoint de produção, credencial, cookie real ou envio de recuperação foi utilizado nesta investigação.

A recuperação legada e de colaboradores foi exercitada com entrega falsa. Contatos privados agora são recusados antes de girar chave ou enviar usando configuração global implícita; isso mantém a recuperação secundária indisponível até o adaptador de origem ser concluído. A próxima adaptação precisa cobrir conjuntamente `Emails_model::get`, `App_mail_template::prepare` (incluindo fallback de idioma), remetente/cabeçalho/rodapé e `App_Email`/fila. O SMTP isolado existente, sozinho, não isola o template e os campos de mesclagem.

`PortalClientIdentity::mailOrigin` resolve agora contato, cliente ativo, proprietário e empresa ativa em uma única consulta, sem usar e-mail repetido, vínculo de compartilhamento ou empresa administrativa selecionada para escolher origem. Testes A/B ligam esse resultado ao `CompanyMailConfig` real com descriptografia simulada. Esse componente ainda não está ligado ao disparo de recuperação: não é evidência de envio, template ou recuperação secundária integral.

## Continuação local de modelos de e-mail

O candidate público solicitado permanece congelado em `b8a5cec7` (368 arquivos).
A continuação local acrescenta schema/fábrica de modelos por empresa e editor
nativo restrito a `Emails::index/email_template`. Há provas de upgrade/replay,
cadastro com rollback, listagem/edição A/B, ativação coerente dos fallbacks,
revogação de RBAC, token obsoleto, bootstrap e escape de HTML persistido.
Essas provas usam PHP/SQLite e I/O de framework simulado, sem servidor HTTP.

A composição contato explícito → proprietário → modelo → SMTP já foi exercitada
com dois contatos de mesmo e-mail, inclusive cliente compartilhado. Ainda falta
a cadeia de recuperação/token, merge fields, persistência de origem na fila e
entrega. Não foi habilitado envio real nem teste SMTP pelo novo editor.

Para o próximo passo, `Authentication_model` ainda usa `row()` por e-mail;
`App_mail_template::get_language` e merge fields têm consultas legadas;
`App_Email` captura a origem da sessão administrativa ao construir a mensagem.
O caminho público precisa uma origem autenticada/persistida própria, sem usar
uma sessão administrativa ou escolher o primeiro contato de e-mail duplicado.
A compatibilidade do módulo legado de múltiplas empresas precisa ser preservada.

A conclusão por chave já existente também foi revisada: `can_reset_password` e
`can_set_password` validam o par ativo e recusam a origem privada antes de
`reset_password/set_password` modificarem senha ou chamarem aviso global.
Validação positiva das chaves legadas/de colaboradores e expiração foram
exercitadas; conclusão positiva privada e consumo atômico da chave seguem pendentes.
