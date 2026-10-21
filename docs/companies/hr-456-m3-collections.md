# RH456 — M3: conjuntos originais, elegibilidade e DTO coerente

M3 corrige três riscos reproduzidos contra o candidato anterior. Preserva os checkpoints M1 e M2, a identidade global, as 18 tabelas RH e os 12 deltas globais separados. O schema continua com SHA256 `de0feda4726b336722201f2cbc30d8a105bec76b3c3fe6d8775a1fa5f05f1ef6`.

## Reproduções RED

O teste `tests/companies/hr_api_phantoms.php` primeiro criou estrutura, cargo e vínculo pelos writers reais, preservando os recibos e a auditoria de origem. A fixture retirou apenas essas linhas de domínio; um trigger SQL `AFTER INSERT` da auditoria API as restaurou depois de a lista ou o relatório já ter sido montado. Isso permitiu comprovar a omissão de uma linha elegível com prova de origem válida, sem depender de um JSON arbitrariamente forjado.

No M2, `hr_employees` retornou uma pessoa enquanto o banco já continha duas. O relatório `roster` fez o mesmo. A chamada foi aceita e confirmou o efeito do trigger, a auditoria e o uso do token. O RED de lista foi registrado no MariaDB/PHP8.3 e o de relatório no MySQL8/PHP8.1. O terceiro caso comprovou `mixed_chart_reference=true`: `hr_structure` retornou um organograma com vínculo apontando para uma unidade ausente da estrutura retornada, pois as duas partes eram lidas em transações diferentes.

A primeira correção de testemunho de conjunto passou nos triggers, mas um teste adicional em duas conexões reais demonstrou que uma releitura simples sob `REPEATABLE READ` podia continuar vendo o snapshot antigo. A conexão secundária ativou um vínculo anteriormente excluído, enquanto a API ainda retornava a lista sem ele. Esse RED também foi preservado. A versão final usa leituras correntes com `FOR UPDATE` e bloqueia explicitamente a identidade/vínculo dos candidatos excluídos. A prova final registra conexões distintas, tentativa externa bloqueada com timeout1205, estado `can_view=0` preservado e retorno coerente até o commit.

## Comportamento da versão final

`observedRows()` registra a primeira seleção exata — SQL, parâmetros e digest canônico de seus resultados — inclusive seleção vazia e linha adicional de paginação. Antes do commit, a operação repete a mesma consulta como leitura corrente e exige igualdade. Uma leitura subsequente não pode substituir silenciosamente o baseline. Apenas um writer que declara explicitamente o conjunto planejado pode substituí-lo: a inclusão da própria tentativa de treinamento e a soma/count planejados do upload de arquivo têm confirmação específica.

Os testemunhos abrangem estrutura, organograma, pessoas, candidatos a vínculo, orientações, cursos, atribuições, tentativas, registros privados, contratos, acessos de participante, arquivos e avisos próprios; incluem as coleções internas de relatórios, materiais capturados de curso, revisões de importação e predicados de referência sem dependentes. Relatórios registram o predicado de vínculo tanto das pessoas incluídas quanto das excluídas. A projeção de questões do curso registra a capacidade opcional `hr_training.configure`, cobrindo concessão e revogação durante a leitura.

`structureSnapshot()` monta estrutura e organograma no mesmo envelope owned. A API e a GUI usam esse envelope; o retorno API confirma exatamente uma auditoria de uso, com os conjuntos e linhas das duas partes submetidos à mesma confirmação final. As operações isoladas `structure()` e `orgChart()` permanecem disponíveis para seus usos próprios.

## Provas finais executadas

| Suíte | Maria10.11.14 / PHP8.3.6 | MySQL8.0.46 / PHP8.1.2 |
|---|---:|---:|
| `hr.php` | 64 | 64 |
| `hr_training.php` | 54 | 54 |
| `hr_integrity.php` | 30 | 30 |
| `hr_api.php` | 82 | 82 |
| `hr_concurrency.php` | 58 | 58 |
| `hr_api_phantoms.php` | 39 | 39 |
| `hr_api_read_locks.php` | 2 | 2 |
| `hr_bootstrap.php` | 296 cumulativos | 296 cumulativos |
| `hr_access_scope.php` | 43 | 43 |

Cada runtime operou com128MiB. As cinco suítes de domínio anteriores mantêm seus288 checks; as duas novas acrescentam41. O bootstrap inclui80 checks preservados das entregas anteriores e216 de RH. Foram executados668 checks por runtime nesse conjunto, sendo588 novos em relação às80 verificações herdadas. Todos os testes e fontes foram conferidos pelo manifesto antes e depois da execução; nenhum mudou durante a rodada final.

O browser completo passou novamente em A, B e principal, cobrindo os12 grupos do candidato: estrutura/vínculos/perfil privado, CSRF/replay/revisão, registros, arquivos e PDF, ciência de participante, treinamento100% e material, avisos, importação e relatórios, portal próprio, API e origem revogada, preservação e layout móvel. Não houve erro JavaScript. O helper de teste percorreu o cursor finito e comprovou o aviso específico, conforme a correção M2; o worker de produção não foi substituído.

O manifesto final é `802eebe7e7d73e1ee063cda64e01c4df742596f2efd7e672a2a3a7b192588843`, com digest de árvore candidata `92c99ee3bc536ec642e4c00c1e5159d71b3b669ac24e4199b1f4edbe3526e5b0`. O código próprio contém34 paths, incluindo os dois testes novos de M3. Deltas, fontes, logs RED/GREEN e prova HTTP estão em `docs/companies/evidence/hr-456-m3-collections.json` e no diretório correspondente.

## Limites e integração

Esta é uma candidata validada sobre o HEAD453 publicado. A fixture HTTP tem instalação nativa453 mais schema RH explicitamente instalado, e continua sem os dois deltas globais candidatos de rotas e worker completo. A prova HTTP não substitui upgrade nativo456 nem teste da composição final. Não houve commit, publicação de branch, merge ou deploy por esta frente. Os globais, a migração456 e os gates integrados permanecem sob responsabilidade da composição coordenada.

O inventário de remanescentes mantém folha, jornada/ponto, metas e demais ações não cobertas como trabalho interno. A candidata RH não os anuncia como concluídos. A frente `CompanyWorktime*` permanece separada e não faz parte deste pacote RH456.
