# PR 52 — checkpoint local da continuação

Branch: `fix/native-company-experience`.
HEAD local e último remoto reconciliado: `cfd3e334dbc7d28f33aa4408b79b5de010c9bdb7`.
Estado: alterações locais preservadas; nenhum novo commit ou push nesta continuação. PR ainda não candidata à revisão para merge.

## Implementado localmente

- Sincronização do smoke de certificados após mudança de viewport, mantendo a asserção original de largura e registrando geometria. A falha do último workflow de Stacks ocorreu nessa asserção durante o passo de provisionamento; o log não indica repetição da falha antiga de faturas.
- Adaptação em andamento do smoke amplo ao formulário oficial de faturas, com criação, edição transacional de linhas e metadados, preservação de IDs, revisão, idempotência e rollback.
- Correções de composição do formulário e da prévia: países públicos, variáveis compartilhadas de endereço, dropdown de tributos, moeda, número armazenado, tags autorizadas, estados e fontes de lembretes.
- Fila de contratos com CC validado e PDF opcional vinculado à intenção; opções ligadas ao formulário existente.
- Valor/moeda opcionais armazenados em contratos e novo valor na renovação. O contrato anterior conserva o snapshot; ausência de valor permanece ausência.
- PDFs de contratos, propostas e orçamentos com identidade e imagens armazenadas, sem carregar URLs externas nem inferir assinatura eletrônica.
- Classificação individual dos 139 métodos originalmente não admitidos: 114 necessários, 20 internos e 5 não aplicáveis. Justificativas em `pr52-action-classification.json`. A classificação não encerra as composições pendentes nem comprova equivalência.

## Validação concluída nesta continuação

- Smoke canônico de certificados: passou.
- Smoke canônico de portal: passou.
- Lote SQLite de regressão afetada: 63 comandos passaram; 23 verificações adicionais executadas por fixtures dependentes.
- Contratos: 29 verificações adicionais de valor, renovação, identidade, imagens, PDF real, isolamento e rollback passaram sobre a fixture existente.
- E-mail de contratos: 23 verificações passaram, incluindo CC/PDF, replay, conflito de intenção, injeção de cabeçalho e adulteração antes do despacho. Worker local também passou. Nenhum envio SMTP/OAuth externo.
- Edição da fatura emitida: testes do escritor original, metadados, ACL, revisão obsoleta, replay, preservação de IDs e rollback passaram em SQLite; a exclusão da própria fatura dos candidatos à mesclagem recebeu asserção adicional.

## Ponto recuperável atual

O helper de faturas já foi corrigido: utiliza somente os campos de segurança declarados na configuração oficial, preserva o CSRF da operação e do framework e exclui `_company_switch_csrf`. O teste continua exigindo conflito 409 para revisão obsoleta e comprova o 303 real por replay idempotente com redirecionamentos desabilitados. Os replays de XHR conservam também os cabeçalhos originais de contexto e CSRF, inclusive quando a empresa já mudou.

Faturas e créditos concluíram integralmente no banco preservado `argws_upgrade_ci_conclusion_19` (237,248 s e 82,11 s). Recibos e logs canônicos em `/tmp/crm-pr52-completion/`. Projetos/tarefas passaram no clone 26 (390,082 s), despesas passaram no clone 27 (25,387 s) e importações também passaram. Projetos precisa de reexecução após as alterações posteriores em lembretes e CAS do checklist. Contratos e atendimento passaram integralmente no clone 30 (120,363 s), incluindo os controles oficiais recompostos, criação/cópia/cancelamento, respostas e estados terminais, conflitos 409 e replay 303 real. Todos os clones anteriores permanecem preservados.

Desde o checkpoint anterior foram compostas e testadas localmente: reversão manual de pagamento na ação original; seis ações originais do checklist de tarefas; preferência de visibilidade em Staff; e sete ações originais de contratos (marcar/desmarcar/limpar assinatura manual, salvar conteúdo, cancelar renovação, anexar e ocultar arquivo). Os testes de contratos agora registram 32 verificações adicionais de controlador e banco, com replay, revisão, rollback e preservação do histórico/snapshot/bytes. A equivalência completa no navegador e na principal ainda precisa ser verificada. A classificação dos 139 métodos continua não sendo comprovação de conclusão.

