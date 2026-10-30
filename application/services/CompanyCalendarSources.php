<?php

declare(strict_types=1);
/** Only canonical private sources; no legacy events/reminders/Google and no implied grant. */
final class CompanyCalendarSources
{
    public static function definitions():array{return [
        'invoices'=>['feature'=>'invoices','toggle'=>'show_invoices_on_calendar','color'=>'calendar_invoice_color','label'=>'Faturas','table'=>'invoices'],
        'estimates'=>['feature'=>'estimates','toggle'=>'show_estimates_on_calendar','color'=>'calendar_estimate_color','label'=>'Orçamentos','table'=>'company_sales_documents'],
        'proposals'=>['feature'=>'proposals','toggle'=>'show_proposals_on_calendar','color'=>'calendar_proposal_color','label'=>'Propostas','table'=>'company_sales_documents'],
        'projects'=>['feature'=>'projects','toggle'=>'show_projects_on_calendar','color'=>'calendar_project_color','label'=>'Projetos','table'=>'company_projects'],
        'project_tasks'=>['feature'=>'tasks','toggle'=>'show_tasks_on_calendar','color'=>'calendar_task_color','label'=>'Tarefas de projeto','table'=>'company_tasks'],
        'standalone_tasks'=>['feature'=>'tasks','toggle'=>'show_tasks_on_calendar','color'=>'calendar_task_color','label'=>'Tarefas avulsas','table'=>'company_standalone_tasks'],
        'contracts'=>['feature'=>'contracts','toggle'=>'show_contracts_on_calendar','color'=>'calendar_contract_color','label'=>'Contratos','table'=>'company_contracts'],
    ];}
    public static function available($db,string $p,string $source):bool
    {
        $d=self::definitions()[$source]??null;if(!$d)return false;$tables=[$d['table']];if($source==='invoices')$tables[]='issuers_invoice_documents';if($source==='project_tasks')$tables[]='company_projects';foreach($tables as$table)if(!$db->table_exists($p.$table)||!IssuerStorage::fieldExists($db,'issuer_id',$p.$table))return false;return true;
    }
    public static function allowed($db,string $p,int $issuer,int $actor,string $source):bool
    {
        $d=self::definitions()[$source]??null;if(!$d)throw new DomainException('company_calendar_invalid');$acl=new IssuerResourceAccess($db,$p);return$acl->can($issuer,$actor,$d['feature'],'view')&&($source!=='project_tasks'||$acl->can($issuer,$actor,'projects','view'));
    }
}
