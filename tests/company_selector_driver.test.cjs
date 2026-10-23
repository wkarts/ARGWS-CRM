'use strict';
const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const { selectCompanyOption } = require('./helpers/company_selector_driver.cjs');
function fixture(collapsed, options = {}) {
    const events = []; let value = '1'; let clicks = 0;
    const root = {waitFor: async () => {}, getAttribute: async name => {assert.equal(name,'data-company-current');return value;}};
    const toggle = {waitFor: async () => {}, boundingBox: async () => collapsed ? null : {x:10}, click: async () => events.push('open dropdown')};
    const choice = id => ({count: async () => ['1','2'].includes(id) ? 1 : 0,isEnabled:async()=>!options.disabled,
        waitFor: async args => {assert.equal(args.state,'visible');events.push('visible choice');},
        click: async () => {events.push('native choice POST');value=options.stale ? '1' : id;}});
    const page = {waitForLoadState: async () => {},waitForFunction:async()=>{},
        waitForNavigation:async args=>{assert.equal(args.waitUntil,'domcontentloaded');events.push('wait navigation');return {status:()=>200};},
        locator:selector=>{
            if(selector==='.company-selector')return root;
            if(selector.includes('[data-company-selector-toggle]'))return toggle;
            if(selector.includes('#header'))return {click:async()=>{clicks++;events.push('open sidebar');}};
            const match=selector.match(/data-company-choice="(\d+)"/);if(match)return choice(match[1]);
            throw Error('unexpected selector '+selector);
        }};
    return {page,events,current:()=>value,sidebarClicks:()=>clicks};
}
test('desktop/mobile choice performs one visible native POST and awaits navigation',async()=>{
    for(const collapsed of [false,true]){
        const f=fixture(collapsed);await selectCompanyOption(f.page,2);
        assert.deepEqual(f.events,[...(collapsed?['open sidebar']:[]),'open dropdown','visible choice','wait navigation','native choice POST']);
        assert.equal(f.current(),'2');assert.equal(f.sidebarClicks(),collapsed?1:0);
    }
});
test('unauthorized and disabled companies fail before any UI action',async()=>{
    for(const [id,options] of [[999,{}],[2,{disabled:true}]]){
        const f=fixture(true,options);await assert.rejects(()=>selectCompanyOption(f.page,id),/authorized enabled catalog/);
        assert.deepEqual(f.events,[]);assert.equal(f.current(),'1');
    }
});
test('unchanged server context after selection is a failure, not a silent pass',async()=>{
    const f=fixture(false,{stale:true});await assert.rejects(()=>selectCompanyOption(f.page,2),/server-rendered context/);
});
test('driver never writes DOM values, forces hidden controls or sends requests itself',()=>{
    const source=fs.readFileSync(__dirname+'/helpers/company_selector_driver.cjs','utf8');
    assert.doesNotMatch(source,/\.value\s*=(?!=)|\.hidden\s*=(?!=)|\.style\s*[.=]|force:\s*true|selectOption\(|dispatchEvent|\.request\.|fetch\(|\.submit\(/);
});
test('reselecting the active authorized company still uses its real submitter exactly once',async()=>{
    const f=fixture(false);await selectCompanyOption(f.page,1);
    assert.equal(f.events.filter(event=>event==='native choice POST').length,1);assert.equal(f.current(),'1');
});
test('shipped selector script guards duplicate submits, retains submitter and resets on pageshow', () => {
    const vm = require('node:vm');
    const listeners = {}, windowListeners = {};
    const toggle = {disabled:false};
    const choices = ['1','2'].map(value=>({name:'company_id',value,disabled:false,attributes:{},setAttribute(name,value){this.attributes[name]=value;},removeAttribute(name){delete this.attributes[name];}}));
    const form = {addEventListener:(name,handler)=>{listeners[name]=handler;}};
    const root = {dataset:{},querySelector:selector=>selector==='.company-selector-form'?form:toggle,querySelectorAll:()=>choices,addEventListener:(name,handler)=>{listeners[name]=handler;}};
    vm.runInNewContext(fs.readFileSync(require('node:path').join(__dirname,'../assets/js/company-selector.js'),'utf8'), {
        document:{querySelectorAll:()=>[root]},window:{addEventListener:(name,handler)=>{windowListeners[name]=handler;}}
    });
    let prevented=0;
    for (const target of [toggle, ...choices]) {
        let stopped=0;
        listeners.keydown({key:' ',target,stopPropagation:()=>stopped++,preventDefault:()=>prevented++});
        assert.equal(stopped,1,'Space does not reach Bootstrap anchor-only handler');
        assert.equal(prevented,0,'native button Space activation is not cancelled');
    }
    const event={submitter:choices[1],preventDefault:()=>prevented++};
    listeners.submit(event);
    assert.equal(prevented,0,'first native submission proceeds');
    assert.equal(toggle.disabled,true);
    assert.equal(event.submitter.name,'company_id'); assert.equal(event.submitter.value,'2'); assert.equal(event.submitter.disabled,false);
    listeners.submit(event); assert.equal(prevented,1,'duplicate prevented');
    windowListeners.pageshow();
    assert.equal(toggle.disabled,false); assert.ok(choices.every(choice=>!Object.hasOwn(choice.attributes,'aria-disabled')));
    listeners.submit(event); assert.equal(prevented,1,'back-forward restored page accepts a new deliberate choice');
});
