<?php
declare(strict_types=1);
namespace app\services;
defined('BASEPATH') or exit('No direct script access allowed');

/** Atomic receipt insertion only. The caller runs notifications/hooks after record() returns. */
final class InvoicePaymentWriter
{
    private $db;
    public function __construct($db) { $this->db = $db; }

    public static function invoiceId($id): int
    {
        if ((!is_int($id) && !is_string($id)) || !preg_match('/^[1-9][0-9]*$/D', (string) $id)
            || (string) (int) $id !== (string) $id) {
            throw new \InvalidArgumentException('invalid_payment_invoice');
        }
        return (int) $id;
    }

    /** Exact DECIMAL(15,2), never floating-point subtraction or rounding into an overpayment. */
    public static function minorUnits($amount): int
    {
        if ((!is_int($amount) && !is_float($amount) && !is_string($amount))
            || !preg_match('/^(\d{1,13})(?:\.(\d{1,2}))?$/D', (string) $amount, $parts)) {
            throw new \InvalidArgumentException('invalid_payment_amount');
        }
        return (int) $parts[1] * 100 + (int) str_pad($parts[2] ?? '', 2, '0');
    }

    public function record(int $targetInvoiceId, array $data, callable $allowed): int
    {
        return $this->write($targetInvoiceId, $data, $allowed, null);
    }

    public function applyCredit(int $targetInvoiceId, int $creditNoteId, array $data, callable $allowed): int
    {
        self::invoiceId($creditNoteId);
        return $this->write($targetInvoiceId, $data, $allowed, $creditNoteId);
    }

