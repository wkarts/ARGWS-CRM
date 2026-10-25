'use strict';
const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),crypto=require('node:crypto'),vm=require('node:vm');
const read=p=>fs.readFileSync(path.join(__dirname,'../..',p),'utf8');
test('native table, filter, kanban and DOM helpers have a single exact extracted implementation',()=>{
 const shared=read('assets/js/native-admin-tables.js'),main=read('assets/js/main.js'),manifest=JSON.parse(read('tests/native_company/tables-extraction.json'));
 for(const entry of manifest.entries){assert.equal(crypto.createHash('sha256').update(entry.source).digest('hex'),entry.sha256);assert(shared.includes(entry.source),entry.name);if(!entry.name.startsWith('plugin-'))assert.doesNotMatch(main,new RegExp(`function\\s+${entry.name}\\s*\\(`),entry.name);}
 assert.equal(manifest.entries.length,13);new vm.Script(shared);assert.doesNotMatch(shared,/get_option|NativeCompanyHeaderConfig|fetch\(/);
});
test('shared native table script configures the real plugin contract without starting requests',()=>{
 let requests=0;const $=()=>{throw Error('definition must not touch DOM before invocation');};$.fn={dataTable:{ext:{}},dataTableExt:{oStdClasses:{}}};const ctx={$,$: $,jQuery:$,requestGet(){requests++;},app:{options:{},lang:{}}};ctx.window=ctx;vm.createContext(ctx);vm.runInContext(read('assets/js/native-admin-tables.js'),ctx);
 assert.equal(requests,0);assert.equal($.fn.dataTable.ext.errMode,'throw');assert.equal($.fn.dataTableExt.oStdClasses.sWrapper,'dataTables_wrapper form-inline dt-bootstrap table-loading');for(const name of ['initDataTable','initDataTableInline','dt_custom_view','fix_kanban_height'])assert.equal(typeof ctx[name],'function');
});
test('principal and selected contexts load the same table source after real plugin and common',()=>{
 const helper=read('application/helpers/assets_helper.php'),presentation=read('application/services/NativeCompanyAdminPresentation.php');assert(helper.includes("'assets/js/native-admin-tables.js'"));assert(helper.includes("['common-js','datatables-js','native-admin-navigation']"));
 assert(presentation.indexOf("'assets/plugins/datatables/datatables.min.js'")<presentation.indexOf("'assets/js/native-admin-tables.js'"));assert(presentation.indexOf("'assets/builds/common.js'")<presentation.indexOf("'assets/js/native-admin-tables.js'"));
});

test('both admin bootstraps share actual widget initialization and bind select validation once',()=>{
 const calls=[],handlers=[];const $=()=>({on:(name,fn)=>handlers.push([name,fn])});$.fn={dataTable:{ext:{}},dataTableExt:{oStdClasses:{}}};
 const ctx={$: $,jQuery:$,document:{},app:{},init_tags_inputs:()=>calls.push('tags'),appColorPicker:()=>calls.push('color'),appDataTableInline:()=>calls.push('table'),appSelectPicker:()=>calls.push('select'),appLightbox:()=>calls.push('lightbox'),appProgressBar:()=>calls.push('progress'),appDatepicker:()=>calls.push('date')};ctx.window=ctx;vm.createContext(ctx);vm.runInContext(read('assets/js/native-admin-tables.js'),ctx);
 assert.deepEqual(calls,[]);ctx.NativeAdminWidgets.initTags();ctx.NativeAdminWidgets.initTables();ctx.NativeAdminWidgets.initVisuals();ctx.NativeAdminWidgets.initDates();ctx.NativeAdminWidgets.initSelects();ctx.NativeAdminWidgets.initSelects();assert.deepEqual(calls,['tags','color','table','lightbox','progress','date','select']);assert.equal(handlers.length,1);assert.equal(handlers[0][0],'app.form-validate');
 for(const f of ['assets/js/main.js','assets/js/native-company-admin.js'])for(const name of ['initTags','initTables','initVisuals','initDates','initSelects'])assert(read(f).includes('NativeAdminWidgets.'+name+'()'));
 assert.equal((read('assets/js/main.js').match(/select_picker_validated_event/g)||[]).length,0);
});
