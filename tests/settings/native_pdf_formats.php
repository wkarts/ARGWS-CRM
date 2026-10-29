<?php
require __DIR__.'/native_pdf_controller_setup.php';
$start=$GLOBALS['essentialChecks'];$legacy=$db->query('SELECT name,value FROM '.$p.'options ORDER BY name')->result_array();
foreach([$a=>'LETTER-LANDSCAPE',$b=>'A4-LANDSCAPE']as$owner=>$choice){
 $page=$pdfGet($owner);$initial=$pdfService->page($owner,7);
 essentialCheck($page['group']['view']==='admin/settings/includes/pdf'&&$page['native_settings_pdf_values']===$initial['values'],'PDF settings enter the original Settings group with the owned DTO');
 foreach($pdfFields as$field)essentialCheck($initial['values'][$field]==='A4-PORTRAIT','absent PDF format uses explicit product default');
 $input=array_fill_keys($pdfFields,$choice);$pdfPost($owner,$input,$initial['revision']);$page=$pdfGet($owner);$html=$pdfView->render($page);$saved=$pdfService->page($owner,7);
 essentialCheck(array_intersect_key($saved['values'],$input)===$input&&$GLOBALS['settingsRedirect']===['https://fixture.invalid/admin/settings?group=pdf',303],'original form commits own exact format batch and redirects to the official group');
 essentialCheck(substr_count($html,'value="LETTER-LANDSCAPE"')===5&&str_contains($html,'data-native-settings-pdf')&&!str_contains($html,'<form'),'original PDF partial exposes five document formats inside the shared form');
 essentialCheck(!str_contains($html,'synthetic-cipher:')&&!str_contains($html,'smtp_password'),'PDF form never renders mail secrets');
 foreach([[$pdfFields[0]=>'A3'],[$pdfFields[0]=>'letter-portrait'],[$pdfFields[0]=>['A4-PORTRAIT']],[$pdfFields[0]=>"A4-PORTRAIT\r\n"],['pdf_format_payment'=>'LETTER-LANDSCAPE'],['smtp_password'=>'forged'],[$pdfFields[0]=>'']]as$bad){$pdfError(fn()=>$pdfPost($owner,$bad,$saved['revision']),422,'unknown or malformed PDF preference is rejected');essentialCheck($pdfService->page($owner,7)===$saved,'invalid batch leaves all owned settings and assets unchanged');}
 $pdfError(fn()=>$pdfPost($owner,$input,$saved['revision'],'pdf',false),403,'PDF form retains native CSRF enforcement');
 $pdfError(fn()=>$pdfPost($owner,$input,$initial['revision']),409,'stale original form cannot overwrite another save');
 $next=array_fill_keys($pdfFields,'LETTER-PORTRAIT');$db->failOn='INSERT INTO '.$p.'issuers_audit';try{$pdfError(fn()=>$pdfPost($owner,$next,$saved['revision']),503,'late audit failure rolls back complete PDF batch');}finally{$db->failOn=null;}
 essentialCheck($pdfService->page($owner,7)===$saved,'failed PDF write preserves all fields');
}
essentialCheck($db->query('SELECT name,value FROM '.$p.'options ORDER BY name')->result_array()===$legacy,'secondary PDF settings leave principal and infrastructure options untouched');
essentialThrows(fn()=>$pdfService->page($a,51),'native_settings_denied','assigned customer access never grants PDF settings view');
$before=$pdfService->page($a,7);$input=[$pdfFields[0]=>'A4-PORTRAIT','pdf_font'=>'freeserif','pdf_font_size'=>'14'];
$after=$pdfService->save($a,7,$before['revision'],$input,['signature'=>$png]);
essentialCheck($after['values']['pdf_font']==='freeserif'&&$after['values'][$pdfFields[0]]==='A4-PORTRAIT'&&CompanyPdfTypography::capture($db,$p,$a,'invoices')===['font'=>'freeserif','size'=>14],'PDF group composes formats, actual snapshot typography and own signature atomically');
$bad=$input;$bad['pdf_font']='dejavuserif';essentialThrows(fn()=>$pdfService->save($a,7,$after['revision'],$bad,['signature'=>'not-an-image']),'company_asset_invalid_image','invalid signature rolls back earlier typography and formats');essentialCheck($pdfService->page($a,7)===$after,'signature failure rolls back the entire PDF form');
echo 'Original PDF settings/controller/validation: '.($GLOBALS['essentialChecks']-$start).' added checks ('.$db->driver().').'.PHP_EOL;
