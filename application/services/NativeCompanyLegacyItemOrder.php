<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyItemOrderEndpoints.php';
require_once __DIR__.'/issuers/IssuerTransaction.php';
require_once __DIR__.'/issuers/IssuerResourceAccess.php';
require_once __DIR__.'/InvoiceIssuerScope.php';
require_once __DIR__.'/ClientIssuerScope.php';
require_once __DIR__.'/NativeCompanyLegacySalesBoundary.php';
/** Historical preview order only: one proved legacy parent, no company-owned document adoption. */
final class NativeCompanyLegacyItemOrder
{
    private const TYPES=['invoice'=>['invoices','invoices'],'estimate'=>['estimates','estimates'],'proposal'=>['proposals','proposals'],'credit_note'=>['creditnotes','credit_notes']];
    public function __construct(private $db,private string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    private function rows(string $sql,array $a=[]):array{return IssuerStorage::query($this->db,$sql,$a)->result_array();}
    private function authorize(int $i,int $a,string $feature):void
    {
        $acl=new IssuerResourceAccess($this->db,$this->p);if($acl->primaryId()!==$i||!$acl->member($i,$a))throw new DomainException('native_legacy_order_denied');
        $s=$this->rows('SELECT admin FROM '.$this->p.'staff WHERE staffid=? AND active=1',[$a])[0]??null;
        if(!$s||((int)$s['admin']!==1&&!$this->rows('SELECT staff_id FROM '.$this->p.'staff_permissions WHERE staff_id=? AND feature=? AND capability=?',[$a,$feature,'edit'])))throw new DomainException('native_legacy_order_denied');
    }
    public function save(int $i,int $a,string $type,$pairs):void
    {
        if(!isset(self::TYPES[$type]))throw new DomainException('native_legacy_order_denied');$ids=NativeCompanyItemOrderEndpoints::orderedIds($pairs);[$table,$feature]=self::TYPES[$type];
        $this->authorize($i,$a,$feature);
        IssuerTransaction::run($this->db,$this->p,['issuers_lock','issuers_registry','issuers_staff','issuers_installation_identity','issuers_resource_permissions','staff','staff_permissions','issuers_clients','issuers_client_links','clients','itemable',$table,'issuers_audit'],function()use($i,$a,$type,$ids,$table,$feature){
            $this->authorize($i,$a,$feature);$rows=$this->rows('SELECT * FROM '.$this->p.'itemable WHERE id IN ('.implode(',',array_fill(0,count($ids),'?')).') ORDER BY id FOR UPDATE',$ids);
            if(count($rows)!==count($ids))throw new DomainException('native_legacy_order_denied');$parent=(int)$rows[0]['rel_id'];
            foreach($rows as$r)if($r['rel_type']!==$type||(int)$r['rel_id']!==$parent)throw new DomainException('native_legacy_order_denied');
            $source=$this->rows('SELECT * FROM '.$this->p.$table.' WHERE id=? FOR UPDATE',[$parent])[0]??null;if(!$source)throw new DomainException('native_legacy_order_denied');
            if(in_array($type,['invoice','credit_note'],true)&&(!array_key_exists('issuer_id',$source)||$source['issuer_id']!==null))throw new DomainException('native_legacy_order_denied');
            if(!($type==='proposal'?NativeCompanyLegacySalesBoundary::permits($this->db,$this->p,$table,$parent):\app\services\ClientIssuerScope::isLegacyDocumentClient($this->db,$this->p,$table,$parent)))throw new DomainException('native_legacy_order_denied');
            $all=$this->rows('SELECT id FROM '.$this->p.'itemable WHERE rel_id=? AND rel_type=? ORDER BY id FOR UPDATE',[$parent,$type]);$expected=$ids;sort($expected,SORT_NUMERIC);
            if($expected!==array_map('intval',array_column($all,'id')))throw new DomainException('native_legacy_order_denied');
            foreach($ids as$order=>$id)IssuerStorage::query($this->db,'UPDATE '.$this->p.'itemable SET item_order=? WHERE id=? AND rel_id=? AND rel_type=?',[$order+1,$id,$parent,$type]);
            if($ids!==array_map('intval',array_column($this->rows('SELECT id FROM '.$this->p.'itemable WHERE rel_id=? AND rel_type=? ORDER BY item_order,id',[$parent,$type]),'id')))throw new RuntimeException('native_legacy_order_unconfirmed');
            if(!($type==='proposal'?NativeCompanyLegacySalesBoundary::permits($this->db,$this->p,$table,$parent):\app\services\ClientIssuerScope::isLegacyDocumentClient($this->db,$this->p,$table,$parent))||$source!==($this->rows('SELECT * FROM '.$this->p.$table.' WHERE id=?',[$parent])[0]??null))throw new RuntimeException('native_legacy_order_unconfirmed');
            $after=$this->rows('SELECT * FROM '.$this->p.'itemable WHERE rel_id=? AND rel_type=? ORDER BY id',[$parent,$type]);
            foreach($rows as&$r)unset($r['item_order']);unset($r);foreach($after as&$r)unset($r['item_order']);unset($r);
            if($rows!==$after)throw new RuntimeException('native_legacy_order_unconfirmed');
            $this->authorize($i,$a,$feature);$meta=json_encode(['type'=>$type,'nonce'=>bin2hex(random_bytes(32))],JSON_THROW_ON_ERROR);
            IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at,metadata_json) VALUES(?,?,?,?,?,?)',[$a,'native_legacy_items_reordered',$i,$parent,date('Y-m-d H:i:s'),$meta]);$audit=(int)$this->db->insert_id();
            if(!$this->rows('SELECT id FROM '.$this->p.'issuers_audit WHERE id=? AND metadata_json=?',[$audit,$meta]))throw new RuntimeException('native_legacy_order_unconfirmed');
        });
    }
}