O formulário geral de Settings passou a apresentar o padrão validado de extensões quando ainda não existe opção armazenada, sem escrita implícita e sem substituir valor vazio explicitamente armazenado. Regressão local passou. Os controles oficiais de tributos/desconto e os estados de pagamento foram preservados no smoke amplo.

## Pendências locais antes da publicação

1. Faturas e créditos passaram integralmente. Concluir projetos/tarefas em andamento e todos os cenários HTTP seguintes sem retirar asserções.
2. Completar as ações necessárias e callbacks da matriz; cada item mantém estado de composição/validação pendente. Não liberar métodos globalmente.
3. Concluir o lote MySQL e demais verificações do código final, incluindo instalação limpa, upgrade com dados, histórico com IDs coincidentes, cópia pelo POST original, pipeline GET/HEAD e Settings principal/A/B/restrito.
4. Remover os logs temporários de diagnóstico `PR52` do controlador de faturas após resolver os erros; revisar composição e regressões adicionais do navegador.
5. Regenerar os inventários a partir de exportação limpa do index; atualizar relatório e descrição da PR, reconciliar novamente o HEAD remoto, criar o commit consolidado e publicar com avanço normal na mesma branch.
6. Verificar todos os workflows do SHA efetivamente publicado. Não há resultado de CI para estas alterações locais ainda não publicadas.

## Dependências externas separadas

- CNAB400: homologação bancária externa pendente e não bloqueante para publicar o restante. Código preservado; operação não homologada deve permanecer desativada e sinalizada. Nenhuma afirmação de operação homologada.
- Cache: política e testes anteriores preservados. Limpeza real e ativação na main continuam não comprovadas. Artefatos, releases e histórico não foram apagados.

## Evidências locais da sessão

Logs e recibos de execução: `/tmp/crm-pr52-completion/`.
Fixture HTTP atual: `argws_upgrade_ci_conclusion_30`, preservada; clones e checkpoints anteriores também preservados.

Lote MySQL retomado na sessão `35402`: o último progresso confirmado nesta atualização foi 309/401 comandos, sem falhas registradas até então. Consultar `/tmp/crm-pr52-completion/mysql/results.json` para o estado atual. O lote parcial não é aprovação; alterações posteriores ao início exigem reexecução dos testes afetados. Novos testes de reversão manual de pagamento e preferência do checklist devem ser incorporados ao lote final.

Runner de navegador: `/tmp/crm-pr52-completion/run-browser.py`; runner local: `/tmp/crm-pr52-completion/run-local-final.py`. Ambos escrevem recibos e logs. O adaptador local altera somente o transporte dos comandos Docker para os processos PHP/MySQL disponíveis. Instalação limpa e upgrade em Docker precisam ainda de confirmação no CI do SHA publicado.

Remover os logs temporários de diagnóstico `PR52` dos controladores de faturas e Settings antes de concluir. Não incluir logs de aplicação, caches serializados nem credenciais sintéticas no commit.

## Continuação local adicional

- PDF: composição dos campos originais de layout, cores, largura do logo, estado, assinatura e paginação. Opções de transações e link de pagamento usam pagamentos/estornos reais e a URL configurada, mantendo a autorização do portal. Teste `pdf_layout_options.php`: 71 verificações adicionais passaram em SQLite; nenhum acesso externo.
- Contratos: renovação com nova moeda USD armazenada e novo valor, replay/conflito e preservação do predecessor passaram em SQLite, além de BRL.
- Eventos compartilhados do checklist extraídos sem duplicação e revalidados em Chromium/jQuery real (`task_checklist_command.browser.cjs`).
- Ainda não houve commit ou publicação destas alterações. Reexecutar os testes afetados no código final e reconstruir os assets após as alterações JavaScript posteriores ao último build.

## Retomada adicional — estado verificável

- Tipos de contratos: catálogo isolado no journal existente, ações oficiais e snapshots/PDF; 14 verificações adicionais SQLite passaram. Notas: 10; referências externas: 10; lembretes de tarefas: 15. Os testes de controlador carregam fixtures dependentes; corrigir os avisos da fixture incompleta antes de aceitar o lote sem diagnósticos.
- Exportações SQL privadas em `/tmp/crm-pr52-completion/post-projects.sql`, `post-expenses.sql` e `post-imports.sql`. O próximo clone para contratos deve partir de `post-imports.sql`; o clone 27 contém a tentativa parcial.
- O smoke de upgrade agora insere um cliente histórico antes do POST original e verifica sua preservação depois. O cliente inserido tardiamente no clone 27 serve apenas ao cenário funcional; não comprova upgrade local com dados.
- O processo do lote MySQL anterior não estava mais disponível. O recibo registrava 309 sucessos e `complete=false`; a retomada usa o mesmo plano e não infere a causa da interrupção.

