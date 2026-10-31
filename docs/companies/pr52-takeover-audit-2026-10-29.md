# PR 52 — retomada, correções e limites de validação

> Registro histórico. Para o pacote posterior ao baseline `1de21abe`, consulte
> [a consolidação local da PR 52](pr52-local-consolidation-2026-10-29.md) e
> [a matriz completa das ações oficiais](pr52-official-action-matrix.json).
> As pendências e provas abaixo se referem ao checkpoint descrito neste arquivo.

Baseline retomado: `de4d044a9fa7e01c021fd528837244ab357a0b06`, branch
`fix/native-company-experience`. O histórico foi preservado. Esta auditoria
substitui a conclusão de entrega completa: a PR ainda contém lacunas funcionais
reproduzíveis e não está pronta para merge/distribuição.

## Correções deste pacote

- Onboarding: `CI_Output::set_header` recebe a linha HTTP inteira. Os dois
  cabeçalhos antes enviados como nome/valor separados eram inválidos. O teste
  de browser também exige `Cache-Control: no-store` e `X-Frame-Options: SAMEORIGIN`
  na resposta 409 real. O transporte completo ainda depende do CI.
- Compras: o alias histórico `company_purchase_portal/index` foi restaurado e o
  wildcard de recurso desconhecido foi removido. Permanecem os 32 aliases
  finitos com destinos canônicos; nomes de recursos inválidos não são admitidos.
- Testes de bootstrap mantêm namespaces públicos históricos, verificando agora
  o destino canônico real (`operations_api`, `purchases_api`, `purchase_portal`).
- O teste negativo Efí usa a classe real CompanySettingsRuntime, preparada por
  reflexão somente no teste, sem rede/SQL/segredos; não redeclara a classe
  carregada pelo controller Settings. Seu pai sintético tem construtor explícito.
- O teste da ACL legada de propostas explicita que não está no caminho nativo.
- Fixtures do portal criam os pais contacts/tickets antes das FKs de catálogos,
  inicializam defaults públicos pela operação transacional real e incluem
  `staff.is_not_staff`. A suíte não ignora nem altera validadores de produção.
- Fixtures de detalhes de projeto/chamado incluem os participantes do portal e
  sessão exigidos pela implementação atual. POST sem prova retorna 403.
- O censo foi gerado por `php scripts/native-experience-inventory.php`:
  somente 78 números de linha mudaram; métodos e classificações são idênticos.
- Inventários lexical e de isolamento foram regenerados. Arquivos alterados
  voltam a `unreviewed`; não foi fabricada uma revisão positiva nem liberado rollout.

## Limpeza de cache

Os dois workflows usam `scripts/cleanup_actions_cache.py` e a mesma concurrency
`argws-crm-cache-cleanup`, sem cancelamento da execução corrente. A manutenção
periódica passa a seis horas e também ocorre ao fechar PR; publicação bem-sucedida
continua sendo gatilho. Manual permanece dry-run por padrão e exige ref main.
O checkout sempre usa main, inclusive em evento de fechamento de PR.

A regra é repositório ocioso, sem limites arbitrários de idade, prefixo ou lote.
Inclui Buildx PHP 8.1/8.5, pip e caches associados a refs de PR. Qualquer outro
run cujo status não seja `completed` bloqueia, inclusive em outra branch,
pois PRs podem restaurar caches da branch padrão. Só o próprio run é excluído.
A paginação exige total_count consistente, IDs únicos e coleção completa.
Erro, truncamento, duplicidade ou mudança de coleção bloqueiam a exclusão.
Antes de cada DELETE, o cache é relido e todas as execuções são reconferidas.
Cache alterado é preservado; DELETE com resultado incerto não é repetido.
O JSON registra IDs, decisões, motivos, tentativa incerta, bytes antes/depois e
bytes de exclusões confirmadas. Falhas são preservadas no artifact e no exit code.

**Limite:** o GitHub não oferece lock atômico de uso por cache. Existe uma janela
entre a última consulta e o DELETE; esta solução reduz o risco e não promete
eliminar essa corrida. Repositórios continuamente ocupados adiam a limpeza.
A listagem integral de runs pode custar mais chamadas; falha parcial sempre fecha
as exclusões. Artifacts, releases, GHCR, runs e logs não são alvos de DELETE.

Nenhum inventário remoto de caches ou purge foi executado nesta retomada.
A negativa anterior de acesso ao endpoint de caches não foi contornada.

## Matriz de entrega

