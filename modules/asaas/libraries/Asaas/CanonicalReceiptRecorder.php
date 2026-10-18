<?php
declare(strict_types=1);
namespace Asaas;

/**
 * Minimal local full-payment write, coordinated by the webhook transaction.
 * No filters, notifications, SDK calls, PDF generation or hooks run here.
 * The caller holds the invoice row lock and commits receipt + map + inbox + outbox.
 */
final class CanonicalReceiptRecorder
{
    private $db;
    public function __construct($db) { $this->db=$db; }
    /** Use the provider's actual payment date, never processing/retry time. */
    public static function effectiveDate(array $payment): ?string
    {
        $value=$payment['paymentDate']??null;
        if (!is_string($value) || !preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/D',$value,$parts)
            || !checkdate((int)$parts[2],(int)$parts[3],(int)$parts[1]) || (int)$parts[1]<2000) return null;
        $today=(new \DateTimeImmutable('now',new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');
        return $value<=$today ? $value : null;
    }
    public function writeLocked(array $invoice, array $origin, array $payment, array $event): int
    {
        $paymentDate=self::effectiveDate($payment);
        $method=$payment['billingType']??null;
        if ($paymentDate===null || !in_array($method,['PIX','BOLETO','CREDIT_CARD'],true)) throw new \RuntimeException('canonical_receipt_metadata_invalid');
        $invoiceId=(int)($invoice['id']??0);
        $remoteId=(string)($origin['remote_id']??'');
        if ($invoiceId<1 || empty($origin['id']) || empty($event['id']) || empty($event['event_id'])
            || !in_array($payment['status']??'', ['CONFIRMED','RECEIVED','RECEIVED_IN_CASH'], true) || ($payment['id']??'')!==$remoteId
            || !in_array((int)($invoice['status']??0),[1,4],true)) throw new \RuntimeException('receipt_contract_invalid');
        // Row locks also protect records visible to cooperating financial writers.
        foreach ([['invoicepaymentrecords','invoiceid'],['credits','invoice_id']] as [$table,$column]) {
            $rows=$this->db->query('SELECT * FROM '.$this->db->protect_identifiers(db_prefix().$table,true).' WHERE '.$column.' = ? FOR UPDATE',[$invoiceId])->result_array();
            if ($rows) throw new \RuntimeException('partial_or_credits_require_review');
        }
        $now=date('Y-m-d H:i:s');
        $row=['invoiceid'=>$invoiceId,'amount'=>(string)$invoice['total'],'paymentmode'=>'asaas',
            'paymentmethod'=>$method,'date'=>$paymentDate,'daterecorded'=>$now,
            'note'=>'Recebimento Asaas conferido; automações pós-conciliação pendentes.', 'transactionid'=>$remoteId];
        if (!$this->db->insert(db_prefix().'invoicepaymentrecords',$row)) throw new \RuntimeException('receipt_write_failed');
        $receiptId=(int)$this->db->insert_id();
        if ($receiptId<1) throw new \RuntimeException('receipt_id_unconfirmed');
        // No total, tax, discount, numbering, customer or recurrence fields change.
        if (!$this->db->where('id',$invoiceId)->where('status',(int)$invoice['status'])
            ->update(db_prefix().'invoices',['status'=>2]) || $this->db->affected_rows()!==1) throw new \RuntimeException('invoice_status_compare_failed');
        if (!$this->db->insert(db_prefix().'asaas_settlement_outbox',[
            'invoice_id'=>$invoiceId,'origin_id'=>(int)$origin['id'],'receipt_id'=>$receiptId,
            'event_row_id'=>(int)$event['id'],'event_id'=>(string)$event['event_id'],
            'provider'=>'asaas','account_id'=>$origin['account_id'],'environment'=>$origin['environment'],
            'remote_id'=>$remoteId,'verified_status'=>(string)$payment['status'],'payment_date'=>$paymentDate,'billing_type'=>$method,'state'=>'pending','created_at'=>$now,'processed_at'=>null,
        ])) throw new \RuntimeException('settlement_outbox_write_failed');
        if (!$this->db->insert(db_prefix().'sales_activity',[
            'rel_type'=>'invoice','rel_id'=>$invoiceId,'description'=>'Recebimento Asaas conferido. Automações posteriores aguardam integração segura.',
            'additional_data'=>serialize(['receipt_id'=>$receiptId,'event_row_id'=>(int)$event['id']]),
            'staffid'=>null,'full_name'=>'Asaas','date'=>$now,
        ])) throw new \RuntimeException('settlement_audit_write_failed');
        return $receiptId;
    }
}
