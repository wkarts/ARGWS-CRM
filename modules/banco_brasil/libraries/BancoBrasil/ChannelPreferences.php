<?php
declare(strict_types=1);
namespace BancoBrasil;
require_once APPPATH.'services/issuers/IssuerTransaction.php';
require_once APPPATH.'services/issuers/IssuerResourceAccess.php';

/** Explicit opt-in for new obligations. Historical origins never consult these switches. */
final class ChannelPreferences
{
    private const NODE='bb_payment_channels';
    public const IDS=['api'=>'banco_brasil_api','cnab'=>'banco_brasil_cnab'];
    public function __construct(private $db,private string $p)
    {if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new \InvalidArgumentException('bb_prefix_invalid');}
    public static function tables():array{return ['issuers_lock','issuers_registry','issuers_installation_identity','issuers_staff','issuers_resource_permissions','issuers_audit','staff'];}
    private function authorize(int $i,int $a,bool $write=false):\IssuerResourceAccess
    {
        if(class_exists('CompanySettingsRuntime',false)&&\CompanySettingsRuntime::active()&&(\CompanySettingsRuntime::issuerId()!==$i||\CompanySettingsRuntime::staffId()!==$a))throw new \DomainException('bb_channel_denied');
        $acl=new \IssuerResourceAccess($this->db,$this->p);
        if(!$acl->can($i,$a,'banco_brasil','view')||($write&&!$acl->can($i,$a,'banco_brasil','manage')))throw new \DomainException('bb_channel_denied');
        return $acl;
    }
    private function source(int $i,int $a):array
    {
        $this->authorize($i,$a);
        $row=\IssuerStorage::query($this->db,'SELECT settings_json FROM '.$this->p.'issuers_registry WHERE id=? AND active=1 FOR UPDATE',[$i])->row_array();
        if(!$row)throw new \DomainException('bb_channel_denied');
        $doc=json_decode($row['settings_json'],false,64,JSON_THROW_ON_ERROR);
        if(!$doc instanceof \stdClass)throw new \DomainException('bb_channel_configuration_invalid');
        $node=$doc->{self::NODE}??null;
        if(property_exists($doc,self::NODE)&&(!$node instanceof \stdClass||(count(get_object_vars($node))!==4||array_diff(array_keys(get_object_vars($node)),['format','revision','api','cnab']))||$node->format!==1||!is_int($node->revision)||$node->revision<1||!is_bool($node->api)||!is_bool($node->cnab)))throw new \DomainException('bb_channel_configuration_invalid');
        return [$doc,$node,['api'=>$node?->api??false,'cnab'=>$node?->cnab??false,'configured'=>$node!==null,'revision'=>hash('sha256',json_encode([$i,$row['settings_json']],JSON_THROW_ON_ERROR))]];
    }
    public function inOwned(int $i,int $a):array
    {
        \IssuerTransaction::assertOwned($this->db,$this->p,self::tables());
        $first=$this->source($i,$a)[2];if($first!==$this->source($i,$a)[2])throw new \DomainException('bb_channel_stale');return $first;
    }
    public function page(int $i,int $a):array
    {return \IssuerTransaction::run($this->db,$this->p,self::tables(),function()use($i,$a){$this->authorize($i,$a)->lockFinancialIdentity($i,$a);return $this->inOwned($i,$a);});}
    public function assertEnabledInOwned(int $i,int $a,string $channel):void
    {if(!isset(self::IDS[$channel])||!$this->inOwned($i,$a)[$channel])throw new \DomainException('bb_channel_disabled');}
    public function setEnabled(int $i,int $a,string $channel,bool $enabled,string $revision):array
    {
        if(!isset(self::IDS[$channel])||!preg_match('/^[a-f0-9]{64}$/D',$revision))throw new \DomainException('bb_channel_invalid');
        return \IssuerTransaction::run($this->db,$this->p,self::tables(),function()use($i,$a,$channel,$enabled,$revision){
            $this->authorize($i,$a,true)->lockFinancialIdentity($i,$a);[$doc,$node,$before]=$this->source($i,$a);
            if(!hash_equals($before['revision'],$revision))throw new \DomainException('bb_channel_stale');
            if($node!==null&&$before[$channel]===$enabled)return $before;
            $next=($node?->revision??0)+1;if($next>2147483647)throw new \DomainException('bb_channel_revision_overflow');
            $values=['api'=>$before['api'],'cnab'=>$before['cnab']];$values[$channel]=$enabled;
            $doc->{self::NODE}=(object)(['format'=>1,'revision'=>$next]+$values);
            $json=json_encode($doc,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
            \IssuerStorage::query($this->db,'UPDATE '.$this->p.'issuers_registry SET settings_json=? WHERE id=? AND active=1',[$json,$i]);
            $metadata=json_encode(['channel'=>$channel,'enabled'=>$enabled,'nonce'=>bin2hex(random_bytes(16))],JSON_THROW_ON_ERROR);
            \IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,metadata_json,created_at) VALUES(?,?,?,NULL,?,?)',[$a,'bb_channel_changed',$i,$metadata,gmdate('Y-m-d H:i:s')]);
            $id=(int)$this->db->insert_id();$audit=\IssuerStorage::query($this->db,'SELECT metadata_json FROM '.$this->p.'issuers_audit WHERE id=? AND actor_id=? AND issuer_id=? AND event=?',[$id,$a,$i,'bb_channel_changed'])->row_array();
            $after=$this->source($i,$a)[2];
            if($id<1||($audit['metadata_json']??null)!==$metadata||$after['api']!==$values['api']||$after['cnab']!==$values['cnab']||$after['revision']===$before['revision'])throw new \RuntimeException('bb_channel_write_unconfirmed');
            $this->authorize($i,$a,true);return $after;
        });
    }
}
