# Rodadas de validação de PR

- Um commit novo cancela a rodada antiga da mesma PR por concurrency, sem cancelar releases/pushes de produção.
- Matrizes de PR usam fail-fast. Fora de PR mantêm a coleta completa de resultados.
- O workflow `PR validation round` cancela validações pendentes/em execução da mesma PR/SHA quando detecta falha real, timeout ou erro de inicialização. Preserva logs e o resultado reprovado. Não transforma cancelamento em aprovação.
- Apenas PRs originadas no próprio repositório. Número da PR, repositório de origem, evento e SHA são conferidos. O HEAD é reconferido antes de cancelar. Não há tokens persistentes novos: o job usa GITHUB_TOKEN efêmero com Actions write e leitura de conteúdo/PR; os testes continuam com suas permissões anteriores.
- Monitoramento a cada30segundos; até4 workflows em execução inspecionados por ciclo para observar falha de job antes do workflow terminar. Há limite90minutos, sem alegar sucesso se excedido. Agendamento/latência do GitHub pode acrescentar espera.
- A correção é analisada e publicada na mesma branch; o novo commit inicia a próxima rodada. O monitor não escreve código, não cria commits vazios e não reexecuta automaticamente um SHA conhecido como reprovado.
- Cancelamento de rodadas anteriores já iniciadas antes desta política pode exigir intervenção inicial; depois o novo monitor reconcilia execuções obsoletas da mesma PR.
- Testes locais: `python3 -m unittest tests/test_pr_validation_round.py`; `actionlint`.

Se uma execução continuar ativa após cancelamento normal (por exemplo, jobs com always()), o monitor reconfirma PR/repositório/HEAD e usa force-cancel exclusivamente naquela execução elegível. Isso libera o slot sem remover logs ou artefatos já gravados.

Os workflows especializados de reconstrução da base CI e ponte PIGE não foram modificados só para concurrency, evitando acioná-los em toda a PR sem mudança de seu domínio. Quando executados numa PR, continuam cobertos pelo cancelamento central da rodada. Inventários devem ser gerados em árvore de fontes limpa, sem incluir bundles ignorados gerados por npm build.

A preparação do navegador teve espera de18min no mirror HTTP azure.archive.ubuntu.com. O runner descartável agora usa o arquivo oficial HTTPS Ubuntu, mantém validação de assinaturas e limita timeout/retry de APT. A etapa tem limite6min; falha de infraestrutura não fica ocupando a fila indefinidamente. O monitor considera somente a execução mais recente de cada workflow no SHA para não confundir a substituição de uma execução com falha do produto.

O limite do monitor cobre o smoke integrado completo, que ainda é sequencial. Ele não reduz asserções nem transforma teste pendente em aprovado. Fail-fast trata falha observada, sem impor um limite menor que o conjunto de validações existente. A preparação de pacotes do deployer também usa o espelho oficial/timeout limitado.


## Prevalidação consolidada e repetição após merge

Antes de iniciar bancos e navegadores longos, um preflight único em `node-contracts` compila a sintaxe PHP própria, recompila e compara os bundles, verifica ligações entre funções JavaScript compartilhadas e executa os contratos Node. O gate detecta referências a funções deixadas somente no bootstrap legado, distingue funções locais e não executa requests. Isso antecipa uma classe de falhas; não substitui testes reais de negócio.

Empresas no núcleo, Banco do Brasil e Integridade das stacks aguardam esse preflight, em vez de consumir seus ambientes longos antes dele. O fluxo focado já faz essa dependência internamente. Os compilados saem do final da matriz PHP e são verificados uma única vez no início.

Após merge, os quatro fluxos longos podem reutilizar seu resultado da PR SOMENTE quando a árvore Git inteira é idêntica, a PR está realmente mesclada nesse commit/branch, é do mesmo repositório, o último run do workflow e SHA correto passou e tem menos de24horas. O gate mostra o link da evidência reutilizada. Qualquer divergência, falha, evidência ausente ou erro de consulta executa a validação completa. O preflight rápido continua sendo executado; não há aprovação fictícia nem reutilização de um resultado de outro código. Os testes leves de versão/distribuição continuam independentes. O reaproveitamento pós-merge tem testes automatizados de casos positivos e negativos; sua confirmação operacional ocorrerá no merge autorizado.

Não foi reduzida durabilidade de MySQL, alterado banco de produção ou omitida cobertura funcional para acelerar o CI.

A publicação na main também exige sucesso dos quatro fluxos longos no SHA publicado ou prova da mesma árvore da PR aprovada. Não publica enquanto CI estiver reprovado, cancelado ou incompleto. Nenhum merge ou deploy é autorizado por esses scripts.

## Browser fixtures before database suites (2026-11-01)

The status browser fixture must load the shipped `native-ui-tools.js` module. Its previous extraction from `main.js` became stale after the shared helper extraction and failed before Chromium started. The fixture now loads the actual whole, definition-only shared module. POST, CSRF, CAS, exact large IDs, retry key, missing-revision refusal and HTTP 409 assertions remain unchanged.

All 13 browser fixture commands now run in a separate early PHP 8.1/8.5 preflight, immediately after shared Node/build/syntax checks, without a MySQL service. The focused database matrix waits for both browser legs; other long workflows also require all three named jobs. The previous late browser phase is removed from the database matrix, avoiding duplicate execution. Missing, cancelled or skipped preflight is never considered success. Gate observation budget is fifteen minutes, not a new artificial success timeout.

Sales HTTP fixtures use the canonical public document ID in URLs/forms and decode it through the production PHP NativeCompanyResourceId codec only when checking physical SQL rows. Storage IDs are unchanged. All four sales, attachment and PDF-settings browser suites use this distinction; wrong namespaces and legacy IDs are rejected. Early tests cover exact round trips and collisions. The application codec, ownership and CSRF rules are not changed.

Invoice recurrence browser coverage follows the current native schedule contract: `schedule-cycles`, `company_invoice_schedules`, completed_cycles and action-scoped run/pause/resume forms. The canonical redirect `/admin/invoices/recurring/{id}` is parsed strictly, with the existing `?schedule=` read link also supported. Conflicting or unrelated references fail. The main sales scenario and both recurrence-mode/resume browser suites use the same route parser. Emission counts, ownership, pause/resume and cross-company checks remain enforced; no application recurrence behavior is modified.

Official sales-portal browser scenarios use the actual `.login-form` on `authentication/login`, not the compatibility portal's `#portal-login` selectors. The shared driver checks the issuer and native CSRF fields before submitting credentials and requires the authenticated greeting afterward. Early contracts compare the driver to the shipped login template. Compatibility-route scenarios remain separate and unchanged.
