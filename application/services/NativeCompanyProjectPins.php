<?php

declare(strict_types=1);
defined('BASEPATH') or exit('No direct script access allowed');

require_once __DIR__.'/issuers/IssuerPolicy.php';
require_once __DIR__.'/issuers/IssuerStorage.php';
require_once __DIR__.'/issuers/IssuerTransaction.php';
require_once __DIR__.'/issuers/IssuerResourceAccess.php';

/**
 * The original project pin is a per-staff presentation preference.  It is not
 * the same thing as following a project.  A small append-only audit ledger is
 * used here so the preference has a durable revision and idempotency proof
 * without changing historical migrations or reusing the follower table.
 */
final class NativeCompanyProjectPins
{
    public function __construct(private $db, private string $prefix)
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$prefix))throw new InvalidArgumentException('Invalid table prefix');
    }

    private function rows(string $sql,array $args=[]):array
    { return IssuerStorage::query($this->db,$sql,$args)->result_array(); }

    private function authorize(int $issuer,int $actor,string $cap='view'):void
    {
        if(!(new IssuerResourceAccess($this->db,$this->prefix))->can($issuer,$actor,'projects',$cap))throw new DomainException('company_project_access_denied');
    }

    private function latest(int $issuer,int $actor,int $project):array
    {
        $row=$this->rows('SELECT id,metadata_json FROM '.$this->prefix.'issuers_audit WHERE issuer_id=? AND actor_id=? AND subject_id=? AND event=? ORDER BY id DESC LIMIT 1',[$issuer,$actor,$project,'native_project_pin_changed'])[0]??null;
        if(!$row)return['id'=>0,'pinned'=>false,'project_revision'=>null,'request_key'=>null];
        $meta=json_decode((string)$row['metadata_json'],true,16,JSON_THROW_ON_ERROR);
        if(!is_array($meta)||!array_key_exists('pinned',$meta)||!in_array($meta['pinned'],[0,1,true,false],true))throw new RuntimeException('native_project_pin_corrupt');
        return['id'=>(int)$row['id'],'pinned'=>(bool)$meta['pinned'],'project_revision'=>$meta['project_revision']??null,'request_key'=>$meta['request_key']??null];
    }

    public function state(int $issuer,int $actor,int $project):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$project=IssuerPolicy::id($project);$this->authorize($issuer,$actor);
        if(!$this->rows('SELECT id FROM '.$this->prefix.'company_projects WHERE issuer_id=? AND id=?',[$issuer,$project]))throw new DomainException('company_project_access_denied');
        $state=$this->latest($issuer,$actor,$project);return['project_id'=>$project,'pinned'=>$state['pinned'],'revision'=>$state['id']];
    }

    public function toggle(int $issuer,int $actor,int $project,$projectRevision,$pinRevision,$pinned,string $requestKey):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$project=IssuerPolicy::id($project);$this->authorize($issuer,$actor);
        if(!is_string($requestKey)||!preg_match('/^[a-f0-9]{64}$/D',$requestKey))throw new DomainException('native_parity_invalid');
        if((!is_int($projectRevision)&&!is_string($projectRevision))||!preg_match('/^(0|[1-9][0-9]{0,9})$/D',(string)$projectRevision))throw new DomainException('company_project_stale');
        if((!is_int($pinRevision)&&!is_string($pinRevision))||!preg_match('/^(0|[1-9][0-9]{0,9})$/D',(string)$pinRevision))throw new DomainException('company_project_stale');
        if(!in_array($pinned,[0,1,'0','1',true,false],true))throw new DomainException('native_parity_invalid');$pinned=(bool)$pinned;
        $tables=['issuers_lock','issuers_registry','issuers_installation_identity','issuers_staff','issuers_resource_permissions','staff','issuers_audit','company_projects'];
        return IssuerTransaction::run($this->db,$this->prefix,$tables,function()use($issuer,$actor,$project,$projectRevision,$pinRevision,$pinned,$requestKey):array{
            (new IssuerResourceAccess($this->db,$this->prefix))->lockFinancialIdentity($issuer,$actor);$this->authorize($issuer,$actor);
            $parent=$this->rows('SELECT revision FROM '.$this->prefix.'company_projects WHERE issuer_id=? AND id=? FOR UPDATE',[$issuer,$project])[0]??null;if(!$parent||(int)$parent['revision']!==(int)$projectRevision)throw new DomainException('company_project_stale');
            $seen=$this->rows('SELECT id,metadata_json FROM '.$this->prefix.'issuers_audit WHERE issuer_id=? AND actor_id=? AND subject_id=? AND event=? ORDER BY id DESC LIMIT 200',[$issuer,$actor,$project,'native_project_pin_changed']);
            foreach($seen as$row){$meta=json_decode((string)$row['metadata_json'],true,16,JSON_THROW_ON_ERROR);if(($meta['request_key']??null)===$requestKey){if((bool)($meta['pinned']??false)!==$pinned||(int)($meta['project_revision']??-1)!==(int)$projectRevision)throw new DomainException('company_project_conflict');return['success'=>true,'project_id'=>$project,'pinned'=>(bool)$meta['pinned'],'revision'=>(int)$row['id'],'idempotent'=>true];}}
            $current=$this->latest($issuer,$actor,$project);if((int)$pinRevision!==$current['id'])throw new DomainException('company_project_stale');
            $meta=json_encode(['version'=>1,'request_key'=>$requestKey,'pinned'=>$pinned,'project_revision'=>(int)$projectRevision,'previous_pin_revision'=>$current['id']],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
            IssuerStorage::query($this->db,'INSERT INTO '.$this->prefix.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at,metadata_json) VALUES(?,?,?,?,?,?)',[$actor,'native_project_pin_changed',$issuer,$project,gmdate('Y-m-d H:i:s'),$meta]);$id=(int)$this->db->insert_id();$stored=$this->rows('SELECT id,metadata_json FROM '.$this->prefix.'issuers_audit WHERE id=? AND issuer_id=? AND actor_id=? AND subject_id=? AND event=?',[$id,$issuer,$actor,$project,'native_project_pin_changed']);if($id<1||count($stored)!==1||$stored[0]['metadata_json']!==$meta)throw new RuntimeException('native_project_pin_unconfirmed');
            return['success'=>true,'project_id'=>$project,'pinned'=>$pinned,'revision'=>$id,'idempotent'=>false];
        });
    }
}
