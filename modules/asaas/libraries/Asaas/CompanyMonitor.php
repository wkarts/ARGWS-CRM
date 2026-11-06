<?php
declare(strict_types=1);
namespace Asaas;
require_once __DIR__.'/CompanyService.php';

/** Paginated local observations. Never queries a provider or applies receipts. */
final class CompanyMonitor
{
    public const LIMIT=50;
    public function __construct(private $db,private string $prefix)
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$prefix))throw new \InvalidArgumentException('asaas_company_prefix_invalid');
    }
    private function rows(string $sql,array $args=[]):array{return \IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private function authorize(int $issuer,int $actor):void
    {
        if(class_exists('CompanySettingsRuntime',false)&&\CompanySettingsRuntime::active()&&(\CompanySettingsRuntime::issuerId()!==$issuer||\CompanySettingsRuntime::staffId()!==$actor))throw new \DomainException('asaas_company_access_denied');
        $service=new CompanyService($this->db,$this->prefix);$service->authorize($issuer,$actor,'configuration');
        if(!(new \IssuerResourceAccess($this->db,$this->prefix))->can($issuer,$actor,'invoices','view'))throw new \DomainException('asaas_company_access_denied');
        $version=$this->rows('SELECT installed_version FROM '.$this->prefix.'modules WHERE module_name=?',['asaas'])[0]['installed_version']??'0';
        if(version_compare($version,'1.7.4','<'))throw new \DomainException('asaas_module_174_required');
    }
    public function page($issuer,$actor,array $filters=[]):array
    {
        $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);$this->authorize($issuer,$actor);
        if(array_diff(array_keys($filters),['kind','account','before','state']))throw new \DomainException('asaas_monitor_filters_invalid');
        $kind=$filters['kind']??'origins';$state=$filters['state']??'';
        if(!is_string($kind)||!in_array($kind,['origins','events','jobs'],true)||!is_string($state)||!preg_match('/^[A-Za-z0-9_]{0,32}$/D',$state))throw new \DomainException('asaas_monitor_filters_invalid');
        $account=($filters['account']??'')===''?null:\IssuerPolicy::id($filters['account']);
        $before=($filters['before']??'')===''?null:\IssuerPolicy::id($filters['before']);
        if($account!==null&&!$this->rows('SELECT id FROM '.$this->prefix.'asaas_company_accounts WHERE id=? AND issuer_id=?',[$account,$issuer]))throw new \DomainException('asaas_company_access_denied');
        $p=$this->prefix;
        [$select,$from,$id,$status]=match($kind){
            'origins'=>['o.id,o.invoice_id,o.remote_id,o.state,m.billing_type,m.created_at,a.id AS account_row,a.environment',
                $p.'asaas_payment_origins o JOIN '.$p.'asaas_company_origin_meta m ON m.origin_id=o.id JOIN '.$p.'asaas_company_accounts a ON a.id=m.account_row AND a.issuer_id=o.issuer_id','o.id','o.state'],
            'events'=>['e.id,e.event_id,e.event_type,e.remote_id,e.state,e.created_at,a.id AS account_row,a.environment',
                $p.'asaas_company_events e JOIN '.$p.'asaas_company_accounts a ON a.id=e.account_row','e.id','e.state'],
            'jobs'=>['j.event_id AS id,j.origin_id,j.state,j.attempts,j.last_code,j.updated_at AS created_at,a.id AS account_row,a.environment',
                $p.'asaas_company_jobs j JOIN '.$p.'asaas_company_events e ON e.id=j.event_id JOIN '.$p.'asaas_company_accounts a ON a.id=e.account_row AND a.issuer_id=j.issuer_id','j.event_id','j.state'],
        };
        $where=['a.issuer_id=?'];$args=[$issuer];
        if($account!==null){$where[]='a.id=?';$args[]=$account;}
        if($before!==null){$where[]=$id.'<?';$args[]=$before;}
        if($state!==''){$where[]=$status.'=?';$args[]=$state;}
        $rows=$this->rows('SELECT '.$select.' FROM '.$from.' WHERE '.implode(' AND ',$where).' ORDER BY '.$id.' DESC LIMIT '.(self::LIMIT+1),$args);
        $hasMore=count($rows)>self::LIMIT;$rows=array_slice($rows,0,self::LIMIT);
        $this->authorize($issuer,$actor);
        return ['kind'=>$kind,'account'=>$account,'state'=>$state,'before'=>$before,'rows'=>$rows,'next_before'=>$hasMore?(int)$rows[array_key_last($rows)]['id']:null];
    }
}
