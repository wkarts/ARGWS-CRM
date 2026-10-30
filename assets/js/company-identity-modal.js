(function () {
    'use strict';
    function initialize() {
        const modal=document.getElementById('company-edit-modal');
        if(modal&&window.jQuery){
            const frame=modal.querySelector('iframe');let opener=null,saved=false;
            document.querySelectorAll('[data-edit-company]').forEach(link=>link.addEventListener('click',event=>{
                const destination=new URL(link.href,location.href);
                if(destination.origin!==location.origin)return;
                event.preventDefault();opener=link;saved=false;
                frame.src=destination.href;
                window.jQuery(modal).modal({backdrop:'static',keyboard:true,show:true});
            }));
            window.addEventListener('message',event=>{
                if(event.origin!==location.origin||event.source!==frame.contentWindow||!event.data)return;
                if(event.data.type==='company-identity-saved')saved=true;
                if(event.data.type==='company-identity-close')window.jQuery(modal).modal('hide');
                if(event.data.type==='company-identity-csrf'&&typeof event.data.name==='string'&&typeof event.data.hash==='string'){
                    // Refresh only the framework token, never the company context or form action.
                    const name=event.data.name;
                    if(['_company_context','action','issuer_id','issuers_csrf','identity_revision'].includes(name))return;
                    document.querySelectorAll('form').forEach(form=>{
                        if(new URL(form.action||location.href,location.href).origin!==location.origin)return;
                        Array.from(form.elements).filter(input=>input.type==='hidden'&&input.name===name).forEach(input=>{input.value=event.data.hash;});
                    });
                }
            });
            window.jQuery(modal).on('hidden.bs.modal',()=>{
                frame.src='about:blank';if(saved)location.reload();else if(opener)opener.focus();
            });
        }
        const close=()=>{
            if(window.parent===window){
                const button=document.querySelector('[data-company-edit-return]');
                if(!button)return;
                const destination=new URL(button.dataset.companyEditReturn,location.href);
                if(destination.origin===location.origin)location.assign(destination.href);
            }else window.parent.postMessage({type:'company-identity-close'},location.origin);
        };
        document.querySelectorAll('[data-company-edit-close]').forEach(button=>button.addEventListener('click',close));
        if(document.querySelector('[data-company-edit-close]'))document.addEventListener('keydown',event=>{if(event.key==='Escape'){event.preventDefault();close();}});
    }
    if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',initialize);else initialize();
}());
