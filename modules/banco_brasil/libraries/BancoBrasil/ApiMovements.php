<?php

declare(strict_types=1);
namespace BancoBrasil;
require_once __DIR__.'/Fields.php';

/** Official return-movement pages are observations. No inferred event ID, cursor increment or financial authority. */
final class ApiMovements
{
    public static function input(array $input):array
    {
        if(array_diff(array_keys($input),['from','to','cursor'])||!is_string($input['from']??null)||!is_string($input['to']??null)||!is_string($input['cursor']??null)||!preg_match('/^[1-9][0-9]{0,9}$/D',$input['cursor'])||(int)$input['cursor']>2147483647)throw new \DomainException('bb_api_movements_invalid');Fields::date($input['from']);Fields::date($input['to']);
        $today=(new \DateTimeImmutable('now',new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');if($input['to']<$input['from']||$input['to']>$today||(new \DateTimeImmutable($input['from']))->diff(new \DateTimeImmutable($input['to']))->days>31)throw new \DomainException('bb_api_movements_invalid');return ['from'=>$input['from'],'to'=>$input['to'],'cursor'=>(string)(int)$input['cursor']];
    }
    public static function payload(Agreement $agreement,array $window):string
    {
        $window=self::input($window);$date=static fn($v)=>(new \DateTimeImmutable($v))->format('d.m.Y');
        return json_encode(['dataMovimentoRetornoInicial'=>$date($window['from']),'dataMovimentoRetornoFinal'=>$date($window['to']),'codigoPrefixoAgencia'=>(int)$agreement->branch,'numeroContaCorrente'=>(int)$agreement->account,'numeroCarteiraCobranca'=>17,'numeroVariacaoCarteiraCobranca'=>(int)$agreement->variation,'numeroRegistroPretendido'=>(int)$window['cursor'],'quantidadeRegistroPretendido'=>100],JSON_THROW_ON_ERROR);
    }
    private static function date($value):string
    {
        if(!is_string($value)||!preg_match('/^([0-9]{2})\.([0-9]{2})\.([0-9]{4})$/D',$value,$m))throw new \DomainException('bb_api_movements_response_invalid');$iso=$m[3].'-'.$m[2].'-'.$m[1];Fields::date($iso);return $iso;
    }
    private static function same($value,string $expected):bool{return is_string($value)&&preg_match('/^[0-9]+$/D',$value)&&ltrim($value,'0')===ltrim($expected,'0');}
    public static function projection(array $data,array $window,array $origin,array $snapshot,Agreement $agreement):array
    {
        $window=self::input($window);if(!in_array($data['indicadorContinuidade']??null,['S','N'],true)||!is_string($data['numeroUltimoRegistro']??null)||!preg_match('/^[0-9]{1,10}$/D',$data['numeroUltimoRegistro'])||(int)$data['numeroUltimoRegistro']>2147483647||!is_array($data['listaRegistro']??null)||!array_is_list($data['listaRegistro'])||count($data['listaRegistro'])>100)throw new \DomainException('bb_api_movements_response_invalid');
        $rows=[];foreach($data['listaRegistro'] as $record){
            if(!is_array($record)||!is_string($record['numeroTituloCobranca']??null)||!preg_match('/^[0-9]{20}$/D',$record['numeroTituloCobranca']))throw new \DomainException('bb_api_movements_response_invalid');
            // An account-level page can contain other titles. Never hydrate/store their customer fields in this origin operation.
            if($record['numeroTituloCobranca']!==$origin['remote_id'])continue;
            foreach(['numeroConvenio'=>$agreement->scope->agreement,'codigoPrefixoAgencia'=>$agreement->branch,'numeroContaCorrente'=>$agreement->account,'numeroCarteiraCobranca'=>'17','numeroVariacaoCarteiraCobranca'=>$agreement->variation] as $field=>$expected)if(!self::same($record[$field]??null,$expected))throw new \DomainException('bb_api_movements_scope_mismatch');
            if(!in_array($record['tipoCobranca']??null,['1','7'],true)||($record['codigoControleParticipante']??null)!==$origin['control_reference']||!is_string($record['codigoComandoAcao']??null)||!preg_match('/^[0-9]{1,2}$/D',$record['codigoComandoAcao']))throw new \DomainException('bb_api_movements_scope_mismatch');
            $row=['command'=>str_pad($record['codigoComandoAcao'],2,'0',STR_PAD_LEFT),'movement_date'=>self::date($record['dataMovimentoRetorno']??null),'due_date'=>self::date($record['dataVencimentoBoleto']??null)];if($row['movement_date']<$window['from']||$row['movement_date']>$window['to'])throw new \DomainException('bb_api_movements_scope_mismatch');
            if(isset($record['dataCreditoPagamentoBoleto'])&&$record['dataCreditoPagamentoBoleto']!=='')$row['credit_date']=self::date($record['dataCreditoPagamentoBoleto']);
            foreach(['valorBoleto'=>'nominal','valorTarifa'=>'fee','valorOutrasDespesasCalculadas'=>'other_expenses','valorJurosDesconto'=>'discount_interest','valorIofDesconto'=>'iof','valorAbatimento'=>'abatement','valorDesconto'=>'discount','valorRecebido'=>'received','valorJurosMora'=>'interest','valorOutrosValoresRecebidos'=>'other_received','valorAbatimentoNaoUtilizado'=>'unused_abatement','valorLancamento'=>'posting','valorAjuste'=>'adjustment'] as $field=>$target){if(!isset($record[$field]))throw new \DomainException('bb_api_movements_response_invalid');$row[$target]=Fields::amount(\app\services\InvoicePaymentWriter::minorUnits($record[$field]));}
            if(\app\services\InvoicePaymentWriter::minorUnits($row['nominal'])!==\app\services\InvoicePaymentWriter::minorUnits($snapshot['invoice']['total']))throw new \DomainException('bb_api_movements_scope_mismatch');
            foreach(['codigoFormaPagamento'=>'posting_direction','codigoValorAjuste'=>'adjustment_direction','naturezaRecebimento'=>'nature','codigoAutorizacaoPagamentoParcial'=>'partial_permission'] as $field=>$target){if(!is_string($record[$field]??null)||!preg_match('/^[0-9]{1,2}$/D',$record[$field]))throw new \DomainException('bb_api_movements_response_invalid');$row[$target]=(string)(int)$record[$field];}
            if(!in_array($row['posting_direction'],['0','1','2'],true)||!in_array($row['adjustment_direction'],['0','1','2'],true)||!in_array($row['partial_permission'],['1','2'],true))throw new \DomainException('bb_api_movements_response_invalid');
            $row['observation']=match($row['command']){'44'=>'cheque_returned','46'=>'cheque_pending_clearance','93'=>'operational_down_reported','06','07','08'=>'settlement_movement_observed',default=>'bank_command_observed'};$rows[]=$row;
        }
        return ['bank_state'=>'movement_page_observed','movement_window'=>$window,'continuation'=>$data['indicadorContinuidade'],'last_record'=>$data['numeroUltimoRegistro'],'movements'=>$rows,'financial_authority'=>'none_observation_only'];
    }
    public static function assertNoAdverseHistory($db,string $p,array $origin,bool $api):void
    {
        $remote=$origin['remote_id'];$short=strlen($remote)===20?substr($remote,3):$remote;$long='000'.$short;
        foreach(\IssuerStorage::query($db,"SELECT x.result_json FROM {$p}bb_api_operations x JOIN {$p}bb_origins o ON o.id=x.origin_id WHERE o.issuer_id=? AND o.account_id=? AND o.environment=? AND o.agreement_number=? AND o.remote_id IN (?,?) AND x.operation='movements' AND x.status='validated' FOR UPDATE",[$origin['issuer_id'],$origin['account_id'],$origin['environment'],$origin['agreement_number'],$short,$long])->result_array() as $observation){$data=json_decode($observation['result_json'],true,32,JSON_THROW_ON_ERROR);foreach($data['movements']??[] as $row)if(in_array($row['command']??null,['44','46','93'],true)||in_array($row['nature']??null,['4','10','11'],true))throw new \DomainException($api?'bb_api_credit_adverse_history':'bb_event_adverse_history_requires_review');}
    }
}
