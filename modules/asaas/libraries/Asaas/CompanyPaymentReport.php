<?php
declare(strict_types=1);
namespace Asaas;
require_once __DIR__.'/CompanyService.php';

/** 1.5.8 reporting semantics adapted to a named account. No global gateway or financial writes. */
final class CompanyPaymentReport
{
    private const MAX_ROWS=10000;
    private const MAX_BYTES=8388608;
    private \Closure $clock;
    public function __construct(private $db,private string $prefix,?callable $clock=null)
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$prefix))throw new \InvalidArgumentException('asaas_company_prefix_invalid');
        $this->clock=$clock===null?static fn():float=>microtime(true):\Closure::fromCallable($clock);
    }
    private function context(int $issuer,int $actor,int $account,string $revision):array
    {
        $service=new CompanyService($this->db,$this->prefix);
        // Dedicated company grants never imply settings editing, credentials or financial writes.
        $service->authorize($issuer,$actor,'configuration');$service->authorize($issuer,$actor,'query');
        $acl=new \IssuerResourceAccess($this->db,$this->prefix);
        if(!$acl->can($issuer,$actor,'asaas_reports','view')||!$acl->can($issuer,$actor,'asaas_reports','export'))throw new \DomainException('asaas_report_access_denied');
        if(class_exists('CompanySettingsRuntime',false)&&\CompanySettingsRuntime::active()&&(\CompanySettingsRuntime::issuerId()!==$issuer||\CompanySettingsRuntime::staffId()!==$actor))throw new \DomainException('asaas_company_access_denied');
        $version=\IssuerStorage::query($this->db,'SELECT installed_version FROM '.$this->prefix.'modules WHERE module_name=?',['asaas'])->row_array()['installed_version']??'0';
        if(version_compare($version,'1.8.7','<'))throw new \DomainException('asaas_module_187_required');
        foreach($service->accounts($issuer,$actor) as $row)if((int)$row['id']===$account){
            if(!hash_equals($row['revision'],$revision)||(int)$row['enabled']!==1||$row['verified_at']===null)throw new \DomainException('asaas_company_stale');
            return ['issuer_id'=>$issuer,'account_row'=>$account,'account_id'=>$row['account_id'],'environment'=>$row['environment'],'revision'=>$revision];
        }
        throw new \DomainException('asaas_company_access_denied');
    }
    public static function csvText(string $value):string
    {
        // Same spreadsheet formula boundary as reference 1.5.8, including leading Unicode space.
        $value=preg_replace('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/u','',$value);
        if(!is_string($value))throw new \DomainException('asaas_report_invalid_text');
        return preg_match('/^[\s\p{Z}\p{C}]*[=+@-]/u',$value)?"'".$value:$value;
    }
    public function export($issuer,$actor,$account,string $revision,array $filters,callable $decrypt,?callable $transport=null):array
    {
        $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);$account=\IssuerPolicy::id($account);
        PaymentListQuery::path($filters); // Validate caller input before normalizing export to the first page.
        $filters['offset']=0;$scope=$this->context($issuer,$actor,$account,$revision);
        $service=new CompanyService($this->db,$this->prefix);$deadline=($this->clock)()+45;$started=gmdate('c');
        $stream=tmpfile();if($stream===false)throw new \RuntimeException('asaas_report_storage_failed');
        try{
            $meta=stream_get_meta_data($stream);if(!isset($meta['uri'])||!chmod($meta['uri'],0600))throw new \RuntimeException('asaas_report_storage_failed');
            $write=static function(array $row)use($stream):void{if(fputcsv($stream,$row,';','"','')===false)throw new \RuntimeException('asaas_report_storage_failed');};
            if(fwrite($stream,"\xEF\xBB\xBF")!==3)throw new \RuntimeException('asaas_report_storage_failed');
            $write(['Empresa','Conta local','Carteira Asaas','Ambiente','Consulta iniciada em','Filtros','Cobrança','Situação remota','Meio','Valor remoto (R$)','Origem local','Fatura local']);
            $seen=[];$expected=null;$count=0;
            $budget=function()use($deadline,$stream,&$count):void{$position=ftell($stream);if(($this->clock)()>$deadline||$count>self::MAX_ROWS||$position===false||$position>self::MAX_BYTES)throw new \DomainException('asaas_report_limit');};
            do{
                $budget();if($this->context($issuer,$actor,$account,$revision)!==$scope)throw new \DomainException('asaas_company_stale');
                $page=$service->remotePayments($issuer,$actor,$account,$revision,$filters,$decrypt,$transport);
                $budget();if($this->context($issuer,$actor,$account,$revision)!==$scope)throw new \DomainException('asaas_company_stale');
                if($expected!==null&&$expected!==$page['total'])throw new \DomainException('asaas_report_changed');
                $expected=$page['total'];if($expected>self::MAX_ROWS)throw new \DomainException('asaas_report_limit');
                $description=$filters;unset($description['offset']);
                foreach($page['items'] as $row){
                    if(isset($seen[$row['remote_id']]))throw new \DomainException('asaas_report_repeated_page');$seen[$row['remote_id']]=true;
                    $write(array_map([self::class,'csvText'],[(string)$issuer,(string)$account,$scope['account_id'],$scope['environment'],$started,json_encode($description,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$row['remote_id'],$row['status'],$row['billing_type'],str_replace('.',',',$row['value']),(string)($row['origin_id']??''),(string)($row['invoice_id']??'')]));
                    $count++;$budget();
                }
                $filters['offset']=$page['next_offset'];
            }while($page['next_offset']!==null);
            if($count!==$expected)throw new \DomainException('asaas_report_incomplete');
            if($this->context($issuer,$actor,$account,$revision)!==$scope)throw new \DomainException('asaas_company_stale');
            $budget();if(!fflush($stream)||!rewind($stream))throw new \RuntimeException('asaas_report_storage_failed');
            $bytes=stream_get_contents($stream);if(!is_string($bytes))throw new \RuntimeException('asaas_report_storage_failed');
            return ['filename'=>'asaas-cobrancas-'.$issuer.'-'.$account.'-'.$scope['environment'].'.csv','bytes'=>$bytes,'rows'=>$count,'scope'=>$scope,'started_at'=>$started];
        }finally{fclose($stream);}
    }
}