| Requisito | Implementação observada | Validação / pendência |
|---|---|---|
| Preservar histórico, mesma PR | branch linear a partir de de4d044 | sem merge, deploy, reset ou force-push |
| Zero arquivos company_* | gate físico existente | zero violações; não prova equivalência de funções |
| PWA | commits anteriores preservados | workflow do baseline passou; revalidar SHA final |
| Censo atualizado | geração oficial, 78 linhas reconciliadas | sem mudança de métodos/classificação |
| Isolamento por empresa | guards preservados; inventários atualizados | rollout continua bloqueado; revisão lexical não é prova de isolamento |
| Onboarding HTTP | cabeçalhos corrigidos; regressão adicionada | smoke HTTP/MySQL/Chromium final pendente |
| Limpeza sem excluir durante atividade | estratégia conservadora acima | nove testes offline; nenhum purge real |
| Rotas de compras | aliases finitos e canônicos | 32 mappings, seis negativos; bootstrap cumulativo 480 checks |
| Efí | controller negativo atualizado | scope/método/CSRF passam, sem provedor |
| Principal/A/B/restrito | contratos existentes preservados | diagnóstico completo SQLite: 433 comandos, cinco falhas iniciais; três revalidadas após correção |
| Projetos/cópia | `Projects::copy` no caminho nativo só aceita GET/HEAD e redireciona | POST original não equivalente; **pendente** |
| Contratos originais | catálogo nativo admite subset | table/send_to_email/renew/copy/delete/comentários exigem composição e testes; **pendente** |
| Settings vendas principal | adapter usa options/contador histórico para principal; A/B mantêm armazenamento próprio | formulário/controller, CSRF, CAS, rollback e consumidor de datas passam SQLite/MySQL; CI final pendente |
| Histórico estimates/proposals | adapter CompanySales lê company_sales_documents | ausência de união com tabelas legadas aponta inacessibilidade, não perda física; **pendente** |
| Pipeline vendas | rota admite GET/HEAD; table exige assertNativeMutation | contrato HTTP inconsistente; **pendente** |
| Chamados históricos | leitura de corpo textual e autoria do portal por registros existentes | original detail + portal passam SQLite/MySQL; comandos novos dependentes de metadata continuam fechados para históricos, sem autoria inventada |
| Asaas/BB/conciliacão | fontes bancárias não alteradas neste pacote | homologação real não executada; CI não equivale a homologação |
| CNAB400 | operational=false preservado | depende de layout/transporte/retorno homologados; não liberado |

## Evidências locais e limitações

Ambiente atual dispõe de Docker; foi usado o runtime oficial PHP 8.1 fixado por
`sha256:c6246e2740c225e7db4f0db2b8ba8657fb72cb9638efdedcfe1a8853fc081a14`
e MySQL 8.0 descartável, com credenciais sintéticas. Não se aplica a antiga
alegação de ausência de Docker neste novo workspace.

- `python3 -m unittest tests/test_actions_cache_cleanup.py tests/test_native_prefix_gate.py tests/test_invoice_attachment_storage.py`: 15 testes passaram.
- `python3 scripts/check_native_prefixes.py`: zero violações.
- `python3 scripts/ci/run-native-company-focused.py node` no runtime fixado: 43/43 comandos passaram.
- Sintaxe YAML de todos os workflows e `git diff --check`: passaram.
- `php tests/efi/native_controller_contract.php`: três barreiras negativas passaram.
- `php tests/companies/purchases_route_catalog.php`: 32 aliases e seis negativos passaram.
- `php tests/companies/purchases_bootstrap.php`: 480 checks cumulativos passaram.
- `php tests/companies/hr_bootstrap.php`: 296 checks cumulativos passaram.
- `php tests/native_company/proposals_list_acl.php`: seis casos passaram.
- `php tests/native_company/ticket_detail_page.php`: passou após preparar o portal/sessão.
- `php tests/companies/native_project_detail_guard.php`: passou após preparar o portal/sessão e esperar 403 para POST sem prova.
- Diagnóstico de todos os 433 comandos SQLite, sem remover checks: cinco falhas
  iniciais (propostas, ticket_detail_page, native_project_detail_guard,
  contracts_controller, contract_discussions_controller). As três primeiras
  foram corrigidas e reexecutadas; as duas últimas permanecem pendentes.
- Diagnóstico MySQL reproduziu a ausência do pai tickets. Corrigida essa
  dependência, expôs testes de controllers ainda apontando ações/views antigas,
  inclusive projects_controller e seus dependentes, contracts_controller e
  asaas_preferences_controller. Não são declarados verdes.

O diagnóstico adicional permite continuar após falhas para descobrir o conjunto;
o runner versionado de CI continua fail-fast e nenhuma asserção foi desativada.

