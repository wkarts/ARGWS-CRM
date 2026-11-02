# Auditoria e correção da validação da PR #52

Consulta: 02/11/2026, 04h19 de Brasília. Evidência estruturada e links das consultas: [pr52-validation-audit.json](pr52-validation-audit.json).

A branch possui **7.506 registros de execução de workflows** nas consultas diárias de 25/10 a 02/11 (dias UTC), todos com contagem não truncada. Não são 7.506 testes, tentativas de rerun nem horas faturadas. Algumas execuções precedem a abertura da PR. Primeira execução encontrada: 25/10, 16h43min59s de Brasília; PR aberta às 18h02min18s. Há 351 commits acima da main disponível no momento da coleta. A consulta geral limita a contagem em 2.500, por isso foi segmentada por dia.

| Dia UTC | Workflows |
|---|---:|
| 25/10 | 50 |
| 26/10 | 1.435 |
| 27/10 | 890 |
| 28/10 | 454 |
| 29/10 | 1.926 |
| 30/10 | 1.159 |
| 31/10 | 596 |
| 01/11 | 710 |
| 02/11 até a consulta | 286 |

Na última rodada consultada, iniciada às 02h59min27s de Brasília em 02/11, Integridade das stacks falhou em 1h03min, Banco do Brasil passou em 42min52s, Empresas no núcleo passou em 32min18s e Native company experience focused passou em 15min24s. O monitor durou 1h03min35s acompanhando a rodada: esse tempo não é execução de testes adicionais. No job de deployment, o passo de provisionamento e navegadores consumiu 3.458 segundos (57min38s). As durações são de workflows/steps e incluem esperas; não podem ser somadas para estimar tempo decorrido ou cobrança.

## Causa reproduzida e correção

O endpoint remoto de número usa `InvoiceIssuerScope::applyLegacy`; criação/edição verificavam duplicidade sem esse filtro. Um número usado somente por empresa nomeada passava na validação remota e era rejeitado pelo POST principal. O novo teste executa os métodos reais do controller com fronteiras de persistência/redirect substituídas: falha no código anterior no caso `named` e passa nos 12 cenários corrigidos. As duas consultas de POST agora usam o mesmo escopo legado; ano, rascunho e exclusão do próprio ID permanecem.

O driver agora identifica a linhagem antes da navegação e captura o maior ID antes do POST. A recuperação pelo banco exige exatamente uma linha nova para cliente e número enviados, com `issuer_id IS NULL`. Não aceita uma fatura antiga/global como sucesso, não repete o POST legado e expõe falhas do banco. Foram retiradas as esperas obrigatórias de dez segundos por gravação e as recuperações sucessivas de até 90 segundos. A leitura do banco tem limite de dez segundos. O navegador ainda precisa abrir e renderizar a fatura persistida.

## Melhoria da rodada completa

- Cache npm no preflight comum; `npm ci`, compilação e comparação dos assets continuam executando.
- Cache Chromium por SO/arquitetura/lockfile e Rust por SO/toolchain/Cargo.lock; instalação de dependências e testes continuam executando.
- Buildx com cache de camadas para a imagem de smoke, carregada localmente, sem publicação. O script exige label com a árvore Git exata antes de reutilizá-la; execução local mantém o build padrão.
- Bases GHCR existentes de PHP 8.1/8.5 consumidas por digest em sete workflows adicionais (14 pernas de matriz), com smoke das ferramentas. Sem alterar as versões PHP. Bases atuais têm exatamente o mesmo hash dos cinco inputs da imagem; não há necessidade de publicar novas imagens.
- O workflow de bases agora compara hashes com os recibos publicados e reutiliza o digest quando são idênticos; recompila quando os inputs mudam ou a publicação manual solicita atualização. O smoke sempre executa, inclusive na reutilização.
- Compilação paralela das mesmas extensões PHP nos workflows que já compilavam extensões; versões de PHP e bancos preservadas.
- Remoção de `registry-plan`, que só fazia checkout e imprimia uma mensagem. Os testes do plano continuam no preflight comum e os oito shards continuam obrigatórios no gate final.
- Tempos por teste de navegador, mantendo a ordem serial porque o fixture é compartilhado.

Foi comparado o inventário de entrypoints `tests/*` dos workflows alterados com o head anterior: nenhum foi removido. Repetições entre bancos/PHPs diferentes permanecem, pois são cobertura diferencial. Não foram alterados limites de tempo para aparentar ganho, filtros para excluir funcionalidades nem checks de falha. Não há previsão numérica de ganho sem uma rodada real com cache frio e outra com cache quente.

## Validação e limites

