'use strict';
// Real finite HTTP requests with an empty cookie jar; the native bearer is the only authority.
const assert=require('node:assert/strict'),{execFileSync}=require('node:child_process'),{request}=require('playwright'),fs=require('node:fs'),path=require('node:path'),crypto=require('node:crypto');
const url=process.env.ISSUERS_BROWSER_URL,app=process.env.ISSUERS_BROWSER_APP_CONTAINER,database=process.env.ISSUERS_BROWSER_DB_NAME,artifacts=process.env.ISSUERS_BROWSER_ARTIFACTS;
assert.match(url||'',/^http:\/\/127\.0\.0\.1:\d+$/);assert.match(app||'',/^argws-crm-(upgrade|fresh)-\d+$/);assert(['argws_upgrade_ci','argws_fresh_ci'].includes(database));assert(artifacts&&path.isAbsolute(artifacts));fs.mkdirSync(artifacts,{recursive:true});
const key=()=>crypto.randomBytes(32).toString('hex');let checks=0;
function eq(actual,expected,description){assert.deepEqual(actual,expected,description);checks++;}
function fixture(...args){return JSON.parse(execFileSync('docker',['exec','-e','ISSUERS_BROWSER_DB_NAME='+database,app,'php','tests/companies/leads_http_fixture.php',...args.map(String)],{encoding:'utf8'}));}
(async()=>{
  for(const file of ['tests/support/BbPdoDatabase.php','tests/companies/leads_http_fixture.php'])execFileSync('docker',['cp',path.join(process.env.ISSUERS_BROWSER_SOURCE_ROOT||process.cwd(),file),app+':/app/'+file]);
  const setup=fixture('prepare'),before=fixture('financial'),sessions=fixture('sessions'),config=fixture('configuration',setup.owners.a),token=fixture('issue',setup.owners.a,'all'),foreign=fixture('issue',setup.owners.b,'all');
  const proof={result:'RUNNING',checks:0,companies:setup.owners,authority:'native bearer only; fresh empty cookie jar for every request',csrf_response_cookie:'native CodeIgniter GET/HEAD behavior; never sent back',sessions_before:sessions};
  async function call(method,endpoint,status,credential=token,data){
    const client=await request.newContext({ignoreHTTPSErrors:false});
    try{
      eq((await client.storageState()).cookies,[],'Every request begins without cookies');
      const headers={Cookie:'',...(credential?{Authorization:'Bearer '+credential.secret}:{})};if(method==='POST')headers['Idempotency-Key']=key();
      const response=await client.fetch(url+'/company_operations_api/'+endpoint,{method,headers,...(data===undefined?{}:{data}),maxRedirects:0});
      eq(response.status(),status,method+' '+endpoint+' status');eq((await client.storageState()).cookies.map(c=>c.name).filter(name=>name!==setup.csrf_cookie_name),[],'Only the native CSRF response cookie is allowed; no session cookie');
      const bytes=await response.body(),responseHeaders=response.headers();return {headers:()=>responseHeaders,body:async()=>bytes,json:async()=>JSON.parse(bytes.toString('utf8'))};
    }finally{await client.dispose();}
  }
  try{
    const denied=await call('GET','leads',401,null);eq(denied.headers()['www-authenticate'],'Bearer','Explicit bearer challenge');
    for(const endpoint of ['leads','lead_stages','lead_sources','lead_assignees','lead_inbox','lead_exports'])await call('GET',endpoint,200);
    const head=await call('HEAD','lead_assignees',200);eq(await head.body(),Buffer.alloc(0),'HEAD emits no body');
    const method=await call('POST','lead_assignees',405,null,{});eq(method.headers().allow,'GET, HEAD','Readonly collection exact Allow');
    await call('GET','leads?issuer_id='+setup.owners.b,422);
    const created=await call('POST','leads',200,token,{action:'create',input:{name:'Cookieless HTTP '+key().slice(0,8),company:'Bearer owned prospect',email:'',phone:'',vat:'',description:'No session authority',stage_id:Number(config.stages.find(s=>Number(s.active)===1&&s.category==='open').id),source_id:Number(config.sources.find(s=>Number(s.active)===1).id),assignee_id:0,notify_assignee:0}});
    const id=(await created.json()).data.result;assert(Number.isInteger(id)&&id>0);checks++;
    await call('GET','leads/'+id,200);await call('GET','leads/'+id,403,foreign);
    const pdf=Buffer.from('%PDF-1.4\nCookieless private fixture\n%%EOF\n'),uploaded=await call('POST','lead_files/'+id,200,token,{action:'upload',revision:1,input:{filename:'cookieless.pdf',content_base64:pdf.toString('base64')}}),file=(await uploaded.json()).data.result;
    const blob=await call('GET','lead_files/'+id+'/'+file,200);eq(await blob.body(),pdf,'Canonical private bytes');await call('GET','lead_files/'+id+'/'+file,403,foreign);
    fixture('revoke',setup.owners.a,token.token_id);await call('GET','leads/'+id,401);fixture('revoke',setup.owners.b,foreign.token_id);
    eq(fixture('financial'),before,'Legacy leads and financial rows remain byte-exact');proof.sessions_after=fixture('sessions');eq(proof.sessions_after,sessions,'Session table remains byte-exact');proof.result='PASS';proof.checks=checks;proof.created_lead_id=id;
    console.log(JSON.stringify(proof));
  }catch(error){proof.result='FAIL';proof.checks=checks;proof.failure=error.message;throw error;}
  finally{fs.writeFileSync(path.join(artifacts,'leads-bearer-proof.json'),JSON.stringify(proof,null,2)+'\n');}
})().catch(error=>{console.error(error.stack);process.exitCode=1;});
