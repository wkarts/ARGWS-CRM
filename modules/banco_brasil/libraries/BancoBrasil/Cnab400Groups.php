<?php
declare(strict_types=1);
namespace BancoBrasil;
require_once __DIR__.'/Cnab400GroupSchema.php';
require_once __DIR__.'/Cnab400Generator.php';
require_once __DIR__.'/Cnab400GeneratedSource.php';

/** Called only inside the preparation's authorized financial mutex transaction. */
final class Cnab400Groups
{
    public const TABLES=['bb_cnab_groups','bb_cnab_group_members'];
    public function __construct(private $db,private string $p){}
    private function rows(string $sql,array $args=[]):array{return \IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private function owned():void{\IssuerTransaction::assertOwned($this->db,$this->p,self::TABLES);}
    public static function compatibility(array $source):string
    {
        $snapshot=json_decode($source['snapshot_json'],true,64,JSON_THROW_ON_ERROR);
        return hash('sha256',json_encode([$source['issuer_id'],$source['connection_id'],$source['layout_binding_id'],$source['scope_key'],$snapshot['contract_id'],$snapshot['connection']['configuration_hash']],JSON_THROW_ON_ERROR));
    }
    /** Re-encode validated individual records with one header/trailer and contiguous sequence. */
    public static function compile(array $sources):string
    {
        if(!$sources||count($sources)>100)throw new \DomainException('bb400_group_size');
        $first=$sources[0];$key=self::compatibility($first);$snapshot=json_decode($first['snapshot_json'],true,64,JSON_THROW_ON_ERROR);$c=$snapshot['connection'];
        $agreement=new Agreement(new Scope((int)$first['issuer_id'],(int)$c['id'],$c['account_id'],$c['environment'],$c['agreement_number']),$c['issuer_tax_id'],$snapshot['beneficiary_name'],$c['variation'],$c['wallet']);
        $titles=[];$date='0000-00-00';
        foreach($sources as $source){
            $snapshot=json_decode($source['snapshot_json'],true,64,JSON_THROW_ON_ERROR);
            Cnab400GeneratedSource::validate($source,$snapshot,$source);
            if(self::compatibility($source)!==$key)throw new \DomainException('bb400_group_incompatible');
            $lines=explode("\r\n",$source['content']);$line=$lines[1];$date=max($date,Fields400::parseDate(substr($lines[0],94,6)));
            $titles[]=$snapshot['title']+[
                'payer_tax_id'=>$snapshot['document_snapshot']['recipient']['tax_id'],
                'payer_name'=>rtrim(substr($line,234,37)),'payer_address'=>rtrim(substr($line,274,40)),
                'payer_district'=>rtrim(substr($line,314,12)),'payer_zip'=>substr($line,326,8),
                'payer_city'=>rtrim(substr($line,334,15)),'payer_state'=>substr($line,349,2),
                'species'=>substr($line,147,2),'acceptance'=>$line[149],
            ];
        }
        return (new Cnab400Generator())->candidate($agreement,(int)$first['file_sequence'],$date,$titles)['content'];
    }
    public function find(int $issuer,int $preparation):?array
    {
        $this->owned();
        $row=$this->rows("SELECT g.* FROM {$this->p}bb_cnab_group_members m LEFT JOIN {$this->p}bb_cnab_groups g ON g.id=m.group_id WHERE m.preparation_id=? FOR UPDATE",[$preparation])[0]??null;
        if($row!==null&&((int)($row['id']??0)<1||(int)($row['issuer_id']??0)!==$issuer))throw new \DomainException('bb400_group_corrupt');
        return $row;
    }
    public function members(int $group):array
    {
        $this->owned();
        return $this->rows("SELECT preparation_id,source_hash FROM {$this->p}bb_cnab_group_members WHERE group_id=? ORDER BY preparation_id FOR UPDATE",[$group]);
    }
    private function sources(array $group,callable $validate):array
    {
        $sources=[];
        foreach($this->members((int)$group['id'])as$member){
            $source=$validate((int)$member['preparation_id']);
            if(!hash_equals($member['source_hash'],$source['content_hash'])||self::compatibility($source)!==$group['compatibility_hash'])throw new \DomainException('bb400_group_corrupt');
            $sources[]=$source;
        }
        return $sources;
    }
    public function attach(int $issuer,int $actor,int $preparation,array $source,callable $validate):void
    {
        $this->owned();Cnab400GroupSchema::assertValid($this->db,$this->p);
        if($this->find($issuer,$preparation)!==null)throw new \DomainException('bb400_group_already_bound');
        $key=self::compatibility($source);$chosen=null;
        $groups=$this->rows("SELECT * FROM {$this->p}bb_cnab_groups WHERE issuer_id=? AND compatibility_hash=? AND state='open' ORDER BY id FOR UPDATE",[$issuer,$key]);
        foreach($groups as $group){
            try{$sources=$this->sources($group,$validate);}
            catch(\DomainException $error){
                // Preserve an old group needing review without blocking an unrelated new title.
                if(in_array($error->getMessage(),['bb400_preparation_stale','bb400_preparation_invoice_unavailable','bb_invoice_not_eligible'],true))continue;
                throw $error;
            }
            if(count($sources)>=100)continue;
            try{self::compile([...$sources,$source]);}
            catch(\InvalidArgumentException $e){if($e->getMessage()==='bb_title_dates_invalid')continue;throw $e;}
            $chosen=(int)$group['id'];break;
        }
        if($chosen===null){
            self::compile([$source]);
            \IssuerStorage::query($this->db,"INSERT INTO {$this->p}bb_cnab_groups(issuer_id,compatibility_hash,state,content,content_hash,created_by,created_at) VALUES(?,?,'open','',?,?,?)",[$issuer,$key,hash('sha256',''),$actor,gmdate('Y-m-d H:i:s')]);
            $chosen=(int)$this->db->insert_id();if($chosen<1)throw new \RuntimeException('issuer_bb_write_unconfirmed');
        }
        \IssuerStorage::query($this->db,"INSERT INTO {$this->p}bb_cnab_group_members(preparation_id,group_id,source_hash) VALUES(?,?,?)",[$preparation,$chosen,$source['content_hash']]);
        $saved=$this->find($issuer,$preparation);
        if(!$saved||(int)$saved['id']!==$chosen||$saved['state']!=='open')throw new \RuntimeException('issuer_bb_write_unconfirmed');
    }
    public function download(array $group,callable $validate):array
    {
        $this->owned();$sources=$this->sources($group,$validate);$content=self::compile($sources);$hash=hash('sha256',$content);
        if($group['state']==='open'){
            if($group['content']!==''||$group['content_hash']!==hash('sha256','')||$group['frozen_at']!==null)throw new \DomainException('bb400_group_corrupt');
            \IssuerStorage::query($this->db,"UPDATE {$this->p}bb_cnab_groups SET state='downloaded',content=?,content_hash=?,frozen_at=? WHERE id=? AND issuer_id=? AND state='open'",[$content,$hash,gmdate('Y-m-d H:i:s'),$group['id'],$group['issuer_id']]);
            $group=$this->rows("SELECT * FROM {$this->p}bb_cnab_groups WHERE id=? AND issuer_id=? FOR UPDATE",[$group['id'],$group['issuer_id']])[0]??[];
        }
        if(($group['state']??null)!=='downloaded'||!is_string($group['frozen_at']??null)||($group['content']??null)!==$content||($group['content_hash']??null)!==$hash)throw new \DomainException('bb400_group_corrupt');
        return ['content'=>$group['content'],'hash'=>$group['content_hash'],'filename'=>'bb-cnab400-lote-'.$group['id'].'.rem','group_id'=>(int)$group['id'],'title_count'=>count($sources)];
    }
}
