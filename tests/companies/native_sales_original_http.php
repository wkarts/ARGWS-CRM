<?php
/** Actual controllers, views and SQL writers. Framework/scope I/O are explicit seams; scope admission has its own real gate test. */
require __DIR__.'/native_sales_original_view.php';
function show_error($message,$status=500){$GLOBALS['CI']->output->set_status_header($status)->set_output((string)$message);}
function redirect($url,$method='auto',$status=null){$GLOBALS['CI']->output->set_status_header($status??302)->set_header('Location: '.$url);}
class AdminController{public function __construct(){}public function __get($key){return $GLOBALS['CI']->$key;}public function __isset($key){return isset($GLOBALS['CI']->$key);}}
class OriginalSalesHttpLoader{
    public array $views=[];
    public function model($name){throw new RuntimeException('Ambient model is forbidden in original Sales HTTP fixture: '.$name);}
    public function view($path,$data=[],$return=false){$this->views[]=[$path,$data];$loader=new OriginalSalesViewLoader($data,'admin/'.$path);return $loader->view($path,[],$return);}
}
require_once APPPATH.'controllers/admin/Estimates.php';require_once APPPATH.'controllers/admin/Proposals.php';require_once APPPATH.'controllers/admin/Invoice_items.php';
$GLOBALS['CI']->input=new class{public string $verb='GET';public bool $ajax=true;public array $posts=[],$gets=[];public function method($upper=false){return$upper?$this->verb:strtolower($this->verb);}public function post($key=null,$filter=false){return$key===null?$this->posts:($this->posts[$key]??null);}public function get($key=null,$filter=false){return$key===null?$this->gets:($this->gets[$key]??null);}public function is_ajax_request(){return$this->ajax;}};
$GLOBALS['CI']->output=new class{public int $status=200;public string $body='',$mime='';public array $headers=[];public function set_header($value){$this->headers[]=$value;return$this;}public function set_status_header($value){$this->status=$value;return$this;}public function set_output($value){$this->body=$value;return$this;}public function set_content_type($value){$this->mime=$value;return$this;}};
$GLOBALS['CI']->security=new class{public function get_csrf_token_name(){return 'synthetic_framework_csrf';}};
$GLOBALS['CI']->load=new OriginalSalesHttpLoader();$http=$GLOBALS['CI']->input;$out=$GLOBALS['CI']->output;
$reset=function(string $verb='GET',array $posts=[],array $gets=[])use($http,$out){$http->verb=$verb;$http->ajax=true;$http->posts=$posts;$http->gets=$gets;$out->status=200;$out->body='';$out->mime='';$out->headers=[];$GLOBALS['CI']->load->views=[];};
$transport=fn(array $wire)=>$wire+['_company_context'=>'synthetic-company-'.CompanyRequestScope::$issuer,'_native_company_csrf'=>'synthetic-scope-csrf','synthetic_framework_csrf'=>'synthetic-framework-value'];
$snapshot=function()use($db,$p){$rows=[];foreach(['company_sales_documents','company_sales_revisions','company_sales_projection','company_custom_field_values','company_work_tags','company_work_tag_links','issuers_sequences','issuers_audit']as$table)$rows[$table]=$db->query('SELECT * FROM '.$p.$table.' ORDER BY 1')->result_array();return$rows;};
$httpStart=$GLOBALS['essentialChecks'];$httpCreated=[];
foreach(['estimates'=>'estimate','proposals'=>'proposal']as$kind=>$type){
    $controller=$kind==='estimates'?new Estimates():new Proposals();$form=$originalForms[$kind];
    $reset('GET',[],[$kind==='estimates'?'customer_id':'rel_id'=>(string)$ca]+($kind==='proposals'?['rel_type'=>'customer']:[]));$controller->$type();
    essentialCheck($out->status===200&&str_contains($out->body,'id="'.$type.'-form"')&&in_array('Cache-Control: private, no-store',$out->headers,true),'actual '.$kind.' GET renders production nested form under selected company');
    $getHtml=$out->body;$data=$GLOBALS['CI']->load->views[0][1];$wire=originalSalesWire($kind,$data,$ca,$originalHeaders[$type],$originalLineField,$originalChoiceField);
    $reset('HEAD');$controller->$type();essentialCheck($out->status===200&&$out->body==='','actual '.$kind.' HEAD rechecks form data without response body');
    $before=$snapshot();$reset('POST',$wire);$controller->$type();essentialCheck($out->status===403&&$snapshot()===$before,'actual '.$kind.' rejects missing native CSRF before all writes');
    $bad=$transport($wire);$bad['_company_context']='synthetic-company-'.$b;$reset('POST',$bad);$controller->$type();essentialCheck($out->status===409&&$snapshot()===$before,'actual '.$kind.' rejects stale company context before all writes');
    $bad=$transport($wire);$bad['_native_sales_id']=NativeCompanyResourceId::encode('company_'.$type,$originalSources[$kind]);$reset('POST',$bad);$controller->$type();essentialCheck($out->status===422&&$snapshot()===$before,'actual '.$kind.' route and hidden document identity must match');
    $reset('POST',$transport($wire));$controller->$type();$saved=json_decode($out->body,true,512,JSON_THROW_ON_ERROR);
    essentialCheck($out->status===200&&($saved['success']??false)&&preg_match('~^/admin/'.$kind.'/list_~',$saved['url']??'')===1,'actual '.$kind.' POST strips framework/context transport and returns native list preview target ('.$out->status.':'.$out->body.')');
    $source=NativeCompanyResourceId::decode($type,$saved['id'])['source_id'];$httpCreated[$kind]=$saved['id'];$canonical=$sales->document($a,12,$source);
    essentialCheck($canonical['context']['recipient']['name']==='Private A'&&CompanyInvoiceAmounts::minor((string)$canonical['total'])===10500,'actual '.$kind.' POST persists recalculated canonical document, not a fixture response');
    $after=$snapshot();$reset('POST',$transport($wire));$controller->$type();essentialCheck(json_decode($out->body,true)['id']===$saved['id']&&$snapshot()===$after,'actual '.$kind.' duplicate HTTP request reuses durable result without side effects');
    $reset('POST',$transport($wire));$http->ajax=false;$controller->$type();essentialCheck($out->status===303&&in_array('Location: '.$saved['url'],$out->headers,true),'actual '.$kind.' non-AJAX success uses 303 PRG with the same canonical target');
    $reset('GET');$controller->$type($saved['id']);essentialCheck($out->status===200&&str_contains($out->body,'name="_native_sales_reason"')&&str_contains($out->body,'Private line value'),'actual '.$kind.' GET edit restores real persisted fields and reviewed-edit controls');
    $edit=$GLOBALS['CI']->load->views[0][1];$change=originalSalesWire($kind,$edit,$ca,$originalHeaders[$type],$originalLineField,$originalChoiceField);$change['newitems']['4']['rate']='55';$change['subtotal']='110.00';$change['total']='115.50';
    $reset('POST',$transport($change));$controller->$type($saved['id']);essentialCheck($out->status===200&&CompanyInvoiceAmounts::minor((string)$sales->document($a,12,$source)['total'])===11550,'actual '.$kind.' edit POST applies original form fields via existing reviewed writer');
    $change['_native_sales_request_key']=hash('sha256','http-stale-'.$kind);$after=$snapshot();$reset('POST',$transport($change));$controller->$type($saved['id']);essentialCheck($out->status===409&&$snapshot()===$after,'actual '.$kind.' stale edit returns conflict without overwriting newer state');
    CompanyRequestScope::$issuer=$b;$reset();$controller->$type($saved['id']);essentialCheck($out->status===403&&!str_contains($out->body,'Private line value'),'actual '.$kind.' direct foreign document cannot render form contents');CompanyRequestScope::$issuer=$a;
    $reset('DELETE');$controller->$type($saved['id']);essentialCheck($out->status===405&&in_array('Allow: GET, HEAD, POST',$out->headers,true),'actual '.$kind.' form rejects unsupported methods explicitly');
    CompanyRequestScope::$sealed=false;$reset();$controller->$type();essentialCheck($out->status===403,'actual '.$kind.' form requires sealed route authority');CompanyRequestScope::$sealed=true;
}

