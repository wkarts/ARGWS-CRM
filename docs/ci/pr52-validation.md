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

Atualização às20:49:38UTC de02/11: as nove consultas diárias foram repetidas; total7.770, com550 em02/11 e as oito contagens anteriores iguais. A coleta original estruturada permanece identificada pelo horário de07:19UTC.

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

Para reduzir o caminho serial, o navegador91 executa quatro pares por empresa nos grupos próprios timers-a, timers-b e timers-primary. A suíte direta continua completa sem argumentos. As12 combinações executam uma vez; CSRF, recusa entre empresas e formulário antigo permanecem na parte A. O plano exige as três partes completas e em grupos distintos, e a preparação real do leitor privado foi exercitada emBash para cada grupo. São103 invocações para a cobertura original de101. As três stacks adicionais repetem a preparação comum para conservar o isolamento, sem repetir calendário/preferências; o limite longo permanece20runners: núcleo4, Banco do Brasil1 por variante, focada2, HTTP11 e monitor1. Tooling termina antes deHTTP. O baseline de recusa é posterior à criação intencional da tarefa B; a prova stale altera a política efetivamente, pois o save idêntico não muda a revisão.

Na rodada04f1173a, a suíte focada completa PHP8.1/8.5 passou. Dois shards MySQL do núcleo chegaram aos controllers de suporte e revelaram um limite do double de loader: view exigia o parâmetro de dados, opcional no CodeIgniter. O double passa a herdar as variáveis da view principal e a renderizar o novo partial real. Passaram os três entrypoints de controllers:110/126/125 checks SQLite e112/128/127 MySQL. A verificação nova exige os dois formulários internos com CSRF nativo e dedicado. O monitor deixou os quatro workflows de diagnóstico terminar após a reprovação.

A lista adicional de participantes do timer é preparada uma vez por operação e descartada com nativeTimerContext em finally; não há cache de permissões, linhas ou resultados de esquema. Cada fonte afetada continua verificando seu próprio esquema/referências/autoridade. A regressão reutiliza os mesmos objetos de writer depois de instalar os marcos, para comprovar que a declaração não sobrevive a outra operação. Timers, controllers, apontamento manual e ações de equipe passaram emSQLite/MySQL após esse ajuste.

Os98 comandos MySQL que o shard3 ainda não tinha alcançado passaram localmente na ordem original após a correção do loader. Incluem os258 checks do ciclo de estorno manual e os consumidores posteriores atéRH; não foram descartados ao encontrar a primeira falha. Os preflights Node e Chromium8.1/8.5 agora executam em paralelo, com o mesmo gate exigindo os três resultados antes dos bancos. Passaram80 contratos Python, inclusive ausência/falha de Node com navegadores aprovados ou ainda executando.

A parte positivaA completou os quatro pares antes de um timeout30s na navegação redundante para o dashboard, após1814,3s de execução local. A preparação agora usa Settings para trocar de empresa, sem modal sobre o seletor nem retorno a URL de recurso estrangeiro. As recusas são verificadas em continuação dirigida usando a origem já criada, sem reset/exclusão de timers. Isso não é declarado como uma execução local única de toda a parteA; a rodada remota final continua necessária. Nos jobs bancários completos da rodada04, as durações somadas dos quatro shards foram37,58min MySQL e28,77min MariaDB; são medições para orientar o orçamento, não duração global aprovada.

A continuação negativaA passou em352,9s: CSRF, empresa estrangeira e política antiga recusados; snapshots completos iguais após as recusas, finanças/opções intactas e origem original encerrada pela GUI. Uma criação repetida na primeira continuação foi identificada e somente o timer excedente foi encerrado pela GUI; as duas linhas e seus históricos foram preservados. A evidência local é composta, não uma execução integral única.

Os demais grupos da rodada04 concluíram cinco diagnósticos positivos (despesas, Settings, vendas, provedores e banco) e revelaram duas causas posteriores. O faturamento39 retornava200 em `/tasks/billing/unsupported`; o controller agora recusa segmentos extras com404 antes de hidratar, inclusive emPOST. A preferência83 detectou que o formulário de Staff criava `native_staff_roles` vazio ao salvar um membro já sem papel. Essa escolha agora é idempotente, sem remover ou filtrar campos do snapshot. As duas regressões falham antes da correção e passam depois emSQLite. Gestão de equipe61 checks MySQL, papéis13 checks SQLite e os endpoints de papéis passaram; o faturamento acrescentou três recusas/invariantes aos102 checks existentes emSQLite/MySQL. A revogação de autoridade durante a transação continua impedindo o no-op e reverte todos os bytes.


