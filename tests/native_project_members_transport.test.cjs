const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(require('node:path').join(__dirname, '../assets/js/projects.js'), 'utf8');
const start = source.indexOf('// Same native member action, explicit POST');
assert(start >= 0);
function fixture(missing = false) {
    let handler, submitted;
    const hidden = ['_company_context','_native_company_csrf','framework_csrf','native_members_revision'].map(name => ({ name, value: name + '-captured', cloneNode() { return { name: this.name, value: this.value }; } }));
    const original = { querySelector: () => missing ? null : hidden[3], querySelectorAll: () => hidden };
    const document = { querySelector: () => original, createElement: () => ({ fields: [], appendChild(i) { this.fields.push(i); }, submit() { submitted = this; } }), body: { appendChild() {} } };
    vm.runInNewContext(source.slice(start), { URL, document, window: { location: { href: 'https://crm.invalid/sub/admin/projects/view/4294967297', origin: 'https://crm.invalid' } }, $: () => ({ on: (event, selector, fn) => { assert.equal(event, 'click'); assert.equal(selector, '.native-project-member-remove'); handler = fn; } }) });
    return { click(url) { handler.call({ href: url }, { preventDefault() {} }); return submitted; } };
}
test('same native remove URL submits captured form proof and revision via POST', () => {
    const url = 'https://crm.invalid/sub/admin/projects/remove_team_member/4294967297/92';
    const form = fixture().click(url);
    assert.equal(form.method, 'POST'); assert.equal(form.action, url);
    assert.deepEqual(Array.from(form.fields, x => x.name), ['_company_context','_native_company_csrf','framework_csrf','native_members_revision']);
    assert.equal(form.fields[3].value, 'native_members_revision-captured');
});
test('missing revision and cross-origin action fail before submit', () => {
    assert.throws(() => fixture(true).click('https://crm.invalid/sub/admin/projects/remove_team_member/1/92'), /incomplete/);
    assert.throws(() => fixture().click('https://other.invalid/admin/projects/remove_team_member/1/92'), /incomplete/);
});
