'use strict';
// Configuration contract only. Does not claim a browser launch or DOM validation.
const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const workflow=fs.readFileSync(path.join(__dirname,'../.github/workflows/asaas-payment-origin.yml'),'utf8').split('\n  settings-ui:')[1];
const harness=fs.readFileSync(path.join(__dirname,'asaas_settings_tabs.test.js'),'utf8');
test('settings UI installs only the pinned paired browser outside the repository dependency tree',()=>{
 assert.match(workflow,/mkdir -p "\$RUNNER_TEMP\/asaas-settings-ui"/);
 assert.match(workflow,/cp docker\/ci\/package\.json docker\/ci\/package-lock\.json "\$RUNNER_TEMP\/asaas-settings-ui\/"/);
 assert.match(workflow,/npm ci --prefix "\$RUNNER_TEMP\/asaas-settings-ui" --ignore-scripts/);
 assert.doesNotMatch(workflow,/npm install|--no-package-lock/);
 assert.match(workflow,/"\$RUNNER_TEMP\/asaas-settings-ui\/node_modules\/\.bin\/playwright" install --with-deps chromium/);
 assert.match(workflow,/NODE_PATH: \$\{\{ runner\.temp \}\}\/asaas-settings-ui\/node_modules/);
 assert.doesNotMatch(workflow,/command -v google-chrome|npx playwright|--only-shell/);
});
test('CI supplies a verified paired executable and cannot silently take the no-browser skip',()=>{
 assert.match(workflow,/chrome="\$\(node -e 'process\.stdout\.write\(require\("playwright"\)\.chromium\.executablePath\(\)\)'\)"/);
 assert.match(workflow,/test -n "\$chrome" && test -x "\$chrome"/);
 assert.match(workflow,/CHROMIUM_BIN="\$chrome" ASAAS_UI_SCREENSHOT=artifacts\/asaas-settings\.png node tests\/asaas_settings_tabs\.test\.js/);
 assert.match(workflow,/timeout 10s "\$chrome" --version/);
 assert.match(harness,/chromium\.launch\(\{headless:true, executablePath:process\.env\.CHROMIUM_BIN, timeout:30000\}\)/);
 assert.doesNotMatch(workflow,/continue-on-error|for attempt|retry/);
});
