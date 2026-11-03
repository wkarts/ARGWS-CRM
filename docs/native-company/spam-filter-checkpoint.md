# Native company spam filters — validation checkpoint

The existing `Spam_filters::view/filter/delete` controller and native tab/modal/table view now consume an explicitly owned catalog for an attested named company. Principal requests retain the original model and routes. The matcher is one shared source used by the original model and new provider; sender equality and literal subject/phrase matching, case handling and precedence are unchanged.

`NativeCompanySpamSchema::ensure()` must be orchestrated by core 474 before enabling routes. It creates the owned rule and durable-command tables, validates existing metadata before any CREATE and never runs in a read request. Namespace 51 is `company_spam_filter` / `spam_filter`; integration must preserve separately reserved 49 and 50.

Administrative CRUD requires current company administrative authority, context/CSRF, optimistic revision and a durable request nonce. Ticket editors cannot administer the catalog, but the original `Tickets::block_sender` action can block the sender derived from an authorized ticket with its current revision; a caller cannot supply another address. `blockedSenderForTicket()` reads the same catalog after the real ticket/department boundary. No global rules are inherited by named companies.

Executed locally: PHP 8.4 with actual SQLite CRUD/controller transactions and original native views, plus explicit schema metadata doubles; strict schema negative tests; the original matcher model; Node execution of the shared delete/status/block handlers. This is not MySQL/MariaDB metadata proof or browser evidence. Native admin shell/form helper boundaries remain explicit test seams.

Remaining gates: full Tickets detail/form composition and actual browser proof, final route admission, core migration orchestration, and a source-bound IMAP/piping caller. No SMTP/piping activity or production activation was performed. List rendering currently follows the existing in-memory native catalog adapters; large-catalog performance must be measured before claiming an optimization.
