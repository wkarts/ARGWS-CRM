<?php

declare(strict_types=1);
defined('BASEPATH') or exit('No direct script access allowed');
require_once __DIR__.'/EfiChargeLedger.php';

/** Durable at-most-once remote dispatch. An uncertain operation requires manual review. */
final class EfiChargeIntent
{
    public function __construct(private $db,private string $prefix)
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$prefix))throw new DomainException('efi_storage_invalid');
    }

    /** Only the request that successfully INSERTs dispatched may issue a provider POST. */
    public function reserve(string $kind,array $options,array $invoice): array
    {
        if(!in_array($kind,['pix','boleto','card'],true))throw new DomainException('efi_charge_kind_invalid');
        $current=(new EfiChargeLedger($this->db,$this->prefix))->invoice($invoice['id'],$invoice['hash']);
        if(!in_array((int)$current['status'],[1,3,4],true)||EfiChargeLedger::minor($current['total'])<1)throw new DomainException('efi_invoice_not_payable');
        // Full-value legacy checkout cannot safely issue a second/full charge after a credit
        // or partial receipt. No rounding or reconstruction of a different payable amount.
        foreach([['invoicepaymentrecords','invoiceid'],['credits','invoice_id']] as [$table,$column]) {
            foreach($this->query('SELECT amount FROM '.$this->prefix.$table.' WHERE '.$column.'=?',[$current['id']])->result_array() as $record) {
                if(EfiChargeLedger::minor($record['amount'])!==0)throw new DomainException('efi_partial_payment_requires_review');
            }
        }
        $amount=EfiChargeLedger::minor($current['total']);$account=EfiChargeLedger::account($options);
        $read=fn()=>$this->query('SELECT * FROM '.$this->prefix.'efi_charge_intents WHERE invoice_id=?',[$current['id']])->result_array();
        $old=$read();
        if(!$old) {
            $this->independent();
            if(!$this->db->trans_begin())throw new RuntimeException('efi_intent_begin_failed');
            $committed=false;
            try {
                $locked=$this->query('SELECT id,issuer_id,hash,total,status,currency FROM '.$this->prefix.'invoices WHERE id=? AND hash=? AND issuer_id IS NULL FOR UPDATE',[$current['id'],$current['hash']])->row_array();
                if(!$locked||$locked['issuer_id']!==null||!in_array((int)$locked['status'],[1,3,4],true)
                    ||EfiChargeLedger::minor($locked['total'])!==$amount||(int)$locked['currency']!==(int)$current['currency'])throw new DomainException('efi_invoice_changed');
                foreach([['invoicepaymentrecords','invoiceid'],['credits','invoice_id']] as [$table,$column]) {
                    foreach($this->query('SELECT amount FROM '.$this->prefix.$table.' WHERE '.$column.'=? FOR UPDATE',[$current['id']])->result_array() as $record) {
                        if(EfiChargeLedger::minor($record['amount'])!==0)throw new DomainException('efi_partial_payment_requires_review');
                    }
                }
                $this->query('INSERT INTO '.$this->prefix."efi_charge_intents (invoice_id,issuer_id,kind,sandbox,account_hash,amount_minor,currency,state,provider_id,created_at,updated_at) VALUES (?,NULL,?,?,?,?,'BRL','dispatched',NULL,UTC_TIMESTAMP(),UTC_TIMESTAMP())",[$current['id'],$kind,(int)$options['sandbox'],$account,$amount]);
                if(!$this->db->trans_status()||!$this->db->trans_commit())throw new RuntimeException('efi_intent_commit_unconfirmed');
                $committed=true;
            } catch(Throwable $error) {
                $this->db->trans_rollback();
                // A collision may have committed elsewhere; that never grants this request dispatch.
            }
            $old=$read();
            if($committed&&(new ReflectionProperty($this->db,'_trans_depth'))->getValue($this->db)===0
                && (!(($this->db->conn_id??null) instanceof PDO)||!$this->db->conn_id->inTransaction())
                &&count($old)===1&&$old[0]['state']==='dispatched'&&$old[0]['provider_id']===null) {
                $this->assertSnapshot($old[0],$current,$amount,$kind,$options);
                return ['dispatch'=>true,'row'=>$old[0]];
            }
        }
        if(count($old)!==1)throw new RuntimeException('efi_intent_storage_unconfirmed');
        $this->assertSnapshot($old[0],$current,$amount,$kind,$options);
        if($old[0]['state']!=='bound'||!is_string($old[0]['provider_id'])||$old[0]['provider_id']==='')throw new DomainException('efi_creation_unknown_requires_review');
        (new EfiChargeLedger($this->db,$this->prefix))->bound($kind,$old[0]['provider_id'],$options,$current);
        return ['dispatch'=>false,'row'=>$old[0]];
    }

    /** Before the second boleto POST, retain its provider ID and a durable second fence. */
    public function providerCreated(array $permit,string $provider): void
    {
        $this->assertPermit($permit);
        $pattern=$permit['row']['kind']==='pix'?'/^[A-Za-z0-9]{26,35}$/D':'/^[1-9][0-9]{0,19}$/D';
        if(!preg_match($pattern,$provider))throw new DomainException('efi_intent_invalid');
        $this->transition($permit['row']['id'],'dispatched','provider_created',$provider);
    }

    public function boletoPayment(array $permit,string $provider): void
    {
        $this->assertPermit($permit);
        if($permit['row']['kind']!=='boleto'||!preg_match('/^[1-9][0-9]{0,19}$/D',$provider))throw new DomainException('efi_intent_invalid');
        $this->transition($permit['row']['id'],'provider_created','payment_dispatched',$provider);
    }

    public function complete(array $permit,string $provider,array $options,array $invoice): void
    {
        $this->assertPermit($permit);
        if($permit['row']['kind']!=='boleto')$this->providerCreated($permit,$provider);
        $binding=(new EfiChargeLedger($this->db,$this->prefix))->bind($permit['row']['kind'],$provider,$options,$invoice);
        if((int)$binding['invoice_id']!==(int)$permit['row']['invoice_id']||$binding['account_hash']!==$permit['row']['account_hash'])throw new DomainException('efi_intent_conflict');
        $from=$permit['row']['kind']==='boleto'?'payment_dispatched':'provider_created';
        $this->transition($permit['row']['id'],$from,'bound',$provider);
    }

    public function uncertain(array $permit): void
    {
        $this->assertPermit($permit);
        // Never reset a fence, erase an ID or overwrite a confirmed binding.
        $this->query('UPDATE '.$this->prefix."efi_charge_intents SET state='unknown',updated_at=UTC_TIMESTAMP() WHERE id=? AND state IN ('dispatched','provider_created','payment_dispatched')",[$permit['row']['id']]);
    }

    private function assertPermit(array $permit):void
    {
        if(($permit['dispatch']??false)!==true||!is_array($permit['row']??null)||(int)($permit['row']['id']??0)<1)throw new DomainException('efi_dispatch_not_owned');
    }
    private function assertSnapshot(array $row,array $invoice,int $amount,string $kind,array $options):void
    {
        if($row['kind']!==$kind||(int)$row['sandbox']!==(int)$options['sandbox']||$row['account_hash']!==EfiChargeLedger::account($options)
            ||$row['issuer_id']!==null||(int)$row['invoice_id']!==(int)$invoice['id']||(int)$row['amount_minor']!==$amount||$row['currency']!=='BRL')throw new DomainException('efi_intent_snapshot_conflict');
    }
    private function independent(): void
    {
        $state=$this->query('SELECT @@SESSION.autocommit AS autocommit, @@SESSION.completion_type AS completion_type',[])->row_array();
        if((string)($state['autocommit']??'')!=='1'||!in_array((string)($state['completion_type']??''),['0','NO_CHAIN'],true)
            ||!property_exists($this->db,'_trans_depth')||(new ReflectionProperty($this->db,'_trans_depth'))->getValue($this->db)!==0
            ||(($this->db->conn_id??null) instanceof PDO&&$this->db->conn_id->inTransaction()))throw new RuntimeException('efi_intent_independent_commit_required');
        $engine=$this->query('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$this->prefix.'efi_charge_intents'])->row_array();
        if(strtoupper((string)($engine['ENGINE']??''))!=='INNODB')throw new RuntimeException('efi_intent_transactional_storage_required');
        $this->query('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ',[]);
    }

    private function transition($id,string $from,string $to,string $provider):void
    {
        $this->independent();
        if(!$this->db->trans_begin())throw new RuntimeException('efi_intent_begin_failed');
        try {
            $this->query('UPDATE '.$this->prefix.'efi_charge_intents SET state=?,provider_id=?,updated_at=UTC_TIMESTAMP() WHERE id=? AND state=?',[$to,$provider,$id,$from]);
            if((int)$this->db->affected_rows()!==1)throw new RuntimeException('efi_intent_transition_unconfirmed');
            if(!$this->db->trans_status()||!$this->db->trans_commit())throw new RuntimeException('efi_intent_commit_unconfirmed');
        } catch(Throwable $error) {$this->db->trans_rollback();throw $error;}
        if((new ReflectionProperty($this->db,'_trans_depth'))->getValue($this->db)!==0
            ||(($this->db->conn_id??null) instanceof PDO&&$this->db->conn_id->inTransaction()))throw new RuntimeException('efi_intent_commit_unconfirmed');
        $row=$this->query('SELECT state,provider_id FROM '.$this->prefix.'efi_charge_intents WHERE id=?',[$id])->row_array();
        if(($row['state']??null)!==$to||($row['provider_id']??null)!==$provider)throw new RuntimeException('efi_intent_transition_unconfirmed');
    }
    private function query(string $sql,array $args)
    {
        $restore=property_exists($this->db,'db_debug');$debug=$restore?$this->db->db_debug:null;
        if($restore)$this->db->db_debug=false;
        try{$result=$this->db->query($sql,$args);if($result===false)throw new RuntimeException('efi_intent_storage_failed');return $result;}
        finally{if($restore)$this->db->db_debug=$debug;}
    }
}
