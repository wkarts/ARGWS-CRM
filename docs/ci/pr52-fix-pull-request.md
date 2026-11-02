# Pull Request — complemento da #52

**Branch:** `fix/native-company-experience` (desenvolvimento local em `fix/pr52-invoice-validation`).

**Título:** `fix(ci): corrigir formato PDF legado e dividir smoke em oito stacks isoladas`

**Descrição / contexto / objetivo:** A última falha de dimensão vinha de exigir um formato congelado no renderer legado, que lê a opção global atual. A conferência agora exige o formato atual exato no legado e o snapshot imutável nos documentos nativos. O smoke de 101 chamadas Node é distribuído em oito stacks isoladas, com cobertura completa, dependências explícitas e gate agregado obrigatório. Detalhes em [standalone-browser-groups.md](standalone-browser-groups.md). Histórico anterior: O validador remoto permitia números pertencentes a outra empresa, mas o POST legado os rejeitava e o navegador procurava por minutos uma fatura inexistente. Alinhar criação/edição ao escopo legado e reduzir preparação repetida de toda a rodada, preservando cobertura.

## Escopo e decisões

Nova etapa: `settings_document_pdf_browser.cjs`, teste rápido `legacy_invoice_pdf_format.php`, manifest/runner de grupos e casos negativos, orquestração em `deployment-integrity.yml`, artefatos por grupo e Rust/Compose paralelo. Imagem construída uma vez e validada pela árvore em cada worker. Nenhuma alteração de código da aplicação nesta etapa.

Duas consultas no controller; driver de testes; regressões rápidas; caches de ferramentas/compilação; compilação paralela das mesmas extensões; retirada de job sem teste; evidências de tempos e auditoria histórica. Fora do escopo: refatoração da aplicação, paralelização no mesmo banco, exclusão de testes ou publicação/merge. Detalhes e fontes em [pr52-validation.md](pr52-validation.md).

## Alterações e arquivos

Modificados: `application/controllers/admin/Invoices.php`; workflows de CI em `.github/workflows`; `tests/helpers/native_invoice_driver.cjs`; os dois scripts de smoke; contrato de shards; inventários gerados em `docs/companies` e `docs/issuer-isolation-inventory.json`.

Adicionados: `tests/legacy_invoice_number_scope.php`, `tests/helpers/persisted_invoice_target.cjs`, `tests/persisted_invoice_target.test.cjs`, `tests/ci/test_ci_runtime_reuse.py` e documentação/auditoria em `docs/ci`.

Removidos: nenhum arquivo. O job `registry-plan` não executava testes e foi removido do caminho crítico; seu preflight e os oito shards permanecem obrigatórios.

## Banco de dados

Sem DDL, migration nova ou alteração de dados. Consultas de duplicidade agora exigem `issuer_id IS NULL` quando o schema existe; compatibilidade anterior à migration mantida. A prova de persistência lê somente o banco descartável.

## APIs, contratos e integrações

Métodos, rotas, formulários e resposta PRG preservados. Criação/edição principal deixam de conflitar com numeração de empresas nomeadas; duplicidades no legado continuam bloqueadas. Sem chamadas a bancos/SMTP externos.

## Dependências, configuração e ambiente

Sem novas dependências da aplicação. Sete workflows adicionais usam as bases PHP 8.1/8.5 já publicadas por digest; o workflow da base só recompila por mudança de inputs ou atualização manual explícita. Actions de cache/Buildx já usados no repositório; mesma toolchain, lockfiles, matrizes PHP e bancos. Nova flag de teste `ARGWS_SMOKE_PREBUILT_IMAGE=1`: exige imagem local com label da árvore Git exata. Sem flag, mantém build local padrão. Sem novos secrets.

## Segurança e privacidade

Sem credenciais nos argumentos SQL ou no relatório. Cache não substitui teste nem autoriza publicar imagem. Driver não repete o POST legado e não aceita ID antigo/de outra empresa como comprovação. Caches não contêm snapshots de banco de produção.

## Desempenho e observabilidade

Removidos waits fixos de dez segundos por save e sucessivos fallbacks de 90 segundos; cache de camadas/npm/Chromium/Rust; extensões PHP compiladas com os mesmos alvos em paralelo; removida uma alocação serial sem teste. Logs `BROWSER_START`/`BROWSER_END` registram duração e saída por comando. Ganho global ainda não medido no CI remoto.

