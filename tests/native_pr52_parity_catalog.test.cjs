const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const read = (name) => fs.readFileSync(path.join(root, name), 'utf8');
const routes = read('application/services/NativeCompanyRoutes.php');
const adapter = read('application/services/NativeCompanyParityHttp.php');

const expected = {
  clients: ['export', 'update_all_proposal_emails_linked_to_customer', 'delete', 'zip_credit_notes', 'zip_invoices', 'zip_estimates', 'zip_payments', 'bulk_action', 'statement', 'statement_pdf', 'send_statement'],
  expenses: ['bulk_action', 'pdf'],
  invoices: ['apply_credits', 'delete', 'send_overdue_notice'],
  payments: ['send_to_email'],
  projects: ['expenses', 'add_expense', 'mark_as', 'file', 'update_file_data', 'add_external_file', 'download_all_files', 'export_project_data', 'update_milestones_order', 'pin_action', 'change_milestone_color', 'change_file_visibility', 'delete_milestone', 'bulk_action_files', 'timesheets', 'timesheet', 'save_note', 'edit_note', 'delete_note', 'mass_stop_timers', 'invoice_project'],
  tasks: ['detailed_overview', 'add_external_attachment', 'download_files', 'delete_task', 'bulk_action', 'gantt_date_update'],
};
const controllers = {
  clients: read('application/controllers/admin/Clients.php'),
  expenses: read('application/controllers/admin/Expenses.php'),
  invoices: read('application/controllers/admin/Invoices.php'),
  payments: read('application/controllers/admin/Payments.php'),
  projects: read('application/controllers/admin/Projects.php'),
  tasks: read('application/controllers/admin/Tasks.php'),
};

test('PR52 necessary native actions remain routed, catalogued and implemented', () => {
  for (const [module, actions] of Object.entries(expected)) {
    for (const action of actions) {
      assert.equal(controllers[module].includes("NativeCompanyParityHttp::handle($this,'" + module + "','" + action + "'"), true, module + '.' + action + ' controller adapter');
      assert.equal(routes.includes("'" + action + "'"), true, module + '.' + action + ' route');
      const actionLiteral = new RegExp("\\$action(?:===|!==)['\"]" + action + "['\"]|in_array\\(\\$action,\\[[^\\]]*['\"]" + action + "['\"]");
      assert.match(adapter, actionLiteral, module + '.' + action + ' bounded adapter');
    }
  }
});

test('bounded contracts retain the safety gates and no hard payment cap', () => {
  assert.doesNotMatch(adapter, /LIMIT 10001/);
  assert.match(adapter, /CompanyClientStatementDeliverySchema::assertValid/);
  assert.match(adapter, /companyClientStatementDelivery/);
  assert.match(adapter, /CompanyInvoiceDelivery/);
  assert.match(adapter, /CompanyRequestScope::assertNativeMutation/);
});

test('document and statement parity uses the original response contracts', () => {
  const zipStart = adapter.indexOf("if(in_array($action,['zip_credit_notes'");
  const zipEnd = adapter.indexOf("return['__download'=>['mime'=>'application/zip'", zipStart);
  const zip = adapter.slice(zipStart, zipEnd);
  assert.match(zip, /CompanyCredits/);
  assert.match(zip, /CompanyFinancialDocumentPdf::payment/);
  assert.match(zip, /NativeCompanyInvoicePdf/);
  assert.match(zip, /salesPdf/);
  assert.match(zip, /\.pdf'/);
  assert.doesNotMatch(zip, /json_encode\(/, 'document entries must not be JSON payloads');
  assert.match(adapter, /statementHtml\(\$rows,\$from,\$to\)/);
  assert.match(adapter, /\['html'=>self::statementHtml/);
  assert.match(adapter, /CompanyClientStatementDeliverySchema::assertValid/);
});

test('notes, pinning, task cancellation and revision batches retain their distinct semantics', () => {
  const noteStart = adapter.indexOf("if(in_array($action,['save_note'");
  const noteEnd = adapter.indexOf('\n    private static function massStopTimers', noteStart);
  const note = adapter.slice(noteStart, noteEnd);
  assert.match(note, /NativeCompanyAuxiliaryUi/);
  assert.match(note, /saveNote\(/);
  assert.doesNotMatch(note, /staffComment|addComment|follow/);
  const pinStart = adapter.indexOf("if($action==='pin_action'");
  const pinEnd = adapter.indexOf("if($action==='change_milestone_color'", pinStart);
  const pin = adapter.slice(pinStart, pinEnd);
  assert.match(pin, /NativeCompanyProjectPins/);
  assert.doesNotMatch(pin, /follow/);
  assert.match(adapter, /function\(\)use\(\$ci,\$p,\$issuer,\$actor,\$task,\$row,\$key,\$input\)/);
  assert.match(adapter, /\$copy\['revision'\]=\(string\)\$row\['revision'\]/);
  assert.match(adapter, /hash\('sha256',\$baseKey\.':'.*\$file/);
});

test('clients and projects expose the complete original native compositions', () => {
  const clients = controllers.clients;
  const clientSettings = read('application/services/NativeCompanyClientsSettings.php');
  const projects = controllers.projects;
  const projectTabs = read('application/services/NativeProjectTabs.php');
  const projectOriginal = read('application/services/NativeCompanyProjectOriginalHttp.php');
  for (const group of ['notes', 'statement', 'payments', 'expenses', 'contracts', 'tasks', 'estimates', 'proposals', 'credit_notes', 'subscriptions', 'reminders']) {
    assert.match(clientSettings, new RegExp(`['"]${group}['"]`), `client tab ${group}`);
  }
  assert.match(clients, /private function nativeClientSectionData/);
  assert.match(clients, /\$relations=\[/);
  assert.match(clients, /native_statement/);
  assert.match(clients, /native_payments/);
  for (const group of ['project_notes', 'project_expenses', 'project_tickets', 'project_contracts', 'project_invoices', 'project_estimates', 'project_credit_notes', 'project_subscriptions', 'project_proposals']) {
    assert.match(projectTabs, new RegExp(`['"]${group}['"]`), `project tab ${group}`);
    assert.match(projectOriginal, new RegExp(`['"]${group}['"]`), `project reader ${group}`);
  }
  assert.match(projects, /NativeProjectTabs::definitions/);
  assert.match(projects, /native_project_tabs/);
  assert.match(projects, /NativeCompanyProjectPins/);
});
