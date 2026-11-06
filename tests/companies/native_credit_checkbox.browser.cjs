'use strict';
// Actual PHP field renderer and native compiled CSS. Synthetic field; no session or database.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const {execFileSync}=require('node:child_process'),{chromium}=require('playwright');
const root=path.resolve(__dirname,'../..');
const php=`define('BASEPATH',true);$esc=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$field=['id'=>'1','name'=>'Line choices','type'=>'checkbox','required'=>0,'options'=>'One "<literal>", Two & literal','display_inline'=>0];
echo '<table class="table items"><tbody>';for($i=0;$i<2;$i++){$fieldName='credit[native_metadata][items]['.$i.'][custom_fields][values][1]';echo '<tr><td class="custom_field">';require 'application/views/admin/credit_notes/native_custom_field.php';echo '</td></tr>';}echo '</tbody></table>';`;
const html=execFileSync(process.env.PHP_BIN||'php',['-r',php],{cwd:root,encoding:'utf8'});
(async()=>{
 const browser=await chromium.launch({headless:true,...(process.env.CHROMIUM_PATH?{executablePath:process.env.CHROMIUM_PATH}:{})});
 try{for(const width of [1280,390]){
  const page=await browser.newPage({viewport:{width,height:800}});page.setDefaultTimeout(2000);
  await page.route('**/*',route=>route.abort());
  await page.setContent('<!doctype html><html><head><meta charset="utf-8">'+['assets/builds/vendor-admin.css','assets/css/style.min.css'].map(p=>'<style>'+fs.readFileSync(path.join(root,p),'utf8')+'</style>').join('')+'</head><body><form>'+html+'</form></body></html>');
  const controls=page.locator('input[type=checkbox]');assert.equal(await controls.count(),4);
  assert.equal(await page.locator('td.custom_field > .form-group > label').first().isVisible(),false,'column heading label remains hidden by native item-table styling');
  for(let n=0;n<4;n++){
   const control=controls.nth(n),id=await control.getAttribute('id'),label=page.locator('label[for="'+id+'"]');
   assert.equal(await label.isVisible(),true,'checkbox option must remain visible inside native item cells');
   await label.click();assert.equal(await control.isChecked(),true,'real pointer click on label selects its associated input');
  }
  assert.deepEqual(await page.locator('form').evaluate(form=>[...new FormData(form).values()]),['One "<literal>"','Two & literal','One "<literal>"','Two & literal']);
  await page.close();
 }}finally{await browser.close();}
 console.log('Native credit checkbox: real PHP + compiled CSS, desktop/mobile visibility, pointer selection and literal form values passed. Not authenticated HTTP.');
})().catch(error=>{console.error(error);process.exitCode=1;});
