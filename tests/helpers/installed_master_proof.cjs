'use strict';
const assert = require('node:assert/strict');
// Only the standalone upgraded fixture created by companies_onboarding_browser.
function assertAttestedMaster(sql) {
  const identity = JSON.parse(sql('SELECT JSON_ARRAY(master_staff_id,primary_issuer_id,source) FROM tblissuers_installation_identity WHERE id=1'));
  assert.deepEqual(identity, [17, 1, 'owner_attestation']);
  const audits = JSON.parse(sql("SELECT JSON_ARRAYAGG(JSON_ARRAY(actor_id,subject_id,issuer_id,metadata_json)) FROM tblissuers_audit WHERE event='original_admin_attested'"));
  assert.equal(audits?.length, 1, 'one idempotent owner confirmation audit');
  const [actor, subject, issuer, metadata] = audits[0];
  assert.deepEqual([actor, subject, issuer], [0, 17, null]);
  const meta = typeof metadata === 'string' ? JSON.parse(metadata) : metadata;
  assert.deepEqual(meta, {proof: 'installation_owner_attestation', confirmation_reference: 'test:explicit-owner-confirmation', primary_issuer_id: 1});
}
module.exports = {assertAttestedMaster};
