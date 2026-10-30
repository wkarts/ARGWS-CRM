<?php
/** Reconstructed119 scenarios under exact current-header native service integration: real native DB/writer, wholly simulated bank. */
declare(strict_types=1);
require __DIR__.'/banco_brasil_api_mysql.php';
require_once __DIR__.'/support/BbHttpNativeRelease.php';
$bb440Release=bbHttpNativeRelease();
$bb440Installed=(string)$db->query("SELECT installed_version FROM {$p}modules WHERE module_name='banco_brasil'")->row()->installed_version;
essentialCheck($bb440Installed===$bb440Release['version'],'fixture began with actual native current-header installation');
$bb440Future=implode('.',str_split((string)($bb440Release['migration']+1)));
$bb439PatchCount=0;$bb439PatchMode='normal';$bb439AfterOauth=null;$bb439Mismatch=[];
$bb439Transport=static function(string $method,string $url,array $headers,string $body)use(&$bb439PatchCount,&$bb439PatchMode,&$bb439AfterOauth,&$bb439Mismatch,&$titles,$transport,$db):array{
    essentialCheck(!$db->pdo->inTransaction(),'119 bank transport is outside all SQL locks');
    if(str_contains($url,'oauth/token')&&$bb439AfterOauth){$hook=$bb439AfterOauth;$bb439AfterOauth=null;$hook();}
    if($method==='PATCH'){
        ++$bb439PatchCount;$wire=\BancoBrasil\ApiClient::decodeExact($body);preg_match('~/boletos/([0-9]{20})~',$url,$match);$number=$match[1];
        if($bb439PatchMode==='rejected')return ['status'=>403,'body'=>'{"sensitive":"synthetic sensitive provider diagnostic"}'];
        if(($wire['indicadorIncluirAbatimento']??'N')==='S')$titles[$number]['operational_abatement']=$wire['abatimento']['valorAbatimento'];
        elseif(($wire['indicadorAlterarAbatimento']??'N')==='S')$titles[$number]['operational_abatement']=$wire['alteracaoAbatimento']['novoValorAbatimento'];
        elseif(($wire['indicadorAlterarEnderecoPagador']??'N')==='S'){$a=$wire['alteracaoEndereco'];$titles[$number]['operational_address']=['textoEnderecoSacadoCobranca'=>$a['enderecoPagador'],'nomeBairroSacadoCobranca'=>$a['bairroPagador'],'nomeMunicipioSacadoCobranca'=>$a['cidadePagador'],'siglaUnidadeFederacaoSacadoCobranca'=>$a['UFPagador'],'numeroCepSacadoCobranca'=>$a['CEPPagador']];}
        elseif(($wire['indicadorAlterarDesconto']??'N')==='S'){$d=$wire['alteracaoDesconto'];$titles[$number]['desconto']=['tipo'=>$d['tipoPrimeiroDesconto'],'dataExpiracao'=>$d['novaDataLimitePrimeiroDesconto'],($d['tipoPrimeiroDesconto']==='1'?'valor':'porcentagem')=>$d[$d['tipoPrimeiroDesconto']==='1'?'novoValorPrimeiroDesconto':'novoPercentualPrimeiroDesconto']];}
        else throw new RuntimeException('unrecognized synthetic PATCH');
        if($bb439PatchMode==='timeout')throw new RuntimeException('synthetic sensitive PATCH timeout');
        return ['status'=>200,'body'=>json_encode(['numeroContratoCobranca'=>1234,'dataAtualizacao'=>gmdate('d.m.Y'),'horarioAtualizacao'=>'14:21:05'],JSON_THROW_ON_ERROR)];
    }
    $response=$transport($method,$url,$headers,$body);
    if($method==='GET'&&preg_match('~/boletos/([0-9]{20})~',$url,$match)){
        $title=$titles[$match[1]];$detail=json_decode($response['body'],true,32,JSON_THROW_ON_ERROR);$payer=$title['pagador'];
        $detail+=['valorAbatimentoTituloCobranca'=>$title['operational_abatement']??'0.00'];
        $detail+=($title['operational_address']??['textoEnderecoSacadoCobranca'=>$payer['endereco'],'nomeBairroSacadoCobranca'=>$payer['bairro'],'nomeMunicipioSacadoCobranca'=>$payer['cidade'],'siglaUnidadeFederacaoSacadoCobranca'=>$payer['uf'],'numeroCepSacadoCobranca'=>(string)$payer['cep']]);
        if(in_array($title['desconto']['tipo']??null,['1','2'],true)){$offer=$title['desconto'];$detail+=['codigoDescontoTitulo'=>$offer['tipo'],'valorDescontoTitulo'=>$offer['valor']??'0','percentualDescontoTitulo'=>$offer['porcentagem']??'0','dataDescontoTitulo'=>$offer['dataExpiracao'],'codigoSegundoDescontoTitulo'=>'0','valorSegundoDescontoTitulo'=>'0','percentualSegundoDescontoTitulo'=>'0','codigoTerceiroDescontoTitulo'=>'0','valorTerceiroDescontoTitulo'=>'0','percentualTerceiroDescontoTitulo'=>'0'];}
        $detail=array_replace($detail,$bb439Mismatch);$response['body']=json_encode($detail,JSON_THROW_ON_ERROR);
    }
    return $response;
};
$bb439Api=new \BancoBrasil\ApiService($db,$p,$encrypt,$decrypt,$bb439Transport);
$bb439Key=static fn(string $value):string=>hash('sha256','reconstructed119:'.$value);
$bb439Invoice=$s->create($a,7,$apiInput,$bb439Key('invoice'));
$bb439Snapshot=$s->document($a,7,$bb439Invoice)->data();
$bb439Origin=$bb439Api->issue($a,7,$profile,$bb439Invoice,$address,$bb439Key('issue'));
$bb439Abat=['action'=>'include','amount'=>'10.00','contract_confirmed'=>'1','reference'=>'SYNTHETIC INDEPENDENT CONTRACT','reason'=>'Synthetic operational abatement'];
essentialThrows(fn()=>$bb439Api->amendAbatement($a,7,$bb439Origin,$bb439Abat,$bb439Key('before-query')),'bb_api_abatement_requires_fresh_normal_query','119 requires fresh account-proven NORMAL observation before PATCH');
$bb439Api->query($a,7,$bb439Origin,$bb439Key('normal'));
$bb439Query=$db->query("SELECT * FROM {$p}bb_api_operations WHERE origin_id=? AND operation='query' ORDER BY id DESC LIMIT 1",[$bb439Origin])->row_array();
$db->query("UPDATE {$p}bb_api_operations SET updated_at=? WHERE id=?",[gmdate('Y-m-d H:i:s',time()-301),$bb439Query['id']]);
essentialThrows(fn()=>$bb439Api->amendAbatement($a,7,$bb439Origin,$bb439Abat,$bb439Key('stale-query')),'bb_api_abatement_requires_fresh_normal_query','119 stale NORMAL query never authorizes mutation');
$bb439Api->query($a,7,$bb439Origin,$bb439Key('fresh'));
essentialThrows(fn()=>$bb439Api->amendAbatement($b,7,$bb439Origin,$bb439Abat,$bb439Key('cross-company')),'bb_api_origin_unavailable','119 rejects cross-company title before PATCH');
$db->query("INSERT INTO {$p}staff(staffid,active,admin) VALUES(39,1,0)");$db->query("INSERT INTO {$p}issuers_staff(issuer_id,staff_id,can_view) VALUES(?,39,1)",[$a]);
foreach([['banco_brasil','view'],['banco_brasil','api_issue'],['invoices','view']] as [$feature,$cap])$db->query("INSERT INTO {$p}issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,39,?,?)",[$a,$feature,$cap]);
essentialThrows(fn()=>$bb439Api->amendAbatement($a,39,$bb439Origin,$bb439Abat,$bb439Key('no-specific-grant')),'bb_api_access_denied','119 does not infer new amendment capability from issuance grant');
$bb439Operation=$bb439Api->amendAbatement($a,7,$bb439Origin,$bb439Abat,$bb439Key('abatement'));
essentialCheck($db->query("SELECT status FROM {$p}bb_api_operations WHERE id=?",[$bb439Operation])->row()->status==='validated','119 PATCH ACK is merely validated request');
$bb439Before=$bb439PatchCount;essentialCheck($bb439Api->amendAbatement($a,7,$bb439Origin,$bb439Abat,$bb439Key('abatement'))===$bb439Operation&&$bb439PatchCount===$bb439Before,'119 exact retry never resends PATCH');
essentialThrows(fn()=>$bb439Api->amendAbatement($a,7,$bb439Origin,array_replace($bb439Abat,['reason'=>'changed']),$bb439Key('abatement')),'bb_api_request_conflict','119 conflicting retry never changes original intention');
essentialThrows(fn()=>$bb439Api->amendAbatement($a,7,$bb439Origin,$bb439Abat,$bb439Key('pending-new-key')),'bb_api_abatement_requires_query','119 second key cannot duplicate pending operational request');
$bb439Api->query($a,7,$bb439Origin,$bb439Key('confirm-abatement'));
essentialCheck($db->query("SELECT status FROM {$p}bb_api_operations WHERE id=?",[$bb439Operation])->row()->status==='confirmed','119 exact account query confirms abatement independently');
essentialCheck($s->document($a,7,$bb439Invoice)->data()===$bb439Snapshot&&$s->payments($a,7,$bb439Invoice)===[],'119 abatement offer preserves native document, money and payments');
$bb439Alter=array_replace($bb439Abat,['action'=>'alter','amount'=>'11.00']);$bb439PatchMode='timeout';
essentialThrows(fn()=>$bb439Api->amendAbatement($a,7,$bb439Origin,$bb439Alter,$bb439Key('timeout')),'bb_api_result_unconfirmed_query_origin','119 lost PATCH reply remains uncertain');
$bb439Before=$bb439PatchCount;$bb439Api->amendAbatement($a,7,$bb439Origin,$bb439Alter,$bb439Key('timeout'));essentialCheck($bb439PatchCount===$bb439Before,'119 uncertain retry never submits a second PATCH');$bb439PatchMode='normal';
$bb439Api->query($a,7,$bb439Origin,$bb439Key('reconcile-timeout'));
essentialCheck($db->query("SELECT status FROM {$p}bb_api_operations WHERE origin_id=? AND operation='amend_abat' ORDER BY id DESC LIMIT 1",[$bb439Origin])->row()->status==='confirmed','119 query reconciles remote mutation whose reply was lost');

