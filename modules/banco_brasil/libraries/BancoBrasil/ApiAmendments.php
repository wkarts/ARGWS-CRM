<?php

declare(strict_types=1);
namespace BancoBrasil;

/** Date-only instructions use the existing durable API outbox. Original invoice/origin snapshots are immutable. */
trait ApiAmendments
{
    public function amendDue($issuer,$actor,$origin,array $input,string $key):int
    {
        if(array_diff(array_keys($input),['due_date','reason'])||!is_string($input['due_date']??null)||!is_string($input['reason']??null))throw new \DomainException('bb_api_amendment_invalid');
        Fields::date($input['due_date']);$reason=trim($input['reason']);
        if($reason===''||strlen($reason)>500||preg_match('/[\x00-\x1f\x7f]/',$reason)||preg_match('//u',$reason)!==1)throw new \DomainException('bb_api_amendment_invalid');
        return $this->perform(\IssuerPolicy::id($issuer),\IssuerPolicy::id($actor),\IssuerPolicy::id($origin),'amend_due',$key,['due_date'=>$input['due_date'],'reason'=>$reason]);
    }
    private function authorizeOperation(int $issuer,int $actor,string $operation):void
    {
        if($operation==='amend_due'){ $module=$this->rows("SELECT installed_version FROM {$this->prefix}modules WHERE module_name='banco_brasil' FOR UPDATE")[0]??[];if(($module['installed_version']??null)!=='1.1.4')throw new \DomainException('bb_api_access_denied'); }
        $this->authorize($issuer,$actor,$operation==='amend_due'?'api_amend':($operation==='movements'?'api_query':'api_'.$operation),true);
        if($operation==='amend_due'&&!(new \IssuerResourceAccess($this->db,$this->prefix))->can($issuer,$actor,'invoices','edit'))throw new \DomainException('bb_api_access_denied');
    }
    public static function amendmentPayload(string $agreement,string $due):string
    {
        if(!preg_match('/^[1-9][0-9]{6}$/D',$agreement))throw new \DomainException('bb_api_origin_invalid');Fields::date($due);
        // Every required indicator is explicit. No interest, amount, identity or collection instruction is inferred.
        $flags=['indicadorAlterarAbatimento','indicadorAlterarDataDesconto','indicadorAlterarDesconto','indicadorAlterarEnderecoPagador','indicadorAlterarPrazoBoletoVencido','indicadorAlterarSeuNumero','indicadorAtribuirDesconto','indicadorCancelarProtesto','indicadorCobrarJuros','indicadorCobrarMulta','indicadorDispensarJuros','indicadorDispensarMulta','indicadorIncluirAbatimento','indicadorNegativar','indicadorNovaDataVencimento','indicadorProtestar','indicadorSustacaoProtesto','indicadorNovoValorNominal'];
        $payload=['numeroConvenio'=>(int)$agreement]+array_fill_keys($flags,'N');$payload['indicadorNovaDataVencimento']='S';$payload['alteracaoData']=['novaDataVencimento'=>(new \DateTimeImmutable($due))->format('d.m.Y')];
        return json_encode($payload,JSON_THROW_ON_ERROR);
    }
    private function prepareAmendment(int $issuer,int $actor,int $originId,string $operation,?array $amendment,array $snapshot,array $origin,int $retryId):void
    {
        if(!in_array($operation,['query','cancel','amend_due','movements'],true))return;
        // A stale pre-send lease may be revoked: its worker must still pass the confirmed sending marker.
        \IssuerStorage::query($this->db,"UPDATE {$this->prefix}bb_api_operations SET status='auth_failed',failure_category='authentication_lease_expired' WHERE origin_id=? AND status='authenticating' AND locked_until<=? AND id<>?",[$originId,gmdate('Y-m-d H:i:s'),$retryId]);
        // An expired sending claim remains uncertain; never steal a possibly submitted write.
        if($this->rows("SELECT id FROM {$this->prefix}bb_api_operations WHERE origin_id=? AND status IN ('authenticating','sending') AND id<>? FOR UPDATE",[$originId,$retryId]))throw new \DomainException('bb_api_operation_in_progress');
        $latest=$this->rows("SELECT * FROM {$this->prefix}bb_api_operations WHERE origin_id=? AND operation='amend_due' ORDER BY id DESC LIMIT 1 FOR UPDATE",[$originId])[0]??null;
        if(!in_array($operation,['query','movements'],true)&&$latest&&!in_array($latest['status'],['confirmed','rejected','auth_failed'],true))throw new \DomainException('bb_api_amendment_requires_query');
        if($operation!=='amend_due')return;
        (new \CompanyInvoiceBalance($this->db,$this->prefix))->inOwned($issuer,$actor,(int)$origin['invoice_id']);
        foreach($this->rows("SELECT projection_json FROM {$this->prefix}bb_api_notifications WHERE origin_id=? FOR UPDATE",[$originId]) as $notification){$event=json_decode($notification['projection_json'],true,32,JSON_THROW_ON_ERROR);if(($event['codigoEstadoBaixaOperacional']??null)==='10')throw new \DomainException('bb_api_amendment_adverse_history');}
        if(!in_array($origin['state'],['api_registered','api_query_observed'],true))throw new \DomainException('bb_api_amendment_requires_fresh_normal_query');
        $query=$this->rows("SELECT result_json,updated_at FROM {$this->prefix}bb_api_operations WHERE origin_id=? AND operation='query' AND status='validated' ORDER BY id DESC LIMIT 1 FOR UPDATE",[$originId])[0]??null;
        $result=$query?json_decode($query['result_json'],true,32,JSON_THROW_ON_ERROR):[];
        if(!$query||$query['updated_at']<gmdate('Y-m-d H:i:s',time()-300)||($result['codigoEstadoTituloCobranca']??null)!=='1'||($result['account_scope_confirmed']??null)!=='1')throw new \DomainException('bb_api_amendment_requires_fresh_normal_query');
        if($this->rows("SELECT origin_id FROM {$this->prefix}bb_api_receipts WHERE origin_id=? FOR UPDATE",[$originId]))throw new \DomainException('bb_api_amendment_financial_history');
        foreach([['invoicepaymentrecords','invoiceid'],['credits','invoice_id']] as [$table,$field])if($this->rows("SELECT amount FROM {$this->prefix}{$table} WHERE {$field}=? FOR UPDATE",[$origin['invoice_id']]))throw new \DomainException('bb_api_amendment_financial_history');
        $today=(new \DateTimeImmutable('now',new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');$due=$amendment['due_date'];
        $old=$result['bank_due_date']??$snapshot['invoice']['duedate'];
        if($due===$old||$due<$today||$due<$snapshot['invoice']['date']||(new \DateTimeImmutable($snapshot['invoice']['date']))->diff(new \DateTimeImmutable($due))->days>2500)throw new \DomainException('bb_api_amendment_date_invalid');
    }
    private static function amendmentResult(array $data,array $snapshot,array $amendment):array
    {
        $contract=$data['numeroContratoCobranca']??null;$date=$data['dataAtualizacao']??null;$time=$data['horarioAtualizacao']??null;
        if(!is_string($contract)||$contract!==($snapshot['accepted_contract']??null)||!is_string($date)||!preg_match('/^([0-9]{2})[.\/]([0-9]{2})[.\/]([0-9]{4})$/D',$date,$m)||!is_string($time)||!preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$/D',$time))throw new \DomainException('bb_api_amendment_response_invalid');
        Fields::date($m[3].'-'.$m[2].'-'.$m[1]);
        return ['numeroContratoCobranca'=>$contract,'dataAtualizacao'=>$date,'horarioAtualizacao'=>$time,'bank_state'=>'amendment_requested','amendment'=>$amendment];
    }
    private function queryAmendmentSnapshot(int $origin,array $detail,array $snapshot):array
    {
        $last=$this->rows("SELECT id,status,result_json FROM {$this->prefix}bb_api_operations WHERE origin_id=? AND operation='amend_due' AND status NOT IN ('rejected','auth_failed') ORDER BY id DESC LIMIT 1",[$origin])[0]??null;
        if(!$last)return $snapshot;
        $request=json_decode($last['result_json'],true,32,JSON_THROW_ON_ERROR)['amendment']??null;
        if(!is_array($request)||!is_string($request['due_date']??null))throw new \DomainException('bb_api_amendment_corrupt');Fields::date($request['due_date']);
        $expected=(new \DateTimeImmutable($request['due_date']))->format('d.m.Y');
        if(($detail['dataVencimentoTituloCobranca']??null)===$expected){$snapshot['invoice']['duedate']=$request['due_date'];$snapshot['amendment_id']=(int)$last['id'];}
        elseif($last['status']==='confirmed')throw new \DomainException('bb_api_amendment_bank_divergence');
        return $snapshot;
    }
    private function confirmAmendment(int $origin,array $result):void
    {
        if(!isset($result['amendment_id']))return;
        $id=(int)$result['amendment_id'];$last=$this->rows("SELECT id,result_json,status FROM {$this->prefix}bb_api_operations WHERE origin_id=? AND operation='amend_due' AND status NOT IN ('rejected','auth_failed') ORDER BY id DESC LIMIT 1 FOR UPDATE",[$origin])[0]??null;
        if(!$last||(int)$last['id']!==$id||!in_array($last['status'],['validated','unknown','confirmed'],true))throw new \DomainException('bb_api_amendment_bank_divergence');
        $data=json_decode($last['result_json'],true,32,JSON_THROW_ON_ERROR);if(($data['amendment']['due_date']??null)!==$result['bank_due_date'])throw new \DomainException('bb_api_amendment_bank_divergence');
        $data['bank_state']='amendment_confirmed';$data['account_evidence_hash']=$result['account_evidence_hash'];$json=json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        \IssuerStorage::query($this->db,"UPDATE {$this->prefix}bb_api_operations SET status='confirmed',result_json=?,updated_at=? WHERE id=?",[$json,gmdate('Y-m-d H:i:s'),$id]);
        $saved=$this->rows("SELECT status,result_json FROM {$this->prefix}bb_api_operations WHERE id=?",[$id])[0]??[];if(($saved['status']??null)!=='confirmed'||($saved['result_json']??null)!==$json)throw new \RuntimeException('issuer_bb_api_write_unconfirmed');
    }
}