Executados: 48 testes Python de CI; 19 contratos Node direcionados; 12 cenários PHP do controller com PHP 8.3; testes de provisionamento/isolamento/monitor/inventário; parse de todos os YAMLs e sintaxe shell/JS/PHP. O teste novo reproduz a falha contra o controller anterior. Inventários de superfície e fronteira regenerados pelo gerador oficial, preservando a classificação de prontidão existente.

O smoke completo com navegador/MySQL e as matrizes remotas permanecem necessários no commit final. O teste PHP com doubles prova o comportamento do controller, não a persistência MySQL nem o PDF em Chromium. A melhoria total de duração só pode ser afirmada após essas execuções; resultados antigos não são prova do código novo.

## Primeira rodada enviada e reconciliação

O commit `009972e1` disparou a rodada de 02/11 às 04h26 de Brasília. O workflow das bases GHCR passou reutilizando os digests, assim como PWA, recorrências e versão do núcleo. O inventário `docs/issuer-isolation-inventory.json` ainda tinha o hash anterior do controller; esse gate falhou, e o monitor cancelou as suítes longas. O terceiro inventário foi reconciliado pelo gerador `audit-issuer-isolation.py --write`, sem desbloquear a prontidão operacional. As verificações dos três inventários foram repetidas antes do envio corretivo. Os resultados dessa rodada cancelada não são comprovação de aprovação integral.

Os pulls e smokes locais de ambas as bases PHP 8.1/8.5 passaram com Chromium real. Em cada base passaram também 12 cenários do controller, 4.023 verificações de datas, 33 de idioma, 88 do painel e os quatro testes de persistência. Nenhuma imagem GHCR nova foi necessária ou publicada.

O preflight seguinte também exigiu atualizar os números de linha do censo `native-experience-action-census.json`. Adicionado `bash scripts/ci/check-generated-inventories.sh` para conferir os quatro inventários juntos antes do push; os gates remotos continuam nos workflows originais, sem duplicação adicional no CI. As quatro fases do manifest focado também foram verificadas com `--check`.

## Rodada de diagnóstico `23f25903` e correções seguintes

