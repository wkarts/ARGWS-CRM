<?php

declare(strict_types=1);
require_once __DIR__.'/NativeCompanyResourceId.php';
require_once __DIR__.'/issuers/IssuerResourceAccess.php';

/** Authorization-aware relation resolver for the existing native browser contracts. */
final class NativeCompanyResourceReferences
{
    private const TYPES = [
        'client'=>['customers','clients','userid','company','clients/client/'],
        'invoice'=>['invoices','invoices','id','number','invoices/list_invoices/'],
        'project'=>['projects','projects','id','name','projects/view/'],
        'task'=>['tasks','tasks','id','name','tasks/view/'],
        'ticket'=>['tickets','tickets','ticketid','subject','tickets/ticket/'],
        'contract'=>['contracts','contracts','id','subject','contracts/contract/'],
        'lead'=>['leads','leads','id','name','leads/index/'],
        'expense'=>['expenses','expenses','id','expense_name','expenses/list_expenses/'],
        'estimate'=>['estimates','estimates','id','number','estimates/list_estimates/'],
        'proposal'=>['proposals','proposals','id','subject','proposals/list_proposals/'],
        'credit_note'=>['credit_notes','creditnotes','id','number','credit_notes/list_credit_notes/'],
        'contact'=>['customers','contacts','id','firstname','clients/client/'],
        'staff'=>['staff','staff','staffid','firstname','profile/'],
        'item'=>['items','items','id','description','invoice_items/'],
        'payment'=>['payments','invoicepaymentrecords','id','amount','payments/payment/'],
    ];
    private const OWNED = [
        'company_project'=>['company_projects','id','name'],
        'company_task'=>['company_tasks','id','name'],
        'company_standalone_task'=>['company_standalone_tasks','id','name'],
        'company_ticket'=>['company_tickets','id','subject'],
        'company_contract'=>['company_contracts','id','name'],
        'company_lead'=>['company_leads','id','name'],
        'company_expense'=>['company_expenses','id','description'],
        'company_estimate'=>['company_sales_documents','id','number'],
        'company_proposal'=>['company_sales_documents','id','number'],
        'company_item'=>['company_items','id','description'],
    ];

    /** Enlist the concrete reader boundary; declaring tables never grants resource authority. */
    public static function transactionTables($db,string $prefix,string $type):array
    {
        if(in_array($type,['ticket','tickets'],true)){require_once __DIR__.'/NativeCompanyTicketRead.php';return(new NativeCompanyTicketRead($db,$prefix))->tables();}
        return [];
    }

