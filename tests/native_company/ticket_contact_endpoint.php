<?php
declare(strict_types=1);
require __DIR__.'/ticket_contacts.php';
$endpoint=getenv('CRM_RELATION_ENDPOINT_FILE')?:__DIR__.'/../../application/services/NativeCompanyRelationEndpoints.php';require_once $endpoint;
function db_prefix(){return'tbl';}
final class CompanyRequestScope{public static bool$allowed=true;public static int$issuer=2,$actor=17;public static function assertNativeMutation($ci):void{if(!self::$allowed)throw new DomainException('native_context_denied');}public static function context(){return new class{function issuerId(){return CompanyRequestScope::$issuer;}function staffId(){return CompanyRequestScope::$actor;}};}}
final class TicketContactInput{function __construct(public array$data){}function post($key){return$this->data[$key]??null;}}
$db->query("INSERT INTO tblissuers_resource_permissions VALUES(2,17,'tickets','create')");$ci=(object)['db'=>$db,'input'=>new TicketContactInput(['type'=>'contacts','tickets_contacts'=>'1','q'=>'Own Two'])];
$rows=NativeCompanyRelationEndpoints::read($ci);kc($rows===[['id'=>'2','name'=>'Own Two','subtext'=>'a@example.invalid']],'real endpoint dispatches real PDO-backed ticket capability');
CompanyRequestScope::$allowed=false;$before=count($db->queries);$deny(fn()=>NativeCompanyRelationEndpoints::read($ci));kc(count($db->queries)===$before,'CSRF/context rejection before any query');CompanyRequestScope::$allowed=true;
$ci->input->data=['type'=>'contacts','tickets_contacts'=>'1','rel_id'=>'3'];$deny(fn()=>NativeCompanyRelationEndpoints::read($ci));
$ci->input->data=['type'=>'customer','tickets_contacts'=>'1'];$deny(fn()=>NativeCompanyRelationEndpoints::read($ci));
$ci->input->data=['type'=>'contacts','tickets_contacts'=>'1','rel_id'=>'2','contact_userid'=>'3'];$deny(fn()=>NativeCompanyRelationEndpoints::read($ci));
$ci->input->data=['type'=>'contacts','tickets_contacts'=>'1'];CompanyRequestScope::$issuer=3;$deny(fn()=>NativeCompanyRelationEndpoints::read($ci));
echo "Real native relation endpoint + PDO TicketContacts: dispatch, scoped query, boundary rejection before SQL, foreign selection/client mismatch and wrong-type flag passed. Context boundary is a test spy, not HTTP/bootstrap validation.\n";
