# PR 52 — retomada, correções e limites de validação

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
| Settings vendas principal | adapter nega primary em authorize | forms encaminha estimates/proposals ao adapter; **pendente** |
| Histórico estimates/proposals | adapter CompanySales lê company_sales_documents | ausência de união com tabelas legadas aponta inacessibilidade, não perda física; **pendente** |
| Pipeline vendas | rota admite GET/HEAD; table exige assertNativeMutation | contrato HTTP inconsistente; **pendente** |
| Chamados históricos | thread exige metadata nova | ticket criado pelo writer histórico falha `native_ticket_metadata_required`; **pendente**, sem inventar autoria |
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
