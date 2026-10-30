'use strict';
// Exact452→453 HTTP upgrade. The original native browser suite performs the POST.
const assert = require('node:assert/strict');
const {execFileSync} = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
assert.match(process.env.ISSUERS_BROWSER_URL || '', /^http:\/\/127\.0\.0\.1:\d+$/);
assert.equal(process.env.ISSUERS_BROWSER_DB_NAME,'argws_legacy_ci', 'Verified backup452 and isolated upgrade clone required');
const app = process.env.ISSUERS_BROWSER_APP_CONTAINER;
assert.match(app || '', /^argws-crm-upgrade-\d+$/);
const output = process.env.ISSUERS_BROWSER_ARTIFACTS;
assert(output && path.isAbsolute(output), 'Explicit artifact directory required');
fs.mkdirSync(output, {recursive:true});
function snapshot() {
  return JSON.parse(execFileSync('docker', ['exec', '-e', 'ISSUERS_BROWSER_DB_NAME='+process.env.ISSUERS_BROWSER_DB_NAME, app, 'php', 'tests/companies/native_core_453_http_snapshot.php'], {encoding:'utf8'}));
}
const verifyCompleted = process.argv[2] === '--verify-completed';
const before = verifyCompleted ? JSON.parse(fs.readFileSync(process.argv[3], 'utf8')) : snapshot();
assert.equal(before.version, 452, 'Previous native452 database, never a reset final schema');
assert.equal(before.memory_limit, '128M');
const added = ['company_staff_commands','company_task_schedules','company_task_schedule_runs','company_task_schedule_requests','company_task_schedule_cursor'].map(x=>'tbl'+x).sort();
for (const table of added) { assert.equal(before.tables[table], undefined); }
fs.writeFileSync(path.join(output, 'before.json'), JSON.stringify(before, null, 2)+'\n');
if (!verifyCompleted) { execFileSync(process.execPath, ['tests/issuers_browser_smoke.cjs'], {stdio:'inherit', env:{...process.env, ISSUERS_BROWSER_MODE:'upgrade'}}); }
const after = snapshot();
assert.equal(after.version, 453);
assert.deepEqual(after.identity, before.identity, 'Master17/principal1 identity preserved without new authorization');
assert.equal(before.tables.tblactivity_log.row_count, 0, 'This preserved baseline has no historical activity events to exclude');
assert.equal(after.native_login_events, 1, 'Only the native login added one qualified event');
assert.equal(after.upgraded_from_version, '452');
assert.equal(after.memory_limit, '128M');
assert.deepEqual(Object.keys(after.tables).sort(), [...Object.keys(before.tables), ...added].sort());
const comparison=JSON.parse(execFileSync('docker',['exec','-e','ISSUERS_BROWSER_DB_NAME='+process.env.ISSUERS_BROWSER_DB_NAME,app,'php','tests/companies/native_core_453_http_compare.php'],{encoding:'utf8'}));
assert.equal(comparison.result,'POSTCONDITIONS_PASS');
fs.writeFileSync(path.join(output,'comparison.json'),JSON.stringify(comparison,null,2)+'\n');
for (const table of added) { assert.equal(after.tables[table].row_count, table.endsWith('_cursor')?1:0, 'No data or permission backfill: '+table); }
assert.deepEqual(after.cursor.map(r=>Object.fromEntries(Object.entries(r).map(([k,v])=>[k,Number(v)]))), [{id:1,last_id:0,upper_id:0,revision:1}]);
fs.writeFileSync(path.join(output, 'after.json'), JSON.stringify(after, null, 2)+'\n');
const proof = {native_upgrade:'452→453', existing_master:17, existing_primary:1, historical_tables:Object.keys(before.tables).length, new_tables:added, qualified_changes:comparison.qualified_changes, memory_limit:after.memory_limit, native_browser_suite:'tests/issuers_browser_smoke.cjs', native_browser_execution:verifyCompleted?'separately recorded before postcondition verification':'executed in this invocation', result:verifyCompleted?'POSTCONDITIONS_PASS':'PASS'};
fs.writeFileSync(path.join(output, 'proof.json'), JSON.stringify(proof, null, 2)+'\n');
console.log(JSON.stringify(proof));
