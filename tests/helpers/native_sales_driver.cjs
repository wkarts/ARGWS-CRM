'use strict';
// Drives the shipped reference controls, calculators and HTTP commands. No DOM/response substitution.
const assert=require('node:assert/strict');
const {picker,postRecorded}=require('./native_invoice_driver.cjs');
function type(kind){assert(['estimates','proposals'].includes(kind));return kind==='estimates'?'estimate':'proposal';}
async function reveal(page,control){
  if(await control.count()!==1)return;
  const panels=await control.evaluate(element=>{const result=[];for(let node=element;node;node=node.parentElement)if(node.matches('.tab-pane:not(.active)')&&node.id)result.unshift(node.id);return result;});
  for(const id of panels){const tab=page.locator('a[data-toggle="tab"][href="#'+id+'"]');assert.equal(await tab.count(),1,'original preview has one tab for its panel');await tab.click();}
  if(!await control.isVisible()){
    const menu=control.locator('xpath=ancestor::*[contains(concat(" ",normalize-space(@class)," ")," dropdown-menu ")][1]');
    if(await menu.count())await menu.locator('xpath=..').locator(':scope > .dropdown-toggle').click();
  }
}
async function createForm(page,url,kind,client){
  const resource=type(kind),callback='/admin/'+kind+'/'+(kind==='estimates'?'client_change_data/':'get_relation_data_values/')+client+(kind==='proposals'?'/customer':'');
  // Observe rejection immediately so a failed navigation/assertion cannot be masked
  // by an unhandled callback waiter when the browser closes.
  const selected=page.waitForResponse(response=>new URL(response.url()).pathname===callback).then(response=>({response}),error=>({error}));
  const response=await page.goto(url+'/admin/'+kind+'/'+resource+(kind==='estimates'?'?customer_id=':'?rel_type=customer&rel_id=')+client);
  assert.equal(response.status(),200,'original '+kind+' form opens');
  const form=page.locator('#'+resource+'-form');await form.locator('tr.main textarea[name=description]').waitFor({state:'visible'});
  await page.waitForFunction(()=>typeof calculate_total==='function'&&typeof native_sales_original_init==='function');
  const selection=await selected;if(selection.error)throw selection.error;const customerResponse=selection.response;assert.equal(customerResponse.status(),200,'original customer callback is scoped and available');
  const currency=await form.locator('#currency option').evaluateAll(xs=>xs.find(x=>x.textContent.trim()==='BRL')?.value);assert(currency,'original '+kind+' form offers BRL');await picker(page,'#'+resource+'-form #currency',currency);
  if(kind==='proposals'){
    await form.locator('[name=subject]').fill('Synthetic original proposal');
    if(!await form.locator('[name=proposal_to]').inputValue())await form.locator('[name=proposal_to]').fill('Synthetic reviewed recipient');
    await form.locator('[name=email]').fill('synthetic-sales@example.invalid');
  }
  return form;
}
async function addLine(page,kind,{description,qty='1.00',rate,taxes=[],longDescription='',unit='',optional=false,selected=true}){
  const form=page.locator('#'+type(kind)+'-form'),before=await form.locator('tr.item').count();
  await form.locator('tr.main textarea[name=description]').fill(description);await form.locator('tr.main input[name=quantity]').fill(qty);await form.locator('tr.main input[name=rate]').fill(rate);
  await form.locator('tr.main textarea[name=long_description]').fill(longDescription);await form.locator('tr.main input[name=unit]').fill(unit);await picker(page,'#'+type(kind)+'-form tr.main select.tax',taxes);
  await form.locator('#main-optional').setChecked(optional);if(optional)await form.locator('#main-optional-choosen').setChecked(selected);
  await form.locator('tr.main button[onclick*="add_item_to_table"]').click();await form.locator('tr.item').nth(before).waitFor({state:'visible'});assert.equal(await form.locator('tr.item').count(),before+1,'original item action adds a real ordered line');
}
async function adjustments(page,kind,{discount='0',mode='',adjustment='0'}={}){
  const form=page.locator('#'+type(kind)+'-form');await picker(page,'#'+type(kind)+'-form [name=discount_type]',mode);
  if(mode)await form.locator('[name=discount_percent]').fill(discount);
  await form.locator('[name=adjustment]').fill(adjustment);await form.locator('[name=adjustment]').press('Tab');
}
async function preview(page,url,kind,id){
  const resource=type(kind),endpoint='/admin/'+kind+'/get_'+resource+'_data_ajax/'+id;
  const [response]=await Promise.all([page.waitForResponse(r=>new URL(r.url()).pathname===endpoint),page.goto(url+'/admin/'+kind+'/list_'+kind+'/'+id)]);
  assert.equal(response.status(),200,'original Sales split preview is authorized');await page.locator('[data-native-sales-preview="'+id+'"]').waitFor({state:'visible'});
}
async function settle(page){const target=new URL(page.url());if(!/^\/admin\/(?:estimates|proposals)(?:\/|$)/.test(target.pathname)||target.searchParams.has('review'))return;if(target.searchParams.has('document')||/\/list_(?:estimates|proposals)\/[1-9][0-9]*$/.test(target.pathname))await page.locator('[data-native-sales-preview]').waitFor({state:'visible'});}
async function open(page,target){const response=await page.goto(target);if(response?.ok())await settle(page);return response;}
async function save(page,url,kind){
  const resource=type(kind),form=page.locator('#'+resource+'-form'),endpoint=new URL(await form.getAttribute('action'),page.url()).pathname;
  // Pause only this real response long enough to copy its body before navigation.
  // continueResponse preserves original status, headers and bytes; no fulfill,
  // request rewrite, DOM substitution or replacement response is used.
  const session=await page.context().newCDPSession(page);let finish,timer;
  const diagnostics={previewStatus:null,pageErrors:[]};
  const onResponse=response=>{if(new RegExp('^/admin/'+kind+'/get_'+resource+'_data_ajax/[0-9]+$').test(new URL(response.url()).pathname))diagnostics.previewStatus=response.status();};
  const onError=error=>{const match=String(error.stack||'').match(/\/(assets\/[A-Za-z0-9_./-]+\.js)(?:\?[^\s:)]*)?:(\d+):(\d+)/);diagnostics.pageErrors.push(match?match[1]+':'+match[2]+':'+match[3]:'unavailable');};
  page.on('response',onResponse);page.on('pageerror',onError);
  const captured=new Promise(resolve=>{finish=resolve;timer=setTimeout(()=>resolve({error:new Error('Original Sales POST body was not observed')}),30000);});
  session.on('Fetch.requestPaused',async event=>{
    let observed;
    try{
      if(event.request.method==='POST'){
        const body=await session.send('Fetch.getResponseBody',{requestId:event.requestId});
        observed={result:JSON.parse(body.base64Encoded?Buffer.from(body.body,'base64').toString('utf8'):body.body)};
      }
    }catch(error){observed={error};}
    finally{
      try{await session.send('Fetch.continueResponse',{requestId:event.requestId});}
      catch(error){observed={error};}
      if(observed)finish(observed);
    }
  });
  try{
    await session.send('Fetch.enable',{patterns:[{urlPattern:new URL(endpoint,url).href,requestStage:'Response'}]});
    const [response]=await Promise.all([page.waitForResponse(r=>r.request().method()==='POST'&&new URL(r.url()).pathname===endpoint),page.waitForNavigation({waitUntil:'domcontentloaded'}),form.locator('.transaction-submit:not(.save-and-send):not(.save-and-send-later)').first().click()]);
    assert.equal(response.status(),200,'original '+kind+' AJAX POST commits');
    const observed=await captured;if(observed.error)throw observed.error;const result=observed.result;
    assert.equal(result.success,true);assert.match(result.id,/^[1-9][0-9]*$/);
    assert.equal(new URL(page.url()).pathname,'/admin/'+kind+'/list_'+kind+'/'+result.id);await page.locator('[data-native-sales-preview="'+result.id+'"]').waitFor({state:'visible'});
    const replay=await postRecorded(page.context(),response.request());assert.equal(replay.status(),200,'original request supports durable replay');assert.equal((await replay.json()).id,result.id);
    return{id:result.id,request:response.request()};
  }catch(error){console.error('Sales preview diagnostics '+JSON.stringify(diagnostics));throw error;}finally{page.off('response',onResponse);page.off('pageerror',onError);clearTimeout(timer);await session.detach();}
}
async function action(page,name){const button=page.getByRole('button',{name,exact:true});await reveal(page,button);await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),button.click()]);}
function command(page,action){assert(['convert','accepted','declined','cancelled'].includes(action));return page.locator('form').filter({has:page.locator('input[name=action][value="'+action+'"]')}).locator('button[type=submit]');}
module.exports={createForm,line:addLine,adjustments,save,preview,action,reveal,command,settle,open};
