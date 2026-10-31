/* Same native controls; scoped mutations must carry a captured revision and POST. */
(function () {
  'use strict';
  document.addEventListener('click', function (event) {
    var target = event.target.closest('[data-native-payment-mode="1"]');
    if (!target) return;
    var previousChecked = !target.checked;
    var remove = target.classList.contains('_delete');
    var toggle = target.classList.contains('onoffswitch-checkbox');
    if (!remove && !toggle) return;
    event.preventDefault(); event.stopImmediatePropagation();
    if (target.dataset.busy === '1') return;
    if (remove && !window.confirm(window.app && app.lang && app.lang.confirm_action_prompt || 'Confirmar?')) return;
    var revision = target.getAttribute('data-revision');
    if (!/^[a-f0-9]{64}$/.test(revision || '')) throw new Error('Payment mode revision missing');
    var url = remove ? target.href : target.getAttribute('data-switch-url') + '/' + target.getAttribute('data-id') + '/' + (target.checked ? '1' : '0');
    target.dataset.busy = '1';
    $.post(url, {revision: revision}).done(function () {
      $('.table-payment-modes').DataTable().ajax.reload(null, false);
    }).fail(function () {
      if (toggle) target.checked = previousChecked;
      if (typeof alert_float === 'function') alert_float('danger', 'Não foi possível confirmar a alteração. Atualize a lista antes de tentar novamente.');
    }).always(function () { delete target.dataset.busy; });
  }, true);
}());
