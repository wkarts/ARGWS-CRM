'use strict';
const { selectCompanyOption } = require('../helpers/native_selector_driver.cjs');
// Native HR credentials: issue, rotate and revoke through GUI; reads use fresh bearer-only contexts.
const assert=require('node:assert/strict'),{execFileSync}=require('node:child_process'),{chromium,request}=require('playwright'),fs=require('node:fs'),path=require('node:path'),crypto=require('node:crypto');
const url=process.env.ISSUERS_BROWSER_URL,database=process.env.ISSUERS_BROWSER_DB_NAME,artifacts=process.env.ISSUERS_BROWSER_ARTIFACTS;
const source=process.env.ISSUERS_BROWSER_SOURCE_ROOT||process.cwd(),localApp=process.env.ISSUERS_HR_HTTP_APP,app=process.env.ISSUERS_BROWSER_APP_CONTAINER,php=process.env.ISSUERS_HR_HTTP_PHP||'php';
assert.match(url||'',/^http:\/\/127\.0\.0\.1:\d+$/);assert(['argws_upgrade_ci','argws_fresh_ci','argws_crm_ci','argws_hr_ci'].includes(database));assert(artifacts&&path.isAbsolute(artifacts));
if(localApp){assert(path.isAbsolute(localApp));assert.equal(database,'argws_hr_ci');assert.equal(process.env.ISSUERS_HR_CANDIDATE,'1');}else assert.match(app||'',/^argws-crm-(upgrade|fresh)-\d+$/);
fs.mkdirSync(artifacts,{recursive:true});const fixturePath='tests/companies/hr_tokens_http_fixture.php';
if(localApp)fs.copyFileSync(path.join(source,fixturePath),path.join(localApp,fixturePath));else execFileSync('docker',['cp',path.join(source,fixturePath),app+':/app/'+fixturePath]);
function fixture(...args){return JSON.parse(localApp?execFileSync(php,[path.join(localApp,fixturePath),...args.map(String)],{cwd:localApp,env:process.env,encoding:'utf8'}):execFileSync('docker',['exec','-e','ISSUERS_BROWSER_DB_NAME='+database,app,'php',fixturePath,...args.map(String)],{encoding:'utf8'}));}
const labels={
 'hr:read':'Consultar diretório, estrutura e orientações de RH',
 'hr:write':'Cadastrar e editar vínculos de funcionários',
 'hr:configure':'Configurar estrutura e orientações de RH',
 'hr:sensitive:read':'Consultar dados pessoais e registros restritos de RH',
 'hr:sensitive:write':'Editar dados pessoais e criar, editar ou anular registros de RH',
 'hr:sensitive:approve':'Aprovar ou rejeitar registros restritos de RH',
 'hr:contracts:read':'Consultar contratos de RH e seus PDFs privados',
 'hr:contracts:write':'Criar, editar, ativar e cancelar contratos de RH',
 'hr:training:read':'Consultar cursos e atribuições de treinamento',
 'hr:training:write':'Criar e editar cursos de treinamento',
 'hr:training:assign':'Atribuir e cancelar treinamentos de funcionários',
 'hr:files:read':'Consultar e baixar arquivos privados de RH',
 'hr:files:write':'Anexar e ocultar arquivos privados de RH',
 'hr:access:manage':'Emitir e revogar links privados de participantes de RH',
 'hr:import':'Revisar e confirmar importação de funcionários',
 'hr:reports':'Consultar e exportar relatórios de RH'
};
const setup=fixture('context'),proof={result:'RUNNING',checks:0,candidate_only:setup.candidate_only,native_version:setup.native_version,companies:setup.owners,sources_before:fixture('sources'),preservation_before:fixture('preservation'),scenarios:[]};
let checks=0;function eq(actual,expected,message){assert.deepEqual(actual,expected,message);checks++;}
eq([...setup.scopes].sort(),Object.keys(labels).sort(),'Every declared HR scope has one explicit expected label');
async function go(page,button){await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),button.click()]);}
(async()=>{
 const browser=await chromium.launch({headless:true}),errors=[],credentials=[];let context,page,hasSecret=false;
 async function login(){context=await browser.newContext({viewport:{width:1360,height:980}});await context.route('**/*',r=>new URL(r.request().url()).origin===url?r.continue():r.abort());page=await context.newPage();page.on('pageerror',e=>errors.push(e.message));await page.goto(url+'/admin/authentication');await page.locator('[name=email]').fill('admin-ci@example.invalid');await page.locator('[name=password]').fill(process.env.ISSUERS_BROWSER_PASSWORD||'synthetic-browser-17');await go(page,page.locator('button[type=submit]'));eq(new URL(page.url()).pathname.includes('authentication'),false,'Native administrator login succeeds');}
 async function select(owner){await page.goto(url+'/admin/');await selectCompanyOption(page, String(owner));eq((await page.goto(url+'/admin/settings/api_tokens')).status(),200,'Native credential page responds200');}
 async function close(){if(context){await context.close();context=null;}}
 async function bearer(secret,owner,status){const client=await request.newContext();try{eq((await client.storageState()).cookies,[],'Each bearer context starts empty');const response=await client.get(url+'/company_operations_api/hr_employees',{headers:{Cookie:'',Authorization:'Bearer '+secret},maxRedirects:0});eq(response.status(),status,'Native-issued HR credential has expected current authorization');eq((await client.storageState()).cookies.filter(c=>c.name!==setup.csrf_cookie_name),[],'Bearer response creates no native session');if(status===200)eq((await response.json()).data.issuer_id,owner,'Bearer selects its own persisted company');}finally{await client.dispose();}}
 try{
  await login();
  for(const [label,owner] of Object.entries(setup.owners)){
   await select(owner);const form=page.locator('form:has(input[name=action][value=issue])');
   for(const [scope,text] of Object.entries(labels)){const box=form.locator('[name="scopes[]"][value="'+scope+'"]');eq(await box.count(),1,'One checkbox for '+scope);eq((await box.locator('xpath=..').innerText()).trim(),text,'Explicit human label for '+scope);await box.check();}
   const help=page.locator('#company-api-hr-help');eq(await help.count(),1,'HR help is present');const helpText=await help.innerText();for(const endpoint of ['hr_structure','hr_employees','hr_profiles','hr_records','hr_contracts','hr_courses','hr_assignments','hr_accesses','hr_files','hr_reports','hr_imports','hr_articles','hr_documents'])eq(helpText.includes(endpoint),true,'Help covers '+endpoint);
   if(label==='a'){await form.locator('[name="scopes[]"][value="hr:read"]').scrollIntoViewIfNeeded();await page.screenshot({path:path.join(artifacts,'hr-token-scopes.png'),fullPage:false});}
   await form.locator('[name=label]').fill('Synthetic native HR credentials '+label+' '+crypto.randomBytes(6).toString('hex'));await form.locator('[name=days]').fill('1');const original=await form.evaluate(f=>new URLSearchParams(new FormData(f)).toString());
   await go(page,form.locator('button[type=submit]'));eq(await page.locator('#api-created-secret').count(),1,'Issue reveals one new secret');hasSecret=true;const oldSecret=await page.locator('#api-created-secret').inputValue();eq(/^crmco_[a-f0-9]{32}_[a-f0-9]{64}$/.test(oldSecret),true,'Issued credential has canonical format without logging its value');const oldId=oldSecret.split('_')[1];
   eq(fixture('token',oldId),{issuer_id:owner,actor_id:17,active:1,scopes:Object.keys(labels).sort()},'GUI issue persists the exact16 selected scopes and identity');
   const retry=await context.request.post(url+'/admin/settings/api_tokens',{data:original,headers:{'Content-Type':'application/x-www-form-urlencoded'},maxRedirects:0});eq(retry.status(),200,'Issue replay succeeds idempotently');eq((await retry.text()).includes('id="api-created-secret"'),false,'Issue replay never re-exposes secret');
   const rotate=page.locator('form:has(input[name=action][value=rotate]):has(input[name=token_id][value="'+oldId+'"])');await rotate.locator('xpath=..').locator('summary').click();await go(page,rotate.locator('button[type=submit]'));eq(await page.locator('#api-created-secret').count(),1,'Native rotation displays replacement once');const secret=await page.locator('#api-created-secret').inputValue();eq(/^crmco_[a-f0-9]{32}_[a-f0-9]{64}$/.test(secret),true,'Replacement has canonical format without logging its value');eq(secret===oldSecret,false,'Rotation produces an independent credential');const id=secret.split('_')[1];eq(fixture('token',oldId).active,0,'Rotation revokes prior record');eq(fixture('token',id),{issuer_id:owner,actor_id:17,active:1,scopes:Object.keys(labels).sort()},'Rotation preserves company, actor and all16 scopes');credentials.push({owner,oldSecret,secret,id});
  }
  await close();proof.sessions_before_reads=fixture('sessions');for(const c of credentials){await bearer(c.oldSecret,c.owner,401);await bearer(c.secret,c.owner,200);}proof.sessions_after_reads=fixture('sessions');eq(proof.sessions_after_reads,proof.sessions_before_reads,'Rotated and current bearer reads preserve all session bytes');
  await login();
  for(const c of credentials){await select(c.owner);const revoke=page.locator('form:has(input[name=action][value=revoke]):has(input[name=token_id][value="'+c.id+'"])');eq(await revoke.count(),1,'Own current credential has a native revoke action');await go(page,revoke.locator('button[type=submit]'));eq(fixture('token',c.id).active,0,'Native revoke persists inactive credential');eq(await page.locator('form:has(input[name=action][value=revoke]):has(input[name=token_id][value="'+c.id+'"])').count(),0,'Revoked credential has no active revoke/rotate form');}
  await close();proof.sessions_before_revoked=fixture('sessions');for(const c of credentials)await bearer(c.secret,c.owner,401);proof.sessions_after_revoked=fixture('sessions');eq(proof.sessions_after_revoked,proof.sessions_before_revoked,'Revoked bearer requests preserve all session bytes');
  proof.preservation_after=fixture('preservation');eq(proof.preservation_after,proof.preservation_before,'Credential GUI and bearer reads preserve identities, finances, legacy HR and own HR data');proof.sources_after=fixture('sources');eq(proof.sources_after,proof.sources_before,'Loaded product and fixture bytes stayed unchanged');eq(errors,[],'No JavaScript error during native credential lifecycle');proof.result='PASS';proof.checks=checks;proof.scenarios=['All16 explicit HR scope labels and endpoint help in A/B/principal','Native issue and idempotent replay without secret redisclosure','Native rotation preserves exact16 scopes and invalidates prior bearer','Positive own-company bearer reads in fresh empty contexts','Native GUI revocation and subsequent401 with sessions unchanged','Identity, financial, legacy HR and own HR data preservation'];console.log(JSON.stringify({result:proof.result,checks,scenarios:proof.scenarios}));
 }catch(error){proof.result='FAIL';proof.checks=checks;proof.failure=error.message;if(page&&context&&!hasSecret)await page.screenshot({path:path.join(artifacts,'hr-token-failure-before-secret.png'),fullPage:false});throw error;}
 finally{fs.writeFileSync(path.join(artifacts,'hr-token-proof.json'),JSON.stringify(proof,null,2)+'\n');await close();await browser.close();}
})().catch(error=>{console.error(error.stack);process.exitCode=1;});
