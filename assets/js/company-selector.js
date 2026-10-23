/* Presentation only: choosing an authorized item uses the native POST form and submitter. */
(function () {
    'use strict';
    document.querySelectorAll('.company-selector').forEach(function (root) {
        if (root.dataset.companySelectorReady) return;
        root.dataset.companySelectorReady = 'true';
        var form = root.querySelector('.company-selector-form');
        var toggle = root.querySelector('[data-company-selector-toggle]');
        var choices = Array.from(root.querySelectorAll('[data-company-choice]'));
        if (!form || !toggle) return;
        var submitting = false;
        form.addEventListener('submit', function (event) {
            if (submitting) { event.preventDefault(); return; }
            submitting = true;
            toggle.disabled = true;
            // Do not disable the submitter: its company_id must remain in native form data.
            choices.forEach(function (choice) { choice.setAttribute('aria-disabled', 'true'); });
        });
        window.addEventListener('pageshow', function () {
            submitting = false;
            toggle.disabled = choices.length === 0;
            choices.forEach(function (choice) { choice.removeAttribute('aria-disabled'); });
        });
        root.addEventListener('keydown', function (event) {
            var keys = ['ArrowDown', 'ArrowUp', 'Home', 'End', 'Escape'];
            if (keys.indexOf(event.key) === -1 || submitting) return;
            var open = toggle.getAttribute('aria-expanded') === 'true';
            if (event.key === 'Escape') {
                if (!open) return;
                event.preventDefault(); event.stopPropagation();
                toggle.click(); toggle.focus(); return;
            }
            if (!choices.length) return;
            event.preventDefault(); event.stopPropagation();
            if (!open) toggle.click();
            var current = choices.indexOf(document.activeElement);
            var next = event.key === 'Home' ? 0 : event.key === 'End' ? choices.length - 1 : current < 0 ? (event.key === 'ArrowUp' ? choices.length - 1 : 0) : (current + (event.key === 'ArrowDown' ? 1 : -1) + choices.length) % choices.length;
            choices[next].focus();
        }, true);
    });
}());
