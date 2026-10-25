# Staff timesheets — checkpoint de engenharia, não admissão de rota

O formulário/controller `Staff::timesheets` ainda NÃO está conectado ao novo reader. Este checkpoint preserva trabalho testado para composição posterior, sem publicação, merge, deploy ou alteração de produção.

## Implementado e verificado localmente

- `reports.view-timesheets` é uma capability explícita, sem concessão automática de grants de equipe, tarefas ou remuneração.
- `NativeCompanyStaffTimesheets::report()` consulta nota/duração/fontes sem selecionar `rate_snapshot_json`; `reportOwn()` preserva a autoridade pessoal e remove dados salariais da resposta.
- `NativeCompanyTimesheetReportAuthority` enumera colaboradores somente a partir de timers reais da empresa, com vínculo/identidade revalidados.
- `NativeCompanyStaffTimeReport` prepara dados do relatório detalhado com período, colaborador, cliente de projeto e projeto canônico, tags reais por fonte timer e status canônico. Exclui timers sem tarefa como o relatório original. Não é endpoint HTTP.
- `NativeCompanyStaffTimeChart` prepara o mesmo contrato de gráfico/footer, com arredondamento compartilhado, semanas terminando no domingo e rótulos correspondentes ao número real de dias do mês.
- `NativeCompanyTimerTagReference` e os adapters de tags usam as tabelas existentes e distinguem timer de projeto, timer standalone e tags da tarefa. Escrita de tags de timer permanece explicitamente recusada.

Provas: PHP 8.4/PDO SQLite reais; report authority 8 checks, roster 4, tags 7, dataset 8, chart 5, arredondamento puro 363, além das suites precursoras. Não equivalem a MySQL/MariaDB, HTTP real ou navegador.

## Pendências que impedem declarar Staff completo

1. Ligar `Staff::timesheets` e a view/tabela originais, incluindo DataTables, agrupamento, filtros, Chart.js e links com autoridade real. Não habilitar rota antes da composição.
2. Completar relação de tarefas standalone a tickets: o reader antigo de equipe não projeta `native_fields_json`; ausência histórica não é prova de ausência de relação. Preservar departamento/issuer antes de qualquer label. O filtro de cliente atualmente cobre somente o vínculo de projeto comprovado.
3. `Tasks::update_timesheet` e edição/exclusão de timesheets não estão implementados aqui. As capabilities nativas `edit_timesheet`/`edit_own_timesheet` ainda não foram adicionadas; não substituir por `tasks.edit`. Writers precisam CAS, origem exata, transação e fences financeiros canônicos. Não editar nem apagar comprovantes de faturamento.
4. Deleção de colaborador com transferência operacional ainda não está adaptada. Não substituir exclusão por revogação de vínculo; preservar identidade compartilhada e recusas financeiras legítimas.
5. Revalidar filtros/seletores de clientes/projetos para report-only sem conceder CRUD ou permitir consulta global. Não usar listas vazias fictícias.
6. Executar prova HTTP/browser da composição e matriz MySQL/MariaDB. Não afirmar homologação por testes SQLite.

O código não publica novas rotas nem enfraquece a admissão central. O principal continua no caminho histórico comprovado; os novos readers recusam principal em vez de omitir silenciosamente histórico.
