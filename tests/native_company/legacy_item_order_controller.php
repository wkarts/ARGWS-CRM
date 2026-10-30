<?php
require __DIR__.'/legacy_item_order.php';
if(!class_exists('AdminController',false)){#[AllowDynamicProperties]class AdminController{}}
if(!class_exists('CompanyRequestScope',false)){
    final class CompanyRequestScope
    {
        public static bool $primary=true;public static int $actor=7;public static int $issuer;
        public static function context():object{return new class{public function isPrimary():bool{return CompanyRequestScope::$primary;}public function staffId():int{return CompanyRequestScope::$actor;}public function issuerId():int{return CompanyRequestScope::$issuer;}};}
    }
}else throw new RuntimeException('scope fixture collision');
if(!function_exists('get_staff_user_id')){function get_staff_user_id(){return 7;}}
require_once APPPATH.'controllers/admin/Misc.php';
CompanyRequestScope::$issuer=$principal;$controller=(new ReflectionClass(Misc::class))->newInstanceWithoutConstructor();$controller->db=$db;
$controller->input=new class{public string $verb='POST';public array $data=[];public function method($upper=false){return$this->verb;}public function post($key){return$this->data[$key]??null;}};
$controller->input->data=['data'=>$pairs];$controller->update_ei_items_order('invoice');essentialCheck(true,'real Misc historical POST reaches restricted writer');
$controller->input->verb='GET';essentialThrows(fn()=>$controller->update_ei_items_order('invoice'),'native_legacy_order_denied','real Misc refuses legacy GET mutation');$controller->input->verb='POST';
CompanyRequestScope::$primary=false;essentialThrows(fn()=>$controller->update_ei_items_order('invoice'),'native_legacy_order_denied','real Misc refuses secondary context falling into legacy');CompanyRequestScope::$primary=true;
CompanyRequestScope::$actor=12;essentialThrows(fn()=>$controller->update_ei_items_order('invoice'),'native_legacy_order_denied','real Misc rejects session actor mismatch');CompanyRequestScope::$actor=7;
$controller->input->data=['data'=>[[(int)$foreign['id'],1]]];essentialThrows(fn()=>$controller->update_ei_items_order('invoice'),'native_legacy_order_denied','real Misc rejects named invoice from legacy native item ID');
echo "Native legacy order actual Misc 5 checks PASS (request boundary doubles)\n";
