<?php
declare(strict_types=1);
namespace BancoBrasil;
require_once __DIR__.'/Origination.php';
require_once __DIR__.'/Cnab400Preparation.php';
require_once __DIR__.'/InvoiceIssuanceSchema.php';
require_once APPPATH.'services/CompanyInvoiceBankInstruction.php';

/** Local-only participant of the canonical invoice transaction. No API, transmission or payment. */
final class InvoiceIssuance
{
    public function __construct(private $db,private string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new \InvalidArgumentException('bb_prefix_invalid');}
    private function rows(string $sql,array $args=[]):array{return \IssuerStorage::query($this->db,$sql,$args)->result_array();}
    public function tables():array{return array_values(array_unique(array_merge((new Cnab400Preparation($this->db,$this->p))->issuanceTables(),['bb_invoice_issuances'])));}
    private function authorize(int $issuer,int $actor):void
    {
        $acl=new \IssuerResourceAccess($this->db,$this->p);$acl->lockFinancialIdentity($issuer,$actor);
        $module=$this->rows('SELECT active,installed_version FROM '.$this->p.'modules WHERE module_name=? FOR UPDATE',['banco_brasil'])[0]??[];
        if((int)($module['active']??0)!==1||($module['installed_version']??null)!==ModuleRelease::VERSION||!$acl->can($issuer,$actor,'invoices','create')||!$acl->can($issuer,$actor,'invoices','view')||!$acl->can($issuer,$actor,'banco_brasil','generate')||!$acl->can($issuer,$actor,'banco_brasil','view'))throw new \DomainException('bb_invoice_issuance_denied');
        InvoiceIssuanceSchema::assertValid($this->db,$this->p);
        (new ChannelPreferences($this->db,$this->p))->assertEnabledInOwned($issuer,$actor,'cnab');
    }
    public function choices(int $issuer,int $actor):array
    {
        if(!$this->db->table_exists($this->p.'bb_invoice_issuances'))return [];
        return \IssuerTransaction::run($this->db,$this->p,$this->tables(),function()use($issuer,$actor):array{
            $this->authorize($issuer,$actor);
            $rows=$this->rows("SELECT d.id,c.account_id,c.agreement_number,c.environment,b.layout_id FROM {$this->p}bb_contract_declarations d JOIN {$this->p}bb_connections c ON c.id=d.connection_id AND c.issuer_id=d.issuer_id JOIN {$this->p}bb_layout_bindings b ON b.id=d.layout_binding_id AND b.issuer_id=d.issuer_id WHERE d.issuer_id=? AND b.layout_id IN (?,?) AND d.id=(SELECT MAX(n.id) FROM {$this->p}bb_contract_declarations n WHERE n.issuer_id=d.issuer_id AND n.connection_id=d.connection_id AND n.layout_binding_id=d.layout_binding_id) ORDER BY d.id DESC LIMIT 100",[$issuer,Agreement::LAYOUT_ID,LayoutCatalog::CNAB400]);
            $acl=new \IssuerResourceAccess($this->db,$this->p);if(!$acl->can($issuer,$actor,'banco_brasil','manage'))$rows=array_values(array_filter($rows,static fn($row)=>$row['layout_id']!==LayoutCatalog::CNAB400));$this->authorize($issuer,$actor);return $rows;
        });
    }
    public function issueInOwned(int $issuer,int $actor,int $invoice,array $instruction):void
    {
        \IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());$this->authorize($issuer,$actor);
        $instruction=\CompanyInvoiceBankInstruction::input($instruction);$contract=$this->rows("SELECT d.*,b.layout_id FROM {$this->p}bb_contract_declarations d JOIN {$this->p}bb_layout_bindings b ON b.id=d.layout_binding_id AND b.issuer_id=d.issuer_id WHERE d.id=? AND d.issuer_id=? FOR UPDATE",[$instruction['contract_id'],$issuer])[0]??null;
        if(!$contract)throw new \DomainException('bb_invoice_issuance_contract_denied');
        $latest=$this->rows("SELECT id FROM {$this->p}bb_contract_declarations WHERE issuer_id=? AND connection_id=? AND layout_binding_id=? ORDER BY id DESC LIMIT 1 FOR UPDATE",[$issuer,$contract['connection_id'],$contract['layout_binding_id']])[0]??[];
        if((int)($latest['id']??0)!==$instruction['contract_id'])throw new \DomainException('bb_invoice_issuance_contract_stale');
        $row=$this->rows('SELECT date FROM '.$this->p.'invoices WHERE id=? AND issuer_id=? FOR UPDATE',[$invoice,$issuer])[0]??null;if(!$row)throw new \DomainException('bb_invoice_issuance_denied');
        $date=(new \DateTimeImmutable('now',new \DateTimeZone($instruction['timezone'])))->format('Y-m-d');
        $connection=(int)$contract['connection_id'];$binding=(int)$contract['layout_binding_id'];$key=hash('sha256','canonical-bb-invoice:'.$issuer.':'.$invoice);
        try {
        if($contract['layout_id']===Agreement::LAYOUT_ID){
            if(isset($instruction['approval400']))throw new \DomainException('bb_invoice_issuance_invalid');
            (new Origination($this->db,$this->p))->generateInvoiceInOwned($issuer,$actor,$connection,$binding,$date,[['invoice_id'=>$invoice]+$instruction['payer']],$key);
        }elseif($contract['layout_id']===LayoutCatalog::CNAB400){
            if(!isset($instruction['approval400']))throw new \DomainException('bb400_homologation_required');
            // Approval remains the operator's explicit instruction, scoped to each new immutable invoice.
            (new Cnab400Preparation($this->db,$this->p))->generateInvoiceInOwned($issuer,$actor,$connection,$binding,$invoice,$date,$instruction['payer'],$instruction['approval400'],$key);
        }else throw new \DomainException('bb_invoice_issuance_invalid');
        } catch (\InvalidArgumentException $error) {
            if($error->getMessage()!=='bb_title_profile_unsupported')throw $error;
            // A rejected title remains an input failure; the owner rolls back invoice and bank state.
            throw new \DomainException('bb_invoice_issuance_invalid',0,$error);
        }
        $origin=$this->rows('SELECT id FROM '.$this->p.'bb_origins WHERE invoice_id=? AND issuer_id=? AND connection_id=? AND layout_binding_id=?',[$invoice,$issuer,$connection,$binding])[0]??null;
        if(!$origin)throw new \RuntimeException('issuer_bb_write_unconfirmed');
        $json=json_encode($instruction,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
        \IssuerStorage::query($this->db,"INSERT INTO {$this->p}bb_invoice_issuances(issuer_id,invoice_id,actor_id,contract_id,origin_id,instruction_json,instruction_hash,created_at) VALUES(?,?,?,?,?,?,?,?)",[$issuer,$invoice,$actor,$instruction['contract_id'],$origin['id'],$json,hash('sha256',$json),gmdate('Y-m-d H:i:s')]);
        $this->confirmInOwned($issuer,$actor,$invoice,$instruction);
    }
    public function confirmInOwned(int $issuer,int $actor,int $invoice,array $instruction):void
    {
        \IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());$this->authorize($issuer,$actor);
        $json=json_encode(\CompanyInvoiceBankInstruction::input($instruction),JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
        $row=$this->rows("SELECT e.*,o.invoice_id origin_invoice,o.issuer_id origin_issuer,s.contract_id origin_contract FROM {$this->p}bb_invoice_issuances e JOIN {$this->p}bb_origins o ON o.id=e.origin_id JOIN {$this->p}bb_origin_snapshots s ON s.origin_id=o.id WHERE e.invoice_id=? AND e.issuer_id=? FOR UPDATE",[$invoice,$issuer])[0]??null;
        if(!$row||(int)$row['actor_id']!==$actor||(int)$row['origin_invoice']!==$invoice||(int)$row['origin_issuer']!==$issuer||(int)$row['origin_contract']!==(int)$instruction['contract_id']||$row['instruction_json']!==$json||$row['instruction_hash']!==hash('sha256',$json))throw new \RuntimeException('issuer_bb_write_unconfirmed');
    }
}
