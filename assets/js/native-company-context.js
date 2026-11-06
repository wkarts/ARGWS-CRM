/* Invalidate old documents after a confirmed server-side company selection. */
(function (global) {
    'use strict';
    if (global.NativeCompanyContextGuard) return;
    var mounted = false, stale = false, retire = null, observers = [];
    global.NativeCompanyContextGuard = Object.freeze({
        isCurrent: function () { return !stale; },
        onObsolete: function (callback) {
            if (typeof callback !== 'function') throw new Error('native_company_context_observer_invalid');
            if (stale) callback();
            else observers.push(callback);
        },
        // The existing selector still performs its authenticated native POST.
        // Stop old reads before that POST can rotate the server-side context.
        beginSelection: function () { if (retire) retire(); },
        mount: function (config) {
            if (mounted) return;
            var root = new URL(config.root, global.location.href);
            if (root.origin !== global.location.origin || root.username || root.password || root.search || root.hash || !root.pathname.endsWith('/') ||
                !/^[a-f0-9]{64}$/.test(config.channel) || !Number.isSafeInteger(config.generation) || config.generation < 0) {
                throw new Error('native_company_context_config_invalid');
            }
            mounted = true;
            var key = 'crm:company-change:' + root.pathname + ':' + config.channel;
            var channel = null;
            retire = function () {
                if (stale) return false;
                stale = true;
                // Do not paint data from the old issuer while the next request resolves its own scope.
                var hide = document.createElement('style');
                hide.textContent = 'body{visibility:hidden!important}';
                document.documentElement.appendChild(hide);
                observers.splice(0).forEach(function (callback) {
                    try { callback(); } catch (_) { /* A teardown failure must not retain an obsolete document. */ }
                });
                return true;
            };
            function invalidate(destination) {
                if (!retire()) return;
                global.location.replace(destination || root.href);
            }
            function changed(value) {
                if (Number.isSafeInteger(value) && value > config.generation) invalidate();
            }
            function stored() {
                try {
                    var value = global.localStorage.getItem(key);
                    if (value !== null && /^(0|[1-9][0-9]*)$/.test(value)) changed(Number(value));
                } catch (_) { /* Storage is optional; the server still validates every request. */ }
            }
            global.addEventListener('storage', function (event) {
                if (event.key === key && typeof event.newValue === 'string' && /^(0|[1-9][0-9]*)$/.test(event.newValue)) changed(Number(event.newValue));
            });
            global.addEventListener('pageshow', function (event) {
                stored();
                // A restored document may belong to a previous authenticated session as well.
                // If no newer company was observed, revalidate its original route so Back
                // keeps its navigation target; the server still resolves ownership afresh.
                if (event.persisted && !stale) {
                    var target = new URL(global.location.href);
                    invalidate(target.origin === root.origin && target.pathname.startsWith(root.pathname) ? target.href : root.href);
                }
            });
            document.addEventListener('visibilitychange', function () {
                if (document.visibilityState === 'visible') stored();
            });
            try {
                if (typeof global.BroadcastChannel === 'function') {
                    channel = new global.BroadcastChannel(key);
                    channel.onmessage = function (event) { changed(event.data); };
                }
            } catch (_) { channel = null; }
            stored();
            if (stale) return;
            // Only an increasing, non-authorizing counter is shared; tokens stay in this document.
            try { global.localStorage.setItem(key, String(config.generation)); } catch (_) {}
            if (channel) channel.postMessage(config.generation);
        }
    });
}(window));
