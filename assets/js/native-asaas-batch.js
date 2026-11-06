(function (root, factory) {
    'use strict';
    var api = factory();
    if (typeof module === 'object' && module.exports) module.exports = api;
    if (!root.document) return;
    var form = root.document.getElementById('asaas-batch-form');
    if (!form) return;
    var busy = false, stop = false;
    var button = form.querySelector('[data-batch-start]'), cancel = form.querySelector('[data-batch-stop]');
    cancel.addEventListener('click', function () { stop = true; cancel.disabled = true; });
    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        if (busy) return;
        var mode = form.querySelector('[name=batch_mode]').value;
        var ids = Array.from(form.querySelectorAll('[name=batch_origin]:checked')).map(function (input) { return input.value; });
        var confirmed = form.querySelector('[name=confirm]').checked;
        var log = form.querySelector('[data-batch-log]');
        function message(text) { var line = root.document.createElement('li'); line.textContent = text; log.appendChild(line); }
        log.replaceChildren();
        if (!ids.length || ids.length > 20 || (mode === 'reconcile' && !confirmed)) { message('Selecione de 1 a 20 origens e confirme a aplicação, se escolhida.'); return; }
        busy = true; stop = false; button.disabled = true; cancel.disabled = false;
        try {
            await api.run(ids, mode, async function (id) {
                var data = new URLSearchParams();
                form.querySelectorAll('input[type=hidden]').forEach(function (input) { data.set(input.name, input.value); });
                data.set('action', mode === 'reconcile' ? 'batch_reconcile' : 'batch_compare');
                data.set('origin_id', id); if (confirmed) data.set('confirm', '1');
                var response = await root.fetch(form.action, {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8', 'Accept': 'application/json'}, body: data.toString()});
                if (!response.headers.get('content-type')?.includes('application/json')) throw new Error('unconfirmed');
                return {status: response.status, body: await response.json()};
            }, function () { return stop; }, function (id, outcome) {
                message('Origem #' + id + ': ' + ({compared: 'consulta concluída, sem baixa', reconciled: 'recebimento confirmado pelo escritor nativo', review_required: 'revisão necessária; nenhuma aplicação confirmada', unconfirmed: 'resultado incerto; lote interrompido, confira antes de repetir'}[outcome]));
            });
            message(stop ? 'Parado após concluir o item em andamento.' : 'Processamento da seleção encerrado. Confira o resultado de cada item.');
        } catch (_) { message('Lote interrompido. Consulte o histórico antes de repetir um resultado incerto.'); }
        finally { busy = false; button.disabled = false; cancel.disabled = true; }
    });
}(typeof window !== 'undefined' ? window : globalThis, function () {
    'use strict';
    return {run: async function (ids, mode, send, stopped, report) {
        if (!Array.isArray(ids) || ids.length < 1 || ids.length > 20 || new Set(ids).size !== ids.length || ids.some(function (id) { return typeof id !== 'string' || !/^[1-9][0-9]{0,9}$/.test(id); }) || !['compare', 'reconcile'].includes(mode)) throw new Error('invalid_selection');
        for (var id of ids) {
            if (stopped()) break;
            var result;
            try { result = await send(id); } catch (_) { report(id, 'unconfirmed'); throw new Error('unconfirmed'); }
            if (result.status === 422 && result.body?.ok === false && result.body.outcome === 'review_required') { report(id, 'review_required'); continue; }
            var expected = mode === 'compare' ? 'compared' : 'reconciled';
            if (result.status !== 200 || result.body?.ok !== true || String(result.body.item?.origin_id) !== id || result.body.item?.outcome !== expected) { report(id, 'unconfirmed'); throw new Error('unconfirmed'); }
            report(id, expected);
        }
    }};
}));