// The same existing catalogue routes now bind to the Sales editor, without granting items.view or invoice privileges.
$item=NativeCompanyResourceId::encode('company_item',1);$foreignItem=NativeCompanyResourceId::encode('company_item',2);
IssuerTransaction::run($db,$p,$fields->tableNames(),fn()=>$fields->saveInOwnedTransaction($a,7,'items_pr',$item,[$originalLineField=>'HTTP catalogue field',$originalChoiceField=>['A']]));
$catalogue=new Invoice_items();
foreach(['estimates'=>'estimate','proposals'=>'proposal']as$kind=>$type){
    $query=['native_editor'=>$type,'native_sales_id'=>'','native_sales_revision'=>''];$reset('GET',[],$query);ob_start();try{$catalogue->get_item_by_id($item);$itemJson=ob_get_clean();}catch(Throwable$error){ob_end_clean();throw$error;}$row=json_decode($itemJson,true,512,JSON_THROW_ON_ERROR);
    essentialCheck($row['description']==='Original catalogue item'&&str_contains($row['custom_fields_html'],'HTTP catalogue field')&&!str_contains($itemJson,'Private B'),'actual '.$kind.' item callback uses real typed catalogue/custom-field projection');
    $reset('POST',$transport($query+['q'=>'Original']));ob_start();$catalogue->search();$found=json_decode(ob_get_clean(),true,512,JSON_THROW_ON_ERROR);essentialCheck(count($found)===1&&str_contains(json_encode($found),'Original catalogue item'),'actual '.$kind.' search uses original route with Sales authority only');
    $reset('GET',[],$query);essentialThrows(fn()=>$catalogue->get_item_by_id($foreignItem),'company_catalog_denied','actual '.$kind.' item route rejects another company catalogue ID');
    $reset('POST',$query+['q'=>'Original']);essentialThrows(fn()=>$catalogue->search(),'native_company_csrf_invalid','actual '.$kind.' search POST retains CSRF requirement');
    $edit=$originalForms[$kind]->data($a,12,$httpCreated[$kind]);$query['native_sales_id']=$httpCreated[$kind];$query['native_sales_revision']=str_repeat('f',64);$reset('GET',[],$query);essentialThrows(fn()=>$catalogue->get_item_by_id($item),'company_sales_stale','actual '.$kind.' callback cannot use a stale parent revision');
}

