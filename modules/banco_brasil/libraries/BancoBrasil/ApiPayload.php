<?php

declare(strict_types=1);
namespace BancoBrasil;
require_once __DIR__.'/ApiClient.php';
require_once __DIR__.'/Agreement.php';
require_once __DIR__.'/ApiDailyInterest.php';require_once __DIR__.'/ApiFixedDiscount.php';
final class ApiPayload
{
    public static function input(array $input):array
    {
        $fields=['payer_address'=>60,'payer_district'=>30,'payer_zip'=>8,'payer_city'=>30,'payer_state'=>2,'species'=>2,'acceptance'=>1];
        if(array_diff(array_keys($input),array_merge(array_keys($fields),['daily_interest','monthly_interest_percentage','fixed_discount','discount_percentage','discount_until']))||array_diff(array_keys($fields),array_keys($input)))throw new \DomainException('bb_api_title_invalid');
        foreach($fields as $field=>$max){$value=$input[$field];if(!is_string($value)||trim($value)===''||strlen($value)>$max||preg_match('/[\x00-\x1f\x7f]/',$value)||preg_match('//u',$value)!==1)throw new \DomainException('bb_api_title_invalid');$input[$field]=trim($value);}
        if(!preg_match('/^[0-9]{8}$/D',$input['payer_zip'])||!in_array($input['payer_state'],['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'],true)||!in_array($input['species'],['02','04','12','17','19'],true)||!in_array($input['acceptance'],['A','N'],true))throw new \DomainException('bb_api_title_invalid');
        if(isset($input['daily_interest']))$input['daily_interest']=ApiDailyInterest::amount($input['daily_interest']);
        if(isset($input['monthly_interest_percentage']))$input['monthly_interest_percentage']=ApiDailyInterest::monthlyRate($input['monthly_interest_percentage']);
        if(array_key_exists('fixed_discount',$input))$input['fixed_discount']=ApiFixedDiscount::amount($input['fixed_discount']);if(array_key_exists('discount_until',$input))$input['discount_until']=ApiFixedDiscount::date($input['discount_until']);
        if(array_key_exists('discount_percentage',$input))$input['discount_percentage']=ApiPercentageDiscount::percentage($input['discount_percentage']);
        return array_replace($fields,$input);
    }
    public static function build(Agreement $a,array $invoice,array $doc,string $number,array $input,array $profile=[]):string
    {
        $input=self::input($input);$amount=\app\services\InvoicePaymentWriter::minorUnits($invoice['total']);
        if($amount<1||$amount>9999999999||$doc['identity']['tax_id']!==$a->taxId)throw new \DomainException('bb_api_invoice_profile_denied');
        foreach(['date','duedate'] as $field)Fields::date($invoice[$field]);
        $today=(new \DateTimeImmutable('now',new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');
        if($invoice['date']>$today||$invoice['duedate']<$today||$invoice['duedate']<$invoice['date']||(new \DateTimeImmutable($invoice['date']))->diff(new \DateTimeImmutable($invoice['duedate']))->days>2500)throw new \DomainException('bb_api_invoice_dates_invalid');
        $tax=Fields::taxId($doc['recipient']['tax_id']);$name=$doc['recipient']['name'];
        if($name===''||strlen($name)>60)throw new \DomainException('bb_api_payer_invalid');
        $money=Fields::amount($amount);$date=static fn($iso)=>(new \DateTimeImmutable($iso))->format('d.m.Y');
        $data=['numeroConvenio'=>(int)$a->scope->agreement,'numeroCarteira'=>17,'numeroVariacaoCarteira'=>(int)$a->variation,'codigoModalidade'=>1,'dataEmissao'=>$date($invoice['date']),'dataVencimento'=>$date($invoice['duedate']),'valorOriginal'=>$money,'valorAbatimento'=>0,'quantidadeDiasProtesto'=>0,'quantidadeDiasNegativacao'=>0,'indicadorAceiteTituloVencido'=>'N','codigoAceite'=>$input['acceptance'],'codigoTipoTitulo'=>(int)$input['species'],'indicadorPermissaoRecebimentoParcial'=>'N','numeroTituloBeneficiario'=>'CRM'.$invoice['id'],'numeroTituloCliente'=>$number,'campoUtilizacaoBeneficiario'=>'CRM'.$invoice['id'].'N'.(string)(int)substr($number,-10),'desconto'=>['tipo'=>0],'jurosMora'=>['tipo'=>3],'multa'=>['tipo'=>0],'indicadorPix'=>'N','pagador'=>['tipoInscricao'=>strlen($tax)===11?1:2,'numeroInscricao'=>$tax,'nome'=>$name,'endereco'=>$input['payer_address'],'bairro'=>$input['payer_district'],'cep'=>(int)$input['payer_zip'],'cidade'=>$input['payer_city'],'uf'=>$input['payer_state']]];
        $data['jurosMora']=ApiDailyInterest::parameters($profile,$input,$amount);$data['desconto']=ApiFixedDiscount::parameters($profile,$input,$invoice,$today,$amount);
        // OpenAPI requires a JSON number. Encode its validated decimal lexeme without float conversion.
        $json=ApiFixedDiscount::encode(ApiDailyInterest::encode($data),$data);$json=preg_replace('/"valorOriginal":"([0-9]+\.[0-9]{2})"/','"valorOriginal":$1',$json,1,$count);if($count!==1)throw new \DomainException('bb_api_payload_invalid');return $json;
    }
    public static function validateResult(string $operation,array $data,array $snapshot,array $origin,Agreement $a):array
    {
        if($operation==='issue'){
            if(($data['numero']??null)!==$origin['remote_id']||!self::sameInteger($data['numeroCarteira']??null,'17')||!self::sameInteger($data['numeroVariacaoCarteira']??null,(string)(int)$a->variation)||!self::sameInteger($data['beneficiario']['agencia']??null,(string)(int)$a->branch)||!self::sameInteger($data['beneficiario']['contaCorrente']??null,(string)(int)$a->account))throw new \DomainException('bb_api_registration_scope_mismatch');
            $barcode=$data['codigoBarraNumerico']??null;$line=$data['linhaDigitavel']??null;
            if(!is_string($barcode)||!preg_match('/^0019[0-9]{40}$/D',$barcode)||!is_string($line)||!preg_match('/^[0-9]{47}$/D',$line)||(int)substr($barcode,9,10)!==\app\services\InvoicePaymentWriter::minorUnits($snapshot['invoice']['total']))throw new \DomainException('bb_api_registration_document_invalid');
            if(substr($barcode,19)!=='000000'.substr($origin['remote_id'],3).'17')throw new \DomainException('bb_api_registration_document_invalid');
            if(self::line($barcode)!==$line)throw new \DomainException('bb_api_registration_document_invalid');
            $factor=1000+(int)(new \DateTimeImmutable('2025-02-22'))->diff(new \DateTimeImmutable($snapshot['invoice']['duedate']))->format('%r%a');
            if($factor<1000||$factor>9999||substr($barcode,5,4)!==str_pad((string)$factor,4,'0',STR_PAD_LEFT))throw new \DomainException('bb_api_registration_document_invalid');
            return ['numero'=>$data['numero'],'linhaDigitavel'=>$line,'codigoBarraNumerico'=>$barcode,'numeroContratoCobranca'=>self::bounded($data['numeroContratoCobranca']??null,40),'bank_state'=>'registration_accepted'];
        }
        if($operation==='cancel'){
            $contract=self::bounded($data['numeroContratoCobranca']??null,40);$date=self::bounded($data['dataBaixa']??null,10);$time=self::bounded($data['horarioBaixa']??null,8);
            if(!preg_match('/^[0-2][0-9]:[0-5][0-9]:[0-5][0-9]$/D',$time)||!preg_match('/^[0-9]{2}[.\/][0-9]{2}[.\/][0-9]{4}$/D',$date))throw new \DomainException('bb_api_cancellation_response_invalid');
            if(isset($snapshot['accepted_contract'])&&!self::sameInteger($contract,$snapshot['accepted_contract']))throw new \DomainException('bb_api_cancellation_response_invalid');
            return ['numeroContratoCobranca'=>$contract,'dataBaixa'=>$date,'horarioBaixa'=>$time,'bank_state'=>'cancellation_requested'];
        }
        $native=\app\services\InvoicePaymentWriter::minorUnits($snapshot['invoice']['total']);$tax=Fields::taxId($snapshot['document']['recipient']['tax_id']);
        if(($data['numeroTituloCedenteCobranca']??null)!=='CRM'.$origin['invoice_id']||Fields::taxId((string)($data['numeroInscricaoSacadoCobranca']??''))!==$tax||!self::sameInteger($data['codigoTipoInscricaoSacado']??null,strlen($tax)===11?'1':'2')||!self::sameInteger($data['numeroCarteiraCobranca']??null,'17')||!self::sameInteger($data['numeroVariacaoCarteiraCobranca']??null,(string)(int)$a->variation)||!self::sameInteger($data['codigoModalidadeTitulo']??null,'1')||!self::sameInteger($data['codigoIndicadorEconomico']??null,'9')||\app\services\InvoicePaymentWriter::minorUnits($data['valorOriginalTituloCobranca']??null)!==$native)throw new \DomainException('bb_api_query_scope_mismatch');
        foreach(['dataEmissaoTituloCobranca'=>'date','dataVencimentoTituloCobranca'=>'duedate'] as $field=>$source){$date=$data[$field]??null;if(!is_string($date)||!preg_match('/^([0-9]{2})[.\/]([0-9]{2})[.\/]([0-9]{4})$/D',$date,$m)||$m[3].'-'.$m[2].'-'.$m[1]!==$snapshot['invoice'][$source])throw new \DomainException('bb_api_query_scope_mismatch');}
        if(isset($data['textoCampoUtilizacaoCedente'])&&trim($data['textoCampoUtilizacaoCedente'])!==$origin['control_reference'])throw new \DomainException('bb_api_query_scope_mismatch');
        if(isset($snapshot['accepted_contract'])&&!self::sameInteger($data['numeroContratoCobranca']??null,$snapshot['accepted_contract']))throw new \DomainException('bb_api_query_scope_mismatch');
        $state=$data['codigoEstadoTituloCobranca']??null;if(!is_string($state)||!preg_match('/^[0-9]{1,2}$/D',$state)||!in_array((int)$state,[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,21,80],true))throw new \DomainException('bb_api_query_state_invalid');
        $result=['bank_state'=>'query_observed','codigoEstadoTituloCobranca'=>$state,'numeroContratoCobranca'=>self::bounded($data['numeroContratoCobranca']??null,40)];
        foreach(['valorOriginalTituloCobranca','valorAtualTituloCobranca','valorPagamentoParcialTitulo','valorImpostoSobreOprFinanceirasRecebidoTitulo','valorAbatimentoTotal','valorJuroMoraRecebido','valorDescontoUtilizado','valorPagoSacado','valorCreditoCedente','valorMultaRecebido','valorReajuste','valorOutroRecebido'] as $field)if(isset($data[$field]))$result[$field]=Fields::amount(\app\services\InvoicePaymentWriter::minorUnits($data[$field]));
        foreach(['dataCreditoLiquidacao','dataRecebimentoTitulo','dataEmissaoTituloCobranca','dataRegistroTituloCobranca','dataVencimentoTituloCobranca','codigoTipoBaixaTitulo','codigoTipoLiquidacao','codigoNaturezaRecebimento'] as $field)if(isset($data[$field]))$result[$field]=self::bounded($data[$field],40);
        // State6 is observation from an authenticated request; this client never creates a financial receipt.
        return $result;
    }
    private static function sameInteger($value,string $expected):bool{return is_string($value)&&preg_match('/^[0-9]+(?:\.0+)?$/D',$value)&&ltrim(explode('.',$value)[0],'0')===ltrim($expected,'0');}
    private static function bounded($value,int $length):string
    {
        if(!is_string($value)||$value===''||strlen($value)>$length||preg_match('/[^\x20-\x7e]/',$value))throw new \DomainException('bb_api_response_invalid');return $value;
    }
    public static function line(string $b):string
    {
        if(!preg_match('/^[0-9]{44}$/D',$b))throw new \DomainException('bb_api_barcode_invalid');
        $sum=0;$weight=2;for($i=43;$i>=0;$i--){if($i===4)continue;$sum+=(int)$b[$i]*$weight;$weight=$weight===9?2:$weight+1;}$dv=11-($sum%11);if(in_array($dv,[0,10,11],true))$dv=1;if((int)$b[4]!==$dv)throw new \DomainException('bb_api_barcode_invalid');
        $mod10=static function(string $block):string{$sum=0;$weight=2;for($i=strlen($block)-1;$i>=0;$i--){$n=(int)$block[$i]*$weight;$sum+=intdiv($n,10)+$n%10;$weight=$weight===2?1:2;}return (string)((10-$sum%10)%10);};
        $one=substr($b,0,4).substr($b,19,5);$two=substr($b,24,10);$three=substr($b,34,10);return $one.$mod10($one).$two.$mod10($two).$three.$mod10($three).$b[4].substr($b,5,14);
    }
}
