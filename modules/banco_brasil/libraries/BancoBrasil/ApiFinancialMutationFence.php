<?php
declare(strict_types=1);
namespace BancoBrasil;
/** Shared closed policy for first financial applications from an API query, inside the canonical writer transaction. */
final class ApiFinancialMutationFence
{
    private static function rows($db,string $sql,array $args=[]):array{return \IssuerStorage::query($db,$sql,$args)->result_array();}
    /** A fresh financial read cannot erase an unresolved write, including a write started before that read. */
    public static function assertResolvedInOwned($db,string $p,int $origin,array $financialQuery,array $financialResult):void
    {
        \IssuerTransaction::assertOwned($db,$p,['bb_api_operations']);
        $writes=self::rows($db,"SELECT * FROM {$p}bb_api_operations WHERE origin_id=? AND operation NOT IN ('issue','query','movements') ORDER BY id FOR UPDATE",[$origin]);$confirmed=[];$latest=null;
        foreach($writes as $write){
            if(in_array($write['status'],['rejected','auth_failed'],true))continue;
            if($write['operation']!=='amend_due'||$write['status']!=='confirmed')throw new \DomainException('bb_api_credit_mutation_requires_review');
            $data=json_decode($write['result_json'],true,32,JSON_THROW_ON_ERROR);$due=$data['amendment']['due_date']??null;
            if(($data['bank_state']??null)!=='amendment_confirmed'||!is_string($due)||!preg_match('/^[a-f0-9]{64}$/D',$data['account_evidence_hash']??''))throw new \DomainException('bb_api_credit_mutation_requires_review');
            try{Fields::date($due);}catch(\Throwable $error){throw new \DomainException('bb_api_credit_mutation_requires_review');}
            $confirmed[(int)$write['id']]=['due'=>$due,'hash'=>$data['account_evidence_hash'],'proved'=>false];$latest=(int)$write['id'];
        }
        if(!$confirmed)return;
        foreach(self::rows($db,"SELECT id,http_status,response_hash,result_json FROM {$p}bb_api_operations WHERE origin_id=? AND operation='query' AND status='validated' FOR UPDATE",[$origin]) as $query){
            $result=json_decode($query['result_json'],true,32,JSON_THROW_ON_ERROR);$id=(int)($result['amendment_id']??0);if(!isset($confirmed[$id]))continue;$proof=$confirmed[$id];
            if((int)$query['id']>$id&&(int)$query['http_status']===200&&preg_match('/^[a-f0-9]{64}$/D',$query['response_hash'])&&($result['account_scope_confirmed']??null)==='1'&&($result['account_evidence_hash']??null)===$proof['hash']&&($result['bank_due_date']??null)===$proof['due']&&($result['dataVencimentoTituloCobranca']??null)===(new \DateTimeImmutable($proof['due']))->format('d.m.Y'))$confirmed[$id]['proved']=true;
        }
        foreach($confirmed as $proof)if(!$proof['proved'])throw new \DomainException('bb_api_credit_mutation_requires_review');
        if($latest===null||(int)$financialQuery['id']<=$latest||(string)($financialResult['amendment_id']??'')!==(string)$latest||($financialResult['bank_due_date']??null)!==$confirmed[$latest]['due']||($financialResult['account_evidence_hash']??null)!==$confirmed[$latest]['hash'])throw new \DomainException('bb_api_credit_mutation_requires_review');
    }
}
