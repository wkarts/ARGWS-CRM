# ARGWS-CRM — entrega local r8

Branch sugerida para revisão: `feat/native-company-closure-r8` (esta cópia local não contém metadados Git).

Título de PR local: `feat: compose remaining company operations into native controllers`

A entrega removeu a execução paralela das famílias especializadas. Os controllers `Company_*` administrativos e as views ativas sob `admin/companies` não participam mais do catálogo; as ações foram compostas nos controllers oficiais com rotas e verbos finitos. O shell, seleção, onboarding e links internos usam somente `Issuers`, `Projects`, `Tasks`, `Tickets`, `Invoices`, `Expenses`, `Settings` e `Staff`.

A entrega também registra a evidência r8, o inventário atualizado, o teste de composição e o estado local do CNAB 400. Não houve push, merge ou alteração no GitHub.

Os gates que exigem PHP, Docker/MySQL, Chromium, Ruby, Mix ou homologação documental permanecem explicitamente bloqueados e estão descritos em `docs/companies/native-experience-closure-local-2026-10-28-r8.md`.
