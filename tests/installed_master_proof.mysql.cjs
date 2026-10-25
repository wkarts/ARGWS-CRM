'use strict';
// Real MySQL -> mysql --batch -> helper pipeline; SELECT-only synthetic derived tables.
const assert = require('node:assert/strict');
const {execFileSync} = require('node:child_process');
const {assertAttestedMaster} = require('./helpers/installed_master_proof.cjs');
const container = process.env.ISSUERS_BROWSER_DB_CONTAINER;
const user = process.env.ISSUERS_BROWSER_DB_USER;
assert.match(container || '', /^argws-crm-provision-db-[0-9]+$/);
assert.match(user || '', /^[a-zA-Z0-9_]+$/);
assert.equal(process.env.ISSUERS_BROWSER_DB_NAME, 'argws_crm_ci');
assert(process.env.ISSUERS_BROWSER_DB_PASSWORD, 'disposable database credentials required');
const metadata = "CAST(JSON_OBJECT('proof','installation_owner_attestation','confirmation_reference','test:explicit-owner-confirmation','primary_issuer_id',1) AS CHAR CHARACTER SET utf8mb4)";
const identity = "(SELECT 1 AS id,17 AS master_staff_id,1 AS primary_issuer_id,'owner_attestation' AS source) AS fixture_identity";
const audit = `(SELECT 0 AS actor_id,17 AS subject_id,NULL AS issuer_id,${metadata} AS metadata_json,'original_admin_attested' AS event) AS fixture_audit`;
function execute(query) {
  assert.match(query, /^SELECT /);
  assert(!/[;]|\b(?:INSERT|UPDATE|DELETE|CREATE|DROP|ALTER|LOAD|INTO)\b/i.test(query), 'read-only SELECT only');
  return execFileSync('docker', ['exec','-e','MYSQL_PWD',container,'mysql','--protocol=tcp','--host=127.0.0.1','--user='+user,'argws_crm_ci','--batch','--skip-column-names','-e',query], {
    env:{...process.env,MYSQL_PWD:process.env.ISSUERS_BROWSER_DB_PASSWORD},encoding:'utf8',timeout:15000,maxBuffer:1024*1024
  }).trim();
}
function adapter(identityRows=identity, auditRows=audit) {
  return query => {
    const identityFrom='FROM tblissuers_installation_identity';
    const auditFrom='FROM tblissuers_audit';
    assert(query.includes(identityFrom) !== query.includes(auditFrom), 'one expected fixture source');
    return execute(query.replace(identityFrom,'FROM '+identityRows).replace(auditFrom,'FROM '+auditRows));
  };
}
// CAST forces LONGTEXT-like nested JSON strings, matching metadata_json, not JSON objects.
const raw=execute(`SELECT JSON_ARRAYAGG(JSON_ARRAY(actor_id,subject_id,issuer_id,metadata_json)) FROM ${audit} WHERE event='original_admin_attested'`);
assert.throws(()=>JSON.parse(raw), SyntaxError, 'prove original batch escaping regression on actual CLI');
assertAttestedMaster(adapter());
const objectAudit = audit.replace(metadata, metadata.replace('CAST(', '').replace(' AS CHAR CHARACTER SET utf8mb4)', ''));
assertAttestedMaster(adapter(identity, objectAudit)); // Native JSON object as well as LONGTEXT string.
const proofRejection = error => error?.code === 'ERR_ASSERTION'; // SQL/process failures must fail the preflight, never count as negative proof.
assert.throws(()=>assertAttestedMaster(adapter(identity.replace('17 AS master_staff_id','42 AS master_staff_id'))), proofRejection);
assert.throws(()=>assertAttestedMaster(adapter(identity,audit.replace('0 AS actor_id','17 AS actor_id'))), proofRejection);
assert.throws(()=>assertAttestedMaster(adapter(identity,audit.replace('test:explicit-owner-confirmation','test:wrong-confirmation'))), proofRejection);
assert.throws(()=>assertAttestedMaster(adapter(identity,audit.replace("'original_admin_attested' AS event","'other' AS event"))), proofRejection);
console.log('Installed master proof: actual MySQL batch escaping reproduced; HEX transport and four negative proofs passed (SELECT-only).');
