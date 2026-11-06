# PR 61 — regressões nas definições por empresa

## Contexto, objetivo e escopo

Correção incremental sobre `c6ec9da024cf5a77846eff56ad0136b3b1846c5e`, na PR
https://github.com/wkarts/ARGWS-CRM/pull/61, branch remota
`fix/native-402-experience-main`. Base observada:
`3cd2ac577a969840c94b019f602cb69d710c0e78` (`main`). Trabalho preparado na branch
local isolada `fix/pr61-settings-isolated`. Preservado o incremento core
**4.8.9 / 489**, já presente sobre 4.8.8; nenhuma migration histórica alterada.

O relato de navegação na principal foi fornecido pela conversa coordenadora.
Os resultados abaixo foram reproduzidos em fixtures locais; não houve consulta
nem alteração do banco de produção. Não se infere a configuração efetivamente
armazenada em produção apenas a partir da mensagem genérica.

## Causas, decisões técnicas e alterações

| Rota | Evidência e resultado |
| --- | --- |
| `settings?group=email` | A principal era encaminhada ao adaptador que rejeita explicitamente `primaryId === issuer`, inclusive na fila. Incluída somente a principal no despacho de compatibilidade para seu formulário/escritor original. Guard dos serviços de secundárias preservado. |
| `settings?group=invoices` | Mesmo erro de despacho, comprovado no serviço `NativeCompanyInvoiceSequencePreferences`, que reserva a principal ao escritor original. Corrigida a lista de compatibilidade, sem mudar sequências, faturas ou snapshots históricos. |
| `settings?group=google` | Reproduzida `native_calendar_google_invalid` ao faltar uma das duas opções opcionais `google_api_key` / `google_calendar_main_calendar`. Os defaults públicos de instalação são vazios. GET agora representa ausência como vazio, sem gravar; POST explícito usa o guard existente. Duplicidade e valor inválido continuam rejeitados. Não há fallback da secundária para opções globais. |
| `settings/google_mail_oauth` | `view()` chamava a validação obrigatória do remetente e falhava antes de renderizar configuração incompleta. Leitura agora permite remetente indisponível e a view orienta a configurar email; salvar/autorizar/transportar continuam exigindo remetente válido. |
| `settings/microsoft_mail_oauth` | Reproduzida separadamente a mesma exigência indevida na leitura Microsoft. Mesma separação estreita entre leitura e operação; identidade, tenant, tokens, epochs e validações de escrita preservados. |
| `settings/efi` | GET e salvamento válidos na principal passaram com banco real descartável. Reproduzido que módulo inativo ou marcador de versão incompatível gera `efi_company_module_required`, antes classificado como 403/acesso revogado. Agora recebe 503 com orientação de ativação/atualização; ACL realmente negada mantém 403. **Não foi confirmado qual desses estados existe na instalação relatada.** Não ativamos módulo, alteramos versão instalada nem removemos seu guard. |
| `banco_brasil/save_channel_preferences` | Nenhum defeito de escrita reproduzido. É comando POST, não página de configuração. GET é rejeitado pelo middleware (403) e diretamente pelo controller (405), sem escrita. POST exige contexto, CSRF, ACL e revisão. Testes cobrem rejeição de contexto obsoleto/empresa errada e preservação de API/CNAB, histórico e dados de outra empresa. Código do módulo intocado. |

Os campos cadastrais vazios relatados posteriormente não foram preenchidos nem
inferidos. Sua origem exige examinar o cadastro da instalação; este incremento
não inventa identidade fiscal nem migra dados automaticamente.

## Arquivos

Modificados:

- `application/services/NativeSettingsCompatibility.php`
- `application/services/NativeCompanyGoogleCalendarPreferences.php`
- `application/services/CompanyGoogleMailOAuth.php`
- `application/services/CompanyMicrosoftMailOAuth.php`
- `application/services/NativeCompanySpecializedOperations.php`
- `application/views/admin/settings/native_google_mail_oauth.php`
- `application/views/admin/settings/native_microsoft_mail_oauth.php`
- `.github/workflows/company-native-ui.yml`
- `.github/workflows/issuer-foundation.yml`

Adicionados:

- `tests/settings/primary_settings_dispatch.php`
- `tests/settings/primary_settings_scope.php`
- `tests/native_company/google_settings_missing_options.php`
- `tests/settings/oauth_unconfigured_sender.php`
- `tests/settings/oauth_configuration_http.php`
- `tests/settings/bb_channel_http_boundary.php`
- `tests/settings/efi_primary_configuration_mysql.php`
- Este documento.

Removidos: nenhum. `CompanyContext.php`, `NativeCompanyRoutes.php`, `assets/js/main.js`
e os testes browser existentes em edição concorrente foram preservados.

## Testes implementados e reprodução anterior

