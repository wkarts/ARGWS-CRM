# PR 52 — evidência consolidada da continuação

Data desta validação: 2026-10-31 (UTC). Repositório: `wkarts/ARGWS-CRM`.
PR: `#52`. Branch: `fix/native-company-experience`.

## Reconciliação

O HEAD remoto usado como pai desta continuação foi o SHA
`98732e73a7351267f88b0c9bf0247f1151425fa8`. A cópia de trabalho fornecida
para esta conversa não contém `.git`; por isso `git status`, `git diff` e
`git log` locais não puderam produzir uma fotografia válida. A branch e o
parent foram consultados pela integração do GitHub antes da publicação. Não
foi feito reset, rebase, checkout destrutivo ou force-push.

## Implementação verificada no código

- Clientes: a composição nativa passou a reconhecer as seções legítimas de
  notas, extrato, pagamentos, despesas, contratos, tarefas, estimativas,
  propostas, créditos, assinaturas e lembretes. Extrato e pagamentos usam
  dados reais, paginação/filtro, ACL, CSRF e contexto da empresa; notas têm
  escritores próprios.
- Projetos: as abas originais aplicáveis são expostas pelo catálogo nativo,
  incluindo notas, despesas e relações de contratos, faturas, tickets,
  estimativas, créditos, assinaturas e propostas. Tarefas, cronômetros,
  arquivos, portal e lotes continuam nas rotas originais e no escopo da
  empresa. Fixar projeto usa um ledger próprio e não o mecanismo de seguir.
- Paridade HTTP: as 44 ações auditadas continuam em seus controllers e
  rotas oficiais, com contratos delimitados. ZIPs de documentos carregam
  PDFs reais; o manifesto permanece metadado. Extrato preserva o campo HTML;
  edição de nota altera nota; fixação altera fixação; `cancelTask` captura o
  input; lotes calculam revisão e chave por arquivo. Essas verificações
  demonstram o contrato estático e a composição, não substituem HTTP
  autenticado com dados reais.
- Concorrência: transações, ACL, CSRF, idempotência, revisão/CAS e conflito
  409 permanecem. A revisão de lote não é reutilizada depois de incrementada.
- PWA: a projeção pública escolhe identidade explícita da instalação, depois
  empresa principal, depois V2. Deriva 192, 512, maskable 512, Apple Touch e
  favicons PNG com dimensões validadas, grava arquivos e manifesto
  atomicamente, calcula hashes/revisão e não sobrescreve uma marca principal
  publicada quando um ator secundário não tem ACL para lê-la. O worker não
  persiste dados privados nem cria convite de instalação.
- V2: os assets públicos de favicon, PWA e logos conferidos são byte-a-byte
  iguais aos arquivos selecionados em `identidade-v2/`. Logos de empresa e de
  instalação continuam prioritários nos pontos de personalização; V2 é
  fallback padrão.
- Build: `npm run build` mantém os dois passos do Mix e agora interpreta a
  versão canônica de migration `483` como `4.8.3` de forma robusta, sem
  depender de um comentário com formato frágil. O passo principal e o módulo
  e-invoice compilam. Como o ajuste do parser é funcional para o gate do
  módulo, o e-invoice foi versionado para `1.0.2` com a migration nativa `102`
  explícita e NOOP; a migration histórica `101` não foi alterada.
- O fixture HTTP PWA inicializa `APPPATH` antes de carregar a mesma view de
  produção. Assim o gate browser valida o manifest real em `/` e `/crm/`, em
  vez de depender de uma página sintética sem os links PWA.

## CNAB400

CNAB400 continua disponível e configurável por empresa, sem habilitação global
automática e sem bloqueio de publicação da aplicação. A política mantém
BB API, CNAB240/400, Asaas e Efí, com validações de empresa, conta, convênio,
layout, origem, fatura, moeda, autorização, integridade e idempotência.

`bb400_homologation_required` continua sendo uma condição operacional na
geração, download e conciliação do candidato: sem a declaração exigida, o
arquivo não é gerado/baixado como operação confirmada. Isso não torna o módulo
indisponível, não simula homologação e não autoriza transmissão ou baixa
financeira automática. A política registra `available=true`,
`configurable=true`, `operational=false` e `publication_blocked=false` para o
candidato local.

