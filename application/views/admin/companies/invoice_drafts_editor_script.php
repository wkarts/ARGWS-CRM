<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<script>
(()=>{
  const renumber=(field,index)=>{
    const previous=field.dataset.itemIndex;field.dataset.itemIndex=index;
    field.querySelector('legend').textContent='Item '+(index+1);
    field.querySelectorAll('input,textarea,select').forEach(input=>{
      const old=input.id;input.id=old.replace('item-'+previous+'-','item-'+index+'-');
      input.name=input.name.replace('[items]['+previous+']','[items]['+index+']');
      field.querySelectorAll('label').forEach(label=>{if(label.htmlFor===old)label.htmlFor=input.id;});
    });
  };
  document.addEventListener('change',event=>{if(!event.target.matches('[data-invoice-line-choice]'))return;const field=event.target.closest('[data-item-index]'),choice=event.target.value;field.querySelector('input[name$="[is_optional]"]').value=choice==='required'?'0':'1';field.querySelector('input[name$="[is_selected]"]').value=choice==='unselected'?'0':'1';});
  document.addEventListener('click',event=>{
    const add=event.target.closest('[data-invoice-add]');
    if(add){
      const items=document.getElementById(add.dataset.invoiceAdd);if(items.children.length>=100)return;
      const copy=items.firstElementChild.cloneNode(true);renumber(copy,items.children.length);
      copy.querySelectorAll('input,textarea').forEach(input=>input.value=input.name.endsWith('[qty]')?'1.00':input.name.endsWith('[is_optional]')?'0':input.name.endsWith('[is_selected]')?'1':(input.name.endsWith('[rate]')&&input.name.includes('[taxes]')?'0.00':''));copy.querySelector('[data-invoice-line-choice]').value='required';
      copy.querySelectorAll('[data-tax-row]').forEach((row,i)=>{if(i>0)row.remove();});
      items.appendChild(copy);copy.querySelector('input').focus();return;
    }
    const taxAdd=event.target.closest('[data-add-tax]');
    if(taxAdd){
      const field=taxAdd.closest('[data-item-index]'),rows=field.querySelector('[data-taxes]');if(rows.children.length>=10)return;
      const copy=rows.firstElementChild.cloneNode(true),index=rows.children.length;
      copy.querySelectorAll('input').forEach(input=>{
        const old=input.id;input.id=old.replace(/tax-\d+-/,'tax-'+index+'-');
        input.name=input.name.replace(/\[taxes\]\[\d+\]/,'[taxes]['+index+']');
        copy.querySelectorAll('label').forEach(label=>{if(label.htmlFor===old)label.htmlFor=input.id;});
        input.value=input.name.endsWith('[rate]')?'0.00':'';
      });rows.appendChild(copy);copy.querySelector('input').focus();
    }
  });
})();
</script>
