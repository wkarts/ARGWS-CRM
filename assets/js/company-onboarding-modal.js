(function () {
    'use strict';
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