## Matriz

`docs/companies/pr52-official-action-matrix.json` agora registra 331 métodos,
316 admitidos e 15 mantidos fora do catálogo, com `full_parity_approved=false`.
As 15 restantes têm justificativa individual:

| Classificação | Ações | Justificativa |
| --- | --- | --- |
| Internas (10) | `Contracts.get_template`; `Expenses.table`, `get_expenses_total`, `get_customer_change_data`; `Invoices.update_number_settings`, `get_merge_data`, `get_bill_expense_data`; `Projects.timesheet_task_assignees`, `get_pre_invoice_project_info`, `get_invoice_project_data` | Callbacks internos de editor, seleção, totalização ou preparação; não são telas/ações de empresa independentes e continuam sujeitos à composição e autorização do fluxo pai. |
| Não aplicáveis (5) | `Clients.login_as_client`; `Projects.view_project_as_client`; `Settings.delete_option`, `Settings.clear_sessions`; `Staff.change_language` | Impersonação, limpeza global da instalação ou política global de idioma não podem ser concedidas a uma empresa secundária. Portal e sessões continuam com autenticação própria. |

As classificações não são contadas como paridade funcional aprovada. As ações
admitidas e as 44 ações necessárias permanecem dependentes dos testes HTTP,
instalação e dados reais do CI.

## Limpeza e preservação

Os sete descartáveis da auditoria não existem na cópia final:

- `upload/image(7).png` e `upload/image(8).png`;
- `application/helpers/misc_helper.php.bak`;
  `application/helpers/assets_helper.php.bak`;
  `application/controllers/Cron.php.bak`;
  `application/controllers/admin/Emails.php.bak`;
  `application/language/portuguese_br/portuguese_br_lang.php.bak`.

Não foram reintroduzidos `company_*` alternativos, uploads legítimos,
proteções `.htaccess`, fontes, vendor ou migrations históricas. A paginação de
pagamentos, os filtros de empacotamento e a política de cache permanecem
preservados. Nenhuma limpeza remota de cache é declarada; apenas o workflow e
o código da branch são considerados.

## Testes executados

| Comando | Resultado |
| --- | --- |
| `node --test tests/native_pr52_parity_catalog.test.cjs tests/native_pwa.test.cjs` | 16/16 passou |
| Suíte Node selecionada para paridade, PWA, e-mail, shell, settings, composição, tarefas e workspace | 49/49 passou |
| `python3 tests/ci/test_native_company_focused.py` | 10/10 passou, incluindo casos negativos sintéticos |
| `python3 tests/test_native_pwa_http.py` | 3 testes reconhecidos, pulados por servidor PHP/browser indisponível |
| `node --check tests/settings_pusher_recovery_browser.cjs` | passou |
| `python3 -m unittest discover -s tests -p 'test_module_migration_diff.py'` | 44/44 passou |
| `node --check tests/native_pwa.browser.cjs` | passou |
| `PATH="$PWD/node_modules/.bin:$PATH" npm run build` | passou nos bundles principais e no módulo e-invoice |

PHP, Docker/MySQL/MariaDB, Chromium/Playwright e o servidor autenticado não
estão instalados neste workspace. Portanto não são alegados aqui instalação
limpa, upgrade real sem perda de dados, login, empresas principal/A/B,
usuário restrito, HTTP autenticado, renderização PDF ou confirmação de CI.
Essas validações continuam gates necessários da revisão; testes sintéticos não
são homologação externa.

## Estado de publicação

O SHA publicado e o resultado dos checks correspondentes devem ser lidos na
descrição atualizada da PR e conferidos diretamente no branch head. Este arquivo
deliberadamente não antecipa um SHA intermediário nem transforma checks
pendentes em aprovação. A candidatura à revisão para merge só é recomendada
depois de CI verde no SHA publicado e da aceitação dos gates HTTP/instalação;
CNAB400 não é bloqueio por falta de homologação externa isolada.
