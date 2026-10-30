<?php
declare(strict_types=1);
namespace BancoBrasil;
require_once __DIR__ . '/Agreement.php';
require_once __DIR__ . '/Record.php';

/** Pure codec. This is not authorization to create, deliver, or transmit a charge. */
final class Cnab240Generator
{
    /**
     * Narrow contract: 17/simple, 7-digit agreement, company-generated Nosso Número,
     * registered BRL title, P/Q, no discounts, penalty, IOF, protest or partial payment.
     * The bank must separately confirm interest exemption in the wallet configuration.
     */
    public function generate(Agreement $a, int $fileSequence, string $date, array $titles): string
    {
        if ($fileSequence < 1 || $fileSequence > 999999 || !$titles || count($titles) > 49999) {
            throw new \InvalidArgumentException('bb_batch_invalid');
        }
        $wireDate = Fields::date($date);
        $test = $a->scope->environment === 'sandbox';
        $header = (new Record())->put(1, 8, '00100000')
            ->put(18, 18, strlen($a->taxId) === 11 ? '1' : '2')->put(19, 32, Fields::taxField($a->taxId, 14))
            ->put(33, 52, $a->wireAgreement())->put(53, 57, $a->branch)->put(58, 58, $a->branchDigit)
            ->put(59, 70, $a->account)->put(71, 71, $a->accountDigit)->put(73, 102, Fields::alpha($a->name, 30))
            ->put(103, 132, str_pad('BANCO DO BRASIL S.A.', 30, ' ', STR_PAD_LEFT))->put(143, 143, '1')
            ->put(144, 151, $wireDate)->put(152, 157, '000000')->put(158, 163, Fields::digits((string) $fileSequence, 6))
            ->put(164, 166, '084')->put(167, 171, '00000');
        $lot = (new Record())->put(1, 11, '00100011R01')->put(14, 16, '043')
            ->put(18, 18, strlen($a->taxId) === 11 ? '1' : '2')->put(19, 33, Fields::taxField($a->taxId, 15))
            ->put(34, 53, $a->wireAgreement($test))->put(54, 58, $a->branch)->put(59, 59, $a->branchDigit)
            ->put(60, 71, $a->account)->put(72, 72, $a->accountDigit)->put(74, 103, Fields::alpha($a->name, 30))
            ->put(184, 191, Fields::digits((string) $fileSequence, 8))->put(192, 199, $wireDate)->put(200, 207, '00000000');
        $lines = [(string) $header, (string) $lot];
        $seenNumbers = $seenDocuments = [];
        $seq = 0;
        foreach ($titles as $t) {
            $expected = ['sequence','document','control','issue_date','due_date','amount','species','acceptance',
                'payer_tax_id','payer_name','payer_address','payer_district','payer_zip','payer_city','payer_state'];
            if (!is_array($t) || array_diff(array_keys($t), $expected) || array_diff($expected, array_keys($t))) {
                throw new \InvalidArgumentException('bb_title_fields_invalid');
            }
            foreach ($t as $v) if (!is_string($v)) throw new \InvalidArgumentException('bb_title_field_type_invalid');
            $number = $a->ourNumber($t['sequence']);
            $control = Fields::alpha($t['control'], 25);
            if (isset($seenNumbers[$number]) || isset($seenDocuments[$control])) throw new \DomainException('bb_duplicate_title');
            $seenNumbers[$number] = $seenDocuments[$control] = true;
            Fields::date($t['issue_date']); Fields::date($t['due_date']);
            if ($t['issue_date'] > $date || $t['issue_date'] > $t['due_date'] || $t['due_date'] < $date
                || (new \DateTimeImmutable($t['issue_date']))->diff(new \DateTimeImmutable($t['due_date']))->days > 2500) {
                throw new \InvalidArgumentException('bb_title_dates_invalid');
            }
            $cents = Fields::cents($t['amount']);
            if ($cents < 1 || !in_array($t['species'], ['02','04','12','17','19'], true)
                || !in_array($t['acceptance'], ['A','N'], true)) throw new \InvalidArgumentException('bb_title_profile_unsupported');
            $p = $this->detail(++$seq, 'P')->put(18, 22, $a->branch)->put(23, 23, $a->branchDigit)
                ->put(24, 35, $a->account)->put(36, 36, $a->accountDigit)->put(38, 57, Fields::alpha($number, 20, true))
                ->put(58, 62, '71122')->put(63, 77, Fields::alpha($t['document'], 15))
                ->put(78, 85, Fields::date($t['due_date']))->put(86, 100, Fields::digits((string) $cents, 15))
                ->put(101, 105, '00000')->put(107, 108, $t['species'])->put(109, 109, $t['acceptance'])
                ->put(110, 117, Fields::date($t['issue_date']))->put(118, 118, '3')->put(119, 141, str_repeat('0', 23))
                ->put(142, 195, str_repeat('0', 54))->put(196, 220, $control)
                ->put(221, 221, '3')->put(222, 239, str_repeat('0', 18));
            $taxId = Fields::taxId($t['payer_tax_id']);
            if (!preg_match('/^[0-9]{8}$/D', $t['payer_zip']) || !in_array($t['payer_state'],
                ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'], true)) {
                throw new \InvalidArgumentException('bb_payer_address_invalid');
            }
            // BB only treats 37 name and 12 district characters. Refuse lossy truncation.
            $q = $this->detail(++$seq, 'Q')->put(18, 18, strlen($taxId) === 11 ? '1' : '2')
                ->put(19, 33, Fields::taxField($taxId, 15))->put(34, 70, Fields::alpha($t['payer_name'], 37))
                ->put(74, 113, Fields::alpha($t['payer_address'], 40))->put(114, 125, Fields::alpha($t['payer_district'], 12))
                ->put(129, 136, $t['payer_zip'])->put(137, 151, Fields::alpha($t['payer_city'], 15))
                ->put(152, 153, $t['payer_state'])->put(154, 169, str_repeat('0', 16))->put(210, 212, '000');
            $lines[] = (string) $p; $lines[] = (string) $q;
        }
        $lines[] = (string) (new Record())->put(1, 8, '00100015')->put(18, 23, Fields::digits((string) ($seq + 2), 6));
        $lines[] = (string) (new Record())->put(1, 8, '00199999')->put(18, 23, '000001')
            ->put(24, 29, Fields::digits((string) ($seq + 4), 6))->put(30, 35, '000000');
        return implode("\r\n", $lines) . "\r\n";
    }
    private function detail(int $sequence, string $segment): Record
    {
        return (new Record())->put(1, 8, '00100013')->put(9, 13, Fields::digits((string) $sequence, 5))
            ->put(14, 14, $segment)->put(16, 17, '01');
    }
}
