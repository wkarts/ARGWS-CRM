(function () {
    'use strict';
    const root = document.getElementById('invoice-repricing');
    if (!root) return;
    const print = document.getElementById('reprice-print');
    if (print) print.addEventListener('click', () => window.print());
    const form = document.getElementById('reprice-form');
    if (!form) return;
    const data = JSON.parse(document.getElementById('reprice-data').textContent);
    const rows = new Map(data.directory.map(row => [String(row.id), row]));
    const catalogue = new Map();
    data.directory.forEach(row => Object.values(row.links).forEach(link => catalogue.set(String(link.item_id), {id: String(link.item_id), description: 'Item #' + link.item_id})));
    const error = document.getElementById('reprice-form-error');
    const refreshSelect = select => {
        const selected = select.value;
        while (select.options.length > 1) select.remove(1);
        catalogue.forEach(item => select.add(new Option(item.description + ' (#' + item.id + ')' + (item.rate ? ' · catálogo ' + item.rate : ''), item.id)));
        select.value = selected;
    };
    const selectItem = () => {
        const select = document.createElement('select');
        select.className = 'form-control reprice-item-select';
        select.add(new Option('Selecione um item revisado', ''));
        select.required = true;
        refreshSelect(select);
        return select;
    };
    const decimal = placeholder => {
        const input = document.createElement('input');
        input.className = 'form-control';input.inputMode = 'decimal';input.placeholder = placeholder;
        input.required = true;input.setAttribute('aria-label', placeholder);
        return input;
    };
    const remove = node => {
        const button = document.createElement('button');button.type = 'button';button.className = 'btn btn-default';button.textContent = 'Remover';
        button.addEventListener('click', () => node.remove());return button;
    };
    function addRule() {
        const row = document.createElement('div');row.className = 'form-group reprice-rule';
        row.append(selectItem(), decimal('Novo preço unitário da regra'), remove(row));
        document.getElementById('reprice-rules').append(row);
    }
    root.querySelectorAll('.reprice-item-select').forEach(refreshSelect);
    document.getElementById('reprice-add-rule').addEventListener('click', addRule);addRule();
    document.getElementById('reprice-search-button').addEventListener('click', async () => {
        const status = document.getElementById('reprice-search-status');status.textContent = 'Buscando…';
        try {
            const url = new URL(data.url, window.location.href);url.searchParams.set('catalog_search', document.getElementById('reprice-search').value);
            const response = await fetch(url, {credentials: 'same-origin', headers: {'Accept': 'application/json'}});
            if (!response.ok) throw new Error('catalog');
            const result = await response.json();if (!Array.isArray(result)) throw new Error('catalog');
            result.forEach(item => catalogue.set(String(item.id), item));
            root.querySelectorAll('.reprice-item-select').forEach(refreshSelect);
            status.textContent = result.length + ' itens encontrados. Se necessário, refine a busca para localizar outros itens.';
        } catch (_) {status.textContent = 'Não foi possível consultar o catálogo. Tente novamente.';}
    });
    root.querySelectorAll('.reprice-plus').forEach(button => button.addEventListener('click', () => {
        const container = button.parentElement.querySelector('.reprice-additions');
        const row = document.createElement('div');row.className = 'form-group reprice-addition';
        row.append(selectItem(), decimal('Quantidade somente desta inclusão'), remove(row));container.append(row);
    }));
    root.querySelectorAll('.reprice-review').forEach(button => button.addEventListener('click', () => {
        const tr = button.closest('tr');const row = rows.get(tr.dataset.schedule);const index = tr.dataset.line;
        const item = tr.querySelector('.reprice-link').value;
        if (!item) {error.textContent = 'Selecione o item correto antes de revisar o vínculo.';return;}
        if (!window.confirm('Confirma que esta linha pertence ao item selecionado? A página será recarregada; regras e seleções ainda não simuladas precisarão ser refeitas.')) return;
        const linkForm = document.getElementById('reprice-link-form');
        const values = {schedule_id: row.id, line_index: index, item_id: item, payload_hash: row.payload_hash, link_revision: row.links[index] ? row.links[index].revision : '0'.repeat(64)};
        Object.entries(values).forEach(([key, value]) => {linkForm.elements.namedItem(key).value = value;});
        linkForm.submit();
    }));
    function filter() {
        const item = document.getElementById('reprice-item-filter').value;
        const mode = document.getElementById('reprice-mode-filter').value;
        root.querySelectorAll('#reprice-grid tr[data-schedule]').forEach(tr => {
            const row = rows.get(tr.dataset.schedule);
            tr.hidden = Boolean(item && !Object.values(row.links).some(link => String(link.item_id) === item) || mode && !row.payment_modes.map(String).includes(mode));
        });
    }
    document.getElementById('reprice-item-filter').addEventListener('change', filter);
    document.getElementById('reprice-mode-filter').addEventListener('change', filter);
    const number = value => value.trim().replace(',', '.');
    form.addEventListener('submit', event => {
        error.textContent = '';
        const rules = Array.from(root.querySelectorAll('.reprice-rule')).map(row => ({item_id: row.querySelector('select').value, rate: number(row.querySelector('input').value)}));
        const targets = [];
        rows.forEach(row => {
            const elements = Array.from(root.querySelectorAll('#reprice-grid tr[data-schedule]')).filter(tr => tr.dataset.schedule === String(row.id));
            const lines = elements.filter(tr => tr.dataset.line !== undefined && tr.querySelector('.reprice-selected').checked).map(tr => ({index: Number(tr.dataset.line), item_id: row.links[tr.dataset.line].item_id, qty: number(tr.querySelector('.reprice-qty').value)}));
            const additions = elements.flatMap(tr => Array.from(tr.querySelectorAll('.reprice-addition'))).map(node => ({item_id: node.querySelector('select').value, qty: number(node.querySelector('input').value)}));
            if (lines.length || additions.length) targets.push({schedule_id: row.id, source_hash: row.source_hash, lines, additions});
        });
        const ids = new Set(rules.map(rule => rule.item_id));
        if (!rules.length || ids.size !== rules.length || !targets.length || targets.some(target => [...target.lines, ...target.additions].some(line => !ids.has(line.item_id)))) {
            event.preventDefault();error.textContent = 'Defina uma regra por item e selecione ao menos um alvo. Toda linha selecionada ou incluída precisa da regra correspondente.';return;
        }
        document.getElementById('reprice-input').value = JSON.stringify({rules, targets, timezone: document.getElementById('reprice-timezone').value, execute_local: document.getElementById('reprice-time').value, filters: {item: document.getElementById('reprice-item-filter').value, payment_mode: document.getElementById('reprice-mode-filter').value}});
    });
}());
