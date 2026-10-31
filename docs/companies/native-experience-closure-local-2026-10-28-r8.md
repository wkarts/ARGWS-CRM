# Fechamento local da experiência nativa — r8 (2026-10-28)

Este documento registra o estado do código local depois da composição das famílias especializadas restantes. Ele não declara homologação bancária, instalação MySQL ou execução HTTP/browser neste executor.

## Composição aplicada

- Os controllers `Company_*` administrativos executáveis e seus tombstones foram retirados fisicamente. A árvore final não contém arquivos `company_*`, sem distinção de maiúsculas/minúsculas; upgrades não recuperam rotas removidas.
- As views ativas de `application/views/admin/companies` foram retiradas da descoberta. As telas foram transferidas para pastas dos controladores oficiais (`projects`, `tasks`, `tickets`, `settings`, `staff`, `invoices`, `expenses`, `clients` e `includes`) e usam o shell nativo compartilhado.
- As operações especializadas foram compostas no trait `application/services/NativeCompanySpecializedOperations.php` e expostas por ações allowlistadas de `Projects`, `Tasks`, `Tickets`, `Invoices`, `Expenses`, `Settings` e `Staff`.
- A seleção e o onboarding usam `Issuers::select` e `Issuers::onboarding`. Não há retorno para `company_selection` ou `company_onboarding`.
- `CompanyRequestScope` aceita somente os métodos canônicos; o catálogo `NativeCompanyRoutes` define verbo por ação.

## Contagens verificadas

- Controllers `Company_*` ativos: **0**.
- Views ativas em `application/views/admin/companies`: **0**.
- Ações especializadas compostas: **38 métodos de trait**, distribuídos entre sete controllers oficiais.
- Inventário estático: **16 views e 6 controllers** descobertos nas superfícies auditadas.

## Banco do Brasil / CNAB 400

O fluxo local já está preservado e foi mantido fechado para efeitos financeiros implícitos:

1. preparação vinculada à fatura e ao emitente;
2. reserva de número e lote dentro da empresa;
3. declaração explícita de moeda, juros e referência de homologação;
4. geração persistida da remessa CNAB 400 e download local;
5. declaração de transmissão, avaliação do retorno e conciliação somente por revisão explícita;
6. baixa financeira apenas quando o operador fornece o vínculo e a evidência exigidos.

`Cnab400Generator::candidate()` continua retornando `available=true`,
`configurable=true`, `publication_blocked=false` e `operational=false`.
Esse último campo significa somente que uma remessa local não é tratada como
transmissão/aceite bancário. O fluxo por empresa permanece disponível sob ACL,
CSRF, idempotência e auditoria, não aplica pagamento apenas por guardar ou ler
retorno, e mantém como gates externos a confirmação documental do CBR641, a
homologação por conexão e o transporte/retorno real do banco.

## Validação local

`python3 scripts/validate-local.py` registrou:

- `node-contracts`: passed;
- `ui-inventory`: passed;
- `focused-ci-contract`: passed;
- `workflow-ownership`: passed;
- `php-runtime`: blocked — PHP ausente;
- `mix-build`: blocked — `node_modules/.bin/mix` ausente;
- `ruby-yaml-runtime`: blocked — Ruby ausente.

O inventário e a suíte Node não substituem renderização PHP, MySQL/Docker, Chromium, Ruby/Mix ou homologação documental. A evidência inicial está em `.continua-ai/evidence/local-validation-r8/local-validation.json`; o incremento r9 está em `.continua-ai/evidence/local-validation-r9/local-validation.json`.
