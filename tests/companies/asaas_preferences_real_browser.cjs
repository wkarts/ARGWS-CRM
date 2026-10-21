'use strict';
// Real authenticated CRM/MySQL/native assets only. No HTML, provider or response fixtures.
// Run unselected immediately after the existing native installer, before A/B account fixtures;
// run selected after companies_asaas_browser.cjs. Both reuse existing A/B invoices and never seed.
// Only login and company-context selection POSTs are admitted; all Asaas navigation is GET-only.
const assert = require('node:assert/strict');
const {execFileSync} = require('node:child_process');
const {createHash} = require('node:crypto');
const fs = require('node:fs');
const path = require('node:path');
const {environment: settingsEnvironment} = require('./asaas_settings_real_browser.cjs');

const ZERO = '0'.repeat(64);
const DEFAULT_PROFILE = Object.freeze({label: 'Asaas', description: 'Fatura {invoice_number}', allow_pix: '1', allow_boleto: '1', allow_card: '1', revision: ZERO});
const ENVIRONMENTS = ['production', 'sandbox'];
const LABELS = {production: 'Produção', sandbox: 'Homologação'};
const digest = value => createHash('sha256').update(value).digest('hex');

function environment(env) {
  const config = settingsEnvironment(env);
  const phase = env.ISSUERS_BROWSER_ASAAS_PREFERENCES_PHASE || 'selected';
  assert.ok(['unselected', 'selected'].includes(phase), 'an explicit supported preferences phase is required');
  return {...config, phase, output: path.join(path.dirname(config.output), 'asaas-real-preferences-' + phase)};
}

function requestDecision(requestUrl, method, origin, permittedPost = null) {
  let url;
  try { url = new URL(requestUrl); } catch { return 'external'; }
  if (url.origin !== origin || url.username || url.password) return 'external';
  // Match canonical paths only: aliases, encoded slashes and extra segments are not admitted.
  const pathname = url.pathname;
  if (method === 'POST' && pathname === permittedPost &&
      ['/admin/authentication', '/admin/authentication/admin', '/admin/company_context/select'].includes(pathname)) return 'allow';
  if (!['GET', 'HEAD'].includes(method)) return 'mutation';
  if (['/admin', '/admin/', '/admin/authentication', '/admin/authentication/admin', '/admin/company_context', '/admin/company_asaas'].includes(pathname)) return 'allow';
  if (/^\/(?:assets|uploads|modules)\/[a-zA-Z0-9_./-]+\.(?:css|js|png|jpe?g|gif|svg|ico|woff2?|ttf|eot|webp)$/i.test(pathname)) return 'allow';
  return 'navigation';
}

function readOnlyQuery(query) {
  assert.ok(typeof query === 'string' && /^(SELECT|SHOW)\s/i.test(query), 'only SELECT/SHOW evidence queries are admitted');
  assert.ok(!/[;\u0000]|--|\/\*|\b(?:INTO|OUTFILE|DUMPFILE|FOR\s+UPDATE|LOCK\s+IN\s+SHARE)\b/i.test(query), 'query must be a single non-locking read');
  return 'START TRANSACTION READ ONLY; ' + query + '; COMMIT;';
}

