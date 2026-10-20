# Continuidade e conclusão da PR 45 — ARGWS CRM
Atualizado em 20/10/2026, 09:57 (America/Bahia). Exclusivo deste repositório.

## Pedido
Retome a implementação e conclua as pendências técnicas. Não entregue apenas planejamento. Preserve o trabalho publicado e o trabalho local recuperável. Não faça merge, deploy, force-push, tag ou release manual.

## Base verificada
- Repositório: wkarts/ARGWS-CRM.
- PR: https://github.com/wkarts/ARGWS-CRM/pull/45
- Branch: feat/banco-brasil-billing-cnab240; destino atual: main. Não mudar base ou branch por iniciativa própria.
- HEAD: 004c334c451707e19abcd68bf66a793f547116fb.
- Último commit: 20/10/2026 11:36:42 UTC, 08:36:42 Bahia.
- Título: feat(settings): publish company-owned ticket reply ordering with validated NOOP444.
- Núcleo 4.4.4 / migration 444; Banco do Brasil 1.2.2 / 122; Asaas 1.5.3 / 153.
- PR aberta, não mesclada. O último commit confirma publicação, não atividade ou parada do executor.
Confira novamente o HEAD remoto antes de editar. Se houver avanço, reconcilie e preserve-o.

## Prioridade 1: corrigir o CI do HEAD atual
Validação terminal 444: 16 workflows distintos, 14 aprovados e 2 falhos. Stacks passou. Não usar aprovação histórica do 436 nem resultados de outro SHA como prova do atual.

1. Empresas:
   https://github.com/wkarts/ARGWS-CRM/actions/runs/39416371604/job/118606275393
   PHP 8.5: SQLSTATE 1049 Unknown database 'asaas_test'.
   Caminho: tests/companies/sales_mutations_foreign_keys.php:45 -> tests/issuers_core_support.php:101.
   Verificar propagação do DSN, banco selecionado, criação e isolamento das fixtures. Reproduzir no MySQL isolado antes de corrigir. Não mascarar o problema apontando testes para uma base produtiva, removendo asserções ou concedendo permissões excessivas. PHP 8.1 cancelado não conta como aprovado.

2. Banco do Brasil:
   https://github.com/wkarts/ARGWS-CRM/actions/runs/39416371572/job/118606275132
   tests/banco_brasil_own_number_sqlite.php:4 aborta porque exige SQLite real, mas herda DSN do contexto MySQL/MariaDB.
   Unitários PHP passaram, integrações SQL falharam.
   A descrição da PR informa correção local RED -> GREEN ainda não publicada no 444. Procure e preserve esse trabalho antes de recriar. Faça o teste negativo usar efetivamente SQLite, preservando cobertura MySQL e MariaDB. Não trocar o teste por skip.

3. Stacks 444 aprovado:
   https://github.com/wkarts/ARGWS-CRM/actions/runs/39416371693
   Preservar os fluxos que já passaram.

Reexecute regressões pertinentes e acompanhe os 16 workflows do SHA realmente publicado até resultado terminal. Documente causas e evidências.

## Não repetir reparos históricos
- Poppler/pdftotext e helper nativo Asaas substituindo guard fixo 1.5.2 já foram corrigidos.
- Os guards históricos de migrations e scopes tiveram correções progressivas.
- A expectativa HTTP 403 versus contrato legado 409 de projeto estrangeiro pertence a falha anterior; não alterar autorização apenas para tornar teste verde.
- Limite de memória 128 MiB em Empresas ocorreu no 443. A causa confirmada de Empresas no 444 é banco asaas_test ausente.
- httpx, HMAC e PRs do PIGE não pertencem a este trabalho.

## Prioridade 2: reconciliar publicado versus local
Leia AGENTS.md, CONTRIBUTING.md e:
- docs/companies/pr45-status.md
- docs/companies/requirements/
- docs/companies/pr45-recovery-439.md até pr45-recovery-444.md
- docs/companies/pr45-validation-439.md até pr45-validation-444.md
- docs/companies/evidence/
- docs/banco-brasil-remaining-contracts.md

Os incrementos 439 a 444 estão publicados. Não tratá-los como apenas locais.
154 testes novos de um snapshot antigo foram perdidos; testes resume_439 foram reconstruídos. Não alegar equivalência ao snapshot perdido nem inventar recuperação.
Preserve alterações locais não publicadas com checkpoint antes de operações Git. Não usar reset/clean destrutivos.
Monte uma lista rastreável: requisito, implementação, arquivo, teste, publicado/local/pendente e bloqueio real.

## Publicado, segundo documentação da PR
Confirmar cobertura no código; esta lista não substitui auditoria.
- Multiempresa no núcleo: empresas, master/equipe, vínculos, contexto ativo, clientes privados/compartilhamento explícito, contatos e portal.
- Faturas/créditos/pagamentos, arquivos e relatórios próprios; operações de projetos/tarefas/despesas/contratos/tickets e APIs adaptadas.
- 437–439: discussões, faturamento de horas, recorrência/cópias, API financeira, refund integral declarado Asaas e recebimento manual posterior, SMTP/OIDC Microsoft, Connect e Pusher privado.
- 440: rascunhos revisáveis e emissão explícita, recorrência de fatura/rascunho, condição de principal quitada, descoberta durável; cópias revisadas de projeto/tarefa; reversão local do manual pós-refund; suporte Connect; BB desconto e janela após vencimento.
- 441: API privada de despesas/categorias/arquivos, scopes opt-in e writers canônicos.
- 442: cópia revisada de despesas; configuração/consentimento/outbox/dispatcher de IA por empresa; PATCH e confirmação de SeuNúmero BB.
- 443: edição/cópia de orçamentos/propostas com numeração, tributos e PDF; importação OBSERVACIONAL dos auxiliares CNAB400 5/01 e 5/10. Não equivale a remessa ou conciliação financeira CNAB400.
- 444: ordem asc/desc das respostas de tickets por empresa, no administrativo e portal privado.

