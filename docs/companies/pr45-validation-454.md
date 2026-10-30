# PR45 — Leads privados e preservação dos leitores financeiros legados (454)

## Escopo e sequência

Este incremento continua o commit publicado `83571303d46cdf5398cd9bd854085e628942ac60` (453), na branch `feat/banco-brasil-billing-cnab240`. O núcleo passa a **4.5.4 / migration 454**, pelo gerador oficial. Finance passa de 1.0.0 a **1.0.1 /101** e Accounting de 1.3.5 a **1.3.6 /136**, pois seus consumidores foram corrigidos; as migrations desses dois módulos somente verificam o próximo passo nativo, sem DDL. BB 1.2.3/123 e Asaas 1.5.3/153 permanecem nas versões anteriores.

O contrato integral anexado permanece em `continuity-contract-2026-10-21.md`. As entregas 445–453, migrations publicadas, identidade do master, empresa principal, privacidade de clientes e origem das integrações são preservadas. Este relatório comprova um incremento da sequência, não o encerramento integral de todos os módulos. Purchase 455, RH 456 e o fluxo positivo adicional de Finance/Accounting ainda não integram este commit.

## Operação positiva de Leads

O fluxo próprio permite cadastrar, editar, listar, filtrar, mover no Kanban, arquivar e restaurar Leads por empresa. Status e fontes possuem revisão e configuração próprias; responsáveis são revalidados na empresa. Notas, anexos privados, importação CSV com prévia e confirmação, exportação e caixa interna de avisos completam a cadeia GUI/API/worker deste recorte. A paginação e os limites são explícitos.

A conversão usa o escritor canônico de clientes na mesma transação do contato, recibos e auditoria. O cliente nasce privado. O contato nasce inativo, sem senha, reset, verificação, token de portal ou preferências de envio habilitadas. Descrição, status, notas e arquivos continuam no Lead, associado aos IDs convertidos; não há perda de histórico nem envio implícito. Conflitos e alterações tardias de origem, identidade, permissões ou confirmação SQL desfazem toda a composição.

São oito endpoints finitos e nove scopes, com rejeição antecipada de caminhos e IDs inválidos. A empresa vem do bearer persistido, sem override pelo payload. Os serviços revalidam token, ator, permissões e origem na transação e no worker. O endpoint de responsáveis admite somente GET/HEAD; o RED que aceitava outro verbo foi reproduzido e corrigido, mantendo 405/Allow nos demais métodos.

Os avisos entregues são da **caixa interna**. SMTP, IMAP, captura pública, campanhas, Connect/WhatsApp e Pusher ainda exigem composição interna específica com esses Leads e, para transporte real, configuração própria e validação autorizada. Tags, campos personalizados e consumidores que ainda consultam `tblleads` também não passam a operar nos registros próprios por este incremento. Nenhuma recusa do controller legado é contabilizada como disponibilidade dessas funções.

Contrato por operação, endpoints, limites e arquivos: `leads-454.md`.

## Finance/Accounting: defeitos reproduzidos e correções delimitadas

As consultas nativas podiam somar ou listar origens de empresas diferentes. Na fixture RED, a receita de Finance era 1500 em vez de 100, a despesa 450 em vez de 160 e a projeção 2460 em vez de 60. Accounting contava 9 faturas/10 pagamentos em vez dos 2/2 legados esperados. Conversões automáticas também podiam apagar histórico antes de validar a origem do documento.

Os leitores agora selecionam faturas com `issuer_id IS NULL`, pagamentos com uma fatura legada existente e despesas compatíveis com a fronteira de cliente e fatura vinculada. Origens 0, IDs de empresas e schemas parcialmente instalados não viram legado por fallback. Escritores revisados validam a origem antes do primeiro efeito. A limpeza mantém históricos de documentos existentes de qualquer origem, removendo somente o órfão real; filtrar a consulta de existência para tornar uma origem privada aparentemente órfã seria perda de dados e foi coberto por regressão.

