'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const cp = require('node:child_process');
const policy = require('../modules/asaas/assets/js/settings-tabs.js');
assert.equal(policy.environmentValue(['0']), '0');
assert.equal(policy.environmentValue(['1']), '1');
for (const values of [[], ['0', '1'], ['0.0'], [true], [0]]) assert.equal(policy.environmentValue(values), '');
assert.equal(policy.sectionFor('debug'), 'diagnostics');
assert.equal(policy.sectionFor('file_log_enabled'), 'diagnostics');
assert.equal(policy.sectionFor('sandbox'), 'authentication');
assert.equal(policy.sectionFor('unknown_future_field'), 'resources');
console.log('Asaas settings JS policy: 11 checks passed');
if (!process.env.CHROMIUM_BIN) { console.log('DOM smoke skipped: set CHROMIUM_BIN to a Chromium executable.'); process.exit(0); }
const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'asaas-tabs-'));
const script = fs.readFileSync(path.join(__dirname, '../modules/asaas/assets/js/settings-tabs.js'), 'utf8');
const tests = `
let checks = 0;
function check(ok, name) { checks++; if (!ok) throw new Error(name); }
const api = module.exports;
function fixture(valid, value) {
 document.body.innerHTML = '<div id="online_payments_asaas_tab"><a href="https://crm.example.test/admin/asaas" data-asaas-webhook-admin-url data-asaas-environment-valid="'+valid+'" data-asaas-environment-value="'+value+'">Webhook</a></div>';
 const pane = document.getElementById('online_payments_asaas_tab');
 ['sandbox','debug','file_log_enabled','api_key','card_only','sync_limit','unknown_future_field','installmentCount'].forEach(key => {
  const group = document.createElement('div'); group.className='form-group';
  group.innerHTML='<label>'+key+'</label><input name="settings[paymentmethod_asaas_'+key+']" value="0" '+(key==='sandbox'?'type="radio" checked':'')+'>';
  pane.appendChild(group);
 });
 return pane;
}
try {
 const pane=fixture('1','1');const original=pane.querySelector('[name="settings[paymentmethod_asaas_api_key]"]');
 api.initialize(document);
 check(pane.querySelectorAll('[role="tab"]').length===6,'six tabs');
 check(pane.querySelectorAll('[role="tabpanel"]').length===6,'six panels');
 const env=pane.querySelector('[name="settings[paymentmethod_asaas_sandbox]"]');
 check(env.tagName==='SELECT' && env.value==='1','server exact metadata wins over loose radio');
 check(pane.querySelectorAll('[name="settings[paymentmethod_asaas_sandbox]"]').length===1,'single environment field');
 check(env.options.length===3 && env.options[1].textContent==='Homologação [sandbox]' && env.options[2].textContent==='Produção','correct labels');
 check(env.required,'explicit selection required');
 check(original===pane.querySelector('[name="settings[paymentmethod_asaas_api_key]"]'),'existing field moved not copied');
 check(pane.querySelector('#asaas-settings-panel-diagnostics [name="settings[paymentmethod_asaas_debug]"]'),'debug separate');
 check(pane.querySelector('#asaas-settings-panel-diagnostics [name="settings[paymentmethod_asaas_file_log_enabled]"]'),'file logging separate');
 check(pane.querySelector('#asaas-settings-panel-resources [name="settings[paymentmethod_asaas_unknown_future_field]"]'),'unknown settings preserved');
 check(pane.querySelector('[name="settings[paymentmethod_asaas_installmentCount]"]').readOnly,'unsupported installments readonly');
 document.getElementById('asaas-settings-tab-webhook').click();
 check(!document.getElementById('asaas-settings-panel-webhook').hidden && document.getElementById('asaas-settings-panel-authentication').hidden,'tab selection');
 check(document.getElementById('asaas-settings-tab-webhook').getAttribute('aria-selected')==='true','aria selected');
 document.getElementById('asaas-settings-tab-webhook').dispatchEvent(new KeyboardEvent('keydown',{key:'ArrowRight'}));
 check(!document.getElementById('asaas-settings-panel-resources').hidden,'keyboard navigation');
 check(pane.querySelector('#asaas-settings-panel-security a').href==='https://crm.example.test/admin/asaas','dedicated admin link');
 api.initialize(document);check(pane.querySelectorAll('[role="tab"]').length===6,'idempotent init');
 fixture('0','0.0');api.initialize(document);check(document.querySelector('select').value==='','invalid legacy not production');
 fixture('1','0');api.initialize(document);check(document.querySelector('select').value==='0','valid production explicit');
 fixture('1','invalid');api.initialize(document);check(document.querySelector('select').value==='','invalid metadata still rejected');
 fixture('1','1'); api.initialize(document);
 const result=document.createElement('pre');result.id='result';result.textContent='PASS '+checks;document.body.appendChild(result);
} catch (error) { document.body.innerHTML='<pre id="result">FAIL '+error.message+'</pre>'; }
`;
try {
 const file=path.join(dir,'fixture.html');fs.writeFileSync(file,'<!doctype html><html><body><script>var module={exports:{}};</script><script>'+script+'</script><script>'+tests+'</script></body></html>');
 const screenshot=process.env.ASAAS_UI_SCREENSHOT?path.resolve(process.env.ASAAS_UI_SCREENSHOT):null;
 if(screenshot)fs.mkdirSync(path.dirname(screenshot),{recursive:true});
 const result=cp.spawnSync(process.env.CHROMIUM_BIN,[...(screenshot?['--screenshot='+screenshot,'--window-size=1280,900']:[]),'--headless','--user-data-dir='+path.join(dir,'profile'),'--no-sandbox','--disable-gpu','--disable-dev-shm-usage','--disable-background-networking','--no-first-run','--dump-dom','file://'+file],{encoding:'utf8',timeout:30000});
 assert.equal(result.status,0,result.stderr);const match=result.stdout.match(/<pre id="result">([^<]+)<\/pre>/);assert.ok(match,result.stdout);assert.match(match[1],/^PASS /);console.log('Asaas settings browser DOM: '+match[1]);
} finally {fs.rmSync(dir,{recursive:true,force:true});}
