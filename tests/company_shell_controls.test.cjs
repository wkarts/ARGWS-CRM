const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname,'../assets/js/company-shell.js'),'utf8');
function button(text, classes='', disabled=false) {
 const node={textContent:text,className:classes,disabled,type:'submit',name:'action',value:'save',form:{csrf:'unchanged'},dataset:{},closest:()=>null};
 node.classList={add:(...values)=>{node.className=[node.className,...values].filter(Boolean).join(' ');}};
 return node;
}
function run(buttons, fields=[], tables=[]) { vm.runInNewContext(source,{document:{createElement:tag=>{assert.equal(tag,'div');return {className:'',appendChild:node=>{node.wrapperAdded=true;}};},querySelectorAll:selector=>{if(selector==='.company-settings-card button')return buttons;if(selector==='.company-settings-card table')return tables;assert.equal(selector,'.company-settings-card input, .company-settings-card select, .company-settings-card textarea');return fields;},querySelector:()=>null}}); }
test('every unstyled company card gets native control classes without touching payload or disabled state',()=>{
 const save=button('Salvar credenciais próprias','',true),form=save.form;run([save]);
 assert.equal(save.className,'btn btn-primary');assert.equal(save.disabled,true);assert.equal(save.form,form);assert.deepEqual(form,{csrf:'unchanged'});assert.equal(save.name,'action');assert.equal(save.value,'save');assert.equal(save.type,'submit');
});
test('destructive labels and explicit styling are preserved independently of workspace tabs',()=>{
 const revoke=button('Revogar conexão local'),explicit=button('Salvar','btn btn-info'),custom=button('Excluir','custom-delete');run([revoke,explicit,custom]);
 assert.equal(revoke.className,'btn btn-danger');assert.equal(explicit.className,'btn btn-info');assert.equal(custom.className,'custom-delete');
});
test('enhancement is idempotent and has no global requests or form submission',()=>{
 const save=button('Salvar');run([save]);run([save]);assert.equal(save.className,'btn btn-primary');
 assert.doesNotMatch(source,/\b(fetch|XMLHttpRequest|submit|requestSubmit)\s*\(/);
});

test('unstyled text fields receive native form-control without touching hidden CSRF or checkbox controls',()=>{
 const text=button(''),secret=button(''),checkbox=button('');text.tagName=secret.tagName=checkbox.tagName='INPUT';text.type='text';secret.type='hidden';checkbox.type='checkbox';
 run([], [text,secret,checkbox]);assert.equal(text.className,'form-control');assert.equal(secret.className,'');assert.equal(checkbox.className,'');assert.equal(secret.value,'save');assert.equal(secret.type,'hidden');
});

test('unstyled table receives native responsive wrapper without cloning rows or changing surrounding form',()=>{
 const table=button(''),row={id:'unchanged-row'},form=table.form;table.rows=[row];table.id='payments';table.parentNode={insertBefore:(wrapper,reference)=>{assert.equal(reference,table);assert.equal(wrapper.className,'table-responsive');table.sameParent=true;}};
 run([],[],[table]);assert.equal(table.className,'table table-hover');assert.equal(table.wrapperAdded,true);assert.equal(table.sameParent,true);assert.equal(table.rows[0],row);assert.equal(table.form,form);assert.equal(table.id,'payments');
 run([],[],[table]);assert.equal(table.className,'table table-hover');
 const explicit=button('','custom-table');run([],[],[explicit]);assert.equal(explicit.wrapperAdded,undefined);
});

test('shared sidebar uses complete icon families from native menu definitions, never forced solid',()=>{
 const head=fs.readFileSync(path.join(__dirname,'../application/views/admin/includes/native_company_layout_head.php'),'utf8');
 const menu=fs.readFileSync(path.join(__dirname,'../application/helpers/menu_helper.php'),'utf8');
 const catalog=fs.readFileSync(path.join(__dirname,'../application/services/NativeAdminMenuCatalog.php'),'utf8');
 for(const [label,slug] of Object.entries({Clientes:'customers',Vendas:'sales',Despesas:'expenses',Contratos:'contracts',Projetos:'projects',Tarefas:'tasks',Suporte:'support',Leads:'leads','Relatórios':'reports'})){
  const block=menu.slice(menu.indexOf("add_sidebar_menu_item('"+slug+"'"));
  const descriptor=block.match(/^add_sidebar_menu_item\('[^']+', (?:array_replace\()?NativeAdminMenuCatalog::legacy\('([^']+)'\)/);
  const source=descriptor ? catalog.split("'"+descriptor[1]+"' => [")[1].split('],')[0] : block;
  const native=source.match(/'icon'\s*=>\s*'([^']+)'/)[1];
  const isolated=head.match(new RegExp("'"+label+"' => \\[\\'([^']+)"))[1];
  assert.equal(isolated,native,label);
 }
 assert.doesNotMatch(head,/class="fa-solid <\?= \$companyLayoutEscape\(\$icon\)/);
 assert.match(head,/fa-regular fa-object-group menu-icon/);
});

test('isolated shell follows native document scrolling and sidebar placement instead of fixed scrollports',()=>{
 const css=fs.readFileSync(path.join(__dirname,'../assets/css/company-shell.css'),'utf8');
 assert.match(css,/#header \{[^}]*position: relative/);
 assert.match(css,/#menu \{[^}]*position: absolute; top: 0;[^}]*overflow: visible/);
 assert.doesNotMatch(css,/position: fixed|overflow-y: auto/);
 assert.match(css,/#wrapper \{ margin: 0 0 0 230px/);
});

test('mobile scrim dismissal consumes only the open mobile gesture and restores focus',()=>{
 const listeners={},classes=new Set();let focused=0,prevented=0,stopped=0,expanded;
 const narrow={matches:true};
 const toggle={setAttribute:(name,value)=>{expanded=value;},addEventListener:(name,fn)=>{listeners.toggle=fn;},focus:()=>{focused++;}};
 const wrapper={addEventListener:(name,fn,capture)=>{assert.equal(capture,true);listeners.wrapper=fn;}};
 const body={classList:{toggle:(name,on)=>on?classes.add(name):classes.delete(name),contains:name=>classes.has(name)}};
 vm.runInNewContext(source,{window:{matchMedia:()=>narrow},document:{body,querySelectorAll:()=>[],querySelector:selector=>selector==='.company-menu-toggle'?toggle:selector==='#wrapper'?wrapper:null,addEventListener:()=>{}}});
 const event={preventDefault:()=>{prevented++;},stopImmediatePropagation:()=>{stopped++;}};
 listeners.wrapper(event);assert.equal(prevented,0,'closed content remains interactive');
 listeners.toggle();assert.equal(expanded,'true');listeners.wrapper(event);
 assert.equal(expanded,'false');assert.equal(prevented,1);assert.equal(stopped,1);assert.equal(focused,1);
 narrow.matches=false;listeners.toggle();listeners.wrapper(event);assert.equal(prevented,1,'desktop content remains interactive');
 const css=fs.readFileSync(path.join(__dirname,'../assets/css/company-shell.css'),'utf8');
 assert.match(css,/#wrapper \{[^}]*min-height: 100%/);
 assert.match(css,/#wrapper::after \{[^}]*top: -42px;[^}]*height: calc\(100% \+ 42px\)/);
});

test('native comparison fixture retains real document direction, encoding and viewport contract',()=>{
 const fixture=fs.readFileSync(path.join(__dirname,'company_shell_fidelity.browser.cjs'),'utf8');
 const nativeHead=fs.readFileSync(path.join(__dirname,'../application/views/admin/includes/head.php'),'utf8');
 assert.match(nativeHead,/dir=/);
 assert.match(nativeHead,/charset=utf-8/i);
 assert.match(fixture,/<html lang="pt-BR" dir="ltr"><head><meta charset="utf-8"><meta name="viewport"/);
 assert.match(fixture,/assertHeaderContents\(page\)/);
});
