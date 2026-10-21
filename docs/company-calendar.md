## Integração nativa446

Composto no núcleo4.4.6/446, migration NOOP oficial, BB122 eAsaas153 intocados. Testes SQL finais nos dois motores, API/controllers/concorrência eHTTP/Chromium em instalação fresca e upgrade445→446 constam de docs/companies/pr45-validation-446.md. O status da publicação/CI pertence aoSHA confirmado naPR45; não é inferido de provas locais. A descrição original do pacote abaixo permanece como histórico de autoria.

# Calendário privado por empresa

Componente independente preparado sobre a composição 444, sem mudança de versão ou DDL. A integração, versão nativa, CI e prova do SHA publicado pertencem à composição posterior. O componente não deve ser anunciado como publicado enquanto estiver apenas neste overlay.

`company_calendar/index` é a página e datasource próprias. Não usa o calendário legado. A empresa e o usuário vêm exclusivamente do contexto selado; POST e links preservam o token de contexto. A configuração exige `calendar.view`, `calendar.configure`, `settings.view` e `settings.edit`. O acesso à página exige `calendar.view`; cada fonte exige seu `view` original. Tarefas de projeto exigem também `projects.view`. Novos colaboradores não recebem concessões automáticas.

As 16 preferências vivem exclusivamente no nó revisionado `company_calendar` de `issuers_registry.settings_json`, também na empresa principal. Nenhuma preferência é herdada das opções globais. Os valores iniciais desligam as seis fontes, usam limite 4 por dia, mês, segunda-feira e somente tarefas atribuídas ao usuário atual. O limite aceita 1–50; primeiro dia 0–6; cinco visualizações existentes; seis cores próprias em hexadecimal. O opt-in de tarefas habilita os adaptadores de projeto e avulso, preservando as permissões separadas. A cor de tarefas é uma preferência privada nova; não recupera a antiga opção removida. Não há campo de lembretes, calendário Google, eventos pessoais ou departamentos.

| Fontes | Data e título | Permissão além de calendar.view |
| --- | --- | --- |
| Faturas próprias emitidas | vencimento; número do snapshot | invoices.view |
| Orçamentos / propostas próprios | validade; número do documento canônico | estimates.view / proposals.view |
| Projetos próprios | vencimento; nome | projects.view |
| Tarefas do projeto próprio | vencimento; nome | tasks.view e projects.view |
| Tarefas avulsas próprias | vencimento; nome | tasks.view |
| Contratos próprios | vencimento; nome | contracts.view |

As entradas mostram datas inteiras e estados existentes. Não representam criação de eventos, horários, envio, cobrança ou transição de estado. As agendas também mostram “Dia inteiro”. Mês e semana limitam apenas os artigos visíveis por dia e informam a quantidade excedente; Dia e agendas mostram todas as entradas autorizadas. `default_view_calendar`, primeiro dia, cores, toggles, atribuição e limite são consumidos pela página, além de serem persistidos.

O datasource `format=json` aceita intervalo Y-m-d inclusivo com diferença máxima de 62 dias, cliente autorizado, trecho literal de título e subconjunto explícito das sete fontes. O cliente exige também `customers.view` e vínculo próprio/compartilhado atual; tarefas avulsas sem cliente são excluídas desse filtro. O título é filtrado somente depois de hidratar um documento autorizado. IDs candidatos são consultados sob empresa e ACL antes de qualquer conteúdo. Cada fonte usa seu leitor canônico; faturas e documentos comerciais validam snapshots e integridade originais; contratos ativados validam emissor, cliente, número, nome e datas do snapshot. Nenhum corpo, cliente, segredo ou snapshot bruto integra o DTO.

Há teto de 500 candidatos no intervalo, antes de filtrar o título. Acima disso a resposta falha integralmente com orientação para reduzir o intervalo/fontes. Não há paginação silenciosa ou contador parcial. Fontes privadas ausentes não podem ser habilitadas; não existe fallback para tabelas legadas.

Captura e confirmação usam transações próprias com o bloqueio nativo da instalação. A captura conserva IDs, hashes privados de linhas/snapshots/histórico e JSON completo das preferências. Após os leitores canônicos, nova captura exige o mesmo quadro e as mesmas permissões atuais. O controller confirma novamente depois de serializar ou renderizar, antes de entregar os bytes. Permissões dos controles de configuração e filtro de cliente também são reconfirmadas após a renderização. Alteração concorrente exige recarregar; não se entrega uma mistura de estados. Salvar confirma o JSON completo e o audit exato após a última escrita e reconfirma as permissões, com rollback em falha.

## Verificação

Entry points PHP: `tests/settings/calendar_preferences.php`, `calendar_read.php`, `calendar_controller.php`, `calendar_boundaries.php` em SQLite e MariaDB/MySQL; `calendar_concurrency.php` somente MariaDB/MySQL. `calendar_setup.php` e `CalendarHookDatabase.php` são suporte, não comandos de CI. Os testes usam bancos descartáveis, os writers canônicos apenas para criar dados sintéticos e transports de provedor ausentes. Os seis cenários de concorrência observam dois processos reais aguardando o bloqueio nativo.

`calendar_browser.cjs` usa HTTP/Chromium e os formulários reais de seleção, identidade e calendário. `calendar_http_fixture.php` exige o nome/host sintético e o marcador nativo atual, cria somente fontes sintéticas pelos serviços canônicos e inspeciona snapshots. Não configura o calendário, concessões, DDL ou marcador por SQL. A declaração BRL e onboarding A/B são pré-requisitos nativos do harness. A prova HTTP fica pendente da composição/versionamento e execução desse harness; checagem de sintaxe do CJS não equivale a uma prova de navegador.

Nenhum writer financeiro, módulo BB/Asaas, Ticket ou CompanyAI foi alterado. As configurações não enviam mensagens, não criam lançamentos financeiros e não mudam o significado das permissões de cada recurso. As opções globais e tabelas de fontes/finanças são verificadas como invariantes após as ações do calendário.
