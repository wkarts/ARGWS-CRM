<?php
// Exercises the existing sales writer, reviewed edits/copies, portal, real PDF and queue.
require __DIR__.'/sales_preferences.php';
require_once APPPATH.'services/NativeCompanySalesSettings.php';
CompanySalesMutationsSchema::ensure($db,$p);
$forms=new NativeCompanySalesSettings($db,$p);$start=$GLOBALS['essentialChecks'];
// Keep a historical format-2 source to prove it remains byte-stable after new settings exist.
$legacy=$sales->create($a,7,'estimates',array_replace($salesInput,['client_id'=>$ca]),hash('sha256','format-legacy-before'));
$legacyDto=$sales->document($a,7,$legacy);$legacyNumber=$legacyDto['formatted_number'];
$settings->mutate($a,7,'update','number_padding_prefixes','3');
$documents=[];
foreach(['1','2','3','4'] as $format){
    $page=$forms->page($a,7,'estimates');$values=$page['values'];$values['estimate_prefix']='FORM-'.$format.'-';$values['estimate_number_format']=$format;
    $forms->save($a,7,'estimates',$page['revision'],$values);
    $payload=array_replace($salesInput,['client_id'=>$ca,'date'=>'2026-01-31','due_date'=>'2026-02-10']);
    $id=$sales->create($a,7,'estimates',$payload,hash('sha256','native-estimate-format-'.$format));$dto=$sales->document($a,7,$id);$number=(string)$dto['number'];
    $expected=SalesNumberPresentation::format($number,$format,$values['estimate_prefix'],$payload['date'],3);
    essentialCheck($dto['context']['sales_preferences']['format']===4&&$dto['formatted_number']===$expected,'each native number format uses own prefix, date and padding');
    $row=array_values(array_filter($sales->page($a,7,'estimates')['items'],fn($r)=>(int)$r['id']===$id))[0];
    essentialCheck($row['formatted_number']===$expected,'staff list uses the same number');
    $opts=$sales->relationOptions($a,7,'estimates',$expected);essentialCheck(count(array_filter($opts,fn($r)=>$r['name']===$expected))===1,'relation selector uses the same number');
    $partial=match($format){'1'=>substr($expected,0,-1),'2'=>$values['estimate_prefix'].'2026','3'=>substr($expected,0,-1),'4'=>substr($expected,0,-5)};
    $partialRows=$sales->relationOptions($a,7,'estimates',$partial);
    essentialCheck(count(array_filter($partialRows,fn($r)=>(int)$r['id']===$id))===1,'relation search matches partial displayed number, including date components');
    essentialCheck($sales->relationOptions($b,7,'estimates',$partial)===[],'formatted partial search cannot cross company boundary');
    $pdf=CompanySalesPdf::prepare($dto)->Output('','S');essentialCheck(str_starts_with($pdf,'%PDF-')&&strlen($pdf)>1000,'actual PDF renders new snapshot format');
    $sales->decide($a,7,$id,'accepted');$rows=$portalSales->page($sessionA,$a,'estimates');$portalRow=array_values(array_filter($rows,fn($r)=>(int)$r['id']===$id))[0];
    essentialCheck($portalRow['formatted_number']===$expected&&!isset($portalRow['payload_json'],$portalRow['input_hash']),'portal shows same number without leaking payload');
    $state=$delivery->state($a,7,$id);$mailKey=hash('sha256','native-format-mail-'.$format);$queue=$delivery->queue($a,7,$id,$contactA,$state['revision'],$mailKey,$base,$decrypt);$mail=$db->query('SELECT * FROM '.$p.'mail_queue WHERE id=?',[$queue])->row_array();
    essentialCheck(str_contains($mail['message'].' '.serialize(unserialize($mail['headers'],['allowed_classes'=>false])),$expected),'queued notification uses same format');
    $delivery->assertDispatch((object)$mail,$mailKey);$documents[$format]=$id;
}
essentialCheck($sales->document($a,7,$legacy)['formatted_number']===$legacyNumber,'old format snapshot is unchanged');
// A tampered persisted date must never affect a portal label or queued message.
$tampered=$documents['1'];$originalPayload=$db->query('SELECT payload_json FROM '.$p.'company_sales_documents WHERE id=?',[$tampered])->row()->payload_json;
$broken=json_decode($originalPayload,true,32,JSON_THROW_ON_ERROR);$broken['date']='2029-01-31';$db->query('UPDATE '.$p.'company_sales_documents SET payload_json=? WHERE id=?',[json_encode($broken,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),$tampered]);
essentialThrows(fn()=>$portalSales->page($sessionA,$a,'estimates'),'company_portal_sales_unavailable','portal rejects a tampered date with unchanged input hash');
essentialThrows(fn()=>$delivery->state($a,7,$tampered),'company_sales_delivery_denied','delivery rejects a tampered date before presentation');
$db->query('UPDATE '.$p.'company_sales_documents SET payload_json=? WHERE id=?',[$originalPayload,$tampered]);
// A revised draft derives year/month from its current validated date, not creation date.
$page=$forms->page($a,7,'estimates');$values=$page['values'];$values['estimate_number_format']='2';$values['estimate_prefix']='EDIT-';$forms->save($a,7,'estimates',$page['revision'],$values);
$payload=array_replace($salesInput,['client_id'=>$ca,'date'=>'2026-01-31','due_date'=>'2026-02-10']);$id=$sales->create($a,7,'estimates',$payload,hash('sha256','native-format-edit'));
$review=$sales->review($a,7,$id,'edit');$edited=array_replace($payload,['date'=>'2027-02-01','due_date'=>'2027-02-11']);
$sales->edit($a,7,$id,$edited,['source_revision'=>$review['source_revision'],'reason'=>'Synthetic year change','edit_confirmed'=>'1'],hash('sha256','native-format-edited'));
$editedDto=$sales->document($a,7,$id);essentialCheck(str_starts_with($editedDto['formatted_number'],'EDIT-2027/'),'reviewed date change updates official year-based number');
$copyReview=$sales->review($a,7,$id,'copy');$copy=$sales->copy($a,7,$id,$copyReview['payload'],['source_revision'=>$copyReview['source_revision'],'reason'=>'Synthetic copy','copy_confirmed'=>'1','destination_revision'=>$copyReview['destination_revision']],hash('sha256','native-format-copy'));
essentialCheck(str_starts_with($sales->document($a,7,$copy)['formatted_number'],'EDIT-2027/'),'reviewed copy preserves destination date and format');
// Typography and layout remain part of the same frozen number-presentation context.
$fonts=new CompanyPdfTypography($db,$p);$fonts->save($a,7,$fonts->page($a,7)['revision'],['pdf_font'=>'freesans','pdf_font_size'=>'14']);
$typographyPage=$forms->page($a,7,'estimates');$typographyValues=$typographyPage['values'];$typographyValues['estimate_number_format']='4';$typographyValues['estimate_prefix']='TYP-';$forms->save($a,7,'estimates',$typographyPage['revision'],$typographyValues);
$typedId=$sales->create($a,7,'estimates',$payload,hash('sha256','native-format-typography'));$typed=$sales->document($a,7,$typedId);
essentialCheck($typed['context']['sales_preferences']['format']===5&&$typed['context']['sales_preferences']['typography']===['font'=>'freesans','size'=>14],'typed number snapshot retains per-company typography');
$typedPdf=CompanySalesPdf::prepare($typed);essentialCheck($typedPdf->getFontFamily()==='freesans'&&abs($typedPdf->getFontSizePt()-14)<.01,'real PDF uses frozen typography with new number snapshot');
// SQL search must follow PHP str_pad semantics when the number is wider than configured padding.
$settings->mutate($a,7,'update','number_padding_prefixes','0');$paddingPage=$forms->page($a,7,'estimates');$paddingValues=$paddingPage['values'];$paddingValues['estimate_prefix']='ZERO-';$paddingValues['estimate_number_format']='1';$forms->save($a,7,'estimates',$paddingPage['revision'],$paddingValues);
$wideId=$sales->create($a,7,'estimates',$payload,hash('sha256','native-format-zero-padding'));$wide=$sales->document($a,7,$wideId);$wideExpected='ZERO-'.(string)$wide['number'];
essentialCheck($wide['formatted_number']===$wideExpected&&count(array_filter($sales->relationOptions($a,7,'estimates',$wideExpected),fn($r)=>(int)$r['id']===$wideId))===1,'zero padding never truncates numeric value in rendering or SQL search');
// Existing old documents use their historical defaults even after new preferences are saved.
essentialCheck($sales->document($a,7,$legacy)['context']===$legacyDto['context'],'historical preference shape remains untouched');
$forged=$editedDto['context'];$forged['sales_preferences']['number_format']='5';essentialThrows(fn()=>CompanySalesPreferences::presentation('estimates',$editedDto['number'],$forged,'',$editedDto['payload']['date']),'company_sales_invalid','forged number format rejected');
echo 'Native estimate number presentation: '.($GLOBALS['essentialChecks']-$start)." added checks. No external delivery.\n";