## Retomada — contratos e atendimento concluídos no navegador

- Smoke `tests/companies/contracts_browser.cjs`: passou integralmente, recibo SHA-256 do fonte `ecc87037076613899bc4981705b58e89ca95793948e73d718af41d4fbe1bf65d`. Snapshot privado `post-contracts.sql` preservado antes das próximas mutações.
- Corrigida regressão real do driver mysqli: captura do ID da auditoria antes de qualquer SELECT, preservando a prova contra inserções ignoradas. Regressão `ticket_mysqli_audit.php` e criação HTTP/MySQL passaram.
- Estados terminais de chamados não aceitam respostas implícitas; reabertura é explícita. Controles de status e configurações usam as rotas originais, CSRF, revisão e nonce. `ticket_terminal_state.php` e `ticket_detail_page.php` passaram.
- Etiquetas: composição local das ações originais Settings, isolamento por empresa/recurso, revisão, replay e auditoria/rollback; 12 verificações adicionais SQLite passaram. Validação HTTP/MySQL ainda pendente.
- Fixture incompleta de contratos corrigida com o schema auxiliar real; o GET agora é afirmado como 200 e a nova renderização é verificada. Reexecução de referências externas passou sem diagnósticos.
- Próximos cenários: anexos de operações e publicação no portal. Atualizar os helpers para as rotas oficiais e completar os controles ausentes sem retirar asserções. Nenhuma nova publicação nesta continuação.

## Retomada — anexos e lote MySQL completo

- Lote original MySQL: 401/401 executados, três falhas registradas. Duas de concorrência exigiam 128 MiB nos processos filhos; o PHP CLI local estava em `-1`. Reexecução com configuração privada herdada passou (Leads 6,268 s; Compras 10,045 s), sem mudar as asserções. A terceira completou as asserções de faturas e falhou no teardown por FK: removido o encerramento redundante que antecedia a limpeza central limitada ao prefixo sintético; reexecução em andamento.
- Anexos de contratos/chamados: smoke completo passou (90,035 s) no clone 32, com controles nas rotas oficiais. Corrigida a prova CAS de arquivos para igualdade entre números mysqli/string e SQLite/int. O POST original de ocultação e seu replay exigem 303; download oculto retorna 404. Testes `ticket_private_upload.php` e `ticket_file_delete.php` passaram.
- Publicação: controles explícitos de publicar/despublicar chamado/projeto/arquivos/respostas e projeção segura do catálogo nativo no portal compostos localmente. Teste de proveniência de estado/texto `ticket_portal_projection.php` passou. Smoke amplo do portal em sequência, ainda sem resultado.
- Fixture atual `argws_upgrade_ci_conclusion_32`; snapshot privado `post-operation-files.sql` preservado. Não houve commit ou publicação.

## Retomada corrente — lote concluído e rotas de despesas

- Os 401 comandos MySQL foram executados. As três falhas registradas passaram em reexecução: concorrência de Leads/Compras com 128 MiB herdados e teardown central de faturas na ordem de dependências. A reexecução de faturas passou integralmente (86,867 s).
- Dezesseis testes MySQL afetados passaram: ações/lembretes/checklist de tarefas, etiquetas, contratos (ações, notas, tipos, referências externas, valor/moeda, fila), layout PDF e arquivos/projeção/auditoria/estados de chamados. Recibos `affected-mysql-*` no diretório de evidências.
- Portal operacional passou integralmente no clone 32 (251,084 s); o limite de chamados ativos recebeu depois a mesma resolução do catálogo nativo, exigindo revalidação afetada.
- Despesas passou novamente no clone 33 (26,411 s) após compor ações originais de criação, edição, cancelamento, categorias, anexo e importação. Snapshot-base preservado: `post-projects.sql`.
- Comentários de tarefas podem ser editados/ocultados nas ações originais; auditoria preserva ambas as versões e autor/data. Datas na ação inline permitem remover prazo opcional. Regressões SQLite e MySQL passaram; browser afetado ainda pendente.
- `NativeCompanyTaskBoard.php` está em implementação local: quadro e preferência nas ações originais. Ainda não conectado/testado nesta marca.
- Remoto novamente consultado: permanece `cfd3e334dbc7d28f33aa4408b79b5de010c9bdb7`. Nenhum commit/push novo. Logs temporários PR52 de controladores já foram removidos; logs/caches de runtime devem ficar fora do pacote.

