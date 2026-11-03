<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyStaffPreferences.php';
require_once __DIR__.'/CompanySettingsStorage.php';
/** Existing Settings/misc form, same option formats and company-owned table preferences. */
final class NativeCompanyMiscSettings
{
 public function __construct(private $db,private string$p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
 public static function defaults():array{return['view_contract_only_logged_in'=>'0','dropbox_app_key'=>'','media_max_file_size_upload'=>'10','newsfeed_maximum_files_upload'=>'10','limit_top_search_bar_results_to'=>'10','delete_activity_log_older_then'=>'1','show_setup_menu_item_only_on_hover'=>'0','show_help_on_setup_menu'=>'1','use_minified_files'=>'1','save_last_order_for_tables'=>'0','show_table_export_button'=>'to_all','tables_pagination_limit'=>'25','staff_members_create_inline_lead_status'=>'1','staff_members_create_inline_lead_source'=>'1','staff_members_create_inline_customer_groups'=>'1','staff_members_create_inline_ticket_services'=>'1','staff_members_save_tickets_predefined_replies'=>'1','staff_members_create_inline_contract_types'=>'1','staff_members_create_inline_expense_categories'=>'1'];}
 public static function fields():array{return array_merge(array_keys(self::defaults()),['default_staff_role']);}
 public static function validateExtras(array$values):void
 {
  if(array_diff(array_keys($values),array_keys(self::defaults())))throw new DomainException('native_misc_settings_invalid');
  foreach($values as$key=>$value){if(!is_string($value))throw new DomainException('native_misc_settings_invalid');
   $valid=match($key){'dropbox_app_key'=>strlen($value)<=191&&preg_match('/^[A-Za-z0-9_-]*$/D',$value)===1,'show_table_export_button'=>in_array($value,['to_all','only_admins','hide'],true),'tables_pagination_limit'=>preg_match('/^[1-9][0-9]{0,8}$/D',$value)===1,'media_max_file_size_upload','newsfeed_maximum_files_upload','limit_top_search_bar_results_to','delete_activity_log_older_then'=>preg_match('/^(0|[1-9][0-9]{0,8})$/D',$value)===1,default=>in_array($value,['0','1'],true)};
   if(!$valid)throw new DomainException('native_misc_settings_invalid');
  }
 }
 public function tables():array{return array_values(array_unique(array_merge((new NativeCompanyStaffPreferences($this->db,$this->p))->tables(),['options','user_meta'])));}
 private function authorize(int$i,int$a,string$cap='view'):void{$acl=new IssuerResourceAccess($this->db,$this->p);if(!$acl->can($i,$a,'settings','view')||!$acl->can($i,$a,'settings',$cap))throw new DomainException('native_misc_settings_denied');}
 private function capture(int$i,int$a):array
 {
  IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());$this->authorize($i,$a);$storage=new CompanySettingsStorage($this->db,$this->p);$primary=$storage->isPrimary($i);$values=[];
  foreach(self::defaults()as$key=>$default){if($primary&&!$storage->exists($i,$key))throw new DomainException('native_misc_settings_invalid');$values[$key]=$storage->exists($i,$key)?$storage->get($i,$key):$default;}self::validateExtras($values);
  $role=(new NativeCompanyStaffPreferences($this->db,$this->p))->page($i,$a);$values+=$role['values'];
  $services=$storage->exists($i,'services')?$storage->get($i,'services'):'1';if(!in_array($services,['0','1'],true))throw new DomainException('native_misc_settings_invalid');
  $this->authorize($i,$a);return['values'=>$values,'roles'=>$role['roles'],'role_revision'=>$role['revision'],'services'=>$services,'revision'=>hash('sha256',json_encode([$i,$primary,$values,$role['revision'],$services],JSON_THROW_ON_ERROR)),'assets'=>null,'custom_fields'=>[]];
 }
 public function page(int$i,int$a):array{return IssuerTransaction::run($this->db,$this->p,$this->tables(),function()use($i,$a){$this->authorize($i,$a);(new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($i,$a);return$this->capture($i,$a);});}
 public function save(int$i,int$a,string$revision,array$values):array
 {
  $this->authorize($i,$a,'edit');return IssuerTransaction::run($this->db,$this->p,$this->tables(),function()use($i,$a,$revision,$values){
   $this->authorize($i,$a,'edit');(new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($i,$a);$old=$this->capture($i,$a);
   if(!preg_match('/^[a-f0-9]{64}$/D',$revision)||!hash_equals($old['revision'],$revision))throw new DomainException('native_settings_stale');
   // The original services switch hides only this optional control, never silently clears it.
   if($old['services']==='0'&&!array_key_exists('staff_members_create_inline_ticket_services',$values))$values['staff_members_create_inline_ticket_services']=$old['values']['staff_members_create_inline_ticket_services'];
   $keys=array_keys($values);$expected=self::fields();sort($keys);sort($expected);if($keys!==$expected)throw new DomainException('native_misc_settings_invalid');
   $extras=array_intersect_key($values,self::defaults());self::validateExtras($extras);
   (new NativeCompanyStaffPreferences($this->db,$this->p))->saveInOwnedTransaction($i,$a,$old['role_revision'],['default_staff_role'=>$values['default_staff_role']]);
   (new CompanySettingsStorage($this->db,$this->p))->updateNativeSettingsInOwnedTransaction($i,$a,'misc',$extras);
   if($values['save_last_order_for_tables']!==$old['values']['save_last_order_for_tables']){
    $primary=(new IssuerResourceAccess($this->db,$this->p))->primaryId()===$i;
    $where=$primary?"meta_key LIKE ? AND meta_key NOT LIKE ?":"meta_key LIKE ?";$args=$primary?['%-table-last-order','issuer:%']:['issuer:'.$i.':%-table-last-order'];
    IssuerStorage::query($this->db,'DELETE FROM '.$this->p.'user_meta WHERE '.$where,$args);
    if(IssuerStorage::query($this->db,'SELECT umeta_id FROM '.$this->p.'user_meta WHERE '.$where,$args)->result_array())throw new RuntimeException('native_misc_settings_unconfirmed');
   }
   $now=$this->capture($i,$a);foreach($values as$key=>$value)if($now['values'][$key]!==$value)throw new RuntimeException('native_misc_settings_unconfirmed');$this->authorize($i,$a,'edit');return$now;
  });
 }
}
