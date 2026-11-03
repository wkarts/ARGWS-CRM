<?php
declare(strict_types=1);
require __DIR__.'/ticket_read.php';$endpoint=getenv('CRM_RELATION_ENDPOINT_FILE')?:__DIR__.'/../../application/services/NativeCompanyRelationEndpoints.php';require_once$endpoint;
function db_prefix(){return'tbl';}
final class CompanyRequestScope{public static bool$allowed=true;public static int$issuer=2,$actor=17;static function assertNativeMutation($ci){if(!self::$allowed)throw new DomainException('native_context_denied');}static function context(){return new class{function issuerId(){return CompanyRequestScope::$issuer;}function staffId(){return CompanyRequestScope::$actor;}};}}
$input=new class{public array$data=['type'=>'ticket','q'=>'Native email'];function post($key){return$this->data[$key]??null;}};$ci=(object)['db'=>$db,'input'=>$input];
$out=NativeCompanyRelationEndpoints::read($ci);kc(count($out)===2&&$out[0]['id']===$id,'real ticket endpoint consumes same owned reader');$input->data=['type'=>'ticket','rel_id'=>$contactId];kc(NativeCompanyRelationEndpoints::read($ci)[0]['id']===$contactId,'selected canonical ticket resolves');
CompanyRequestScope::$allowed=false;$before=count($db->queries);$deny(fn()=>NativeCompanyRelationEndpoints::read($ci));kc(count($db->queries)===$before,'boundary denied before SQL');CompanyRequestScope::$allowed=true;
$input->data=['type'=>'ticket','rel_id'=>'1'];$deny(fn()=>NativeCompanyRelationEndpoints::read($ci));$input->data=['type'=>'ticket','extra'=>['client_id'=>'2']];$deny(fn()=>NativeCompanyRelationEndpoints::read($ci));
$db->query('UPDATE tblissuers_installation_identity SET master_staff_id=NULL');$db->query('UPDATE tblstaff SET admin=0 WHERE staffid=17');$input->data=['type'=>'ticket','rel_id'=>$id];$deny(fn()=>NativeCompanyRelationEndpoints::read($ci));
echo "Real Misc ticket relation dispatch + SQLite reader: owned/selected, context before SQL, raw legacy and department revocation negatives passed; boundary is a spy, not HTTP admission.\n";