São **158 verificações por runtime**: 122 de leitores/escritores, 12 de consultas DataTables e 24 de migrations nativas. Passaram MariaDB/PHP 8.3 e MySQL 8/PHP 8.1, incluindo prefixo alternativo, schema parcial, update ignorado, retry e preservação dos históricos. As seis consultas DataTables reais são capturadas antes de contagem/renderização com doubles de loader/input/hooks: isso não é prova HTTP da interface inteira. A instalação vazia original desses módulos e todos os seus fluxos ainda não foram exercitados. O trabalho positivo de orçamento/projeção por empresa é uma entrega posterior.

Detalhes e evidência independente: `finance-accounting-legacy-inputs.md` e `evidence/finance-accounting-454.json`.

## Upgrade e instalação limpa reais

### Instalação 453 existente → 454

O teste original `tests/issuers_browser_smoke.cjs` executou login e POST nativo de upgrade com CSRF sobre a instalação 453 já exercitada por equipe e recorrência. Passou sem redesignação do master. A comparação posterior somente de leitura confirmou **926 condições**, preservando as **298 tabelas históricas**, a identidade do master 17 e a principal 1. A migration acrescenta exclusivamente as nove tabelas de Leads.

Os deltas esperados foram discriminados: marcador 453 → 454; três opções nativas de atualização; cinco eventos exatos de tracking de Settings; um login nativo; timestamps/IP desse master; e uma sessão autenticada local adicional, com as 26 sessões anteriores byte-exatas. A primeira versão do comparador esperava duas novas sessões, contagem específica de outro fixture 453. A investigação confirmou uma sessão nesta execução e ajustou essa qualificação exata, sem repetir login/upgrade, resetar a base ou aceitar qualquer sessão genérica.

O backup 453 anterior ao upgrade foi preservado e restaurado separadamente para comparação: SHA256 do arquivo compactado `017cbeb6612511a0c0963982bb0da9b2326723f99b38b6161bcac90e84347d37`. O conteúdo normalizado restaurado coincide com o dump original. A captura auxiliar de um XHR `tasks/table` perdeu o corpo quando o browser fechou; ela não é usada como prova retrospectiva de JSON válido. O resultado positivo do teste original e a comparação SQL são evidências distintas.

Depois da conferência de preservação, o navegador de Leads passou **dez cenários completos** em A/B/principal: GUI/Kanban/configuração, CSRF nativo/próprio, replay e revisão antiga, notas/arquivos/IDs cruzados, conversão privada, CSV, worker/inbox, membro somente leitura, API e revogação, e preservação byte-exata de Leads/finanças legados. Não houve outro upgrade ou reset.

### Instalação 454 vazia

O instalador nativo concluiu em 6,477 s: POST válido 200, CSRF inválido 403 sem criar schema, marcador 454, master 17:installer e principal 1. As rotas HTTP de bootstrap continuaram 404, com funções de processo shell desabilitadas. O teste original de navegador fresco concluiu em 10,368 s, incluindo shell/menu, desktop/mobile, falha nativa de schema, correção/retry, recuperação com o master já identificado em uma empresa secundária e manutenção da seleção/identidade.

Duas tentativas anteriores do browser não chegaram ao login porque a sincronização do workspace restaurou a pasta do instalador que o próprio instalador havia mandado retirar. A proteção nativa respondeu “Delete the install folder”. A cópia sintética foi deslocada para `/tmp`, com 19.937 arquivos comparados por hash, mesmo banco e configuração preservados, sem alterar a proteção do produto. Só então o mesmo teste original passou. Essas tentativas não são contadas como aprovações. O harness local usa PHP-S/Chromium e um adaptador finito de CLI, não Docker; o CI remoto exercita seu próprio provisionamento.

## Testes de domínio e integração