// Customer callback uses actual owned profile/contacts, with shared identity projecting no private owner data.
foreach(['contacts','contact_permissions']as$table)if(!$db->table_exists($p.$table)){
    preg_match('/CREATE TABLE `tbl'.preg_quote($table,'/').'` \(.*?\) ENGINE=InnoDB[^;]*;/s',$native,$match);if(!$match)throw new RuntimeException('native fixture missing '.$table);
    $ddl=str_replace('`tbl'.$table.'`','`'.$p.$table.'`',$match[0]);$ddl=str_replace('`id` int NOT NULL,','`id` '.($db->driver()==='mysql'?'int NOT NULL AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT').',',$ddl);
    if($db->driver()==='sqlite'){$ddl=preg_replace('/ CHARACTER SET [A-Za-z0-9_]+ COLLATE [A-Za-z0-9_]+/','',$ddl);$ddl=preg_replace('/enum\([^)]*\)/','varchar(50)',$ddl);}$db->query($ddl);
}
CompanyClientProfileSchema::ensure($db,$p);unset($db->data_cache['table_names']);
$db->query('UPDATE '.$p.'clients SET address=?,city=?,phonenumber=?,billing_street=?,shipping_street=?,default_currency=1 WHERE userid=?',['Owned customer address','Owned city','Owned phone','Owned billing','Owned shipping',$ca]);
$db->query('UPDATE '.$p.'clients SET address=?,billing_street=? WHERE userid=?',['Private B address','Private B billing',$cb]);
$db->query('INSERT INTO '.$p.'contacts(userid,firstname,lastname,email,phonenumber,is_primary,active,datecreated,password) VALUES(?,?,?,?,?,1,1,?,?)',[$ca,'Owned','Contact','owned@example.invalid','Owned contact phone','2026-01-01 00:00:00','synthetic-hash-not-a-credential']);
$customers->setSharing($b,7,$cb,[$a]);
foreach(['estimates'=>'estimate','proposals'=>'proposal']as$kind=>$type){
    $controller=$kind==='estimates'?new Estimates():new Proposals();$callback=fn($client)=>$kind==='estimates'?$controller->client_change_data((string)$client):$controller->get_relation_data_values((string)$client,'customer');
    $query=['native_sales_id'=>'','native_sales_revision'=>''];$reset('GET',[],$query);$callback($ca);$owned=json_decode($out->body,true,512,JSON_THROW_ON_ERROR);
    essentialCheck($out->status===200&&$owned['billing_shipping'][0]['billing_street']==='Owned billing'&&$owned['email']==='owned@example.invalid'&&$owned['address']==='Owned customer address'&&$owned['client_currency']===1,'actual '.$kind.' client selection restores owned original address/contact/currency fields');
    essentialCheck($owned['billable_tasks']===[]&&$owned['merge_info']===''&&$owned['expenses_bill_info']===''&&!str_contains($out->body,'synthetic-hash'),'actual '.$kind.' callback does not expand invoice/task/expense access or expose contact credentials');
    $reset('GET',[],$query);$callback($cb);$shared=json_decode($out->body,true,512,JSON_THROW_ON_ERROR);essentialCheck($out->status===200&&$shared['to']==='Private B'&&!$shared['profile_available']&&$shared['address']===''&&$shared['email']===''&&$shared['billing_shipping'][0]['billing_street']===''&&!str_contains($out->body,'Private B address'),'actual '.$kind.' shared customer exposes label only, never owner-private profile');
    $reset('HEAD',[],$query);$callback($ca);essentialCheck($out->status===200&&$out->body==='','actual '.$kind.' client HEAD suppresses body');
    $reset('POST',$transport($query));$callback($ca);essentialCheck($out->status===405&&in_array('Allow: GET, HEAD',$out->headers,true),'actual '.$kind.' client callback rejects POST');
    $db->query('DELETE FROM '.$p.'issuers_resource_permissions WHERE issuer_id=? AND staff_id=12 AND feature=? AND capability=?',[$a,$kind,'create']);$reset('GET',[],$query);$callback($ca);essentialCheck($out->status===403&&!str_contains($out->body,'owned@example.invalid'),'actual '.$kind.' revoked editor grant closes customer callback');$db->query('INSERT INTO '.$p.'issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,12,?,?)',[$a,$kind,'create']);
}
$customers->setSharing($b,7,$cb,[]);$reset('GET',[],['native_sales_id'=>'','native_sales_revision'=>'']);(new Estimates())->client_change_data((string)$cb);essentialCheck($out->status===403,'revoked customer sharing closes original callback immediately');
echo 'Original Sales actual controller/view/POST/customer/catalogue: '.($GLOBALS['essentialChecks']-$httpStart).' added checks ('.$db->driver().'); framework and sealed-scope I/O are explicit seams.'.PHP_EOL;
