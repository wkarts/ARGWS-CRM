const test=require('node:test'),assert=require('node:assert/strict'),path=require('node:path');
const {audit,repository}=require('../../scripts/ci/check-native-js-dependencies.cjs');
test('missing legacy-only date helper fails before browser initialization',()=>{assert.equal(audit({'tasks.js':'function task(){unformat_date("01/01/2026");}'},'function unformat_date(){}')[0].name,'unformat_date');});
test('a shared definition resolves the dependency without invoking it',()=>{assert.deepEqual(audit({'tasks.js':'function task(){unformat_date("01/01/2026");}','tools.js':'function unformat_date(){throw new Error("not run");}'},'function unformat_date(){}'),[]);});
test('local functions do not become false missing globals',()=>{assert.deepEqual(audit({'navigation.js':'(function(){function set_body_small(){}set_body_small();})();'},'function set_body_small(){}'),[]);});
test('manifest catches an accidentally removed shared definition',()=>{assert.equal(audit({'tasks.js':'function task(){unformat_date();}'},'', ['unformat_date']).length,1);});
test('actual shared native asset graph links before long suites',()=>{assert.deepEqual(repository(path.resolve(__dirname,'../..')),[]);});

test('legacy-only global state is detected as well as functions',()=>{assert.equal(audit({'tasks.js':'function task(){return table_tasks;}'},'var table_tasks;')[0].name,'table_tasks');});
