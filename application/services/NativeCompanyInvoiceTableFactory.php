<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyInvoiceTable.php';
require_once __DIR__.'/NativeCompanyCustomFields.php';
require_once __DIR__.'/InvoiceStatusPresentation.php';
/** One catalogue for native invoice list and persisted Filters, without global model/hook bootstrapping. */
final class NativeCompanyInvoiceTableFactory
{
    public function __construct(private $db,private string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    private function rows(string $sql,array $params):array{return IssuerStorage::query($this->db,$sql,$params)->result_array();}
    public function data(int $issuer,int $actor):array
    {
        $acl=new IssuerResourceAccess($this->db,$this->p);
        if(!$acl->can($issuer,$actor,'invoices','view'))throw new DomainException('company_invoice_access_denied');
        $primary=$acl->primaryId();$where='(i.issuer_id=?'.($issuer===$primary?' OR i.issuer_id IS NULL':'').')';
        $years=$this->rows('SELECT DISTINCT SUBSTR(i.date,1,4) year FROM '.$this->p.'invoices i WHERE '.$where.' ORDER BY year DESC',[$issuer]);
        $draftProjection=null;$draftFrame=[];
        if($this->db->table_exists($this->p.'company_invoice_drafts')){
            require_once __DIR__.'/NativeCompanyInvoiceDraftProjection.php';$draftProjection=new NativeCompanyInvoiceDraftProjection($this->db,$this->p);$draftFrame=$draftProjection->frame($issuer,$actor);
            $yearMap=[];foreach($years as$row)$yearMap[(string)$row['year']]=$row;foreach($draftFrame as$row)$yearMap[substr($row['date'],0,4)]=['year'=>substr($row['date'],0,4)];krsort($yearMap);$years=array_values($yearMap);
        }
        foreach($years as&$year){if(!preg_match('/^[1-9][0-9]{3}$/D',(string)$year['year']))throw new DomainException('native_company_invoice_catalog_invalid');$year['year']=(int)$year['year'];}unset($year);
        // Agent names are visible only when the persisted issuer membership exists; no global directory lookup.
        $agents=$this->rows('SELECT DISTINCT s.staffid sale_agent,s.firstname,s.lastname FROM '.$this->p.'invoices i JOIN '.$this->p.'staff s ON s.staffid=i.sale_agent LEFT JOIN '.$this->p.'issuers_staff m ON m.staff_id=s.staffid AND m.issuer_id=? WHERE (m.staff_id IS NOT NULL OR EXISTS (SELECT 1 FROM '.$this->p.'issuers_installation_identity master_identity WHERE master_identity.id=1 AND master_identity.master_staff_id=s.staffid)) AND '.$where.' ORDER BY s.firstname,s.lastname,s.staffid',[$issuer,$issuer]);
        $agentMap=[];foreach($agents as$agent)$agentMap[(int)$agent['sale_agent']]=$agent;
        foreach($draftFrame as$draft){$id=(int)$draft['sale_agent'];if($id>0&&!isset($agentMap[$id])&&$acl->member($issuer,$id)){$row=$this->rows('SELECT staffid sale_agent,firstname,lastname FROM '.$this->p.'staff WHERE staffid=?',[$id])[0]??null;if($row)$agentMap[$id]=$row;}}
        $agents=array_values($agentMap);usort($agents,static fn($a,$b)=>[$a['firstname'],$a['lastname'],(int)$a['sale_agent']]<=>[$b['firstname'],$b['lastname'],(int)$b['sale_agent']]);
        $agentOptions=[];foreach($agents as&$agent){$agent['sale_agent']=IssuerPolicy::id($agent['sale_agent']);$agentOptions[]=['value'=>(string)$agent['sale_agent'],'label'=>trim($agent['firstname'].' '.$agent['lastname'])];}unset($agent);
        // This is a factual receipt-filter inventory, NOT a gateway eligibility/charge/manual-writer catalogue.
        $recordedModes=$this->rows('SELECT DISTINCT p.paymentmode FROM '.$this->p.'invoicepaymentrecords p JOIN '.$this->p.'invoices i ON i.id=p.invoiceid WHERE '.$where.' ORDER BY p.paymentmode',[$issuer]);
        $modes=[];$modeOptions=[];
        foreach($recordedModes as$row){$mode=(string)$row['paymentmode'];if(!preg_match('/^[A-Za-z0-9_-]{1,100}$/D',$mode))throw new DomainException('native_company_invoice_catalog_invalid');
            // Provider receipt IDs are retained verbatim. They must never become manual modes by coercion.
            $label=$mode;
            if($issuer===$primary&&preg_match('/^[1-9][0-9]*$/D',$mode)){$native=$this->rows('SELECT name FROM '.$this->p.'payment_modes WHERE id=?',[$mode]);if($native)$label=(string)$native[0]['name'];}
            $modes[]=['id'=>$mode,'name'=>$label,'basis'=>'recorded_receipt_filter'];$modeOptions[]=['value'=>$mode,'label'=>$label];
        }
        $statuses=range(1,6);$options=['sale_agent'=>$agentOptions,'status'=>array_map(static fn($status)=>['value'=>(string)$status,'label'=>_l(InvoiceStatusPresentation::labelKey($status))],$statuses),
            'year'=>array_map(static fn($year)=>['value'=>(string)$year['year'],'label'=>(string)$year['year']],$years),'made_payment_by'=>$modeOptions];
        $definitions=(new NativeCompanyCustomFields($this->db,$this->p))->definitions($issuer,$actor,'invoice',['active'=>1]);
        if(!$acl->can($issuer,$actor,'invoices','view')||$acl->primaryId()!==$primary)throw new DomainException('company_invoice_access_denied');
        if($draftProjection&&$draftProjection->frame($issuer,$actor)!==$draftFrame)throw new DomainException('native_company_invoice_catalog_invalid');
        return ['options'=>$options,'invoices_years'=>$years,'invoices_sale_agents'=>$agents,'invoices_statuses'=>$statuses,'payment_modes'=>$modes,'customDefinitions'=>$definitions];
    }
    public function make(int $issuer,int $actor,string $identifier='invoices',array $savedFilters=[]):NativeCompanyInvoiceTable
    {
        $data=$this->data($issuer,$actor);
        return new NativeCompanyInvoiceTable($this->db,$this->p,$issuer,$actor,$identifier,$data['options'],$savedFilters,$data['customDefinitions']);
    }
}
