/* Presentation only. Context switches remain explicit POSTs handled by the server. */
(function () {
    'use strict';
    // Normalize unstyled controls across every isolated page, not only tabbed workspaces.
    // Preserve nodes, names, types, values, disabled state, form owners, handlers and CSRF.
    document.querySelectorAll('.company-settings-card button').forEach(function (button) {
        if (button.className.trim() !== '' || button.closest('[role="tablist"]') || button.dataset.companyKeepStyle !== undefined) { return; }
        var destructive = /^(revogar|excluir|remover|cancelar|desativar)\b/i.test(button.textContent.trim());
        button.classList.add('btn', destructive ? 'btn-danger' : 'btn-primary');
    });
    document.querySelectorAll('.company-settings-card input, .company-settings-card select, .company-settings-card textarea').forEach(function (field) {
        if (field.className.trim() !== '' || field.dataset.companyKeepStyle !== undefined) { return; }
        if (field.tagName === 'INPUT' && ['hidden', 'checkbox', 'radio', 'button', 'submit', 'reset', 'image', 'file', 'range', 'color'].includes(field.type)) { return; }
        field.classList.add('form-control');
    });
    document.querySelectorAll('.company-settings-card table').forEach(function (table) {
        if (table.className.trim() !== '' || table.dataset.companyKeepStyle !== undefined) { return; }
        table.classList.add('table', 'table-hover');
        if (!table.closest('.table-responsive')) {
            var wrapper = document.createElement('div');
            wrapper.className = 'table-responsive';
            // Move the same table under a wrapper at the same parent: IDs, listeners,
            // cells and enclosing form ownership remain intact. No DataTables initialization.
            table.parentNode.insertBefore(wrapper, table);
            wrapper.appendChild(table);
        }
    });
    var search = document.querySelector('[data-company-menu-search]');
    if (search) {
        var groups = Array.from(document.querySelectorAll('#side-menu > li'));
        var initial = new Map();
        groups.forEach(function (group) { var details = group.querySelector('details'); if (details) { initial.set(details, details.open); } });
        search.addEventListener('input', function () {
            var term = search.value.trim().toLocaleLowerCase('pt-BR');
            groups.forEach(function (group) {
                group.hidden = term !== '' && !group.textContent.toLocaleLowerCase('pt-BR').includes(term);
                var details = group.querySelector('details');
                if (details) { details.open = term ? !group.hidden : initial.get(details); }
            });
        });
    }
    var toggle = document.querySelector('.company-menu-toggle');
    if (!toggle) { return; }
    var narrow = window.matchMedia('(max-width: 768px)');
    function setCollapsed(collapsed) {
        document.body.classList.toggle('company-menu-collapsed', collapsed);
        toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    }
    setCollapsed(narrow.matches);
    toggle.addEventListener('click', function () {
        setCollapsed(!document.body.classList.contains('company-menu-collapsed'));
    });
    var wrapper = document.querySelector('#wrapper');
    if (wrapper) {
        // The native mobile scrim targets its wrapper. Capture prevents a covered
        // control from navigating/submitting while this gesture dismisses the menu.
        wrapper.addEventListener('click', function (event) {
            if (narrow.matches && !document.body.classList.contains('company-menu-collapsed')) {
                event.preventDefault();
                event.stopImmediatePropagation();
                setCollapsed(true);
                toggle.focus();
            }
        }, true);
    }
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && narrow.matches) { setCollapsed(true); toggle.focus(); }
    });
}());
