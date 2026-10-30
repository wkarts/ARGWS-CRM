<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyInvoicePreviewData.php';
require_once __DIR__.'/NativeCompanyInvoicePaymentReversal.php';
/** Original Payments module data, resolved through the actual invoice owner before hydration. */
final class NativeCompanyPaymentRecords
{
    public function __construct(private $db,private string $p){}
    private function rows(string $q,array $a=[]):array{return IssuerStorage::query($this->db,$q,$a)->result_array();}
    private function authorize(int $i,int $a,string $cap='view'):void{
        $acl=new IssuerResourceAccess($this->db,$this->p);
        if(!$acl->can($i,$a,'payments',$cap)||!$acl->can($i,$a,'invoices','view'))throw new DomainException('native_payment_denied');
    }
    private function source(int $i,int $a,int $id):array{
        $this->authorize($i,$a);$row=$this->rows('SELECT p.* FROM '.$this->p.'invoicepaymentrecords p JOIN '.$this->p.'invoices i ON i.id=p.invoiceid WHERE p.id=? AND i.issuer_id=?',[$id,$i])[0]??null;
        if(!$row)throw new DomainException('native_payment_denied');return$row;
    }
    private static function sourceHash(array $row):string{
        return hash('sha256',json_encode(array_map('strval',array_intersect_key($row,array_flip(['id','invoiceid','date','paymentmode','paymentmethod','transactionid','note']))),JSON_THROW_ON_ERROR));
    }
    private function note(int $i,int $id,array $row):array{
        $history=$this->rows('SELECT id,actor_id,metadata_json FROM '.$this->p.'issuers_audit WHERE issuer_id=? AND subject_id=? AND event=? ORDER BY id',[$i,$id,'native_payment_note_saved']);$note=(string)$row['note'];$source=self::sourceHash($row);
        foreach($history as$entry){$data=json_decode($entry['metadata_json'],true,32,JSON_THROW_ON_ERROR);
            if(($data['source_hash']??null)!==$source||($data['before']??null)!==$note||!is_string($data['after']??null))throw new DomainException('native_payment_history_invalid');$note=$data['after'];
        }
        return ['note'=>$note,'revision'=>hash('sha256',json_encode([$source,$history],JSON_THROW_ON_ERROR)),'history'=>$history];
    }
    public function read(int $i,int $a,$id):array{
        $id=IssuerPolicy::id($id);$row=$this->source($i,$a,$id);$preview=(new NativeCompanyInvoicePreviewData($this->db,$this->p))->read($i,$a,$row['invoiceid']);$payments=array_values(array_filter($preview['payments'],static fn($payment)=>(int)$payment['id']===$id));
        if(count($payments)!==1)throw new DomainException('native_payment_denied');$note=$this->note($i,$id,$row);
        $financialRevision=(new CompanyInvoices($this->db,$this->p))->paymentRevision($i,$a,$row['invoiceid']);
        if($this->source($i,$a,$id)!==$row||$this->note($i,$id,$row)!==$note)throw new DomainException('native_payment_stale');
        $this->authorize($i,$a);return ['payment'=>$payments[0],'invoice'=>$preview['document'],'balance'=>$preview['balance'],'invoice_revision'=>$preview['revision'],'financial_revision'=>$financialRevision]+$note;
    }
    /** Used only by an owned financial document/queue transaction with the full canonical participants. */
    public function readInOwned(int $i,int $a,int $id):array
    {
        $service=new CompanyInvoices($this->db,$this->p);IssuerTransaction::assertOwned($this->db,$this->p,$service->transactionParticipants());
        $row=$this->source($i,$a,$id);$invoice=(int)$row['invoiceid'];$doc=$service->documentInOwned($i,$a,$invoice)->data();$payments=array_values(array_filter($service->paymentsInOwned($i,$a,$invoice),static fn($r)=>(int)$r['id']===$id));
        if(count($payments)!==1)throw new DomainException('native_payment_denied');$balance=(new CompanyInvoiceBalance($this->db,$this->p))->inOwned($i,$a,$invoice);$note=$this->note($i,$id,$row);
        $this->authorize($i,$a);return ['payment'=>$payments[0],'invoice'=>$doc,'balance'=>$balance,'invoice_revision'=>$service->revision($i,$a,$invoice),'financial_revision'=>$service->paymentRevision($i,$a,$invoice)]+$note;
    }
    public function saveNote(int $i,int $a,$id,string $revision,string $note,string $key):void{
        $id=IssuerPolicy::id($id);if(!preg_match('/^[a-f0-9]{64}$/D',$revision)||!preg_match('/^[a-f0-9]{64}$/D',$key)||strlen($note)>10000||preg_match('//u',$note)!==1||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$note))throw new DomainException('native_payment_invalid');
        $tables=['issuers_lock','issuers_registry','issuers_installation_identity','issuers_staff','issuers_resource_permissions','staff','issuers_audit','invoices','invoicepaymentrecords'];
        IssuerTransaction::run($this->db,$this->p,$tables,function()use($i,$a,$id,$revision,$note,$key){
            (new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($i,$a);$this->authorize($i,$a,'edit');$row=$this->source($i,$a,$id);$before=$this->note($i,$id,$row);$hash=hash('sha256',json_encode([$id,$revision,$note],JSON_THROW_ON_ERROR));
            foreach($before['history']as$event){$prior=json_decode($event['metadata_json'],true,32,JSON_THROW_ON_ERROR);if(($prior['request_key']??null)===$key){if((int)$event['actor_id']!==$a||($prior['input_hash']??null)!==$hash)throw new DomainException('native_payment_conflict');return;}}
            if(!hash_equals($before['revision'],$revision))throw new DomainException('native_payment_stale');
            $data=['request_key'=>$key,'input_hash'=>$hash,'source_hash'=>self::sourceHash($row),'before'=>$before['note'],'after'=>$note];$json=json_encode($data,JSON_THROW_ON_ERROR);
            IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(issuer_id,actor_id,subject_id,event,metadata_json,created_at) VALUES(?,?,?,?,?,?)',[$i,$a,$id,'native_payment_note_saved',$json,gmdate('Y-m-d H:i:s')]);$audit=(int)$this->db->insert_id();
            if($audit<1||!$this->rows('SELECT id FROM '.$this->p.'issuers_audit WHERE id=? AND issuer_id=? AND actor_id=? AND subject_id=? AND metadata_json=?',[$audit,$i,$a,$id,$json])||$this->note($i,$id,$row)['note']!==$note||$this->source($i,$a,$id)!==$row)throw new RuntimeException('issuer_payment_write_unconfirmed');$this->authorize($i,$a,'edit');
        });
    }
    /** The principal union preserves raw historical IDs; every owned payment retains the financial writer projection. */
    public function listing(int $i,int $a,?int $client=null):array{
        $this->authorize($i,$a);$acl=new IssuerResourceAccess($this->db,$this->p);$primary=$acl->primaryId();
        if($client!==null){require_once __DIR__.'/CompanyCustomerAccess.php';if(!(new CompanyCustomerAccess($this->db,$this->p))->can($i,$a,$client,'view'))throw new DomainException('native_payment_denied');}
        $query='SELECT p.id,p.invoiceid,i.issuer_id,i.clientid,c.company,curr.name currency,p.amount,p.date,i.date invoice_date,p.paymentmode,p.paymentmethod,p.transactionid,i.formatted_number,i.number,i.prefix,i.number_format FROM '.$this->p.'invoicepaymentrecords p JOIN '.$this->p.'invoices i ON i.id=p.invoiceid JOIN '.$this->p.'clients c ON c.userid=i.clientid JOIN '.$this->p.'currencies curr ON curr.id=i.currency WHERE (i.issuer_id=?'.($primary===$i?' OR i.issuer_id IS NULL':'').')'.($client===null?'':' AND i.clientid=?').' ORDER BY p.id LIMIT 10001';$args=$client===null?[$i]:[$i,$client];$rows=$this->rows($query,$args);if(count($rows)>10000)throw new DomainException('native_payment_limit');$result=[];
        foreach($rows as$row){
            if($row['issuer_id']!==null){$current=$this->read($i,$a,$row['id']);$row['amount']=$current['payment']['effective_amount'];$row['formatted_number']=$current['invoice']['number'];}
            else{
                (new NativeCompanyInvoiceIdentity($this->db,$this->p))->resolve($i,$a,$row['invoiceid']);
                if(!$row['formatted_number']){require_once __DIR__.'/SalesNumberPresentation.php';$padding=(new CompanySettingsStorage($this->db,$this->p))->get($i,'number_padding_prefixes');if(!preg_match('/^(?:0|[1-9][0-9]?)$/D',$padding))throw new DomainException('native_payment_invalid');$row['formatted_number']=SalesNumberPresentation::format($row['number'],$row['number_format'],$row['prefix'],$row['invoice_date'],(int)$padding);}
            }
            $result[]=$row;
        }
        if($this->rows($query,$args)!==$rows||$acl->primaryId()!==$primary)throw new DomainException('native_payment_stale');$this->authorize($i,$a);return$result;
    }
}