$bb439Address=['address'=>'Rua Nova Sintetica 20','district'=>'Novo Centro','city'=>'Salvador','state'=>'BA','zip'=>'04000001','contract_confirmed'=>'1','reference'=>'SYNTHETIC ADDRESS CONTRACT','reason'=>'Synthetic operational address'];
$bb439AddressOp=$bb439Api->amendAddress($a,7,$bb439Origin,$bb439Address,$bb439Key('address'));
essentialCheck($db->query("SELECT status FROM {$p}bb_api_operations WHERE id=?",[$bb439AddressOp])->row()->status==='validated','119 address ACK does not update fiscal identity or confirm bank observation');
$bb439Api->query($a,7,$bb439Origin,$bb439Key('confirm-address'));
essentialCheck($db->query("SELECT status FROM {$p}bb_api_operations WHERE id=?",[$bb439AddressOp])->row()->status==='confirmed','119 full account-bound address observation confirms request');
essentialCheck($s->document($a,7,$bb439Invoice)->data()===$bb439Snapshot,'119 operational address never rewrites issued fiscal snapshot');
// Recover interrupted sending with its own claim. Expiry permits read reconciliation only.
$bb439NextAddress=array_replace($bb439Address,['address'=>'Rua Interrompida 30']);$bb439Interrupted=$bb439Api->amendAddress($a,7,$bb439Origin,$bb439NextAddress,$bb439Key('sending-address'));
$db->query("UPDATE {$p}bb_api_operations SET status='sending',locked_until=? WHERE id=?",[gmdate('Y-m-d H:i:s',time()-10),$bb439Interrupted]);
$bb439Before=$bb439PatchCount;$bb439Api->amendAddress($a,7,$bb439Origin,$bb439NextAddress,$bb439Key('sending-address'));essentialCheck($bb439PatchCount===$bb439Before,'119 interrupted sending never repeats PATCH');
$bb439Api->query($a,7,$bb439Origin,$bb439Key('reconcile-sending-address'));
essentialCheck($db->query("SELECT status FROM {$p}bb_api_operations WHERE id=?",[$bb439Interrupted])->row()->status==='confirmed'&&$bb439PatchCount===$bb439Before,'119 query confirms expired sending claim without remote write');

