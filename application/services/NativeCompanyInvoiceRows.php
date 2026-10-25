<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyInvoiceIdentity.php';
require_once __DIR__.'/CompanyInvoices.php';
require_once __DIR__.'/NativeCompanyInvoiceCustomColumns.php';
require_once __DIR__.'/NativeCompanyResourceTags.php';
require_once __DIR__.'/SalesNumberPresentation.php';
require_once __DIR__.'/TagPresentation.php';
/** DTO assembler for the SAME native table_row partial, never a legacy model or hook dispatcher. */
final class NativeCompanyInvoiceRows
{
    public function __construct(private $db,private string $p){}
    public function render(int $issuer,int $actor,$invoiceId,array $scope=[]):array
    {
        $presentation=$this->presentation($issuer,$actor,$invoiceId,$scope);
        return (static function(array $presentation):array {
            extract($presentation,EXTR_SKIP);
            return require __DIR__.'/../views/admin/invoices/table_row.php';
        })($presentation);
    }
    private function rows(string $sql,array $params):array{return IssuerStorage::query($this->db,$sql,$params)->result_array();}
    public function presentation(int $issuer,int $actor,$invoiceId,array $scope=[]):array
    {
        $source=NativeCompanyResourceId::decode('invoice',$invoiceId);
        if($source['source_kind']==='company_invoice_draft')return$this->draftPresentation($issuer,$actor,$source,$scope);
        $identity=new NativeCompanyInvoiceIdentity($this->db,$this->p);$ref=$identity->resolve($issuer,$actor,$invoiceId);
        $recordSql='SELECT id,issuer_id,clientid,number,prefix,number_format,formatted_number,date,duedate,currency,subtotal,total,total_tax,status,sent,hash,recurring,project_id,deleted_customer_name FROM '.$this->p.'invoices WHERE id=?';
        $records=$this->rows($recordSql,[$ref['invoice_id']]);$record=$records[0];
        $currency=$this->rows('SELECT id,name FROM '.$this->p.'currencies WHERE id=?',[$record['currency']])[0]??null;
        if(!$currency||!preg_match('/^[A-Z]{3}$/D',$currency['name'])||!preg_match('/^[a-f0-9]{32}$/D',$record['hash']))throw new DomainException('native_company_invoice_row_invalid');
        $aRow=$record+['currency_name'=>$currency['name'],'year'=>substr($record['date'],0,4),'company'=>'','project_name'=>''];
        if($ref['lineage']==='named_company'){
            $document=(new CompanyInvoices($this->db,$this->p))->document($issuer,$actor,$ref['invoice_id'])->data();
            $formattedNumber=$document['number'];$aRow['company']=$document['recipient']['name'];
            $aRow['total']=$document['totals']['total'];$aRow['total_tax']=$document['totals']['total_tax'];
            $aRow['deleted_customer_name']='';$aRow['recurring']=0;
            CompanyInvoiceProjectSchema::assertValid($this->db,$this->p);
            $relation=$this->rows('SELECT client_id,source_kind,source_id FROM '.$this->p.CompanyInvoiceProjectSchema::TABLE.' WHERE issuer_id=? AND invoice_id=?',[$issuer,$ref['invoice_id']]);
            if($relation){
                if(count($relation)!==1||$relation[0]['source_kind']!=='company_project'||(string)$relation[0]['client_id']!==(string)$record['clientid'])throw new DomainException('native_company_invoice_row_invalid');
                $aRow['project_id']=NativeCompanyResourceId::encode('company_project',$relation[0]['source_id']);
            }else{$aRow['project_id']=0;}
            require_once __DIR__.'/CompanyInvoiceRecurring.php';require_once __DIR__.'/CompanyInvoiceRecurringModes.php';
            CompanyInvoiceRecurringModesSchema::assertValid($this->db,$this->p);
            $modes=$this->rows('SELECT schedule_id FROM '.$this->p.'company_invoice_schedule_modes WHERE issuer_id=? AND principal_invoice_id=?',[$issuer,$ref['invoice_id']]);
            foreach($modes as$mode){$schedule=(new CompanyInvoiceRecurring($this->db,$this->p))->document($issuer,$actor,$mode['schedule_id']);if(($schedule['mode']['principal_invoice_id']??null)!==$ref['invoice_id'])throw new DomainException('native_company_invoice_row_invalid');$aRow['recurring']=1;}
        }else{
            $settings=new CompanySettingsStorage($this->db,$this->p);
            if(!$settings->exists($issuer,'number_padding_prefixes')||!preg_match('/^(?:0|[1-9][0-9]?)$/D',$settings->get($issuer,'number_padding_prefixes')))throw new DomainException('native_company_invoice_padding_invalid');
            $formattedNumber=(int)$record['status']===6?$record['prefix'].'DRAFT':SalesNumberPresentation::format($record['number'],$record['number_format'],$record['prefix'],$record['date'],(int)$settings->get($issuer,'number_padding_prefixes'));
        }
        $customerLink=false;$projectLink=false;
        try{$customer=NativeCompanyResourceReferences::resolve($this->db,$this->p,$issuer,$actor,'client',$record['clientid']);$customerLink=true;if($ref['lineage']==='native_legacy_null')$aRow['company']=$customer['name'];}
        catch(DomainException $denied){if($denied->getMessage()!=='native_resource_reference_denied')throw$denied;}
        if((int)$aRow['project_id']>0){
            try{$project=NativeCompanyResourceReferences::resolve($this->db,$this->p,$issuer,$actor,'project',$aRow['project_id']);$aRow['project_name']=$project['name'];$projectLink=true;}
            catch(DomainException $denied){if($denied->getMessage()!=='native_resource_reference_denied')throw$denied;}
        }
        $tags=(new NativeCompanyResourceTags($this->db,$this->p))->read($issuer,$actor,'invoice',$ref['invoice_id']);
        $custom=new NativeCompanyInvoiceCustomColumns($this->db,$this->p,$issuer,$actor);$definitions=$custom->definitions(true);$values=$custom->values($ref['invoice_id']);$customFieldsColumns=[];
        foreach($definitions as$field){$key=(in_array($field['type'],['date_picker','date_picker_time'],true)?'date_picker_':'cvalue_').$field['id'];$customFieldsColumns[]=$key;$aRow[$key]=htmlspecialchars($values[(string)$field['id']]??'',ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
        $custom->recheck();
        if($identity->resolve($issuer,$actor,$ref['invoice_id'])!==$ref||$this->rows($recordSql,[$ref['invoice_id']])!==$records)throw new DomainException('native_company_invoice_read_changed');
        return ['aRow'=>$aRow,'formattedNumber'=>$formattedNumber,'canEditInvoice'=>(new IssuerResourceAccess($this->db,$this->p))->can($issuer,$actor,'invoices','edit'),
            'invoiceTagsHtml'=>TagPresentation::render($tags['tags']),'invoiceStatus'=>(int)$record['status'],'customFieldsColumns'=>$customFieldsColumns,
            'invoiceCustomerLink'=>$customerLink,'invoiceProjectLink'=>$projectLink,'clientid'=>$scope['customer_id']??'','project_id'=>$scope['project_id']??''];
    }
    private function draftPresentation(int$issuer,int$actor,array$source,array$scope):array
    {
        require_once __DIR__.'/CompanyInvoiceDrafts.php';
        $drafts=new CompanyInvoiceDrafts($this->db,$this->p);$draft=$drafts->document($issuer,$actor,$source['source_id']);
        if($draft['state']!=='open')throw new DomainException('company_invoice_draft_unavailable');
        $payload=$draft['payload'];$context=$draft['context'];$money=CompanyInvoiceLines::calculate($payload['items'],array_intersect_key($payload,array_flip(['discount_mode','discount_value','discount_type','adjustment','calculation_contract'])));
        $currency=(new CompanyCurrencyUnits($this->db,$this->p))->resolveRead($payload['currency']);
        $projectId=isset($payload['native_project']['source_id'])?NativeCompanyResourceId::encode('company_project',$payload['native_project']['source_id']):0;
        $aRow=['id'=>$source['canonical_id'],'issuer_id'=>$issuer,'clientid'=>$payload['client_id'],'total'=>$money['total'],'total_tax'=>$money['tax'],
            'currency_name'=>$currency['iso_code'],'date'=>$payload['date'],'duedate'=>$payload['due_date'],'year'=>substr($payload['date'],0,4),
            'company'=>$context['recipient']['name'],'deleted_customer_name'=>'','project_id'=>$projectId,'project_name'=>'','recurring'=>0];
        $customerLink=false;$projectLink=false;
        try{NativeCompanyResourceReferences::resolve($this->db,$this->p,$issuer,$actor,'client',$payload['client_id']);$customerLink=true;}
        catch(DomainException$e){if($e->getMessage()!=='native_resource_reference_denied')throw$e;}
        if($projectId){try{$project=NativeCompanyResourceReferences::resolve($this->db,$this->p,$issuer,$actor,'project',$projectId);$aRow['project_name']=$project['name'];$projectLink=true;}catch(DomainException$e){if($e->getMessage()!=='native_resource_reference_denied')throw$e;}}
        $tags=(new NativeCompanyResourceTags($this->db,$this->p))->read($issuer,$actor,'invoice',$source['canonical_id']);
        $custom=new NativeCompanyInvoiceCustomColumns($this->db,$this->p,$issuer,$actor);$definitions=$custom->definitions(true);$values=$custom->values($source['canonical_id']);$columns=[];
        foreach($definitions as$field){$key=(in_array($field['type'],['date_picker','date_picker_time'],true)?'date_picker_':'cvalue_').$field['id'];$columns[]=$key;$aRow[$key]=htmlspecialchars($values[(string)$field['id']]??'',ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
        $custom->recheck();if($drafts->document($issuer,$actor,$source['source_id'])!==$draft)throw new DomainException('native_company_invoice_read_changed');
        return ['aRow'=>$aRow,'formattedNumber'=>'DRAFT','invoiceViewUrl'=>admin_url('invoices/list_invoices/'.$source['canonical_id']),
            'canEditInvoice'=>(new IssuerResourceAccess($this->db,$this->p))->can($issuer,$actor,'invoices','edit'),'invoiceTagsHtml'=>TagPresentation::render($tags['tags']),
            'invoiceStatus'=>6,'customFieldsColumns'=>$columns,'invoiceCustomerLink'=>$customerLink,'invoiceProjectLink'=>$projectLink,
            'clientid'=>$scope['customer_id']??'','project_id'=>$scope['project_id']??''];
    }

}
