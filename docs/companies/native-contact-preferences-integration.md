# Native contact preferences integration contract (WIP)

`NativeCompanyContactPreferences` owns validation and issuer-private persistence. Existing principal continues its original native settings route. Named-company callers must not call global `get_option` or `automatic_calling_codes_enabled` hooks.

The central settings owner should compose these changes into `CompanySettingsCatalog` (not applied here to avoid concurrent edits):

- group `contact_preferences`, label `Preferências de contatos`;
- `fields('contact_preferences')` delegates `NativeCompanyContactPreferences::fields()`;
- `defaultValue` delegates `NativeCompanyContactPreferences::defaultValue($field)`;
- `prepare` delegates `NativeCompanyContactPreferences::prepare($settings)` before persistence.

Settings POST requires its existing explicit issuer/ACL/CSRF/revision boundary. `save` on the dedicated class requires settings/edit, compares the exact previous issuer-private values under an owned transaction, and uses `CompanySettingsStorage` for atomic audited writes. The native default permissions selector retains the native wire format (serialized list of string/integer IDs 1–6). The UI must serialize validated IDs server-side; never deserialize objects or accept arbitrary permissions.

Default provenance is tested against the tracked public installation SQL (`default_contact_permissions`) and `clients_helper.php` (`automatic_calling_codes_enabled`, true before hooks). This does not adopt the principal tenant's customization or hook result. No SQL seed edits are included.

Actual PHP 8.4 SQLite tests: `tests/companies/native_contact_preferences.php`. Matrix PHP 8.1/8.5, MySQL, native rendered settings, and browser proof remain integration acceptance work.
