(function () {
    'use strict';
    var list = document.getElementById('asaas-settings-tabs');
    if (!list) return;
    var tabs = Array.from(list.querySelectorAll('[role="tab"]'));
    function panel(tab) { return document.getElementById(tab.getAttribute('aria-controls')); }
    tabs = tabs.filter(function (tab) { return panel(tab); });
    function activate(tab, focus) {
        tabs.forEach(function (item) {
            var active = item === tab;
            item.setAttribute('aria-selected', String(active));
            item.tabIndex = active ? 0 : -1;
            item.parentElement.classList.toggle('active', active);
            panel(item).hidden = !active;
            panel(item).setAttribute('role', 'tabpanel');
            panel(item).setAttribute('aria-labelledby', item.id);
        });
        if (focus) tab.focus();
    }
    function fromHash() {
        var target = document.getElementById(window.location.hash.slice(1));
        var tab = tabs.find(function (item) { return target && panel(item).contains(target); });
        if (tab) activate(tab, false);
        return tab;
    }
    tabs.forEach(function (tab, index) {
        tab.addEventListener('click', function (event) {
            event.preventDefault();
            activate(tab, false);
            window.history.replaceState(null, '', tab.getAttribute('href'));
        });
        tab.addEventListener('keydown', function (event) {
            var next;
            if (event.key === 'ArrowRight') next = (index + 1) % tabs.length;
            if (event.key === 'ArrowLeft') next = (index + tabs.length - 1) % tabs.length;
            if (event.key === 'Home') next = 0;
            if (event.key === 'End') next = tabs.length - 1;
            if (next !== undefined) { event.preventDefault(); activate(tabs[next], true); }
        });
        panel(tab).addEventListener('invalid', function () { activate(tab, false); }, true);
        panel(tab).querySelectorAll('form').forEach(function (form) {
            form.addEventListener('submit', function () {
                form.setAttribute('action', (form.getAttribute('action') || window.location.href).split('#')[0] + tab.getAttribute('href'));
            });
        });
    });
    if (!fromHash() && tabs.length) activate(tabs[0], false);
    window.addEventListener('hashchange', fromHash);
}());
