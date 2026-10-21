<?php
declare(strict_types=1);
namespace BancoBrasil;
require_once __DIR__ . '/Agreement.php';

/** Strict, bounded parser: validation produces observations, never accounting entries. */
final class Cnab240ReturnParser
{
    public function parse(Agreement $a, string $bytes): array
    {
        if ($bytes === '' || strlen($bytes) > 16 * 1024 * 1024 || substr($bytes, -2) !== "\r\n"
            || preg_match('/[^\x20-\x7E\r\n]/', $bytes)) throw new \UnexpectedValueException('bb_return_encoding_invalid');
        $lines = explode("\r\n", substr($bytes, 0, -2));
        if (count($lines) < 6) throw new \UnexpectedValueException('bb_return_incomplete');
        foreach ($lines as $line) if (strlen($line) !== 240 || substr($line, 0, 3) !== '001') {
            throw new \UnexpectedValueException('bb_return_record_invalid');
        }
        $header = $lines[0];
        if (substr($header, 0, 8) !== '00100000' || $header[142] !== '2' || substr($header, 163, 3) !== '084'
            || $header[17] !== (strlen($a->taxId) === 11 ? '1' : '2')
            || substr($header, 18, 14) !== Fields::taxField($a->taxId, 14)) throw new \UnexpectedValueException('bb_return_header_mismatch');
        $this->account($header, 53, 58, 59, 71, $a);
        $headerAgreement = substr($header, 32, 20);
        if ($headerAgreement !== str_repeat(' ', 20) && $headerAgreement !== $a->wireAgreement()) {
            throw new \UnexpectedValueException('bb_return_agreement_mismatch');
        }
        Fields::parseDate(substr($header, 143, 8));
        $this->numeric(substr($header, 157, 6));
        $fileHash = hash('sha256', $bytes);
        $events = []; $i = 1; $lotNumber = 0; $seen = [];
        while ($i < count($lines) - 1) {
            $lotStart = $i; $lot = $lines[$i++]; $lotNumber++;
            $lotId = Fields::digits((string) $lotNumber, 4);
            if (substr($lot, 3, 5) !== $lotId . '1' || !in_array($lot[8], ['T','P'], true)
                || substr($lot, 9, 2) !== '01' || substr($lot, 13, 3) !== '043'
                || $lot[17] !== (strlen($a->taxId) === 11 ? '1' : '2')
                || substr($lot, 18, 15) !== Fields::taxField($a->taxId, 15)
                || substr($lot, 33, 20) !== $a->wireAgreement($a->scope->environment === 'sandbox')) {
                throw new \UnexpectedValueException('bb_return_lot_mismatch');
            }
            $this->account($lot, 54, 59, 60, 72, $a);
            $seq = 0; $pairs = 0;
            while ($i < count($lines) - 1 && $lines[$i][7] === '3') {
                $t = $lines[$i++]; $this->detail($t, $lotId, ++$seq, 'T');
                if ($i >= count($lines) - 1) throw new \UnexpectedValueException('bb_return_unpaired');
                $u = $lines[$i++]; $this->detail($u, $lotId, ++$seq, 'U');
                $code = substr($t, 15, 2);
                if (substr($u, 15, 2) !== $code || !preg_match('/^[0-9]{2}$/D', $code)) {
                    throw new \UnexpectedValueException('bb_return_occurrence_mismatch');
                }
                // T uses G065 (09=BRL), unlike BB's remittance-P override (00=BRL).
                if (substr($t, 130, 2) !== '09') throw new \UnexpectedValueException('bb_return_currency_unsupported');
                // 85/86 overload U123-125 with a negative-credit-reporting agent, not money.
                if (in_array($code, ['85','86'], true)) throw new \UnexpectedValueException('bb_return_occurrence_unsupported');
                $this->account($t, 18, 23, 24, 36, $a);
                $ourNumber = rtrim(substr($t, 37, 20), ' ');
                if (!preg_match('/^[0-9]{17}$/D', $ourNumber) || substr($ourNumber, 0, 7) !== $a->scope->agreement
                    || substr($t, 57, 1) !== '7') throw new \UnexpectedValueException('bb_return_title_scope_mismatch');
                $control = trim(substr($t, 105, 25));
                if ($control === '' || !preg_match('/^[A-Z0-9 ]+$/D', $control)) {
                    throw new \UnexpectedValueException('bb_return_control_missing');
                }
                $event = ['scope_key'=>$a->scope->key(), 'provider'=>'banco_brasil', 'channel'=>'cnab240',
                    'layout'=>Agreement::LAYOUT_ID, 'file_hash'=>$fileHash, 'lot'=>$lotNumber, 'sequence'=>$seq - 1,
                    'our_number'=>$ourNumber, 'control'=>$control, 'document'=>trim(substr($t,58,15)),
                    'occurrence'=>$code, 'currency'=>'BRL', 'currency_code'=>'09', 'reasons'=>str_split(substr($t,213,10),2),
                    'nominal_amount'=>Fields::amount($this->numeric(substr($t,81,15))),
                    'paid_amount'=>Fields::amount($this->numeric(substr($u,77,15))),
                    'net_amount'=>Fields::amount($this->numeric(substr($u,92,15))),
                    'other_expenses'=>Fields::amount($this->numeric(substr($u,107,15))),
                    'other_credits'=>Fields::amount($this->numeric(substr($u,122,15))),
                    'fees'=>Fields::amount($this->numeric(substr($t,198,15))),
                    'interest'=>Fields::amount($this->numeric(substr($u,17,15))),
                    'discount'=>Fields::amount($this->numeric(substr($u,32,15))),
                    'abatement'=>Fields::amount($this->numeric(substr($u,47,15))),
                    'iof'=>Fields::amount($this->numeric(substr($u,62,15))),
                    'due_date'=>Fields::parseDate(substr($t,73,8), true),
                    'occurrence_date'=>Fields::parseDate(substr($u,137,8), true),
                    'credit_date'=>Fields::parseDate(substr($u,145,8), true),
                    'observation'=>$this->observation($code, $lot[8]), 'financial_effect'=>'not_applied'];
                // Semantic identity survives the same bank event repackaged in another file.
                $identity = [$a->scope->key(), $ourNumber, $code, $event['reasons'], $event['paid_amount'],
                    $event['net_amount'], $event['occurrence_date'], $event['credit_date']];
                $event['event_key'] = hash('sha256', json_encode($identity, JSON_THROW_ON_ERROR));
                if (isset($seen[$event['event_key']])) throw new \UnexpectedValueException('bb_return_duplicate_event');
                $seen[$event['event_key']] = true;
                $event['requires_review'] = true; // File provenance/origin and accounting policy must be reconciled separately.
                $events[] = $event; $pairs++;
            }
            if ($pairs === 0 || $i >= count($lines) - 1) throw new \UnexpectedValueException('bb_return_lot_incomplete');
            $trailer = $lines[$i++];
            if (substr($trailer, 3, 5) !== $lotId . '5' || $this->numeric(substr($trailer,17,6)) !== $i - $lotStart) {
                throw new \UnexpectedValueException('bb_return_lot_count_mismatch');
            }
        }
        $trailer = $lines[$i];
        if (substr($trailer,0,8) !== '00199999' || $this->numeric(substr($trailer,17,6)) !== $lotNumber
            || $this->numeric(substr($trailer,23,6)) !== count($lines)) throw new \UnexpectedValueException('bb_return_file_count_mismatch');
        return ['file_hash'=>$fileHash, 'scope_key'=>$a->scope->key(), 'layout'=>Agreement::LAYOUT_ID,
            'lots'=>$lotNumber, 'records'=>count($lines), 'events'=>$events, 'financial_effect'=>'not_applied'];
    }
    private function account(string $line, int $b, int $bd, int $c, int $cd, Agreement $a): void
    {
        if (substr($line,$b-1,5) !== $a->branch || $line[$bd-1] !== $a->branchDigit
            || substr($line,$c-1,12) !== $a->account || $line[$cd-1] !== $a->accountDigit) {
            throw new \UnexpectedValueException('bb_return_account_mismatch');
        }
    }
    private function detail(string $line, string $lot, int $sequence, string $segment): void
    {
        if (substr($line,3,5) !== $lot.'3' || substr($line,8,5) !== Fields::digits((string) $sequence,5)
            || $line[13] !== $segment || $line[14] !== ' ') throw new \UnexpectedValueException('bb_return_sequence_invalid');
    }
    private function numeric(string $value): int
    {
        if (!preg_match('/^[0-9]{1,15}$/D', $value)) throw new \UnexpectedValueException('bb_return_numeric_invalid');
        return (int) $value;
    }
    private function observation(string $code, string $operation): string
    {
        if ($operation === 'P' || $code === '93') return 'operational_down_reported';
        return ['02'=>'registration_reported','03'=>'registration_rejected','06'=>'settlement_reported',
            '09'=>'removal_reported','17'=>'settlement_after_removal_reported','44'=>'cheque_returned',
            '50'=>'cheque_pending_clearance'][$code] ?? 'unmapped_occurrence';
    }
}
