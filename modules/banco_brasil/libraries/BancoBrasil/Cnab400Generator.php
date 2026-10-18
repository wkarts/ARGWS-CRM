<?php
declare(strict_types=1);
namespace BancoBrasil;
require_once __DIR__.'/Fields400.php';
require_once __DIR__.'/Record400.php';
require_once __DIR__.'/LayoutCatalog.php';

/** Candidate codec only. No operational download/transmission endpoint is registered. */
final class Cnab400Generator
{
    /**
     * BRL's wire code is not specified by CBR641 July/2026 note 30. The caller must
     * supply a raw candidate explicitly; this method never certifies it as bank-approved.
     * It is deliberately absent from the administrative operational capabilities.
     */
    public function candidate(Agreement $a, int $fileSequence, string $date, array $titles, string $currencyField): array
    {
        if (!preg_match('/^[A-Z0-9 ]{2}$/D', $currencyField) || in_array($currencyField,['02','14'],true)) {
            throw new \InvalidArgumentException('bb400_brl_candidate_invalid');
        }
        if ($fileSequence < 1 || $fileSequence > 9999999 || !$titles || count($titles) > 99997) throw new \InvalidArgumentException('bb_batch_invalid');
        [$branch,$branchDv,$account,$accountDv] = Fields400::account($a);
        $header = (new Record400())->put(1,2,'01')->put(3,9,$a->scope->environment==='sandbox'?'TESTE  ':'REMESSA')
            ->put(10,19,'01COBRANCA')->put(27,30,$branch)->put(31,31,$branchDv)->put(32,39,$account)->put(40,40,$accountDv)
            ->put(41,46,'000000')->put(47,76,Fields::alpha($a->name,30,true))->put(77,94,str_pad('001BANCODOBRASIL',18))
            ->put(95,100,Fields400::date($date))->put(101,107,Fields::digits((string)$fileSequence,7))
            ->put(130,136,$a->scope->agreement)->put(395,400,'000001');
        $lines=[(string)$header];$numbers=$controls=[];$seq=1;
        foreach($titles as $t){
            $expected=['sequence','document','control','issue_date','due_date','amount','species','acceptance',
                'payer_tax_id','payer_name','payer_address','payer_district','payer_zip','payer_city','payer_state'];
            if(!is_array($t)||array_diff(array_keys($t),$expected)||array_diff($expected,array_keys($t)))throw new \InvalidArgumentException('bb_title_fields_invalid');
            foreach($t as $v)if(!is_string($v))throw new \InvalidArgumentException('bb_title_field_type_invalid');
            $number=$a->ourNumber($t['sequence']);$control=Fields::alpha($t['control'],25,true);
            if(isset($numbers[$number])||isset($controls[$control]))throw new \DomainException('bb_duplicate_title');
            $numbers[$number]=$controls[$control]=true;
            Fields400::date($t['issue_date']);Fields400::date($t['due_date']);
            if($t['issue_date']>$date||$t['issue_date']>$t['due_date']||$t['due_date']<$date
                ||(new \DateTimeImmutable($t['issue_date']))->diff(new \DateTimeImmutable($t['due_date']))->days>2500)throw new \InvalidArgumentException('bb_title_dates_invalid');
            if(Fields::cents($t['amount'])<1||!in_array($t['species'],['01','02','05','08','12','13'],true)
                ||!in_array($t['acceptance'],['A','N'],true))throw new \InvalidArgumentException('bb400_title_profile_unsupported');
            $tax=Fields::taxId($t['payer_tax_id']);
            if(!preg_match('/^[0-9]{8}$/D',$t['payer_zip'])||!in_array($t['payer_state'],
                ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'],true))throw new \InvalidArgumentException('bb_payer_address_invalid');
            $line=(new Record400())->put(1,1,'7')->put(2,3,strlen($a->taxId)===11?'01':'02')->put(4,17,Fields::taxField($a->taxId,14))
                ->put(18,21,$branch)->put(22,22,$branchDv)->put(23,30,$account)->put(31,31,$accountDv)->put(32,38,$a->scope->agreement)
                ->put(39,63,$control)->put(64,80,$number)->put(81,84,'0000')->put(85,86,$currencyField)
                ->put(92,94,$a->variation)->put(95,101,'0000000')->put(107,108,'17')->put(109,110,'01')
                ->put(111,120,Fields::alpha($t['document'],10,true))->put(121,126,Fields400::date($t['due_date']))
                ->put(127,139,Fields400::money($t['amount']))->put(140,146,'0010000')->put(148,149,$t['species'])
                ->put(150,150,$t['acceptance'])->put(151,156,Fields400::date($t['issue_date']))->put(157,160,'0700')
                ->put(161,218,str_repeat('0',58))->put(219,220,strlen($tax)===11?'01':'02')->put(221,234,Fields::taxField($tax,14))
                ->put(235,271,Fields::alpha($t['payer_name'],37,true))->put(275,314,Fields::alpha($t['payer_address'],40,true))
                ->put(315,326,Fields::alpha($t['payer_district'],12,true))->put(327,334,$t['payer_zip'])
                ->put(335,349,Fields::alpha($t['payer_city'],15,true))->put(350,351,$t['payer_state'])
                ->put(392,393,'00')->put(394,394,'N')->put(395,400,Fields::digits((string)++$seq,6));
            $lines[]=(string)$line;
        }
        $lines[]=(string)(new Record400())->put(1,1,'9')->put(395,400,Fields::digits((string)++$seq,6));
        return ['content'=>implode("\r\n",$lines)."\r\n",'layout'=>LayoutCatalog::CNAB400,
            'state'=>'candidate_requires_bank_confirmation','operational'=>false,
            'unresolved'=>['brl_currency_wire_code','interest_inherited_from_agreement','homologation_per_connection']];
    }
}