async function run(env = process.env) {
  let browser, config;
  let checks = 0, phase = 'validate disposable environment', screenshotCount = 0;
  const counts = {external: 0, mutation: 0, navigation: 0, pageerrors: 0};
  function check(condition, label) { phase = label; assert.ok(condition, label); checks++; }
  try {
    config = environment(env);
    function sql(query) {
      return execFileSync('docker', ['exec', '-e', 'MYSQL_PWD', config.db, 'mysql', '--protocol=tcp', '--host=127.0.0.1',
        '--user=' + env.ISSUERS_BROWSER_DB_USER, 'argws_upgrade_ci', '--batch', '--skip-column-names', '--raw', '-e', readOnlyQuery(query)],
      {env: {...env, MYSQL_PWD: env.ISSUERS_BROWSER_DB_PASSWORD}, encoding: 'utf8', maxBuffer: 16 * 1024 * 1024}).trim();
    }
    function jsonRows(query) { const value = sql(query); return value ? value.split('\n').map(line => JSON.parse(line)) : []; }
    function identifier(value) { assert.match(value, /^[a-z0-9_]+$/); return '`' + value + '`'; }
    function id(value) { value = String(value); assert.match(value, /^[1-9]\d*$/); return value; }
    // Hash each column on the server. Secret ciphertext and business records never leave MySQL.
    function businessSnapshot() {
      const tables = sql("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND (TABLE_NAME LIKE 'tblasaas\\_%' OR TABLE_NAME LIKE 'tblcompany_asaas\\_%' OR TABLE_NAME IN ('tblinvoices','tblinvoicepaymentrecords','tblcompany_financial_events','tblcompany_expenses')) ORDER BY TABLE_NAME").split('\n').filter(Boolean);
      const evidence = [];
      for (const table of tables) {
        identifier(table);
        const columns = sql("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='" + table + "' ORDER BY ORDINAL_POSITION").split('\n');
        const pieces = columns.map(column => "COALESCE(SHA2(CAST(" + identifier(column) + " AS BINARY),256),'NULL')");
        const rows = sql("SELECT SHA2(CONCAT_WS(':'," + pieces.join(',') + '),256) FROM ' + identifier(table) + ' ORDER BY 1');
        evidence.push([table, digest(rows)]);
      }
      evidence.push(['legacy-options', digest(sql("SELECT CONCAT(name,':',SHA2(value,256)) FROM tbloptions WHERE name LIKE 'paymentmethod_asaas_%' ORDER BY name"))]);
      evidence.push(['native-tracking', sql("SELECT CONCAT(installed_version,':',active) FROM tblmodules WHERE module_name='asaas'")]);
      return digest(JSON.stringify(evidence));
    }
    phase = 'verify actual native155 tracking and pre-existing A/B fixtures';
    const version = sql("SELECT installed_version FROM tblmodules WHERE module_name='asaas' AND active=1");
    const header = fs.readFileSync(path.resolve(__dirname, '../../modules/asaas/asaas.php'), 'utf8').match(/^Version:\s*(\d\.\d\.\d)\s*$/m);
    check(header && Number(header[1].replaceAll('.', '')) >= 155 && version === header[1], 'native155+ tracking matches the shipped module');
    const staff = sql("SELECT staffid FROM tblstaff WHERE email='admin-ci@example.invalid' AND admin=1 AND active=1");
    id(staff);
    const owners = [];
    for (const [letter, tax] of [['a', '11222333000181'], ['b', '52998224725']]) {
      const issuer = id(sql("SELECT id FROM tblissuers_registry WHERE tax_id='" + tax + "'"));
      const invoice = id(sql('SELECT id FROM tblinvoices WHERE issuer_id=' + issuer + ' ORDER BY id DESC LIMIT 1'));
      const selection = jsonRows("SELECT JSON_OBJECT('active_environment',active_environment,'revision',revision) FROM tblasaas_company_preferences WHERE issuer_id=" + issuer)[0] || {active_environment: null, revision: ZERO};
      const profiles = Object.fromEntries(ENVIRONMENTS.map(name => [name, {...DEFAULT_PROFILE}]));
      for (const row of jsonRows("SELECT JSON_OBJECT('environment',environment,'label',label,'description',description,'allow_pix',CAST(allow_pix AS CHAR),'allow_boleto',CAST(allow_boleto AS CHAR),'allow_card',CAST(allow_card AS CHAR),'revision',revision) FROM tblasaas_company_profiles WHERE issuer_id=" + issuer)) profiles[row.environment] = row;
      const accounts = jsonRows("SELECT JSON_OBJECT('id',id,'environment',environment,'account_id',account_id,'enabled',enabled,'verified_at',verified_at,'revision',revision) FROM tblasaas_company_accounts WHERE issuer_id=" + issuer);
      check(selection.active_environment === (config.phase === 'selected' ? 'sandbox' : null), 'existing fixture has the required selection state: ' + letter);
      if (config.phase === 'selected') {
        check(accounts.length === 1 && accounts[0].environment === 'sandbox' && accounts[0].account_id === 'wallet-browser-' + letter && Number(accounts[0].enabled) === 1 && accounts[0].verified_at !== null, 'existing verified synthetic wallet is required: ' + letter);
      } else {
        check(accounts.length === 0, 'unselected phase precedes synthetic account creation: ' + letter);
      }
      owners.push({letter, issuer, invoice, selection, profiles, accounts});
    }
    check(owners[0].issuer !== owners[1].issuer && owners[0].invoice !== owners[1].invoice, 'A/B fixtures are different actual owners and invoices');
    const before = businessSnapshot();
    fs.mkdirSync(config.output, {recursive: true});
    const {chromium} = require('playwright');
    browser = await chromium.launch({headless: true, ...(env.ISSUERS_BROWSER_EXECUTABLE_PATH ? {executablePath: env.ISSUERS_BROWSER_EXECUTABLE_PATH} : {})});
    for (const width of [1280, 390]) {
      phase = 'launch isolated browser context';
      const context = await browser.newContext({viewport: {width, height: 900}, locale: 'pt-BR', serviceWorkers: 'block'});
      context.setDefaultTimeout(10000);
      let permittedPost = null, observePageErrors = false;
      await context.route('**/*', route => {
        const request = route.request();
        const decision = requestDecision(request.url(), request.method(), config.url, permittedPost);
        if (decision !== 'allow') { counts[decision]++; return route.abort(); }
        return route.continue();
      });
      try {
        const page = await context.newPage();
        page.on('pageerror', () => { if (observePageErrors) counts.pageerrors++; });
        async function submitSession(form) {
          const target = new URL(await form.getAttribute('action'), config.url);
          check(target.origin === config.url && ['/admin/authentication', '/admin/authentication/admin', '/admin/company_context/select'].includes(target.pathname), 'only native login/context forms may be submitted');
          permittedPost = target.pathname;
          try { await Promise.all([page.waitForNavigation({waitUntil: 'domcontentloaded'}), form.locator('button[type=submit]').click()]); }
          finally { permittedPost = null; }
        }
        phase = 'authenticate synthetic administrator';
        check((await page.goto(config.url + '/admin/authentication')).status() === 200, 'real login renders');
        await page.locator('input[name=email]').fill('admin-ci@example.invalid');
        await page.locator('input[name=password]').fill(env.ISSUERS_BROWSER_PASSWORD);
        await submitSession(page.locator('form').filter({has: page.locator('input[name=password]')}));
        check(!page.url().includes('/authentication'), 'synthetic administrator is authenticated');
        const scopeHashes = [];
        for (const owner of owners) {
          phase = 'select real company context: ' + owner.letter;
          observePageErrors = false;
          await page.goto(config.url + '/admin/company_context');
          await page.locator('#company-context-select').selectOption(owner.issuer);
          await submitSession(page.locator('.company-selector-form'));
          observePageErrors = true;
          const response = await page.goto(config.url + '/admin/company_asaas');
          check(response.status() === 200, 'actual nominal preferences render: ' + owner.letter);
          check((response.headers()['cache-control'] || '').includes('no-store'), 'real nominal view prevents cached account/preferences recovery');
          check(await page.locator('#company-context-select').inputValue() === owner.issuer, 'header confirms the actual active owner');
          check(await page.locator('body.company-context-shell').count() === 1 && await page.locator('script[src*="assets/js/company-context-tabs.js"]').count() === 1, 'real company shell and native tabs asset are present');
          check(await page.locator('link[href*="assets/plugins/bootstrap/css/bootstrap.min.css"]').count() === 1 && await page.evaluate(() => typeof window.jQuery?.fn?.tab === 'function'), 'actual Bootstrap stylesheet and plugin load');
          const preferences = page.locator('#preferences');
          const tabs = preferences.locator('[role=tab]');
          check(JSON.stringify((await tabs.allTextContents()).map(text => text.trim())) === JSON.stringify(['Produção', 'Homologação']), 'two native environment tabs use real PT-BR labels');
          check((await preferences.innerText()).includes(config.phase === 'selected' ? 'Ambiente para novas cobranças: Homologação' : 'Ambiente para novas cobranças: Nenhum selecionado'), 'selection status is the persisted server state');
          const forms = preferences.locator('form:has(input[name=action][value=save_profile])');
          check(await forms.count() === 2, 'both real environment profile forms exist');
          scopeHashes.push(digest(await forms.first().locator('input[name=_company_context]').inputValue()));
          const formState = () => forms.evaluateAll(items => JSON.stringify(items.map(form => Array.from(new FormData(form).entries()))));
          const initialFormState = await formState();
          async function active(environmentName, focused = false) {
            const tab = page.locator('#company-asaas-profile-tab-' + environmentName);
            const panel = page.locator('#company-asaas-profile-' + environmentName);
            check(await panel.isVisible() && await panel.getAttribute('aria-hidden') === 'false', 'selected profile is actually visible and exposed to accessibility');
            check(await tab.getAttribute('aria-selected') === 'true' && await tab.getAttribute('tabindex') === '0', 'selected profile has roving keyboard/ARIA state');
            check(await preferences.locator('[role=tabpanel].active').count() === 1 && await preferences.locator('[role=tab][aria-selected=true]').count() === 1, 'only one environment is active');
            const other = environmentName === 'production' ? 'sandbox' : 'production';
            check(!await page.locator('#company-asaas-profile-' + other).isVisible() && await page.locator('#company-asaas-profile-' + other).getAttribute('aria-hidden') === 'true', 'the other real profile is hidden');
            check(await page.locator('#company-asaas-profile-tab-' + other).getAttribute('tabindex') === '-1', 'inactive tab stays out of sequential keyboard focus');
            if (focused) check(await tab.evaluate(node => node === document.activeElement), 'keyboard focus follows the selected profile');
          }
          await active(config.phase === 'selected' ? 'sandbox' : 'production');
          for (const environmentName of ENVIRONMENTS) {
            phase = 'inspect native controls: ' + environmentName;
            await page.locator('#company-asaas-profile-tab-' + environmentName).click();
            await active(environmentName);
            const panel = page.locator('#company-asaas-profile-' + environmentName);
            const form = panel.locator('form:has(input[name=action][value=save_profile])');
            check(await form.locator('[name="profile[label]"]').inputValue() === owner.profiles[environmentName].label && await form.locator('[name="profile[description]"]').inputValue() === owner.profiles[environmentName].description, 'actual profile text matches its persisted/default environment');
            const fields = await form.locator('[name^="profile["]').evaluateAll(inputs => [...new Set(inputs.map(input => input.name))].sort());
            check(JSON.stringify(fields) === JSON.stringify(['allow_boleto', 'allow_card', 'allow_pix', 'description', 'label'].map(name => 'profile[' + name + ']').sort()), 'only the five supported nominal preferences are configurable');
            for (const flag of ['allow_pix', 'allow_boleto', 'allow_card']) check(await form.locator('input[type=checkbox][name="profile[' + flag + ']"]').isChecked() === (owner.profiles[environmentName][flag] === '1'), 'actual payment permission checkbox matches this environment');
            check(await form.locator('input[name=environment]').inputValue() === environmentName && await form.locator('input[name=profile_revision]').inputValue() === owner.profiles[environmentName].revision, 'environment/profile optimistic revision is carried by the native form');
            const account = owner.accounts.find(item => item.environment === environmentName);
            check(await form.locator('input[name=account_revision]').inputValue() === (account?.revision || ZERO), 'account revision belongs to this owner/environment');
            for (const token of ['_company_context', 'company_asaas_preferences_csrf', 'csrf_token_name']) check((await form.locator('input[name="' + token + '"]').inputValue()).length > 0, 'real profile form includes native context and both CSRF defenses');
            check(await formState() === initialFormState, 'tab navigation never changes any real form value');
            check(await preferences.evaluate(node => { const box = node.getBoundingClientRect(); return box.left >= 0 && box.right <= innerWidth + 1 && node.scrollWidth <= node.clientWidth + 1; }), 'preferences remain contained on the actual viewport');
            await preferences.screenshot({path: path.join(config.output, width + '-' + owner.letter + '-' + environmentName + '.png')});
            screenshotCount++;
          }
          // Native keyboard activation, both wrapping directions and repeated activation.
          await page.locator('#company-asaas-profile-tab-sandbox').press('Home'); await active('production', true);
          await page.keyboard.press('ArrowLeft'); await active('sandbox', true);
          await page.keyboard.press('ArrowRight'); await active('production', true);
          await page.keyboard.press('End'); await active('sandbox', true);
          await page.keyboard.press('End'); await active('sandbox', true);
          check(await formState() === initialFormState, 'keyboard/repeated activation preserves all form values');
          // Browser-only unsaved ordinary text/checkbox edits; no credentials or submission.
          const sandboxLabel = page.locator('#company-asaas-label-sandbox');
          await sandboxLabel.fill('Preferência sintética não salva');
          const card = page.locator('#company-asaas-sandbox-allow_card');
          await card.setChecked(!await card.isChecked());
          const editedState = await formState();
          await page.locator('#company-asaas-profile-tab-production').click();
          await page.locator('#company-asaas-profile-tab-sandbox').click();
          check(await formState() === editedState, 'switching profiles preserves unsaved form edits in their own environment');
          await page.reload({waitUntil: 'domcontentloaded'});
          await page.locator('#company-asaas-profile-tab-sandbox').click();
          check(await sandboxLabel.inputValue() === owner.profiles.sandbox.label && await card.isChecked() === (owner.profiles.sandbox.allow_card === '1'), 'real GET reload discards unsaved edits');
          await page.goto(config.url + '/admin/company_asaas#company-asaas-profile-production');
          await active('production');
          check(await page.locator('#preferences input[name=environment][value=sandbox]').count() >= 1, 'deep-link navigation preserves the other environment controls');
          // Owned invoice hydration is a real GET. Never click the financial submit button.
          check((await page.goto(config.url + '/admin/company_asaas?invoice=' + owner.invoice)).status() === 200, 'existing own invoice opens through the real controller');
          const issue = page.locator('#issue form').filter({has: page.locator('input[name=action][value=issue]')});
          if (config.phase === 'unselected') {
            check(await issue.count() === 0 && await page.locator('#asaas-issue-account').count() === 0, 'no issuance form/account appears before explicit selection');
            check((await page.locator('#issue [role=status]').innerText()).includes('Selecione explicitamente um ambiente com conta verificada'), 'unselected invoice explains the required explicit verified environment');
          } else {
            const account = owner.accounts[0], profile = owner.profiles.sandbox;
            check(await issue.count() === 1, 'selected verified profile enables the actual issuance form');
            check(await issue.locator('#asaas-issue-account option').count() === 1 && await issue.locator('#asaas-issue-account').inputValue() === String(account.id), 'new issuance offers only the explicitly selected owner account');
            const expectedMethods = [['allow_pix', 'PIX'], ['allow_boleto', 'BOLETO'], ['allow_card', 'CREDIT_CARD']].filter(([flag]) => profile[flag] === '1').map(([, method]) => method);
            check(JSON.stringify(await issue.locator('#asaas-issue-billing option').evaluateAll(options => options.map(option => option.value))) === JSON.stringify(expectedMethods), 'real issuance options follow only selected environment permissions');
            for (const [name, value] of Object.entries({selection_revision: owner.selection.revision, profile_revision: profile.revision, account_revision: account.revision})) check(await issue.locator('input[name=' + name + ']').inputValue() === value, 'issuance carries the current owner policy revision');
            check((await page.locator('#accounts').innerText()).includes('wallet-browser-' + owner.letter) && !(await page.locator('#accounts, #charges').allTextContents()).join('\n').includes('wallet-browser-' + (owner.letter === 'a' ? 'b' : 'a')), 'own account/history never reveals the foreign wallet');
          }
          // Real HTTP 403 using the browser transport, so the same network guard still applies.
          const foreign = owners.find(item => item.issuer !== owner.issuer);
          check((await page.goto(config.url + '/admin/company_asaas?invoice=' + foreign.invoice)).status() === 403, 'foreign invoice hydration is rejected within the current owner');
          check(await page.locator('#preferences, #asaas-issue-account').count() === 0, 'denied foreign invoice response exposes no preference or issuance form');
          await page.goBack({waitUntil: 'domcontentloaded'});
          check(await page.locator('#company-context-select').inputValue() === owner.issuer && await page.locator('#preferences').count() === 1, 'Back recovers the owned view without changing company');
          await page.goForward({waitUntil: 'domcontentloaded'});
          check(await page.locator('#preferences').count() === 0, 'Forward cannot recover a foreign preference view');
        }
        check(scopeHashes[0] !== scopeHashes[1], 'A/B profile forms carry distinct native company context tokens');
      } finally { await context.close(); }
    }
    check(counts.pageerrors === 0, 'real nominal UI emits no page errors');
    check(counts.mutation === 0, 'presentation attempted no non-session mutation');
    check(businessSnapshot() === before, 'all Asaas tables, native financial records and legacy settings remain unchanged');
    const report = {surface: 'authenticated real nominal Asaas preferences', phase: config.phase, nativeVersion: version, widths: [1280, 390], owners: ['a', 'b'], checks, screenshots: screenshotCount,
      blockedRequests: counts, database: 'all Asaas tables, core financial records and legacy settings unchanged; login/session metadata excluded',
      limitations: 'No save/selection POST, provider transport, financial action or production/homologation-provider proof. Production preference panel only; synthetic active wallet is sandbox.'};
    fs.writeFileSync(path.join(config.output, 'coverage.json'), JSON.stringify(report, null, 2));
    console.log('Asaas real nominal preferences (' + config.phase + '): ' + checks + ' checks passed; PT-BR tabs, keyboard/mobile, owner isolation and issuance availability. Business state unchanged; no financial/provider action.');
    return report;
  } catch {
    // Never forward Playwright/SQL error payloads, page content, credentials or context URLs.
    console.error(JSON.stringify({test: 'asaas-real-preferences', phase, checks, blockedRequests: counts}));
    throw new Error('Asaas real nominal preferences failed at a sanitized phase; inspect its fixed-label CI diagnostics.');
  } finally { if (browser) await browser.close(); }
}

module.exports = {environment, requestDecision, readOnlyQuery, run};
if (require.main === module) run().catch(() => { process.exitCode = 1; });
