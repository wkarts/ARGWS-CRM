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
