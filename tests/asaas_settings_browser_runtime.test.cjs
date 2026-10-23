'use strict';
// Configuration contract only. Does not claim a browser launch or DOM validation.
const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const workflow=fs.readFileSync(path.join(__dirname,'../.github/workflows/asaas-payment-origin.yml'),'utf8').split('\n  settings-ui:')[1];
const harness=fs.readFileSync(path.join(__dirname,'asaas_settings_tabs.test.js'),'utf8');
test('browser jobs consume the same immutable tools image without repeated installation',()=>{
 const nativeWorkflow=fs.readFileSync(path.join(__dirname,'../.github/workflows/company-native-ui.yml'),'utf8');
 const image=/image: (ghcr\.io\/wkarts\/argws-crm-ci-php@sha256:[a-f0-9]{64})/;
 const settingsImage=workflow.match(image);
 assert.ok(settingsImage,'settings UI image must be digest-pinned');
 assert.equal(nativeWorkflow.match(image)?.[1],settingsImage[1]);
 for (const job of [workflow,nativeWorkflow]) {
  assert.match(job,/options: --shm-size=1g/);
  assert.doesNotMatch(job,/npm (?:install|ci)|npx playwright|install --with-deps|setup-php|setup-node|credentials:|packages: read/);
  assert.doesNotMatch(job,/command -v google-chrome|--only-shell|NODE_PATH:/,'use the image runtime, not runner-installed or overwritten paths');
 }
 const docker=fs.readFileSync(path.join(__dirname,'../docker/ci/Dockerfile'),'utf8');
 assert.match(docker,/NODE_PATH=\/opt\/ci-browser\/node_modules/);
 assert.match(docker,/PLAYWRIGHT_BROWSERS_PATH=\/opt\/ci-browsers/);
 assert.equal(JSON.parse(fs.readFileSync(path.join(__dirname,'../docker/ci/package.json'),'utf8')).dependencies.playwright,'1.55.1');
});
test('CI supplies a verified paired executable and cannot silently take the no-browser skip',()=>{
 assert.match(workflow,/chrome="\$\(node -e 'process\.stdout\.write\(require\("playwright"\)\.chromium\.executablePath\(\)\)'\)"/);
 assert.match(workflow,/test -n "\$chrome" && test -x "\$chrome"/);
 assert.match(workflow,/CHROMIUM_BIN="\$chrome" ASAAS_UI_SCREENSHOT=artifacts\/asaas-settings\.png node tests\/asaas_settings_tabs\.test\.js/);
 assert.match(workflow,/timeout 10s "\$chrome" --version/);
 assert.match(harness,/chromium\.launch\(\{headless:true, executablePath:process\.env\.CHROMIUM_BIN, timeout:30000\}\)/);
 assert.doesNotMatch(workflow,/continue-on-error|for attempt|retry/);
});
