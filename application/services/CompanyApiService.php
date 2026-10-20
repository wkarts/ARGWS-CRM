<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyApiTokens.php';require_once __DIR__.'/CompanyInvoices.php';

/** Narrow v1 application API. Company and actor come only from the dedicated credential. */
final class CompanyApiService
{
    private CompanyApiTokens $tokens;
    public function __construct(private $db,private string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid table prefix');CompanyApiSchema::assertValid($db,$p);$this->tokens=new CompanyApiTokens($db,$p);}
    private function transaction(array $who,callable $work)
    {
        return IssuerTransaction::run($this->db,$this->p,['issuers_lock','issuers_registry','issuers_installation_identity','issuers_staff','issuers_resource_permissions','staff','company_api_tokens','company_api_token_requests','issuers_audit','invoices','issuers_invoice_documents','currencies'],function()use($who,$work){(new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($who['issuer_id'],$who['actor_id']);return $work();});
    }
    public function invoices(string $secret,$after=0):array
    {
        $who=$this->tokens->resolve($secret,'invoices:read');$after=$after===0||$after==='0'?0:IssuerPolicy::id($after);return $this->transaction($who,function()use($who,$secret,$after):array{$this->tokens->usedInOwnedTransaction($secret,'invoices:read',$who['issuer_id'],$who['actor_id'],'invoice_list');$result=(new CompanyInvoices($this->db,$this->p))->page($who['issuer_id'],$who['actor_id'],$after);$this->tokens->guard($secret,'invoices:read',$who['issuer_id'],$who['actor_id']);return ['issuer_id'=>$who['issuer_id']]+$result;});
    }
    private function invoiceData(string $secret,string $scope,$invoice):array
    {
        $who=$this->tokens->resolve($secret,$scope);$invoice=IssuerPolicy::id($invoice);$tokenService=$this->tokens;
        $documents=new InvoiceIssuerDocuments($this->db,$this->p,$who['actor_id'],static function($issuer,$actor,$cap)use($tokenService,$secret,$scope,$who):bool{if($issuer!==$who['issuer_id']||$actor!==$who['actor_id']||!in_array($cap,['render_invoices','view_invoices'],true))return false;$tokenService->guard($secret,$scope,$issuer,$actor);return true;});
        $dto=$documents->forPdf($invoice,$who['issuer_id']);$this->transaction($who,function()use($secret,$scope,$who,$invoice):void{$this->tokens->usedInOwnedTransaction($secret,$scope,$who['issuer_id'],$who['actor_id'],$scope==='invoices:pdf'?'invoice_pdf':'invoice_read',null,['invoice_id'=>$invoice]);});$this->confirmInvoiceData($documents,$dto,$invoice,$who['issuer_id']);return [$who,$dto,$documents];
    }
    /** Read again through the canonical source: token, actor and persisted origin remain current. */
    private function confirmInvoiceData(InvoiceIssuerDocuments $documents,InvoiceIssuerDocumentData $dto,int $invoice,int $issuer):void
    {
        if($documents->forPdf($invoice,$issuer)->data()!==$dto->data())throw new DomainException('issuer_document_scope_mismatch');
    }
    public function invoice(string $secret,$invoice):array{[$who,$dto]=$this->invoiceData($secret,'invoices:read',$invoice);return ['issuer_id'=>$who['issuer_id'],'document'=>$dto->data()];}
    public function invoicePdf(string $secret,$invoice):string
    {
        [$who,$dto,$documents]=$this->invoiceData($secret,'invoices:pdf',$invoice);if(!class_exists('TCPDF'))require_once APPPATH.'vendor/tecnickcom/tcpdf/tcpdf.php';require_once APPPATH.'libraries/pdf/Issuer_invoice_pdf.php';$bytes=(new Issuer_invoice_pdf($dto))->prepare()->Output('','S');$this->confirmInvoiceData($documents,$dto,IssuerPolicy::id($invoice),$who['issuer_id']);return $bytes;
    }
    public function createInvoice(string $secret,array $input,string $idempotencyKey):array
    {
        if(!preg_match('/^[a-f0-9]{64}$/D',$idempotencyKey))throw new DomainException('company_api_invalid_input');$who=$this->tokens->resolve($secret,'invoices:create');$issuer=$who['issuer_id'];$actor=$who['actor_id'];$key=hash('sha256','company-api-invoice|'.$issuer.'|'.$actor.'|'.$idempotencyKey);$audited=false;$replayed=false;
        $guard=function()use($secret,$issuer,$actor,$key,&$audited,&$replayed):void{$this->tokens->guard($secret,'invoices:create',$issuer,$actor);if(!$audited){$replayed=IssuerStorage::query($this->db,'SELECT request_key FROM '.$this->p.'company_invoice_requests WHERE request_key=? AND issuer_id=? AND actor_id=?',[$key,$issuer,$actor])->result_array()!==[];$this->tokens->usedInOwnedTransaction($secret,'invoices:create',$issuer,$actor,'invoice_create',$key);$audited=true;}};
        $id=(new CompanyInvoices($this->db,$this->p))->create($issuer,$actor,$input,$key,$guard,['company_api_tokens','company_api_token_requests']);return ['issuer_id'=>$issuer,'invoice_id'=>$id,'replayed'=>$replayed];
    }
    public function financial(string $secret,bool $csv=false)
    {
        $who=$this->tokens->resolve($secret,'reports:read');$audited=false;$guard=function()use($secret,$who,&$audited):void{$this->tokens->guard($secret,'reports:read',$who['issuer_id'],$who['actor_id']);if(!$audited){$this->tokens->usedInOwnedTransaction($secret,'reports:read',$who['issuer_id'],$who['actor_id'],'financial_report');$audited=true;}};$reports=new CompanyReports($this->db,$this->p,$guard,['company_api_tokens','company_api_token_requests']);return $csv?$reports->csv($who['issuer_id'],$who['actor_id']):$reports->financialPosition($who['issuer_id'],$who['actor_id']);
    }
}
