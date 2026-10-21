# PR45 — equipe, integridade de Settings e recorrência operacional (453)

## Escopo e vínculo com o histórico

O incremento parte do HEAD remoto `543ff96fe2a49ceef48d8595a58e8400befc6266`, árvore `8690b0a058282369db3d3a9c2448d2d6d0aaf6a6`, na mesma branch `feat/banco-brasil-billing-cnab240`. O núcleo passa a **4.5.3 / migration 453** pelo gerador canônico de versões. Banco do Brasil 1.2.3/123 e Asaas 1.5.3/153 não recebem incremento artificial. Não há merge, deploy, tag, force-push ou operação bancária real.

O documento integral de continuidade está em `continuity-contract-2026-10-21.md`; a descrição remota anterior foi preservada em `evidence/pr45-description-452.md`. Migrations publicadas e entregas anteriores são mantidas. Este relatório cobre um incremento da sequência do documento, não o encerramento de seu contrato integral.

Houve perda do executor anterior. O commit local 453 mencionado naquele trabalho (`fd91e89c4e572d5ba1cc911d99188bb602313a17`) e os antigos trabalhos locais de módulos não foram recuperados. As fontes deste incremento foram reconstruídas sobre o HEAD 452 confirmado e possuem novas execuções de teste. Nenhum resultado antigo 453 foi atribuído a estes bytes. Checkpoints intermediários foram preservados separadamente; não foram apresentados como commits publicados.

## Entregas positivas

| Área | Comportamento entregue | Limite explícito |
| --- | --- | --- |
| Equipe própria | Listar, criar identidade sem administração global, vincular identidade existente, editar perfil elegível/permissões e revogar somente o vínculo da empresa atual; GUI com CSRF/contexto/revisão/recibos. | Compartilhar identidade não autoriza um gerente a reescrever o perfil global. Não abre CRUDs legados universais de módulos. |
| Staff nativo | Perfil, departamentos, permissões nativas, campos personalizados, anúncios, vínculos, permissões por empresa, preferência e auditoria confirmados na mesma transação. | Hooks de escrita terceiros sem participantes revisados são recusados. Avatar, e-mail e callbacks posteriores ao commit têm aviso próprio, sem falsa confirmação parcial de ACL. |
| Settings | Reconferência final da empresa, identidade da instalação, opções e auditoria depois da última escrita; falha ou SQL ignorado desfaz toda a tentativa. | A proteção do storage não comprova que todos os consumidores dos 38 módulos já usam configuração própria. |
| Recorrência | Agendas diárias, semanais e mensais de tarefas de projeto e avulsas; modelo imutável, datas/fuso, condição de conclusão, pausa/retomada/cancelamento, execução GUI/API/worker e histórico de ciclos. | Não copia arquivos, comentários, tempos, dependências, publicação de portal ou vínculos financeiros. Uma tarefa canônica de portal sem responsável precisa ser atribuída antes de virar modelo. |
| Origem API | Dois endpoints finitos, `task_schedules` e `standalone_schedules`, usam os scopes existentes `tasks:read/write`; cada ciclo revalida token persistido, ator e empresa. | Rotação de token não adota agendas anteriores. Revogação, expiração e perda de scope/autoridade impedem novo ciclo. |
| Inventário | 38 diretórios de módulos, controllers/métodos candidatos, opções literais/dinâmicas, hooks, jobs, relatórios e referências a tabelas/DB, com hashes de fonte. | Candidatos lexicais não são falhas confirmadas nem recursos disponíveis. Estado geral permanece `blocked / operational_ready=false`. |

O master já registrado continua reconhecido, a empresa principal e as preferências existentes são preservadas, e novas empresas não recebem credenciais alheias. O assistente mantém clientes privados por padrão e explica o compartilhamento opcional de identidade. As páginas próprias oferecem ações efetivas; rotas legadas negadas não são contabilizadas como implementação.

## Causas reproduzidas e correções

**Settings.** Testemunhas SQL após a auditoria demonstraram que a implementação 452 podia retornar sucesso mesmo com alteração de opções/identidade na mesma transação. O novo guard compara o estado esperado depois das últimas gravações e exige o registro de auditoria correto, com ID posterior, ator, empresa, evento, sujeito e metadados esperados. A prova RED é execução sobre a fonte 452; seu exit 0 significa observação do defeito, não aprovação do comportamento. As provas GREEN verificam testemunha, rollback e retry. Veja `settings-commit-453.md`.

