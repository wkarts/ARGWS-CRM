# Native invoice payment-mode choices

`NativeCompanyInvoicePaymentChoices` reads the selected issuer's actual offline
catalogue and active supported modules. It never calls a gateway, decrypts a
credential, chooses a bank account, or issues a payment. Offline IDs remain
canonical numeric IDs; `asaas`, `banco_brasil`, and `gerencianet` remain their real
gateway identifiers. No global gateway options are inherited.

Selection availability is not checkout availability:
- Asaas requires its explicit active environment, enabled verified account and an
  allowed payment method in the issuer profile.
- Efí reads its actual choices and revalidates the account revision, enabled flag,
  and configurator grants through `accountInOwned`. It never picks the first
  account or assumes production; the later operation still needs an explicit
  account/environment.
- BB uses its protected native API overview and configured profile/credential
  references. Selection does not bind one profile or claim that its instrument
  has been issued/published. The actual API operation must still validate its
  immutable connection/profile/credentials and financial source.

Every online choice explicitly carries `checkout_ready=false`. A later checkout
requires the existing per-invoice origin/proof/publication contract; the legacy
NULL-owner gateway must not be used as a fallback. Denied/unconfigured choices
carry an unavailable reason, not a fabricated successful backend. Schema/storage
failures propagate rather than silently returning an empty payment catalogue.

SQLite tests execute actual Asaas preference/account readers and the real offline
catalogue, including revocation, missing environment, A/B and no writes/provider
calls. Efí/BB module transaction and profile integration still require their
isolated MySQL suites; lint or these Asaas tests are not substitutes for that proof.

## Creation persistence

Explicit `native_preferences.allowed_payment_modes` is now part of the canonical
creation hash. Offline identifiers remain canonical strings and provider gateway
identifiers remain `asaas`, `banco_brasil`, or `gerencianet`; neither is relabelled as
a payment receipt. The exact issuer transaction leases the selected providers'
metadata tables, revalidates current permission/configuration before and after the
write, and reads back the native serialized column. Draft creation/change applies
the same check; issuance validates again. An exact successful creation replay
returns its historical receipt without rewriting selection after configuration
revocation. Omission preserves older hashes and the existing empty selection.
Manual edit does not gain a preference-update bypass.

Tests exercise real SQLite/native DDL for offline and Asaas selection, rollback,
foreign/inactive modes, draft-to-issue, exact replay, and unchanged receipt tables.
BB's independent candidate test covers the owned transaction and full profile
census. Efí runtime and the PHP/MySQL matrices remain CI obligations. Selection
still does not expose or enable a public checkout endpoint or charge a provider.
