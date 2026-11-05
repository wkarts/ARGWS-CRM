<?php
/** Real review template -> browser-style successful controls -> actual admin command -> SQL copy. */
require __DIR__.'/native_sales_original_http.php';
require_once APPPATH.'services/NativeCompanySalesAdminHttp.php';
$metadataStart=$GLOBALS['essentialChecks'];$csrf=str_repeat('e',64);
$GLOBALS['CI']->session=new class($csrf){private array $values;public function __construct($csrf){$this->values=['company_sales_csrf'=>$csrf];}public function userdata($key){return$this->values[$key]??null;}public function set_userdata($key,$value){$this->values[$key]=$value;}};
$GLOBALS['CI']->load=new class{public array $views=[];public function view($path,$data=[],$return=false){$this->views[]=[$path,$data];return'';}}; // Failure presentation only; the command itself and review template are real.
function originalReviewWire(array $review,string $mode,string $kind,string $csrf):array{
    $mutations_ready=true;$detail=null;$mutation_review=$review;$mutation_mode=$mode;$clients=[['userid'=>$review['payload']['client_id'],'company'=>'Private A']];$currencies=[['id'=>1,'name'=>'BRL']];
    $esc=fn($value)=>htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');$url=fn($suffix='')=>'/admin/'.$kind.$suffix;
    $hidden=function($action)use($csrf){echo form_hidden('action',$action).form_hidden('company_sales_csrf',$csrf).form_hidden('request_key',hash('sha256','actual-review-wire-'.$action.'-'.$GLOBALS['essentialChecks']));};
    ob_start();try{include APPPATH.'views/admin/includes/native_sales/mutations.php';$html=ob_get_clean();}catch(Throwable$error){ob_end_clean();throw$error;}
    $dom=new DOMDocument();libxml_use_internal_errors(true);$dom->loadHTML('<?xml encoding="UTF-8">'.$html);libxml_clear_errors();$xpath=new DOMXPath($dom);$pairs=[];
    foreach($xpath->query('//form//input[@name and not(@disabled)]|//form//textarea[@name and not(@disabled)]|//form//select[@name and not(@disabled)]')as$node){
        if(in_array($node->getAttribute('type'),['checkbox','radio'],true)&&!$node->hasAttribute('checked'))continue;
        $value=$node->nodeName==='textarea'?$node->textContent:$node->getAttribute('value');
        if($node->nodeName==='select'){$options=$xpath->query('option[@selected]',$node);if(!$options->length)$options=$xpath->query('option[1]',$node);$value=$options->item(0)?->getAttribute('value')??'';}
        $pairs[]=rawurlencode($node->getAttribute('name')).'='.rawurlencode($value);
    }
    parse_str(implode('&',$pairs),$wire);return[$wire,$html];
}
foreach(['estimates'=>'estimate','proposals'=>'proposal']as$kind=>$type){
    $id=NativeCompanyResourceId::decode($type,$httpCreated[$kind])['source_id'];$review=$sales->review($a,12,$id,'copy');
    [$wire,$html]=originalReviewWire($review,'copy_confirm',$kind,$csrf);$metadata=json_decode($wire['invoice_native_metadata'],true,512,JSON_THROW_ON_ERROR);
    essentialCheck($metadata['native_project']===null&&$metadata['native_item_fields']===$review['payload']['native_item_fields']&&$metadata['native_custom_fields']===$review['payload']['native_custom_fields']&&is_int($metadata['native_status']),'actual '.$kind.' copy confirmation preserves typed metadata, null and nested line values');
    essentialCheck(!isset($wire['invoice']['native_item_fields'])&&!str_contains($html,'value="<script>'),'actual '.$kind.' metadata has one escaped JSON transport, without ambiguous recursive input types');
    $wire['review']['reason']='Reviewed native metadata wire';$wire['review']['copy_confirmed']='1';$reset('POST',$transport($wire));
    $controller=$kind==='estimates'?new Estimates():new Proposals();$controller->index();
    essentialCheck($out->status===303,'actual '.$kind.' copy command accepts successful controls from its real confirmation template ('.$out->status.':'.$out->body.')');
    $receipt=$db->query('SELECT result_document_id FROM '.$p.'company_sales_copy_requests WHERE request_key=?',[$wire['request_key']])->row_array();$copy=(int)($receipt['result_document_id']??0);$copied=$sales->review($a,12,$copy,'edit');
    essentialCheck($copy!==$id&&$copied['payload']['due_date']===null&&$copied['payload']['native_item_fields']===$review['payload']['native_item_fields']&&$copied['payload']['native_custom_fields']===$review['payload']['native_custom_fields'],'actual '.$kind.' reviewed copy persists full original metadata after an HTML round trip');
    $edit=$sales->review($a,12,$copy,'edit');[$editWire,$editHtml]=originalReviewWire($edit,'edit',$kind,$csrf);$editWire['review']['reason']='Reviewed metadata edit';$editWire['review']['edit_confirmed']='1';$editWire['invoice']['items'][0]['rate']='60.00';$reset('POST',$transport($editWire));$controller->index();
    essentialCheck($out->status===303&&$sales->review($a,12,$copy,'edit')['payload']['native_item_fields']===$review['payload']['native_item_fields'],'actual '.$kind.' retained revision editor preserves line metadata when correcting price');
    essentialCheck(!preg_match('/id="sales-review-due"[^>]*required/',$editHtml),'actual '.$kind.' reviewed form keeps optional expiry optional');
    $before=$snapshot();$bad=$wire;$bad['request_key']=hash('sha256','invalid-json-'.$kind);$bad['invoice_native_metadata']='{invalid';$reset('POST',$transport($bad));(new NativeCompanySalesAdminHttp($controller))->index($kind,null,false,'workspace');essentialCheck($out->status===422&&$snapshot()===$before,'malformed '.$kind.' metadata JSON is a validation error without writes');
    $bad=$wire;$bad['request_key']=hash('sha256','overlap-json-'.$kind);$bad['invoice']['native_status']='1';$reset('POST',$transport($bad));(new NativeCompanySalesAdminHttp($controller))->index($kind,null,false,'workspace');essentialCheck($out->status===422&&$snapshot()===$before,'ambiguous '.$kind.' duplicated native metadata is rejected');
}
echo 'Original Sales metadata actual review/HTML/admin/SQL round trip: '.($GLOBALS['essentialChecks']-$metadataStart).' added checks ('.$db->driver().').'.PHP_EOL;