## Testes implementados

Novos: quatro cenários de formato legado atual versus snapshot nativo; 101 invocações com dono único; oito execuções simuladas usando os blocos reais do shell; ordem, dependências, falhas propagadas, execução incompleta, modo Node sem banco e gate obrigatório rejeitando falha/cancelamento/skip.

12 cenários do controller: criação/edição, número em empresa nomeada, duplicidade legada, rascunho, próprio ID, ano e schema anterior. Quatro testes Node de identidade exata/ambiguidade/entrada/banco indisponível. Três contratos Python para imagem incorreta, cache sem omitir execução e gate sem job vazio.

## Testes executados

Etapa atual: 55 testes Python de CI aprovados; testes de ambiente/provisionamento/empacotamento; quatro cenários dos renderers em PHP 8.1/8.5; parse YAML, sintaxe JS/shell e quatro inventários. Nenhum entrypoint anterior removido. Execução real dos oito grupos ainda precisa concluir no CI do commit final.

- Teste PHP com doubles contra código anterior: falha reproduzida no caso `named`; contra código corrigido em PHP 8.3: 12 cenários aprovados.
- `python3 -m unittest discover -s tests/ci -p 'test_*.py'`: 48 aprovados.
- `node --test tests/ci_preflight_order.test.cjs tests/persisted_invoice_target.test.cjs tests/native_invoice_transport.test.cjs`: 19 aprovados.
- `python3 -m unittest tests/test_ci_validation_base.py tests/test_docker_provisioning.py tests/test_issuer_browser_environment.py tests/test_pr_validation_round.py tests/test_company_boundary_inventory.py`: 39 aprovados.
- Parse YAML, sintaxe PHP/JS/shell e comparação dos entrypoints anteriores/atuais: sem comandos de teste removidos.

Também executados após o pull das bases reais GHCR, em PHP 8.1 e 8.5, sem rede: smoke Chromium; 12 cenários do controller; 4.023 checks de datas recorrentes; 33 de idioma; 88 do painel; quatro testes Node de persistência. Ambos aprovados.

## Testes recomendados / pendentes

Smoke HTTP completo em MySQL/Chromium e matrizes remotas do commit final; comparar execução com cache frio/quente. Sem alegação de redução medida ou CI completo aprovado neste ambiente.

## Validação manual

Na empresa nomeada, criar fatura com número N. Na principal, criar fatura legada N no mesmo ano: deve salvar e abrir o ID novo. Repetir N no legado: deve rejeitar. Editar o próprio número sem mudança: deve permitir. No CI, imagem de árvore divergente deve falhar antes de provisionar; cache hit não deve eliminar testes.

## Compatibilidade, impactos, riscos e mitigação

Sem breaking changes previstos. Caches frios e variação dos runners impedem prometer duração fixa. A prova com doubles não substitui SQL/navegador. Workflows preservam dependências de sucesso, matrizes e fixtures isolados; ordem dos testes de navegador preservada.

## Deploy

Atualizar a PR #52 e aguardar checks do commit final. Esta alteração não autoriza merge, release ou implantação.

## Rollback

Reverter o commit desta correção. Os caches são descartáveis; o smoke mantém build padrão. Sem rollback de dados.

## Build, release e distribuição

Imagem de smoke carregada apenas localmente, `push: false`. A PR #52 já carrega os incrementos nativos 474–484 em relação à main 473; esta correção permanece na mesma PR e não altera migrations históricas. Nenhuma tag/release nova criada.

## Documentação

Auditoria com contagens diárias não truncadas e links de fontes públicas, limitações, causa reproduzida e validações pendentes.

## Checklist

- [x] Causa reproduzida e corrigida no POST.
- [x] Duplicidade real, ano, rascunho e próprio ID preservados.
- [x] Entry points de testes preservados; matrizes mantidas.
- [x] Testes direcionados aprovados; inventários regenerados.
- [ ] CI completo do commit final aprovado.
- [ ] Redução global medida com cache frio/quente.

**Commit:** `fix(ci): align legacy invoice numbers and reuse validation tooling`.

**Merge sugerido:** manter estratégia já adotada pela PR #52, após checks e autorização.

**Versão:** núcleo 4.8.4 já previsto pela PR; sem bump adicional nesta correção da mesma PR.
