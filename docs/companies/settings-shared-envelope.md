# Shared Settings presentation envelope

`admin/settings/all.php` renders the shared `presentation_layout.php`: the native responsive columns, heading hierarchy,
section spacing, links/icons, active styles and panel sidebar. The scoped parent
no longer loads its approximate `company-settings-native.css`. Both parents retain
their own existing form body/footer, action, CSRF, edit permissions and revisions.
The shared component does not create another form or execute hooks, storage,
option lookup, route dispatch or callbacks. Its content slot is trusted output of
server view rendering, never request HTML. Labels and URLs are escaped; unsafe
URL schemes/protocol-relative URLs, malformed identifiers and icon attributes fail
closed. Section admission remains the caller's authorization responsibility.

The same official parent is used for the principal and selected company. It obtains
the issuer-owned section catalog and never loads a parallel Settings shell or global
catalog for a selected company.

Company identity fields already use `includes/company_identity_fields.php` in both
parents. Custom company fields/free-form company formatting, the broader native
SMTP queue/test/engine settings, native general/branding controls and unrestricted
payment gateways are NOT made tenant-safe by this presentation extraction. Missing
backend features remain unavailable rather than rendering global actions as tenant
controls. Email and provider presentation parity are subsequent separate work.

## Evidence contract

- Existing scoped PHP controller/view tests retain form/context/CSRF/readonly and
  complete admitted navigation assertions, with global option access forbidden.
- `php tests/company_settings_layout.php` renders the real principal parent over
  synthetic framework I/O, checks native hooks/forms/admin navigation and rejects
  malicious DTO fields in the shared component.
- `node tests/company_settings_layout.browser.cjs` compares components extracted
  from both actual PHP parents at 1280/768/390 using unmodified native CSS. It
  checks grid geometry and computed typography/colors/spacing, form ownership,
  unchanged FormData, overflow and captures masked synthetic screenshots.

This is an envelope/component comparison, NOT full authenticated production UI,
feature equivalence, or proof from the view inventory. Field count/text and form
height differ where admitted capabilities differ; the test does not normalize
away these differences. Native global JavaScript is not executed in the component
fixture. PHP/browser execution is pending CI; local Node contracts and JS syntax
checks alone do not establish visual equality.
