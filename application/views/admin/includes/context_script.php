<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php if (class_exists('CompanyRequestScope', false) && ($companyPageToken = CompanyRequestScope::token()) !== null) { ?>
<?php
$companyBrowserState = method_exists('CompanyRequestScope', 'context') ? CompanyRequestScope::context() : null;
$companyBrowserState = is_object($companyBrowserState) && method_exists($companyBrowserState, 'browserState') ? $companyBrowserState->browserState() : null;
if ($companyBrowserState !== null) { ?>
<script src="<?= htmlspecialchars(base_url('assets/js/native-company-context.js').'?v='.(defined('ARGWS_VERSION') ? ARGWS_VERSION : 'core'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<?php } ?>
<script>
(function () {
    'use strict';
    // Captured once per document. Never read the current selection from cookies/localStorage.
    const token = <?= json_encode($companyPageToken, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const nativeCsrf = <?= json_encode(function_exists('get_instance') && method_exists('CompanyRequestScope', 'nativeCsrfToken') ? CompanyRequestScope::nativeCsrfToken(get_instance()) : null, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const root = new URL(<?= json_encode(CompanyRequestScope::adminUrl($this), JSON_HEX_TAG | JSON_HEX_AMP); ?>, location.href);
    <?php if ($companyBrowserState !== null) { ?>
    NativeCompanyContextGuard.mount(Object.assign({root: root.href}, <?= json_encode($companyBrowserState, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR); ?>));
    <?php } ?>
    const current = () => !window.NativeCompanyContextGuard || NativeCompanyContextGuard.isCurrent();
    // NativeCompanyTransport owns native network headers. The shared head keeps
    // form stamping, but two XHR owners would append the same header twice.
    const ownsNetwork = <?= json_encode(!(function_exists('get_instance') && method_exists('CompanyRequestScope', 'nativeFor') && CompanyRequestScope::nativeFor(get_instance()))); ?>;
    const scoped = value => { try { const u = new URL(value || location.href, location.href); return u.origin === root.origin && (u.pathname === root.pathname.replace(/\/$/, '') || u.pathname.startsWith(root.pathname)); } catch (_) { return false; } };
    const stamp = form => {
        if (!current()) return;
        if (!scoped(form.action) || (form.method || 'get').toLowerCase() === 'get') return;
        let field = form.querySelector('input[name="_company_context"]');
        if (!field) { field = document.createElement('input'); field.type = 'hidden'; field.name = '_company_context'; field.value = token; form.appendChild(field); }
        if (nativeCsrf && !form.querySelector('input[name="_native_company_csrf"]')) {
            const csrf = document.createElement('input'); csrf.type = 'hidden'; csrf.name = '_native_company_csrf'; csrf.value = nativeCsrf; form.appendChild(csrf);
        }
        // Existing server-rendered tokens must keep their original company's value.
    };
    const stampAll = () => document.querySelectorAll('form').forEach(stamp);
    document.addEventListener('submit', event => { if (!current()) { event.preventDefault(); return; } if (event.target instanceof HTMLFormElement) stamp(event.target); }, true);
    const observeForms = () => {
        // A streamed response can expose <form> before its server-rendered tokens.
        // Observe only after parsing completes so those original values stay unique.
        stampAll();
        new MutationObserver(stampAll).observe(document.documentElement, {childList:true, subtree:true});
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', observeForms, {once:true});
    else observeForms();
    const nativeSubmit = HTMLFormElement.prototype.submit;
    HTMLFormElement.prototype.submit = function () { if (!current()) throw new Error('native_company_context_obsolete'); stamp(this); return nativeSubmit.call(this); };
    // Track scoped XHR in both shells. Header ownership remains singular, while
    // old reads are cancelled before the selector rotates the server context.
    const pending = new Set();
    if (window.NativeCompanyContextGuard) NativeCompanyContextGuard.onObsolete(() => {
        for (const xhr of Array.from(pending)) xhr.abort();
        pending.clear();
    });
    const open = XMLHttpRequest.prototype.open, send = XMLHttpRequest.prototype.send;
    XMLHttpRequest.prototype.open = function (method, url) { this.__companyScoped = scoped(url); return open.apply(this, arguments); };
    XMLHttpRequest.prototype.send = function () {
        if (this.__companyScoped) {
            if (!current()) throw new Error('native_company_context_obsolete');
            if (ownsNetwork) { this.setRequestHeader('X-Company-Context', token); if (nativeCsrf) this.setRequestHeader('X-Native-Company-CSRF', nativeCsrf); }
            pending.add(this);
            this.addEventListener('loadend', () => pending.delete(this), {once:true});
        }
        return send.apply(this, arguments);
    };
    if (!ownsNetwork) return;
    if (window.fetch) {
        const fetch = window.fetch;
        window.fetch = function (input, init) {
            const url = input instanceof Request ? input.url : input;
            if (scoped(url)) { if (!current()) return Promise.reject(new Error('native_company_context_obsolete')); init = Object.assign({}, init); const headers = new Headers(init.headers || (input instanceof Request ? input.headers : undefined)); headers.set('X-Company-Context', token); if (nativeCsrf) headers.set('X-Native-Company-CSRF', nativeCsrf); init.headers = headers; }
            return fetch.call(this, input, init);
        };
    }
}());
</script>
<?php } ?>
