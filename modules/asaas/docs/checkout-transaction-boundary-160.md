# Asaas 1.6.0 — shared transaction ownership

PR51 at `6b4b7bd5` failed the integrated hosted-checkout setup after the Asaas
A/B configuration and removal browser scenarios had passed. The fixture command
exited before its JSON result; the old harness did not preserve a sanitized CLI
exception code. Consequently the CI log alone does not prove the exception type.

Code inspection establishes a deterministic defect on the current schema:
`CompanyService::tablesForRun()` includes `asaas_company_origin_policy` and,
when present, `efi_company_origins`; `CompanyPayers`, `CompanyHostedLinks` and
`CompanyRefundStorage` reconstructed older table lists. Their callbacks invoke
service methods whose `IssuerTransaction::assertOwned` requires the complete
current table list. Registration, hosted publication and refunds therefore fail
closed rather than entering a valid owned boundary. The previous standalone
historical fixtures without these new tables did not cover this composition.

The adapters now compose their boundaries from the same public service method,
retaining adapter-specific checkout/refund/contact tables. No ownership,
transactional-engine, ACL, issuer/account/environment, revision, network or
provider-origin check is removed. `CompanyRemoteOperations` already included
both tables and remains unchanged. Reconciliation and received receipts use
separate existing service contracts; they do not call the four methods requiring
`tablesForRun`. Fee expenses call the receipt contract, not these source methods.

Migration160 is a native NOOP for the runtime correction. Historical159 remains
byte-identical. Tests keep explicit159 failure/readback/replay checkpoints and
add latest160 failure/readback/replay without rewriting historical assertions.

## Verification

Run `php tests/asaas_checkout_transaction_tables.php` before the long smoke.
It executes the actual adapter composition and actual `IssuerTransaction` with
an explicit SQL double. It proves the complete owner succeeds, missing policy or
Efí fence is rejected before account/SDK access, unsafe engine is rejected before
the callback, and no owner is rejected. It also preserves the pre157 schema case.
This is not a real MySQL test or a successful provider execution.

Then run the existing native migration tests and integrated payer/hosted portal,
removal and refund scenarios. Those require PHP/MySQL/Chromium and were **not
executed locally** for this patch. The CLI fixture now prints only an allowlisted
error code and exception class on failure, never SQL, bindings or credentials.

## CI reconciliation

The authentic PHP inventory was generated on commit `0e6c48944954d98415c2e654b3be56974f7d7226` by job `121135277588`, artifact `12594641246` (ZIP SHA256 `c3bbbd2516f846742ed543f0b48309414083cbecc6067fb7c44691e907337437`). Only its source manifest, source-file count and Asaas version changed; readiness remains blocked. The preparation workflow is now explicit manual diagnostics, not a recurring push suite. Permanent PHP8.1/8.5 registry preflight runs the new ownership contract; its exact inventory rises from498 to499 PHP commands per version without changing historical shard assignments. The same contract runs before the long Stacks smoke. Full runtime results remain dependent on the final CI SHA.
