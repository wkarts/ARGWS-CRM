<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyInvoiceEditorItems.php';
require_once __DIR__.'/NativeCompanyInvoiceSequencePreferences.php';
require_once __DIR__.'/NativeCompanyInvoicePreferences.php';
require_once __DIR__.'/NativeCompanyInvoiceAgents.php';
require_once __DIR__.'/NativeCompanyInvoicePaymentChoices.php';
require_once __DIR__.'/NativeCompanyCatalogUi.php';
require_once __DIR__.'/CompanyClients.php';
require_once __DIR__.'/NativeCompanyInvoiceCommunicationState.php';
/** Data/commands for the original invoice form, not an alternate view or ambient model. */
final class NativeCompanyInvoiceForm
{
 public function __construct(private $db,private string $p){}
 private function authorize(int $i,int $a,string $cap):void{$acl=new IssuerResourceAccess($this->db,$this->p);if($acl->primaryId()===$i||!$acl->can($i,$a,'invoices','view')||!$acl->can($i,$a,'invoices',$cap))throw new DomainException('native_invoice_editor_denied');}
 private function options(int $i,array $selection):array
 {
  $store=new CompanySettingsStorage($this->db,$this->p);$defaults=NativeCompanyInvoicePreferences::defaults();
  $values=[];foreach($defaults as$key=>$default)if($key!=='next_invoice_number')$values[$key]=$store->exists($i,$key)?$store->get($i,$key):$default;
  NativeCompanyInvoicePreferences::validate($values,false);
  // Same public installation defaults used by the native form and canonical invoice profile.
  foreach(['number_padding_prefixes'=>'6','automatically_set_logged_in_staff_sales_agent'=>'1','default_tax'=>'a:0:{}']as$key=>$default)$values[$key]=$store->exists($i,$key)?$store->get($i,$key):$default;
  if(!preg_match('/^(?:0|[1-9][0-9]?)$/D',$values['number_padding_prefixes'])||!in_array($values['automatically_set_logged_in_staff_sales_agent'],['0','1'],true))throw new DomainException('native_invoice_editor_options_invalid');
  $values['next_invoice_number']=$selection['next_number'];return$values;
 }
 public function createData(int $i,int $a,$customer=null):array
 {
  $this->authorize($i,$a,'create');$editor=NativeCompanyInvoiceEditorItems::load($this->db,$this->p,$i,$a);
  $catalog=new NativeCompanyCatalogUi($this->db,$this->p,$i,$a);$selection=(new NativeCompanyInvoiceSequencePreferences($this->db,$this->p))->readForInvoiceCreation($i,$a);$options=$this->options($i,$selection);
  $defaultTaxes=@unserialize($options['default_tax'],['allowed_classes'=>false]);if(!is_array($defaultTaxes)||!array_is_list($defaultTaxes))throw new DomainException('native_invoice_editor_options_invalid');$defaultTaxes=$catalog->validateTaxSelections($defaultTaxes);$options['default_tax']=serialize($defaultTaxes);
  $choices=$catalog->invoiceEditorChoices();foreach($choices['taxes']as&$tax)$tax['taxrate']=NativeCompanyCatalog::simpleFields(['name'=>(string)$tax['name'],'taxrate'=>(string)$tax['taxrate']],true)['taxrate'];unset($tax);
  $reminders=(new NativeCompanyInvoiceCommunicationState($this->db,$this->p))->reminderSources($i,$a);
  $data=$choices+['native_invoice_reminder_sources'=>$reminders,'native_invoice_reminders_enabled'=>$reminders['enabled']]+['native_invoice_editor_items'=>$editor,'native_invoice_id'=>'','native_invoice_revision'=>'','native_invoice_number_selection'=>$selection,'native_invoice_options'=>$options,'native_invoice_request_key'=>bin2hex(random_bytes(32)),
   'payment_modes'=>(new NativeCompanyInvoicePaymentChoices($this->db,$this->p))->listing($i,$a),'currencies'=>$catalog->currencies(),'base_currency'=>(object)$catalog->baseCurrency(),'staff'=>(new NativeCompanyInvoiceAgents($this->db,$this->p))->choices($i,$a),
   'title'=>_l('create_new_invoice'),'bodyclass'=>'invoice'];
  if($customer!==null&&$customer!==''){$customer=IssuerPolicy::id($customer);$details=(new CompanyClients($this->db,$this->p))->details($i,$a,$customer);if(!$details)throw new DomainException('company_client_access_denied');$data['customer_id']=$customer;$data['native_invoice_customer']=['id'=>$customer,'name'=>$details['client']['company']];}
  // No selected customer yet has no billable task selection; do not substitute empties for a selected customer's eligibility.
  if(!isset($data['customer_id']))$data['billable_tasks']=[];
  else {require_once __DIR__.'/NativeCompanyBillableTaskRead.php';$taskSelection=(new NativeCompanyBillableTaskRead($this->db,$this->p))->forCustomer($i,$a,$customer);$data['billable_tasks']=$taskSelection['items'];$data['native_invoice_task_selection']=$taskSelection;}
  $this->authorize($i,$a,'create');$editor->assertCurrent();return$data;
 }
 /** Original POST composition. Non-manual sources/actions are explicit, never ignored. */
 public function create(int $i,int $a,array $input):array
 {
  $this->authorize($i,$a,'create');require_once __DIR__.'/NativeCompanyInvoiceFormInput.php';require_once __DIR__.'/NativeCompanyInvoiceCommands.php';
  $store=new CompanySettingsStorage($this->db,$this->p);$format=$store->exists($i,'dateformat')?$store->get($i,'dateformat'):CompanySettingsCatalog::defaultValue('localization','dateformat');$request=NativeCompanyInvoiceFormInput::create($input,$format);
  if(in_array($request['action'],['save_and_send','save_and_send_later'],true))throw new DomainException('native_invoice_form_mail_required');
  $catalog=new NativeCompanyCatalogUi($this->db,$this->p,$i,$a);foreach($request['payload']['items']as$line)$catalog->validateTaxSelections(array_map(static fn($tax)=>$tax['name'].'|'.$tax['rate'],$line['taxes']));
  if($request['project']!==''){
   require_once __DIR__.'/CompanyProjects.php';$project=NativeCompanyResourceId::decode('project',$request['project']);if($project['source_kind']!=='company_project')throw new DomainException('company_invoice_project_invalid');$row=(new CompanyProjects($this->db,$this->p))->get($i,$a,$project['source_id']);
   if((int)$row['client_id']!==$request['payload']['client_id'])throw new DomainException('company_invoice_project_invalid');$request['payload']['native_project']=['source_kind'=>'company_project','source_id'=>$project['source_id'],'source_revision'=>(int)$row['revision']];
  }
  $this->authorize($i,$a,'create');$commands=new NativeCompanyInvoiceCommands($this->db,$this->p);$result=$request['action']==='save_as_draft'?$commands->createDraft($i,$a,$request['payload'],$request['metadata'],$request['request_key']):$commands->createIssued($i,$a,$request['payload'],$request['metadata'],$request['request_key']);
  return ['result'=>$result,'action'=>$request['action']];
 }
}
