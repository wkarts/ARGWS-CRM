<?php
declare(strict_types=1);
namespace BancoBrasil;
require_once __DIR__.'/Fields400.php';
require_once __DIR__.'/LayoutCatalog.php';
require_once __DIR__.'/Cnab400Auxiliary.php';

/** CBR643 July/2023, seven-digit agreement, wallet 17 simple, records 0/7/9. */
final class Cnab400ReturnParser
{
    public function parse(Agreement $a, string $bytes,string $layout=LayoutCatalog::CNAB400): array
    {
        if(!in_array($layout,[LayoutCatalog::CNAB400,LayoutCatalog::CNAB400_AUX],true))throw new \UnexpectedValueException('bb400_layout_unsupported');
        Fields400::account($a);
        if($bytes===''||strlen($bytes)>16*1024*1024||substr($bytes,-2)!=="\r\n"||preg_match('/[^\x20-\x7E\r\n]/',$bytes))throw new \UnexpectedValueException('bb_return_encoding_invalid');
        $lines=explode("\r\n",substr($bytes,0,-2));$count=count($lines);
        if($count<3||$count>999999)throw new \UnexpectedValueException('bb_return_incomplete');
        foreach($lines as $i=>$line){
            if(strlen($line)!==400||substr($line,394,6)!==Fields::digits((string)($i+1),6))throw new \UnexpectedValueException('bb400_record_sequence_invalid');
        }
        $h=$lines[0];
        if(substr($h,0,19)!=='02RETORNO01COBRANCA'||substr($h,19,7)!==str_repeat(' ',7)
            ||substr($h,40,6)!=='000000'||substr($h,76,18)!==str_pad('001BANCODOBRASIL',18)
            ||substr($h,149,7)!==$a->scope->agreement||substr($h,156,238)!==str_repeat(' ',238))throw new \UnexpectedValueException('bb400_header_mismatch');
        $this->account($h,27,31,32,40,$a);
        $fileDate=Fields400::parseDate(substr($h,94,6));$returnSequence=Fields400::numeric(substr($h,100,7));
        if($returnSequence<1)throw new \UnexpectedValueException('bb400_return_sequence_invalid');
        $hash=hash('sha256',$bytes);$events=[];$seen=[];$parent=null;$auxSeen=[];
        for($i=1;$i<$count-1;$i++){
            $l=$lines[$i];
            if($l[0]==='5'&&$layout===LayoutCatalog::CNAB400_AUX){
                if($parent===null)throw new \UnexpectedValueException('bb400_auxiliary_parent_required');
                $service=substr($l,1,2);if(isset($auxSeen[$service]))throw new \UnexpectedValueException('bb400_duplicate_auxiliary');
                $event=Cnab400Auxiliary::event($parent,$l,$hash,$i+1);if(isset($seen[$event['event_key']]))throw new \UnexpectedValueException('bb_return_duplicate_event');
                $seen[$event['event_key']]=true;$auxSeen[$service]=true;$events[]=$event;continue;
            }
            // The original profile continues to reject every auxiliary record.
            if($l[0]!=='7')throw new \UnexpectedValueException('bb400_auxiliary_record_unsupported');
            $this->account($l,18,22,23,31,$a);
            if(substr($l,1,16)!==str_repeat('0',16)||substr($l,31,7)!==$a->scope->agreement
                ||$l[80]!=='7'||$l[81]!=='0'||substr($l,91,3)!==$a->variation||substr($l,106,2)!=='17'
                ||$l[105]!==' '||substr($l,126,20)!==str_repeat(' ',20))throw new \UnexpectedValueException('bb400_title_scope_mismatch');
            $number=substr($l,63,17);$control=rtrim(substr($l,38,25),' ');
            if(!preg_match('/^[0-9]{17}$/D',$number)||substr($number,0,7)!==$a->scope->agreement||substr($number,7)==='0000000000'
                ||$control===''||trim($control)!==$control||!preg_match('/^[A-Z0-9 ]+$/D',$control))throw new \UnexpectedValueException('bb_return_control_missing');
            $code=substr($l,108,2);$nature=substr($l,86,2);
            Fields400::numeric($code);Fields400::numeric($nature);
            if(!in_array($code,['06','07','08','15'],true)&&substr($l,175,6)!=='      ')throw new \UnexpectedValueException('bb400_credit_date_command_mismatch');
            if(in_array($code,['39','85','86'],true))throw new \UnexpectedValueException('bb400_overloaded_amount_unsupported');
            // Narrow simple collection profile: do not interpret discounted/shared balances as cash.
            if($l[94]!=='0'||substr($l,95,10)!==str_repeat('0',10)||$l[319]!=='0'
                ||substr($l,320,12)!==str_repeat('0',12)||substr($l,332,10)!==str_repeat(' ',10)
                ||substr($l,342,48)!==str_repeat('0',48)||$l[391]!==' '
                ||!in_array($l[318],['0','1','2'],true)||!in_array($l[390],['0','1','2','S','N',' '],true))throw new \UnexpectedValueException('bb400_collection_profile_unsupported');
            Fields400::numeric(substr($l,82,4));Fields400::numeric(substr($l,392,2));
            $event=['scope_key'=>$a->scope->key(),'provider'=>'banco_brasil','channel'=>'cnab400','layout'=>LayoutCatalog::CNAB400,
                'file_hash'=>$hash,'lot'=>0,'sequence'=>$i+1,'our_number'=>$number,'control'=>$control,'document'=>rtrim(substr($l,116,10),' '),
                'occurrence'=>$code,'nature'=>$nature,'reasons'=>[$nature],
                'currency'=>null,'declared_currency'=>'BRL','currency_evidence'=>'not_present_in_file',
                'nominal_amount'=>$this->money($l,153,13),'paid_amount'=>$this->money($l,254,13),
                'net_amount'=>null,'posting_amount'=>$this->money($l,306,13),'posting_direction'=>$l[318],
                'fees'=>$this->money($l,182,7),'other_expenses'=>$this->money($l,189,13),
                'discount_interest'=>$this->money($l,202,13),'iof'=>$this->money($l,215,13),
                'abatement'=>$this->money($l,228,13),'discount'=>$this->money($l,241,13),
                'interest'=>$this->money($l,267,13),'other_credits'=>$this->money($l,280,13),
                'unused_abatement'=>$this->money($l,293,13),'partial_authorization'=>$l[390],
                'payment_channel'=>substr($l,392,2),'due_date'=>Fields400::parseDate(substr($l,146,6),true),
                'occurrence_date'=>Fields400::parseDate(substr($l,110,6),true),'credit_date'=>Fields400::parseDate(substr($l,175,6),true),
                'observation'=>$this->observation($code,$nature),'financial_effect'=>'not_applied','requires_review'=>true];
            // Include the layout/channel: CNAB240 code 50 and CNAB400 code 46 have different meanings.
            $identity=[LayoutCatalog::CNAB400,$a->scope->key(),$number,$code,$nature,$event['paid_amount'],
                $event['posting_amount'],$event['posting_direction'],$event['occurrence_date'],$event['credit_date']];
            $event['event_key']=hash('sha256',json_encode($identity,JSON_THROW_ON_ERROR));
            if(isset($seen[$event['event_key']]))throw new \UnexpectedValueException('bb_return_duplicate_event');
            $seen[$event['event_key']]=true;$events[]=$event;$parent=$event;$auxSeen=[];
        }
        $t=$lines[$count-1];
        if(substr($t,0,7)!=='9201001')throw new \UnexpectedValueException('bb400_trailer_invalid');
        $totals=[];
        foreach(['simple'=>18,'linked'=>58,'secured'=>98,'discounted'=>138,'vendor'=>218] as $name=>$start){
            $quantity=Fields400::numeric(substr($t,$start-1,8));$raw=substr($t,$start+7,14);Fields400::numeric($raw);
            $notice=substr($t,$start+21,8);Fields400::numeric($notice);
            if($name!=='simple'&&($quantity!==0||(int)$raw!==0))throw new \UnexpectedValueException('bb400_trailer_profile_unsupported');
            $totals[$name]=['quantity'=>$quantity,'total_raw'=>$raw,'notice'=>$notice];
        }
        foreach([[8,10],[48,10],[88,10],[128,10],[168,50],[248,147]] as [$start,$width]){
            if(substr($t,$start-1,$width)!==str_repeat(' ',$width))throw new \UnexpectedValueException('bb400_trailer_invalid');
        }
        return ['file_hash'=>$hash,'scope_key'=>$a->scope->key(),'layout'=>$layout,'lots'=>0,'records'=>$count,
            'events'=>$events,'financial_effect'=>'not_applied','file_date'=>$fileDate,'return_sequence'=>$returnSequence,
            'trailer_raw_totals'=>$totals,'trailer_total_interpretation'=>'unverified_position_picture_disagreement',
            'environment_evidence'=>'not_present_in_file','issuer_tax_id_evidence'=>'not_present_in_file',
            'date_window'=>'2000-2099'];
    }
    private function account(string $line,int $b,int $bd,int $c,int $cd,Agreement $a):void
    {
        [$branch,$branchDv,$account,$accountDv]=Fields400::account($a);
        if(substr($line,$b-1,4)!==$branch||$line[$bd-1]!==$branchDv||substr($line,$c-1,8)!==$account||$line[$cd-1]!==$accountDv)throw new \UnexpectedValueException('bb_return_account_mismatch');
    }
    private function money(string $line,int $start,int $width):string
    {
        return Fields::amount(Fields400::numeric(substr($line,$start-1,$width)));
    }
    private function observation(string $code,string $nature):string
    {
        if($code==='93')return 'operational_down_reported';
        if($code==='44')return 'cheque_returned';
        if($code==='46'||(in_array($code,['05','06','07','08','15'],true)&&in_array($nature,['04','10','11'],true)))return 'cheque_pending_clearance';
        return ['02'=>'registration_reported','03'=>'registration_rejected','06'=>'settlement_reported',
            '07'=>'partial_receipt_reported','08'=>'balance_receipt_reported','09'=>'removal_reported',
            '10'=>'removal_reported','11'=>'portfolio_observed','15'=>'court_receipt_reported',
            '20'=>'debit_instruction_reported','73'=>'partial_policy_reported'][$code]??'unmapped_occurrence';
    }
}