O navegador39 completo passou em249,7s após a recusa de sufixos: A/B/principal, edição/cancelamento/retry, PDF, SQL503/recuperação, origem stale e NULL/caixa preservados. Como o banco local já continha conversões, a repetição usou rótulos e chaves sintéticos novos no bootstrap privado, com as mesmas assertivas e sem excluir dados. Os consumidores de apontamento e faturamento fixo passaram emSQLite/MySQL. As provas locais verificam as fontes diretamente envolvidas; outras fontes dos containers locais podem ser anteriores. A rodada remota final deve usar uma imagem nova da árvore completa.


O navegador83 completo passou em167,3s: GUI A/B/principal, defaults/ordem de respostas, publicação individual e mensagens privadas, falha tardia observada/rollback, contexto/revisão409, ator exclusivoB e revogação atual403, snapshots financeiros e todos os settings alheios preservados. O papel vazio é escolhido depois de abrir o tab Permissões original. A repetição preparou apenas a preferência própria da principal pela GUI; não houve reset de JSON nem remoção de campos dos snapshots.


Rodada43bd9167: o grupo completo de vendas/PDF passou em26min54s, incluindo os erros originais de destino da fatura e dimensão PDF. TemporizadoresA passaram completos em27min14s; suporte e temporizadores da principal também passaram. Settings e provedores passaram, assim como os quatro shards PHP8.1. Banco do Brasil completo passou em52min29s. A rodada reprovou depois no projeto41: a rota billing_rates aceitava um sufixo desconhecido e retornava200. Os entrypoints billing_rates/fixed_billing/time_billing e time_batches passam a recusar sufixos com404 antes de hidratar, emGET/POST. Dez novos checks preservam os bytes das faturas; as regressões falham antes e passam depois emSQLite/MySQL.

A mesma rodada mostrou shards próximos de28min, sob o limite de30. O núcleo passa a cinco shards de94–95 comandos por versão e até cinco workers simultâneos, sem aumentar o timeout. A matriz focada conserva os dois jobs/checks, executando um por vez; o orçamento longo permanece20 (núcleo5+bancos2+focada1+HTTP11+monitor1). O manifest conserva seus472 argv e SHA-256. O novo plano reproduz os mesmos tempos pareados em cinco capacidades, sem inventar amostras para o comando não medido; baseline/oito ZIPs/944 observações e reprodução histórica de quatro permanecem intactos. Os81 contratos Python passaram, inclusive cobertura única, capacidades não divisíveis e recusa de metadados de partição ausentes/incorretos. A rodada final ainda é necessária.


O diagnóstico remoto43bd9167 concluiu o núcleo inteiro PHP8.1/8.5 em59min52s e o Banco do Brasil em52min29s. O workflow HTTP reprovou em1h1min56s; não é duração de validação integral aprovada. O teste41 completo passou localmente em77,4s após a correção, e42 passou em121,9s (A/B/principal, PDF, retry, falha tardia/recuperação, stale e finanças intactas).

O navegador90 é independente do89: cria seus clientes/projetos/tarefas pela GUI, aceita a política própria inicial e usa um leitor estritamente read-only. Foi movido para sales-pdf, com shim/leitor preparados nesse worker e sem repetir84–89. O seletor usa Settings/DOMContentLoaded; as assertivas e as três empresas permanecem. Os82 contratos deCI e24 de ambiente/provisionamento passaram. A duração e o exit de cada invocação também são publicados no resumo do job HTTP, além do TSV existente. A nova distribuição precisa concluir no commit final.


A execução local completa90 após a redistribuição encontrou um erro JavaScript latente nos nomes de seguidores: um membro sintético com `<script>literal</script>` entrava como HTML no helper render_select. A view agora escapa somente a cópia dos rótulos; IDs, seleção, projeção e armazenamento ficam intactos. O renderer real reproduz o erro antes e passa nos14 checks depois. A assertiva errors[] do navegador não foi removida nem filtrada; o cenário completo foi repetido com os mesmos nomes.


O diagnóstico curto identificou que os cinco erros JavaScript restantes eram emitidos pelo perfil do cliente, no seletor customer_admins[]. O mesmo helper recebia nomes raw de funcionários como `Exclusive <script>literal</script> Staff453`. A correção copia e escapa somente os rótulos desse seletor, mantendo IDs/seleção/projeção. Três novos checks do renderer real reproduzem o erro antes e passam emSQLite/MySQL depois; a persistência literal e o replay do cliente permanecem aprovados. O seletor de seguidores tinha o mesmo defeito de saída, independentemente dessa origem; ambas as views foram corrigidas. O navegador90 conserva errors[] estrito e registra o pathname da página quando há erro, sem query strings ou credenciais.


