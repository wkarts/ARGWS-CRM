const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const test = require('node:test');
const root = path.resolve(__dirname, '..');
const readJson = name => JSON.parse(fs.readFileSync(path.join(root, 'application/services/email_templates_ptbr', name), 'utf8'));
const walk = dir => fs.readdirSync(dir, {withFileTypes:true}).flatMap(e => e.isDirectory() ? walk(path.join(dir,e.name)) : [path.join(dir,e.name)]);
test('all literal mail class slugs have shipped localized definitions', () => {
 const core=readJson('templates.json'), modules=readJson('templates_modules.json');
 const slugs=new Set();
 for(const file of [...walk(path.join(root,'application/libraries/mails')), ...walk(path.join(root,'modules'))].filter(f=>f.endsWith('.php'))) {
  const text=fs.readFileSync(file,'utf8');
  for(const match of text.matchAll(/(?:public|protected)\s+\$slug\s*=\s*['"]([^'"]+)/g)) slugs.add(match[1]);
 }
 assert.equal(slugs.size,101);
 assert.deepEqual([...slugs].sort(),Object.keys({...core,...modules}).sort());
 for(const row of readJson('company_defaults.json')) assert.ok(row.subject.trim() && row.message.trim(),row.slug);
 for(const [slug,row] of Object.entries(modules)) assert.ok(row.subject.trim() && row.source_subject.trim() && row.source_message.trim(),slug);
});
test('webhook new body is translated without raw diagnostics; historic blank remains recognized',()=>{
 const row=readJson('templates_modules.json')['webhook-failed'];
 assert.ok(row.source_message.includes('Review the webhook logs'));
 assert.ok(!row.source_message.includes('{webhook_response_code}'));
 const phrase=row.source_message.replace(/<[^>]*>/g,'');
 assert.ok(readJson('phrases_modules.json')[phrase]);
 assert.ok(readJson('legacy_sources.json')['webhook-failed'].some(r=>r.source_message===''));
});
