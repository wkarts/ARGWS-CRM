<?php
require __DIR__.'/relation_directory.php';
// The transport boundary is a spy here; directory storage/ACL is the actual service above.
if(!class_exists('CompanyRequestScope',false)){
final class CompanyRequestScope
{
    public static bool $allow=true;
    public static function assertNativeMutation($ci):void{if(!self::$allow)throw new DomainException('native_post_csrf_denied');}
    public static function context():object{return new class{public function issuerId():int{return 41;}public function staffId():int{return 12;}};}
}
}else{throw new RuntimeException('fixture scope collision');}
require_once APPPATH.'services/NativeCompanyRelationEndpoints.php';
$ci=new stdClass();$ci->db=$db;$ci->input=new class{public array $data=[];public function post($k){return $this->data[$k]??null;}};
$ci->input->data=['type'=>'contact','q'=>'','contact_userid'=>'701'];essentialCheck(NativeCompanyRelationEndpoints::read($ci)[0]['id']==='801','native POST contract reaches real scoped contact reader');
$ci->input->data=['type'=>'contact','contact_userid'=>'701','extra'=>['client_id'=>'702']];settingThrows(fn()=>NativeCompanyRelationEndpoints::read($ci),'native_relation_invalid','conflicting client authorities denied');
$ci->input->data=['type'=>'contact','tickets_contacts'=>['1']];settingThrows(fn()=>NativeCompanyRelationEndpoints::read($ci),'native_relation_invalid','array coercion denied');
$ci->input->data=['type'=>'customer','rel_id'=>'702'];essentialCheck(NativeCompanyRelationEndpoints::read($ci)[0]['name']==='B','explicit shared customer still uses authorized reader');
$ci->input->data=['type'=>'contact','rel_id'=>'802'];settingThrows(fn()=>NativeCompanyRelationEndpoints::read($ci),'native_relation_denied','same shared customer cannot expose contact via endpoint');
$ci->input->data=['type'=>'unknown'];settingThrows(fn()=>NativeCompanyRelationEndpoints::read($ci),'native_relation_provider_required','unknown domain cannot call global helper');
CompanyRequestScope::$allow=false;settingThrows(fn()=>NativeCompanyRelationEndpoints::read($ci),'native_post_csrf_denied','transport guard occurs before provider dispatch');
echo "Native relation endpoint 7 checks PASS (transport spy, real directory)\n";
