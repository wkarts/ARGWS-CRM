<?php

declare(strict_types=1);
namespace BancoBrasil;
require_once __DIR__.'/Origination.php';
require_once __DIR__.'/ReconciliationSchema.php';
/** Explicit source matching and append-only review. No bank transport or implicit money movement. */
final class Reconciliation
{
    public function __construct(private $db,private string $prefix){}
    public static function tables():array
    {
        return ['bb_connections','bb_layout_bindings','bb_origins','bb_batches','bb_batch_origins','bb_origin_snapshots','bb_return_files','bb_return_file_events','bb_return_events','bb_audit','bb_event_links','bb_event_reviews','bb_event_receipts'];
    }
    public static function authorize($db,string $p,int $issuer,int $actor,string $cap):void
    {
        $row=\IssuerStorage::query($db,'SELECT active,installed_version FROM '.$p.'modules WHERE module_name=?',['banco_brasil'])->row_array();
        $acl=new \IssuerResourceAccess($db,$p);
        if((int)($row['active']??0)!==1||($row['installed_version']??null)!=='1.1.1'||!$acl->can($issuer,$actor,'banco_brasil','view')||!$acl->can($issuer,$actor,'banco_brasil',$cap)||($cap==='reconcile'&&!$acl->can($issuer,$actor,'invoices','view')))throw new \DomainException('bb_reconciliation_access_denied');
    }
    public static function event($db,string $p,int $issuer,int $event):array
    {
        $row=\IssuerStorage::query($db,'SELECT * FROM '.$p.'bb_return_events WHERE id=? AND issuer_id=? FOR UPDATE',[$event,$issuer])->row_array();
        if(!$row)throw new \DomainException('bb_event_unavailable');
        if(!hash_equals($row['payload_hash'],hash('sha256',$row['observation_json'])))throw new \DomainException('bb_event_corrupt');
        $e=json_decode($row['observation_json'],true,32,JSON_THROW_ON_ERROR);
        foreach(['scope_key'=>'scope_key','remote_id'=>'our_number','control_reference'=>'control','event_key'=>'event_key','occurrence'=>'occurrence','observation'=>'observation'] as $column=>$key)if(($e[$key]??null)!==$row[$column])throw new \DomainException('bb_event_corrupt');
        if(($e['provider']??null)!=='banco_brasil')throw new \DomainException('bb_event_corrupt');
        $files=\IssuerStorage::query($db,'SELECT f.content_hash,f.provenance_json,x.layout_binding_id FROM '.$p.'bb_return_file_events x JOIN '.$p.'bb_return_files f ON f.id=x.return_file_id WHERE x.return_event_id=? AND x.issuer_id=? AND f.issuer_id=? AND x.connection_id=? AND f.connection_id=? AND x.scope_key=? AND f.scope_key=?',[$event,$issuer,$issuer,$row['connection_id'],$row['connection_id'],$row['scope_key'],$row['scope_key']])->result_array();
        if(!$files)throw new \DomainException('bb_event_provenance_required');
        foreach($files as $file)if(!preg_match('/^[a-f0-9]{64}$/D',$file['content_hash'])||(int)$file['layout_binding_id']!==(int)$row['layout_binding_id']||!is_array(json_decode($file['provenance_json']??'',true)))throw new \DomainException('bb_event_provenance_required');
        return [$row,$e];
    }
    public static function origin($db,string $p,int $issuer,array $row,array $e):array
    {
        $rows=\IssuerStorage::query($db,'SELECT * FROM '.$p.'bb_origins WHERE issuer_id=? AND connection_id=? AND scope_key=? AND provider=? AND channel=? AND remote_id=? AND control_reference=? FOR UPDATE',[$issuer,$row['connection_id'],$row['scope_key'],'banco_brasil',$e['channel'],$row['remote_id'],$row['control_reference']])->result_array();
        if(count($rows)!==1)throw new \DomainException('bb_event_origin_unmatched');$origin=$rows[0];
        $snapshot=\IssuerStorage::query($db,'SELECT s.*,b.generation_state,b.delivery_state,b.layout_id,b.layout_source_hash FROM '.$p.'bb_origin_snapshots s JOIN '.$p.'bb_batch_origins x ON x.origin_id=s.origin_id JOIN '.$p.'bb_batches b ON b.id=x.batch_id WHERE s.origin_id=? AND b.issuer_id=? AND b.connection_id=? AND b.scope_key=?',[$origin['id'],$issuer,$row['connection_id'],$row['scope_key']])->row_array();
        if(!$snapshot||!hash_equals($origin['payload_hash'],hash('sha256',$snapshot['snapshot_json']))||(int)$row['layout_binding_id']!==(int)$origin['layout_binding_id']||(int)$snapshot['layout_binding_id']!==(int)$origin['layout_binding_id'])throw new \DomainException('bb_event_origin_unmatched');
        $s=json_decode($snapshot['snapshot_json'],true,32,JSON_THROW_ON_ERROR);$c=$s['connection'];
        $a=new Agreement(new Scope($issuer,(int)$c['id'],$c['account_id'],$c['environment'],$c['agreement_number']),$c['issuer_tax_id'],$s['beneficiary_name']??$c['issuer_name'],$c['variation'],$c['wallet']);
        if($a->scope->key()!==$row['scope_key']||$origin['remote_id']!==$a->ourNumber($s['title']['sequence'])||$origin['control_reference']!==$s['title']['control']||(int)$origin['invoice_id']!==(int)$s['invoice']['id']||$origin['account_id']!==$c['account_id']||$origin['environment']!==$c['environment']||$origin['agreement_number']!==$c['agreement_number']||($e['document']??null)!==$s['title']['document']||($e['due_date']??null)!==$s['title']['due_date'])throw new \DomainException('bb_event_origin_unmatched');
        return [$origin,$s,$snapshot];
    }
    private function transaction(callable $work)
    {
        ReconciliationSchema::ensure($this->db,$this->prefix,false);OriginationSchema::assertValid($this->db,$this->prefix);
        return \IssuerTransaction::run($this->db,$this->prefix,array_merge(['issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','staff','modules','invoices','issuers_invoice_documents'],self::tables()),$work);
    }
    public function link($issuer,$actor,$event):int
    {
        $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);$event=\IssuerPolicy::id($event);
        return $this->transaction(function()use($issuer,$actor,$event):int{
            self::authorize($this->db,$this->prefix,$issuer,$actor,'reconcile');[$row,$e]=self::event($this->db,$this->prefix,$issuer,$event);[$origin]=self::origin($this->db,$this->prefix,$issuer,$row,$e);
            $old=\IssuerStorage::query($this->db,'SELECT origin_id FROM '.$this->prefix.'bb_event_links WHERE event_id=? FOR UPDATE',[$event])->row_array();
            if($old){if((int)$old['origin_id']!==(int)$origin['id'])throw new \DomainException('bb_event_link_conflict');return (int)$origin['id'];}
            \IssuerStorage::query($this->db,'INSERT INTO '.$this->prefix.'bb_event_links(event_id,origin_id,issuer_id,linked_by,created_at) VALUES(?,?,?,?,?)',[$event,$origin['id'],$issuer,$actor,date('Y-m-d H:i:s')]);
            $confirmed=\IssuerStorage::query($this->db,'SELECT origin_id,issuer_id FROM '.$this->prefix.'bb_event_links WHERE event_id=?',[$event])->row_array();
            if(!$confirmed||(int)$confirmed['origin_id']!==(int)$origin['id']||(int)$confirmed['issuer_id']!==$issuer)throw new \RuntimeException('issuer_bb_write_unconfirmed');
            self::audit($this->db,$this->prefix,$issuer,(int)$row['connection_id'],$actor,'event_origin_linked',$event);return (int)$origin['id'];
        });
    }
    public static function text($value,int $max):string
    {
        if(!is_string($value)||trim($value)===''||strlen($value)>$max||preg_match('/[\x00-\x1f\x7f]/',$value)||preg_match('//u',$value)!==1)throw new \DomainException('bb_review_invalid_input');return trim($value);
    }
    public function review($issuer,$actor,$event,array $input):void
    {
        $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);$event=\IssuerPolicy::id($event);
        if(array_diff(array_keys($input),['decision','reason','reference'])||!in_array($input['decision']??null,['unmatched','divergence','rejected','reversal','operational','other'],true))throw new \DomainException('bb_review_invalid_input');
        $reason=self::text($input['reason']??null,2000);$reference=self::text($input['reference']??null,191);
        $this->transaction(function()use($issuer,$actor,$event,$input,$reason,$reference):void{
            self::authorize($this->db,$this->prefix,$issuer,$actor,'reconcile');[$row]=self::event($this->db,$this->prefix,$issuer,$event);
            self::appendReview($this->db,$this->prefix,$issuer,$actor,$event,$input['decision'],$reason,$reference);
            self::audit($this->db,$this->prefix,$issuer,(int)$row['connection_id'],$actor,'event_review_without_payment',$event);
        });
    }
    public static function appendReview($db,string $p,int $issuer,int $actor,int $event,string $decision,string $reason,string $reference):void
    {
        $key=hash('sha256',json_encode([$issuer,$actor,$event,$decision,$reason,$reference],JSON_THROW_ON_ERROR));
        if(\IssuerStorage::query($db,'SELECT id FROM '.$p.'bb_event_reviews WHERE review_key=?',[$key])->row_array())return;
        \IssuerStorage::query($db,'INSERT INTO '.$p.'bb_event_reviews(event_id,issuer_id,actor_id,decision,reason,evidence_reference,review_key,created_at) VALUES(?,?,?,?,?,?,?,?)',[$event,$issuer,$actor,$decision,$reason,$reference,$key,date('Y-m-d H:i:s')]);
        if(!\IssuerStorage::query($db,'SELECT id FROM '.$p.'bb_event_reviews WHERE review_key=?',[$key])->row_array())throw new \RuntimeException('issuer_bb_write_unconfirmed');
    }
    public function summaries($issuer,$actor):array
    {
        $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);self::authorize($this->db,$this->prefix,$issuer,$actor,'view');
        ReconciliationSchema::ensure($this->db,$this->prefix,false);
        $rows=\IssuerStorage::query($this->db,'SELECT e.id,l.origin_id,r.payment_id FROM '.$this->prefix.'bb_return_events e LEFT JOIN '.$this->prefix.'bb_event_links l ON l.event_id=e.id AND l.issuer_id=e.issuer_id LEFT JOIN '.$this->prefix.'bb_event_receipts r ON r.event_id=e.id AND r.issuer_id=e.issuer_id WHERE e.issuer_id=? ORDER BY e.id DESC LIMIT 50',[$issuer])->result_array();
        $result=[];foreach($rows as $row)$result[(int)$row['id']]=['linked'=>$row['origin_id']!==null,'applied'=>$row['payment_id']!==null];
        return $result;
    }
    public static function audit($db,string $p,int $issuer,int $connection,int $actor,string $action,int $event):void
    {
        \IssuerStorage::query($db,'INSERT INTO '.$p.'bb_audit(issuer_id,connection_id,actor_id,action,resource_type,resource_id,created_at) VALUES(?,?,?,?,?,?,?)',[$issuer,$connection,$actor,$action,'return_event',$event,date('Y-m-d H:i:s')]);
        $id=(int)$db->insert_id();
        if($id<1||count(\IssuerStorage::query($db,'SELECT id FROM '.$p.'bb_audit WHERE id=? AND issuer_id=? AND actor_id=? AND action=? AND resource_id=?',[$id,$issuer,$actor,$action,$event])->result_array())!==1)throw new \RuntimeException('issuer_bb_write_unconfirmed');
    }
}
