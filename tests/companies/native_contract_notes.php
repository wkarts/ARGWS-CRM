<?php
require __DIR__.'/contracts_controller.php';
require dirname(__DIR__).'/helpers/native_auxiliary_schema_fixture.php';
// Pure rendering adapters; authorization, transaction, storage and controller are real.
function staff_profile_image($id,$classes=[]){return '<span data-staff="'.e($id).'"></span>';}
function _dt($value){return$value;}
function render_textarea($name,$label,$value){return '<textarea name="'.e($name).'">'.e($value).'</textarea>';}
$start=$GLOBALS['essentialChecks'];$out=$GLOBALS['CI']->output;$token=$GLOBALS['CI']->session->userdata('native_contracts_csrf');CompanyRequestScope::$issuer=$b;
$reset=function(array$post=[],string$verb='POST')use($http,$out){$http->verb=$verb;$http->gets=[];$http->posts=$post;$out->status=200;$out->body='';};
$command=function(string$label)use($contracts,$b,$httpContract,$canonicalId,$token){return['contract_id'=>$canonicalId,'revision'=>(string)$contracts->get($b,7,$httpContract)['revision'],'request_key'=>hash('sha256',$label),'native_contracts_csrf'=>$token,'description'=>'Private <script>contract note'];};
$post=$command('original-contract-note');$before=$contracts->get($b,7,$httpContract);$reset($post);$cc->add_note($canonicalId);
$read=fn()=>$db->query('SELECT * FROM '.$p.'company_notes WHERE issuer_id=? AND rel_type=? AND rel_id=? ORDER BY id',[$b,'contract',$canonicalId])->result_array();$rows=$read();
essentialCheck($out->status===303&&count($rows)===1&&$rows[0]['description']===$post['description']&&$rows[0]['source_kind']==='company_contract'&&(int)$rows[0]['source_id']===$httpContract,'Original contract add_note stores an internal typed note and redirects with 303');
$reset($post);$cc->add_note($canonicalId);essentialCheck($out->status===303&&$read()===$rows,'Original contract note replay creates no duplicate');
$reset(array_replace($post,['description'=>'Changed intent']));$cc->add_note($canonicalId);essentialCheck($out->status===409&&$read()===$rows,'Reused note key rejects another payload with 409');
$reset([],'GET');$cc->get_notes($canonicalId);essentialCheck($out->status===200&&str_contains($out->body,'Private &lt;script&gt;contract note')&&!str_contains($out->body,'Private <script>'),'Original get_notes renders actual stored content safely');
$reset([],'HEAD');$cc->get_notes($canonicalId);essentialCheck($out->status===200&&$out->body==='','Contract notes HEAD returns no response body');
$reset(array_replace($command('stale-contract-note'),['revision'=>'1']));$cc->add_note($canonicalId);essentialCheck($out->status===409&&$read()===$rows,'Internal note requires current parent contract revision');
$reset($command('note-csrf'));unset($http->posts['native_contracts_csrf']);$cc->add_note($canonicalId);essentialCheck($out->status===403&&$read()===$rows,'Contract note keeps dedicated CSRF');
$reset($command('foreign-contract-note'));CompanyRequestScope::$issuer=$a;$cc->add_note($canonicalId);essentialCheck($out->status===403&&$read()===$rows,'Foreign contract note rejected before storage');CompanyRequestScope::$issuer=$b;
$reset($command('note-rollback'));$db->failOn='INSERT INTO '.$p.'issuers_audit';$cc->add_note($canonicalId);$db->failOn=null;essentialCheck($out->status===503&&$read()===$rows,'Late audit failure rolls back note creation');
essentialCheck($contracts->get($b,7,$httpContract)===$before,'Private notes preserve issued contract identity, value and snapshot');
echo 'Original contract notes: '.($GLOBALS['essentialChecks']-$start).' additional controller/storage checks passed ('.$db->driver().').'.PHP_EOL;
