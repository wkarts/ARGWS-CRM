'use strict';
const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
test('native header job consumes validated immutable tools without install delays or credentials',()=>{
 const workflow=fs.readFileSync(path.join(__dirname,'../.github/workflows/native-header-layout.yml'),'utf8');
 const native=fs.readFileSync(path.join(__dirname,'../.github/workflows/company-native-ui.yml'),'utf8');
 const image=/image: (ghcr\.io\/wkarts\/argws-crm-ci-php@sha256:[a-f0-9]{64})/;
 assert.ok(workflow.match(image));
 assert.equal(workflow.match(image)[1],native.match(image)[1]);
 assert.match(workflow,/options: --shm-size=1g/);
 assert.match(workflow,/timeout-minutes: 10/);
 assert.match(workflow,/EXPECTED_PHP=8\.1 argws-ci-smoke/);
 assert.doesNotMatch(workflow,/npm |npx |setup-node|setup-php|credentials:|packages: read|ASAAS_PLAYWRIGHT_MODULE:/);
 assert.match(workflow,/node tests\/native_header_overflow\.browser\.cjs/);
 assert.match(workflow,/if: always\(\)/);
 assert.match(workflow,/path: \/tmp\/native-header-layout-evidence/);
});
