<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyPortalSales.php';
require_once __DIR__.'/CompanySales.php';

/** Recoverable automatic invoice conversion, bound to the exact contact decision. */
final class NativeCompanyEstimateAutoConvert
{
    private ?array $active=null;
    public function __construct(private $db,private string $p,private CompanyPortal $portal){}
    private function tables():array
    {
        $tables=['issuers_lock','issuers_registry','issuers_installation_identity','issuers_clients','contacts','clients','contact_permissions','company_sales_documents','issuers_audit'];
        foreach(['company_sales_revisions','company_estimate_signatures'] as$table)if($this->db->table_exists($this->p.$table))$tables[]=$table;return $tables;
    }
    /** Only this active, contact-bound compositor may attest the local invoice origin. */
    public function assertConversionInOwned($db,string $prefix,int $issuer,int $actor,int $id):void
    {
        $frame=$this->active;if($db!==$this->db||$prefix!==$this->p||!$frame||$frame['issuer']!==$issuer||$frame['id']!==$id||$frame['reference']['actor_id']!==$actor)throw new DomainException('native_estimate_auto_convert_denied');
        if($frame['reader']->autoConversionReferenceInOwned($frame['session'],$issuer,$id,$frame['request_key'])!==$frame['reference'])throw new DomainException('native_estimate_auto_convert_denied');
    }
    public function process(array $session,int $issuer,int $id,string $requestKey):array
    {
        IssuerPolicy::id($issuer);IssuerPolicy::id($id);if(!preg_match('/^[a-f0-9]{64}$/D',$requestKey))throw new DomainException('native_estimate_auto_convert_denied');
        $reader=new CompanyPortalSales($this->db,$this->p,$this->portal);$tables=$this->tables();
        $reference=IssuerTransaction::run($this->db,$this->p,$tables,fn()=>$reader->autoConversionReferenceInOwned($session,$issuer,$id,$requestKey));
        if(!$reference['requested'])return ['requested'=>false,'completed'=>true];
        $guard=function()use($reader,$session,$issuer,$id,$requestKey,$reference):void{if($reader->autoConversionReferenceInOwned($session,$issuer,$id,$requestKey)!==$reference)throw new DomainException('native_estimate_auto_convert_denied');};
        // The captured creator is reauthorized by the canonical writer at every stage.
        $this->active=['issuer'=>$issuer,'id'=>$id,'reference'=>$reference,'reader'=>$reader,'session'=>$session,'request_key'=>$requestKey];
        try{(new CompanySales($this->db,$this->p))->convert($issuer,$reference['actor_id'],$id,null,$guard,$tables,$this);}finally{$this->active=null;}
        return ['requested'=>true,'completed'=>true];
    }
}
