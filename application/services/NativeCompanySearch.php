<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerResourceAccess.php';
require_once __DIR__.'/NativeCompanyResourceReferences.php';

/** Search candidates are always owned; every returned link is resolved and authorized again. */
final class NativeCompanySearch
{
    private $db;private string $p;
    private const OWNED=[
        ['project','projects','company_projects','name'],
        ['task','tasks','company_tasks','name'],
        ['task','tasks','company_standalone_tasks','name'],
        ['ticket','tickets','company_tickets','subject'],
        ['contract','contracts','company_contracts','name'],
        ['lead','leads','company_leads','name'],
        ['expense','expenses','company_expenses','description'],
        ['item','items','company_items','description'],
    ];
    public function __construct($db,string $prefix){if(!preg_match('/^[A-Za-z0-9_]+$/D',$prefix))throw new InvalidArgumentException('Invalid table prefix');$this->db=$db;$this->p=$prefix;}
    public function search(int $issuer,int $actor,string $query):array
    {
        $query=trim($query);
        if(!preg_match('//u',$query)||preg_match('/[\x00-\x1f\x7f]/u',$query)||mb_strlen($query,'UTF-8')<2||mb_strlen($query,'UTF-8')>191)throw new DomainException('issuer_search_invalid');
        $acl=new IssuerResourceAccess($this->db,$this->p);
        if(!$acl->member($issuer,$actor))throw new DomainException('issuer_access_denied');
        $needle='%'.str_replace(['!','%','_'],['!!','!%','!_'],$query).'%';$groups=[];
        foreach(self::OWNED as [$type,$feature,$table,$label]){
            if(!$acl->can($issuer,$actor,$feature,'view'))continue;
            $rows=IssuerStorage::query($this->db,'SELECT id FROM '.$this->p.$table.' WHERE issuer_id=? AND '.$label." LIKE ? ESCAPE '!' ORDER BY id LIMIT 20",[$issuer,$needle])->result_array();
            $source=['company_projects'=>'company_project','company_tasks'=>'company_task','company_standalone_tasks'=>'company_standalone_task','company_tickets'=>'company_ticket','company_contracts'=>'company_contract','company_leads'=>'company_lead','company_expenses'=>'company_expense','company_items'=>'company_item'][$table];
            foreach($rows as $row)$this->append($groups,$issuer,$actor,$type,$feature,NativeCompanyResourceId::encode($source,(string)$row['id']));
        }
        if($acl->can($issuer,$actor,'customers','view')){
            // CompanyClients includes shared-client ACL; do not scan global clients then filter labels in PHP.
            require_once __DIR__.'/CompanyClients.php';
            $page=(new CompanyClients($this->db,$this->p))->page($issuer,$actor,0,20,$query);
            foreach($page['items'] as $row)$this->append($groups,$issuer,$actor,'client','customers',(string)($row['userid']??$row['id']));
        }
        if($acl->can($issuer,$actor,'invoices','view')){
            $rows=IssuerStorage::query($this->db,'SELECT i.id FROM '.$this->p.'invoices i JOIN '.$this->p.'issuers_invoice_documents d ON d.invoice_id=i.id AND d.issuer_id=i.issuer_id WHERE i.issuer_id=? AND d.formatted_number'." LIKE ? ESCAPE '!' ORDER BY i.id LIMIT 20",[$issuer,$needle])->result_array();
            foreach($rows as $row)$this->append($groups,$issuer,$actor,'invoice','invoices',(string)$row['id']);
        }
        foreach(['estimates'=>'company_estimate','proposals'=>'company_proposal'] as $kind=>$source){
            if(!$acl->can($issuer,$actor,$kind,'view'))continue;
            $rows=IssuerStorage::query($this->db,'SELECT id FROM '.$this->p.'company_sales_documents WHERE issuer_id=? AND kind=? AND (CAST(number AS CHAR)'." LIKE ? ESCAPE '!' OR payload_json LIKE ? ESCAPE '!') ORDER BY id LIMIT 20",[$issuer,$kind,$needle,$needle])->result_array();
            foreach($rows as $row)$this->append($groups,$issuer,$actor,$kind==='estimates'?'estimate':'proposal',$kind,NativeCompanyResourceId::encode($source,(string)$row['id']));
        }
        $primary=$acl->primaryId()===$issuer;
        if($primary){
            require_once __DIR__.'/ClientIssuerScope.php';require_once __DIR__.'/InvoiceIssuerScope.php';require_once __DIR__.'/CustomerRelationBoundary.php';require_once __DIR__.'/TaskClientBoundary.php';
            foreach([
                ['project','projects','projects','id','name'],['task','tasks','tasks','id','name'],
                ['ticket','tickets','tickets','ticketid','subject'],['contract','contracts','contracts','id','subject'],
                ['lead','leads','leads','id','name'],['expense','expenses','expenses','id','expense_name'],
                ['estimate','estimates','estimates','id','number'],['proposal','proposals','proposals','id','subject'],
                ['item','items','items','id','description'],
            ] as [$type,$feature,$table,$key,$label]){
                if(!$acl->can($issuer,$actor,$feature,'view'))continue;
                $boundary='1=1';$alias='native_search';
                if(in_array($table,['projects','tickets','contracts','expenses'],true))$boundary=\app\services\CustomerRelationBoundary::sql($this->db,$this->p,$table,$alias);
                elseif($table==='tasks')$boundary=\app\services\TaskClientBoundary::legacySql($this->db,$this->p,$alias);
                elseif(in_array($table,['estimates','proposals'],true))$boundary=\app\services\ClientIssuerScope::legacyDocumentSql($this->db,$this->p,$table,$alias);
                elseif($table==='leads')$boundary=\app\services\ClientIssuerScope::legacySql($this->db,$this->p,$alias,'client_id');
                $rows=IssuerStorage::query($this->db,'SELECT '.$alias.'.'.$key.' AS id FROM '.$this->p.$table.' '.$alias.' WHERE ('.$boundary.') AND CAST('.$alias.'.'.$label." AS CHAR) LIKE ? ESCAPE '!' ORDER BY ".$alias.'.'.$key.' LIMIT 20',[$needle])->result_array();
                foreach($rows as $row)$this->append($groups,$issuer,$actor,$type,$feature,(string)$row['id']);
            }
            if($acl->can($issuer,$actor,'invoices','view')){
                $rows=IssuerStorage::query($this->db,'SELECT id FROM '.$this->p.'invoices WHERE issuer_id IS NULL AND CAST(number'." AS CHAR) LIKE ? ESCAPE '!' ORDER BY id LIMIT 20",[$needle])->result_array();
                foreach($rows as $row)$this->append($groups,$issuer,$actor,'invoice','invoices',(string)$row['id']);
            }
        }
        if($acl->can($issuer,$actor,'customers','view')){
            $owner='(o.owner_issuer_id=?'.($primary?' OR o.owner_issuer_id IS NULL':'').')';
            $rows=IssuerStorage::query($this->db,'SELECT c.id FROM '.$this->p.'contacts c LEFT JOIN '.$this->p.'issuers_clients o ON o.client_id=c.userid WHERE '.$owner." AND (c.firstname LIKE ? ESCAPE '!' OR c.lastname LIKE ? ESCAPE '!') ORDER BY c.id LIMIT 20",[$issuer,$needle,$needle])->result_array();
            foreach($rows as $row)$this->append($groups,$issuer,$actor,'contact','customers',(string)$row['id']);
        }
        if($acl->can($issuer,$actor,'staff','view')){
            $rows=IssuerStorage::query($this->db,'SELECT s.staffid FROM '.$this->p.'staff s WHERE s.active=1 AND (EXISTS (SELECT 1 FROM '.$this->p.'issuers_staff m WHERE m.issuer_id=? AND m.staff_id=s.staffid AND m.can_view=1) OR EXISTS (SELECT 1 FROM '.$this->p.'issuers_installation_identity x WHERE x.id=1 AND x.master_staff_id=s.staffid AND s.admin=1))'." AND (s.firstname LIKE ? ESCAPE '!' OR s.lastname LIKE ? ESCAPE '!') ORDER BY s.staffid LIMIT 20",[$issuer,$needle,$needle])->result_array();
            foreach($rows as $row)$this->append($groups,$issuer,$actor,'staff','staff',(string)$row['staffid']);
        }
        if($acl->can($issuer,$actor,'credit_notes','view')){
            $rows=IssuerStorage::query($this->db,'SELECT id FROM '.$this->p.'creditnotes WHERE (issuer_id=?'.($primary?' OR issuer_id IS NULL':'').') AND CAST(number AS CHAR)'." LIKE ? ESCAPE '!' ORDER BY id LIMIT 20",[$issuer,$needle])->result_array();
            foreach($rows as $row)$this->append($groups,$issuer,$actor,'credit_note','credit_notes',(string)$row['id']);
        }
        if($acl->can($issuer,$actor,'payments','view')&&$acl->can($issuer,$actor,'invoices','view')){
            $rows=IssuerStorage::query($this->db,'SELECT p.id FROM '.$this->p.'invoicepaymentrecords p JOIN '.$this->p.'invoices i ON i.id=p.invoiceid WHERE (i.issuer_id=?'.($primary?' OR i.issuer_id IS NULL':'').') AND (CAST(p.id AS CHAR)'." LIKE ? ESCAPE '!' OR CAST(p.amount AS CHAR) LIKE ? ESCAPE '!') ORDER BY p.id LIMIT 20",[$issuer,$needle,$needle])->result_array();
            foreach($rows as $row)$this->append($groups,$issuer,$actor,'payment','payments',(string)$row['id']);
        }
        if($acl->can($issuer,$actor,'invoices','view')){
            $matches=$this->invoiceItems($issuer,$actor,$needle,$primary);
            if($matches)$groups['invoice_items']=['type'=>'invoice_items','search_heading'=>'invoice_items','native_owned'=>true,'result'=>$matches];
        }
        if($acl->can($issuer,$actor,'estimates','view')){
            $matches=$this->estimateItems($issuer,$actor,$query,$needle,$primary);
            if($matches)$groups['estimate_items']=['type'=>'estimate_items','search_heading'=>'estimate_items','native_owned'=>true,'result'=>$matches];
        }
        if(!$acl->member($issuer,$actor))throw new DomainException('issuer_access_denied');
        foreach($groups as $feature=>$group)if(!$acl->can($issuer,$actor,(['invoice_items'=>'invoices','estimate_items'=>'estimates'][$feature]??$feature),'view'))unset($groups[$feature]);
        if($acl->administrative($issuer,$actor)){
            require_once __DIR__.'/NativeCompanyCustomFields.php';
            $matches=(new NativeCompanyCustomFields($this->db,$this->p))->search($issuer,$actor,$query);
            if($matches)$groups['custom_fields']=['type'=>'custom_fields','search_heading'=>'custom_fields','native_owned'=>true,'result'=>$matches];
        }
        return array_values($groups);
    }
    private function estimateItems(int $issuer,int $actor,string $query,string $needle,bool $primary):array
    {
        require_once __DIR__.'/CompanySales.php';
        $result=[];$after=0;$sales=new CompanySales($this->db,$this->p);
        do{
            $candidates=IssuerStorage::query($this->db,'SELECT id FROM '.$this->p."company_sales_documents WHERE issuer_id=? AND kind='estimates' AND id>? AND payload_json LIKE ? ESCAPE '!' ORDER BY id LIMIT 50",[$issuer,$after,$needle])->result_array();
            foreach($candidates as $candidate){
                $after=(int)$candidate['id'];
                try{
                    $reference=NativeCompanyResourceReferences::resolve($this->db,$this->p,$issuer,$actor,'estimate',NativeCompanyResourceId::encode('company_estimate',$after),'view');
                    $document=$sales->document($issuer,$actor,$after); // Existing canonical reader verifies hashes, amount, owner and revision.
                    foreach($document['payload']['items'] as $line){
                        if(mb_stripos($line['description'],$query,0,'UTF-8')===false&&mb_stripos($line['long_description']??'',$query,0,'UTF-8')===false)continue;
                        $result[]=['label'=>$document['formatted_number'],'secondary_label'=>$line['description'],'relative_url'=>$reference['url'],'source_kind'=>$reference['source_kind'],'source_id'=>$reference['source_id']];
                        if(count($result)>=20)return $result;
                    }
                }catch(DomainException $denied){continue;}
            }
        }while(count($candidates)===50);
        if(!$primary)return $result;
        require_once __DIR__.'/ClientIssuerScope.php';
        $boundary=\app\services\ClientIssuerScope::legacyDocumentSql($this->db,$this->p,'estimates','native_estimate');
        $rows=IssuerStorage::query($this->db,'SELECT l.id,l.rel_id FROM '.$this->p.'itemable l JOIN '.$this->p."estimates native_estimate ON native_estimate.id=l.rel_id WHERE l.rel_type='estimate' AND (".$boundary.") AND (l.description LIKE ? ESCAPE '!' OR l.long_description LIKE ? ESCAPE '!') ORDER BY l.description,l.id LIMIT 20",[$needle,$needle])->result_array();
        foreach($rows as $candidate){
            try{$reference=NativeCompanyResourceReferences::resolve($this->db,$this->p,$issuer,$actor,'estimate',(string)$candidate['rel_id'],'view');}catch(DomainException $denied){continue;}
            $row=IssuerStorage::query($this->db,'SELECT l.description FROM '.$this->p.'itemable l JOIN '.$this->p.'estimates native_estimate ON native_estimate.id=l.rel_id WHERE l.id=? AND l.rel_id=? AND l.rel_type=\'estimate\' AND ('.$boundary.')',[$candidate['id'],$candidate['rel_id']])->row_array();
            if(!$row)continue;
            $result[]=['label'=>$reference['name'],'secondary_label'=>$row['description'],'relative_url'=>$reference['url'],'source_kind'=>$reference['source_kind'],'source_id'=>$reference['source_id']];
            if(count($result)>=20)break;
        }
        return $result;
    }
    private function invoiceItems(int $issuer,int $actor,string $needle,bool $primary):array
    {
        $scope='(i.issuer_id=?'.($primary?' OR i.issuer_id IS NULL':'').')';
        $rows=IssuerStorage::query($this->db,'SELECT l.id,l.rel_id FROM '.$this->p.'itemable l JOIN '.$this->p."invoices i ON i.id=l.rel_id WHERE l.rel_type='invoice' AND ".$scope." AND (l.description LIKE ? ESCAPE '!' OR l.long_description LIKE ? ESCAPE '!') ORDER BY l.description,l.id LIMIT 20",[$issuer,$needle,$needle])->result_array();$result=[];
        foreach($rows as $candidate){
            try{$reference=NativeCompanyResourceReferences::resolve($this->db,$this->p,$issuer,$actor,'invoice',(string)$candidate['rel_id'],'view');}catch(DomainException $denied){continue;}
            // Hydrate only the exact authorized invoice line, with ownership repeated in SQL.
            $row=IssuerStorage::query($this->db,'SELECT l.description,d.formatted_number FROM '.$this->p.'itemable l JOIN '.$this->p.'invoices i ON i.id=l.rel_id LEFT JOIN '.$this->p.'issuers_invoice_documents d ON d.invoice_id=i.id AND d.issuer_id=i.issuer_id WHERE l.id=? AND l.rel_id=? AND l.rel_type=\'invoice\' AND '.$scope,[$candidate['id'],$candidate['rel_id'],$issuer])->row_array();
            if(!$row)continue;
            try{NativeCompanyResourceReferences::resolve($this->db,$this->p,$issuer,$actor,'invoice',(string)$candidate['rel_id'],'view');}catch(DomainException $denied){continue;}
            $result[]=['label'=>$row['formatted_number']??$reference['name'],'secondary_label'=>$row['description'],'relative_url'=>$reference['url'],'source_kind'=>$reference['source_kind'],'source_id'=>$reference['source_id']];
        }
        return $result;
    }
    private function append(array &$groups,int $issuer,int $actor,string $type,string $feature,string $id):void
    {
        try{$r=NativeCompanyResourceReferences::resolve($this->db,$this->p,$issuer,$actor,$type,$id,'view');}
        catch(DomainException $denied){return;}
        if(!preg_match('~^(?:[a-z_]+/)+[0-9]+(?:\?group=contacts&contactid=[0-9]+)?$~D',$r['url']))throw new RuntimeException('issuer_search_route_invalid');
        if(!isset($groups[$feature]))$groups[$feature]=['type'=>$feature,'search_heading'=>$feature,'native_owned'=>true,'result'=>[]];
        if(count($groups[$feature]['result'])<20)$groups[$feature]['result'][]=['label'=>$r['name'],'relative_url'=>$r['url'],'source_kind'=>$r['source_kind'],'source_id'=>$r['source_id']];
    }
}
