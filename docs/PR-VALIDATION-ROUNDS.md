# Rodadas de validação de PR

- Um commit novo cancela a rodada antiga da mesma PR por concurrency, sem cancelar releases/pushes de produção.
- Matrizes de PR usam fail-fast. Fora de PR mantêm a coleta completa de resultados.
- O workflow `PR validation round` cancela validações pendentes/em execução da mesma PR/SHA quando detecta falha real, timeout ou erro de inicialização. Preserva logs e o resultado reprovado. Não transforma cancelamento em aprovação.
- Apenas PRs originadas no próprio repositório. Número da PR, repositório de origem, evento e SHA são conferidos. O HEAD é reconferido antes de cancelar. Não há tokens persistentes novos: o job usa GITHUB_TOKEN efêmero com Actions write e leitura de conteúdo/PR; os testes continuam com suas permissões anteriores.
- Monitoramento a cada30segundos; até4 workflows em execução inspecionados por ciclo para observar falha de job antes do workflow terminar. Há limite45minutos, sem alegar sucesso se excedido. Agendamento/latência do GitHub pode acrescentar espera.
- A correção é analisada e publicada na mesma branch; o novo commit inicia a próxima rodada. O monitor não escreve código, não cria commits vazios e não reexecuta automaticamente um SHA conhecido como reprovado.
- Cancelamento de rodadas anteriores já iniciadas antes desta política pode exigir intervenção inicial; depois o novo monitor reconcilia execuções obsoletas da mesma PR.
- Testes locais: `python3 -m unittest tests/test_pr_validation_round.py`; `actionlint`.

Se uma execução continuar ativa após cancelamento normal (por exemplo, jobs com always()), o monitor reconfirma PR/repositório/HEAD e usa force-cancel exclusivamente naquela execução elegível. Isso libera o slot sem remover logs ou artefatos já gravados.
