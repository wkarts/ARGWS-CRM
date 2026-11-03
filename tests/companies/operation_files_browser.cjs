'use strict';
const { selectCompanyOption } = require('../helpers/native_selector_driver.cjs');
const { workspacePage } = require('../helpers/native_workspace_driver.cjs');
const ticketDriver = require('../helpers/native_ticket_driver.cjs');
// Actual multipart/UI/HTTP/MySQL; disposable fixture only, with every request kept on localhost.
const assert=require('node:assert/strict'),{execFileSync}=require('node:child_process'),{chromium}=require('playwright');
const url=process.env.ISSUERS_BROWSER_URL,db=process.env.ISSUERS_BROWSER_DB_CONTAINER;assert.match(url||'',/^http:\/\/127\.0\.0\.1:\d+$/);assert.match(db||'',/^argws-crm-provision-db-\d+$/);assert.equal(process.env.ISSUERS_BROWSER_DB_NAME,'argws_upgrade_ci');
function sql(q){return execFileSync('docker',['exec','-e','MYSQL_PWD',db,'mysql','--protocol=tcp','--host=127.0.0.1','--user='+process.env.ISSUERS_BROWSER_DB_USER,'argws_upgrade_ci','--batch','--skip-column-names','-e',q],{env:{...process.env,MYSQL_PWD:process.env.ISSUERS_BROWSER_DB_PASSWORD},encoding:'utf8'}).trim();}
async function go(p,b){await Promise.all([p.waitForNavigation({waitUntil:'domcontentloaded'}),b.click()]);}async function hidden(form){return form.locator('input[type=hidden]').evaluateAll(xs=>Object.fromEntries(xs.filter(x=>x.name).map(x=>[x.name,x.value])));}
function contractRef(id){return String(5n*4294967296n+BigInt(id));}
function ticketRef(id){return String(4n*4294967296n+BigInt(id));}
function detailRoute(kind,id){return kind==='contract'?'/admin/contracts/contract/'+contractRef(id):'/admin/tickets/ticket/'+ticketRef(id);}
function fileRoute(kind,id,key,preview=false){return kind==='contract'?'/admin/contracts/file/'+contractRef(id)+'/'+key+(preview?'?preview=1':''):'/download/file/ticket/'+key+'?ticket_id='+ticketRef(id)+(preview?'&preview=1':'');}
(async()=>{const browser=await chromium.launch({headless:true}),ctx=await browser.newContext();await ctx.route('**/*',r=>new URL(r.request().url()).origin===url?r.continue():r.abort());const page=await workspacePage(ctx),errors=[],proofs={},parents={};page.on('pageerror',e=>errors.push(e.message));try{
 await page.goto(url+'/admin/authentication');await page.locator('input[name=email]').fill('admin-ci@example.invalid');await page.locator('input[name=password]').fill(process.env.ISSUERS_BROWSER_PASSWORD);await go(page,page.locator('button[type=submit]'));
 const a=sql("SELECT id FROM tblissuers_registry WHERE tax_id='11222333000181'"),b=sql("SELECT id FROM tblissuers_registry WHERE tax_id='52998224725'"),actor=sql('SELECT master_staff_id FROM tblissuers_installation_identity WHERE id=1');let old;
 const pdf=Buffer.from('%PDF-1.4\nPrivate operational browser proof\n%%EOF\n'),png=Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAMAAAACCAIAAAASFvFNAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAC0lEQVQImWNgwAQAABQAAWX1h1kAAAAASUVORK5CYII=','base64');
 async function select(id){await page.goto(url+'/admin/');await selectCompanyOption(page, id);}
 for(const[issuer,letter]of[[a,'a'],[b,'b']]){await select(issuer);const client=sql("SELECT userid FROM tblclients WHERE company='Synthetic portal client "+letter+"'");proofs[issuer]={};parents[issuer]={};
  for(const kind of['contract','ticket']){const route=kind==='contract'?'/admin/contracts/contract?source=company':'/admin/tickets';await page.goto(url+route);
   if(kind==='contract'){await page.locator('#contract-client').selectOption(client);await page.locator('#contract-new-name').fill('Operational private files '+letter);await page.locator('#contract-new-body').fill('Synthetic internal private contract');await page.locator('#contract-new-start').fill('2026-10-19');await page.locator('#contract-new-due').fill('2026-11-19');await go(page,page.getByRole('button',{name:'Criar rascunho de contrato',exact:true}));await go(page,page.getByRole('button',{name:'Ativar contrato e preservar texto',exact:true}));}
   else{await ticketDriver.create(page,url,sql,{issuer,client,subject:'Operational private support '+letter,body:'Synthetic internal proof'});}
   const routedId=new URL(page.url()).pathname.split('/').filter(Boolean).at(-1);assert.match(routedId||'',/^[1-9]\d*$/);const id=String(BigInt(routedId)%4294967296n);parents[issuer][kind]=id;const snapshot=kind==='contract'?sql('SELECT snapshot_json FROM tblcompany_contracts WHERE id='+id):null;
   for(const [name,mimeType,buffer]of[['proof.pdf','application/pdf',pdf],['proof.png','image/png',png]]){const form=page.locator('form').filter({has:page.locator('#operation-file')});assert.equal(await form.getAttribute('enctype'),'multipart/form-data');await page.locator('#operation-file').setInputFiles({name,mimeType,buffer});if(letter==='a')old=await hidden(form);await go(page,form.locator('button[type=submit]').filter({hasText:'Anexar arquivo privado'}));}
   const key=sql("SELECT file_key FROM tblcompany_"+kind+"_files WHERE "+kind+"_id="+id+" AND filename='proof.pdf'");proofs[issuer][kind]=key;assert.match(key,/^[a-f0-9]{64}$/);const download=await ctx.request.get(url+fileRoute(kind,id,key));assert.equal(download.status(),200);assert.deepEqual(await download.body(),pdf);assert.match(download.headers()['content-security-policy'],/sandbox/);assert.match(download.headers()['content-disposition'],/^attachment;/);
   const image=sql("SELECT file_key FROM tblcompany_"+kind+"_files WHERE "+kind+"_id="+id+" AND filename='proof.png'");const preview=await ctx.request.get(url+fileRoute(kind,id,image,true));assert.equal(preview.status(),200);assert.match(preview.headers()['content-disposition'],/^inline;/);assert.equal((await preview.body()).subarray(0,4).toString('hex'),'89504e47');if(snapshot!==null)assert.equal(sql('SELECT snapshot_json FROM tblcompany_contracts WHERE id='+id),snapshot,'upload leaves issued contract snapshot unchanged');
  }
 }
 for(const kind of['contract','ticket']){
  const foreignParent=await ctx.request.get(url+fileRoute(kind,parents[a][kind],proofs[a][kind]));
  assert.equal(foreignParent.status(),kind==='ticket'?404:403,'canonical download denies a foreign parent before bytes');
  const foreignChild=await ctx.request.get(url+fileRoute(kind,parents[b][kind],proofs[a][kind]));
  assert.equal(foreignChild.status(),kind==='ticket'?404:403,'persisted file parent is authoritative');
  await page.goto(url+detailRoute(kind,parents[b][kind]));const row=page.locator('li').filter({has:page.getByRole('link',{name:'proof.pdf',exact:true})});if(kind==='ticket')await ticketDriver.submit(page,row.locator('form'));else await go(page,row.getByRole('button',{name:'Ocultar anexo e preservar histórico',exact:true}));
  const hiddenFile=await ctx.request.get(url+fileRoute(kind,parents[b][kind],proofs[b][kind]));assert.equal(hiddenFile.status(),kind==='ticket'?404:403,'hidden bytes are unavailable through the canonical download');assert.equal(sql("SELECT active FROM tblcompany_"+kind+"_files WHERE file_key='"+proofs[b][kind]+"'"),'0');
 }
 assert.equal((await ctx.request.post(url+detailRoute('ticket',parents[a].ticket),{form:old})).status(),409,'old company context cannot mutate another company files');assert.deepEqual(errors,[]);console.log('Private contract/support A/B actual multipart/PDF/PNG/download/preview/hide/stale/cross-owner/snapshot passed. No external transports.');
}finally{await browser.close();}})().catch(e=>{console.error(e.message);process.exitCode=1;});
