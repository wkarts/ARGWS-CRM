<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerTransaction.php';

/** Dependency inventory for a future native customer deletion writer.
 * A clear result is not sufficient to delete: extensions and all participating
 * writers still require an explicit concurrency contract and independent audit.
 */
final class CompanyCustomerDeletionReferences
{
    private const DIRECT=[
        'contacts'=>'userid','tickets'=>'userid','contracts'=>'client',
        'invoices'=>'clientid','estimates'=>'clientid','creditnotes'=>'clientid',
        'expenses'=>'clientid','projects'=>'clientid','subscriptions'=>'clientid',
        'leads'=>'client_id','vault'=>'customer_id','gdpr_requests'=>'clientid','user_meta'=>'client_id',
        'customer_groups'=>'customer_id','customer_admins'=>'customer_id',
        'issuers_client_links'=>'client_id','company_customer_admins'=>'client_id',
        'company_leads'=>'converted_client_id','company_contracts'=>'client_id',
        'company_tickets'=>'client_id','company_projects'=>'client_id',
        'company_expenses'=>'client_id','company_sales_documents'=>'client_id',
        'company_invoice_drafts'=>'client_id','company_invoice_draft_projection'=>'clientid',
        'company_invoice_copy_requests'=>'source_client_id',
        'company_invoice_native_relations'=>'client_id','asaas_company_payers'=>'client_id',
        'company_standalone_time_clients'=>'client_id',
        'company_customer_file_contents'=>'client_id',
        'company_native_portal_registrations'=>'client_id',
        'company_native_portal_remember'=>'client_id','company_portal_tokens'=>'client_id',
        'company_portal_mail_deliveries'=>'client_id',
        'company_portal_task_origins'=>'client_id','company_portal_task_comments'=>'client_id',
        'company_portal_task_files'=>'client_id','company_portal_task_requests'=>'client_id',
        'company_portal_support_requests'=>'client_id','company_portal_support_files'=>'client_id',
        'company_portal_support_origins'=>'client_id','company_portal_support_replies'=>'client_id',
        'company_ticket_connect_messages'=>'client_id','company_ticket_merge_replies'=>'client_id',
        'company_contract_discussions'=>'client_id',
        'company_project_discussions'=>'client_id','company_project_discussion_posts'=>'client_id',
        'company_project_discussion_requests'=>'client_id','company_discussion_files'=>'client_id',
        'company_discussion_receipt_seals'=>'client_id','company_project_contact_files'=>'client_id',
        'company_project_file_requests'=>'client_id',
        // Bundled modules: records may outlive or refer to native core records.
        'order_master'=>'clientid','acc_account_history'=>'customer',
        'sm_orders'=>'client_id','sm_contracts'=>'client','sm_service_details'=>'client_id',
        'sm_service_invoices'=>'client_id','asaas_customers_map'=>'client_id',
        'ma_point_action_logs'=>'client_id','ma_email_logs'=>'client_id','ma_sms_logs'=>'client_id',
        'ma_campaign_flows'=>'client_id','ma_email_click_logs'=>'client_id',
        'ma_campaign_client_exceptions'=>'client_id','ma_asset_logs'=>'client_id','ma_lead_segments'=>'client_id',
        'company_task_time_batch_origins'=>'client_id',
    ];
    private const POLYMORPHIC=[
        'files'=>['rel_id','rel_type','customer'],
        'notes'=>['rel_id','rel_type','customer'],
        'tasks'=>['rel_id','rel_type','customer'],
        'reminders'=>['rel_id','rel_type','customer'],
        'proposals'=>['rel_id','rel_type','customer'],
        // These core polymorphic stores are normally used for other resource types;
        // fail closed if any installed integration stores customer relationships.
        'form_questions'=>['rel_id','rel_type','customer'],
        'form_results'=>['rel_id','rel_type','customer'],
        'itemable'=>['rel_id','rel_type','customer'],
        'item_tax'=>['rel_id','rel_type','customer'],
        'related_items'=>['rel_id','rel_type','customer'],
        'sales_activity'=>['rel_id','rel_type','customer'],
        'scheduled_emails'=>['rel_id','rel_type','customer'],
        'taggables'=>['rel_id','rel_type','customer'],
        'tracked_mails'=>['rel_id','rel_type','customer'],
        'views_tracking'=>['rel_id','rel_type','customer'],
        'customfieldsvalues'=>['relid','fieldto','customers'],
        'company_custom_field_values'=>['source_id','source_kind','client'],
    ];
    public function __construct(private $db,private string $prefix)
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$prefix))throw new InvalidArgumentException('Invalid table prefix');
    }
    /** These existing tables must join the same owned transaction as deletion. */
    public function tables():array
    {
        $all=array_merge(array_keys(self::DIRECT),array_keys(self::POLYMORPHIC),['company_client_import_batches']);
        return array_values(array_filter(array_unique($all),fn($name)=>$this->db->table_exists($this->prefix.$name)));
    }
    /** Reject any recorded reference, including historical import attestations. */
    public function assertClearInOwned(int $client,array $tables):void
    {
        if($client<1)throw new DomainException('company_client_invalid_input');
        IssuerTransaction::assertOwned($this->db,$this->prefix,$tables);
        if($tables!==$this->tables())throw new RuntimeException('issuer_customer_dependency_schema_changed');
        foreach(self::DIRECT as$table=>$column){
            if(!in_array($table,$tables,true))continue;
            if(!IssuerStorage::fieldExists($this->db,$column,$this->prefix.$table))throw new RuntimeException('issuer_customer_dependency_schema_changed');
            if(IssuerStorage::query($this->db,'SELECT '.$column.' FROM '.$this->prefix.$table.' WHERE '.$column.'=? LIMIT 1 FOR UPDATE',[$client])->result_array())throw new DomainException('company_client_referenced');
        }
        foreach(self::POLYMORPHIC as$table=>[$id,$type,$value]){
            if(!in_array($table,$tables,true))continue;
            if(!IssuerStorage::fieldExists($this->db,$id,$this->prefix.$table)||!IssuerStorage::fieldExists($this->db,$type,$this->prefix.$table))throw new RuntimeException('issuer_customer_dependency_schema_changed');
            $types=array_values(array_unique([$value,'customer','client']));
            if(IssuerStorage::query($this->db,'SELECT '.$id.' FROM '.$this->prefix.$table.' WHERE '.$id.'=? AND '.$type.' IN ('.implode(',',array_fill(0,count($types),'?')).') LIMIT 1 FOR UPDATE',array_merge([$client],$types))->result_array())throw new DomainException('company_client_referenced');
        }
        if(in_array('company_client_import_batches',$tables,true)){
            $table=$this->prefix.'company_client_import_batches';
            if(!IssuerStorage::fieldExists($this->db,'state',$table)||!IssuerStorage::fieldExists($this->db,'result_json',$table))throw new RuntimeException('issuer_customer_dependency_schema_changed');
            foreach(IssuerStorage::query($this->db,'SELECT result_json FROM '.$table.' WHERE state=? FOR UPDATE',['committed'])->result_array() as$row){
                try{$result=json_decode($row['result_json'],true,16,JSON_THROW_ON_ERROR);}catch(Throwable $e){throw new RuntimeException('issuer_customer_dependency_schema_changed');}
                if(!is_array($result)||!isset($result['client_ids'])||!is_array($result['client_ids'])||!array_is_list($result['client_ids']))throw new RuntimeException('issuer_customer_dependency_schema_changed');
                foreach($result['client_ids'] as$id){if(!is_int($id)||$id<1)throw new RuntimeException('issuer_customer_dependency_schema_changed');if($id===$client)throw new DomainException('company_client_referenced');}
            }
        }
    }
}
