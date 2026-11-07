<?php
declare(strict_types=1);
namespace BancoBrasil;
require_once __DIR__.'/Agreement.php';
require_once __DIR__.'/ApiPayload.php';

/** BB Doc5175 annex IX, seven-digit agreement/wallet17; no bank registration assertion. */
final class CnabBoleto
{
    public static function svg(string $numeric):string
    {
        ApiPayload::line($numeric);
        require_once APPPATH.'vendor/tecnickcom/tcpdf/tcpdf_barcodes_1d.php';
        $encoder=new \TCPDFBarcode($numeric,'I25');$data=$encoder->getBarcodeArray();
        $svg=$encoder->getBarcodeSVGcode(2,30,'black');
        $svg=substr($svg,strpos($svg,'<svg'));
        // A viewport alone clips TCPDF's fixed coordinates. Scale all bars into
        // the physical103x13mm print area and omit XML/external DTD declarations.
        return preg_replace('/<svg /','<svg viewBox="0 0 '.($data['maxw']*2).' 30" preserveAspectRatio="none" ',$svg,1);
    }
    public static function codes(Agreement $agreement,string $sequence,string $due,string $amount):array
    {
        Fields::date($due);
        if(!preg_match('/^[0-9]{1,10}$/D',$sequence)||(int)$sequence<1)throw new \DomainException('bb_boleto_number_invalid');
        $number=$agreement->ourNumber($sequence);
        if(strlen($number)!==17)throw new \DomainException('bb_boleto_number_invalid');
        $cents=\app\services\InvoicePaymentWriter::minorUnits($amount);
        if($cents<1||$cents>9999999999)throw new \DomainException('bb_boleto_amount_invalid');
        $utc=new \DateTimeZone('UTC');
        $factor=1000+(int)(new \DateTimeImmutable('2025-02-22',$utc))->diff(new \DateTimeImmutable($due,$utc))->format('%r%a');
        if($factor<1000||$factor>9999)throw new \DomainException('bb_boleto_date_unsupported');
        $barcode='00190'.(string)$factor.str_pad((string)$cents,10,'0',STR_PAD_LEFT).'000000'.$number.$agreement->wallet;
        $sum=0;$weight=2;
        for($i=43;$i>=0;$i--){if($i===4)continue;$sum+=(int)$barcode[$i]*$weight;$weight=$weight===9?2:$weight+1;}
        $dv=11-$sum%11;if(in_array($dv,[0,10,11],true))$dv=1;$barcode[4]=(string)$dv;
        $line=ApiPayload::line($barcode);
        return ['our_number'=>$number,'barcode'=>$barcode,'line'=>$line,'formatted_line'=>substr($line,0,5).'.'.substr($line,5,5).' '.substr($line,10,5).'.'.substr($line,15,6).' '.substr($line,21,5).'.'.substr($line,26,6).' '.$line[32].' '.substr($line,33)];
    }
}