## Estado vigente da retomada (substitui números parciais acima)

- Remoto reconferido nesta retomada: `cfd3e334dbc7d28f33aa4408b79b5de010c9bdb7`, igual ao HEAD local. Nenhuma publicação nova ainda. Alterações locais preservadas.
- O lote MySQL de 401 comandos terminou. As três falhas tiveram correção/ajuste comprovado da execução e reexecução aprovada. Os recibos canônicos são a fonte para contagem e SHA dos testes; este estado não aprova mudanças posteriores.
- Quadro de tarefas e cópia pelo POST original implementados; SQLite e MySQL aprovados. Comprovação HTTP/navegador e composição para a principal ainda pendentes.
- Comentários respeitam autoria, ACL e a opção da primeira hora. Lançamento, edição e exclusão de horas pelos métodos oficiais implementados com revisão, auditoria, proteção de origens faturadas, replay e rollback. SQLite aprovado; lote MySQL afetado em execução (sessão 10350).
- Fixture HTTP vigente: `argws_upgrade_ci_conclusion_33`. Despesas pelo formulário oficial aprovadas (26,411 s). Portal operacional aprovado no clone 32 (251,084 s), antes do ajuste posterior de contagem de chamados ativos.
- Docker verificado neste ambiente: comando ausente. Os serviços PHP/MySQL/SQLite e Chromium locais estão disponíveis. Instalação e upgrade completos em Docker precisam ser comprovados pelo workflow do SHA publicado; não inferir aprovação a partir de clones funcionais.
- Próximo trabalho: compor as ações oficiais de projetos usando os escritores existentes; concluir as demais linhas necessárias da matriz, testes afetados, inventários e publicação. A classificação de 114 necessários ainda NÃO prova implementação/equivalência integral.

## Estado vigente — migração 481 e composição de Projetos

- HEAD remoto reconferido: `cfd3e334dbc7d28f33aa4408b79b5de010c9bdb7`; nenhuma publicação nova. Todos os arquivos locais anteriores preservados.
- Corrigido defeito real MySQL: `company_blank_task_timers.target_kind` precisava de 23 caracteres para a origem standalone, mas admitia 16. Migração 481 valida o schema anterior, amplia para 32 e comprova preservação integral das linhas; não há downgrade destrutivo. Versão do pacote 4.8.1, sem release/deploy. Testes MySQL/SQLite do schema aprovados.
- Lançamentos manuais e edição/exclusão de horas passaram integralmente em MySQL (92 verificações cumulativas, 19,839 s), incluindo transferência, taxa armazenada e guardas de faturamento.
- Upgrade HTTP autenticado real 480→481 passou no clone 33, com CSRF e preservação das colunas preexistentes de nove tabelas de negócio. Subcaso de upgrade, não aprovação do smoke amplo inteiro. O teste foi depois melhorado para comparar as colunas preexistentes também em upgrades antigos.
- Projetos: conversas/comentários, marcos, tarefas, horas e Gantt compostos nas rotas/abas originais, reutilizando leitores/escritores autorizados. Smoke novo A/B passou (128,076 s), incluindo GET/HEAD, POST 303 real, replay, revisão obsoleta 409, CSRF e isolamento. Não comprova todas as ações restantes nem a principal.
- Em andamento: união de tarefas históricas da principal com registros tipados, conservando IDs, fatos e rotas originais. Testes anteriores que esperavam bloqueio estão sendo substituídos por verificações dos dados reais e de isolamento, sem remover cobertura.
- Próximos passos: terminar composições necessárias da matriz, regressões afetadas (incluindo principal/restrito), build, inventários, reconciliação remota, publicação e CI do SHA final. Nada somente local deve ser descrito como publicado.
