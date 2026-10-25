'use strict';
const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const root=path.resolve(__dirname,'../..'),read=p=>fs.readFileSync(path.join(root,p),'utf8');
test('native includes consume one server-attested presentation contract, not parallel layout',()=>{
 for(const name of ['head','header','aside','setup_menu','scripts']) {
  const text=read(`application/views/admin/includes/${name}.php`);
  assert.match(text,/NativeCompanyAdminPresentation::current\(function_exists\('get_instance'\) \? get_instance\(\) : \$this\)/);
  assert.doesNotMatch(text,/companies\/layout_|company-shell\.js|company-workspace\.js/);
 }
 const service=read('application/services/NativeCompanyAdminPresentation.php');
 assert.match(service,/CompanyRequestScope::nativeFor\(\$ci\)/);assert.match(service,/CompanyRequestScope::sealedFor\(\$ci\)/);
 assert.match(service,/self::\$owner!==\$ci/);assert.match(service,/self::\$token!==CompanyRequestScope::token/);
 assert.match(service,/nativeRouteAllowed\(\$ci,\$entry\['route'\]\)/);assert.match(service,/\$acl->can\(\$issuer,\$actor/);
 assert.doesNotMatch(service,/hooks\(\)|->load->model|get_option\(/);
});
test('legacy and scoped menus share actual constant descriptors',()=>{
 const menu=read('application/helpers/menu_helper.php'),catalog=read('application/services/NativeAdminMenuCatalog.php');
 const uses=[...menu.matchAll(/NativeAdminMenuCatalog::legacy\('([^']+)'\)/g)].map(m=>m[1]);
 assert.equal(uses.length,63);assert.equal(new Set(uses).size,63);
 for(const key of uses)assert(catalog.includes(`'${key}' => [`),key);
 for(const route of ['clients','invoices','projects','tasks','settings','subscriptions']) assert(catalog.includes(`'route' => '${route}'`));
 assert.doesNotMatch(catalog,/'route' => 'company_/);
});
test('one extracted native interaction is initialized by both native and contextual pages',()=>{
 const shared=read('assets/js/native-admin-navigation.js'),main=read('assets/js/main.js'),scoped=read('assets/js/native-company-admin.js');
 assert.match(main,/NativeAdminNavigation.init/);assert.match(scoped,/NativeAdminNavigation.init/);
 assert.doesNotMatch(scoped,/addEventListener|classList.toggle|metisMenu|fetch\(|XMLHttpRequest/);
 assert.match(shared,/side_bar.metisMenu\(\)/);assert.match(shared,/if\(mounted\)return/);
 assert.match(shared,/options.persistSetup/);assert.doesNotMatch(shared,/requestGet\(|fetch\(|XMLHttpRequest/);
 const frozen=JSON.parse(read('tests/native_company/navigation-extraction.json'));
 assert(shared.includes(frozen.height_body));
 assert(shared.includes(frozen.small_body.replace('$(this).width()','$(window).width()')));
 assert(read('assets/js/native-device.js').includes(frozen.mobile_body));
 assert.match(read('assets/js/app.js'),/function is_mobile\(\) \{ return NativeDevice.isMobile\(\); \}/);
 assert(read('webpack.mix.js').indexOf('"assets/js/native-device.js"')<read('webpack.mix.js').indexOf('"assets/js/app.js"'));
});
test('all extracted descriptors preserve the published native menu baseline',()=>{
 const baseline=JSON.parse(read('tests/native_company/native-menu-baseline.json')).entries;
 const source=read('application/services/NativeAdminMenuCatalog.php');assert.equal(Object.keys(baseline).length,63);
 for(const [key,expected] of Object.entries(baseline)){
  const line=source.split(`'${key}' => [`)[1]?.split('],')[0];assert(line,key);
  for(const [field,value] of Object.entries(expected)){
   const match=line.match(new RegExp(`'${field}'\\s*=>\\s*('([^']*)'|null|true|false|[0-9]+)`));assert(match,key+':'+field);
   const actual=match[2]!==undefined?match[2]:JSON.parse(match[1]);assert.deepEqual(actual,value,key+':'+field);
  }
 }
});
test('shared navigation initializes once and performs no session/provider transport by default',()=>{
 const vm=require('node:vm'),ready=[],bindings=[],plugins=[];
 const document={documentElement:{dir:'ltr'},getElementById:()=>null},window={};
 function box(name){const value={length:0,hasClass:()=>false,height:()=>name==='#side-menu'?600:800,outerHeight:()=>name==='#header'?57:1000,width:()=>1280,find:()=>box('empty'),metisMenu:()=>{plugins.push(name);return value;},on:(events,fn)=>{bindings.push([name,events,fn]);return value;},click:fn=>{if(fn)bindings.push([name,'click',fn]);return value;}};for(const key of ['addClass','removeClass','attr','prop','css','parents','not','prev','trigger'])value[key]=()=>value;return value;}
 const $=target=>{if(typeof target==='function'){ready.push(target);return;}return box(target);};window.jQuery=$;window.NativeDevice={isMobile:()=>false};
 vm.runInNewContext(read('assets/js/native-admin-navigation.js'),{window,document,navigator:{userAgent:'test'},location:'http://fixture.test/admin/',setTimeout:()=>{},console});
 window.NativeAdminNavigation.init({isMobile:()=>false,isRTL:'false'});const before=[ready.length,bindings.length];window.NativeAdminNavigation.init({isMobile:()=>false,isRTL:'false'});assert.deepEqual([ready.length,bindings.length],before);
 ready.forEach(fn=>fn());assert.deepEqual(plugins,['#side-menu','#setup-menu-wrapper']);
 const open=bindings.find(([name,event])=>name==='.open-customizer'&&event==='click');assert(open);open[2]({preventDefault(){}});
 const close=bindings.find(([name,event])=>name==='.close-customizer'&&event==='click');assert(close);close[2]({preventDefault(){}});
 assert.equal(bindings.filter(([name,event])=>name==='.hide-menu'&&event==='click').length,1);
});
