/* Presentation only. Context switches remain explicit POSTs handled by the server. */
(function () {
    'use strict';
    var toggle = document.querySelector('.company-menu-toggle');
    if (!toggle) { return; }
    var narrow = window.matchMedia('(max-width: 767px)');
    function setCollapsed(collapsed) {
        document.body.classList.toggle('company-menu-collapsed', collapsed);
        toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    }
    setCollapsed(narrow.matches);
    toggle.addEventListener('click', function () {
        setCollapsed(!document.body.classList.contains('company-menu-collapsed'));
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && narrow.matches) { setCollapsed(true); toggle.focus(); }
    });
}());
