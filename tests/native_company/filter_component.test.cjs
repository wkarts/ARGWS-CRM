'use strict';
const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
const source=fs.readFileSync('resources/js/components/filters/AppFilters.vue','utf8');
const body=source.match(/function castFilterValues\(f\) \{[\s\S]*?\n\}/)[0];
const ctx={};vm.createContext(ctx);vm.runInContext(body,ctx);
test('same native component accepts actual legacy and owned default/shared representations',()=>{
 for(const value of [true,'1',1]){const r=ctx.castFilterValues({id:'158913789953',is_shared:value,is_default:value});assert.equal(r.id,158913789953);assert.equal(r.is_shared,true);assert.equal(r.is_default,true);}
 for(const value of [false,'0',0]){const r=ctx.castFilterValues({id:'22',is_shared:value,is_default:value});assert.equal(r.is_shared,false);assert.equal(r.is_default,false);}
});
test('same save/delete actions carry revision and exact native table identity',()=>{
 assert.match(source,/revision: activeFilter\.value \? activeFilter\.value\.revision : undefined/);
 assert.match(source,/filters\/delete\/\$\{filterId\}`,[^\n]+identifier: props.id[^\n]+view: viewName.value[^\n]+revision:/);
 assert.match(source,/typeof response === "string" \? JSON.parse\(response\) : response/);
});
