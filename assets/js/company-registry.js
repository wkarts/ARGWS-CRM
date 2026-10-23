(function () {
    'use strict';
    function initialize() {
        const root = document.querySelector('[data-company-registry]');
        if (!root) return;
        const body = root.querySelector('tbody');
        const rows = Array.from(body.rows); // Only the server-authorized identity projection reaches this page.
        const search = root.querySelector('#company-grid-search');
        const size = root.querySelector('[data-page-size]');
        const previous = root.querySelector('[data-page-prev]');
        const next = root.querySelector('[data-page-next]');
        let page = 0, column = 0, direction = 1;
        const collator = new Intl.Collator('pt-BR', {numeric:true, sensitivity:'base'});
        function render() {
            const query = search.value.trim().toLocaleLowerCase('pt-BR');
            const filtered = rows.filter(row => row.textContent.toLocaleLowerCase('pt-BR').includes(query));
            filtered.sort((a,b) => direction * collator.compare(a.cells[column].textContent.trim(),b.cells[column].textContent.trim()));
            const limit = Number(size.value);
            page = Math.min(page, Math.max(0, Math.ceil(filtered.length / limit)-1));
            rows.forEach(row => {row.hidden = true;});
            filtered.forEach(row => body.appendChild(row));
            filtered.slice(page*limit,(page+1)*limit).forEach(row => {row.hidden = false;});
            previous.disabled = page === 0;
            next.disabled = (page+1)*limit >= filtered.length;
            root.querySelector('[data-company-empty]').hidden = filtered.length !== 0;
            root.querySelector('[data-company-count]').textContent = filtered.length ? `${page*limit+1}–${Math.min((page+1)*limit,filtered.length)} de ${filtered.length} empresas` : '0 empresas';
        }
        search.addEventListener('input', () => {page = 0; render();});
        size.addEventListener('change', () => {page = 0; render();});
        previous.addEventListener('click', () => {page--; render();});
        next.addEventListener('click', () => {page++; render();});
        root.querySelectorAll('[data-sort]').forEach(button => button.addEventListener('click', () => {
            const index = Number(button.dataset.sort);
            direction = column === index ? -direction : 1; column = index; page = 0;
            root.querySelectorAll('th').forEach((th,i) => th.setAttribute('aria-sort',i===column?(direction===1?'ascending':'descending'):'none'));
            render();
        }));
        render();
        root.querySelectorAll('.company-status-form').forEach(form => form.addEventListener('submit', event => {
            if (!window.confirm('Confirmar alteração da situação desta empresa?')) event.preventDefault();
        }));
        const modal = root.querySelector('#company-create-modal');
        if (!modal || !window.jQuery) return;
        const frame = modal.querySelector('iframe');
        let completed = false;
        function open() {
            completed = false;
            window.jQuery(modal).modal({backdrop:'static', keyboard:true, show:true});
            document.getElementById('company-wizard-open').submit();
        }
        root.querySelector('[data-new-company]').addEventListener('click', open);
        window.addEventListener('message', event => {
            if (event.origin !== location.origin || event.source !== frame.contentWindow || !event.data) return;
            if (event.data.type === 'company-onboarding-csrf') {
                // The framework may rotate its global CSRF cookie on each wizard POST.
                // Refresh only that known field in same-origin forms; NEVER rotate the page's company token.
                const openingForm = document.getElementById('company-wizard-open');
                const known = Array.from(openingForm.elements).some(input => input.name === event.data.name && input.name !== '_company_context' && input.name !== 'action');
                if (known && typeof event.data.hash === 'string') document.querySelectorAll('form').forEach(form => {
                    if (new URL(form.action || location.href, location.href).origin !== location.origin) return;
                    Array.from(form.elements).filter(input => input.name === event.data.name).forEach(input => {input.value = event.data.hash;});
                });
            }
            if (event.data.type === 'company-onboarding-complete') completed = true;
            if (event.data.type === 'company-onboarding-close') window.jQuery(modal).modal('hide');
        });
        window.jQuery(modal).on('hidden.bs.modal', () => {
            frame.src = 'about:blank';
            const url = new URL(location.href); url.searchParams.delete('new_company'); url.searchParams.delete('draft');
            history.replaceState(null,'',url);
            if (completed) location.replace(url.href);
            else root.querySelector('[data-new-company]').focus();
        });
        if (modal.dataset.openOnLoad === '1') open();
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded',initialize); else initialize();
}());