    /** Explicit manual receipt for a named company. Never called by generic legacy/provider paths. */
    public function recordCompanyManual($issuer,$actor,$invoice,array $input,string $requestKey): int
    {
        require_once __DIR__.'/issuers/IssuerTransaction.php';
        require_once __DIR__.'/issuers/IssuerResourceAccess.php';
        $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);$invoice=self::invoiceId($invoice);
        if(!preg_match('/^[a-f0-9]{64}$/D',$requestKey)||array_diff(array_keys($input),['amount','date','reference','note']))throw new \DomainException('company_payment_invalid_input');
        $amount=self::minorUnits($input['amount']??null);$date=$input['date']??null;
        $parsed=is_string($date)?\DateTimeImmutable::createFromFormat('!Y-m-d',$date):false;
        if($amount<1||!$parsed||$parsed->format('Y-m-d')!==$date)throw new \DomainException('company_payment_invalid_input');
        $reference=$input['reference']??null;$note=$input['note']??'';
        foreach(['reference'=>[$reference,191,true],'note'=>[$note,4000,false]] as [$text,$limit,$required]){
            if(!is_string($text)||strlen($text)>$limit||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$text)||preg_match('//u',$text)!==1||($required&&trim($text)===''))throw new \DomainException('company_payment_invalid_input');
        }
        $data=['invoiceid'=>$invoice,'amount'=>intdiv($amount,100).'.'.str_pad((string)($amount%100),2,'0',STR_PAD_LEFT),'paymentmode'=>'company_manual','paymentmethod'=>'Recebimento manual','date'=>$date,'transactionid'=>trim($reference),'note'=>trim($note)];
        $hash=hash('sha256',json_encode($data,JSON_THROW_ON_ERROR));$prefix=db_prefix();
        $origins=[];foreach(['bb_origins','asaas_payment_origins'] as $table)if($this->db->table_exists($prefix.$table))$origins[]=$table;
        $tables=array_merge(['issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','staff','invoices','issuers_invoice_documents','invoicepaymentrecords','credits','company_payment_receipts','issuers_audit'],$origins);
        return \IssuerTransaction::run($this->db,$prefix,$tables,function()use($issuer,$actor,$invoice,$requestKey,$amount,$data,$hash,$prefix,$origins):int{
            $query=fn($sql,$args=[])=>\IssuerStorage::query($this->db,$sql,$args)->result_array();
            $acl=new \IssuerResourceAccess($this->db,$prefix);
            $authorize=static function()use($acl,$issuer,$actor):void{if(!$acl->can($issuer,$actor,'invoices','view')||!$acl->can($issuer,$actor,'payments','create'))throw new \DomainException('company_payment_access_denied');};
            $authorize();
            $parent=$query('SELECT id,issuer_id,total,status,duedate FROM '.$prefix.'invoices WHERE id=? AND issuer_id=? FOR UPDATE',[$invoice,$issuer])[0]??null;
            if(!$parent)throw new \DomainException('company_payment_access_denied');
            if(!$query('SELECT invoice_id FROM '.$prefix.'issuers_invoice_documents WHERE invoice_id=? AND issuer_id=?',[$invoice,$issuer]))throw new \DomainException('company_payment_invoice_not_eligible');
            $receipt=$query('SELECT * FROM '.$prefix.'company_payment_receipts WHERE request_key=? FOR UPDATE',[$requestKey])[0]??null;
            if($receipt){
                if((int)$receipt['issuer_id']!==$issuer||(int)$receipt['actor_id']!==$actor||(int)$receipt['invoice_id']!==$invoice||!hash_equals($receipt['input_hash'],$hash))throw new \DomainException('company_payment_request_conflict');
                $stored=$query('SELECT invoiceid,amount,transactionid FROM '.$prefix.'invoicepaymentrecords WHERE id=?',[$receipt['payment_id']])[0]??null;
                if(!$stored||(int)$stored['invoiceid']!==$invoice||self::minorUnits($stored['amount'])!==$amount||$stored['transactionid']!==$data['transactionid'])throw new \RuntimeException('issuer_payment_receipt_unconfirmed');
                return (int)$receipt['payment_id'];
            }
            if(!in_array((int)$parent['status'],[1,3,4],true))throw new \DomainException('company_payment_invoice_not_eligible');
            foreach($origins as $table)if($query('SELECT id FROM '.$prefix.$table.' WHERE invoice_id=? FOR UPDATE',[$invoice]))throw new \DomainException('company_payment_provider_review_required');
            // An external reference cannot be silently counted twice under a fresh UI key.
            if($query('SELECT id FROM '.$prefix.'invoicepaymentrecords WHERE invoiceid=? AND paymentmode=? AND transactionid=? FOR UPDATE',[$invoice,'company_manual',$data['transactionid']]))throw new \DomainException('company_payment_reference_conflict');
            $balance=self::minorUnits($parent['total']);
            foreach([['invoicepaymentrecords','invoiceid'],['credits','invoice_id']] as [$table,$column])foreach($query('SELECT amount FROM '.$prefix.$table.' WHERE '.$column.'=? FOR UPDATE',[$invoice]) as $row){$used=self::minorUnits($row['amount']);if($used>$balance)throw new \DomainException('company_payment_balance_requires_review');$balance-=$used;}
            if($amount>$balance)throw new \DomainException('company_payment_exceeds_balance');
            $record=$data+['daterecorded'=>date('Y-m-d H:i:s')];
            \IssuerStorage::query($this->db,'INSERT INTO '.$prefix.'invoicepaymentrecords (`'.implode('`,`',array_keys($record)).'`) VALUES ('.implode(',',array_fill(0,count($record),'?')).')',array_values($record));
            $id=(int)$this->db->insert_id();if($id<1)throw new \RuntimeException('issuer_payment_write_unconfirmed');
            $status=$balance===$amount?2:3;
            \IssuerStorage::query($this->db,'UPDATE '.$prefix.'invoices SET status=? WHERE id=? AND issuer_id=?',[$status,$invoice,$issuer]);
            \IssuerStorage::query($this->db,'INSERT INTO '.$prefix.'company_payment_receipts(request_key,issuer_id,actor_id,input_hash,invoice_id,payment_id,created_at) VALUES(?,?,?,?,?,?,?)',[$requestKey,$issuer,$actor,$hash,$invoice,$id,date('Y-m-d H:i:s')]);
            \IssuerStorage::query($this->db,'INSERT INTO '.$prefix.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at) VALUES(?,?,?,?,?)',[$actor,'company_manual_payment_recorded',$issuer,$invoice,date('Y-m-d H:i:s')]);
            $confirmed=$query('SELECT p.invoiceid,p.amount,i.status FROM '.$prefix.'invoicepaymentrecords p JOIN '.$prefix.'invoices i ON i.id=p.invoiceid WHERE p.id=? AND i.issuer_id=?',[$id,$issuer])[0]??null;
            if(!$confirmed||(int)$confirmed['invoiceid']!==$invoice||self::minorUnits($confirmed['amount'])!==$amount||(int)$confirmed['status']!==$status||count($query('SELECT request_key FROM '.$prefix.'company_payment_receipts WHERE request_key=?',[$requestKey]))!==1)throw new \RuntimeException('issuer_payment_write_unconfirmed');
            $authorize();return $id;
        });
    }

    private function write(int $targetInvoiceId, array $data, callable $allowed, ?int $creditNoteId): int
    {
        $invoiceKey = $creditNoteId === null ? 'invoiceid' : 'invoice_id';
        if (self::invoiceId($data[$invoiceKey] ?? null) !== $targetInvoiceId) {
            throw new \RuntimeException('payment_target_changed');
        }
        $amount = self::minorUnits($data['amount'] ?? null);
        if ($amount < 1) { throw new \RuntimeException('payment_amount_must_be_positive'); }
        $date = is_string($data['date'] ?? null) ? substr($data['date'], 0, 10) : '';
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$parsed || $parsed->format('Y-m-d') !== $date
            || ($creditNoteId === null && ((!is_string($data['paymentmode'] ?? null) && !is_int($data['paymentmode'] ?? null))
                || (string) $data['paymentmode'] === '' || strlen((string) $data['paymentmode']) > 40))) {
            throw new \InvalidArgumentException('invalid_payment_record');
        }
        $data[$invoiceKey] = $targetInvoiceId;
        $data['amount'] = intdiv($amount, 100) . '.' . str_pad((string) ($amount % 100), 2, '0', STR_PAD_LEFT);
        $data['date'] = $date;
        $data['daterecorded'] = date('Y-m-d H:i:s');
        unset($data['id'], $data['amount_with_fee']);
        if ($creditNoteId !== null) {
            $data = array_intersect_key($data, array_flip(['invoice_id','amount','staff_id']));
            $data += ['credit_id'=>$creditNoteId,'date'=>date('Y-m-d'),'date_applied'=>date('Y-m-d H:i:s')];
        }
        $debug = $this->db->db_debug;
        $this->db->db_debug = false;
        $owned = false;
        try {
            $this->assertIndependentTransaction();
            foreach (array_merge(['invoices', 'invoicepaymentrecords', 'credits'], $creditNoteId === null ? [] : ['creditnotes', 'creditnote_refunds']) as $table) {
                $result = $this->db->query('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [db_prefix() . $table]);
                if (!$result || strtoupper((string) ($result->row_array()['ENGINE'] ?? '')) !== 'INNODB') {
                    throw new \RuntimeException('payment_transactional_storage_required');
                }
            }
            // MySQL/MariaDB reject this next-transaction setting inside a raw SQL
            // transaction. Unlike START TRANSACTION it cannot implicitly commit it.
            if (!$this->db->query('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ')) {
                throw new \RuntimeException('payment_outer_transaction_or_isolation_unavailable');
            }
            if (!$this->db->trans_begin()) { throw new \RuntimeException('payment_transaction_unavailable'); }
            $owned = true;
            $invoices = $this->db->protect_identifiers(db_prefix() . 'invoices', true);
            $locked = $this->db->query('SELECT * FROM ' . $invoices . ' WHERE id = ? FOR UPDATE', [$targetInvoiceId]);
            $invoice = $locked ? $locked->row_array() : [];
            if (!$invoice || ($invoice['issuer_id'] ?? null) !== null
                || !in_array((int) ($invoice['status'] ?? 0), [1, 3, 4, 6], true)) {
                throw new \RuntimeException('payment_invoice_not_eligible');
            }
            $balance = self::minorUnits($invoice['total'] ?? null);
            foreach ([['invoicepaymentrecords', 'invoiceid'], ['credits', 'invoice_id']] as [$table, $column]) {
                $result = $this->db->query('SELECT amount FROM ' . $this->db->protect_identifiers(db_prefix() . $table, true)
                    . ' WHERE ' . $column . ' = ? FOR UPDATE', [$targetInvoiceId]);
                if (!$result) { throw new \RuntimeException('payment_balance_unavailable'); }
                foreach ($result->result_array() as $record) {
                    $deduction = self::minorUnits($record['amount'] ?? null);
                    if ($deduction > $balance) { throw new \RuntimeException('payment_balance_requires_review'); }
                    $balance -= $deduction;
                }
            }
            if ($amount > $balance) { throw new \RuntimeException('payment_exceeds_locked_balance'); }
            if ($creditNoteId !== null) {
                $notes = $this->db->protect_identifiers(db_prefix() . 'creditnotes', true);
                $lockedNote = $this->db->query('SELECT * FROM ' . $notes . ' WHERE id = ? FOR UPDATE', [$creditNoteId]);
                $note = $lockedNote ? $lockedNote->row_array() : [];
                if (!$note || ($note['issuer_id'] ?? null) !== null || (int) ($note['status'] ?? 0) !== 1
                    || (int) ($note['clientid'] ?? 0) < 1 || (int) ($note['clientid'] ?? 0) !== (int) ($invoice['clientid'] ?? 0)
                    || (int) ($note['currency'] ?? 0) < 1 || (int) ($note['currency'] ?? 0) !== (int) ($invoice['currency'] ?? 0)) {
                    throw new \RuntimeException('credit_note_not_eligible');
                }
                $availableCredit = self::minorUnits($note['total'] ?? null);
                foreach ([['credits', 'credit_id'], ['creditnote_refunds', 'credit_note_id']] as [$table, $column]) {
                    $result = $this->db->query('SELECT amount FROM ' . $this->db->protect_identifiers(db_prefix() . $table, true)
                        . ' WHERE ' . $column . ' = ? FOR UPDATE', [$creditNoteId]);
                    if (!$result) { throw new \RuntimeException('credit_note_balance_unavailable'); }
                    foreach ($result->result_array() as $used) {
                        $deduction = self::minorUnits($used['amount'] ?? null);
                        if ($deduction > $availableCredit) { throw new \RuntimeException('credit_note_balance_requires_review'); }
                        $availableCredit -= $deduction;
                    }
                }
                if ($amount > $availableCredit) { throw new \RuntimeException('credit_note_exceeds_locked_balance'); }
            }
            // Veto-only contract: callbacks must be side-effect free and cannot
            // replace invoice/amount. Provider requests do not belong in this lock.
            if ($allowed($invoice, $data) !== true) { throw new \RuntimeException('payment_origin_requires_canonical_receipt'); }
            if (!$this->db->insert(db_prefix() . ($creditNoteId === null ? 'invoicepaymentrecords' : 'credits'), $data)) {
                throw new \RuntimeException('payment_insert_failed');
            }
            $id = (int) $this->db->insert_id();
            if ($id < 1 || !$this->db->trans_status() || !$this->db->trans_commit()) {
                throw new \RuntimeException('payment_commit_unconfirmed');
            }
            $owned = false;
            // A driver that silently nested would leave its transaction active.
            $this->assertIndependentTransaction();
            return $id;
        } catch (\Throwable $error) {
            if ($owned) { $this->db->trans_rollback(); }
            throw $error;
        } finally {
            $this->db->db_debug = $debug;
        }
    }

    private function assertIndependentTransaction(): void
    {
        if (!property_exists($this->db, '_trans_depth')
            || (new \ReflectionProperty($this->db, '_trans_depth'))->getValue($this->db) !== 0) {
            throw new \RuntimeException('payment_outer_transaction_not_allowed');
        }
        $connection = $this->db->conn_id ?? null;
        if ($connection instanceof \PDO && $connection->inTransaction()) {
            throw new \RuntimeException('payment_outer_transaction_not_allowed');
        }
        $result = $this->db->query('SELECT @@SESSION.autocommit AS autocommit, @@SESSION.completion_type AS completion_type');
        $state = $result ? $result->row_array() : [];
        if ((string) ($state['autocommit'] ?? '') !== '1' || !in_array((string) ($state['completion_type'] ?? ''), ['0', 'NO_CHAIN'], true)) {
            throw new \RuntimeException('payment_autocommit_required');
        }
    }
}
