<?php
/** Reconstructed lost119 bootstrap for preserved440 tests. Not the lost original suite. */
declare(strict_types=1);
require __DIR__.'/banco_brasil_api16_components_mysql.php';
require_once APP_MODULES_PATH.'banco_brasil/libraries/BancoBrasil/ApiCreditedReceipt.php';
require_once APP_MODULES_PATH.'banco_brasil/libraries/BancoBrasil/ApiDiscountAmendment.php';
$discountAmendTransport=static function($method,$url,$headers,$body)use($percentageTransport,&$titles,$db):array{
    if($method==='PATCH'){
        essentialCheck(!$db->pdo->inTransaction(),'reconstructed119 instruction transport remains outside SQL locks');
        $wire=\BancoBrasil\ApiClient::decodeExact($body);preg_match('~/boletos/([0-9]{20})~',$url,$m);
        if(($wire['indicadorAlterarDesconto']??'N')!=='S'||!in_array($wire['alteracaoDesconto']['tipoPrimeiroDesconto']??null,['1','2'],true))throw new RuntimeException('unexpected119 bootstrap PATCH');
        $d=$wire['alteracaoDesconto'];$type=$d['tipoPrimeiroDesconto'];$titles[$m[1]]['desconto']=['tipo'=>$type,'dataExpiracao'=>$d['novaDataLimitePrimeiroDesconto'],($type==='1'?'valor':'porcentagem')=>$d[$type==='1'?'novoValorPrimeiroDesconto':'novoPercentualPrimeiroDesconto']];
        return ['status'=>200,'body'=>json_encode(['numeroContratoCobranca'=>1234,'dataAtualizacao'=>gmdate('d.m.Y'),'horarioAtualizacao'=>'12:34:56'],JSON_THROW_ON_ERROR)];
    }
    $reply=$percentageTransport($method,$url,$headers,$body);
    if($method==='GET'&&preg_match('~/boletos/([0-9]{20})~',$url,$m)){
        $offer=$titles[$m[1]]['desconto']??[];
        if(in_array($offer['tipo']??null,['1','2'],true)){
            $detail=json_decode($reply['body'],true,32,JSON_THROW_ON_ERROR);
            $detail=array_replace($detail,['codigoDescontoTitulo'=>(int)$offer['tipo'],'valorDescontoTitulo'=>(float)($offer['valor']??'0'),'percentualDescontoTitulo'=>(float)($offer['porcentagem']??'0'),'dataDescontoTitulo'=>$offer['dataExpiracao'],'codigoSegundoDescontoTitulo'=>0,'valorSegundoDescontoTitulo'=>0,'percentualSegundoDescontoTitulo'=>0,'codigoTerceiroDescontoTitulo'=>0,'valorTerceiroDescontoTitulo'=>0,'percentualTerceiroDescontoTitulo'=>0]);
            $reply['body']=json_encode($detail,JSON_THROW_ON_ERROR);
        }
    }
    return $reply;
};
$discountAmendApi=new \BancoBrasil\ApiService($db,$p,$encrypt,$decrypt,$discountAmendTransport);
$discountAmendInput=['type'=>'1','value'=>'12.00','until'=>$discountUntil,'contract_confirmed'=>'1','reference'=>'Synthetic independent discount contract','reason'=>'Synthetic first discount operational change'];
function discountAmendFresh(string $tag):array{
    global $s,$a,$apiInput,$discountAmendApi,$discountProfile,$discountTitle,$db,$p;
    $invoice=$s->create($a,7,$apiInput,hash('sha256','reconstructed119-discount-invoice-'.$tag));
    $origin=$discountAmendApi->issue($a,7,$discountProfile,$invoice,$discountTitle,hash('sha256','reconstructed119-discount-origin-'.$tag));
    $number=$db->query("SELECT remote_id FROM {$p}bb_origins WHERE id=?",[$origin])->row()->remote_id;
    $query=$discountAmendApi->query($a,7,$origin,hash('sha256','reconstructed119-discount-query-'.$tag));
    return compact('invoice','origin','number','query');
}
$db->query("INSERT INTO {$p}staff(staffid,active,admin) VALUES(22,1,0)");
$db->query("INSERT INTO {$p}issuers_staff(issuer_id,staff_id,can_view) VALUES(?,22,1)",[$a]);
foreach([['banco_brasil','view'],['banco_brasil','api_query'],['banco_brasil','api_amend_discount'],['invoices','view'],['invoices','edit']] as [$feature,$cap])$db->query("INSERT INTO {$p}issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,22,?,?)",[$a,$feature,$cap]);
essentialCheck(!(new IssuerResourceAccess($db,$p))->can($a,22,'banco_brasil','api_amend_window')&&!(new IssuerResourceAccess($db,$p))->can($a,22,'banco_brasil','api_assign_discount'),'reconstructed bootstrap does not pregrant new440 capabilities');
echo 'Reconstructed119 bootstrap for unchanged440 tests: original API16 fixture and restricted actor prepared; not a recovery of missing original tests.'.PHP_EOL;
