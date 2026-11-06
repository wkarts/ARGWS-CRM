'use strict';
// Real view, jQuery, Bootstrap, DataTables and shipped PIGE JS; synthetic framework/bootstrap.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const {execFileSync}=require('node:child_process'),{chromium}=require('playwright');
const root=path.resolve(__dirname,'..');
const html=execFileSync(process.env.PHP_BIN||'php',[path.join(__dirname,'pige_bridge_settings_view.php')],{encoding:'utf8'}).replace(/<script src=[^>]+><\/script>/g,'');
const app=fs.readFileSync(path.join(root,'assets/js/app.js'),'utf8');
const inline=app.slice(app.indexOf('function appDataTableInline('),app.indexOf('// Returns datatbles export button'));
(async()=>{
 const browser=await chromium.launch({headless:true,executablePath:process.env.CHROMIUM_PATH||undefined});
 try{for(const width of [1280,390]){
  const page=await browser.newPage({viewport:{width,height:900}}),errors=[];
  page.on('pageerror',e=>errors.push(String(e)));await page.route('**/*',r=>r.abort());
  await page.route('https://crm.example/', r=>r.fulfill({status:200,contentType:'text/html',body:html}));
  await page.goto('https://crm.example/');await page.addStyleTag({path:path.join(root,'assets/plugins/bootstrap/css/bootstrap.min.css')});
  for(const file of ['assets/plugins/jquery/jquery.min.js','assets/plugins/bootstrap/js/bootstrap.min.js','assets/plugins/datatables/datatables.min.js'])await page.addScriptTag({path:path.join(root,file)});
  await page.addScriptTag({content:'window.app={lang:{dt_length_menu_all:"All",datatables:{},options:"Ações"},options:{tables_pagination_limit:10}};window.DataTablesInlineLazyLoadImages=function(){};window._table_jump_to_page=function(){};window.get_datatable_buttons=function(){return []};'+inline});
  await page.addScriptTag({path:path.join(root,'assets/js/native-admin-tables.js')});
  await page.addScriptTag({path:path.join(root,'modules/pige_bridge/assets/settings.js')});
  await page.waitForSelector('#pige-connections_filter input');
  assert.equal(await page.locator('#pige-connections tbody tr').count(),10);
  await page.locator('#pige-connections_filter input').fill('school-11');
  assert.equal(await page.locator('#pige-connections tbody tr').count(),1);
  await page.locator('.pige-edit').click();await page.waitForSelector('#pige-editor.in');
  assert.equal(await page.locator('#pige-connection-form [name=connection_key]').inputValue(),'school-11');
  let payload=await page.locator('#pige-connection-form').evaluate(f=>Object.fromEntries(new FormData(f)));
  assert.equal(payload.issuer_id,'8');assert.equal(payload.revision,'3');assert.equal(payload.pige_csrf,'a'.repeat(64));assert.equal(payload._company_context,'synthetic-context');
  assert.equal(await page.locator('#pige-issuer').isDisabled(),true);
  await page.locator('a[href="#pige-tab-prepare"]').click();
  assert.equal(await page.locator('#pige-prepare-form button').isDisabled(),false,'disabled connection can be prepared explicitly');
  const preparation=await page.locator('#pige-prepare-form').evaluate(f=>Object.fromEntries(new FormData(f)));
  assert.equal(preparation.connection_key,'school-11');assert.equal(preparation.revision,'3');assert.equal(preparation.pige_csrf,'a'.repeat(64));assert.equal(preparation._company_context,'synthetic-context');
  assert.equal(Object.hasOwn(preparation,'secret'),false,'stored HMAC never enters browser form');
  assert.equal(await page.locator('#pige-prepare-form [name=confirm_transfer]').isChecked(),false);
  await page.locator('#pige-prepare-credential').fill('synthetic-preparation-jwt');
  await page.locator('a[href="#pige-tab-connection"]').click();
  await page.locator('#pige-secret').fill('synthetic-must-clear');
  await page.locator('#pige-editor .modal-footer button').click();await page.waitForSelector('#pige-editor.in',{state:'hidden'});
  await page.waitForFunction(()=>document.querySelector('#pige-secret').value==='');
  assert.equal(await page.locator('#pige-secret').inputValue(),'');
  assert.equal(await page.locator('#pige-prepare-credential').inputValue(),'','closing modal clears preparation JWT');
  await page.locator('#pige-new').click();await page.waitForSelector('#pige-editor.in');
  assert.equal(await page.locator('#pige-issuer').isDisabled(),false);
  assert.equal(await page.locator('#pige-connection-form [name=connection_key]').inputValue(),'');
  assert.equal(await page.locator('.pige-existing-tab').first().isVisible(),false);
  assert.equal(await page.locator('#pige-secret').getAttribute('required'),'');
  await page.evaluate(() => {
    window.discoveryCalls=[];
    window.jQuery.ajax=function(options){
      const d=window.jQuery.Deferred();const request=d.promise();request.abort=()=>d.reject();
      window.discoveryCalls.push({action:options.data.discovery_action,issuer:options.data.issuer_id,csrf:options.data.pige_csrf,context:options.data._company_context,credentialPresent:options.data.admin_credential==='synthetic.header.signature',financialSecretPresent:Object.hasOwn(options.data,'secret')});
      setTimeout(()=>d.resolve(options.data.discovery_action==='schools'?{success:true,schools:[{id:'12345678-1234-1234-1234-123456789abc',name:'Institution <script>literal</script>'}]}:{success:true,configuration:{issuer_id:options.data.issuer_id,school_id:'12345678-1234-1234-1234-123456789abc',account_id:'discovered-account',environment:'production',remote_revision:3,secret_configured:true}}),10);
      return request;
    };
  });
  await page.locator('#pige-discovery-url').fill('https://pige.example');
  await page.locator('#pige-discovery-credential').fill('synthetic.header.signature');
  await page.locator('#pige-discovery-schools').click();
  await page.waitForFunction(()=>document.querySelector('#pige-discovery-school').options.length===2);
  assert.equal(await page.locator('#pige-discovery-school option').last().textContent(),'Institution <script>literal</script>');
  await page.locator('#pige-discovery-school').selectOption('12345678-1234-1234-1234-123456789abc');
  await page.locator('#pige-discovery-read').click();
  await page.waitForFunction(()=>document.querySelector('#pige-account_id').value==='discovered-account');
  assert.equal(await page.locator('#pige-environment').inputValue(),'production');
  assert.equal(await page.locator('#pige-connection-form [name=enabled]').isChecked(),false,'remote configuration does not enable bridge');
  assert.equal(await page.locator('#pige-secret').inputValue(),'','financial HMAC is never discovered');
  assert.equal(await page.locator('#pige-connection_key').inputValue(),'','remote row ID is not silently adopted');
  assert.equal(await page.locator('#pige-discovery-credential').inputValue(),'');
  const reads=await page.evaluate(()=>window.discoveryCalls);
  assert.deepEqual(reads.map(r=>r.action),['schools','configuration']);
  for(const r of reads){assert.equal(r.csrf,'a'.repeat(64));assert.equal(r.context,'synthetic-context');assert(r.credentialPresent&&!r.financialSecretPresent);}
  await page.evaluate(() => {
    window.jQuery('#pige-discovery-credential').val('synthetic.header.signature');
    window.jQuery.ajax=function(){const d=window.jQuery.Deferred();window.pendingDiscovery=d;const promise=d.promise();promise.abort=function(){};return promise;};
  });
  await page.locator('#pige-discovery-read').click();
  await page.waitForFunction(()=>!!window.pendingDiscovery);


  await page.locator('#pige-editor .modal-footer button').click();await page.waitForSelector('#pige-editor.in',{state:'hidden'});
  await page.locator('#pige-connections_filter input').fill('school-00');await page.locator('.pige-edit').click();
  await page.locator('a[href="#pige-tab-external"]').click();
  assert.equal(await page.locator('#pige-discovery').isVisible(),false,'existing immutable connection cannot be overwritten by discovery');
  assert.equal(await page.locator('#pige-prepare-form button').isDisabled(),true,'enabled connection cannot be prepared');
  await page.evaluate(()=>window.pendingDiscovery.resolve({success:true,configuration:{issuer_id:'7',school_id:'12345678-1234-1234-1234-123456789abc',account_id:'stale-must-not-apply',environment:'production',remote_revision:9}}));
  assert.equal(await page.locator('#pige-account_id').inputValue(),'account-0','late response from cancelled new form cannot overwrite another connection');
  assert.equal(await page.locator('#pige-discovery-status').textContent(),'');

  assert.equal(await page.locator('#pige-discovery-credential').inputValue(),'');
  assert.equal(await page.locator('[name=external_enabled]').isChecked(),false,'obsolete external consent stays unchecked');
  assert.equal(await page.locator('#pige-ext-account_row').inputValue(),'4');
  assert.equal(await page.locator('#pige-map-actor_id option:checked').textContent(),'Synthetic actor7');
  assert.equal(await page.locator('#pige-ext-profile').isDisabled(),true);
  await page.locator('#pige-ext-provider').selectOption('banco_brasil');
  assert.equal(await page.locator('#pige-ext-account_row').inputValue(),'','changing provider requires explicit account');
  assert.equal(await page.locator('#pige-ext-account_row option[value="4"]').count(),0,'Asaas ID cannot survive BB selection');
  await page.locator('#pige-ext-account_row').selectOption('5');
  assert.equal(await page.locator('#pige-ext-profile').isDisabled(),false);
  assert.equal(await page.locator('#pige-ext-profile').inputValue(),'','no automatic financial profile selection');
  await page.locator('#pige-ext-profile').selectOption('8');
  assert.equal(await page.locator('[name=external_enabled]').isChecked(),false,'selector does not activate consent');
  await page.locator('#pige-editor .modal-footer button').click();await page.waitForSelector('#pige-editor.in',{state:'hidden'});
  const dialog=page.waitForEvent('dialog').then(d=>d.dismiss());await page.locator('.pige-disable button').click();await dialog;
  assert.equal(await page.locator('#pige-connections').count(),1,'cancel keeps list');
  assert.equal(await page.evaluate(()=>window.leaked),undefined);assert.deepEqual(errors,[]);
  await page.close();
 }}finally{await browser.close();}
 console.log('PIGE browser: desktop/mobile real modal, search/pagination, A/B form ownership, revisions, token clearing, explicit disabled remote preparation form, read-only discovery, stale HTTP response, stale consent, cancel and escaped labels passed; synthetic framework, no HTTP writes.');
})().catch(e=>{console.error(e);process.exitCode=1;});
