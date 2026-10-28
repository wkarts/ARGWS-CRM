'use strict';
const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs');
const read=path=>fs.readFileSync(path,'utf8');
test('wizard uses native navigation without bypassing draft step guards',()=>{
 const view=read('application/views/admin/issuers/onboarding.php');
 assert.match(view,/class="nav nav-tabs company-onboarding-steps"/);
 assert.ok(view.includes('$number<=min((int)($draft[\'step\']??0),5)'));
 assert.ok(view.includes('aria-disabled="true"'));
 for(const field of ['name="company_onboarding_csrf"','name="revision"','name="step"','name="action"'])assert.ok(view.includes(field));
 for(const action of ['Consultar CNPJ','Salvar e continuar','Criar empresa e aplicar permissões'])assert.ok((view+read('application/views/admin/issuers/identity_fields.php')).includes(action));
});
test('shared identity tabs preserve fields and support history and keyboard',()=>{
 const view=read('application/views/admin/issuers/identity_fields.php'),js=read('assets/js/company-identity.js');
 assert.match(view,/class="nav nav-tabs company-identity-sections"/);
 for(const key of ['company-identification','company-address','company-contact'])assert.ok(view.includes('href="#'+key+'"'));
 for(const contract of ['aria-controls','aria-labelledby','aria-selected',"'ArrowRight'","'ArrowLeft'","'Home'","'End'","history.pushState(null, '', tabs[index].hash)","addEventListener('popstate'","addEventListener('hashchange'","addEventListener('invalid'"])assert.ok(js.includes(contract),contract);
 const navigation=js.slice(js.indexOf('var navigation ='),js.indexOf('function target(key)'));
 assert.doesNotMatch(navigation,/\.submit\(|fetch\(|\.value\s*=/,'presentation never submits, queries providers or rewrites fields');
 assert.ok(read('assets/css/company-identity.css').includes('overflow-x:auto'));
 assert.doesNotMatch(read('assets/css/company-identity.css'),/company-onboarding-steps li\{[^}]*border-radius/,'remove boxed-card navigation');
});
test('existing editor is scoped, CSRF protected and revision guarded before writes',()=>{
 const controller=read('application/controllers/admin/Issuers.php'),service=read('application/services/issuers/IssuerRegistry.php'),view=read('application/views/admin/issuers/identity_modal.php');
 for(const contract of ["=== 'edit_identity'","=== '1'","method(true)!=='POST'",'hash_equals($token,$submitted)','identityRevision($actor,$id)','updateExistingIdentity','canManage($id,$actor)'])assert.ok(controller.includes(contract),contract);
 for(const field of ['name="issuer_id"','name="identity_revision"','name="issuers_csrf"','name="_company_context"','value="edit_identity"'])assert.ok(view.includes(field),field);
 assert.ok(view.includes("require __DIR__.'/identity_fields.php'"));
 assert.doesNotMatch(view,/value="(?:begin|advance|complete)"|company_onboarding/,'editing is not creation');
 assert.ok(service.indexOf('hash_equals($expectedIdentity,$this->identityRevision')<service.indexOf('UPDATE {$this->tables}registry SET legal_name='),'revision check precedes write inside transaction');
 assert.ok(service.includes("$input['active']=1"));
 const js=read('assets/js/company-identity-modal.js');
 for(const contract of ['event.origin!==location.origin','event.source!==frame.contentWindow','opener.focus()','company-identity-close','window.parent===window','button.dataset.companyEditReturn'])assert.ok(js.includes(contract));
});
test('official company Settings uses one native field component with explicit issuer adapter',()=>{
 const native=read('application/views/admin/settings/includes/company.php'),fields=read('application/views/admin/settings/includes/identity_fields.php'),controller=read('application/services/NativeCompanySettingsController.php');
 assert.ok(native.includes("require __DIR__.'/identity_fields.php'"));
 assert.ok(native.includes('$companyIdentityNative=true'));
 assert.ok(controller.includes("'native_settings_company_values'=>\$group==='company'?\$page['values']:null"));
 assert.ok(fields.includes("echo render_input('settings['.$name.']',$label,$companyIdentityOption($name))"));
 assert.ok(fields.includes('empty($companyIdentityNative)'));
 assert.ok(fields.includes("$inputId='company-setting-"));
 assert.doesNotMatch(fields,/get_option\(|render_custom_fields\(|hooks\(/,'shared component has no implicit global data access');
 assert.ok(native.includes('render_custom_fields'),'legacy principal feature retained');
 const legacySettings='application/views/admin/settings/scoped.php';
 assert.ok(!fs.existsSync(legacySettings)||/^<\?php\s*\/\/\s*Removed\b/.test(read(legacySettings)),'parallel Settings view is absent or inert');
});