## Prioridade 3: concluir multiempresa e operação
Concluir as lacunas do contrato original, sem reduzir silenciosamente o escopo:
- Ledger local sem efeitos fictícios de caixa.
- Recebimento/crédito após restauração.
- Calendário próprio, defaults de tarefas e demais consumidores/configurações ainda não adaptados.
- Recorrências, cópias, rascunhos e fluxos financeiros completos, incluindo retomada/idempotência e efeitos reais apenas quando explicitamente autorizados.
- Empresas no núcleo, menu em Configurações acima de Colaboradores, wizard utilizável, empresa inicial incluída, usuário principal reconhecido conforme instalação e demais usuários com acesso explícito.
- Alternância de empresa e configurações da empresa ativa devem alcançar telas, APIs, serviços, filas, arquivos, relatórios e integrações. Não afirmar isolamento universal só por existir empresa_id ou um inventário lexical.
- Cadastro da empresa deve alimentar suas informações sem duplicação desnecessária.
- Não impor novo ritual de comprovação ao master de instalação válida apenas por atualização. Separar recuperação excepcional de identidade inconsistente do login/upgrade normal.
- Clientes compartilhados somente por ação explícita; segredos/contas bancárias/configurações não herdados automaticamente.
- Preservar Asaas, organização de parâmetros, segurança de webhook e correções de notificações já entregues; conferir regressões conforme contrato.
- Interface PT-BR, utilizável, seguindo padrões existentes; não entregar integrações configuráveis apenas editando código quando o contrato pede administração visual.

## Prioridade 4: Banco do Brasil API / CNAB240 / CNAB400
Preservar os comandos existentes, origem/conta/ambiente/empresa, snapshots e confirmação posterior.
Pendências explícitas da PR:
- Adapters, estados e provas para demais comandos, pagamentos parciais e componentes.
- Tarifas, IOF e outros componentes; reversões parciais CNAB240.
- API18/19: falta identificador estável por crédito e definição individual ou cumulativa de valorCreditoCedente, além de componentes/datas necessários. Não presumir delta de consultas.
- CNAB400 financeiro/outbound ainda não implementado integralmente. Importação observacional não é entrega dessa vertical.

Insumos bancários específicos ainda indicados como necessários:
1. Código BRL no CBR64 posições185–186.
2. Regra inequívoca de isenção ou parâmetros de juros herdados da conta/convênio/carteira/variação.
3. Errata/explicação CBR64 posições326–339: 14 posições versus 9(013)v99.
O trailer CBR64 já foi resolvido, segundo a descrição atual.

Valide esses pontos em documentação primária aplicável ao contrato. Aproveite remessas aceitas fornecidas pelo usuário como casos de regressão, sem tratá-las como autorização para qualquer variação. Se os documentos faltarem, solicite exatamente o necessário e avance nas partes independentes. Nunca inventar regras financeiras para encerrar a tarefa.
Homologação externa, envio de remessas, cobrança, cancelamento e movimentação bancária real exigem autorização específica; não executar nesta tarefa.

## Versionamento e migrations
- Regra nativa desta plataforma: sequencial inteiro, sem saltos; 444 -> 445 -> 446, representados 4.4.4 -> 4.4.5 -> 4.4.6.
- Não usar lógica que gere 3.11.0 ou versões incompatíveis com o runner nativo.
- Toda atualização funcional deve atualizar versão do núcleo e migration correspondente, mesmo NOOP.
- Módulo alterado funcionalmente também recebe versão e migration nativa coerentes; não incrementar módulos intocados arbitrariamente.
- Preservar migrations históricas publicadas e seus hashes. Corrigir aditivamente, sem renumerar histórico.
- Novos módulos seguem início 1.0.1, arquivo principal com nome do módulo e diretório migrations; instalação deve acionar runner nativo.
- Conferir VERSION, constantes, config de migrations, cabeçalhos dos módulos, manifestos e distribuição.
- Usar gerador/gates oficiais e documentar como correções exclusivamente de teste se enquadram nas regras do repositório.

## Critérios de teste e conclusão
- Instalação limpa, upgrade nativo 444 -> próximo incremento e replay idempotente.
- MySQL/MariaDB reais, SQLite nos cenários apropriados; não substituir o banco exigido por mock.
- Master, empresa A/B/principal, colaborador restrito, troca de contexto, revogação, portal, APIs e arquivos privados.
- Concorrência, rollback, idempotência, outbox e estados sending/uncertain sem retry cego.
- Chromium para fluxos gráficos; PDF/CSV, numeração, saldo e proveniência.
- Preservar histórico, caixa e documentos; não criar créditos fictícios nem backfill automático de autorização.
- Publicar commits na MESMA branch da PR45, atualizar descrição/evidências e acompanhar CI do SHA publicado.
- Sem merge, deploy, alteração da base main/develop, force-push ou release manual.
- Entrega final: SHAs, versões/migrations, recursos concluídos, testes locais e remotos por SHA, pendências técnicas reais e dependências externas separadas.
- Não declarar “concluído” com código apenas local, workflow vermelho ou vertical financeira somente observacional.

## Instrução de execução
Comece pelo HEAD remoto e pelos dois erros de CI do 444. Reaproveite o reparo local SQLite se existir. Depois conclua as pendências técnicas com checkpoints publicados e validados. Evite gastar a rodada inteira em planejamento ou repetir provas já válidas sem relação com mudanças. Se houver bloqueio real, informe com precisão e continue nas partes independentes.
