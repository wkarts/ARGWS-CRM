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


Rodada6829ea87: 19 workflows aprovados; o worker registry-shards(8.5,4) falhou em project_portal_rules_concurrency.php. Reprodução MySQL identificou uma atribuição sintética A não restaurada pelo novo caso do leitor, que alterava a verificação atômica posterior. O caso passa a preservar/restaurar as linhas e o responsável original, com provas de igualdade; a prova atômica/concorrrente passou em MySQL e SQLite. O SQLite do fixture usa a coluna nullable atual. O adaptador da fila de e-mail também passa a expor a conexão PDO real, preservando consulta ao catálogo e todas as assertivas de envio/isolamento. A matriz desse workflow conclui os diagnósticos do mesmo head, continua reprovando falhas e mantém os checks obrigatórios. Concorrência longa é limitada para evitar fila dos navegadores.


Última verificação local: todos os118 argv selecionados do shard4 foram cobertos em PHP8.3/MySQL8.0, combinando execução inicial e continuações explícitas, sem fingir um receipt de CI fresco. A fase SQLite completa aprovou443 execuções/199 inclusões. RH99/100 passou (163,7s/66,5s; tokens221 checks). Compras96/97/98 passou (140,1s/1,0s/1,2s), incluindo portal, cadeia financeira, link real da despesa tipada, API sem sessão e revogação. O portal/API de compras passou a reconhecer os dois aliases declarados depois da resolução do controller pelo CI; URI original e vetos continuam exigidos (59 checks). O link antigo usava ID físico e falhava403; a view agora usa NativeCompanyResourceId. A fonte de tokens RH atesta Settings/NativeCompanySpecializedOperations atuais em lugar do controller aposentado.


Rodada2151777b (02/11/2026):21 workflows aprovados; suíte focada PHP8.1/8.5 completa em12min36s e Banco do Brasil em27min28s. HTTP completo: vendas/PDF16min12s, settings33min06s, provedores33min22s e banco37min48s. O workflow HTTP terminou reprovado em53min47s: despesas43/MIME, projetos39/mesclagem da principal, suporte30/rota antiga e tarefas84/URL de cliente antiga. Núcleo PHP8.1 shard3 falhou no mock URI dos dois estornos; outros três shards8.1 e um8.5 passaram antes do cancelamento. Não é prova de duração integral aprovada.

O monitor agora inspeciona também matrizes reportadas como queued e preserva os quatro workflows de diagnóstico do mesmo head, mantendo a reprovação. Treze testes do monitor incluem a descoberta de filho falho em matriz queued. Os dois mocks de estorno passaram emSQLite/MySQL com novas recusas404, sem flexibilizar a proteção de produção.

A continuação encontrou e corrigiu problemas que a primeira falha escondia: MIME e entrada de GUI da importação, recusa de sufixo, mesclagem secundária no editor próprio da principal, seleção de despesas próprias e o atendimento staff de chamados de contato com origem persistida. Os testes de suporte mantêm autoria, mensagens privadas/publicação, revogação e fechamento; a nova transação recusa histórico sem origem e reverte falha tardia de recibo.

Provas locais adicionais: importação43 A/B/principal completa52,9s; suporte30 completo295,7s; recorrência45 completa93,7s; API77 A/B/principal/restrito completa46,8s; cópias78 A/B/principal completas56,1s. São execuções direcionadas emPHP8.3/MySQL8.0/Chromium140, não substituem a rodada remota do commit final. O teste do editor próprio passou239 checks SQLite e235 MySQL, com NULL/estrangeiro/mesclagem ainda negados. Importação/controller118 SQLite/121 MySQL; suporte staff com rollback136 SQLite/139 MySQL.

Continuação local: suporte31 completo111,3s e contrato32 completo88,7s. Recorrência92 completou as seis combinações A/B/principal × projeto/avulsa em419,7s. A recorrência agora lê a atribuição nativa ativa em transação própria, congela o responsável e recusa revogação/ambiguidade;128 novas assertivas/164 cumulativas MySQL. Calendário prepara a fatura própria da principal por faturamento de tarefa na GUI existente; o formulário manual principal conserva seu contrato legado. A prova de equipe escolhe explicitamente o papel vazio e a associação/permissões da principal na tela original. Provas integrais desses últimos navegadores ainda pendentes.

