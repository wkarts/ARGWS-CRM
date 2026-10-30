<?php

declare(strict_types=1);
namespace BancoBrasil;
require_once __DIR__.'/ModuleRelease.php';
require_once __DIR__.'/ApiWebhookSchema.php';
require_once __DIR__.'/ApiClient.php';
require_once __DIR__.'/ApiSchema.php';
require_once __DIR__.'/OriginationSchema.php';
require_once __DIR__.'/MtlsEvidence.php';
require_once __DIR__.'/Scope.php';
require_once APPPATH.'services/issuers/IssuerCore.php';
require_once APPPATH.'services/issuers/IssuerResourceAccess.php';
require_once APPPATH.'services/issuers/IssuerTransaction.php';

/** A transport-authenticated observation is not a bank credit, cancellation or a payment instruction. */
final class ApiWebhook
{
    public function __construct(private $db,private string $prefix){if(!preg_match('/^[A-Za-z0-9_]+$/D',$prefix))throw new \InvalidArgumentException('bb_prefix_invalid');}
    public static function tables():array{return ['issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','staff','modules','bb_api_profiles','bb_connections','bb_api_origins','bb_api_operations','bb_origins','bb_api_webhook_receivers','bb_api_notifications','bb_audit'];}
    private function rows(string $sql,array $args=[]):array{return \IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private function tx(callable $work){ApiSchema::ensure($this->db,$this->prefix,false);OriginationSchema::assertValid($this->db,$this->prefix);ApiWebhookSchema::ensure($this->db,$this->prefix,false);return \IssuerTransaction::run($this->db,$this->prefix,self::tables(),function()use($work){$module=$this->rows("SELECT active,installed_version FROM {$this->prefix}modules WHERE module_name='banco_brasil' FOR UPDATE")[0]??[];if((int)($module['active']??0)!==1||($module['installed_version']??null)!==ModuleRelease::VERSION)throw new \DomainException('bb_webhook_unavailable');return $work();});}
    private function authorize(int $issuer,int $actor):void{$acl=new \IssuerResourceAccess($this->db,$this->prefix);$acl->lockFinancialIdentity($issuer,$actor);if(!$acl->can($issuer,$actor,'banco_brasil','view')||!$acl->can($issuer,$actor,'banco_brasil','manage'))throw new \DomainException('bb_webhook_access_denied');}
    public function configure($issuer,$actor,$profile,array $input):void
    {
        $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);$profile=\IssuerPolicy::id($profile);
        if(array_diff(array_keys($input),['fingerprint','reference','enabled'])||!is_string($input['fingerprint']??null)||!preg_match('/^[a-f0-9]{64}$/D',$input['fingerprint'])||!is_string($input['reference']??null)||trim($input['reference'])===''||strlen($input['reference'])>191||preg_match('/[\x00-\x1f\x7f]/',$input['reference'])||preg_match('//u',$input['reference'])!==1||!in_array($input['enabled']??null,['0','1'],true))throw new \DomainException('bb_webhook_configuration_invalid');
        $this->tx(function()use($issuer,$actor,$profile,$input):void{
            $this->authorize($issuer,$actor);$p=$this->rows("SELECT id,connection_id FROM {$this->prefix}bb_api_profiles WHERE id=? AND issuer_id=? FOR UPDATE",[$profile,$issuer])[0]??null;if(!$p)throw new \DomainException('bb_webhook_profile_unavailable');
            $old=$this->rows("SELECT * FROM {$this->prefix}bb_api_webhook_receivers WHERE profile_id=? AND fingerprint=? FOR UPDATE",[$profile,$input['fingerprint']])[0]??null;
            if($old){if((int)$old['issuer_id']!==$issuer||$old['reference']!==trim($input['reference']))throw new \DomainException('bb_webhook_configuration_conflict');$id=(int)$old['id'];\IssuerStorage::query($this->db,"UPDATE {$this->prefix}bb_api_webhook_receivers SET enabled=? WHERE id=?",[(int)$input['enabled'],$id]);}
            else{\IssuerStorage::query($this->db,"INSERT INTO {$this->prefix}bb_api_webhook_receivers(issuer_id,profile_id,fingerprint,reference,enabled,created_by,created_at) VALUES(?,?,?,?,?,?,?)",[$issuer,$profile,$input['fingerprint'],trim($input['reference']),(int)$input['enabled'],$actor,gmdate('Y-m-d H:i:s')]);$id=(int)$this->db->insert_id();}
            $saved=$this->rows("SELECT * FROM {$this->prefix}bb_api_webhook_receivers WHERE id=?",[$id])[0]??[];if($id<1||(int)($saved['enabled']??-1)!==(int)$input['enabled']||($saved['fingerprint']??null)!==$input['fingerprint'])throw new \RuntimeException('issuer_bb_webhook_write_unconfirmed');
            \IssuerStorage::query($this->db,"INSERT INTO {$this->prefix}bb_audit(issuer_id,connection_id,actor_id,action,resource_type,resource_id,created_at) VALUES(?,?,?,'api_webhook_configured','api_webhook_receiver',?,?)",[$issuer,$p['connection_id'],$actor,$id,gmdate('Y-m-d H:i:s')]);$audit=(int)$this->db->insert_id();if($audit<1||!$this->rows("SELECT id FROM {$this->prefix}bb_audit WHERE id=? AND issuer_id=?",[$audit,$issuer]))throw new \RuntimeException('issuer_bb_webhook_write_unconfirmed');
        });
    }
    private static function date($input,string $format):string
    {
        if(!is_string($input))throw new \DomainException('bb_webhook_payload_invalid');$date=\DateTimeImmutable::createFromFormat('!'.$format,$input,new \DateTimeZone('America/Sao_Paulo'));if(!$date||$date->format($format)!==$input)throw new \DomainException('bb_webhook_payload_invalid');return $date->format(str_contains($format,'H')?'Y-m-d H:i:s':'Y-m-d');
    }
    public static function projection(string $body):array
    {
        if($body===''||strlen($body)>65536)throw new \DomainException('bb_webhook_payload_invalid');$data=ApiClient::decodeExact($body);
        foreach(['id'=>'/^000[1-9][0-9]{16}$/D','numeroConvenio'=>'/^[1-9][0-9]{6}$/D','numeroOperacao'=>'/^[1-9][0-9]{0,9}$/D','carteiraConvenio'=>'/^17$/D','variacaoCarteiraConvenio'=>'/^[0-9]{1,3}$/D','codigoEstadoBaixaOperacional'=>'/^(1|2|10)$/D','codigoModalidadeBoleto'=>'/^1$/D','canalLiquidacao'=>'/^[1-9]$/D','formaPagamento'=>'/^[1-4]$/D'] as $key=>$pattern)if(!is_string($data[$key]??null)||!preg_match($pattern,$data[$key]))throw new \DomainException('bb_webhook_payload_invalid');
        if(substr($data['id'],3,7)!==$data['numeroConvenio'])throw new \DomainException('bb_webhook_payload_invalid');
        $result=[];foreach(['id','numeroConvenio','numeroOperacao','carteiraConvenio','variacaoCarteiraConvenio','codigoEstadoBaixaOperacional','codigoModalidadeBoleto','canalLiquidacao','formaPagamento'] as $key)$result[$key]=$data[$key];
        foreach(['dataRegistro','dataVencimento','dataCredito'] as $key)$result[$key]=self::date($data[$key]??null,'d.m.Y');$result['dataLiquidacao']=self::date($data['dataLiquidacao']??null,'d/m/Y H:i:s');
        foreach(['valorOriginal','valorPagoSacado'] as $key)$result[$key]=Fields::amount(\app\services\InvoicePaymentWriter::minorUnits($data[$key]??null));
        $result['observation']=$result['codigoEstadoBaixaOperacional']==='10'?'operational_down_cancelled':'operational_down_observed';return $result;
    }
    public function receive(MtlsEvidence $tls,string $body):int
    {
        $event=self::projection($body);$payloadHash=hash('sha256',json_encode($event,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
        return $this->tx(function()use($tls,$event,$payloadHash):int{
            // Resolve origin and receiver together. No company selector/default, fallback credential or browser session.
            $matches=$this->rows("SELECT o.id,o.issuer_id,o.connection_id,o.account_id,o.environment,o.agreement_number,o.scope_key,o.invoice_id,o.payload_hash,r.id receiver_id,a.snapshot_json,p.profile_hash,p.configuration_hash profile_configuration_hash,p.api_stage,p.definition_json,c.variation,c.wallet,c.issuer_tax_id,c.issuer_name,c.configuration_hash,c.identity_hash,c.account_id connection_account,c.environment connection_environment,c.agreement_number connection_agreement FROM {$this->prefix}bb_origins o JOIN {$this->prefix}bb_api_origins a ON a.origin_id=o.id JOIN {$this->prefix}bb_api_profiles p ON p.id=a.profile_id AND p.issuer_id=o.issuer_id JOIN {$this->prefix}bb_connections c ON c.id=o.connection_id AND c.issuer_id=o.issuer_id JOIN {$this->prefix}bb_api_webhook_receivers r ON r.profile_id=p.id AND r.issuer_id=o.issuer_id JOIN {$this->prefix}issuers_registry i ON i.id=o.issuer_id WHERE o.remote_id=? AND o.agreement_number=? AND o.channel='api' AND o.provider='banco_brasil' AND r.fingerprint=? AND r.enabled=1 AND i.active=1 FOR UPDATE",[$event['id'],$event['numeroConvenio'],$tls->fingerprint]);
            if(count($matches)!==1)throw new \DomainException('bb_webhook_origin_unavailable');$origin=$matches[0];
            $snapshot=json_decode($origin['snapshot_json'],true,32,JSON_THROW_ON_ERROR);$definition=json_decode($origin['definition_json'],true,32,JSON_THROW_ON_ERROR);
            $identity=hash('sha256',json_encode([(int)$origin['issuer_id'],$origin['connection_account'],$origin['connection_environment'],$origin['connection_agreement'],$origin['wallet'],$origin['variation']],JSON_THROW_ON_ERROR));
            $configuration=hash('sha256',json_encode([$identity,$origin['issuer_tax_id'],$origin['issuer_name']],JSON_THROW_ON_ERROR));
            $scope=(new Scope((int)$origin['issuer_id'],(int)$origin['connection_id'],$origin['account_id'],$origin['environment'],$origin['agreement_number']))->key();
            if(!hash_equals($identity,$origin['identity_hash'])||!hash_equals($configuration,$origin['configuration_hash'])||!hash_equals($origin['configuration_hash'],$origin['profile_configuration_hash'])||!hash_equals($origin['profile_hash'],hash('sha256',$origin['definition_json']))||($definition['spec_sha256']??null)!==ApiClient::SPEC_SHA256||($definition['api_stage']??null)!==$origin['api_stage']||($definition['configuration_hash']??null)!==$configuration||($origin['api_stage']==='production')!==($origin['environment']==='production')||$origin['account_id']!==$origin['connection_account']||$origin['environment']!==$origin['connection_environment']||$origin['agreement_number']!==$origin['connection_agreement']||$origin['scope_key']!==$scope||($snapshot['scope_key']??null)!==$scope||($snapshot['api_stage']??null)!==$origin['api_stage']||($snapshot['profile_hash']??null)!==$origin['profile_hash']||(int)($snapshot['invoice']['issuer_id']??0)!==(int)$origin['issuer_id']||(int)($snapshot['invoice']['id']??0)!==(int)$origin['invoice_id'])throw new \DomainException('bb_webhook_origin_unavailable');
            if(!hash_equals($origin['payload_hash'],hash('sha256',$origin['snapshot_json']))||(int)$origin['variation']!==(int)$event['variacaoCarteiraConvenio']||\app\services\InvoicePaymentWriter::minorUnits($snapshot['invoice']['total'])!==\app\services\InvoicePaymentWriter::minorUnits($event['valorOriginal']))throw new \DomainException('bb_webhook_origin_unavailable');
            $dates=[$snapshot['invoice']['duedate']];foreach($this->rows("SELECT result_json FROM {$this->prefix}bb_api_operations WHERE origin_id=? AND operation='amend_due' AND status IN ('validated','unknown','confirmed') FOR UPDATE",[$origin['id']]) as $instruction){$data=json_decode($instruction['result_json'],true,32,JSON_THROW_ON_ERROR);if(isset($data['amendment']['due_date']))$dates[]=$data['amendment']['due_date'];}
            if(!in_array($event['dataVencimento'],$dates,true))throw new \DomainException('bb_webhook_origin_unavailable');
            $key=hash('sha256',json_encode([$event['id'],$event['numeroOperacao'],$event['codigoEstadoBaixaOperacional'],$event['dataLiquidacao']],JSON_THROW_ON_ERROR));
            $old=$this->rows("SELECT id,payload_hash FROM {$this->prefix}bb_api_notifications WHERE origin_id=? AND event_key=? FOR UPDATE",[$origin['id'],$key])[0]??null;if($old){if(!hash_equals($old['payload_hash'],$payloadHash))throw new \DomainException('bb_webhook_event_conflict');return (int)$old['id'];}
            $json=json_encode($event,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);\IssuerStorage::query($this->db,"INSERT INTO {$this->prefix}bb_api_notifications(issuer_id,origin_id,receiver_id,event_key,payload_hash,fingerprint,projection_json,created_at) VALUES(?,?,?,?,?,?,?,?)",[$origin['issuer_id'],$origin['id'],$origin['receiver_id'],$key,$payloadHash,$tls->fingerprint,$json,gmdate('Y-m-d H:i:s')]);$id=(int)$this->db->insert_id();
            $saved=$this->rows("SELECT payload_hash,projection_json FROM {$this->prefix}bb_api_notifications WHERE id=? AND issuer_id=?",[$id,$origin['issuer_id']])[0]??[];if($id<1||($saved['payload_hash']??null)!==$payloadHash||($saved['projection_json']??null)!==$json)throw new \RuntimeException('issuer_bb_webhook_write_unconfirmed');return $id;
        });
    }
    public function overview($issuer,$actor):array
    {
        $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);return $this->tx(function()use($issuer,$actor):array{$this->authorize($issuer,$actor);$receivers=$this->rows("SELECT id,profile_id,fingerprint,reference,enabled FROM {$this->prefix}bb_api_webhook_receivers WHERE issuer_id=? ORDER BY id DESC LIMIT 30",[$issuer]);$events=$this->rows("SELECT n.id,n.origin_id,n.projection_json,n.created_at FROM {$this->prefix}bb_api_notifications n JOIN {$this->prefix}bb_origins o ON o.id=n.origin_id AND o.issuer_id=n.issuer_id WHERE n.issuer_id=? ORDER BY n.id DESC LIMIT 30",[$issuer]);foreach($events as &$event){$event['projection']=json_decode($event['projection_json'],true,32,JSON_THROW_ON_ERROR);unset($event['projection_json']);}return compact('receivers','events');});
    }
}
