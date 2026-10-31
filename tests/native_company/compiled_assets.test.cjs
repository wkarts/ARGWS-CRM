'use strict';
const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm');
const root=process.env.CRM_TEST_ROOT?path.resolve(process.env.CRM_TEST_ROOT):path.resolve(__dirname,'../..'),read=p=>fs.readFileSync(path.join(root,p),'utf8');
test('delivered production main uses extracted shared handlers rather than historical duplicate definitions',()=>{
 const main=read('assets/js/main.min.js');new vm.Script(main,{filename:'main.min.js'});
 for(const component of ['NativeAdminNavigation','NativeAdminSearch','NativeAdminNotifications','NativeAdminRuntime'])assert(main.includes(component),`production main missing ${component}`);
 for(const name of ['toggle_file_visibility','save_ei_items_order','switch_field','retrieve_imap_folders','initDataTable','initDataTableInline','dt_custom_view','do_filter_active','fix_kanban_height','mainWrapperHeightFix','init_datepicker','init_color_pickers','init_selectpicker','init_lightbox','init_progress_bars','new_task','init_task_modal','timer_action','delete_user_unfinished_timesheet','requestGet','requestGetJSON','init_form_reminder','new_task_reminder','edit_reminder','reminderFormHandler','reload_reminders_tables','update_todo_items','toggle_edit_note','edit_note'])assert.doesNotMatch(main,new RegExp(`function\\s+${name}\\s*\\(`),`production main duplicates extracted ${name}`);
});
test('delivered common contains actual shared device helper before native app code',()=>{
 const common=read('assets/builds/common.js');new vm.Script(common,{filename:'common.js'});
 assert(common.includes('NativeDevice'),'common bundle must contain native-device.js, not stale pre-extraction app code');
 const helper=common.indexOf('NativeDevice'),use=common.lastIndexOf('NativeDevice.isMobile');assert(use>helper,'shared helper definition precedes app consumer');
});
test('real assets helper loads shared source files before whichever main core_file chooses',()=>{
 const helper=read('application/helpers/assets_helper.php');
 for(const file of ['native-admin-item-order','native-admin-files','native-admin-switch','native-admin-imap','native-admin-tables','native-admin-navigation','native-admin-search','native-admin-notifications','native-admin-runtime','native-admin-reminders','native-admin-todos','native-admin-notes','native-ui-tools','native-tasks'])assert(helper.includes(`assets/js/${file}.js`),file);
 const assets=read('application/libraries/assets/App_assets.php');assert.match(assets,/get_option\('use_minified_files'\) == 1/);assert.match(assets,/file_exists\(\$path \. '\/' \. \$filename\)/);
});
