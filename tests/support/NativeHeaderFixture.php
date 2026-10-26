<?php

declare(strict_types=1);

/**
 * Presentation-only CLI fixture. Outputs the actual native header fragment.
 *
 * The consumer supplies native CSS/JS. The selector loads its shipped assets. No database,
 * HTTP server, authenticated session, provider request or CRM bootstrap is used.
 * CompanySettingsIdentity and IssuerResourceAccess are the shipped classes;
 * their read-only I/O is an exact synthetic query whitelist. Framework globals,
 * permissions and CompanyRequestScope are presentation stubs, not ACL evidence.
 * The quick-action catalog, notifications and timers are empty. Their actual
 * native views are retained. Full-shell mode also renders actual setup/aside/
 * pinned-project views with empty menus, languages and projects plus a synthetic
 * staff identity. The caller can request the server's mobile header branch.
 *
 * Usage: php tests/support/NativeHeaderFixture.php [--full-shell] [--long-name] [--mobile-user]
 * Selector-only POST fixture: --selector-only [--selected-two]
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$fixtureFlags = array_slice($argv, 1);
if (array_diff($fixtureFlags, ['--full-shell', '--long-name', '--mobile-user', '--selector-only', '--selected-two', '--company-google', '--company-microsoft', '--company-efi'])) {
    throw new InvalidArgumentException('Unknown native-header fixture flag');
}
define('NATIVE_HEADER_FIXTURE_COMPANY', in_array('--company-google', $fixtureFlags, true) ? 'google_mail_oauth' : (in_array('--company-microsoft', $fixtureFlags, true) ? 'microsoft_mail_oauth' : (in_array('--company-efi', $fixtureFlags, true) ? 'efi' : '')));
define('NATIVE_HEADER_FIXTURE_FULL_SHELL', in_array('--full-shell', $fixtureFlags, true));
define('NATIVE_HEADER_FIXTURE_LONG_NAME', in_array('--long-name', $fixtureFlags, true));
define('NATIVE_HEADER_FIXTURE_MOBILE_USER', in_array('--mobile-user', $fixtureFlags, true));
define('NATIVE_HEADER_FIXTURE_SELECTOR_ONLY', in_array('--selector-only', $fixtureFlags, true));
define('NATIVE_HEADER_FIXTURE_SELECTED_TWO', in_array('--selected-two', $fixtureFlags, true));
const NATIVE_HEADER_FIXTURE_ROOT = __DIR__ . '/../..';
define('BASEPATH', NATIVE_HEADER_FIXTURE_ROOT . '/system/');
define('APPPATH', NATIVE_HEADER_FIXTURE_ROOT . '/application/');
require APPPATH . 'language/portuguese_br/portuguese_br_lang.php';
require APPPATH . 'services/CompanySettingsIdentity.php';

final class NativeHeaderFixtureDatabase
{
    public function field_exists(string $field, string $table): bool
    {
        if (NATIVE_HEADER_FIXTURE_COMPANY && $table === 'fixture_staff') { return false; }
        if ($field !== 'primary_issuer_id' || $table !== 'fixture_issuers_installation_identity') {
            throw new LogicException('Unexpected fixture field lookup');
        }
        return true;
    }

    public function query(string $sql, array $parameters = [])
    {
        if ($sql === 'SELECT active FROM fixture_modules WHERE module_name=?' && $parameters === ['theme_style']) {
            $rows = [['active' => 1]];
        } elseif ($sql === 'SELECT value FROM fixture_options WHERE name=?' && $parameters === ['theme_style']) {
            $rows = [['value' => '[{"id":"btn-primary","color":"#32c977"},{"id":"admin-menu-active-item","color":"#32c977"}]']];
        } elseif ($sql === 'SELECT primary_issuer_id FROM fixture_issuers_installation_identity WHERE id=1'
            && $parameters === []) {
            $rows = [['primary_issuer_id' => 1]];
        } elseif ($sql === 'SELECT * FROM fixture_issuers_registry WHERE id=? AND active=1'
            && ($parameters === [1] || (NATIVE_HEADER_FIXTURE_SELECTOR_ONLY && $parameters === [2]))) {
            $rows = [[
                'id' => $parameters[0],
                'active' => 1,
                'legal_name' => $parameters[0] === 1 ? native_header_fixture_company_name() : 'Outra empresa de teste',
                'identity_status' => 'complete',
                'settings_json' => '{}',
            ]];
        } elseif ($sql === 'SELECT value FROM fixture_options WHERE name=?'
            && $parameters === ['company_trade_name']) {
            $rows = [['value' => native_header_fixture_company_name()]];
        } else {
            throw new LogicException('Unexpected fixture query');
        }
        return new class($rows) {
            private array $rows;
            public function __construct(array $rows) { $this->rows = $rows; }
            public function result_array(): array { return $this->rows; }
            public function row_array(): array { return $this->rows[0] ?? []; }
        };
    }
}

function native_header_fixture_company_name(): string
{
    return NATIVE_HEADER_FIXTURE_LONG_NAME
        ? 'Empresa sintética CI de demonstração com razão social e nome fantasia muito longos para validação de seleção responsiva UnidadeRegionalOperacionalSemSeparadoresParaValidacaoDeQuebraDeLinha'
        : 'Empresa sintética CI';
}
function e($value): string 