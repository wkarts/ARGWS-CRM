const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const test=require('node:test');
const root=path.resolve(__dirname,'..');
const read=p=>fs.readFileSync(path.join(root,p),'utf8');
const labels=JSON.parse(read('application/services/email_templates_ptbr/interface.json'));
function files(dir){return fs.readdirSync(path.join(root,dir),{withFileTypes:true}).flatMap(e=>e.isDirectory()?files(`${dir}/${e.name}`):[`${dir}/${e.name}`]);}
test('all shipped literal merge-field labels have exact key/name translations',()=>{
 const sources=[...files('application/libraries/merge_fields'),...files('modules').filter(p=>/\/libraries\/.*merge_fields\.php$/.test(p))];
 let count=0;
 for(const file of sources){
  const source=read(file);
  for(const m of source.matchAll(/'name'\s*=>\s*'([^']*)',\s*'key'\s*=>\s*'([^']+)'/g)){
   assert.ok(labels.fields[m[2]]?.[m[1]],`${file}: ${m[1]} ${m[2]}`);count++;
  }
  const group=path.basename(file,'.php').toLowerCase().replace(/_merge_fields$/,'');
  if(group!=='app' && /class .* extends App_merge_fields/.test(source))assert.ok(labels.groups[group],file);
 }
 assert.ok(count>=264);
 for(const [key,name] of Object.entries({'{lead_public_form_url}':'Lead Public Form URL','{lead_public_consent_url}':'Lead Consent Link','{contact_public_consent_url}':'Contact Public Consent URL','{staff_phonenumber}':'Staff Phone'}))assert.ok(labels.fields[key][name]);
});
test('editor renders actual persisted language and localized descriptions without changing keys',()=>{
 const editor=read('application/views/admin/emails/template.php');
 const controller=read('application/controllers/admin/Emails.php');
 assert.doesNotMatch(editor,/>English<|This template contains|ucfirst\(\$availableLanguage/);
 assert.match(editor,/languageLabel\(\(string\) \$template->language\)/);
 assert.match(editor,/groupLabel\(\$key\)/);
 assert.match(editor,/fieldLabel\(\$_field\)/);
 assert.match(editor,/echo e\(\$_field\['key'\]\)/);
 assert.match(controller,/emailtemplateid.*!==.*selectedTemplate->emailtemplateid/);
 assert.ok(labels.languages.portuguese_br==='Português (Brasil)');
 assert.ok(labels.languages.english==='Inglês');
 for(const language of fs.readdirSync(path.join(root,'application/language'),{withFileTypes:true}).filter(e=>e.isDirectory()))assert.ok(labels.languages[language.name],language.name);
});
test('source dictionaries preserve every subject variable, including historical subjects',()=>{
 const sources=JSON.parse(read('application/services/email_templates_ptbr/legacy_sources.json'));
 for(const [slug,items] of Object.entries(sources))for(const d of items){
  assert.ok(d.type && d.source_file,slug);
  assert.deepEqual(d.source_subject.match(/\{[^{}]+\}/g)||[],d.subject.match(/\{[^{}]+\}/g)||[],slug);
 }
 assert.ok(sources.new_leave_application_send_to_notification_recipients);
 assert.ok(Object.values(sources).flat().some(s=>s.derived_by?.includes('111_version_111')));
});
