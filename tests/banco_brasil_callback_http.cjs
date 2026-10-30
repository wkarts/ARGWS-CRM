'use strict';
// Actual disposable CRM HTTP pipeline. This tests rejection before native TLS is available, never BB.
const assert=require('node:assert/strict');
const base=process.env.ISSUERS_BROWSER_URL;assert.match(base||'',/^http:\/\/127\.0\.0\.1:\d+$/,'a disposable loopback CRM URL is required');
(async()=>{
 let checks=0;
 async function call(path,options,status){const r=await fetch(base+path,{redirect:'manual',...options});assert.equal(r.status,status,path+' must preserve the machine boundary');checks++;const body=await r.text();assert.deepEqual(JSON.parse(body),{accepted:false});checks++;assert.ok(!r.headers.get('location'),'no staff/client login redirect');checks++;return r;}
 await call('/banco_brasil/notifications',{method:'GET'},405);
 await call('/banco_brasil/notifications',{method:'POST',headers:{'Content-Type':'application/json'},body:'{}'},401);
 await call('/banco_brasil/notifications/index',{method:'POST',headers:{'Content-Type':'application/json','SSL_CLIENT_VERIFY':'SUCCESS','SSL_CLIENT_CERT':'SYNTHETIC-FORGED-CERT','X-SSL-Client-Verify':'SUCCESS'},body:'{}'},401);
 await call('/banco_brasil/notifications',{method:'POST',headers:{'Content-Type':'application/json','SSL_CLIENT_VERIFY':'SUCCESS'},body:'x'.repeat(65537)},401);
 console.log('BB public callback actual HTTP: '+checks+' rejection/JSON/no-redirect checks passed; no BB TLS handshake or homologation.');
})().catch(error=>{console.error(error.message);process.exitCode=1;});
