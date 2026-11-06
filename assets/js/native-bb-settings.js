(function () {
    'use strict';
    const tabs = Array.from(document.querySelectorAll('#bb-settings-tabs [role=tab]'));
    if (!tabs.length) return;
    function select(tab, focus) {
        tabs.forEach(item => {
            const active = item === tab;
            item.setAttribute('aria-selected', String(active));item.tabIndex = active ? 0 : -1;
            item.parentElement.classList.toggle('active', active);
            const panel = document.getElementById(item.getAttribute('aria-controls'));
            panel.hidden = !active;panel.classList.toggle('active', active);
        });
        if (focus) tab.focus();
    }
    tabs.forEach((tab, index) => {
        tab.addEventListener('click', e => {e.preventDefault();select(tab, false);history.replaceState(null, '', tab.getAttribute('href'));});
        tab.addEventListener('keydown', e => {
            const n = e.key === 'Home' ? 0 : e.key === 'End' ? tabs.length - 1 : e.key === 'ArrowRight' ? (index + 1) % tabs.length : e.key === 'ArrowLeft' ? (index + tabs.length - 1) % tabs.length : -1;
            if (n >= 0) {e.preventDefault();select(tabs[n], true);}
        });
    });
    document.addEventListener('invalid', e => {const panel = e.target.closest('[role=tabpanel]');const tab = panel && tabs.find(item => item.getAttribute('aria-controls') === panel.id);if (tab) select(tab, false);}, true);
    select(tabs.find(tab => tab.getAttribute('href') === location.hash) || tabs[0], false);
}());
