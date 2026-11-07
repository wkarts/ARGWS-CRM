<?php
declare(strict_types=1);
require_once __DIR__.'/PigeBridgeConnections.php';

/** Public, scoped selector data. Advisory only; transactional writers revalidate every choice. */
final class PigeBridgeSettingsChoices
{
    public function __construct(private $db,private string $p)
    {if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('invalid_prefix');}
    private function rows(string $sql,array $args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    public function forScope(int $caller,array $scope):array
    {
        $scope=PigeBridgeConnections::scope($scope);$issuer=(int)$scope['issuer_id'];$acl=new IssuerResourceAccess($this->db,$this->p);
        if(!$acl->can($issuer,$caller,'settings','edit'))throw new DomainException('pige_configuration_access_denied');
        $out=['actors'=>[],'currencies'=>[],'accounts'=>[],'profiles'=>[]];$master=$acl->isMaster($caller);
        foreach($this->rows('SELECT staffid,firstname,lastname FROM '.$this->p.'staff WHERE active=1 ORDER BY firstname,lastname,staffid') as $staff){
            $id=(int)$staff['staffid'];if(!$master&&$id!==$caller)continue;
            if(!$acl->can($issuer,$id,'invoices','view')||!$acl->can($issuer,$id,'invoices','create')||!$acl->can($issuer,$id,'customers','view'))continue;
            $out['actors'][]=['id'=>$id,'label'=>trim($staff['firstname'].' '.$staff['lastname']).' (#'.$id.')'];
        }
        foreach($this->rows('SELECT id,name FROM '.$this->p.'currencies WHERE name=? ORDER BY id',['BRL']) as $currency)$out['currencies'][]=['id'=>(int)$currency['id'],'label'=>'BRL (#'.$currency['id'].')'];
        if($this->db->table_exists($this->p.'asaas_company_accounts')){
            foreach($this->rows('SELECT id,account_id FROM '.$this->p.'asaas_company_accounts WHERE issuer_id=? AND environment=? AND account_id=? AND enabled=1 AND verified_at IS NOT NULL',[$issuer,$scope['environment'],$scope['account_id']]) as $row)$out['accounts'][]=['id'=>(int)$row['id'],'provider'=>'asaas','label'=>'Asaas — '.$row['account_id'].' (#'.$row['id'].')'];
        }
        if($this->db->table_exists($this->p.'bb_connections')){
            foreach($this->rows('SELECT id,account_id FROM '.$this->p.'bb_connections WHERE issuer_id=? AND environment=? AND account_id=? AND provider=?',[$issuer,$scope['environment'],$scope['account_id'],'banco_brasil']) as $row){
                $out['accounts'][]=['id'=>(int)$row['id'],'provider'=>'banco_brasil','label'=>'Banco do Brasil — '.$row['account_id'].' (#'.$row['id'].')'];
                if($this->db->table_exists($this->p.'bb_api_profiles'))foreach($this->rows('SELECT id,api_stage FROM '.$this->p.'bb_api_profiles WHERE issuer_id=? AND connection_id=? ORDER BY id',[$issuer,$row['id']]) as $profile)$out['profiles'][]=['id'=>(int)$profile['id'],'account_id'=>(int)$row['id'],'label'=>'Perfil #'.$profile['id'].' — '.$profile['api_stage']];
            }
        }
        // An ACL change between reads cannot turn this advisory page into a disclosure.
        if(!$acl->can($issuer,$caller,'settings','edit'))throw new DomainException('pige_configuration_access_denied');
        return $out;
    }
}