**Staff nativo.** O modelo real 452 gravava o perfil antes da unidade de ACL: uma falha de auditoria deixava uma identidade sem empresa utilizável. A composição 453 usa o modelo e os participantes reais sob uma unidade transacional. A suíte antiga que usava mock e esperava aviso de confirmação parcial foi preservada em arquivos de fixture com hashes e substituída por cobertura do modelo/controlador reais. As negativas de ACL permanecem. Flags históricas dos vínculos retidos são conservadas; não passam a conceder autoridade por si mesmas.

O campo `role` vazio do formulário é normalizado para 0. O formulário também envia `hourly_rate="0"`, enquanto DECIMAL retorna `0.00`; a reprodução real recusava uma gravação correta. O witness agora compara esse decimal exatamente, sem float ou arredondamento permissivo. SQL ignorado e valor diferente continuam recusados. Veja `staff-management-453.md`.

**Recorrência.** Cada ciclo usa o escritor canônico da modalidade, na mesma transação do recibo, auditoria e progresso da agenda. O número de ciclo enviado na execução manual impede que um reenvio processe acidentalmente o ciclo seguinte. O histórico e os recibos são relidos depois da última confirmação de origem. O worker mantém cursor persistente para não examinar sempre as mesmas agendas bloqueadas. Veja `task-recurring.md` e `evidence/task-recurring-453-results.json`.

## Banco e atualização nativa

A migration 453 cria apenas cinco tabelas: `company_staff_commands`, `company_task_schedules`, `company_task_schedule_runs`, `company_task_schedule_requests` e `company_task_schedule_cursor`. Não altera tabelas históricas nem preenche autorizações, equipe, tarefas ou histórico financeiro. Só o cursor recebe sua linha inicial `(1,0,0,1)`.

O recibo de equipe referencia a empresa e conserva o ID histórico do colaborador sem FK para Staff, para não impedir a exclusão nativa elegível. A exclusão de uma identidade não permite ressuscitá-la por replay. Schemas exigem engine/colunas/defaults/collations/índices/FKs esperados; não tratam `CREATE TABLE IF NOT EXISTS` como validação suficiente.

O runner real App/CI parte de 452 produzido pelos predecessores, testa DDL falho, marcador falho e SQL ignorado, readback, retry/replay e preservação de linhas/metadados. **1.863 verificações próprias** no runner 453, separadas dos includes históricos. Passou em MariaDB/PHP 8.3 e MySQL 8/PHP 8.1 com 128 MiB.

**HTTP:** instalação nova 453 em banco vazio passou pelo instalador nativo, CSRF inválido 403, POST válido 200, master 17/origem installer e principal 1, sem shell/processos no servidor. Um clone separado do backup 452 verificado (`c731d78f381cb12b3ab9202cd3796aa01ec31c11da03f631669c39633b39f995`) passou pelo browser original do upgrade. A comparação READ ONLY preservou as 293 tabelas históricas e confirmou exatamente cinco novas.

Os deltas qualificados dessa comparação são restritos: marcador 452→453; três opções nativas (`upgraded_from_version`, `update_info_message`, `last_updated_date`); cinco auditorias de Settings desses controles, mantendo as anteriores byte a byte; um evento exato de login do master 17; duas sessões locais novas com as antigas intactas; e campos nativos de último acesso. Apenas os AUTO_INCREMENT dos participantes que receberam essas linhas são normalizados na comparação estrutural. Outros dados, metadados, opções, identidade e grants permanecem iguais.

As primeiras tentativas HTTP não foram contadas como PASS integral. Uma falhou no login e outra terminou com DataTables inválido depois de atualizar o marcador. A consulta posterior isolada era JSON válido, sem comprovar retrospectivamente a resposta perdida. O clone 452 separado passou no browser original, com resposta JSON capturada. A primeira versão do comparador tratava login/sessões/auditoria/opções nativas como alterações inesperadas; foi corrigida para conferir os deltas exatos acima contra o backup preservado, sem resetar nem repetir o upgrade já concluído.

O ambiente também recriava um diretório `install/` vazio após sua retirada. Foram observados processos de sincronização da plataforma, compatíveis com o mtime antigo/ctime novo; não foram interrompidos. O router apenas do fixture retira esse scaffold vazio depois da instalação comprovada. Se existir qualquer arquivo, o guard original continua bloqueando. Nenhum guard do produto foi alterado. O harness local usa PHP-S/Chromium/MariaDB e um adaptador finito de CLI; não é uma execução do Docker remoto.

## Testes e evidências