$bb439Declaration=$profileInput+['discount_policy'=>'fixed_until','discount_policy_confirmed'=>'1'];$bb439DiscountProfile=$bb439Api->declareProfile($a,7,$connection,$bb439Declaration);$bb439Api->saveCredentials($a,7,$bb439DiscountProfile,$secret);
$bb439DiscountUntil=(new DateTimeImmutable($apiInput['date']))->modify('+10 days')->format('Y-m-d');$bb439DiscountTitle=$address+['fixed_discount'=>'10.00','discount_until'=>$bb439DiscountUntil];
$bb439DiscountInvoice=$s->create($a,7,$apiInput,$bb439Key('discount-invoice'));$bb439DiscountSnapshot=$s->document($a,7,$bb439DiscountInvoice)->data();
$bb439DiscountOrigin=$bb439Api->issue($a,7,$bb439DiscountProfile,$bb439DiscountInvoice,$bb439DiscountTitle,$bb439Key('discount-issue'));$bb439Api->query($a,7,$bb439DiscountOrigin,$bb439Key('discount-normal'));
$bb439Discount=['type'=>'1','value'=>'12.00','until'=>$bb439DiscountUntil,'contract_confirmed'=>'1','reference'=>'SYNTHETIC DISCOUNT CONTRACT','reason'=>'Synthetic discount adjustment'];
essentialThrows(fn()=>$bb439Api->amendDiscount($a,7,$bb439Origin,$bb439Discount,$bb439Key('wrong-profile')),'bb_api_discount_amend_profile_denied','119 no-discount profile cannot acquire discount through amendment');
$bb439DiscountOp=$bb439Api->amendDiscount($a,7,$bb439DiscountOrigin,$bb439Discount,$bb439Key('discount-patch'));
essentialCheck($db->query("SELECT status FROM {$p}bb_api_operations WHERE id=?",[$bb439DiscountOp])->row()->status==='validated','119 discount PATCH is request until independent query');
$bb439Api->query($a,7,$bb439DiscountOrigin,$bb439Key('discount-confirm'));
essentialCheck($db->query("SELECT status FROM {$p}bb_api_operations WHERE id=?",[$bb439DiscountOp])->row()->status==='confirmed','119 new first offer confirmed by exact amount/date/tier and account list');
$bb439Before=$bb439PatchCount;$bb439Api->amendDiscount($a,7,$bb439DiscountOrigin,$bb439Discount,$bb439Key('discount-patch'));essentialCheck($bb439PatchCount===$bb439Before,'119 confirmed discount retry never resends');
essentialCheck($s->document($a,7,$bb439DiscountInvoice)->data()===$bb439DiscountSnapshot&&$s->position($a,7,$bb439DiscountInvoice)['receivable']==='105.53','119 offer instruction never extinguishes debt or rewrites financial snapshot');
$bb439Mismatch=['valorDescontoTitulo'=>'12.01'];essentialThrows(fn()=>$bb439Api->query($a,7,$bb439DiscountOrigin,$bb439Key('discount-divergence')),'bb_api_result_unconfirmed_query_origin','119 divergent bank offer after confirmation cannot silently become truth');$bb439Mismatch=[];