## Operação, compatibilidade e rollback

Não há migration, dado, credencial, dependência ou integração de produção alterada
neste complemento. O incremento core já presente na PR é 479/4.7.9; novas
migrations históricas não foram editadas. O patch de rotas preserva os aliases
válidos e remove admissão genérica não contratada. Restaurar este complemento
exige revert normal do commit, mantendo o histórico e os dados.

Merge/deploy/release continuam não autorizados. Após solucionar as pendências,
validar instalação limpa/upgrade e principal/A/B/restrito no SHA final, revisar a
matriz e só então avaliar publicação. Limpeza deve ser conferida primeiro em
dry-run por fluxo autorizado; não disparar purge para contornar negação de acesso.

## Segundo checkpoint de validação (29/10)

O CI do primeiro complemento (`1134ddd`) confirmou sucesso em escopo de faturas,
PWA, inventário de fronteiras, Node focused e outros gates. Empresas no núcleo
continuou vermelho: <https://github.com/wkarts/ARGWS-CRM/actions/runs/40868763644>.

O inventário `operational-surfaces.json` no blob publicado não era JSON UTF-8
válido. Foi reconstruído com `php scripts/company-surface-inventory.php`; seu
`--check` passou, sem mudar o estado bloqueado de prontidão operacional.

O teste negativo Efí usava `ReflectionProperty::setAccessible`, obsoleto em
PHP 8.5 e dispensável desde PHP 8.1. A saída extra quebrava a comparação exata do
subprocesso. Removida a chamada, as três barreiras passaram em PHP 8.1 (imagem CI)
e PHP 8.5 (imagem oficial CLI Alpine). Falhas futuras agora incluem saída/exit do
filho no diagnóstico, mantendo a comparação e os códigos esperados.
A imagem completa CI 8.5 não coube no disco local; o runtime Alpine foi usado
somente para esse teste sem banco, e não substitui a matriz completa do CI.

Reexecuções MySQL com sucesso:
- Asaas preferences/controller: 179 checks cumulativos; chamada corrigida para
  a ação oficial `Settings::asaas`.
- Operations subresources bootstrap: 95 checks, destinos oficiais dos aliases.
- Bank reversal report views: 197 checks, caminhos atuais e framework sintético.
- Ledger controller/CSV/paginação/renderização: 162 checks. O teste de revogação
  mantém a rota admitida como nativa e revoga separadamente o selo; assim o
  handler financeiro deve recusar 503 antes da leitura.
- Testes Python de cache/prefixos/anexos: 15 passaram novamente.

O diagnóstico amplo MySQL segue expondo dependências históricas dos testes:
projects_controller e dependentes esperam o antigo POST em index e views
removidas; settings sales_pdf/document_pdf dependem de um fixture que passou a
exercitar forms diretamente e já não define Settings. Tickets históricos sem
metadados novos são recusados por NativeCompanyTicketThreadPresentation com
native_ticket_metadata_required. Essas falhas não foram mascaradas nem
classificadas como simples problema de cache. A matriz funcional acima continua
pendente; este checkpoint não autoriza merge nem afirma entrega completa.

Também foram reexecutados com MySQL os regressivos de exportação de despesas
(115 checks) e conversão de despesa em fatura (125 checks): os fixtures agora
invocam Settings::imports, mantêm admissão nativa separada do selo revogável e
usam IDs canônicos/CSRF da ação atual. As verificações A/B/principal, auditoria
antes de bytes, revogação e persistência continuam ativas.

O run de stacks 40868763643 confirmou instalação limpa/upgrade e três execuções
do browser smoke, superando o erro anterior de cabeçalho. O próximo ponto de
falha do onboarding é `/admin/invoices`: o teste antigo espera 403, mas a rota
agora adaptada responde 503. Não se alterou a asserção para aceitar falha interna.
É necessário diagnosticar o GET integrado e comprovar a tela positiva antes de
atualizar esse contrato de browser. Não houve homologação completa das stacks.

## Correção adicional da apresentação de faturas

Foi identificada uma dependência ausente no bootstrap nativo: o AdminController
carregava helpers de configurações/tickets, mas não o helper de faturas usado
por quick_stats (`get_invoice_status_label`/`format_invoice_status`). O fixture
substituía essas funções e mascarava essa ausência. O helper de faturas agora é
carregado no mesmo caminho nativo; os testes de rows/manage usam suas funções
reais. A página aninhada passou com SQLite (156 checks) e MySQL (158 checks).
O browser de onboarding exige agora resposta 200, DataTable oficial, um único
content root e retenção da empresa selecionada. Essa prova HTTP precisa ser
confirmada no novo CI; não basta a renderização com envelope sintético.

