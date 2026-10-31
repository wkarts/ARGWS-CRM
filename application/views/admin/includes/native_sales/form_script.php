<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<script>
(()=>{
  const renumber=(field,index)=>{
    const previous=field.dataset.itemIndex;field.dataset.itemIndex=index;
    field.querySelector('legend').textContent='Item '+(index+1);
    field.querySelectorAll('input,textarea,select').forEach(input=>{
      const old=input.id;if(old)input.id=old.replace('item-'+previous+'-','item-'+index+'-');
      if(input.name)input.name=input.name.replace('[items]['+previous+']','[items]['+index+']');
      field.querySelectorAll('label').forEach(label=>{if(old&&label.htmlFor===old)label.htmlFor=input.id;});
    });
  };
  const choice=select=>{
    const field=select.closest('[data-item-index]');if(!field)return;
    field.querySelector('input[name$="[is_optional]"]').value=select.value==='required'?'0':'1';
    field.querySelector('input[name$="[is_selected]"]').value=select.value==='unselected'?'0':'1';
  };
  document.addEventListener('change',event=>{
    if(event.target.matches('[data-invoice-line-choice]'))choice(event.target);
  });
  document.addEventListener('click',event=>{
    const add=event.target.closest('[data-invoice-add]');
    if(add){
      const items=document.getElementById(add.dataset.invoiceAdd);if(items.children.length>=100)return;
      const copy=items.firstElementChild.cloneNode(true);renumber(copy,items.children.length);
      copy.querySelectorAll('input').forEach(input=>{
        if(input.name.endsWith('[qty]'))input.value='1.00';
        else if(input.name.endsWith('[is_optional]'))input.value='0';
        else if(input.name.endsWith('[is_selected]'))input.value='1';
        else input.value=input.name.endsWith('[rate]')&&input.name.includes('[taxes]')?'0.00':'';
      });
      copy.querySelectorAll('textarea').forEach(input=>input.value='');
      copy.querySelectorAll('[data-invoice-line-choice]').forEach(select=>{select.value='required';choice(select);});
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