Equipe93 concluiu Chromium A/B/principal em113,8s, com revisão stale409, CSRF aplicável, vínculo compartilhado, revogação, identidade mestre preservada e geometria móvel após fontes/layout. Leads94 concluiu97,1s e95 concluiu2,3s/58 checks bearer sem sessão. A GUI original de leads navega por sua âncora visível, e nomes de configuração são únicos por execução. A falha de calendário principal foi reproduzida como native_calendar_google_invalid: departamentos legados com calendar_id SQL NULL significam ausência de calendário, sem alteração de armazenamento; quatro novos checks conferem isolamento, página/confirm, NULL imutável e rejeição de valor não vazio inválido. O controller registra somente o código de recusa delimitado. Limites de calendário variam a partir do estado inicial para garantir mudança real nas provas de rollback/revisão.

Verificação de compatibilidade: o caminho antigo de cópia de despesa da principal com ID físico na URI continua legado. O protocolo próprio usa seletor explícito ou source_expense_id no POST; campos próprios malformados chegam ao writer delimitado para recusa e não caem no modelo legado. O roteamento passou45 casos de fatura e48 de despesa; o controller de cópias passou154 checks SQLite e o navegador78 completo passou62,9s. O seletor de atribuição em lote de Tarefas passa a escapar nomes de funcionários antes do helper que aceita HTML; a regressão de equipe93 completa passou97,4s e o portal34 completo passou122,7s sem erros JavaScript. O calendário86 de tarefas sem responsável passou58,2s com preparação equivalente à85 pela GUI antes do snapshot imutável. As execuções locais continuam separadas da comprovação remota final.

O navegador90 de temporizadores passou integralmente em467,8s. Os testes90/91 agora salvam explicitamente o estado inicial A fazer; o teste84 pode deixar a preferência Em andamento. A modalidade avulsa do91 usa o namespace standalone3, diferente de project-task2, e IDs físicos iguais em tabelas distintas não compartilham um mapa. A criação usa os formulários originais em uma segunda aba, reaproveita somente a página do projeto/empresa correta e fecha o modal pelo botão nativo. Os12 pares A/B/principal × modalidades e todas as verificações financeiras/CSRF/stale permanecem.

Os dois servidores HTTP do smoke habilitam o OPcache já compilado na imagem, com validate_timestamps=1 e revalidate_freq=0. Uma prova HTTP isolada confirmou cache ativo, compilação e atualização imediata de fonte alterada com o mesmo tamanho, sem tocar na aplicação ou no banco. Passaram24 contratos Python de provisionamento/ambiente e45 contratos Node; a suíte de seletores/reuso passou15 casos após a correção de fechamento do modal. Isso comprova a configuração e a atualização de fonte; o ganho global ainda depende da rodada remota completa.

A continuação91 chegou ao segundo par e reproduziu500 na parada automática projeto→avulsa: issuer_owned_transaction_required. A transação avulsa não incluía participantes exclusivos do writer de projeto, como marcos. A composição usa listas dos dois writers sem contexto ativo, evitando recursão e conservando a verificação de propriedade, engine e esquema. O teste rápido novo falha antes da correção com o mesmo código e passa depois:108 checks cumulativos SQLite e96 MySQL. Os24 checks novos cobrem quatro pares, falha tardia/rollback dos dois históricos, recuperação, único instante de stop/start, estado e tarifa anteriores preservados. Esses checks não substituem o navegador91 completo do commit final.

A recuperação Chromium da operação original também passou usando as mesmas tarefas persistidas, sem apagar histórico: um novo timer, instante compartilhado, estado anterior/tarifa preservados e stop explícito pela GUI. Os snapshots financeiros e de preferências permaneceram iguais. Os três consumidores do fixture (controller de tracking, apontamento manual e ações de equipe) passaram emSQLite/MySQL; a preparação continua deixando o timer avulso original ativo para seus consumidores.
