'use strict';
const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
function functionAt(source, start) {
  const begin = source.indexOf('function', start), brace = source.indexOf('{', begin);
  let depth = 1, end = brace + 1;
  for (; depth && end < source.length; end++) { if (source[end] === '{') depth++; if (source[end] === '}') depth--; }
  assert.equal(depth, 0); return source.slice(begin, end);
}
for (const file of ['main.js','main.min.js']) {
  const source=fs.readFileSync(path.join(__dirname,'../assets/js',file),'utf8');
  const start=source.search(/\$\("\.hide-menu"\)\.click\(\(?function/);assert.ok(start>=0);
  function model(initial, width=1440) {
    const classes=new Set(initial), calls=[], timers=[];
    const body={hasClass:name=>classes.has(name),addClass(names){names.split(' ').forEach(name=>classes.add(name));return this;},removeClass(names){names.split(' ').forEach(name=>classes.delete(name));return this;}};
    const env={$:target=>target==='body'?body:target==='.close-customizer'?{click:()=>calls.push('close setup')}:{width:()=>width},setup_menu:{hasClass:()=>true},navigator:{userAgent:'Desktop synthetic'},is_mobile:()=>false,setTimeout:(callback,delay)=>{timers.push(delay);callback();},mainWrapperHeightFix:()=>calls.push('height fix')};
    const click=vm.runInNewContext('('+functionAt(source,start)+')',env);
    env.set_body_small=vm.runInNewContext('('+functionAt(source,source.indexOf('function set_body_small()'))+')',env);
    const listenerStart=source.indexOf('$(window).on("load resize"');assert.ok(listenerStart>=0);
    const resizeListener=vm.runInNewContext('('+functionAt(source,listenerStart)+')',env);
    return {classes,calls,timers,click(){click({preventDefault:()=>calls.push('preventDefault')});},resize(next,type='resize'){width=next;resizeListener({type});}};
  }
  test(file+': one click toggles actual native desktop/mobile states with show taking precedence',()=>{
    for(const [initial,opened] of [[[],false],[['hide-sidebar'],true],[['page-small'],true],[['page-small','hide-sidebar'],true],[['page-small','show-sidebar'],false],[['show-sidebar','hide-sidebar'],false]]) {
      const m=model(initial);m.click();assert.equal(m.classes.has('show-sidebar'),opened);assert.equal(m.classes.has('hide-sidebar'),!opened);
      assert.deepEqual(m.calls,['preventDefault','close setup'],'native event and setup-menu behavior preserved');
    }
  });
  test(file+': real resize initialization and toggle agree across breakpoints',()=>{
    const m=model([]);m.resize(390,'load');assert.deepEqual(m.timers,[150]);assert(m.classes.has('page-small'));m.click();assert(m.classes.has('show-sidebar'));
    m.resize(1440);assert.deepEqual(m.timers,[150,0]);assert(!m.classes.has('page-small'));assert(!m.classes.has('show-sidebar'));assert(!m.classes.has('hide-sidebar'));
    m.click();assert(m.classes.has('hide-sidebar'));m.resize(390);m.click();assert(m.classes.has('show-sidebar'));assert(!m.classes.has('hide-sidebar'));
  });
}
