<?php
declare(strict_types=1);
namespace BancoBrasil;
require_once __DIR__.'/ReturnValidationFailure.php';
require_once __DIR__.'/ReturnRejectionSchema.php';
require_once __DIR__.'/ModuleRelease.php';

/** Private rejected uploads. No event, origin, receipt or invoice mutation belongs here. */
final class ReturnRejections
{
    private const LIMIT=16*1024*1024;
    public function __construct(private $db,private string $p)
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new \InvalidArgumentException('bb_prefix_invalid');
    }
    private function access(int $issuer,int $actor,string $cap):void
    {
        $root=dirname(__DIR__,4).'/application/services/issuers/';
        require_once $root.'IssuerCore.php';
        require_once $root.'IssuerResourceAccess.php';
        $acl=new \IssuerResourceAccess($this->db,$this->p);
        $acl->lockFinancialIdentity($issuer,$actor);
        $module=\IssuerStorage::query($this->db,'SELECT active,installed_version FROM '.$this->p.'modules WHERE module_name=? FOR UPDATE',['banco_brasil'])->row_array();
        if(!\IssuerCore::ready($this->db,$this->p)||(int)($module['active']??0)!==1
            ||($module['installed_version']??null)!==ModuleRelease::VERSION
            ||!$acl->can($issuer,$actor,'banco_brasil','view')||!$acl->can($issuer,$actor,'banco_brasil',$cap))throw new \DomainException('bb_return_rejection_denied');
    }
    private function transaction(int $issuer,int $actor,string $cap,callable $work)
    {
        if($issuer<1||$actor<1)throw new \DomainException('bb_return_rejection_denied');
        require_once dirname(__DIR__,4).'/application/services/issuers/IssuerTransaction.php';
        $tables=['issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','staff','modules','bb_connections','bb_layout_bindings','bb_return_rejections','bb_audit'];
        return \IssuerTransaction::run($this->db,$this->p,$tables,function()use($issuer,$actor,$cap,$work){
            $this->access($issuer,$actor,$cap);
            SchemaValidator::assertCompatible($this->db,$this->p,ReturnRejectionSchema::requirements());
            $result=$work();$this->access($issuer,$actor,$cap);return$result;
        });
    }
    private function one(string $sql,array $args):array
    {
        $rows=\IssuerStorage::query($this->db,$sql,$args)->result_array();
        if(count($rows)!==1)throw new \DomainException('bb_return_rejection_unavailable');
        return$rows[0];
    }
    private static function recordIndex(string $bytes,int $width):?int
    {
        $offset=0;$line=1;$length=strlen($bytes);
        while($offset<$length){
            $end=strpos($bytes,"\r\n",$offset);
            if($end===false||$end-$offset!==$width)return$line;
            $offset=$end+2;++$line;
        }
        return null;
    }
    private function verify(array $row):void
    {
        if(!isset(ReturnValidationFailure::labels()[$row['reason_code']??''])
            ||!is_string($row['content']??null)||strlen($row['content'])<1||strlen($row['content'])>self::LIMIT
            ||(int)($row['byte_count']??0)!==strlen($row['content'])
            ||!is_string($row['content_hash']??null)||!hash_equals($row['content_hash'],hash('sha256',$row['content'])))throw new \DomainException('bb_return_rejection_corrupt');
    }
    private function audit(int $issuer,int $connection,int $actor,string $action,int $id):void
    {
        $sql='SELECT id FROM '.$this->p.'bb_audit WHERE issuer_id=? AND connection_id=? AND actor_id=? AND action=? AND resource_type=? AND resource_id=? ORDER BY id';
        $args=[$issuer,$connection,$actor,$action,'return_rejection',$id];
        $before=\IssuerStorage::query($this->db,$sql,$args)->result_array();
        \IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'bb_audit(issuer_id,connection_id,actor_id,action,resource_type,resource_id,created_at) VALUES(?,?,?,?,?,?,?)',[...$args,date('Y-m-d H:i:s')]);
        $after=\IssuerStorage::query($this->db,$sql,$args)->result_array();
        if(count($after)!==count($before)+1||array_slice($after,0,count($before))!==$before)throw new \RuntimeException('bb_return_rejection_audit_unconfirmed');
    }
    public function retain(array $connection,int $actor,string $bytes,int $bindingId,ReturnValidationFailure $failure):array
    {
        $issuer=(int)($connection['issuer_id']??0);$connectionId=(int)($connection['id']??0);
        if(strlen($bytes)<1||strlen($bytes)>self::LIMIT||$connectionId<1||$bindingId<1)throw new \DomainException('bb_return_rejection_invalid');
        $code=$failure->getMessage();if(!isset(ReturnValidationFailure::labels()[$code]))throw new \DomainException('bb_return_rejection_invalid');
        return$this->transaction($issuer,$actor,'import',function()use($connection,$connectionId,$issuer,$actor,$bytes,$bindingId,$code){
            $current=$this->one('SELECT * FROM '.$this->p.'bb_connections WHERE id=? AND issuer_id=? FOR UPDATE',[$connectionId,$issuer]);
            if($current!==$connection)throw new \DomainException('bb_return_rejection_changed');
            $store=new ModuleStore($this->db,$this->p);$binding=$store->layoutBinding($bindingId,$current);$scope=ModuleStore::agreement($current)->scope->key();$hash=hash('sha256',$bytes);
            $key=[$scope,$bindingId,$hash,$code];
            $matches=\IssuerStorage::query($this->db,'SELECT * FROM '.$this->p.'bb_return_rejections WHERE scope_key=? AND layout_binding_id=? AND content_hash=? AND reason_code=? FOR UPDATE',$key)->result_array();
            if(count($matches)>1)throw new \DomainException('bb_return_rejection_corrupt');
            $duplicate=$matches!==[];
            $expected=['layout_binding_id'=>$bindingId,'issuer_id'=>$issuer,'connection_id'=>$connectionId,'scope_key'=>$scope,'content_hash'=>$hash,'layout_id'=>$binding['layout_id'],'reason_code'=>$code,'record_index'=>self::recordIndex($bytes,in_array($binding['layout_id'],[LayoutCatalog::CNAB400,LayoutCatalog::CNAB400_AUX],true)?400:240),'byte_count'=>strlen($bytes),'content'=>$bytes];
            if($duplicate){$row=$matches[0];}
            else{
                $row=$expected+['created_by'=>$actor,'created_at'=>date('Y-m-d H:i:s')];
                $written=$row;
                \IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'bb_return_rejections('.implode(',',array_keys($row)).') VALUES('.implode(',',array_fill(0,count($row),'?')).')',array_values($row));
                $id=(int)$this->db->insert_id();if($id<1)throw new \RuntimeException('bb_return_rejection_write_unconfirmed');
                $row=$this->one('SELECT * FROM '.$this->p.'bb_return_rejections WHERE id=? AND issuer_id=?',[$id,$issuer]);
                $this->audit($issuer,$connectionId,$actor,'return_rejected_archived',$id);
            }
            $this->verify($row);
            foreach(($duplicate?$expected:$written) as$key=>$value)if($value===null?($row[$key]??null)!==null:(string)($row[$key]??'')!==(string)$value)throw new \RuntimeException('bb_return_rejection_write_unconfirmed');
            $again=$this->one('SELECT * FROM '.$this->p.'bb_return_rejections WHERE id=? AND issuer_id=?',[(int)$row['id'],$issuer]);
            if($again!==$row||$this->one('SELECT * FROM '.$this->p.'bb_connections WHERE id=? AND issuer_id=?',[$connectionId,$issuer])!==$current
                ||$store->layoutBinding($bindingId,$current)!==$binding)throw new \DomainException('bb_return_rejection_changed');
            return['id'=>(int)$row['id'],'duplicate'=>$duplicate,'rejected'=>true,'reason_code'=>$code,'financial_effect'=>'not_applied'];
        });
    }
    public function recent(int $issuer,int $actor):array
    {
        return$this->transaction($issuer,$actor,'view',function()use($issuer){
            $sql='SELECT id,connection_id,layout_binding_id,layout_id,reason_code,record_index,byte_count,content_hash,created_at FROM '.$this->p.'bb_return_rejections WHERE issuer_id=? ORDER BY id DESC LIMIT 30';
            $rows=\IssuerStorage::query($this->db,$sql,[$issuer])->result_array();
            foreach($rows as&$row){$row['reason_label']=ReturnValidationFailure::labels()[$row['reason_code']]??throw new \DomainException('bb_return_rejection_corrupt');}unset($row);
            return$rows;
        });
    }
    public function download(int $issuer,int $actor,int $id):array
    {
        if($id<1)throw new \DomainException('bb_return_rejection_unavailable');
        return$this->transaction($issuer,$actor,'download',function()use($issuer,$actor,$id){
            $row=$this->one('SELECT * FROM '.$this->p.'bb_return_rejections WHERE id=? AND issuer_id=? FOR UPDATE',[$id,$issuer]);$this->verify($row);
            $connection=$this->one('SELECT * FROM '.$this->p.'bb_connections WHERE id=? AND issuer_id=? FOR UPDATE',[(int)$row['connection_id'],$issuer]);
            $binding=$this->one('SELECT * FROM '.$this->p.'bb_layout_bindings WHERE id=? AND issuer_id=? AND connection_id=? FOR UPDATE',[(int)$row['layout_binding_id'],$issuer,(int)$row['connection_id']]);
            $scope=ModuleStore::agreement($connection)->scope->key();
            if(!hash_equals($scope,$row['scope_key'])||!hash_equals($scope,(string)$binding['scope_key'])||$binding['layout_id']!==$row['layout_id'])throw new \DomainException('bb_return_rejection_corrupt');
            $this->audit($issuer,(int)$row['connection_id'],$actor,'return_rejection_downloaded',$id);
            if($this->one('SELECT * FROM '.$this->p.'bb_return_rejections WHERE id=? AND issuer_id=?',[$id,$issuer])!==$row
                ||$this->one('SELECT * FROM '.$this->p.'bb_connections WHERE id=? AND issuer_id=?',[(int)$row['connection_id'],$issuer])!==$connection
                ||$this->one('SELECT * FROM '.$this->p.'bb_layout_bindings WHERE id=? AND issuer_id=? AND connection_id=?',[(int)$row['layout_binding_id'],$issuer,(int)$row['connection_id']])!==$binding)throw new \DomainException('bb_return_rejection_changed');
            return['content'=>$row['content'],'hash'=>$row['content_hash'],'filename'=>'bb-retorno-recusado-'.$id.'.ret'];
        });
    }
}