| Conjunto | Evidência nova |
| --- | --- |
| Recorrência SQL | 400 verificações próprias por runtime: core 116, API 76, integridade 83, worker 51 e concorrência 74; Maria 10.11.14/PHP 8.3.6 e MySQL 8.0.46/PHP 8.1.2, 128 MiB. Inclui waiters observados, token expirando durante a espera, SIGKILL após INSERT, rollback, retry e revogação após interrupção. |
| Equipe | 177 verificações próprias: gestão 56, modelo nativo 69, controlador 30 e concorrência 22; ambos os motores/PHPs, com cinco corridas observadas e SIGKILL/retry. Includes não são somados outra vez. Regressões antigas de acesso/master permanecem. |
| Settings | 40 verificações próprias de commit fencing e 6 de auditoria, além dos 78 pré-requisitos; transação de identidade, controlador/modelo, PDF principal e concorrências originais executados nos dois motores. |
| GUI recorrência | A/B/principal × projeto/avulsa; criação/reabertura, pausa/retomada/cancelamento, ciclo elegível, reenvio, CSRF 403, falha tardia 503/rollback/retry, acesso cruzado 403 e preservação de origem/finanças. |
| GUI equipe | A/B/principal, gerente sem master, identidade compartilhada, concessões próprias, revogação preservando outra empresa, contexto/revisão/CSRF, sessão revogada, formulário nativo e identidade do master preservada. 61 navegações, três respostas JSON 200 de tasks/table e nenhum erro JavaScript; desktop e mobile revistos. Resultado detalhado no documento de equipe. |
| Composição CLI | `php index.php company_recurring_worker index` executou o controlador e os quatro processadores. Na base HTTP de prova, todas as agendas criadas já estavam canceladas/concluídas; a execução reportou 0 pendentes. Positivos do worker são os testes SQL próprios, não esse 0. |
| Preservação | O controlador financeiro original completou 255 verificações com 128 MiB no MySQL/PHP 8.1; cópias originais de projeto/tarefa passaram. As fontes congeladas foram conferidas por SHA256 antes/depois; os logs distinguem comandos interrompidos por caminho de teste incorreto dos testes efetivamente concluídos. |
| Inventários/CI | Inventários e testes de convenção/empacotamento são gates adicionais. `--assert-ready` deve continuar falhando enquanto o contrato integral estiver aberto. Aprovações 452 são históricas; o CI de 453 precisa ser conferido em seu próprio SHA. |

Os 63 testes Python de versão, empacotamento, convenção de migrations, inventários e armazenamento concluíram em 24,773 s. A consolidação executável e os hashes estão em `evidence/resume453-current.json`.

O novo inventário da composição 453 contém 38 módulos, 4.099 fontes PHP, 684 opções literais, 145 referências dinâmicas, 247 controllers candidatos, 553 hooks, 64 candidatos a jobs, 289 a relatórios, 792 nomes de tabelas e 12.530 chamadas DB candidatas. No fixture fresco, somente `theme_style` 2.3.0 estava registrado como módulo ativo; o diretório distribuído não prova ativação em produção. O inventário completo é gerado em artefato CI; o resumo versionado mantém `operational_ready=false`.

## Pendências após este incremento

**Internas:** concluir a sequência Leads→Purchase→RH e demais módulos/superfícies; validar consumidores de Settings ainda não migrados; manter provas positivas de API/portal/arquivos/relatórios/jobs. Trabalhos futuros locais não integram este commit. Finance/Accounting, folha, timesheets e demais módulos exigem tratamento próprio, sem anunciar uma rota negada como recurso.

**Banco do Brasil:** ainda não avançados neste incremento: reversão da nova base tarifária 240; combinação de tarifa/juros/desconto/abatimento/IOF/outros/parciais e seus adaptadores; alteração nominal com revisão financeira/fiscal; protesto/sustação/cancelamento/negativação com estados e confirmação independentes. São pendências de implementação, não apenas homologação.

**Externas específicas:** confirmação contratual de BRL em CBR641 85–86; taxa padrão efetiva ou isenção dos juros herdados CNAB400; errata CBR643 26–39 (largura 14 versus picture 9(013)v99 de 15) e totalizadores; identidade estável e semântica individual/acumulativa/datas/componentes/reversões da API 18/19; domínios e exemplo normativo de PATCH juros/multa; e contrato de transporte de arquivos com endpoint/protocolo/autenticação/recibo. Preservar a solução já entregue para o trailer CBR641. Homologação real depende de acesso/contrato/autorização específicos e não foi executada.

O SHA publicado deve ser conferido no remoto e na PR, junto da árvore correspondente; não há afirmação de conclusão integral derivada de CI verde. O trabalho continua na mesma branch, sem merge ou deploy.
