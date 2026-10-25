'use strict';
const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const root=path.resolve(__dirname,'../..');
const read=p=>fs.readFileSync(path.join(root,p),'utf8');
test('header storage is issuer-owned and does not adopt global notification rows',()=>{
 const s=read('application/services/NativeCompanyHeaderSchema.php');
 assert.match(s,/company_native_notifications/);assert.match(s,/company_native_search_history/);
 assert.match(s,/`resource_id` bigint unsigned NOT NULL/);assert.match(s,/notification_event.*issuer_id.*recipient_id.*event_key/);
 assert.match(s,/assertTable\(\$db,\$p,\$table,\$parts\); else \$missing/);
 assert.doesNotMatch(s,/ALTER TABLE|INSERT INTO|UPDATE .*notifications/);
 assert.match(s,/REFERENTIAL_CONSTRAINTS/);assert.match(s,/DELETE_RULE/);
});
test('history never removes by visual index or reads legacy staff history',()=>{
 const s=read('application/services/NativeCompanySearchHistory.php');
 assert.match(s,/IssuerTransaction::run/);assert.match(s,/issuer_id=\? AND staff_id=\? AND id=\? AND revision=\?/);
 assert.match(s,/issuer_search_stale/);assert.match(s,/->member\(\$issuer,\$actor\)/);
 assert.doesNotMatch(s,/SELECT .*recent_search|UPDATE .*staff SET|OFFSET/);
});
test('notification events derive payload and route from authorized source, not supplied links',()=>{
 const s=read('application/services/NativeCompanyNotifications.php');
 assert.match(s,/IssuerTransaction::assertOwned/);assert.match(s,/NativeCompanyResourceReferences::resolve/);
 assert.match(s,/source_kind.*!==.*source_kind/);assert.match(s,/issuer_notification_event_conflict/);
 assert.match(s,/WHERE issuer_id=\? AND recipient_id=\?/);
 assert.match(s,/\$event=\$kind\.':'.\$sourceId\.':'.\$source\['revision'\]/);
 assert.doesNotMatch(s,/fromcompany|misc_model|add_notification\(|serialize\(/);
});
test('native search uses scoped SQL candidates and ownership resolver before same native view',()=>{
 const s=read('application/services/NativeCompanySearch.php');
 assert.match(s,/WHERE issuer_id=\?/);assert.match(s,/ESCAPE '!'/);assert.match(s,/NativeCompanyResourceReferences::resolve/);
 assert.match(s,/issuers_invoice_documents d ON d.invoice_id=i.id AND d.issuer_id=i.issuer_id/);
 assert.doesNotMatch(s,/misc_model|perform_search|SELECT \*/);
 const view=read('application/views/admin/search.php');
 assert.match(view,/NativeCompanyAdminPresentation::current/);assert.match(view,/native_search_result_invalid/);
 assert.match(view,/\$nativeSearch \? \$output : hooks\(\)/);
 assert.match(view,/e\(\$_result\['label'\]\)/);
});
test('same notification markup consumes explicit owned DTO without global hydration',()=>{
 const s=read('application/views/admin/includes/notifications.php');
 assert.match(s,/native_notification_provider_missing/);
 assert.match(s,/\$_notifications=\$this->misc_model->get_user_notifications\(\)/);
 assert.match(s,/if\s*\(!\$notificationContext &&/);
 assert.match(s,/\$description=e\(\$notification\['title'\]\).*e\(\$notification\['body'\]\)/);
 assert.match(s,/NativeCompanyAdminPresentation::current/);
});
test('search interactions are extracted once from main, transport injected and text escaped',()=>{
 const main=read('assets/js/main.js'),shared=read('assets/js/native-admin-search.js');
 assert.match(main,/NativeAdminSearch.init/);assert.doesNotMatch(main,/\$\("#search_input"\)\.on\(/);
 assert.match(shared,/keyup paste/);assert.match(shared,/700/);assert.match(shared,/\.text\(text\)/);
 assert.match(shared,/options.search\(q\)/);assert.match(shared,/options.remove\(historyRows\[index\],index\)/);
 assert.match(shared,/historyRows.splice\(index,1\)/);assert.match(shared,/if\(mounted\)return/);
 assert.doesNotMatch(shared,/\$\.post|requestGet|company_context/);
});
test('native notifications share real interactions and preserve two independent read states',()=>{
 const main=read('assets/js/main.js'),shared=read('assets/js/native-admin-notifications.js');
 assert.match(main,/NativeAdminNotifications.init/);assert.match(main,/return NativeAdminNotifications.markOne/);
 assert.match(shared,/transport.mark\(id,"inline"\)/);assert.match(shared,/transport.mark\(null,"seen"\)/);
 assert.match(shared,/transport.mark\(notId,"all"\)/);assert.doesNotMatch(shared,/requestGet|\$\.post/);
 const service=read('application/services/NativeCompanyNotifications.php');
 assert.match(service,/\['read_at','inline_read_at'\]/);assert.match(service,/\$mode==='seen'\?'read_at':'inline_read_at'/);
 const endpoints=read('application/services/NativeCompanyHeaderEndpoints.php');
 assert.match(endpoints,/assertNativeMutation\(\$ci\)/);assert.match(endpoints,/self::context\(\$ci,false\)/);
});
test('contextual glue captures CSRF/issuer and sends no automatic provider mutation',()=>{
 const vm=require('node:vm');const calls=[],ready=[],widgets=[];let search,notifications,navigation;
 const config={setupPersistence:true,root:'https://crm.test/sub/admin/',context:'issuer2:revision',csrf:'csrf-fixed',frameworkCsrf:{name:'csrf-native',value:'framework-fixed'},search:true,history:[],notifications:true,options:{values:{desktop_notifications:'0',auto_dismiss_desktop_notifications_after:null}},language:'portuguese_br',browser:'chrome',newNotificationLabel:'New'};
 const ctx={init_table_tickets:()=>widgets.push('tickets'),init_table_staff_projects:()=>widgets.push('staffProjects'),$:fn=>{assert.equal(typeof fn,'function');ready.push(fn);},app:{options:{calendar_first_day:'1'},available_tags:[]},NativeAdminWidgets:Object.fromEntries(['initTags','initTables','initVisuals','initDates','initSelects'].map(name=>[name,()=>widgets.push(name)])),URL,URLSearchParams,location:{href:'https://crm.test/sub/admin/',origin:'https://crm.test'},document:{title:'CRM',body:{classList:{contains:()=>true},dataset:{nativeMobile:'false'}},documentElement:{dir:'ltr'}},NativeCompanyHeaderConfig:config,
 Dropzone:{options:{otherUpload:{url:'/owned'}}},NativeAdminNavigation:{init(o){navigation=o;}},NativeAdminSearch:{init(o){search=o;}},NativeAdminNotifications:{init(o){notifications=o;},markOne(){},markAll(){},fetch(){}},jQuery:{ajaxPrefilter(fn){this.prefilter=fn;},ajax(o){if(this.prefilter)this.prefilter(o);calls.push(o);return {then(fn){return fn({notificationsIds:[1]});}}}}};ctx.window=ctx;
 vm.runInNewContext(read('assets/js/native-admin-runtime.js'),ctx);vm.runInNewContext(read('assets/js/native-company-transport.js'),ctx);vm.runInNewContext(read('assets/js/native-company-admin.js'),ctx);assert.equal(calls.length,0);assert.equal(ready.length,1);assert.deepEqual(widgets,[]);ready[0]();assert.deepEqual(widgets,['initTags','initTables','tickets','staffProjects','initVisuals','initDates','initSelects']);assert.equal(calls.length,0,'widget ready must not perform provider requests');assert.equal(ctx.Dropzone.options.newsFeedDropzone,false);assert.equal(ctx.Dropzone.options.salesUpload,false);assert.equal(ctx.Dropzone.options.otherUpload.url,'/owned');
 search.search('query');assert.equal(calls[0].type,'POST');assert.equal(calls[0].headers['X-Company-Context'],'issuer2:revision');assert.equal(calls[0].headers['X-Native-Company-CSRF'],'csrf-fixed');assert.equal(calls[0].data['csrf-native'],'framework-fixed');
 search.remove({id:'176093659137',revision:7});assert.equal(calls[1].url,'https://crm.test/sub/admin/misc/remove_recent_search/176093659137');assert.equal(calls[1].data.revision,7);
 notifications.transport.mark('171798691841','inline');assert.equal(calls[2].type,'POST');assert.match(calls[2].url,/set_notification_read_inline\/171798691841$/);
 const result=notifications.transport.read();assert.equal(calls[3].type,'GET');assert.equal(result.notificationsIds.length,0);
 navigation.persistSetup('open');assert.equal(calls[4].type,'POST');assert.equal(calls[4].headers['X-Native-Company-CSRF'],'csrf-fixed');assert.match(calls[4].url,/misc\/set_setup_menu_open$/);assert.throws(()=>navigation.persistSetup('anything'),/state_invalid/);
 config.root='https://foreign.test/admin/';search.search('still captured');assert.match(calls[5].url,/^https:\/\/crm.test\/sub\/admin\//);assert.throws(()=>ctx.NativeCompanyTransport.request('https://foreign.test/admin/','POST',{}),/route_invalid/);
});

test('native common dependency order preserves real plugins without global app bootstrap',()=>{
 const source=read('application/services/NativeCompanyAdminPresentation.php');
 assert(source.indexOf("'assets/plugins/jquery-validation/jquery.validate.min.js'")<source.indexOf("'assets/builds/common.js'"));
 assert(source.includes("'assets/builds/vendor-admin.js'"));
 assert(!source.includes("'assets/js/main.js'"));
 const runtime=read('assets/js/native-admin-runtime.js'),main=read('assets/js/main.js');
 assert(main.includes('NativeAdminRuntime.configureUploads();'));
 assert(!main.includes('Dropzone.options.newsFeedDropzone = false;'));
 assert(!runtime.includes('autoDiscover'));
 assert(!/ajax|requestGet|fetch\(/.test(runtime));
});

 test('staff project table uses the shared source after DataTables in both contexts',()=>{
 const presentation=read('application/services/NativeCompanyAdminPresentation.php'),assets=read('application/helpers/assets_helper.php');
 assert(presentation.indexOf("'assets/js/native-admin-tables.js'")<presentation.indexOf("'assets/js/native-admin-staff.js'"));
 assert(presentation.indexOf("'assets/js/native-admin-staff.js'")<presentation.indexOf("'assets/js/native-company-admin.js'"));
 assert.match(assets,/add\('native-admin-staff', 'assets\/js\/native-admin-staff.js', 'admin', \['native-admin-tables'\]\)/);
 const mainRegistration=assets.slice(assets.indexOf("'app-js',"),assets.indexOf("$CI->app_scripts->add('app-v3'"));
 assert(mainRegistration.includes("'native-admin-staff'"),'main must depend on the shared staff initializer');
 assert(assets.indexOf("add('native-admin-staff'")<assets.indexOf("'app-js',"),'shared initializer registration precedes main');
 });
