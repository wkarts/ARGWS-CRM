<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyInvoiceSequencePreferences.php';
require_once __DIR__.'/NativeCompanyInvoiceEditorItems.php';
require_once __DIR__.'/CompanySettingsFormats.php';
require_once __DIR__.'/CompanySettingsStorage.php';
require_once __DIR__.'/CompanySettingsCatalog.php';
/** Native remote validation is read-only; the allocator, not browser input, assigns numbers. */
final class NativeCompanyInvoiceNumberValidation
{
 public function __construct(private $db,private string $p){}
 public function validate(int $issuer,int $actor,array $input):bool
 {
  if(array_diff(array_keys($input),['number','isedit','original_number','date','invoice_id','revision'])||!is_string($input['number']??null)||!preg_match('/^[0-9]{1,30}$/D',$input['number'])||!in_array($input['isedit']??null,['true','false'],true)||!is_string($input['date']??null))throw new DomainException('native_invoice_number_invalid');
  $acl=new IssuerResourceAccess($this->db,$this->p);if(!$acl->can($issuer,$actor,'invoices','view')||!$acl->can($issuer,$actor,'invoices',$input['isedit']==='true'?'edit':'create'))throw new DomainException('company_invoice_access_denied');
  $number=ltrim($input['number'],'0');if($number==='')return false;
  $store=new CompanySettingsStorage($this->db,$this->p);$format=$store->exists($issuer,'dateformat')?$store->get($issuer,'dateformat'):CompanySettingsCatalog::defaultValue('localization','dateformat');
  $formats=CompanySettingsFormats::dates();if(!isset($formats[$format]))throw new DomainException('native_invoice_number_invalid');$date=$input['date'];$f=preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D',$date)?'Y-m-d':$formats[$format];$parsed=DateTimeImmutable::createFromFormat('!'.$f,$date,new DateTimeZone('UTC'));if(!$parsed||$parsed->format($f)!==$date)throw new DomainException('native_invoice_number_invalid');
  if($input['isedit']==='false'){
   if(!in_array($input['invoice_id']??'',['',null],true)||!in_array($input['revision']??'',['',null],true)||!in_array($input['original_number']??'false',['false',''],true))throw new DomainException('native_invoice_number_invalid');
   return $number===(new NativeCompanyInvoiceSequencePreferences($this->db,$this->p))->readForInvoiceCreation($issuer,$actor)['next_number'];
  }
  $id=NativeCompanyResourceId::decode('invoice',$input['invoice_id']??null);$editor=NativeCompanyInvoiceEditorItems::load($this->db,$this->p,$issuer,$actor,$id['canonical_id']);
  if($id['source_kind']==='company_invoice_draft'){
   $draft=(new CompanyInvoiceDrafts($this->db,$this->p))->document($issuer,$actor,$id['source_id']);if((string)$draft['revision']!==(string)($input['revision']??''))throw new DomainException('company_invoice_draft_stale');
   // Drafts have no reserved number. Display/remote validation is edit-only; issue separately requires create and CAS.
   $ok=$number===(new NativeCompanyInvoiceSequencePreferences($this->db,$this->p))->readForDraftEditor($issuer,$actor,$id['canonical_id'])['next_number'];$editor->assertCurrent();return$ok;
  }
  $writer=new CompanyInvoices($this->db,$this->p);$revision=$writer->revision($issuer,$actor,$id['source_id']);if(!is_string($input['revision']??null)||!hash_equals($revision,$input['revision']))throw new DomainException('company_invoice_stale');
  $writer->document($issuer,$actor,$id['source_id']);
  $row=IssuerStorage::query($this->db,'SELECT number,date FROM '.$this->p.'invoices WHERE id=? AND issuer_id=?',[$id['source_id'],$issuer])->row_array();
  if(!$row||!hash_equals($revision,$writer->revision($issuer,$actor,$id['source_id'])))throw new DomainException('company_invoice_stale');$editor->assertCurrent();
  // Never trust original_number supplied by a browser to authorize renumbering or a foreign invoice.
  return (string)$row['number']===$number&&$row['date']===$parsed->format('Y-m-d');
 }
}
