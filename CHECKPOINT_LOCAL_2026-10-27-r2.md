# Checkpoint local r2 — 2026-10-27

## Escopo

Continuação local do contrato `docs/companies/native-experience-closure.md` para a PR 52. Nenhuma ação foi enviada ao GitHub, nenhum merge e nenhum deploy foram executados.

## Estado verificado

- Clientes, Leads e Settings usam os controllers/rotas oficiais (`admin/clients`, `admin/leads`, `admin/settings/all.php`) nos fluxos nativos já implementados.
- Os controllers paralelos `Company_clients.php` e `Company_leads.php` não possuem mais classe executável.
- Os fragments antigos `companies/leads_fields.php` e `settings/company_scoped.php` estão apenas como tombstones `Removed`; não entram no inventário ativo e não podem emitir markup.
- O inventário está sincronizado em 93 views ativas e 51 controllers/endpoints, com 44 controllers `Company_*` executáveis ainda pendentes de migração para rotas oficiais.
- O censo `docs/companies/native-experience-action-census.json` foi regenerado contra os controllers atuais e não contém os dois controllers removidos.
- O manifesto `tests/fixtures/company_ui_surfaces.json` foi reconciliado: 93 views e 51 controllers, sem owners apontando para superfícies removidas.
- Banco do Brasil CNAB 400 já possui preparação vinculada à fatura, reserva idempotente do número, snapshot, geração do arquivo CBR643, download/auditoria, declaração de envio, parser de retorno, comparação e baixa assistida com revisão independente. A aplicação continua exigindo homologação e confirmação humana; não há transmissão bancária automática.

## Validações executadas

- `python3 tests/company_ui_surface_inventory.py` — PASS (93 views, 51 controllers).
- `python3 tests/ci/test_native_company_focused.py` — PASS (10 testes).
- `node --test tests/native_customers_contract.test.cjs tests/company_navigation_tabs.test.cjs tests/company_settings_native.test.cjs` — PASS (18 testes).
- `node --test tests/*.test.cjs` — 219 PASS; 3 testes ficaram bloqueados por `spawnSync php ENOENT` em `tests/native_header_overflow.test.cjs`. O workspace não possui PHP, Docker, MySQL/MariaDB, Playwright instalado ou `node_modules` para o build Mix.
- `node --check` nos browsers alterados — PASS.
- A validação YAML completa não pôde ser executada porque `ruby` não está disponível; o diff do workflow foi revisado estaticamente.

## Pendências reais

1. Ainda existem 44 controllers `Company_*` executáveis e suas views especializadas. Eles não foram apagados porque não existe, para todos, uma implementação oficial equivalente comprovada; removê-los agora quebraria fluxos de RH, integrações, faturamento especializado, portal e operações financeiras.
2. A prova final PHP/MySQL/Chromium (Settings 422, upgrade histórico BB, troca principal/secundária, deep links, permissões, arquivos e responsividade) precisa rodar no container de CI com PHP 8.1/8.5 e MySQL.
3. O build Mix precisa de `npm ci`/`node_modules`.
4. Homologação documental do layout CNAB 400 e confirmação bancária continuam pré-requisitos para considerar uma baixa financeira automática; o código mantém a baixa assistida explícita.

## Próxima ação determinística

Migrar o próximo conjunto de controllers especializados para os controllers oficiais correspondentes, começando por uma família completa (Projetos/Tarefas ou Faturas/Portal), adicionando os adapters nativos, removendo a rota paralela somente após testes positivos/negativos e executando o lote PHP/HTTP no runtime de CI.
