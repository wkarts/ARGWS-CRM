<?php
declare(strict_types=1);
require_once __DIR__.'/CompanyLocalNoncash.php';

/** Core proof reader for real native funds after LOCAL debt restoration; historical reads need no bank module or live grants. */
final class CompanyLocalNoncashSettlements
{
    public const BASIS='local_independent_restored_funds_v3';
    public const MODE='company_local_restored';
    public const METHOD='Fundos independentes após restauração LOCAL';
    public function __construct(private$db,private string$p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('company_local_noncash_invalid');}
    public static function revision(array$canonicalDto):string{return hash('sha256',CompanyLocalNoncash::json(CompanyLocalNoncashIntegrity::canonical($canonicalDto)));}
    public function tables():array{return array_merge(CompanyLocalNoncashSchema::tables(),['invoicepaymentrecords','company_payment_receipts','credits','company_credit_entries','creditnotes','company_credit_documents','itemable','item_tax','company_financial_events','issuers_audit']);}
    private function rows(string$sql,array$args=[]):array{return CompanyLocalNoncashIntegrity::rows($this->db,$sql,$args);}
    public static function input(array$input):array
    {
        $allowed=['declaration_key','restoration_key','invoice_revision','amount','date','reference','reason','evidence_hash','local_only_confirmed','received_independently_confirmed','credit_id','parent_key','payment_id'];
        if(array_diff(array_keys($input),$allowed)||($input['local_only_confirmed']??null)!=='1'||($input['received_independently_confirmed']??null)!=='1')throw new DomainException('company_local_settlement_confirmation_required');
        $data=['declaration_key'=>CompanyLocalNoncash::key($input['declaration_key']??null),'restoration_key'=>CompanyLocalNoncash::key($input['restoration_key']??null),'invoice_revision'=>CompanyLocalNoncash::key($input['invoice_revision']??null),'amount'=>CompanyLocalNoncash::amount($input['amount']??null),'date'=>CompanyLocalNoncash::date($input['date']??null),'reference'=>CompanyLocalNoncash::text($input['reference']??null,191),'reason'=>CompanyLocalNoncash::text($input['reason']??null,2000),'evidence_hash'=>CompanyLocalNoncash::key($input['evidence_hash']??null),'local_only_confirmed'=>'1','received_independently_confirmed'=>'1'];
        if($data['amount']==='0.00'||$data['evidence_hash']===str_repeat('0',64))throw new DomainException('company_local_settlement_independent_proof_required');
        foreach(['credit_id','payment_id']as$field)if(isset($input[$field]))$data[$field]=IssuerPolicy::id($input[$field]);if(isset($input['parent_key']))$data['parent_key']=CompanyLocalNoncash::key($input['parent_key']);return$data;
    }
    public function independentInOwned(int$i,int$id,array$local,string$hash):bool
    {
        $known=[$local['key'],CompanyLocalNoncash::restorationKey($local['key'])];foreach(['origin_hash','query_hash','query_projection_hash','account_hash','content_hash']as$field)$known[]=$local['data'][$field];
        foreach(CompanyLocalNoncashSchema::tables()as$table)foreach($this->rows('SELECT request_key,payload_hash,record_hash FROM '.$this->p.$table.' WHERE issuer_id=? AND invoice_id=? FOR UPDATE',[$i,$id])as$row)foreach(['request_key','payload_hash','record_hash']as$field)$known[]=$row[$field];
        foreach($known as$value)if(hash_equals($value,$hash))return false;return true;
    }
    public static function creditContent($db,string$p,int$i,int$id):string
    {
        $header=CompanyLocalNoncashIntegrity::rows($db,'SELECT * FROM '.$p.'creditnotes WHERE id=? AND issuer_id=? FOR UPDATE',[$id,$i])[0]??null;if(!$header)throw new DomainException('company_local_settlement_review');unset($header['status']);$data=[$header,CompanyLocalNoncashIntegrity::rows($db,'SELECT * FROM '.$p.'company_credit_documents WHERE credit_id=? AND issuer_id=? FOR UPDATE',[$id,$i]),CompanyLocalNoncashIntegrity::rows($db,"SELECT * FROM {$p}itemable WHERE rel_id=? AND rel_type='credit_note' ORDER BY id FOR UPDATE",[$id]),CompanyLocalNoncashIntegrity::rows($db,"SELECT * FROM {$p}item_tax WHERE rel_id=? AND rel_type='credit_note' ORDER BY id FOR UPDATE",[$id])];return hash('sha256',CompanyLocalNoncash::json(CompanyLocalNoncashIntegrity::canonical($data)));
    }
    public function decode(int$i,int$id,array$local,array$row):array
    {
        $data=json_decode($row['payload_json'],true,48,JSON_THROW_ON_ERROR);if(!is_array($data)||$row['payload_json']!==CompanyLocalNoncash::json($data)||!hash_equals($row['payload_hash'],hash('sha256',$row['payload_json']))||!hash_equals($row['record_hash'],CompanyLocalNoncash::recordHash($row))||(int)$row['issuer_id']!==$i||(int)$row['invoice_id']!==$id||$row['declaration_key']!==$local['key']||$row['restoration_key']!==CompanyLocalNoncash::restorationKey($local['key'])||!$local['restored'])throw new DomainException('company_local_settlement_review');
        foreach(['basis'=>self::BASIS,'issuer_id'=>$i,'invoice_id'=>$id,'actor_id'=>(int)$row['created_by'],'kind'=>$row['kind'],'request_key'=>$row['request_key'],'declaration_key'=>$row['declaration_key'],'restoration_key'=>$row['restoration_key']]as$field=>$value)if(($data[$field]??null)!==$value)throw new DomainException('company_local_settlement_review');
        if(!in_array($row['kind'],['manual_receipt','credit_apply','credit_unapply','manual_reverse'],true)||($data['review']??null)!==self::input($data['review']??[])||$data['review']['amount']!==CompanyLocalNoncash::amount((string)$row['amount'])||$data['review']['date']!==$row['book_date']||$data['review']['date']<$local['restoration']['book_date']||$row['reference_key']!==hash('sha256',CompanyLocalNoncash::json([$i,$row['kind'],$data['review']['reference']]))||!$this->independentInOwned($i,$id,$local,$data['review']['evidence_hash']))throw new DomainException('company_local_settlement_review');
        if($row['kind']==='manual_receipt'?($row['payment_id']===null||$row['credit_entry_key']!==null):($row['payment_id']!==null||$row['credit_entry_key']!==$row['request_key']))throw new DomainException('company_local_settlement_review');
        if(($data['payment_id']??null)!==($row['payment_id']===null?null:(int)$row['payment_id'])||($data['credit_entry_key']??null)!==$row['credit_entry_key']||!is_array($data['native_images']??null)||!is_array($data['journal']??null)||!is_array($data['audit']??null))throw new DomainException('company_local_settlement_review');
        $audit=$this->rows('SELECT * FROM '.$this->p.'issuers_audit WHERE issuer_id=? AND event=? AND subject_id=? AND metadata_json=? FOR UPDATE',[$i,'company_local_independent_funds_recorded',$id,CompanyLocalNoncash::json(['request_key'=>$row['request_key'],'payload_hash'=>$row['payload_hash'],'kind'=>$row['kind']])]);if(count($audit)!==1||(int)$audit[0]['actor_id']!==(int)$row['created_by']||$audit[0]['created_at']!==$row['created_at'])throw new DomainException('company_local_settlement_review');
        $journal=$this->rows('SELECT * FROM '.$this->p.'company_financial_events WHERE evidence_key=? AND issuer_id=? FOR UPDATE',[$data['journal']['evidence_key']??'',$i]);if(count($journal)!==1||!CompanyLocalNoncashIntegrity::equal($journal[0],$data['journal']))throw new DomainException('company_local_settlement_review');(new CompanyFinancialJournal($this->db,$this->p))->decode($journal[0]);
        if(isset($data['credit_id'])&&$data['credit_id']!==null&&!hash_equals($data['credit_content_hash'],self::creditContent($this->db,$this->p,$i,$data['credit_id'])))throw new DomainException('company_local_settlement_review');return$data;
    }
    public function validateInOwned(int$i,int$id,array$local):array
    {
        IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());$rows=$this->rows('SELECT * FROM '.$this->p.'company_local_noncash_settlements WHERE invoice_id=? ORDER BY created_at,request_key FOR UPDATE',[$id]);$proofs=[];foreach($rows as$row){$data=$this->decode($i,$id,$local,$row);if(isset($proofs[$row['request_key']]))throw new DomainException('company_local_settlement_review');$proofs[$row['request_key']]=$data;}
        $payments=[];$entries=[];$creditApplies=[];$reversed=[];$unapplied=[];
        foreach($proofs as$key=>$data){$amount=CompanyInvoiceAmounts::minor($data['review']['amount']);if($data['kind']==='manual_receipt'){$native=$data['payment_id'];if(isset($payments[$native]))throw new DomainException('company_local_settlement_review');$payments[$native]=$data;}else{$entries[$key]=$data;if($data['kind']==='manual_reverse'){$parent=$data['review']['payment_id']??null;$reversed[$parent]=($reversed[$parent]??0)+$amount;}elseif($data['kind']==='credit_apply'){$creditApplies[$data['native_id']]=$data;}else{$parent=$data['review']['parent_key']??'';$unapplied[$parent]=($unapplied[$parent]??0)+$amount;}}
        }
        foreach($proofs as$key=>$data)foreach($data['native_images']as[$table,$column,$value,$expected]){
            if($table==='invoicepaymentrecords'){$remaining=CompanyInvoiceAmounts::minor($expected['amount'])-($reversed[(int)$expected['id']]??0);if($remaining<0)throw new DomainException('company_local_settlement_review');$expected['amount']=CompanyInvoiceAmounts::decimal($remaining);}
            elseif($table==='credits'){$remaining=CompanyInvoiceAmounts::minor($expected['amount'])-($unapplied[$key]??0);if($remaining<0)throw new DomainException('company_local_settlement_review');$expected['amount']=CompanyInvoiceAmounts::decimal($remaining);}
            if(!in_array([$table,$column],[['invoicepaymentrecords','id'],['company_payment_receipts','request_key'],['company_credit_entries','request_key'],['credits','id'],['issuers_audit','id']],true))throw new DomainException('company_local_settlement_review');CompanyLocalNoncashIntegrity::assertRow($this->db,$this->p,$table,$column,$value,$expected);
        }
        $native=$this->rows('SELECT id FROM '.$this->p.'invoicepaymentrecords WHERE invoiceid=? ORDER BY id FOR UPDATE',[$id]);if(count($native)!==count($payments))throw new DomainException('company_local_settlement_review');foreach($native as$row)if(!isset($payments[(int)$row['id']]))throw new DomainException('company_local_settlement_review');
        $native=$this->rows("SELECT request_key FROM {$this->p}company_credit_entries WHERE invoice_id=? AND kind IN ('apply','unapply','manual_reverse') ORDER BY request_key FOR UPDATE",[$id]);if(count($native)!==count($entries))throw new DomainException('company_local_settlement_review');foreach($native as$row)if(!isset($entries[$row['request_key']]))throw new DomainException('company_local_settlement_review');
        foreach($proofs as$data)if($data['kind']==='manual_reverse'&&!isset($payments[$data['review']['payment_id']??null])||$data['kind']==='credit_unapply'&&($proofs[$data['review']['parent_key']??'']['kind']??null)!=='credit_apply')throw new DomainException('company_local_settlement_review');return$proofs;
    }
}
