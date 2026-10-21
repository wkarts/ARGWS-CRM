# Preço fixo de projeto concluído por empresa

`admin/company_project_fixed_billing` declara um preço fixo e converte um projeto próprio **concluído**, por inteiro, em uma única fatura. A GUI separa a revisão do preço da emissão. A origem preserva projeto, cliente próprio, tarefas, timers, origem de contato434 quando existente, ator, revisão, moeda/ISO, identidade fiscal, destinatário, configuração, preço, tributos, desconto, ajuste e datas. Não consulta nem adota projetos, tarefas ou faturas legados/NULL. A coluna `invoices.project_id` permanece no namespace legado e não recebe IDs de `company_projects`.

O perfil `project_fixed_completed_v1` exige todas as tarefas concluídas e todos os timers encerrados. Projeto vazio e tarefa sem timer são válidos: preço fixo não inventa horas. Durações e horários originais são preservados; datas ingênuas de timers são identificadas como `native_server_local_naive_duration_seconds_authoritative`, sem alegar UTC original. Limites: 500 tarefas, 500 timers por tarefa e 2 MiB de origem; excesso é recusado, nunca truncado.

A confirmação exige `projects/view+edit`, `tasks/view+edit`, `customers/view` e `invoices/view+create`, ator ativo e cliente ativo **próprio**. Compartilhamento do diretório não transfere o trabalho. Owner e permissões são revalidados sob o mutex de origem, inclusive após auditorias e enquanto concorrentes aguardam. A GUI usa apenas o contexto selado, POST e CSRF nativo e próprio. Métodos adicionais ficam fechados.

Aritmética e emissão reutilizam `CompanyInvoiceAmounts`/`CompanyInvoices::create`, com quantidade 1.00, preço exato, até 10 tributos, desconto fixo/percentual antes/depois de tributos e ajuste com sinal.100.10+5%+2%=107.11; 10% antes de tributos e ajuste−0.01 resultam 96.38. Gera apenas conta a receber e o evento canônico da emissão; não cria recebimento, baixa bancária, crédito ou lançamento fiscal fictício.

## Exclusão, reserva e retomada

As tabelas novas são `company_project_fixed_billing_origins` e `company_project_fixed_billing_links`. `CompanyProjectFixedBillingSchema::ensure` é aditivo e deve rodar **fora** das transações, após schema dos projetos e faturas. Não declara preço nem associa trabalho antigo. A raiz integra a migration oficial; fixtures `ensure/replay` são protótipos e não comprovam execução de uma migration futura.

Estados próprios: `ready`, `converting`, `converted`, `disabled`. Cada projeto tem uma chave canônica SHA256 de `company-project-fixed-invoice:<issuer>:<project>`. Declaração tem chave e digest de empresa/projeto/ator/revisão/input. Reserva é durável; a emissão usa o único writer canônico com guard de origem antes/depois. Falha no vínculo após commit da fatura retoma o recibo original, inclusive após alteração de configurações, sem nova numeração ou segunda fatura. Origem, payload, contexto e seus bytes/hashes são conferidos; falhas de auditoria e mutações tardias revertem a transação correspondente.

Uma declaração `ready` ainda não reserva trabalho. Tarefa com declaração própria `ready`/`converting`/`converted`, ou com recibo canônico anterior mesmo sem vínculo, impede a cobrança integral. Retire explicitamente quotes não convertidas. Depois que o projeto reserva, o fence compartilhado bloqueia preparação e alteração financeira das tarefas. Duas conversões do projeto retornam a mesma fatura; tarefa versus projeto tem somente um vencedor sob o mutex. Não há somatório residual ou marcação silenciosa de tarefas antigas como faturadas.

Origens434 existentes passam juntas de `unbilled` para `converting` e `billed`. Retirada pelo mesmo ator é permitida somente sem recibo canônico, restaura `unbilled` e preserva histórico/autoria. Retirar uma quote de projeto que nunca reservou **não** altera o estado de uma tarefa concorrente. Tarefas de staff/antigas sem origem de contato continuam sem essa origem. Cancelar/editar a fatura não desfatura automaticamente o trabalho.

## Evidência e limites reais

Testes próprios executam serviços, controller e views reais, TCPDF, rollback por SQL/auditoria, integridade dos bytes mesmo com hash recalculado, exclusão tarefa/projeto, A/B/principal/NULL e concorrentes reais InnoDB com dois processos observados aguardando. O browser copia explicitamente a fixture completa e `BbPdoDatabase` para um runtime sem testes, e percorre HTTP/Chromium, CSRF, revisão, emissão, PDF, replay, escopo e recuperação póscommit.

Este perfil é local e não comprova homologação de banco ou execução remota. Permanecem como **código interno ainda pendente** os perfis de lote de várias tarefas em uma fatura, projeto-hora em linha única, faturamento parcial de timers e tarefa avulsa; este incremento não os declara entregues. O projeto por taxa horária e a tarefa por hora/por timer dos incrementos anteriores permanecem disponíveis com seus próprios contratos.
