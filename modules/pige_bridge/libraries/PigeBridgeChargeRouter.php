<?php
declare(strict_types=1);
require_once __DIR__.'/PigeBridgeExternalConfiguration.php';
/** Chooses provider from immutable origin (or configured consent for a new issue), never request input. */
final class PigeBridgeChargeRouter
{
    public function __construct(private $db,private $encryption,private string $prefix){if(!preg_match('/^[A-Za-z0-9_]+$/D',$prefix))throw new InvalidArgumentException('invalid_prefix');}
    public function execute(string $action,string $connection,array $event,string $secret):array
    {
        $row=IssuerStorage::query($this->db,'SELECT origin_json,origin_hash FROM '.$this->prefix.'pige_bridge_charge_origins WHERE connection_key=? AND charge_id=?',[$connection,$event['data']['charge_id']])->row_array();
        if($row){if(!hash_equals($row['origin_hash'],hash('sha256',$row['origin_json'])))throw new DomainException('pige_charge_origin_changed');$snapshot=json_decode($row['origin_json'],true,32,JSON_THROW_ON_ERROR);$provider=$snapshot['mapping']['provider']??null;}
        elseif($action==='issue')$provider=(new PigeBridgeExternalConfiguration($this->db,$this->encryption,$this->prefix))->get($connection)['mapping']['provider']??null;
        else throw new OutOfBoundsException('pige_charge_not_found');
        if($provider==='asaas'){require_once __DIR__.'/PigeBridgeCharges.php';$service=new PigeBridgeCharges($this->db,$this->encryption,$this->prefix);}
        elseif($provider==='banco_brasil'){require_once __DIR__.'/PigeBridgeBbCharges.php';$service=new PigeBridgeBbCharges($this->db,$this->encryption,$this->prefix);}
        else throw new DomainException('pige_provider_contract_invalid');
        return $service->execute($action,$connection,$event,$secret);
    }
}
