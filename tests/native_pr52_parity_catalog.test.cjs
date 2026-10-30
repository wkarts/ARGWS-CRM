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
      const actionLiteral = new RegExp("\\$action===['\"]" + action + "['\"]|in_array\\(\\$action,\\[[^\\]]*['\"]" + action + "['\"]");
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
