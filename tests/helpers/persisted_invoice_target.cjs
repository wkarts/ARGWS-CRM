'use strict';
const assert=require('node:assert/strict');
const {execFileSync}=require('node:child_process');

function query(sql, env=process.env, run=execFileSync) {
  const container=env.ISSUERS_BROWSER_DB_CONTAINER;
  if(!container)return null;
  assert.equal(env.ISSUERS_BROWSER_DB_NAME,'argws_upgrade_ci','only disposable upgrade fixtures are allowed');
  assert.match(container,/^argws-crm-provision-db-\d+$/);
  assert(env.ISSUERS_BROWSER_DB_USER&&env.ISSUERS_BROWSER_DB_PASSWORD,'fixture database credentials required');
  return run('docker',['exec','-e','MYSQL_PWD',container,'mysql','--protocol=tcp','--host=127.0.0.1',
    '--user='+env.ISSUERS_BROWSER_DB_USER,env.ISSUERS_BROWSER_DB_NAME,'--batch','--skip-column-names','-e',sql],
    {env:{...env,MYSQL_PWD:env.ISSUERS_BROWSER_DB_PASSWORD},encoding:'utf8',timeout:10000,stdio:['ignore','pipe','pipe']}).trim();
}
function invoiceCheckpoint(env=process.env,run=execFileSync) {
  const value=query('SELECT COALESCE(MAX(id),0) FROM tblinvoices',env,run);
  if(value===null)return null;
  assert.match(value,/^(0|[1-9][0-9]*)$/,'invoice checkpoint must be readable');
  return value;
}
function persistedInvoiceTarget(checkpoint,body,url,env=process.env,run=execFileSync) {
  assert.match(String(checkpoint),/^(0|[1-9][0-9]*)$/);
  const fields=new URLSearchParams(body);
  const client=fields.get('clientid'),number=(fields.get('number')||'').replace(/^0+/,'');
  assert.match(client||'',/^[1-9][0-9]*$/);
  assert.match(number,/^[1-9][0-9]*$/);
  // No global/latest-row fallback: exactly one new row for this original command.
  const rows=query('SELECT id FROM tblinvoices WHERE id>'+checkpoint+' AND issuer_id IS NULL AND clientid='+client+' AND number='+number,env,run);
  assert.match(rows||'',/^[1-9][0-9]*$/,'legacy POST must persist exactly one NEW invoice for its submitted client and number; no row or ambiguous rows is a failure');
  return new URL('/admin/invoices/list_invoices/'+rows,url).href;
}
module.exports={invoiceCheckpoint,persistedInvoiceTarget};