// Old and future module markers do not authorize this exact runtime.
foreach(['1.1.9',$bb440Future] as $badVersion){$db->query("UPDATE {$p}modules SET installed_version=? WHERE module_name='banco_brasil'",[$badVersion]);$bb439Before=$bb439PatchCount;essentialThrows(fn()=>$bb439Api->query($a,7,$bb439Origin,$bb439Key('version-'.$badVersion)),'bb_api_access_denied','exact current runtime denies '.$badVersion.' tracking');essentialCheck($bb439PatchCount===$bb439Before,'denied tracking performs no PATCH');}
// Undo only the deliberate version-corruption fixture; original installation was proved above.
$db->query("UPDATE {$p}modules SET installed_version=? WHERE module_name='banco_brasil'",[$bb440Installed]);
essentialCheck((string)$db->query("SELECT installed_version FROM {$p}modules WHERE module_name='banco_brasil'")->row()->installed_version===$bb440Release['version'],'original natively installed marker restored after negative probes');
// OAuth revocation is rechecked before the sending marker, preserving the intent for explicit retry.
$bb439Api->query($a,7,$bb439Origin,$bb439Key('before-revocation'));
$bb439RevokeAddress=array_replace($bb439Address,['address'=>'Rua Autorizada 40']);
$bb439AfterOauth=fn()=>$db->query("UPDATE {$p}staff SET active=0 WHERE staffid=7");$bb439Before=$bb439PatchCount;
essentialThrows(fn()=>$bb439Api->amendAddress($a,7,$bb439Origin,$bb439RevokeAddress,$bb439Key('revoked-oauth')),'bb_api_authentication_not_submitted','119 actor revoked during OAuth cannot submit address PATCH');
essentialCheck($bb439PatchCount===$bb439Before,'119 revocation before sending performs no PATCH');$db->query("UPDATE {$p}staff SET active=1 WHERE staffid=7");
$bb439Resume=$bb439Api->amendAddress($a,7,$bb439Origin,$bb439RevokeAddress,$bb439Key('revoked-oauth'));
essentialCheck($bb439PatchCount===$bb439Before+1,'119 restored authorization resumes pre-send reservation exactly once');
$bb439Api->query($a,7,$bb439Origin,$bb439Key('confirm-resumed-address'));
essentialCheck($db->query("SELECT status FROM {$p}bb_api_operations WHERE id=?",[$bb439Resume])->row()->status==='confirmed','119 resumed intent requires independent query confirmation');
// Retire only a read lease; a stale worker cannot publish its old result afterward.
$bb439Read=$db->query("SELECT * FROM {$p}bb_api_operations WHERE origin_id=? AND operation='query' ORDER BY id DESC LIMIT 1",[$bb439Origin])->row_array();
$db->query("UPDATE {$p}bb_api_operations SET status='sending',locked_until=? WHERE id=?",[gmdate('Y-m-d H:i:s',time()-10),$bb439Read['id']]);$bb439Before=$bb439PatchCount;
$bb439Api->query($a,7,$bb439Origin,$bb439Key('retire-read-lease'));
$bb439Retired=$db->query("SELECT status,failure_category,claim_token FROM {$p}bb_api_operations WHERE id=?",[$bb439Read['id']])->row_array();
essentialCheck($bb439Retired['status']==='unknown'&&$bb439Retired['failure_category']==='read_lease_expired'&&$bb439Retired['claim_token']===$bb439Read['claim_token']&&$bb439PatchCount===$bb439Before,'119 new authorized query retires only expired read claim without losing its nonce or sending mutation');
$bb439Complete=new ReflectionMethod(\BancoBrasil\ApiService::class,'completeOperation');
essentialThrows(fn()=>$bb439Complete->invoke($bb439Api,$a,7,$bb439Origin,(int)$bb439Read['id'],$bb439Read['claim_token'],'query',200,$bb439Read['response_hash'],json_decode($bb439Read['result_json'],true,32,JSON_THROW_ON_ERROR)),'bb_api_claim_superseded','119 response from retired read worker cannot overwrite current state');
// A second company owns a separate account/agreement/profile/origin and can use the positive flow.
[$bb439ConnectionB]=bbSyntheticConnection($b,'7654321','001:00452-3:000000223873-X','52998224725','Invoice B');
$bb439ProfileB=$bb439Api->declareProfile($b,7,$bb439ConnectionB,$profileInput);$bb439Api->saveCredentials($b,7,$bb439ProfileB,$secret);
$bb439ClientB=$customers->create($b,7,['company'=>'Synthetic B payer','vat'=>'52998224725']);$bb439InputB=array_replace($apiInput,['client_id'=>$bb439ClientB]);
$bb439InvoiceB=$s->create($b,7,$bb439InputB,$bb439Key('B-invoice'));$bb439SnapshotB=$s->document($b,7,$bb439InvoiceB)->data();$providerAccount=223873;
$bb439OriginB=$bb439Api->issue($b,7,$bb439ProfileB,$bb439InvoiceB,$address,$bb439Key('B-issue'));$bb439Api->query($b,7,$bb439OriginB,$bb439Key('B-normal'));
$bb439OpB=$bb439Api->amendAbatement($b,7,$bb439OriginB,$bb439Abat,$bb439Key('B-abatement'));$bb439Api->query($b,7,$bb439OriginB,$bb439Key('B-confirm'));
essentialCheck($db->query("SELECT status FROM {$p}bb_api_operations WHERE id=?",[$bb439OpB])->row()->status==='confirmed'&&$s->document($b,7,$bb439InvoiceB)->data()===$bb439SnapshotB,'119 B own-account positive abatement preserves issued document');
essentialThrows(fn()=>$bb439Api->amendAddress($a,7,$bb439OriginB,$bb439Address,$bb439Key('A-cross-B')),'bb_api_origin_unavailable','119 A cannot mutate B account origin');$providerAccount=123873;
essentialCheck($s->document($a,7,$bb439Invoice)->data()===$bb439Snapshot,'119 B operations preserve A complete snapshot');
essentialCheck($db->query("SELECT COUNT(*) n FROM {$p}invoicepaymentrecords WHERE invoiceid IN (?,?)",[$bb439Invoice,$bb439DiscountInvoice])->row()->n==='0'||(int)$db->query("SELECT COUNT(*) n FROM {$p}invoicepaymentrecords WHERE invoiceid IN (?,?)",[$bb439Invoice,$bb439DiscountInvoice])->row()->n===0,'119 all amendment paths preserve zero financial receipts');
echo 'BB119 reconstructed native upgrade/amendment/recovery/ACL integration: '.$GLOBALS['essentialChecks'].' cumulative checks. Real DB, injected transport; no external bank homologation.'.PHP_EOL;