A tentativa de preparar reprodução HTTP local foi bloqueada por falta de disco
no Docker VFS. Apenas o cache local produzido por essa tentativa foi removido,
recuperando espaço. O endpoint de caches GitHub continua sem consulta/exclusão.
O download do artefato de diagnóstico 12894932888 retornou URL, mas seu GET
respondeu HTTP403; os logs de jobs permaneceram disponíveis.


## Complemento consolidado após 50fc8f4

- Settings de orçamentos/propostas da principal conserva `options` e o contador
  legado, não migra históricos nem mistura a sequência das empresas A/B. O novo
  adaptador de contador exige revisão atual e valor acima dos números emitidos;
  rollback de auditoria, formulário original e CSRF foram exercitados.
- O POST de clientes preserva os campos textuais antes do escritor validado e
  das views que escapam saída. O filtro XSS global antes alterava o nome literal
  usado pela recuperação da prova de onboarding. Controller com texto literal,
  replay e isolamento foi exercitado; browser final ainda é obrigatório.
- Recorrência declara todos os participantes transacionais dos escritores atuais.
  Núcleo, integridade, API, worker e concorrência MySQL reexecutados no diagnóstico;
  o controller antigo ainda tem fixture pendente. Nenhum guard foi removido.
- Branding histórico ignora somente `atime` na comparação anterior/posterior à
  leitura: a própria leitura atualiza esse campo. Regressão reproduzida com
  horário de acesso antigo; inode, tamanho, mtime e ctime continuam comparados.
- Chamados históricos são apresentados como texto, sem fabricar metadados de
  origem/autor. Quando existe recibo de autoria do portal, o leitor já autorizado
  fornece nome/contato e é revalidado. IDs e autoria de respostas continuam vindo
  do leitor canônico. As ações novas dependentes de metadados não são concedidas
  a chamados históricos. Essa limitação permanece explícita.
- Fixtures de projetos/tarefas/contratos agora exercitam os controllers atuais.
  Harnesses incompatíveis são executados em processos separados, com exit e
  diagnósticos PHP obrigatórios; criação, revisão, CSRF, isolamento, histórico,
  anexos e HTML escapado continuam testados. Nenhum filho é opcional.
- A fixture SQLite declara metadados MySQL somente para o schema de respostas
  que ela efetivamente instala; isso não é prova de migração MySQL. Os testes
  MySQL usam informação real do servidor e não entram nessa declaração.

### Evidência deste complemento

- Runner focused SQLite oficial: **434/434 comandos passaram**, PHP 8.1, incluindo
  os dois novos entrypoints de texto literal e Settings principal. Evidência
  local preservada; ainda não equivale a validação no commit publicado.
- Settings principal: 29 checks adicionais de armazenamento, mais controller
  real/formulário/CSRF/revisão/contexto, passaram SQLite e MySQL.
- Portal operações: 110 SQLite / 112 MySQL cumulativos; suporte e arquivos do
  portal: 125 SQLite / 127 MySQL cumulativos, em cada entrypoint.
- Projetos extras: 45 SQLite / 47 MySQL; tarefas independentes: 42 / 44.
- Contratos/controller/PDF passaram SQLite e MySQL; os subtestes nativos de
  tickets usam SQLite explicitamente, inclusive quando chamados do job MySQL.
- Recorrência API: 76 novos / 112 cumulativos MySQL. Núcleo/integridade haviam
  passado 116/83 novos checks respectivamente. Não houve transporte externo.
- Diagnóstico MySQL de 71 falhas anteriores: 30 passaram, 41 falharam durante
  a reconciliação; várias falhas foram corrigidas depois. É diagnóstico de
  estados locais em mudança, **não** uma matriz final verde. Logs preservados.
- No SHA publicado 50fc8f4, Banco do Brasil terminou success; focused, núcleo e
  stacks terminaram failure. As correções locais exigem novo CI final.

### Pendências funcionais que impedem fechamento

Continuam abertas as quatro lacunas da matriz: histórico de vendas legado,
rotas/ações completas de contratos secundários, POST original de cópia de projeto
(e metadados/participantes correspondentes) e pipeline GET com contrato HTML.
Além disso, fixtures antigas de Settings PDF/políticas apontam grupos removidos;
PDF original ainda precisa de composição completa, não de reintrodução de uma
página paralela. Há testes antigos de outros controllers ainda em reconciliação.
Censo atualizado e ausência de arquivos prefixados não comprovam entrega dessas
funções. Este complemento **não declara a PR concluída nem pronta para merge**.