Na execução [41442097955](https://github.com/wkarts/ARGWS-CRM/actions/runs/41442097955), os oito grupos terminaram seu diagnóstico. A preparação levou 2min30s, com build em 34s usando cache. O teste de dimensão PDF original passou; o grupo vendas avançou até o teste 81. A rodada **reprovou**, e as durações abaixo não comprovam o tempo de uma validação completa aprovada.

| Grupo | Duração do job que falhou | Primeiro teste que falhou |
|---|---:|---:|
| providers | 16min03s | 55 |
| banking | 16min53s | 46 |
| sales-pdf | 24min00s | 81 |
| projects | 27min54s | 33 |
| expenses | 28min09s | 26 |
| tasks-domains | 29min28s | 33 |
| support | 39min19s | 29 |
| settings | 41min08s | 22 |

A execução local com MySQL e Chromium reproduziu causas independentes, antes não alcançadas pelo smoke serial:

- API: o wildcard do módulo legado capturava as rotas de máquina declaradas no núcleo. O router prioriza somente os aliases exatos, preservando autenticação e vizinhos legados. Novo contrato com 47 casos.
- Asaas: faltava carregar a biblioteca de criptografia no contexto isolado. O formulário global de gateways da principal conserva seu renderer legado e a autorização administrativa.
- OAuth: as formas Google/Microsoft da principal agora usam a apresentação compartilhada; callbacks negados limpam material de protocolo por 303 sem trocar códigos, inclusive após revogação. Novo contrato com 21 casos.
- Projetos: restaurado o link da publicação; a projeção do portal lê atribuições nativas ativas com os mesmos gates de equipe/ACL, sem ressuscitar atribuição removida. O tab legado de projetos da equipe principal permanece no renderer original.
- Arquivos de suporte: o status nativo é resolvido pela projeção autoritativa já existente, mantendo o marcador de armazenamento e a recusa de anexos após fechamento.
- Banco do Brasil: corrigido o fechamento extra de um elemento da view, que colocava painéis fora do tab container; a recusa isolada retorna 403 sem chamar o helper legado dependente de ambiente global. Módulo 1.3.6, migration nativa 136 NOOP; sem DDL.
- Fixtures e navegadores: carregamentos transitivos usam require_once; testes usam formulários, CSRF, rotas de preview/PDF e IDs públicos/raw atuais, conservando os cenários financeiros, de retry e de isolamento.

Provas locais já aprovadas: relatório/API real; Google e portal; política de arquivos; publicação e escrita de tarefas A/B; arquivos operacionais API com ticket nativo aberto e rejeição após fechamento; vendas/copiar/converter/PDF; Asaas, ciclo completo pós-devolução, IA A/B/principal e origem/componentes/reversão bancários. Contratos MySQL: 110 checks do leitor de portal, 133 de arquivos, 250 de subrecursos e 228 de tracking bancário até 136. Essas execuções direcionadas não substituem os oito grupos completos do commit final.

Foram retirados sete pré-requisitos desnecessários de settings e a repetição de 33/34 entre projects e tasks-domains. Todos os 101 comandos permanecem no manifest com dono único; matrizes PHP/bancos permanecem. Não há promessa de duração fixa nem declaração de CI integral aprovado.

A recuperação de linhas de vendas (63) e a recuperação operacional (64) foram atribuídas a sales-pdf e projects, respectivamente. Ambas criam seus próprios objetos a partir da preparação comum; não dependem dos outros formulários de settings. O balanceamento distribui trabalho obrigatório sem excluir comandos.

Pusher: a origem privada resolve a atribuição nativa explícita de um único responsável, preservando o marcador armazenado e as atribuições legadas. Remoção, ambiguidade e troca de época vetam o envio; novos casos PDO exercitam esses limites. Carregamentos de classes em fixtures/bootstraps HTTP são idempotentes, sem excluir cenários ou assertivas.

A conferência adicional do faturamento encontrou duas divergências reais: o editor recusava impostos históricos que ele próprio oferecia, e impunha o algoritmo de uma fatura nova às origens emitidas com cálculo histórico. O update agora mantém a origem atestada pelo diário e aceita um par histórico somente na mesma linha própria. Cadastro/catálogo continuam obrigatórios para pares novos; fonte estrangeira, nova linha, taxa/nome inventados e troca de algoritmo são recusados. A criação principal continua no controller legado; somente ações sobre uma linha persistida com issuer_id não NULL usam o leitor próprio. Os 45 casos de seleção de origem e os 12/4 cenários legados preservam essa distinção.

Também foram conciliados os formulários delimitados da principal com seu contexto isolado e restaurada a confirmação de salvamento nativo de Settings, vinculada ao ator/empresa/grupo. Os testes de suporte usam o departamento real do chamado e a ação Salvar do Staff, mantendo a política de acesso por departamento.

O grupo local completo providers concluiu 18/18 comandos, com todos os nove preparatórios e as duas provas TLS originais. Os 14 navegadores do grupo bancário passaram em execuções direcionadas. O caso CNAB400 exige o arquivo privado de rejeição, uma única auditoria vinculada aos bytes e motivo, deduplicação do replay e nenhuma importação parcial ou efeito financeiro; não exige que uma recusa auditável deixe de ser auditada.


A distribuição SQL do Banco do Brasil preserva os 60 argv MySQL e 72 MariaDB da árvore `23f25903`, conferidos por contagem e SHA-256. Cada variante usa quatro serviços de banco independentes; verificador e checkout ficam juntos. Comprovantes de conclusão vinculam banco, shard, commit, run e sequência completa. Os checks obrigatórios originais `mysql` e `mariadb` rejeitam falha/cancelamento/incompletude. Foram aprovados 71 testes Python de CI, incluindo casos negativos do runner/agregador; isso não substitui a execução SQL completa no GitHub.


Reprodução final dos navegadores: marcos 121,8 s; horas 125,9 s; Gantt 158,3 s; arquivos de projetos 80,9 s; preferências de chamados 314,9 s, todos aprovados. Fixtures de projetos explicitam pela GUI as permissões inicialmente privadas, preservando defaults da aplicação e todas as assertivas. No suporte, a repetição local corrigiu um reset temporário com JSON_REMOVE que havia reordenado o nó de funções; essa adaptação permanece fora do repositório e não altera regras da aplicação. Faturamento local de tarefa A comprovou edição55,11/cancelamento5, com interrupção posterior do helper; não se apaga nem reutiliza a origem já convertida para alegar uma execução fresca completa.


Rodada5ca99875: 19 workflows aprovados; a fase MySQL focada falhou em `tests/settings/sales_pdf.php` porque o transporte simulado não fornecia sessão para a confirmação nativa de Settings. A sessão foi acrescentada aos quatro fixtures equivalentes, com assertivas de confirmação e recusa. Os 14 entrypoints MySQL passaram nas execuções locais (PHP8.3/MySQL8.0), e os contratos de Settings passaram em SQLite. Os preflights Chromium8.1/8.5 e o workflow completo de interface haviam passado remotamente; a rodada completa permaneceu reprovada/cancelada, sem ganho global alegado. O runner focado passa a publicar causa e pilha sanitizadas em anotação limitada a4KB.
