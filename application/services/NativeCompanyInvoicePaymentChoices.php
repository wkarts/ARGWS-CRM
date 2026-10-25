<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyPaymentModes.php';
/** Selection permission only. No account is selected and no charge/public checkout is promised or created. */
final class NativeCompanyInvoicePaymentChoices
{
 private const PROVIDERS=['asaas'=>['asaas','Asaas'],'banco_brasil'=>['banco_brasil','Banco do Brasil'],'gerencianet_gateway'=>['gerencianet','EFÍ Pay']];
 public function __construct(private $db,private string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
 private function authorize(int $i,int $a):void{$acl=new IssuerResourceAccess($this->db,$this->p);if(!$acl->can($i,$a,'invoices','view')||(!$acl->can($i,$a,'invoices','create')&&!$acl->can($i,$a,'invoices','edit')))throw new DomainException('native_invoice_payment_mode_denied');}
 private function modules():array{return IssuerStorage::query($this->db,'SELECT module_name,active,installed_version FROM '.$this->p.'modules WHERE module_name IN (?,?,?) ORDER BY module_name',array_keys(self::PROVIDERS))->result_array();}
 private function provider(string $module,int $i,int $a,bool $owned=false):bool
 {
  if($module==='asaas'){
   require_once APPPATH.'../modules/asaas/libraries/Asaas/CompanyPreferences.php';require_once APPPATH.'../modules/asaas/libraries/Asaas/CompanyService.php';
   $preferences=(new Asaas\CompanyPreferences($this->db,$this->p))->page($i,$a);$environment=$preferences['active_environment'];
   if(!$preferences['ready']||!in_array($environment,['production','sandbox'],true))return false;
   $profile=$preferences['profiles'][$environment];if(!in_array('1',[$profile['allow_pix'],$profile['allow_boleto'],$profile['allow_card']],true))return false;
   foreach((new Asaas\CompanyService($this->db,$this->p))->accounts($i,$a)as$account)if($account['environment']===$environment&&(int)$account['enabled']===1&&$account['verified_at']!==null&&$account['secret_configured']===true)return true;
   return false;
  }
  if($module==='gerencianet_gateway'){
   require_once APPPATH.'../modules/gerencianet_gateway/services/CompanyAccounts.php';
   // Both environments remain explicit candidates; never infer production or choose the first account.
   $accounts=new Efi\CompanyAccounts($this->db,$this->p);
   $rows=$owned?IssuerStorage::query($this->db,'SELECT id,environment,revision,enabled FROM '.$this->p.'efi_company_accounts WHERE issuer_id=? ORDER BY id',[$i])->result_array():$accounts->choices($i,$a);
   foreach($rows as$account)if((int)$account['enabled']===1&&in_array($account['environment'],['production','sandbox'],true)){
    try{$read=function()use($accounts,$i,$a,$account):void{$accounts->accountInOwned($i,$a,(int)$account['id'],$account['revision']);};if($owned)$read();else IssuerTransaction::run($this->db,$this->p,Efi\CompanyAccounts::tables(),$read);return true;}catch(DomainException $revoked){}
   }
   return false;
  }
  require_once APPPATH.'../modules/banco_brasil/libraries/BancoBrasil/ApiService.php';
  $service=new BancoBrasil\ApiService($this->db,$this->p);$profiles=$owned?$service->permissionCandidatesInOwned($i,$a):$service->permissionCandidates($i,$a);
  foreach($profiles as$profile)if($profile['credential_configured']===true)return true;
  return false;
 }
 public function listing(int $i,int $a):array
 {
  $this->authorize($i,$a);$modules=$this->modules();$modes=(new NativeCompanyPaymentModes($this->db,$this->p))->listing($i,$a,'invoices',false);
  foreach($modes as&$mode){$mode['selection_available']=true;$mode['checkout_ready']=false;$mode['selection_reason']=null;}unset($mode);
  foreach($modules as$module){if((int)$module['active']!==1)continue;[$id,$name]=self::PROVIDERS[$module['module_name']];
   try{$available=$this->provider($module['module_name'],$i,$a);}catch(DomainException $denied){$available=false;}
   $modes[]=['id'=>$id,'name'=>$name,'active'=>1,'selected_by_default'=>0,'is_online'=>true,'selection_available'=>$available,'selection_reason'=>$available?null:'Configuração ou autorização indisponível nesta empresa.','checkout_ready'=>false];
  }
  $this->authorize($i,$a);if($modules!==$this->modules())throw new DomainException('native_invoice_payment_modes_changed');return$modes;
 }
 public static function selection($selected):array
 {
  if(!is_array($selected)||!array_is_list($selected)||count($selected)>100)throw new DomainException('native_invoice_payment_mode_invalid');$out=[];
  foreach($selected as$id){if(!is_string($id)&&!is_int($id))throw new DomainException('native_invoice_payment_mode_invalid');$id=(string)$id;if(!in_array($id,['asaas','banco_brasil','gerencianet'],true)&&!preg_match('/^[1-9][0-9]{0,15}$/D',$id))throw new DomainException('native_invoice_payment_mode_invalid');if(in_array($id,$out,true))throw new DomainException('native_invoice_payment_mode_invalid');$out[]=$id;}return$out;
 }
 public static function validationTables(array $selected):array
 {
  $selected=self::selection($selected);$tables=['issuers_lock','issuers_registry','issuers_installation_identity','issuers_staff','issuers_resource_permissions','staff','modules','company_payment_modes','payment_modes'];
  if(in_array('asaas',$selected,true)){require_once APPPATH.'../modules/asaas/libraries/Asaas/CompanyPreferences.php';$tables=array_merge($tables,Asaas\CompanyPreferences::tables());}
  if(in_array('gerencianet',$selected,true)){require_once APPPATH.'../modules/gerencianet_gateway/services/CompanyAccounts.php';$tables=array_merge($tables,Efi\CompanyAccounts::tables());}
  if(in_array('banco_brasil',$selected,true)){require_once APPPATH.'../modules/banco_brasil/libraries/BancoBrasil/ApiService.php';$tables=array_merge($tables,BancoBrasil\ApiService::permissionCandidateTables());}
  return array_values(array_unique($tables));
 }
 /** Revalidation in the exact invoice transaction; does not create a payment, origin or checkout. */
 public function validateInOwned(int $i,int $a,array $selected):array
 {
  $selected=self::selection($selected);IssuerTransaction::assertOwned($this->db,$this->p,self::validationTables($selected));$this->authorize($i,$a);$modules=array_column($this->modules(),null,'module_name');
  foreach($selected as$id){$module=null;foreach(self::PROVIDERS as$name=>$definition)if($definition[0]===$id)$module=$name;
   if($module!==null){if((int)($modules[$module]['active']??0)!==1||!$this->provider($module,$i,$a,true))throw new DomainException('native_invoice_payment_mode_denied');}
   else{(new NativeCompanyPaymentModes($this->db,$this->p))->get($i,$a,$id,'invoices',false);}
  }
  $this->authorize($i,$a);return$selected;
 }
 public function validate(int $i,int $a,array $selected):array
 {
  if(!array_is_list($selected)||count($selected)>100)throw new DomainException('native_invoice_payment_mode_invalid');$modes=$this->listing($i,$a);$out=[];
  foreach($selected as$id){if(!is_string($id)&&!is_int($id))throw new DomainException('native_invoice_payment_mode_invalid');$match=null;foreach($modes as$mode)if((string)$mode['id']===(string)$id){$match=$mode;break;}if(!$match||!$match['selection_available']||in_array((string)$id,$out,true))throw new DomainException('native_invoice_payment_mode_denied');$out[]=(string)$id;}
  return$out;
 }
}
