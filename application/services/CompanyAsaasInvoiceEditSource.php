<?php
declare(strict_types=1);
require_once FCPATH.'modules/asaas/libraries/Asaas/CompanyInvoiceEdits.php';
/** This exception is deliberately thrown inside the owned native writer to roll back its preview. */
final class CompanyAsaasInvoiceEditPreview extends DomainException
{
    public function __construct(public readonly array $before,public readonly array $after){parent::__construct('asaas_invoice_edit_preview_rolled_back');}
}
/** Closed authority: canonical preview always rolls back; application requires a confirmed durable PUT. */
final class CompanyAsaasInvoiceEditSource
{
    private ?array $before=null;
    private bool $bound=false;
    public function __construct(private $db,private string $p,private int $i,private int $a,private int $o,private array $request,private bool $preview){}
    public function origin():int{return $this->o;}
    public function tables():array{return (new \Asaas\CompanyInvoiceEdits($this->db,$this->p))->tables();}
    public function bind(array $payload,array $metadata,string $key,string $revision):void
    {
        if([$payload,$metadata,$key,$revision]!==[$this->request['payload'],$this->request['metadata'],$this->request['key'],$this->request['revision']])throw new DomainException('asaas_invoice_edit_input_conflict');$this->bound=true;
    }
    public function assertBefore($db,string $p,int $i,int $a,int $invoice,string $revision,array $data):void
    {
        if(!$this->bound||$db!==$this->db||[$p,$i,$a,$invoice,$revision]!==[$this->p,$this->i,$this->a,$this->request['invoice'],$this->request['revision']]||$data!==CompanyInvoices::previewInput($this->request['payload']))throw new DomainException('asaas_invoice_edit_scope');
        $this->before=(new \Asaas\CompanyInvoiceEdits($db,$p))->reviewInOwned($i,$a,$this->o,$this->request,$this->preview);
    }
    public function finished($db,string $p,int $i,int $a,int $invoice):void
    {
        if($this->before===null||$db!==$this->db||[$p,$i,$a,$invoice]!==[$this->p,$this->i,$this->a,$this->request['invoice']])throw new DomainException('asaas_invoice_edit_scope');
        $after=(new \Asaas\CompanyService($db,$p))->editSnapshotInOwnedTransaction($i,$a,$invoice,array_key_exists('asaas_profile_contract',$this->before));
        \Asaas\CompanyInvoiceEditJournal::snapshots($this->before,$after);
        if($this->preview)throw new CompanyAsaasInvoiceEditPreview($this->before,$after);
        (new \Asaas\CompanyInvoiceEdits($db,$p))->appliedInOwned($i,$a,$this->request['key'],$after);
    }
}
