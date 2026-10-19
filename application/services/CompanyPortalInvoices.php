<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyPortal.php';
require_once __DIR__.'/issuers/InvoiceIssuerDocumentData.php';

/** Read-only named-company invoices, private contact permission and native document DTO; no legacy finance hydration. */
final class CompanyPortalInvoices
{
    public function __construct(private $db,private string $p,private CompanyPortal $portal)
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid table prefix');
    }
    private function rows(string $sql,array $args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private function transaction(callable $work)
    {
        return IssuerTransaction::run($this->db,$this->p,['issuers_lock','issuers_registry','issuers_installation_identity','issuers_clients','contacts','clients','contact_permissions','invoices','issuers_invoice_documents','currencies','issuers_audit'],$work);
    }
    private function identity(array $session,int $issuer,bool $lock):?array
    {
        $who=$this->portal->invoiceIdentity($session,$issuer,$lock);if(!$who)return null;
        $permission=$this->rows('SELECT id FROM '.$this->p.'contact_permissions WHERE userid=? AND permission_id=1'.($lock?' FOR UPDATE':''),[$who['contact_id']]);
        return count($permission)===1?$who:null;
    }
    private function invoice(array $session,int $issuer,int $invoice,bool $lock):array
    {
        $who=$this->identity($session,$issuer,$lock);if(!$who)throw new DomainException('company_portal_invoice_unavailable');
        if(!$this->rows('SELECT id FROM '.$this->p.'invoices WHERE id=? AND issuer_id=? AND clientid=?'.($lock?' FOR UPDATE':''),[$invoice,$issuer,$who['client_id']]))throw new DomainException('company_portal_invoice_unavailable');return $who;
    }
    /** NULL means no financial permission; an empty authorized list is distinct in the UI. */
    public function page(array $session,$issuer,$before=0):?array
    {
        $issuer=IssuerPolicy::id($issuer);if((!is_int($before)&&!is_string($before))||!preg_match('/^(0|[1-9][0-9]*)$/D',(string)$before)||(string)(int)$before!==(string)$before)throw new DomainException('company_portal_invoice_unavailable');$before=(int)$before;
        return $this->transaction(function()use($session,$issuer,$before):?array{
            $who=$this->identity($session,$issuer,true);if(!$who)return null;
            $sql='SELECT i.id,d.formatted_number number,i.date,i.duedate,i.status,i.total,c.name currency FROM '.$this->p.'invoices i JOIN '.$this->p.'issuers_invoice_documents d ON d.invoice_id=i.id AND d.issuer_id=i.issuer_id JOIN '.$this->p.'currencies c ON c.id=i.currency WHERE i.issuer_id=? AND i.clientid=?'.($before?' AND i.id<?':'').' ORDER BY i.id DESC LIMIT 51';$args=[$issuer,$who['client_id']];if($before)$args[]=$before;$rows=$this->rows($sql,$args);$next=count($rows)>50?(int)$rows[49]['id']:null;
            return ['items'=>array_slice($rows,0,50),'next'=>$next];
        });
    }
    private function assertEngines():void
    {
        $pdo=$this->db->pdo??$this->db->conn_id??null;if($pdo instanceof PDO&&$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite')return;
        $tables=array_map(fn($s)=>$this->p.$s,['contacts','clients','contact_permissions','issuers_clients','issuers_installation_identity']);$rows=$this->rows('SELECT TABLE_NAME name,ENGINE engine FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('.implode(',',array_fill(0,count($tables),'?')).')',$tables);$engines=[];foreach($rows as $row)$engines[$row['name']]=strtoupper($row['engine']);foreach($tables as $table)if(($engines[$table]??null)!=='INNODB')throw new RuntimeException('issuer_transactional_storage_required');
    }
    public function document(array $session,$issuer,$invoice):InvoiceIssuerDocumentData
    {
        $issuer=IssuerPolicy::id($issuer);$invoice=IssuerPolicy::id($invoice);$this->assertEngines();$who=$this->invoice($session,$issuer,$invoice,false);
        // The callback rechecks native permission and owner before and inside the document's owned transaction.
        $documents=new InvoiceIssuerDocuments($this->db,$this->p,$who['contact_id'],function($i,$contact,$cap)use($session,$issuer,$invoice,$who):bool{
            if($i!==$issuer||$contact!==$who['contact_id']||!in_array($cap,['view_invoices','render_invoices'],true))return false;
            try{$this->invoice($session,$issuer,$invoice,false);return true;}catch(DomainException $error){return false;}
        });
        return InvoiceIssuerDocumentData::fromInvoice($documents,$invoice,$issuer);
    }
    /** Called after rendering, before releasing PDF bytes. Permission revocation/audit failure denies delivery. */
    public function confirmDownload(array $session,$issuer,$invoice,string $contentHash):void
    {
        $issuer=IssuerPolicy::id($issuer);$invoice=IssuerPolicy::id($invoice);if(!preg_match('/^[a-f0-9]{64}$/D',$contentHash))throw new DomainException('company_portal_invoice_unavailable');
        $this->transaction(function()use($session,$issuer,$invoice,$contentHash):void{
            $who=$this->invoice($session,$issuer,$invoice,true);$event='company_portal_invoice_pdf_downloaded';
            $metadata=json_encode(['contact_id'=>$who['contact_id'],'client_id'=>$who['client_id'],'sha256'=>$contentHash,'receipt'=>bin2hex(random_bytes(16))],JSON_THROW_ON_ERROR);
            IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at,metadata_json) VALUES(0,?,?,?,?,?)',[$event,$issuer,$invoice,date('Y-m-d H:i:s'),$metadata]);
            $id=(int)$this->db->insert_id();if(count($this->rows('SELECT id FROM '.$this->p.'issuers_audit WHERE id=? AND issuer_id=? AND subject_id=? AND event=? AND metadata_json=?',[$id,$issuer,$invoice,$event,$metadata]))!==1)throw new RuntimeException('issuer_portal_write_unconfirmed');
        });
    }
}
