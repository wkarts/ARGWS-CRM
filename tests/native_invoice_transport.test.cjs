'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const root = path.join(__dirname, '..');
const read = p => fs.readFileSync(path.join(root, p), 'utf8');
test('native constructor returns before loading legacy financial models', () => {
  const source = read('application/controllers/admin/Invoices.php');
  const constructor = source.slice(source.indexOf('public function __construct()'), source.indexOf('public function index('));
  assert.match(constructor, /CompanyRequestScope::nativeFor\(\$this\)[\s\S]*?return;/);
  assert(constructor.indexOf('return;') < constructor.indexOf("model('invoices_model')"));
  const pdf = source.slice(source.indexOf('public function pdf('), source.indexOf('private function companyPdf('));
  assert(pdf.indexOf('$this->companyPdf($id)') < pdf.indexOf('user_can_view_invoice'));
});
test('PDF uses existing scoped document/audit and never calls legacy helpers or forwards TCPDF modes', () => {
  const source = read('application/services/NativeCompanyInvoicePdf.php');
  assert.match(source, /new CompanyInvoices/);
  assert.match(source, /\$service->document\(\$issuer, \$actor, \$invoice\)/);
  assert.match(source, /new Issuer_invoice_pdf\(\$document\)/);
  assert.match(source, /Output\('', 'S'\)/);
  assert.match(source, /confirmPdfDownload\(\$issuer, \$actor, \$id\)/);
  assert(!/hooks\(|get_option\(|invoices_model|invoice_pdf\(/.test(source.replace('Issuer_invoice_pdf(', '')));
  assert(source.indexOf('confirmPdfDownload') < source.lastIndexOf("'body'=>$bytes"));
});
test('native read adapter binds issuer and uses canonical financial position without global model calls', () => {
  const source = read('application/services/NativeCompanyInvoiceRead.php');
  assert.match(source, /d\.invoice_id=i\.id AND d\.issuer_id=i\.issuer_id/);
  assert.match(source, /WHERE i\.issuer_id=\?/);
  assert.match(source, /\$this->invoices->document\(\$issuer, \$actor, \$row\['id'\]\)/);
  assert.match(source, /\$document\['position'\]\['received'\]/);
  assert.match(source, /\$document\['position'\]\['receivable'\]/);
  assert(!/hooks\(|get_option\(|invoices_model|SUM\(amount\)|UPDATE |INSERT |DELETE /.test(source));
  assert.match(source, /\$unitId !== \$currency/);
  assert.match(source, /\$this->rows\(\$sql, \$params\) !== \$rows/);
  assert.match(source, /\$params=array_merge\(\[\$issuer\],\$related\['params'\]\)/);
  assert.match(source, /if \(\$issuer === \$primary\)/);
  assert.match(source, /WHERE i\.issuer_id IS NULL'\.\$related\['legacy'\]\.' ORDER BY i\.id/);
  for (const marker of ['canonical_verified_position', 'native_legacy_recorded']) assert(source.includes(marker));
});
test('real fixture includes cross-owner, historical snapshot, currency/year separation and unchanged cash', () => {
  const source = read('tests/companies/native_invoice_read.php');
  for (const marker of ['foreign client filter', 'historical B recipient', 'other currency/year', 'USD never mixed', 'summary cannot mutate any receipt', 'principal union preserves']) {
    assert(source.includes(marker), marker);
  }
});
test('lineage dispatcher resolves real row before selecting source and never converts missing to legacy', () => {
  const source = read('application/services/NativeCompanyInvoiceIdentity.php');
  assert.match(source, /count\(\$rows\) !== 1/);
  assert.match(source, /\$row\['issuer_id'\] === null/);
  assert.match(source, /\$primary !== \$issuer/);
  assert.match(source, /IssuerPolicy::id\(\$row\['issuer_id'\]\) !== \$issuer/);
  assert.match(source, /\$read\(\) !== \$rows/);
  assert(!/get_option\(|hooks\(|UPDATE |INSERT |DELETE /.test(source));
});
test('native presentation metadata stays inside canonical writer and never exposes a standalone mutator', () => {
  const fields = read('application/services/CompanyInvoiceNativeFields.php');
  const writer = read('application/services/CompanyInvoices.php');
  assert(!/UPDATE |INSERT |DELETE |->query\(/.test(fields));
  assert.match(writer, /private function writeNativeFields/);
  assert.match(writer, /WHERE id=\? AND issuer_id=\?/);
  const legacy = writer.slice(writer.indexOf('private function legacyRequestHash('), writer.indexOf('private function insert('));
  const guard = legacy.match(/if\s*\((.*?)\)\s*return\s+null\s*;/s);
  assert(guard, 'legacy hash must reject native contracts before computing compatibility bytes');
  const branches = guard[1].split('||').map(part => part.trim());
  for (const field of ['native_fields', 'native_project', 'native_preferences', 'calculation_contract']) {
    assert(branches.includes("array_key_exists('" + field + "',$data)"), field + ' cannot enter historical hash compatibility');
  }
  assert(branches.every(part => /^array_key_exists\('[a-z_]+',\$data\)$/.test(part)), 'each native presence must independently reject fallback');
  assert.match(writer, /company_invoice_provider_review_required/);
  assert.match(writer, /CompanyInvoiceNativeFields::input\(\$read\[0\]\)\s*!==\s*\$input/);
  const draft = read('application/services/CompanyInvoiceDraftsInput.php');
  assert.match(draft, /array_key_exists\('native_fields',\$c\)/);
});
test('invoice native note endpoints compose the owned auxiliary service and actual existing partial', () => {
  const source = read('application/controllers/admin/Invoices.php');
  const notes = source.slice(source.indexOf('private function companyNotes('),source.indexOf('public function pause_overdue_reminders('));
  assert.match(notes, /new NativeCompanyAuxiliaryUi/);
  assert(notes.indexOf('assertNativeMutation($this)') < notes.indexOf("->saveNote('invoice'"));
  assert.match(notes, /admin\/includes\/sales_notes_template/);
  assert.match(notes, /native_company_notes/);
  assert(!/misc_model|user_can_view_invoice/.test(notes));
});
test('table presentation reuses actual native rule/JS classes with explicit owned saved filters', () => {
  const source=read('application/services/NativeCompanyInvoiceTable.php');
  assert.match(source,/App_table_filter::new/);
  assert.match(source,/utilities\\Js::from/);
  assert.match(source,/\$filter\['issuer_id'\]/);
  assert.match(source,/\$filter\['staff_id'\]/);
  assert(!/App_table::|hooks\(|get_option\(|->filters_model|->raw\(/.test(source));
  assert.match(source,/private array \$savedFilters/);
});

test('native due-date endpoint uses owned public settings without global date hooks',()=>{
  const dates=read('application/services/NativeCompanyInvoiceDates.php');
  const controller=read('application/controllers/admin/Invoices.php');
  assert.match(dates,/CompanySettingsStorage/);
  assert.match(dates,/exists\(\$issuer,\$key\)/);
  assert.match(dates,/CompanySettingsFormats::dates/);
  assert.doesNotMatch(dates,/get_option\(|hooks\(\)|to_sql_date\(|_d\(/);
  const action=controller.slice(controller.indexOf('public function get_due_date()'));
  assert.ok(action.indexOf('assertNativeMutation')<action.indexOf('->dueDate('));
  assert.ok(action.indexOf('NativeCompanyInvoiceDates')<action.indexOf("get_option('invoice_due_after')"));
});


test('project selection composes existing owned writer without legacy project IDs or public mutator',()=>{
 const writer=read('application/services/CompanyInvoices.php');
 assert.match(writer,/private function writeNativeProject/);
 assert.doesNotMatch(writer,/public function writeNativeProject/);
 assert.match(writer,/getInOwnedTransaction\(\$issuer,\$actor,\$input\['source_id'\]\)/);
 assert.match(writer,/CompanyInvoiceProjectSchema::assertValid/);
 assert.doesNotMatch(writer,/SET project_id=/);
 const drafts=read('application/services/CompanyInvoiceDrafts.php');
 assert.match(drafts,/validateProjectInOwnedTransaction/);
 assert.match(drafts,/nativeProjectFieldsOwned/);
});

test('totals keeps existing native partial with authorized customer/project namespaces',()=>{
 const controller=read('application/controllers/admin/Invoices.php');
 const action=controller.slice(controller.indexOf('public function get_invoices_total()'),controller.indexOf('public function record_invoice_payment_ajax'));
 assert.ok(action.indexOf('assertNativeMutation')<action.indexOf('->summary('));
 assert.ok(action.indexOf("view('admin/invoices/invoices_total_template'")<action.indexOf('load_invoices_total_template()'));
 const reader=read('application/services/NativeCompanyInvoiceRead.php');
 assert.match(reader,/NativeCompanyResourceReferences::resolve/);
 assert.match(reader,/r\.issuer_id=i\.issuer_id AND r\.invoice_id=i\.id AND r\.client_id=i\.clientid/);
 assert.match(reader,/source_kind'\]==='company_project'/);
 assert.match(reader,/source_kind'\]==='project'/);
});

test('advanced filters execute before counts with bound params and canonical recurrence proof',()=>{
 const reader=read('application/services/NativeCompanyInvoiceRead.php');
 assert.match(reader,/NativeCompanyInvoiceRules::compile/);
 assert.match(reader,/\$where=\$rules\['sql'\]/);
 assert.match(reader,/\$params=array_merge\(\$params,\$rules\['params'\]\)/);
 assert.match(reader,/\$lineage==='named'\?array_merge\(\[\$issuer\],\$related\['params'\]\)/);
 assert.match(reader,/\$allParams=array_merge\(\$allParams,\$projectionParams,\$params\)/);
 assert.match(reader,/\$service->document\(\$issuer,\$actor,\$mode\['schedule_id'\]\)/);
 const rules=read('application/services/NativeCompanyInvoiceRules.php');
 assert.doesNotMatch(rules,/eval\(|call_user_func|hooks\(|get_option\(/);
 assert.match(rules,/principal_invoice_id=i\.id/);
});
