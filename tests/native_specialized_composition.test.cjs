'use strict';
const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const root=path.join(__dirname,'..');
const read=p=>fs.readFileSync(path.join(root,p),'utf8');
const active=dir=>fs.readdirSync(path.join(root,dir)).filter(name=>name.endsWith('.php')).filter(name=>!read(path.join(dir,name)).match(/\/\/\s*Removed\b/));

test('specialized company operations are composed into canonical controllers',()=>{
  assert.deepEqual(active('application/controllers/admin').filter(name=>name.startsWith('Company_')),[]);
  assert.equal(fs.existsSync(path.join(root,'application/views/admin/companies')),false,'parallel company view tree must be physically absent');
  for(const view of ['application/views/admin/projects/manage.php','application/views/admin/tasks/manage.php','application/views/admin/tickets/list.php','application/views/admin/invoices/manage.php','application/views/admin/expenses/manage.php','application/views/admin/settings/all.php','application/views/admin/staff/manage.php']) assert.equal(fs.existsSync(path.join(root,view)),true,view+' must remain an official renderer');
  const trait=read('application/services/NativeCompanySpecializedOperations.php');
  for(const method of ['nativeProjectExtras','nativeProjectPortalRules','nativeProjectBillingRates','nativeProjectFixedBilling','nativeProjectCopies','nativeProjectTimeBilling','nativeTaskCopies','nativeTaskRecurring','nativeTaskStandalone','nativeTaskBilling','nativeTaskTimeBatches','nativeTicketAi','nativeTicketConnect','nativeTicketConnectApi','nativeInvoiceStandaloneBilling','nativeExpensePurchases','nativeSettingsPusher','nativeSettingsAsaas','nativeSettingsImports','nativeSettingsEfi','nativeSettingsGoogleMailOauth','nativeSettingsMicrosoftMailOauth','nativeSettingsApiTokens','nativeHr','nativeHrFile','nativeHrPdf','nativeHrExport','nativeHrMine','nativeHrMine_file']) assert.match(trait,new RegExp('function '+method+'\\b'));
  for(const controller of ['Projects.php','Tasks.php','Tickets.php','Invoices.php','Expenses.php','Settings.php','Staff.php']) assert.match(read('application/controllers/admin/'+controller),/use NativeCompanySpecializedOperations;/);
});

test('canonical specialized actions are finite and admitted only by native route catalog',()=>{
  const routes=read('application/services/NativeCompanyRoutes.php');
  for(const route of ['extras','portal_rules','billing_rates','fixed_billing','copies','time_billing','recurring','standalone','billing','time_batches','ai','connect','connect_api','standalone_billing','purchases','pusher','asaas','imports','efi','google_mail_oauth','microsoft_mail_oauth','api_tokens','hr','hr_file','hr_pdf','hr_export','hr_mine','hr_mine_file']) assert.match(routes,new RegExp("'"+route+"'\\s*=>"));
  const scope=read('application/services/CompanyRequestScope.php');
  assert.doesNotMatch(scope,/class\s*===\s*'company_(?:ai|asaas|efi|hr|purchases|tasks|projects|tickets|invoices|expenses)'/);
});
