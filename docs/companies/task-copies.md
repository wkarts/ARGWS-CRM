# Cópia revisada de tarefa de projeto ou avulsa

`admin/company_task_copies` abre uma revisão de tarefa própria, pelo link na tarefa de projeto ou avulsa. A seleção da empresa e o colaborador vêm do contexto selado. A ação exige acesso atual para consultar e criar tarefas; a modalidade de projeto também exige consultar projetos. Não abre a cópia nativa global, não adota tarefas `NULL` e não aceita um namespace diferente de `project` ou `standalone`.

O operador revisa nome, prazo e responsável explicitamente. Uma tarefa de projeto precisa de um projeto próprio em andamento como destino; o prazo deve caber nas datas atuais desse projeto. Pode selecionar o mesmo projeto ou outro projeto próprio. Uma tarefa avulsa permanece avulsa. Responsável antigo, ausente ou revogado não concede acesso: o operador precisa escolher um responsável atualmente autorizado, inclusive um substituto quando a origem estiver sem responsável.

A nova tarefa começa `todo`, com revisão1 e todos os itens do checklist abertos. Preserva somente o texto da descrição e os textos do checklist, além dos campos explicitamente revisados. Não copia comentários, horas, timers ativos ou encerrados, relações/DAG, marcos, arquivos, seguidores, publicações, contato autor, cliente financeiro, preços, cobranças, claims de faturamento ou concessões. A origem permanece íntegra, mesmo quando concluída, faturada ou em projeto encerrado. Criar essa nova identidade não libera claims ou altera saldo da origem.

`CompanyTaskCopies` usa uma transação independente própria, o mutex de Empresas e os mesmos participantes e validadores dos writers canônicos. `CompanyTasks` e `CompanyStandaloneTasks` expõem um compositor interno que exige a posse da transação e do ledger de cópia; a criação normal mantém seu recibo e comportamento anteriores. Todas as tabelas condicionais de faturamento já instaladas entram na transação, incluindo preço fixo, tarefa, tempo, lotes e tarefa avulsa. Nenhuma chamada de provider ou fila é iniciada.

O snapshot inclui a linha persistida da tarefa e seu projeto de origem, quando aplicável. Mudanças na origem após a revisão vetam a escrita. Projeto de destino, responsável e grants são conferidos sob lock; revogação enquanto dois processos aguardam impede ambos de escrever. O ledger aditivo `company_task_copy_requests` preserva namespace, fonte, ator, empresa, revisão e JSON/hashes da origem, revisão e resultado. IDs de tarefa são polimórficos por namespace; os parents e owners são resolvidos pelo serviço, sem fabricar uma FK entre tabelas diferentes.

A chave da tentativa é estável. A mesma chave e conteúdo retornam a mesma tarefa; conteúdo, origem, empresa, ator ou namespace divergentes não criam outra identidade. Após falha, o link de recibo permite conferir a tentativa antes de iniciar outra. Falha no writer, no ledger ou na auditoria final desfaz a nova tarefa e suas auditorias na mesma transação. O recibo continua exigindo acesso atual ao recurso e ao ator original. As duas proteções CSRF nativas e a proteção dedicada continuam obrigatórias.

Esta fatia libera a GUI selada e o serviço canônico. Não acrescenta scopes de API nem amplia credenciais existentes. A API de criação de tarefas mantém seu contrato anterior.

Testes implementados:

- `tests/companies/task_copies.php`: A/B/principal e os dois namespaces, substituto, origem sem responsável, referências cruzadas, conteúdo divergente, revisão obsoleta, ator restrito, revogação, rollback e retry.
- `task_copies_controller.php`: controller e templates reais com adaptadores somente de I/O do framework; CSRF, recibo, namespaces, métodos e contexto finitos.
- `task_copy_concurrency.php`:11cenários com dois processos/conexões independentes observavelmente aguardando em InnoDB; revogação de ator/responsável, origem alterada e destino encerrado.
- `task_copy_native_billing_participants.php`: writers e composição com todas as migrations436–439 reais e participantes de faturamento presentes; confirma que cópia não altera esses registros.
- `task_copies_http_browser.cjs`: CRM real, GUI A/B/principal e ambos os namespaces, campos revisados, CSRF, falha tardia, retomada, fonte preservada e recusa cruzada. Sua execução é registrada separadamente da prova de controller/PDO.

Instalação, upgrade e replay do ledger fazem parte da migration nativa440 composta no núcleo; o fixture HTTP somente valida o schema existente. SQLite não comprova InnoDB ou locks; as provas MariaDB e HTTP não equivalem a homologação bancária, nem usam transações externas.
