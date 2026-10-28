<?php

declare(strict_types=1);
namespace BancoBrasil;
require_once __DIR__.'/Fields400.php';
require_once __DIR__.'/LayoutCatalog.php';

/** Validate the complete fixed portion of the supported CBR641 BRL/simple profile. */
final class Cnab400GeneratedSource
{
    public static function validate(array $origin,array $snapshot,array $batch):void
    {
        $bytes=$batch['content']??null;
        if(!is_string($bytes)||$bytes===''||strlen($bytes)>16*1024*1024||!str_ends_with($bytes,"\r\n")
            ||preg_match('/[^\x20-\x7e\r\n]/',$bytes)||!is_string($batch['content_hash']??null)
            ||!hash_equals($batch['content_hash'],hash('sha256',$bytes)))throw new \DomainException('bb400_generated_source_corrupt');
        $lines=explode("\r\n",substr($bytes,0,-2));if(count($lines)!==3)throw new \DomainException('bb400_generated_source_corrupt');
        foreach($lines as $i=>$line)if(strlen($line)!==400||substr($line,394,6)!==Fields::digits((string)($i+1),6))throw new \DomainException('bb400_generated_source_corrupt');
        $c=$snapshot['connection']??null;$title=$snapshot['title']??null;$recipient=$snapshot['document_snapshot']['recipient']??null;
        if(!is_array($c)||!is_array($title)||!is_array($recipient))throw new \DomainException('bb400_generated_source_corrupt');
        try{
            $a=new Agreement(new Scope((int)$origin['issuer_id'],(int)$c['id'],$c['account_id'],$c['environment'],$c['agreement_number']),$c['issuer_tax_id'],$snapshot['beneficiary_name']??$c['issuer_name'],$c['variation'],$c['wallet']);
            [$branch,$branchDv,$account,$accountDv]=Fields400::account($a);
            $check=[
                [substr($lines[0],0,2),'01'],[substr($lines[0],2,7),$c['environment']==='sandbox'?'TESTE  ':'REMESSA'],[substr($lines[0],9,10),'01COBRANCA'],
                [substr($lines[0],19,7),str_repeat(' ',7)],
                [substr($lines[0],26,4),$branch],[$lines[0][30],$branchDv],[substr($lines[0],31,8),$account],[$lines[0][39],$accountDv],
                [substr($lines[0],40,6),'000000'],[substr($lines[0],46,30),Fields::alpha($a->name,30,true)],[substr($lines[0],76,18),str_pad('001BANCODOBRASIL',18)],[substr($lines[0],129,7),$a->scope->agreement],
                [substr($lines[0],100,7),Fields::digits((string)$batch['file_sequence'],7)],[substr($lines[0],107,22),str_repeat(' ',22)],[substr($lines[0],136,258),str_repeat(' ',258)],
                [$lines[1][0],'7'],[substr($lines[1],1,2),strlen($a->taxId)===11?'01':'02'],[substr($lines[1],3,14),Fields::taxField($a->taxId,14)],
                [substr($lines[1],17,4),$branch],[$lines[1][21],$branchDv],[substr($lines[1],22,8),$account],[$lines[1][30],$accountDv],
                [substr($lines[1],31,7),$a->scope->agreement],[substr($lines[1],38,25),Fields::alpha($title['control'],25,true)],
                [substr($lines[1],63,17),$a->ourNumber($title['sequence'])],[substr($lines[1],80,4),'0000'],
                [substr($lines[1],84,2),LayoutCatalog::CNAB400_BRL_CURRENCY_FIELD],[substr($lines[1],86,5),str_repeat(' ',5)],
                [substr($lines[1],91,3),$a->variation],[substr($lines[1],94,7),'0000000'],[substr($lines[1],101,5),str_repeat(' ',5)],[substr($lines[1],106,2),'17'],
                [substr($lines[1],108,2),'01'],[substr($lines[1],110,10),Fields::alpha($title['document'],10,true)],
                [substr($lines[1],120,6),Fields400::date($title['due_date'])],[substr($lines[1],126,13),Fields400::money($title['amount'])],
                [substr($lines[1],139,7),'0010000'],[$lines[1][146],' '],[substr($lines[1],150,6),Fields400::date($title['issue_date'])],
                [substr($lines[1],156,4),'0700'],[substr($lines[1],160,13),LayoutCatalog::CNAB400_DAILY_INTEREST_FIELD],[substr($lines[1],173,45),str_repeat('0',45)],
                [substr($lines[1],218,2),strlen(Fields::taxId($recipient['tax_id']))===11?'01':'02'],[substr($lines[1],220,14),Fields::taxField($recipient['tax_id'],14)],
                [substr($lines[1],271,3),str_repeat(' ',3)],[substr($lines[1],351,40),str_repeat(' ',40)],[substr($lines[1],391,2),'00'],[$lines[1][393],'N'],
                [$lines[2][0],'9'],[substr($lines[2],1,393),str_repeat(' ',393)],
            ];
            $generationDate=Fields400::parseDate(substr($lines[0],94,6));
            $payerName=rtrim(substr($lines[1],234,37),' ');$payerAddress=rtrim(substr($lines[1],274,40),' ');$payerDistrict=rtrim(substr($lines[1],314,12),' ');$payerCity=rtrim(substr($lines[1],334,15),' ');
            if($generationDate<$title['issue_date']||$generationDate>$title['due_date']||!in_array(substr($lines[1],147,2),['01','02','05','08','12','13'],true)||!in_array($lines[1][149],['A','N'],true)
                ||Fields::alpha($payerName,37,true)!==substr($lines[1],234,37)||Fields::alpha($payerAddress,40,true)!==substr($lines[1],274,40)
                ||Fields::alpha($payerDistrict,12,true)!==substr($lines[1],314,12)||Fields::alpha($payerCity,15,true)!==substr($lines[1],334,15)
                ||!preg_match('/^[0-9]{8}$/D',substr($lines[1],326,8))||!in_array(substr($lines[1],349,2),['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'],true))throw new \DomainException('bb400_generated_source_corrupt');
            foreach($check as [$actual,$expected])if($actual!==$expected)throw new \DomainException('bb400_generated_source_corrupt');
            if($a->scope->key()!==($origin['scope_key']??null)||$a->ourNumber($title['sequence'])!==($origin['remote_id']??null)
                ||$title['control']!==($origin['control_reference']??null)||(int)($snapshot['invoice']['id']??0)!==(int)($origin['invoice_id']??0))throw new \DomainException('bb400_generated_source_corrupt');
        }catch(\DomainException $error){throw $error;}
        catch(\Throwable $error){throw new \DomainException('bb400_generated_source_corrupt',0,$error);}
    }
}
