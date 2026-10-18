const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const {catalog} = require('../scripts/generate-company-email-catalog.cjs');
const root=path.resolve(__dirname,'..');
test('company defaults are reproducible from the installer, without database customization',()=>{
 const expected=catalog(fs.readFileSync(path.join(root,'install/database.sql'),'utf8'));
 const shipped=JSON.parse(fs.readFileSync(path.join(root,'application/services/email_templates_ptbr/company_defaults.json'),'utf8'));
 assert.deepEqual(shipped,expected);
 assert.equal(shipped.length,82);
 assert.equal(new Set(shipped.map(row=>row.slug)).size,82);
 for(const row of shipped){assert.equal(row.language,'english');assert.ok(!Object.hasOwn(row,'issuer_id'));assert.ok(!Object.hasOwn(row,'emailtemplateid'));assert.ok(!row.fromemail);}
});
