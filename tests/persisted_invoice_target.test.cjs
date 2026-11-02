'use strict';
const {test}=require('node:test'),assert=require('node:assert/strict');
const {invoiceCheckpoint,persistedInvoiceTarget}=require('./helpers/persisted_invoice_target.cjs');
const env={ISSUERS_BROWSER_DB_CONTAINER:'argws-crm-provision-db-42',ISSUERS_BROWSER_DB_NAME:'argws_upgrade_ci',ISSUERS_BROWSER_DB_USER:'fixture',ISSUERS_BROWSER_DB_PASSWORD:'synthetic'};
const url='http://127.0.0.1:8080';
test('checkpoint precedes exact new legacy row recovery with bounded read-only SQL',()=>{
 const calls=[];const run=(cmd,args,opts)=>{calls.push(args.at(-1));assert.equal(cmd,'docker');assert.equal(opts.timeout,10000);assert.equal(opts.env.MYSQL_PWD,'synthetic');assert(!args.includes('synthetic'));return calls.length===1?'42\n':'43\n';};
 const checkpoint=invoiceCheckpoint(env,run);
 assert.equal(persistedInvoiceTarget(checkpoint,'clientid=17&number=000001',url,env,run),url+'/admin/invoices/list_invoices/43');
 assert.deepEqual(calls,['SELECT COALESCE(MAX(id),0) FROM tblinvoices','SELECT id FROM tblinvoices WHERE id>42 AND issuer_id IS NULL AND clientid=17 AND number=1']);
});
test('missing and ambiguous persisted rows fail immediately without latest/global fallbacks',()=>{
 for(const rows of ['','43\n44','NULL'])assert.throws(()=>persistedInvoiceTarget('42','clientid=17&number=1',url,env,()=>rows),/exactly one NEW invoice/);
});
test('unsafe identifiers fail before any database command',()=>{
 for(const body of ['clientid=1 OR 1=1&number=1','clientid=17&number=1;DROP','number=1'])assert.throws(()=>persistedInvoiceTarget('42',body,url,env,()=>assert.fail('SQL executed')));
 assert.throws(()=>invoiceCheckpoint({...env,ISSUERS_BROWSER_DB_NAME:'production'},()=>assert.fail()),/disposable/);
});
test('database command failures are surfaced and never converted into long browser retries',()=>{
 assert.throws(()=>invoiceCheckpoint(env,()=>{throw new Error('fixture unavailable');}),/fixture unavailable/);
 assert.equal(invoiceCheckpoint({}),null);
});