    public static function resolve($db, string $prefix, int $issuer, int $actor, string $type, $id, string $cap = 'view'): array
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/D', $prefix)) { throw new InvalidArgumentException('Invalid table prefix'); }
        $type = ['customer'=>'client','customers'=>'client','invoices'=>'invoice','projects'=>'project','tasks'=>'task','tickets'=>'ticket','contracts'=>'contract','leads'=>'lead'][$type] ?? $type;
        if (!isset(self::TYPES[$type]) || !in_array($cap, ['view','edit','delete'], true)) { throw new DomainException('native_resource_reference_denied'); }
        $issuer=IssuerPolicy::id($issuer); $actor=IssuerPolicy::id($actor);
        $reference=NativeCompanyResourceId::decode($type,$id);
        [$feature,$table,$key,$label,$path]=self::TYPES[$type];
        $acl=new IssuerResourceAccess($db,$prefix);
        if (!$acl->can($issuer,$actor,$feature,$cap)) { throw new DomainException('native_resource_reference_denied'); }
        $source=$reference['source_kind']; $sourceId=$reference['source_id'];
        if ($type === 'ticket') {
            require_once __DIR__.'/NativeCompanyTicketRead.php';
            $reader=new NativeCompanyTicketRead($db,$prefix);$owned=false;
            try { IssuerTransaction::assertOwned($db,$prefix,$reader->tables());$owned=true; }
            catch (RuntimeException $error) { if($error->getMessage()!=='issuer_owned_transaction_required')throw$error; }
            $resolved=$owned?$reader->referenceInOwnedTransaction($issuer,$actor,$id):$reader->reference($issuer,$actor,$id);
            if(!$acl->can($issuer,$actor,$feature,$cap))throw new DomainException('native_resource_reference_denied');
            return $resolved;
        }
        if ($source === 'company_invoice_draft') {
            require_once __DIR__.'/CompanyInvoiceDrafts.php';
            $drafts=new CompanyInvoiceDrafts($db,$prefix);
            // A raw SQL transaction is not authority. Join only the exact owned boundary;
            // otherwise document() opens its own transaction and rejects outer callers.
            $owned=false;
            try { IssuerTransaction::assertOwned($db,$prefix,$drafts->tables());$owned=true; }
            catch (RuntimeException $error) { if($error->getMessage()!=='issuer_owned_transaction_required')throw$error; }
            $draft=$owned?$drafts->documentInOwned($issuer,$actor,$sourceId):$drafts->document($issuer,$actor,$sourceId);
            if($cap!=='view'&&$draft['state']!=='open')throw new DomainException('native_resource_reference_denied');
            if(!$acl->can($issuer,$actor,$feature,$cap))throw new DomainException('native_resource_reference_denied');
            return $reference+['feature'=>$feature,'name'=>'draft:'.$sourceId,'url'=>$path.$reference['canonical_id'],
                'state'=>$draft['state'],'revision'=>$draft['revision'],'issued_invoice_id'=>$draft['invoice_id']];
        }
        if (isset(self::OWNED[$source])) {
            [$table,$key,$label]=self::OWNED[$source];
            $kind=in_array($source,['company_estimate','company_proposal'],true)?($source==='company_estimate'?'estimates':'proposals'):null;
            $taskParent=null;$standaloneParent=null;
            if($source==='company_standalone_task'){require_once __DIR__.'/NativeCompanyTaskRelationAuthority.php';$standaloneParent=NativeCompanyTaskRelationAuthority::capture($db,$prefix,$issuer,$actor,$sourceId);}
            if($source==='company_task'){
                if(!$acl->can($issuer,$actor,'projects','view'))throw new DomainException('native_resource_reference_denied');
                $taskParent=IssuerStorage::query($db,"SELECT t.id,t.project_id FROM {$prefix}company_tasks t JOIN {$prefix}company_projects p ON p.id=t.project_id AND p.issuer_id=t.issuer_id WHERE t.id=? AND t.issuer_id=?",[$sourceId,$issuer])->row_array();
                if(!$taskParent)throw new DomainException('native_resource_reference_denied');
            }
            $row=IssuerStorage::query($db,"SELECT {$key} AS source_id, {$label} AS label FROM {$prefix}{$table} WHERE {$key}=? AND issuer_id=?".($kind===null?'':' AND kind=?'),$kind===null?[$sourceId,$issuer]:[$sourceId,$issuer,$kind])->row_array();
            if($standaloneParent!==null)NativeCompanyTaskRelationAuthority::confirm($db,$prefix,$issuer,$actor,$sourceId,$standaloneParent);
            if ($source === 'company_task' && $row) {
                // A damaged relation must not make a task from another project visible.
                $owner=IssuerStorage::query($db,"SELECT t.id,t.project_id FROM {$prefix}company_tasks t JOIN {$prefix}company_projects p ON p.id=t.project_id AND p.issuer_id=t.issuer_id WHERE t.id=? AND t.issuer_id=?",[$sourceId,$issuer])->row_array();
                if (!$owner || $owner!==$taskParent || !$acl->can($issuer,$actor,'projects','view')) { throw new DomainException('native_resource_reference_denied'); }
            }
        } elseif (in_array($type,['invoice','credit_note'],true)) {
            // Lineage and historical client ownership are proved before number hydration.
            require_once __DIR__.'/ClientIssuerScope.php';
            $primary=$acl->primaryId()===$issuer;
            $lineage=IssuerStorage::query($db,"SELECT id,issuer_id,clientid FROM {$prefix}{$table} WHERE id=?",[$sourceId])->row_array();
            if(!$lineage)throw new DomainException('native_resource_reference_denied');
            $legacy=$lineage['issuer_id']===null;
            if($legacy){
                if(!$primary||!\app\services\ClientIssuerScope::isLegacyClient($db,$prefix,$lineage['clientid']))throw new DomainException('native_resource_reference_denied');
            }elseif((int)$lineage['issuer_id']!==$issuer)throw new DomainException('native_resource_reference_denied');
            $row=IssuerStorage::query($db,"SELECT id AS source_id,number AS label FROM {$prefix}{$table} WHERE id=? AND ".($legacy?'clientid=? AND issuer_id IS NULL':'issuer_id=?'),$legacy?[$sourceId,$lineage['clientid']]:[$sourceId,$issuer])->row_array();
            if($lineage!==IssuerStorage::query($db,"SELECT id,issuer_id,clientid FROM {$prefix}{$table} WHERE id=?",[$sourceId])->row_array()||($legacy&&(!$primary||$acl->primaryId()!==$issuer||!\app\services\ClientIssuerScope::isLegacyClient($db,$prefix,$lineage['clientid']))))throw new DomainException('native_resource_reference_denied');
        } elseif ($type === 'client') {
            // Ownership projection first: do not hydrate the private client row on a guessed ID.
            $ownerRow=IssuerStorage::query($db,"SELECT owner_issuer_id FROM {$prefix}issuers_clients WHERE client_id=?",[$sourceId])->row_array();
            $owner=$ownerRow?(int)$ownerRow['owner_issuer_id']:null;
            $allowed=$owner===$issuer||($owner===null&&$acl->primaryId()===$issuer);
            if(!$allowed&&$cap==='view')$allowed=(bool)IssuerStorage::query($db,"SELECT client_id FROM {$prefix}issuers_client_links WHERE client_id=? AND issuer_id=?",[$sourceId,$issuer])->row_array();
            if(!$allowed)throw new DomainException('native_resource_reference_denied');
            $row=IssuerStorage::query($db,"SELECT userid AS source_id,company AS label FROM {$prefix}clients WHERE userid=?",[$sourceId])->row_array();
        } elseif($type==='contact') {
            $relation=IssuerStorage::query($db,"SELECT userid FROM {$prefix}contacts WHERE id=?",[$sourceId])->row_array();
            if(!$relation)throw new DomainException('native_resource_reference_denied');
            // Shared customers do not imply access to their contacts or credentials.
            $owner=IssuerStorage::query($db,"SELECT owner_issuer_id FROM {$prefix}issuers_clients WHERE client_id=?",[$relation['userid']])->row_array();
            if($owner?(int)$owner['owner_issuer_id']!==$issuer:$acl->primaryId()!==$issuer)throw new DomainException('native_resource_reference_denied');
            $row=IssuerStorage::query($db,"SELECT id AS source_id,firstname AS label FROM {$prefix}contacts WHERE id=? AND userid=?",[$sourceId,$relation['userid']])->row_array();
            $path='clients/client/'.(int)$relation['userid'].'?group=contacts&contactid=';
        } elseif($type==='staff') {
            require_once __DIR__.'/CompanyStaff.php';try{(new CompanyStaff($db,$prefix))->assertAdministrativeTarget($issuer,$actor,$sourceId,$cap);}catch(DomainException $e){if($e->getMessage()!=='issuer_staff_denied')throw $e;throw new DomainException('native_resource_reference_denied');}
            $row=IssuerStorage::query($db,"SELECT staffid AS source_id,firstname AS label FROM {$prefix}staff WHERE staffid=?",[$sourceId])->row_array();
        } elseif($type==='payment') {
            $relation=IssuerStorage::query($db,"SELECT invoiceid FROM {$prefix}invoicepaymentrecords WHERE id=?",[$sourceId])->row_array();
            if(!$relation)throw new DomainException('native_resource_reference_denied');
            self::resolve($db,$prefix,$issuer,$actor,'invoice',$relation['invoiceid'],'view');
            $row=IssuerStorage::query($db,"SELECT id AS source_id,amount AS label FROM {$prefix}invoicepaymentrecords WHERE id=? AND invoiceid=?",[$sourceId,$relation['invoiceid']])->row_array();
        } else {
            // Legacy resources never become company-owned by inference or a matching ID.
            if ($acl->primaryId()!==$issuer) { throw new DomainException('native_resource_reference_denied'); }
            require_once __DIR__.'/ClientIssuerScope.php';
            require_once __DIR__.'/InvoiceIssuerScope.php';
            require_once __DIR__.'/CustomerRelationBoundary.php';
            require_once __DIR__.'/TaskClientBoundary.php';
            $legacyTaskRelation=null;
            if($table==='tasks'){
                $legacyTicketParent=null;$legacyTaskRelation=IssuerStorage::query($db,'SELECT rel_type,rel_id FROM '.$prefix.'tasks WHERE id=?',[$sourceId])->row_array();
                if(!$legacyTaskRelation||($legacyTaskRelation['rel_type']==='project'&&!$acl->can($issuer,$actor,'projects','view')))throw new DomainException('native_resource_reference_denied');
                if($legacyTaskRelation['rel_type']==='ticket')$legacyTicketParent=self::resolve($db,$prefix,$issuer,$actor,'ticket',$legacyTaskRelation['rel_id'],'view');
            }
            $alias='native_reference';$boundary='1=1';
            if(in_array($table,['contracts','projects','expenses','tickets'],true))$boundary=\app\services\CustomerRelationBoundary::sql($db,$prefix,$table,$alias);
            elseif($table==='tasks')$boundary=\app\services\TaskClientBoundary::legacySql($db,$prefix,$alias);
            elseif(in_array($table,['estimates','proposals'],true)){require_once __DIR__.'/NativeCompanyLegacySalesBoundary.php';$boundary=NativeCompanyLegacySalesBoundary::sql($db,$prefix,$table,$alias);}
            elseif($table==='leads')$boundary=\app\services\ClientIssuerScope::legacySql($db,$prefix,$alias,'client_id');
            $row=IssuerStorage::query($db,"SELECT {$alias}.{$key} AS source_id, {$alias}.{$label} AS label FROM {$prefix}{$table} {$alias} WHERE {$alias}.{$key}=? AND ({$boundary})",[$sourceId])->row_array();
            if($table==='tasks'&&$legacyTicketParent!==null&&self::resolve($db,$prefix,$issuer,$actor,'ticket',$legacyTaskRelation['rel_id'],'view')!==$legacyTicketParent)throw new DomainException('native_resource_reference_denied');
            if($table==='tasks'&&($legacyTaskRelation!==IssuerStorage::query($db,'SELECT rel_type,rel_id FROM '.$prefix.'tasks WHERE id=?',[$sourceId])->row_array()||($legacyTaskRelation['rel_type']==='project'&&!$acl->can($issuer,$actor,'projects','view'))))throw new DomainException('native_resource_reference_denied');
        }
        if($type==='staff'){try{(new CompanyStaff($db,$prefix))->assertAdministrativeTarget($issuer,$actor,$sourceId,$cap);}catch(DomainException $e){if($e->getMessage()!=='issuer_staff_denied')throw $e;throw new DomainException('native_resource_reference_denied');}}
        if (!$row || (string)$row['source_id']!==(string)$sourceId || !$acl->can($issuer,$actor,$feature,$cap)) {
            throw new DomainException('native_resource_reference_denied');
        }
        return $reference + ['feature'=>$feature,'name'=>(string)$row['label'],'url'=>$path.$reference['canonical_id']];
    }
}