- Despacho: teste falhou antes da correção com `Primary email must retain its original writer`.
- Google: teste falhou antes com `native_calendar_google_invalid` na leitura da principal sem as opções.
- Cada OAuth: execução separada falhou antes com `company_google_mail_sender_required` / `company_microsoft_mail_sender_required` em `view()`.
- Efí: controller real falhou na expectativa de distinguir indisponibilidade do módulo de ACL; antes retornava 403. Após correção retorna 503.
- Middleware real verifica contexto válido/obsoleto, despacho principal e BB GET/POST. Escritor original de settings salva email/contador da principal com armazenamento real sem modificar a secundária.
- Controllers OAuth reais verificam GET/POST da principal/secundária, CSRF inválido, revisão obsoleta, falta recuperável de remetente e ACL negada.
- Controllers usam I/O de framework sintético. Banco SQLite/MariaDB é real e descartável; criptografia e provedores são fakes. Nenhuma chamada real de OAuth, SMTP, Efí ou BB.

## Testes executados

Ambiente: PHP 8.4.24 extraído em `/tmp`, SQLite e MariaDB 11.8.6 em socket local,
sem rede habilitada no servidor de banco. Chromium `/usr/bin/chromium` com Playwright.

Aprovados (comando `php <arquivo>`):

- Os sete testes novos listados acima; o teste Efí exige DSN MySQL descartável.
- `tests/native_navigation_parity.php`
- `tests/native_context.php`
- `tests/companies/native_settings_controller.php`
- `tests/native_company/settings_google.php`
- `tests/native_company/settings_email.php`
- `tests/native_company/settings_email_transport.php`
- `tests/native_company/mail_oauth_endpoint.php`
- `tests/settings/google_mail_oauth.php`
- `tests/settings/resume_439_microsoft.php`
- `tests/banco_brasil_admin.php`
- `tests/companies/bb_channel_preferences.php`
- `tests/companies/native_invoice_sequence_preferences.php` (SQLite)
- `tests/efi/native_accounts_contract.php`
- `tests/efi/native_controller_contract.php`
- `tests/companies/efi_company_checkout.php` (MariaDB; 64 verificações adicionais, transporte fake).
- `tests/native_company/google_settings_missing_options.php` também no MariaDB.

`node --test tests/native_settings_native.test.cjs tests/native_specialized_composition.test.cjs`:
10 testes aprovados.

Browser (`node <arquivo>`, com `CHROMIUM_PATH=/usr/bin/chromium`):

- `tests/native_settings_native_browser.cjs`
- `tests/native_settings_layout.browser.cjs`
- `tests/efi/native_ui.browser.cjs`

Os três passaram com renderização PHP real, assets locais e fixtures sintéticas.
Não equivalem a navegação autenticada ponta a ponta em uma instalação completa.

Gates locais também aprovados: `php -l` nos 14 PHP modificados/adicionados,
`git diff --check`, `bash scripts/check-release-version.sh --base <main> --head <commit>`
e `python3 scripts/check-module-migration-diff.py --base <main> --head <commit>`.
O gate confirmou 4.8.9 = migration 489 e validou os módulos api/webhooks já
alterados pelo restante da PR, sem mudança adicional nesses módulos.

## Limitações e testes pendentes

- `tests/companies/efi_operational_mysql.php` tentou executar, mas MariaDB não
  fornece `performance_schema.data_lock_waits` requerido pela suíte MySQL 8.
  A suíte de concorrência completa permanece pendente no CI/MySQL 8.
- `native_invoice_sequence_preferences.php` usa triggers SQLite; a tentativa
  em MariaDB falhou por sintaxe incompatível, e sua execução SQLite passou.
- A primeira execução MySQL usou nome de banco fora da allowlist do cleanup;
  os checks funcionais passaram mas o processo falhou no cleanup. As suítes
  aplicáveis foram repetidas com `issuer_tests` e terminaram com código zero.
- A primeira tentativa browser não encontrou Chromium do cache Playwright;
  repetida com o Chromium instalado, com sucesso.
- CI do SHA publicado deve ser consultado separadamente; resultados locais
  não são aprovação remota. A API GitHub retornou `Forbidden` neste ambiente
  durante a leitura inicial da PR; o Git remoto permaneceu legível.
- Falta comprovar o estado real do módulo Efí e dos campos cadastrais na
  instalação relatada. Uma indisponibilidade legítima de módulo permanece
  bloqueada, agora com diagnóstico específico.

## Banco, APIs, dependências e segurança

Sem DDL, seeds, versões de módulos, mudanças de dependências ou variáveis de
produção. Google cria opção ausente somente em POST autorizado pelo guard
existente; GET é somente leitura. O despacho legado está restrito à principal
com identidade comprovada e contexto selado; conserva o contrato legado de
escrita, não introduz revisão agregada nova nesse contrato. Secundárias mantêm
seus validadores/revisões. Tokens, credenciais e histórico não são expostos.

As rotas e payloads são preservados. Apenas a indisponibilidade reconhecida do
módulo Efí muda de 403 para 503. Provedores, filas e callback não foram relaxados.
Sem impacto estrutural de desempenho; nenhuma consulta de provedor adicionada.

