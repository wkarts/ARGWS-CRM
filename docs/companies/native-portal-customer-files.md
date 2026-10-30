# Native customer portal files

This change connects the original `Clients::files`, `upload_files`, and
`delete_file` actions and original perfex `files.php`, not another portal page.
The production Clients admission gate remains closed until its entire existing
contract is composed. Tests explicitly bind the controller boundary; they do not
claim that the complete application is admitted or browser-validated.

## Sources and permissions

- Actual authenticated contact binding, explicit active issuer and customer;
  no staff-session authority. `files.id` and attachment keys retain their native
  meaning. Project/task files retain their distinct canonical SHA-256 keys.
- Owned files use the existing customer content sidecar and native sharing rows.
  Principal historical files require proven principal lineage and existing
  explicit contact sharing. GET never adopts them. Historical paths are rooted,
  bounded, symlink-checked and inode-checked; an HTTP path is never authority.
- Native upload limits and allowed extensions are read from the issuer; shared
  binary normalization is reused. External Google/Dropbox references require
  explicitly configured public keys, closed HTTPS host rules and no server fetch.
- `only_own_files_contacts` determines sharing of new uploads, as in the original
  application. It does not silently replace previously explicit sharing grants.
- Deletion requires the original `allow_contact_to_delete_files` setting, current
  file revision and contact authorship. It revokes visibility/content activity,
  retaining bytes and attribution. Project/task branches use their actual contact
  writers and revision/parent/publication contracts. The native wrapper reads the
  original Clients option, not the old parallel project-preference node.
- Original Download client route returns only reauthorized bytes with no-store,
  nosniff, sandbox CSP and HEAD behavior. Public bootstrap admission for this exact
  route still belongs to central integration; no wildcard was added here.

## Native effects and durable delivery

Upload atomically allocates the original file row, content/origin, shares,
notification and a mail intent. The final writer's private, transaction-bound
INSERT/readback proof authorizes the contact notification; no caller can emit it
later using only a file ID. Original template
`new-customer-profile-file-uploaded-to-staff` is used.

Two empty additive core tables are provided by
`NativeCompanyCustomerUploadMailSchema`: `company_file_upload_outbox` and
`company_file_upload_mail`. They contain identifiers and hashes, not secrets or
mail bodies. FK names are explicitly bounded. The maximum physical prefix is 38
characters (the longest table suffix is 26); larger prefixes fail before DDL.
The central next migration must enlist the helper; no seed or published migration
was modified by this change.

The existing worker prepares authorized recipients, binds template/config/source
and queue hashes, then commits the existing single-use dispatch fence before
SMTP. A lost/unknown result is not automatically retried. Recipient/source/config
revocation blocks dispatch; a revocation after SMTP permits only the frozen
technical receipt. HTTP says `recorded` / `pending`, never `sent`.

Required separately delivered dependencies:
- Header physical SQLite metadata: `2dd4d645`.
- Notification `customer_file` source: `9e008ceb`.
- Dispatch fence purpose: `487e5150`.
- Existing App_Email + OAuth hook union: `9a7ec121`.
Do not duplicate prerequisites or replace the central versions of these files.

## Executed evidence

PHP 8.4 real SQLite/filesystem and actual-controller boundaries:
- shared/legacy reader: 7 + 6 checks;
- upload and actual notification: 8 + 7 checks;
- general deletion: 6 checks;
- queue/fence: 7 checks;
- actual App_Email, both engines: 13 checks, plus 3 actual OAuth-provider
  revocation checks; final SMTP captured, no external traffic;
- original Clients/view: 11 checks; actual Download: 5 checks;
- project and task native deletion policies: 12 checks each, A/B/principal;
- original project/task delete controller: 3 checks each;
- physical identifier boundary: 3 checks.
Existing contact worker 22 checks and base 135 checks also passed. Original
project-file and contact-task suites passed (102 and 142 cumulative respectively).
Node tests exercised the actual files script's request key/retry, CSRF/issuer,
confirmed pending receipt and malformed/error responses.

The isolated MySQL/MariaDB schema fixture is written and linted, **not executed
locally**. MySQL transaction/concurrency, PHP 8.1/8.5, real multipart HTTP,
responsive browser/picker interactions, and full application bootstrap/route
integration remain CI/integration requirements. No network provider, production
mail, merge, deployment, or financial operation was performed.