| Conjunto | Resultado e alcance |
| --- | --- |
| Leads SQL | 54 de domínio + 20 de worker + 30 de integridade + 18 de concorrência = **122 por runtime**, em Maria 10.11.14/PHP 8.3.6 e MySQL 8.0.46/PHP 8.1.2, com 128 MiB. Corridas usam processos independentes e waiters SQL observados. |
| API/trait | 29 de API integrada e 33 de métodos HTTP por runtime. A recorrência API 453 preservou 76 verificações próprias e 36 de sua base operacional. |
| Bootstrap | 115 verificações novas de caminhos, routes, CSRF e scopes, além das 80 anteriores: 195 cumulativas. Caminhos não documentados não entram no despacho autorizado. |
| Migration 454 | 1929 verificações próprias por runtime no runner nativo: predecessor 453 real, nove tabelas aditivas, falha DDL, tracking falho/ignorado, rollback/retry, replay e dados históricos preservados. |
| Upgrade HTTP | Teste original positivo e comparação de 926 condições/298 tabelas históricas, seguida da GUI/API de Leads. |
| Instalação HTTP | Instalador nativo 454 e teste original fresco com recuperação e master existente, em banco independente. |
| API sem sessão | 58 verificações HTTP adicionais, contexto vazio por requisição, bearer como única autoridade e tabela de sessões byte-exata. O cookie CSRF nativo de resposta não foi reenviado. |
| Finance/Accounting | 158 por runtime, com os limites de captura DataTables e instalação modular explicitados acima. |

As primeiras execuções SQL de migration 454 completaram 1929 condições, mas terminaram com exit 255 ao limpar a fixture: faltava remover os filhos de Leads antes dos pais API/contatos. O cleanup passou a respeitar essas FKs e as duas execuções completas seguintes terminaram com exit 0. Não se atribui PASS ao comando inicial incompleto.

O CI 453 também detectou uma fixture histórica do worker de despesas sem as tabelas da recorrência de tarefas 453. O controlador completo corretamente recusou o schema incompleto. `recurring_cli_prerequisites.php` prepara os schemas reais vazios de tarefas/Leads e seus participantes; nenhuma asserção financeira antiga foi removida, nenhum processador foi substituído por mock e nenhum guard de produção foi relaxado. O teste completo passou com 194 verificações cumulativas em cada motor, executando os cinco processadores e confirmando zero registros de negócio nos namespaces operacionais vazios. Positivos dos workers continuam sendo suas suítes próprias, não esses contadores zero.

Os 101 testes Python de versões, empacotamento, convenções de migrations, inventário e armazenamento passaram em 25,528 s. A sintaxe do shell e o bootstrap PHP 8.1 também passaram.

Os inventários foram regenerados sobre uma composição exclusiva 454, sem WIP de Purchase/RH. Permanecem 38 módulos, 684 opções literais e 145 referências dinâmicas. Há 14.544 arquivos de fronteira, 11.908 candidatos a consumidores e 533 arquivos no inventário de isolamento; os candidatos lexicais não são contagem de bugs ou de funcionalidades. O estado integral continua `operational_ready=false` / rollout bloqueado. O gate que exige prontidão integral deve continuar recusando o estado enquanto houver pendências.

Hashes de fontes, logs, provas, qualificações de tentativas e resultados novos estão em `evidence/resume454-current.json`. O SHA publicado deve ser conferido na PR e na ref remota, com a árvore correspondente. O resultado de CI do 453 não é transferido ao 454.

## Continuidade ainda necessária

**Interna multiempresa:** Purchase→RH e submódulos instalados, Finance/Accounting positivo e demais módulos; paridade por ação de projetos/tarefas/portal/relatórios; consumidores de Settings ainda não classificados/adaptados; transportes e automações de Leads ainda ausentes. O inventário e os bloqueios atuais não encerram essas tarefas.

**Interna bancária:** reversão da base tarifária 240; composição tarifa/juros/desconto/abatimento/IOF/outros/parciais e adapters; alteração nominal com revisão financeira/fiscal; protesto/sustação/cancelamento/negativação com outbox/estados e confirmação independente. Este incremento não implementa essas pendências e não as chama de mera homologação.

**Externa específica:** BRL no CBR641 posições 85–86 aplicável ao contrato; taxa efetiva ou isenção dos juros herdados 400; errata de largura/escala e totalizadores do CBR643 posições 26–39; identidade estável/composição/datas/reversões da API 18/19; domínios/exemplo normativo do PATCH juros/multa; protocolo/endpoint/autenticação/recibo de transporte. A solução do trailer CBR641 já entregue permanece preservada. Não houve operação bancária real, merge, deploy, tag ou force-push.