## Validação manual recomendada

Em instalação de homologação descartável, abrir as sete rotas com principal e
secundária; salvar configurações sintéticas; repetir formulário obsoleto e
CSRF/contexto inválidos; verificar dados e auditoria de ambas as empresas.
Em Efí, comparar módulo ativo/atualizado com inativo/desatualizado; observar 503
específico no segundo caso. Abrir BB pelo índice e salvar pelo formulário POST;
nunca usar GET como comando de alteração. Validar OAuth sem remetente, depois
com remetente sintético, sem iniciar consentimento ou envio externo.

## Impactos, deploy, rollback e distribuição

Correção compatível de despacho/configuração, sem migração de dados. Nenhum
merge, deploy, tag ou release é parte desta execução. Quando a PR for revisada,
seguir o pipeline normal da release 4.8.9 já preparada. Reverter apenas o commit
deste incremento restaura o comportamento anterior sem desfazer migrations ou
histórico. Não reverter indiscriminadamente os demais commits da PR compartilhada.
Sem novo build de assets: não há alteração JS/CSS. Documentação: este arquivo.

## Checklist e proposta de PR

- [x] Mudanças mínimas revisadas; guards e isolamento preservados.
- [x] Regressões reproduzidas antes e testadas depois.
- [x] Testes locais, limitações e diferenças de ambiente registrados.
- [x] Core 4.8.9/489 preservado; sem alteração de produção.
- [ ] CI do SHA remoto final confirmado.
- [ ] Estado Efí/cadastro da instalação relatada comprovado em homologação.

Branch remota: `fix/native-402-experience-main` (PR 61 existente).
Título do incremento: `fix(settings): restore primary forms and recover incomplete configuration`.
Descrição: correções e evidências deste documento, acrescidas à descrição existente
sem remover o escopo da outra implementação.
Commit: `fix(settings): restore primary forms and recover incomplete configuration`.
Merge sugerido: nenhum nesta execução; aguardar revisão da PR existente.
Versão: 4.8.9/489, sem novo incremento arbitrário.

## Follow-up do inventário após CI de 1d8dad50

O workflow [Barreiras de escopo de faturas, run 42135235219](https://github.com/wkarts/ARGWS-CRM/actions/runs/42135235219)
falhou no job inventory 127621577403: hash antigo de
`NativeCompanySpecializedOperations.php` e fronteira nova
`NativeSettingsCompatibility.php` ainda não inventariada. O resultado foi
comunicado pela conversa coordenadora, que também verificou os 11 unittests e
os jobs core MySQL PHP 8.1/8.5 aprovados nesse run; isso não significa aprovação
integral da PR nem de um SHA posterior.

Procedimento seguido: `docs/invoice-issuer-core-scope.md` e
`docs/companies/core-transition.md`. O gerador oficial
`python3 scripts/audit-issuer-isolation.py --write` atualiza hashes/linhas e
reinicia arquivos alterados como `unreviewed`. Registrada evidência da revisão
limitada nas duas entradas, **mantendo ambas unreviewed**: despacho restrito à
principal, manutenção dos guards de secundárias e alteração exclusivamente do
diagnóstico de módulo Efí. Não se declarou revisão completa dos escritores
legados nem de todo o trait de operações especializadas.

Também regenerado pelo comando oficial
`python3 scripts/inventory-company-boundaries.py`, sem editar exclusões, regras,
gates ou estados de aprovação: 15.239 arquivos continuam não revisados,
`operational_ready=false`; somente o fingerprint da árvore muda.

A tentativa `python3 scripts/register-company-consumers.py` falhou por evidência
órfã preexistente: `application/controllers/admin/Settings.php::isolated_index`.
Foi comprovado por `git show` que o método já não existia em `c6ec9da0`, antes
deste incremento. `consumer-register.json` e `consumer-evidence.json` foram
preservados: remover ou transferir aquela evidência exigiria revisar a migração
anterior do consumidor, não simplesmente apagar o bloqueio. Essa limitação
não foi apresentada como geração aprovada.

Validação do follow-up: os 11 testes de
`python3 -m unittest tests/test_issuer_isolation_inventory.py tests/test_invoice_attachment_storage.py`
passaram. O audit normal retorna 684 arquivos, 655 unreviewed, 29 partial,
zero erros e `operational_rollout=blocked`; `--assert-ready` foi executado e
continuou falhando com a mensagem de bloqueio operacional. Os cinco testes de
`test_company_boundary_inventory.py` e `test_company_consumer_register.py` também
passaram. Reexecutados os testes `primary_settings_dispatch.php`,
`primary_settings_scope.php` e `efi/native_controller_contract.php`, aprovados.
Nenhum código funcional, schema, versão, tag ou configuração de produção mudou
neste follow-up. A aprovação remota do novo SHA precisa ser observada separadamente.
