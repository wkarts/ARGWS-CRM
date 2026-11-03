'use strict';
const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const source=fs.readFileSync(path.join(__dirname,'../assets/js/native-admin-navigation.js'),'utf8');
function model(initial, width=1440, setupOpen=true) {
  const classes=new Set(initial), calls=[], timers=[], handlers=new Map(), attributes={};
  const eventTarget=selector=>selector==='#header > .hide-menu'?'.hide-menu':selector;
  const body={hasClass:name=>classes.has(name),addClass(names){names.split(/\s+/).filter(Boolean).forEach(name=>classes.add(name));return this;},removeClass(names){names.split(/\s+/).filter(Boolean).forEach(name=>classes.delete(name));return this;}};
  const collection=(selector)=>({
    length:0,
    find(){return collection('empty');}, parents(){return collection('empty');}, not(){return this;}, prop(){return this;},
    metisMenu(){return this;}, height(){return selector==='#side-menu'?280:240;}, outerHeight(){return selector==='#header'?57:1000;},
    width(){return width;}, css(){return this;}, hasClass(name){return selector==='#setup-menu-wrapper'&&name==='display-block'&&setupOpen;},
    addClass(names){if(selector==='body')body.addClass(names);return this;},removeClass(names){if(selector==='body')body.removeClass(names);return this;},
    on(event,handler){handlers.set(eventTarget(selector)+'|'+event,handler);return this;}, click(handler){if(handler)handlers.set(eventTarget(selector)+'|click',handler);else{calls.push(selector+' click');const plain=handlers.get(eventTarget(selector)+'|click');if(plain)plain({preventDefault(){calls.push('preventDefault');}});const aria=handlers.get(eventTarget(selector)+'|click.nativeMenuAria');if(aria)aria();}return this;},
    attr(name,value){attributes[name]=value;return this;},trigger(name){calls.push(selector+' trigger '+name);return this;},
    onContent(){return this;},
  });
  const doc={documentElement:{dir:'ltr'},outerHeight:()=>1000,getElementById:()=>({addEventListener(){}})};
  let env;
  const $=target=>{
    if(typeof target==='function'){target();return collection('ready');}
    if(target===doc)return Object.assign(collection('document'),{outerHeight:()=>1000});
    if(target===env||target&&target.NativeDevice)return winCollection();
    if(target==='body')return body;
    if(target==='#wrapper')return Object.assign(collection(target),{find:()=>({height:()=>300})});
    return collection(target);
  };
  function winCollection(){return Object.assign(collection('window'),{width:()=>width,height:()=>900});}
  env={document:doc,navigator:{userAgent:'Desktop synthetic'},location:'https://crm.test/sub/admin/tasks',setTimeout:(cb,delay)=>{timers.push(delay);cb();},NativeDevice:{isMobile:()=>false},jQuery:$,$};
  env.window=env;vm.runInNewContext(source,env);env.NativeAdminNavigation.init({isMobile:()=>false,isRTL:'false'});
  return {classes,calls,timers,attributes,click(){collection('.hide-menu').click();},resize(next,type='resize'){width=next;const listener=handlers.get('window|load resize');if(listener)listener({type});const aria=handlers.get('window|resize.nativeMenuAria');if(aria)aria();}};
}
test('the delivered native menu handler toggles desktop/mobile states with show taking precedence',()=>{
  for(const [initial,opened] of [[[],false],[['hide-sidebar'],true],[['page-small'],true],[['page-small','hide-sidebar'],true],[['page-small','show-sidebar'],false]]) {
    const m=model(initial,initial.includes('page-small')?390:1440);m.click();assert.equal(m.classes.has('show-sidebar'),opened);assert.equal(m.classes.has('hide-sidebar'),!opened);
    assert.equal(m.calls.filter(call=>call==='preventDefault').length,2,'menu and setup controls both prevent navigation');
    assert.equal(m.calls.filter(call=>call==='.close-customizer click').length,1,'open setup menu closes through its delivered handler');
    assert.equal(m.attributes['aria-expanded'],String(opened),'aria state follows the actual menu state');
  }
});
test('the delivered resize handler reconciles mobile and desktop state in both directions',()=>{
  const m=model([]);m.resize(390,'load');assert.deepEqual(m.timers,[150]);assert(m.classes.has('page-small'));m.click();assert(m.classes.has('show-sidebar'));
  m.resize(1440);assert.deepEqual(m.timers,[150,0]);assert(!m.classes.has('page-small'));assert(!m.classes.has('show-sidebar'));assert(!m.classes.has('hide-sidebar'));
  m.click();assert(m.classes.has('hide-sidebar'));m.resize(390);m.click();assert(m.classes.has('show-sidebar'));assert(!m.classes.has('hide-sidebar'));
});
