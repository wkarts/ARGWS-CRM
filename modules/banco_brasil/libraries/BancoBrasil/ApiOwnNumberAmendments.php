<?php
declare(strict_types=1);
namespace BancoBrasil;
require_once __DIR__.'/ApiOwnNumber.php';
trait ApiOwnNumberAmendments
{
    public function amendOwnNumber($issuer,$actor,$origin,array$input,string$key):int{return$this->perform(\IssuerPolicy::id($issuer),\IssuerPolicy::id($actor),\IssuerPolicy::id($origin),'amend_ref',$key,ApiOwnNumber::input($input));}
    /** Derive aliases exclusively from canonical outbox rows, in order; no mutable alias is written into the origin. */
    private function referenceHistory(array$origin,array$snapshot,int$ignoreId=0):array
    {
        $current=ApiOwnNumber::reference('CRM'.$origin['invoice_id']);$aliases=[$current];$attempts=[];$last=null;
        foreach($this->rows("SELECT * FROM {$this->prefix}bb_api_operations WHERE origin_id=? AND operation='amend_ref' ORDER BY id",[$origin['id']])as$row){
            if((int)$row['id']===$ignoreId)continue;
            $data=json_decode($row['result_json'],true,32,JSON_THROW_ON_ERROR);$intent=ApiOwnNumber::input($data['reference_instruction']??[]);
            if(($data['reference_contract']??null)!==ApiOwnNumber::CONTRACT||!hash_equals(ApiOwnNumber::fingerprint($origin,$snapshot,$intent),$data['reference_fingerprint']??''))throw new \DomainException('bb_api_reference_corrupt');
            $attempts[]=['id'=>(int)$row['id'],'status'=>$row['status'],'actor_id'=>(int)$row['actor_id'],'created_at'=>$row['created_at'],'previous_reference'=>$intent['previous_reference'],'new_reference'=>$intent['new_reference'],'reference'=>$intent['reference'],'reason'=>$intent['reason']];
            if($row['status']==='confirmed'){if($intent['previous_reference']!==$current||in_array($intent['new_reference'],$aliases,true))throw new \DomainException('bb_api_reference_corrupt');$current=$intent['new_reference'];$aliases[]=$current;}
            if(!in_array($row['status'],['rejected','auth_failed'],true))$last=$row+['intent'=>$intent];
        }
        return compact('current','aliases','last','attempts');
    }
    private function prepareOwnNumber(int$issuer,int$actor,int$originId,string$operation,?array$intent,array$snapshot,array$origin,int$ignoreId=0):void
    {
        $history=$this->referenceHistory($origin,$snapshot,$ignoreId);$last=$history['last'];if(!in_array($operation,['query','movements'],true)&&$last&&$last['status']!=='confirmed')throw new \DomainException('bb_api_reference_requires_query');if($operation!=='amend_ref')return;
        if(!in_array($origin['state'],['api_registered','api_query_observed'],true))throw new \DomainException('bb_api_reference_requires_fresh_normal_query');$query=$this->rows("SELECT * FROM {$this->prefix}bb_api_operations WHERE origin_id=? AND operation='query' ORDER BY id DESC LIMIT 1 FOR UPDATE",[$originId])[0]??null;$data=$query?json_decode($query['result_json'],true,32,JSON_THROW_ON_ERROR):[];
        if(!$query||$query['status']!=='validated'||(int)$query['http_status']!==200||!preg_match('/^[a-f0-9]{64}$/D',$query['response_hash'])||$query['updated_at']<gmdate('Y-m-d H:i:s',time()-300)||$query['updated_at']>gmdate('Y-m-d H:i:s')||($data['codigoEstadoTituloCobranca']??null)!=='1'||($data['account_scope_confirmed']??null)!=='1'||!preg_match('/^[a-f0-9]{64}$/D',$data['account_evidence_hash']??''))throw new \DomainException('bb_api_reference_requires_fresh_normal_query');
        if(($data['bank_own_number']??null)!==$history['current']||$intent['previous_reference']!==$history['current'])throw new \DomainException('bb_api_reference_stale');if(in_array($intent['new_reference'],$history['aliases'],true))throw new \DomainException('bb_api_reference_alias_reused');
        foreach($this->rows("SELECT result_json FROM {$this->prefix}bb_api_operations WHERE origin_id=? AND operation='query' AND status='validated' FOR UPDATE",[$originId])as$past){$old=json_decode($past['result_json'],true,32,JSON_THROW_ON_ERROR);if(($old['codigoEstadoTituloCobranca']??null)!=='1'||(isset($old['valorPagamentoParcialTitulo'])&&Fields::cents($old['valorPagamentoParcialTitulo'])!==0))throw new \DomainException('bb_api_reference_adverse_history');}
        foreach($this->rows("SELECT projection_json FROM {$this->prefix}bb_api_notifications WHERE origin_id=? FOR UPDATE",[$originId])as$notification)if((json_decode($notification['projection_json'],true,32,JSON_THROW_ON_ERROR)['codigoEstadoBaixaOperacional']??null)==='10')throw new \DomainException('bb_api_reference_adverse_history');ApiMovements::assertNoAdverseHistory($this->db,$this->prefix,$origin,true);
    }
    /** Bind every query/reference I/O to its current owned financial and bank observation context. */
    private function referenceActivityHash(int$issuer,int$actor,array$origin,array$stored,array$profile,int$id):string
    {
        $position=(new \CompanyInvoiceBalance($this->db,$this->prefix))->inOwned($issuer,$actor,(int)$origin['invoice_id']);
        $invoice=$this->rows("SELECT id,issuer_id,clientid,total,status,currency,date,duedate FROM {$this->prefix}invoices WHERE id=? AND issuer_id=? FOR UPDATE",[$origin['invoice_id'],$issuer]);
        $document=$this->rows("SELECT * FROM {$this->prefix}issuers_invoice_documents WHERE invoice_id=? AND issuer_id=? FOR UPDATE",[$origin['invoice_id'],$issuer]);
        $payments=$this->rows("SELECT * FROM {$this->prefix}invoicepaymentrecords WHERE invoiceid=? ORDER BY id FOR UPDATE",[$origin['invoice_id']]);
        $credits=$this->rows("SELECT * FROM {$this->prefix}credits WHERE invoice_id=? ORDER BY id FOR UPDATE",[$origin['invoice_id']]);
        $events=$this->rows("SELECT * FROM {$this->prefix}bb_api_notifications WHERE origin_id=? ORDER BY id FOR UPDATE",[$origin['id']]);
        $operations=$this->rows("SELECT * FROM {$this->prefix}bb_api_operations WHERE origin_id=? AND id<>? ORDER BY id FOR UPDATE",[$origin['id'],$id]);
        return hash('sha256',json_encode([$origin,$stored,$profile,$position,$invoice,$document,$payments,$credits,$events,$operations],JSON_THROW_ON_ERROR));
    }
    /** Caller owns the transaction; no HTTP is performed while these locks are held. */
    private function assertReferenceActivityInOwned(int$issuer,int$actor,int$originId,string$operation,array$prepared,string$status):void
    {
        if(!isset($prepared['reference_activity']))return;
        $this->authorizeOperation($issuer,$actor,$operation);
        $op=$this->rows("SELECT * FROM {$this->prefix}bb_api_operations WHERE id=? AND origin_id=? FOR UPDATE",[$prepared['id'],$originId])[0]??[];
        if(($op['claim_token']??null)!==$prepared['claim']||($op['status']??null)!==$status||(int)($op['actor_id']??0)!==$actor)throw new \DomainException('bb_api_claim_superseded');
        [$origin,$stored,$snapshot,$profile]=$this->loadOrigin($issuer,$originId);
        $credential=$this->rows("SELECT * FROM {$this->prefix}bb_api_credentials WHERE profile_id=? AND issuer_id=? ORDER BY id DESC LIMIT 1 FOR UPDATE",[$profile['id'],$issuer])[0]??[];
        if($credential!==$prepared['credential'])throw new \DomainException('bb_api_credentials_rotated');
        if($operation==='amend_ref'){
            $saved=json_decode($op['result_json'],true,32,JSON_THROW_ON_ERROR);
            if(($saved['reference_instruction']??null)!==$prepared['amendment']||($saved['reference_contract']??null)!==ApiOwnNumber::CONTRACT||!hash_equals(ApiOwnNumber::fingerprint($origin,$snapshot,$prepared['amendment']),$saved['reference_fingerprint']??''))throw new \DomainException('bb_api_reference_corrupt');
            $this->assertUnmovedFinancialPosition($issuer,$actor,$origin,$operation);
            $this->prepareOwnNumber($issuer,$actor,$originId,$operation,$prepared['amendment'],$snapshot,$origin,(int)$prepared['id']);
        }
        if(!hash_equals($prepared['reference_activity'],$this->referenceActivityHash($issuer,$actor,$origin,$stored,$profile,(int)$prepared['id'])))throw new \DomainException('bb_api_reference_activity_changed');
        // CI/DB adapters can invoke hooks while hydrating the position. Close that boundary too.
        $this->authorizeOperation($issuer,$actor,$operation);
    }
    private function queryOwnNumberSnapshot(array$origin,array$detail,array$snapshot):array
    {
        $history=$this->referenceHistory($origin,$snapshot);$last=$history['last'];$observed=ApiOwnNumber::reference($detail['numeroTituloCedenteCobranca']??null);$current=$history['current'];if($last&&$last['status']==='confirmed')$snapshot['reference_id']=(int)$last['id'];
        if($last&&$last['status']!=='confirmed'){
            if(!in_array($last['status'],['validated','unknown','sending'],true)||$last['intent']['previous_reference']!==$current)throw new \DomainException('bb_api_reference_corrupt');
            if($observed===$last['intent']['new_reference']){$snapshot['reference_id']=(int)$last['id'];if($last['status']==='sending')$snapshot['reference_claim_hash']=hash('sha256',$last['claim_token']);$current=$observed;}
        }
        if($observed!==$current)throw new \DomainException('bb_api_reference_bank_divergence');$snapshot['bank_own_number']=$current;return$snapshot;
    }
    private function confirmOwnNumber(int$issuer,int$originId,array$result):void
    {
        if(!isset($result['reference_id']))return;[$origin,,$snapshot]=$this->loadOrigin($issuer,$originId);$history=$this->referenceHistory($origin,$snapshot);$last=$history['last'];$id=(int)$result['reference_id'];if(!$last||(int)$last['id']!==$id)throw new \DomainException('bb_api_reference_bank_divergence');
        $interrupted=$last['status']==='sending'&&$last['locked_until']<=gmdate('Y-m-d H:i:s')&&preg_match('/^[a-f0-9]{64}$/D',$last['claim_token'])&&hash_equals(hash('sha256',$last['claim_token']),$result['reference_claim_hash']??'');
        if(!in_array($last['status'],['validated','unknown','confirmed'],true)&&!$interrupted)throw new \DomainException('bb_api_reference_bank_divergence');if(($result['bank_own_number']??null)!==$last['intent']['new_reference']||($result['account_scope_confirmed']??null)!=='1'||!preg_match('/^[a-f0-9]{64}$/D',$result['account_evidence_hash']??''))throw new \DomainException('bb_api_reference_bank_divergence');
        $data=json_decode($last['result_json'],true,32,JSON_THROW_ON_ERROR);$data['first_account_evidence_hash']=$data['first_account_evidence_hash']??$result['account_evidence_hash'];$data['bank_state']='reference_confirmed';$data['account_evidence_hash']=$result['account_evidence_hash'];$json=json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);\IssuerStorage::query($this->db,"UPDATE {$this->prefix}bb_api_operations SET status='confirmed',result_json=?,updated_at=? WHERE id=?",[$json,gmdate('Y-m-d H:i:s'),$id]);$saved=$this->rows("SELECT status,result_json FROM {$this->prefix}bb_api_operations WHERE id=?",[$id])[0]??[];if(($saved['status']??null)!=='confirmed'||($saved['result_json']??null)!==$json)throw new \RuntimeException('issuer_bb_api_write_unconfirmed');
    }
}
