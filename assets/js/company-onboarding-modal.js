(function () {
    'use strict';
    // Reveal the active step inside its own horizontal scroller, never scroll the page.
    const navigation = Array.from(document.querySelectorAll('.company-onboarding-modal-body .company-onboarding-steps, .company-onboarding-modal-body .company-identity-sections'));
    function revealCurrent(nav) {
        const focused = document.activeElement;
        const current = focused !== nav && nav.contains(focused) ? focused : nav.querySelector('[aria-current="step"]');
        if (!current || nav.scrollWidth <= nav.clientWidth) return;
        const item = current.getBoundingClientRect(), box = nav.getBoundingClientRect();
        if (item.left < box.left) nav.scrollLeft -= box.left - item.left;
        else if (item.right > box.right) nav.scrollLeft += item.right - box.right;
    }
    navigation.forEach(nav => {
        nav.tabIndex = 0;
        nav.addEventListener('focusin', () => revealCurrent(nav));
        nav.addEventListener('click', () => requestAnimationFrame(() => revealCurrent(nav)));
    });
    const revealNavigation = () => navigation.forEach(revealCurrent);
    window.addEventListener('load', revealNavigation);
    window.addEventListener('resize', revealNavigation);
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(revealNavigation);
    revealNavigation();
    const close = () => window.parent.postMessage({type:'company-onboarding-close'},window.location.origin);
    document.querySelectorAll('[data-wizard-close]').forEach(button => button.addEventListener('click',close));
    // Keyboard events in an iframe do not bubble to the parent's Bootstrap modal.
    document.addEventListener('keydown',event => {
        if (event.key === 'Escape') {event.preventDefault(); close();}
    });
    document.addEventListener('submit',event => {
        if(event.target.checkValidity()) event.target.querySelectorAll('button[type="submit"]').forEach(button => {button.disabled=true;});
    });
}());
