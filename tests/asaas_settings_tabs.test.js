'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const policy = require('../modules/asaas/assets/js/settings-tabs.js');
assert.equal(policy.environmentValue(['0']), '0');
assert.equal(policy.environmentValue(['1']), '1');
for (const values of [[], ['0', '1'], ['0.0'], [true], [0]]) assert.equal(policy.environmentValue(values), '');
assert.equal(policy.sectionFor('debug'), 'authentication');
assert.equal(policy.sectionFor('file_log_enabled'), 'authentication');
assert.equal(policy.sectionFor('sandbox'), 'authentication');
assert.equal(policy.sectionFor('unknown_future_field'), 'authentication');
assert.equal(policy.sectionFor('profile_production_interest_value'), 'general');
assert.equal(policy.sectionFor('profile_sandbox_debug'), 'authentication');
assert.equal(policy.sectionFor('profile_production_active'), 'authentication');
assert.deepEqual(policy.settingParts('profile_sandbox_description'), {environment:'sandbox',key:'description'});
assert.deepEqual(policy.settingParts('profile_untrusted_description'), {environment:null,key:'profile_untrusted_description'});
const fixtureHtml = require('./support/AsaasSettingsFixture.cjs').html;
const dependencyFixture = fixtureHtml('');
const dependencySource = name => fs.readFileSync(path.join(__dirname, '..', name), 'utf8').replace(/<\/script/gi, '<\\/script');
const waypointOffset = dependencyFixture.indexOf(dependencySource('assets/plugins/waypoint/jquery.waypoints.min.js'));
const scrollableOffset = dependencyFixture.indexOf(dependencySource('assets/plugins/internal/bootstrap-nav-tabs-scrollable/bootstrap-nav-tab-scrollable.js'));
assert.ok(waypointOffset >= 0 && scrollableOffset > waypointOffset, 'native Waypoint is loaded before horizontalTabs');
const nativeDelay = fs.readFileSync(path.join(__dirname, '../assets/js/app.js'), 'utf8').match(/var delay = \(function \(\) \{[\s\S]*?\n\}\)\(\);/);
assert.ok(nativeDelay && dependencyFixture.includes(nativeDelay[0]), 'native delay callback dependency is included');
assert.ok(dependencyFixture.indexOf(nativeDelay[0]) < dependencyFixture.indexOf('window.bindNativeSettings'), 'delay exists before horizontal tabs can initialize');
console.log('Asaas settings JS policy/fixture: 19 checks passed');
if (!process.env.CHROMIUM_BIN) { console.log('DOM smoke skipped: set CHROMIUM_BIN to a Chromium executable.'); process.exit(0); }
const tests = `
let checks = 0;
function check(ok, name) { checks++; if (!ok) throw new Error(name); }
const api = module.exports;
try {
 const pane=fixture('1','1');const original=pane.querySelector('[name="settings[paymentmethod_asaas_api_key]"]');
 api.initialize(document);
 bindNativeSettings();
 check(pane.querySelectorAll('.nav-tabs-horizontal [role="tab"]').length===3,'three tabs');
 check(pane.querySelectorAll('#online_payments_asaas_tab > .tab-content > [role="tabpanel"]').length===3,'three panels');
 check(pane.querySelectorAll('.nav-tabs-horizontal > li[role="presentation"] > a[data-toggle="tab"]').length===3,'native tab markup');
 check(pane.querySelectorAll('button[role="tab"],a[role="tab"].btn').length===0,'tabs are not action buttons');
 check(Array.from(pane.querySelectorAll('.nav-tabs-horizontal [role="tab"]')).map(tab=>tab.textContent).join('|')==='Geral|Autenticação|Webhook e operações','original order preserved');
 document.getElementById('gateway-tab').click();
 const env=pane.querySelector('[name="settings[paymentmethod_asaas_sandbox]"]');
 check(env.tagName==='SELECT' && env.value==='1','server exact metadata wins over loose radio');
 check(pane.querySelectorAll('[name="settings[paymentmethod_asaas_sandbox]"]').length===1,'single environment field');
 check(env.options.length===3 && env.options[1].textContent==='Homologação' && env.options[2].textContent==='Produção','correct labels');
 check(env.required,'explicit selection required');
 check(original===pane.querySelector('[name="settings[paymentmethod_asaas_api_key]"]'),'existing field moved not copied');
 check(pane.querySelector('#asaas-settings-panel-authentication [name="settings[paymentmethod_asaas_debug]"]'),'debug separate');
 check(pane.querySelector('#asaas-settings-panel-authentication [name="settings[paymentmethod_asaas_file_log_enabled]"]'),'file logging separate');
 check(pane.querySelector('#asaas-settings-panel-authentication [name="settings[paymentmethod_asaas_unknown_future_field]"]'),'unknown settings preserved');
 check(pane.querySelector('[name="settings[paymentmethod_asaas_installmentCount]"]').readOnly,'unsupported installments readonly');
 document.getElementById('asaas-settings-tab-operations').click();
 check(!document.getElementById('asaas-settings-panel-operations').hidden && document.getElementById('asaas-settings-panel-authentication').hidden,'tab selection');
 check(document.getElementById('asaas-settings-tab-operations').getAttribute('aria-selected')==='true','aria selected');
 check(document.getElementById('settings-form').action.endsWith('&active_tab=online_payments_asaas_tab'),'save retains parent gateway');
 check(pane.classList.contains('active'),'nested tab leaves parent active');
 check(pane.querySelectorAll('#online_payments_asaas_tab > .tab-content > .tab-pane.active').length===1 && pane.querySelectorAll('.nav-tabs-horizontal > li.active').length===1,'single native active tab and panel');
 document.getElementById('asaas-settings-tab-operations').dispatchEvent(new KeyboardEvent('keydown',{key:'ArrowRight'}));
 check(!document.getElementById('asaas-settings-panel-general').hidden,'keyboard navigation');
 check(document.activeElement.id==='asaas-settings-tab-general','keyboard wraps focus');
 document.getElementById('asaas-settings-tab-general').dispatchEvent(new KeyboardEvent('keydown',{key:'End'}));
 check(document.activeElement.id==='asaas-settings-tab-operations','End reaches last tab');
 document.getElementById('asaas-settings-tab-operations').dispatchEvent(new KeyboardEvent('keydown',{key:'Home'}));
 check(document.activeElement.id==='asaas-settings-tab-general','Home reaches first tab');
 const data=Array.from(new FormData(document.getElementById('settings-form')).entries());
 document.getElementById('asaas-settings-tab-general').click();
 check(JSON.stringify(data)===JSON.stringify(Array.from(new FormData(document.getElementById('settings-form')).entries())),'tab navigation preserves POST payload including hidden fields and other gateways');
 env.value='';document.getElementById('general-tab').click();
 check(!document.getElementById('settings-form').reportValidity(),'invalid environment blocked');
 check(pane.classList.contains('active') && !document.getElementById('asaas-settings-panel-authentication').hidden,'invalid field activates outer and inner tab');
 check(document.activeElement===env,'native validation focuses visible environment');env.value='1';
 check(pane.querySelector('#asaas-settings-panel-authentication #fixture-callback'),'URL fragment moved to webhook tab');
 check(pane.querySelector('#asaas-settings-panel-authentication #fixture-api-sandbox'),'API URLs in authentication tab');
 original.value='synthetic-key';const reveal=pane.querySelector('[data-asaas-reveal="'+original.id+'"]');
 reveal.click();check(original.type==='text'&&reveal.getAttribute('aria-pressed')==='true','explicit admin reveal');
 document.getElementById('asaas-settings-tab-operations').click();check(original.type==='password','leaving tab conceals key');
 api.initialize(document);check(pane.querySelectorAll('.nav-tabs-horizontal [role="tab"]').length===3,'idempotent init');
 fixture('0','0.0');api.initialize(document);check(document.querySelector('select').value==='','invalid legacy not production');
 fixture('1','0');api.initialize(document);check(document.querySelector('select').value==='0','valid production explicit');
 fixture('1','invalid');api.initialize(document);check(document.querySelector('select').value==='','invalid metadata still rejected');
 prepareFixture('1','1',true);document.getElementById('gateway-tab').click();
 check(document.querySelectorAll('#asaas-settings-panel-general [data-asaas-profile]').length===2,'independent payment profile groups');
 const production=document.querySelector('[name="settings[paymentmethod_asaas_profile_production_description]"]'),sandbox=document.querySelector('[name="settings[paymentmethod_asaas_profile_sandbox_description]"]');
 production.value='Production description';sandbox.value='Homologation description';document.getElementById('asaas-operation-environment').value='0';
 check(production.value==='Production description'&&sandbox.value==='Homologation description','changing active environment never overwrites profile fields');
 check(production.closest('[data-asaas-profile]').getAttribute('data-asaas-profile')==='production'&&sandbox.closest('[data-asaas-profile]').getAttribute('data-asaas-profile')==='sandbox','profiles correctly scoped');
 check(document.querySelector('[name="settings[paymentmethod_asaas_profile_sandbox_sync_enabled]"]').disabled,'unsupported sync cannot imply operation');
 const result=document.createElement('pre');result.id='result';result.textContent='PASS '+checks;document.body.appendChild(result);
} catch (error) { document.body.innerHTML='<pre id="result">FAIL '+error.message+'</pre>'; }
`;
// The Chrome CLI may exit successfully without emitting --dump-dom when combined
// with --screenshot. Drive the browser protocol and await our assertion marker,
// not process exit or network idle (the fixture deliberately attempts no live API).
(async () => {
 const {chromium} = require(process.env.ASAAS_PLAYWRIGHT_MODULE || 'playwright');
 const browser = await chromium.launch({headless:true, executablePath:process.env.CHROMIUM_BIN, timeout:30000});
 const errors=[];
 try {
  const context=await browser.newContext({viewport:{width:1280,height:900}});
  await context.route('**/*', route=>route.abort());
  const page=await context.newPage();
  page.on('pageerror', error=>errors.push(error.message));
  page.setDefaultTimeout(30000);
  await page.setContent(fixtureHtml(tests),{waitUntil:'domcontentloaded',timeout:30000});
  try {
   await page.locator('#result').waitFor({state:'attached'});
  } catch (error) {
   throw new Error('Asaas fixture did not finish its DOM assertions; page errors: '+JSON.stringify(errors),{cause:error});
  }
  const result=await page.locator('#result').textContent();
  assert.match(result,/^PASS [1-9][0-9]*$/, 'browser DOM assertions: '+result);
  assert.deepEqual(errors,[], 'uncaught browser errors');
  const screenshot=process.env.ASAAS_UI_SCREENSHOT?path.resolve(process.env.ASAAS_UI_SCREENSHOT):null;
  if(screenshot) {
   fs.mkdirSync(path.dirname(screenshot),{recursive:true});
   await page.screenshot({path:screenshot,timeout:30000});
  }
  console.log('Asaas settings browser DOM: '+result);
 } finally {await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
