# Native staff creation invitations

The existing welcome checkbox will create a one-time password-definition invitation rather than email the supplied password. The original `new-staff-created` template and `admin/authentication/set_password/1/{id}/native` form are reused. The bearer travels in a fragment, is removed from history and submitted in the form body; the server-side native marker is not a bearer.

Only the concrete `CompanyStaff` creation participant can issue it, inside the same transaction as identity, membership, role, departments, custom fields, queue and receipt. An existing identity, simple company permission or forged creation request key is not sufficient. A later additional company association, revoked creator grant, removed membership, inactive identity, changed login/password, used or expired token denies completion. Password, token consumption and audit commit together. No identity/company is automatically adopted.

`NativeCompanyStaffInviteSchema::ensure` belongs in migration 474, outside transactions. It adds empty historical receipts, no seeded credentials or users. Original installation SQL is not changed. The delivery uses the persisted company's explicit SMTP/OAuth configuration, exact template/payload and a single-use dispatch fence. Unknown SMTP outcomes cannot be retried by resetting queue status. Technical receipts survive in-flight revocation without renewing authority.

Tests use real PHP 8.4/PDO SQLite and actual App_Email for both CodeIgniter and PHPMailer, with only final SMTP captured. OAuth HTTP is synthetic. Actual native authentication model/controller are exercised with framework I/O doubles. This is not HTTP/browser or MySQL/InnoDB concurrency proof. Those checks and complete Staff controller/view admission remain required. The invitation code alone does not complete the overall Staff feature.

## Company hourly rate

The original `hourly_rate` control is membership data, not a shared login property.
The native form participant accepts `rateChange = ['value' => decimal-or-null, 'revision' => integer]`;
its CAS, audit and command receipt commit with profile, permissions and departments.
Direct `CompanyStaff::createNative/updateNative` profile arrays reject `hourly_rate`.
Migration 474 must call `NativeCompanyStaffRateSchema::ensure` outside a transaction.
No global value is copied into a named company: an absent rate is explicitly unknown (`null`).
Only the proven principal reads its historical `staff.hourly_rate` value.

Tasks consume `NativeCompanyStaffRates::forOwnTimer(issuer, actor)` or
`forOwnTimerInOwned(issuer, actor)` with `tables()` enlisted. The DTO is
`hourly_rate: ?decimal-string`, `revision: int|string`, `source_kind`, `source_id`.
The timer must retain that exact snapshot; it must not recompute past costs from later rates.
The rate reader requires current membership plus `tasks.view`, not staff administration.
