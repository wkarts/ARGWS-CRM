# Native privacy request and notification delivery

`Clients::gdpr` uses the original page and removal modal. The public contact is
resolved from its real issuer-bound portal binding; POST uses the issuer CSRF
and request idempotency key. The request, ownership sidecar, native administrator
notifications and pending mail intent commit together. It never deletes a
customer, contact, document or financial record.

A missing SMTP configuration does not discard the privacy request. The existing
`App_Email::send_queue` consumer prepares pending intents using the selected
issuer's actual `gdpr-removal-request` template and transport. Only current
issuer-authorized administrators are recipients. Disabled templates have an
explicit disabled state; source changes require review. A request rendered as
recorded or a queue rendered as pending is not an email-delivery assertion.

Two additive 474 helper tables hold a request outbox and per-queue technical
receipts. They store source/configuration/payload hashes, not credentials or
mail bodies. Existing native `mail_queue` and the shared single-use dispatch
fence perform delivery. The worker revalidates request/contact ownership,
administrator identity, template and transport before OAuth and SMTP. A permit
commits before transport. An uncertain result cannot be automatically resent,
even if the generic queue is reset to pending. Completion after revocation
records only the frozen technical outcome. “Sent” in the page means accepted
by the configured transport, not proven inbox delivery.

Composition dependencies:

- `NativeCompanyPrivacyMailSchema::ensure` in the central unpublished 474
  orchestration; never modify published 473 or seed.
- Auxiliary privacy fence kind `06365e0f` and App_Email/OAuth hooks `9744d0f0`.
  The customer branch's `0891a793` is only a local prerequisite copy, not another
  patch to apply centrally.
- Existing canonical privacy request sidecar and notification service.

Executed locally with PHP 8.4 and real SQLite: 15 request/notification checks,
17 preparation/negative/source checks, 8 original controller/view checks,
13 actual App_Email checks across both engines, and 3 actual OAuth-provider
negative checks. The actual worker also passed 22 contact-mail and 135 inherited
checks. Only the final SMTP side effect was captured; no external mail or OAuth
request was sent. MySQL/MariaDB metadata, matrix PHP 8.1/8.5, full bootstrap and
browser remain required. Positive OAuth exchange remains covered by the existing
transport suites, not newly claimed as an end-to-end privacy-provider run.

The public `Clients` dispatcher remains closed until the complete native action
inventory is adapted. This package closes the GDPR action, not every public page
or the still-pending full GDPR export across business domains.
