<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyPortal.php';
require_once __DIR__.'/CompanyCurrencyUnits.php';
require_once __DIR__.'/CompanyInvoiceAttachmentSchema.php';
require_once __DIR__.'/issuers/InvoiceIssuerDocumentData.php';

/** Read-only named-company invoices, private contact permission and native document DTO; no legacy finance hydration. */
final class CompanyPortalInvoices
{
    public function __construct(private $db,private string $p,private CompanyPortal $portal)
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid table prefix');
    }
    private function rows(string $sql,array $args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    public function tableNames():array
    {
        $extra=[];if($this->db->table_exists($this->p.'company_invoice_attachments')){CompanyInvoiceAttachmentSchema::assertValid($this->db,$this->p);$extra[]='company_invoice_attachments';}
        return array_merge($extra,['issuers_lock','issuers_registry','issuers_installation_identity','issuers_clients','contacts','clients','contact_permissions','invoices','issuers_invoice_documents','currencies','company_currency_units','issuers_audit']);
    }
    private function transaction(callable $work){return IssuerTransaction::run($this->db,$this->p,$this->tableNames(),$work);}
    private function identity(array $session,int $issuer,bool $lock):?array
    {
        $who=$this->portal->invoiceIdentity($session,$issuer,$lock);if(!$who)return null;
        $permission=$this->rows('SELECT id FROM '.$this->p.'contact_permissions WHERE userid=? AND permission_id=1'.($lock?' FOR UPDATE':''),[$who['contact_id']]);
        return count($permission)===1?$who:null;
    }
    private function invoice(array $session,int $issuer,int $invoice,bool $lock):array
    {
        $who=$this->identity($session,$issuer,$lock);if(!$who)throw new DomainException('company_portal_invoice_unavailable');
        if(!$this->rows('SELECT id FROM '.$this->p.'invoices WHERE id=? AND issuer_id=? AND clientid=? AND status IN (1,2,3,4,5)'.($lock?' FOR UPDATE':''),[$invoice,$issuer,$who['client_id']]))throw new DomainException('company_portal_invoice_unavailable');return $who;
    }
    /** Authority for the private blob reader, only inside its own transaction on this connection. */
    public function attachmentIdentityInOwnedTransaction(array $session,int $issuer,int $invoice):array
    {
        IssuerTransaction::assertOwned($this->db,$this->p,['issuers_registry','issuers_installation_identity','issuers_clients','clients','contacts','contact_permissions','invoices','issuers_invoice_documents','company_invoice_attachments','issuers_audit']);
        $who=$this->invoice($session,$issuer,$invoice,true);
        if(!$this->rows('SELECT invoice_id FROM '.$this->p.'issuers_invoice_documents WHERE invoice_id=? AND issuer_id=?',[$invoice,$issuer]))throw new DomainException('company_portal_invoice_unavailable');return $who;
    }
    /** NULL means no financial permission; an empty authorized list is distinct in the UI. */
    public function page(array $session,$issuer,$before=0,$status=0):?array
    {
        $issuer=IssuerPolicy::id($issuer);if((!is_int($before)&&!is_string($before))||!preg_match('/^(0|[1-9][0-9]*)$/D',(string)$before)||(string)(int)$before!==(string)$before)throw new DomainException('company_portal_invoice_unavailable');$before=(int)$before;
        if((!is_int($status)&&!is_string($status))||!preg_match('/^[0-6]$/D',(string)$status))throw new DomainException('company_portal_invoice_unavailable');$status=(int)$status;
        return $this->transaction(fn()=>$this->pageInOwnedTransaction($session,$issuer,$before,$status));
    }
    /** Composition with other issuer-bound list projections on the same connection. */
    public function pageInOwnedTransaction(array $session,int $issuer,int $before=0,int $status=0):?array
    {
        if($issuer<1||$before<0||$status<0||$status>6)throw new DomainException('company_portal_invoice_unavailable');
        IssuerTransaction::assertOwned($this->db,$this->p,$this->tableNames());
            $who=$this->identity($session,$issuer,true);if(!$who)return null;
            $sql='SELECT i.id,d.formatted_number number,i.date,i.duedate,i.status,i.total,i.hash,i.currency currency_id FROM '.$this->p.'invoices i JOIN '.$this->p.'issuers_invoice_documents d ON d.invoice_id=i.id AND d.issuer_id=i.issuer_id WHERE i.issuer_id=? AND i.clientid=? AND i.status IN (1,2,3,4,5)'.($before?' AND i.id<?':'').($status?' AND i.status=?':'').' ORDER BY i.id DESC LIMIT 51';$args=[$issuer,$who['client_id']];if($before)$args[]=$before;if($status)$args[]=$status;$rows=$this->rows($sql,$args);$next=count($rows)>50?(int)$rows[49]['id']:null;
            $rows=array_slice($rows,0,50);
            $units=new CompanyCurrencyUnits($this->db,$this->p);foreach($rows as &$row){$row['currency']=$units->resolveRead($row['currency_id'])['iso_code'];unset($row['currency_id']);}unset($row);
            $attachments=[];
            if($rows&&$this->db->table_exists($this->p.'company_invoice_attachments')){
                $ids=array_map('intval',array_column($rows,'id'));
                $files=$this->rows('SELECT invoice_id,file_key,filename,mime,byte_size FROM '.$this->p.'company_invoice_attachments WHERE issuer_id=? AND invoice_id IN ('.implode(',',array_fill(0,count($ids),'?')).') AND active=1 AND portal_visible=1 ORDER BY created_at,file_key LIMIT 1001',array_merge([$issuer],$ids));
                if(count($files)>1000)throw new RuntimeException('issuer_invoice_attachment_limit_invalid');
                foreach($files as $file){$parent=(int)$file['invoice_id'];if(count($attachments[$parent]??[])>=20)throw new RuntimeException('issuer_invoice_attachment_limit_invalid');unset($file['invoice_id']);$attachments[$parent][]=$file;}
            }
            foreach($rows as &$row)$row['attachments']=$attachments[(int)$row['id']]??[];unset($row);
            if($this->identity($session,$issuer,true)!==$who)throw new DomainException('company_portal_invoice_unavailable');return ['items'=>$rows,'next'=>$next];
    }
    private function assertEngines():void
    {
        $pdo=$this->db->pdo??$this->db->conn_id??null;if($pdo instanceof PDO&&$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite')return;
        $tables=array_map(fn($s)=>$this->p.$s,['contacts','clients','contact_permissions','issuers_clients','issuers_installation_identity']);$rows=$this->rows('SELECT TABLE_NAME name,ENGINE engine FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('.implode(',',array_fill(0,count($tables),'?')).')',$tables);$engines=[];foreach($rows as $row)$engines[$row['name']]=strtoupper($row['engine']);foreach($tables as $table)if(($engines[$table]??null)!=='INNODB')throw new RuntimeException('issuer_transactional_storage_required');
    }
    /** Explicit authority for the shared financial reader, bound to this exact connection and prefix. */
    public function balanceTables():array{return ['issuers_registry','issuers_installation_identity','issuers_clients','contacts','clients','contact_permissions','invoices','issuers_invoice_documents'];}
    public function balanceIdentityInOwnedTransaction(array $session,int $issuer,int $invoice,$db,string $prefix):array
    {
        if($this->db!==$db||$this->p!==$prefix)throw new DomainException('company_portal_invoice_unavailable');IssuerTransaction::assertOwned($this->db,$this->p,$this->balanceTables());$who=$this->invoice($session,$issuer,$invoice,true);if(!$this->rows('SELECT invoice_id FROM '.$this->p.'issuers_invoice_documents WHERE invoice_id=? AND issuer_id=?',[$invoice,$issuer]))throw new DomainException('company_portal_invoice_unavailable');return $who;
    }
    public function document(array $session,$issuer,$invoice):InvoiceIssuerDocumentData
    {
        $issuer=IssuerPolicy::id($issuer);$invoice=IssuerPolicy::id($invoice);$this->assertEngines();$who=$this->invoice($session,$issuer,$invoice,false);
        // The callback rechecks native permission and owner before and inside the document's owned transaction.
        $documents=new InvoiceIssuerDocuments($this->db,$this->p,$who['contact_id'],function($i,$contact,$cap)use($session,$issuer,$invoice,$who):bool{
            if($i!==$issuer||$contact!==$who['contact_id']||!in_array($cap,['view_invoices','render_invoices'],true))return false;
            try{$this->invoice($session,$issuer,$invoice,false);return true;}catch(DomainException $error){return false;}
        },$this,$session);
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

    /** Exact existing payment records; no staff impersonation or provider calls. */
    public function payments(array $session,$issuer,$invoice):array
    {
        require_once __DIR__.'/CompanyInvoiceBalance.php';
        $issuer=IssuerPolicy::id($issuer);$invoice=IssuerPolicy::id($invoice);$balance=new CompanyInvoiceBalance($this->db,$this->p);
        $tables=array_values(array_unique(array_merge($this->tableNames(),$balance->tables(),$this->balanceTables())));
        return IssuerTransaction::run($this->db,$this->p,$tables,fn():array=>$this->paymentsInOwned($session,$issuer,$invoice,$balance));
    }
    private function paymentsInOwned(array $session,int $issuer,int $invoice,CompanyInvoiceBalance $balance):array
    {
            $who=$this->attachmentIndependentIdentity($session,$issuer,$invoice);$position=$balance->portalInOwned($this,$session,$issuer,$invoice);
            $rows=$this->rows('SELECT p.id,p.amount,p.date,p.paymentmode FROM '.$this->p.'invoicepaymentrecords p JOIN '.$this->p.'invoices i ON i.id=p.invoiceid WHERE p.invoiceid=? AND i.issuer_id=? ORDER BY p.id',[$invoice,$issuer]);
            foreach($rows as &$row){
                $remaining=CompanyInvoiceAmounts::minor((string)$row['amount']);$manual=0;
                if($this->db->table_exists($this->p.'company_credit_entries'))foreach($this->rows("SELECT amount FROM {$this->p}company_credit_entries WHERE issuer_id=? AND invoice_id=? AND kind='manual_reverse' AND native_id=?",[$issuer,$invoice,$row['id']]) as $entry){$n=CompanyInvoiceAmounts::minor((string)$entry['amount']);if($n<0||$n>CompanyInvoiceAmounts::MAX-$manual)throw new DomainException('company_invoice_balance_review');$manual+=$n;}
                $bank=$position['reversals'][(int)$row['id']]??null;$external=$bank===null?0:CompanyInvoiceAmounts::minor($bank['cash_principal_reversed']);if($remaining<0||$external<0||$external>$remaining||$manual>CompanyInvoiceAmounts::MAX-$remaining)throw new DomainException('company_invoice_balance_review');
                $row=['id'=>(int)$row['id'],'date'=>(string)$row['date'],'paymentmode'=>(string)$row['paymentmode'],'original'=>CompanyInvoiceAmounts::decimal($remaining+$manual),'reversed'=>CompanyInvoiceAmounts::decimal($manual+$external),'effective'=>CompanyInvoiceAmounts::decimal($remaining-$external)];
            }unset($row);
            if($this->attachmentIndependentIdentity($session,$issuer,$invoice)!==$who)throw new DomainException('company_portal_invoice_unavailable');return $rows;
    }
    /** Recheck exact payment state and contact authority before receipt bytes leave the server. */
    public function confirmPaymentReceipt(array $session,$issuer,$invoice,array $payment,string $hash):void
    {
        require_once __DIR__.'/CompanyInvoiceBalance.php';$issuer=IssuerPolicy::id($issuer);$invoice=IssuerPolicy::id($invoice);$paymentId=IssuerPolicy::id($payment['id']??null);if(!preg_match('/^[a-f0-9]{64}$/D',$hash))throw new DomainException('company_portal_invoice_unavailable');$balance=new CompanyInvoiceBalance($this->db,$this->p);$tables=array_values(array_unique(array_merge($this->tableNames(),$balance->tables(),$this->balanceTables())));
        IssuerTransaction::run($this->db,$this->p,$tables,function()use($session,$issuer,$invoice,$payment,$paymentId,$hash,$balance):void{
            $matching=array_values(array_filter($this->paymentsInOwned($session,$issuer,$invoice,$balance),static fn($row)=>$row['id']===$paymentId));if(count($matching)!==1||$matching[0]!==$payment)throw new DomainException('company_portal_invoice_unavailable');$who=$this->attachmentIndependentIdentity($session,$issuer,$invoice);
            $event='company_portal_payment_receipt_downloaded';$metadata=json_encode(['contact_id'=>$who['contact_id'],'client_id'=>$who['client_id'],'payment_id'=>$paymentId,'sha256'=>$hash,'receipt'=>bin2hex(random_bytes(16))],JSON_THROW_ON_ERROR);
            IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at,metadata_json) VALUES(0,?,?,?,?,?)',[$event,$issuer,$invoice,date('Y-m-d H:i:s'),$metadata]);$id=(int)$this->db->insert_id();if(count($this->rows('SELECT id FROM '.$this->p.'issuers_audit WHERE id=? AND issuer_id=? AND subject_id=? AND event=? AND metadata_json=?',[$id,$issuer,$invoice,$event,$metadata]))!==1)throw new RuntimeException('issuer_portal_write_unconfirmed');
            if($this->attachmentIndependentIdentity($session,$issuer,$invoice)!==$who)throw new DomainException('company_portal_invoice_unavailable');
        });
    }
    private function attachmentIndependentIdentity(array $session,int $issuer,int $invoice):array
    {
        $who=$this->invoice($session,$issuer,$invoice,true);if(!$this->rows('SELECT invoice_id FROM '.$this->p.'issuers_invoice_documents WHERE invoice_id=? AND issuer_id=?',[$invoice,$issuer]))throw new DomainException('company_portal_invoice_unavailable');return $who;
    }
    public function attachments(array $session,$issuer,$invoice):array
    {
        $issuer=IssuerPolicy::id($issuer);$invoice=IssuerPolicy::id($invoice);return $this->transaction(function()use($session,$issuer,$invoice):array{$who=$this->attachmentIndependentIdentity($session,$issuer,$invoice);if(!$this->db->table_exists($this->p.'company_invoice_attachments'))return[];$rows=$this->rows('SELECT filename,file_key FROM '.$this->p.'company_invoice_attachments WHERE issuer_id=? AND invoice_id=? AND active=1 AND portal_visible=1 ORDER BY created_at,file_key LIMIT 21',[$issuer,$invoice]);if(count($rows)>20)throw new DomainException('company_portal_invoice_unavailable');$result=[];foreach($rows as$row){if(!preg_match('/^[a-f0-9]{64}$/D',(string)$row['file_key']))throw new DomainException('company_portal_invoice_unavailable');$result[]=['filename'=>$row['filename'],'key'=>$row['file_key'],'external'=>null];}if($this->attachmentIndependentIdentity($session,$issuer,$invoice)!==$who)throw new DomainException('company_portal_invoice_unavailable');return$result;});
    }
}
