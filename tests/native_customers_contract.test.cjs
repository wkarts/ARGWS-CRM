const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const read=p=>fs.readFileSync(p,'utf8');
test('native routes use scoped services and original renderers, not parallel redirect',()=>{
 const s=read('application/controllers/admin/Clients.php');
 const branch=s.slice(s.indexOf('public function client('),s.indexOf("if (staff_cant('view', 'customers'))",s.indexOf('public function client(')));
 assert.match(branch,/nativeCompanyClients/);assert.match(branch,/assertNativeMutation/);
 assert.match(branch,/admin\/clients\/client/);assert.doesNotMatch(branch,/redirect\([^\n]*company_clients/);
 assert.match(branch,/_native_company_csrf/);
});
test('primary company-owned customer entry stays on the official Clients route',()=>{
 const scope=read('application/services/CompanyRequestScope.php');
 const clients=read('application/controllers/admin/Clients.php');
 const manage=read('application/views/admin/clients/manage.php');
 const profile=read('application/views/admin/clients/groups/profile.php');
 assert.match(scope,/ownedClientRoute/);assert.match(scope,/source.*company/);assert.match(scope,/native_company_csrf/);
 assert.match(clients,/native_customer_action/);assert.match(clients,/source.*company/);
 assert.match(manage,/clients\/client\?source=company/);assert.match(profile,/native_customer_action/);
 assert.doesNotMatch(read('application/views/admin/leads/native.php'),/company_clients/);
 assert.doesNotMatch(read('application/controllers/admin/Leads.php'),/Company_leads|admin\/companies\/leads/);
});
test('native field writes keep authorization and readback in owner transaction',()=>{
 const s=read('application/services/CompanyClientNativeFields.php');
 assert.match(s,/IssuerTransaction::assertOwned/);assert.match(s,/owner_issuer_id=\?/);
 assert.match(s,/company_client_currency_locked/);assert.match(s,/issuer_client_write_unconfirmed/);
 assert.doesNotMatch(s,/get_option|hooks\(|clients_model|stripe_id/);
});
test('new group ownership does not backfill global definitions',()=>{
 const s=read('application/services/CompanyCustomerGroupsSchema.php');
 assert.match(s,/PRIMARY KEY\(group_id\)/);assert.match(s,/FOREIGN KEY\(issuer_id\)/);
 assert.doesNotMatch(s,/INSERT INTO|UPDATE |DELETE FROM/);
 const service=read('application/services/CompanyCustomerGroups.php');
 assert.match(service,/manage_groups/);assert.match(service,/company_client_group_owner_mismatch/);
});
test('shared identity excludes additional private native fields',()=>{
 const s=read('application/services/NativeCompanyClients.php');
 assert.match(s,/owner_issuer_id.*===\$this->issuer\)\$details\['native_profile'\]/);
 assert.match(s,/THEN c.phonenumber ELSE NULL/);
 assert.match(s,/THEN c.datecreated ELSE NULL/);
});
test('native contact writer binds owner, permissions and password revocation',()=>{
 const s=read('application/services/CompanyNativeContacts.php');
 assert.match(s,/IssuerTransaction::run/);assert.match(s,/owner_issuer_id=\?/);
 assert.match(s,/company_contact_permission_denied/);assert.match(s,/unset\(\$row\['password'\]\)/);
 assert.match(s,/company_portal_tokens WHERE contact_id=/);assert.match(s,/user_auto_login WHERE user_id=\? AND staff=0/);
 assert.doesNotMatch(s,/get_option|hooks\(|send_mail|clients_model/);
});
