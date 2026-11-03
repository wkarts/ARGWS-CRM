# Native dashboard adapter — integration work in progress

This change is not a deployable or visually validated dashboard by itself. It reuses the existing Dashboard controller and thirteen native widget partials; it does not introduce a second panel or company landing route.

## Data contracts

- The sealed native request must supply an authenticated actor and current issuer. No valid selection renders the same dashboard route without querying dashboard data. Native route admission and header selector remain central-owned.
- Counters read explicit issuer-owned project/task/ticket/lead tables. Missing schema throws; denied features do not return fake zero balances. Permission and persisted principal identity are rechecked before emission.
- `NativeCompanyInvoiceRead::summary` supplies the original invoice total template. Principal combines named canonical records and native NULL lineage under explicit currency/year boundaries; secondary companies never include NULL.
- Payment charts retain current effective receipts grouped by original receipt date. They are not bank cash-flow history. Exact integer minor-unit aggregation preserves reversals without inventing receipt dates. Principal legacy receipts are marked separately as locally recorded, not bank-verified.
- `NativeCompanyAuxiliaryUi` supplies personal todos, announcements, accessible reminders and sanitized audit activity; `NativeCompanyCalendar::dashboard` supplies native event DTOs without Google HTTP reads.
- Native task timer DTOs contain encoded task and timer IDs, explicit total_seconds and revision. Ownership joins and actor filters apply before and after reading.

## Integration dependencies and outstanding work

This isolated branch has not copied other workers' services. Central must compose the InvoiceRead, Auxiliary, Calendar, ResourceId and native request bootstrap commits. Header/assets/menus, staff dashboard preference endpoints, native calendar filter/template adapters, and resource-scoped native endpoints must be complete before enabling admission.

Remaining work in this branch includes complete primary legacy widget lineage, the native Projects/Tasks/Tickets controllers and dependent mutation contracts, owned native status/department metadata, exact navigation links, preservation of dashboard order/preferences, and rendered A/B/primary/no-selection tests. Existing owned status enums must not be silently recoded as different native states. Unauthorized empty lists are not evidence of a missing authorized feature being implemented.

## Validation

Implemented tests: real PDO/IssuerResourceAccess A/B counters and revocation, and pure PHP exact payment-series projection including invalid dates/amounts/periods. Executed with isolated official Debian PHP 8.4.26: PHP lint passed; payment projection 11 checks, pure preferences 11 checks, PDO SQLite A/B counters 13 checks, assignment schema/replay/ACL 23 checks, native timer lineage 7 checks and persisted preferences isolation/rollback 8 checks passed. Four Node source-contract tests passed. MySQL, CI PHP 8.1/8.5 and browser rendering remain unexecuted; SQLite does not establish MySQL/InnoDB concurrency or visual correctness.

No provider request, financial mutation, production action, remote publication, merge, or deploy was performed by this slice.
