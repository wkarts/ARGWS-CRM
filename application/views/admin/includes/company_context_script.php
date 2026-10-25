<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php if (class_exists('CompanyRequestScope', false) && ($companyPageToken = CompanyRequestScope::token()) !== null) { ?>
<script>
(function () {
    'use strict';
    // Captured once per document. Never read the current selection from cookies/localStorage.
    const token = <?= json_encode($companyPageToken, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const nativeCsrf = <?= json_encode(function_exists('get_instance') && method_exists('CompanyRequestScope', 'nativeCsrfToken') ? CompanyRequestScope::nativeCsrfToken(get_instance()) : null, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const root = new URL(<?= json_encode(CompanyRequestScope::adminUrl($this), JSON_HEX_TAG | JSON_HEX_AMP); ?>, location.href);
    const scoped = value => { try { const u = new URL(value || location.href, location.href); return u.origin === root.origin && (u.pathname === root.pathname.replace(/\/$/, '') || u.pathname.startsWith(root.pathname)); } catch (_) { return false; } };
    const stamp = form => {
        if (!scoped(form.action) || (form.method || 'get').toLowerCase() === 'get') return;
        let field = form.querySelector('input[name="_company_context"]');
        if (!field) { field = document.createElement('input'); field.type = 'hidden'; field.name = '_company_context'; field.value = token; form.appendChild(field); }
        if (nativeCsrf && !form.querySelector('input[name="_native_company_csrf"]')) {
            const csrf = document.createElement('input'); csrf.type = 'hidden'; csrf.name = '_native_company_csrf'; csrf.value = nativeCsrf; form.appendChild(csrf);
        }
        // Existing server-rendered tokens must keep their original company's value.
    };
    const stampAll = () => document.querySelectorAll('form').forEach(stamp);
    document.addEventListener('submit', event => { if (event.target instanceof HTMLFormElement) stamp(event.target); }, true);
    document.addEventListener('DOMContentLoaded', stampAll);
    new MutationObserver(stampAll).observe(document.documentElement, {childList:true, subtree:true});
    const nativeSubmit = HTMLFormElement.prototype.submit;
    HTMLFormElement.prototype.submit = function () { stamp(this); return nativeSubmit.call(this); };
    const open = XMLHttpRequest.prototype.open, send = XMLHttpRequest.prototype.send;
    XMLHttpRequest.prototype.open = function (method, url) { this.__companyScoped = scoped(url); return open.apply(this, arguments); };
    XMLHttpRequest.prototype.send = function () { if (this.__companyScoped) { this.setRequestHeader('X-Company-Context', token); if (nativeCsrf) this.setRequestHeader('X-Native-Company-CSRF', nativeCsrf); } return send.apply(this, arguments); };
    if (window.fetch) {
        const fetch = window.fetch;
        window.fetch = function (input, init) {
            const url = input instanceof Request ? input.url : input;
            if (scoped(url)) { init = Object.assign({}, init); const headers = new Headers(init.headers || (input instanceof Request ? input.headers : undefined)); headers.set('X-Company-Context', token); if (nativeCsrf) headers.set('X-Native-Company-CSRF', nativeCsrf); init.headers = headers; }
            return fetch.call(this, input, init);
        };
    }
}());
</script>
<?php } ?>