O navegador90 completo passou em295,7s com A/B/principal e os mesmos nomes de funcionários que antes geravam cinco erros. Permaneceram todas as assertivas de OFF/ON, projeto/avulsa, recibo único, stop preservando estado, CSRF/revisão/contexto e snapshots financeiros/opções. Não houve reset de dados ou filtro de errors[]. O cliente teve três novos checks de rótulo/ID/projeção emSQLite/MySQL, e os consumidores de seguidores/defaults também passaram nos dois bancos.


Cada worker HTTP tem limite explícito de60 minutos para impedir navegador/processo preso consumindo o padrão de seis horas do GitHub. Timeout continua reprovando o check obrigatório; nenhuma cobertura incompleta passa. O limite não representa a duração esperada nem é utilizado como comprovação de ganho.


## Diagnóstico3a4c25e0 e recuperação de horas

A rodada iniciada02/11/2026 às23:53:31UTC aprovou as duas versões PHP do núcleo (47min), o Banco do Brasil nas duas variantes (43min24s) e o foco nativo (26min32s). O grupo de PDFs passou, incluindo as dimensões reais. A rodada ainda reprovou no navegador64, que esperava50.00 mesmo com mais de1800 segundos registrados pelo timer real. A expectativa agora usa os segundos imutáveis e a regra documentada de horas a duas casas; mantém comparação exata, claims individuais, replay, cancelamento, isolamento e ausência de pagamentos. O caso1836 segundos exige51.00.

A inspeção do cenário73 revelou ainda uma variável de arquivo sem definição: a preparação agora cria o anexo e o seguidor pela GUI e confere ambos na origem antes da prova de não adoção pela cópia. Os casos64,72 e73 são executados localmente por inteiro antes do próximo envio. Preferências83 e89 e recuperação64 foram redistribuídas entre workers existentes, preservando cobertura e preparação de leitores. Ganho global permanece pendente de rodada completa aprovada.

Auditoria externa atualizada03/11/2026 às00:05:16UTC:7.818 registros de workflows na branch, desde25/10/2026 às19:43:59UTC. Contagem de registros de workflow, não testes únicos, reruns ou horas faturadas. A evidência original acima conserva sua data de coleta.


Provas locais do ajuste seguinte:64 completo passou em404,8s e89 em293,4s emChromium/MySQL. Os segundos fechados1818/1820 exigiram51.00;1817 exigiu50.00. Ambos mantêm errors[] vazio e todos os checks financeiros/contexto. O89 passou em banco diferente do64, sem preparação de calendário. Os83 contratos deCI passaram. A rodada3a terminou com22 workflows aprovados e2 reprovados (HTTP e monitor);10/11 gruposHTTP passaram. Suporte terminou em60min15s e tarefas em59min53s no worker, justificando redistribuir os cenários independentes. A duração publicada do workflowHTTP reprovado foi1h09min34s; não representa validação completa aprovada.


No cenário72, a execução local revelou que os responsáveis das tarefas não podem ser presumidos a partir do campo legado: o teste agora seleciona responsáveis autorizados no formulário de revisão antes de capturar o POST. O84 foi deslocado integralmente para banking; não fornece origens a Calendar, que mantém33/34/85/86. Nenhuma validação foi suprimida.


Cópias de projetos72: execução integral Chromium/MySQL aprovada em842,0s, nas três empresas, com interface/API, seleção explícita autorizada, CSRF403, rollback503 e retry idempotente, isolamento401/403, ausência de adoção de arquivos/tempo/finanças e origem imutável. Cópia individual73 também normaliza o ID esperado de projeto para número antes da comparação estrita, coerente com o contrato numérico do leitor; a variável do arquivo e sua preparação pela GUI permanecem corrigidas.


A continuação73 chegou à principal e encontrou auto-follow ativado pelo teste89 no banco local: a nova cópia corretamente recebe um seguidor novo pela política atual. Para provar não adoção dos seguidores antigos com contagem exata zero,73 estabelece auto-follow0 pela GUI de Settings de cada empresa, segue explicitamente apenas a origem e confere origem1/cópia0. A comparação de todas as relações permanece estrita; nenhuma alteração no comportamento de produção ou SQL de configuração foi necessária. A prova completa é repetida com esse pré-requisito explícito.


Cópia individual73: execução integral Chromium/MySQL aprovada em754,4s após o pré-requisito explícito de auto-follow, nas três empresas e nos dois namespaces, com origem/arquivo/seguidor reais, target numérico, zero adoção de relações, CSRF403, rollback503+retry, replay, ownership403, bytes da origem e todas as fotografias financeiras preservados. Os quatro alvos64/72/73/89 passaram integralmente antes do envio seguinte. Os marcadores por empresa/namespace nos logs não modificam suas assertivas.
