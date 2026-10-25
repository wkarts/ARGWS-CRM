<?php
declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerStorage.php';
/** Concrete contact authorship retained by existing CRM domains; never substring-matches personal data. */
final class NativeCompanyContactRetention
{
    public const REFERENCES=[
        'company_native_portal_remember'=>'contact_id','company_native_portal_registrations'=>'contact_id',
        'company_contract_discussions'=>'contact_id','company_project_discussions'=>'contact_id','company_project_discussion_posts'=>'contact_id','company_discussion_files'=>'contact_id',
        'company_portal_task_origins'=>'contact_id','company_portal_task_comments'=>'contact_id','company_portal_task_files'=>'contact_id','company_portal_task_requests'=>'contact_id',
        'company_project_contact_files'=>'contact_id','company_project_file_requests'=>'contact_id',
        'company_portal_support_origins'=>'contact_id','company_portal_support_replies'=>'contact_id','company_portal_support_requests'=>'contact_id','company_portal_support_files'=>'contact_id',
        'company_ticket_connect_messages'=>'contact_id','company_ticket_connect_policies'=>'scan_contact_id',
        'company_leads'=>'converted_contact_id','company_connect_api_messages'=>'contact_id','company_portal_mail_deliveries'=>'contact_id','company_sales_deliveries'=>'contact_id','company_invoice_deliveries'=>'contact_id','company_native_consents'=>'contact_id','gdpr_requests'=>'contact_id',
        'projectdiscussioncomments'=>'contact_id','projectdiscussions'=>'contact_id','project_activity'=>'contact_id','project_files'=>'contact_id','task_comments'=>'contact_id','tickets'=>'contactid','ticket_replies'=>'contactid','files'=>'contact_id','shared_customer_files'=>'contact_id',
    ];
    public static function present($db,string$p,string$table):bool
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p.$table))throw new InvalidArgumentException('Invalid table');$pdo=$db->pdo??$db->conn_id??null;
        $sql=$pdo instanceof PDO&&$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?"SELECT name FROM sqlite_master WHERE type='table' AND name=?":'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?';
        return(bool)IssuerStorage::query($db,$sql,[$p.$table])->result_array();
    }
    public static function existing($db,string$p):array
    {
        $out=[];foreach(array_merge(array_keys(self::REFERENCES),['tasks','task_assigned','company_contract_comment_requests','company_project_discussion_requests','company_discussion_receipt_seals'])as$table)if(self::present($db,$p,$table))$out[]=$table;return$out;
    }
    public static function assertUnreferenced($db,string$p,int$contact):void
    {
        foreach(self::REFERENCES as$table=>$column){
            if(!self::present($db,$p,$table))continue;
            if(IssuerStorage::query($db,'SELECT '.$column.' FROM '.$p.$table.' WHERE '.$column.'=? LIMIT 1',[$contact])->result_array())throw new DomainException('company_contact_history_retained');
        }
        foreach(['company_contract_comment_requests','company_project_discussion_requests','company_discussion_receipt_seals']as$table){
            if(self::present($db,$p,$table)&&IssuerStorage::query($db,"SELECT actor_id FROM ".$p.$table." WHERE actor_id=? AND author_type='contact' LIMIT 1",[$contact])->result_array())throw new DomainException('company_contact_history_retained');
        }
        foreach(['tasks'=>['addedfrom','is_added_from_contact'],'task_assigned'=>['assigned_from','is_assigned_from_contact']]as$table=>$columns){
            if(!self::present($db,$p,$table))continue;
            if(IssuerStorage::query($db,'SELECT '.$columns[0].' FROM '.$p.$table.' WHERE '.$columns[0].'=? AND '.$columns[1].'=1 LIMIT 1',[$contact])->result_array())throw new DomainException('company_contact_history_retained');
        }
    }
}
