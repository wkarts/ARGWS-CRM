<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerRegistry.php';
require_once __DIR__.'/issuers/IssuerStaffAccess.php';
require_once __DIR__.'/CompanySettingsStorage.php';
require_once __DIR__.'/CompanySettingsFormats.php';

/** Resumable, versioned drafts. Creation/settings/grants/completion commit together. */
final class CompanyOnboarding
{
    private $db;
    private string $prefix;
    private array $catalog;
    public function __construct($db,string $prefix,array $resourceCatalog)
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$prefix)){throw new InvalidArgumentException('Invalid table prefix');}
        $this->db=$db;$this->prefix=$prefix;$this->catalog=$resourceCatalog;
    }
    private function rows(string $sql,array $params=[]): array { return IssuerStorage::query($this->db,$sql,$params)->result_array(); }
    private function master($actor,bool $lock=false): int
    {
        $actor=IssuerPolicy::id($actor);
        if($lock){$this->rows("SELECT staffid FROM {$this->prefix}staff WHERE staffid=? FOR UPDATE",[$actor]);}
        if(!(new IssuerResourceAccess($this->db,$this->prefix))->isMaster($actor)){throw new DomainException('issuer_access_denied');}
        return $actor;
    }
    private function key($key): string
    {
        if(!is_string($key)||!preg_match('/^[a-f0-9]{64}$/D',$key)){throw new DomainException('issuer_wizard_invalid_draft');}
        return $key;
    }
    private function revision($revision): int
    {
        if(!(is_int($revision)&&$revision>=0)&&!(is_string($revision)&&preg_match('/^(0|[1-9][0-9]{0,8})$/D',$revision))){throw new DomainException('issuer_wizard_stale');}
        return (int)$revision;
    }
    private function transaction(callable $operation, bool $createIssuer=false)
    {
        $tables=['company_wizard_drafts','issuers_lock','issuers_registry','issuers_staff','issuers_staff_defaults',
            'issuers_audit','issuers_resource_permissions','issuers_installation_identity','staff','options'];
        if($this->db->table_exists($this->prefix.'emailtemplates')){$tables[]='emailtemplates';}
        if($createIssuer){require_once __DIR__.'/NativeCompanyTicketBootstrap.php';$plan=NativeCompanyTicketBootstrap::plan($this->db,$this->prefix);$tables=array_merge($tables,$plan['tables']);$work=$operation;$operation=function()use($work,$plan){NativeCompanyTicketBootstrap::confirmInOwnedTransaction($this->db,$this->prefix,$plan);return$work();};}
        return IssuerTransaction::run($this->db,$this->prefix,$tables,$operation);
    }
    private function row(int $actor,string $key): array
    {
        $row=$this->rows("SELECT * FROM {$this->prefix}company_wizard_drafts WHERE draft_key=? AND actor_id=?",[$key,$actor])[0]??null;
        if(!$row){throw new DomainException('issuer_wizard_invalid_draft');}
        $row['payload']=json_decode($row['payload_json'],true,16,JSON_THROW_ON_ERROR);
        if(!is_array($row['payload'])||(int)$row['step']<1||(int)$row['step']>6){throw new RuntimeException('issuer_wizard_corrupt_draft');}
        unset($row['payload_json']);return $row;
    }
    public function read($actor,$key): array { return $this->row($this->master($actor),$this->key($key)); }
    public function pending($actor): ?array
    {
        $actor=$this->master($actor);
        $row=$this->rows("SELECT draft_key FROM {$this->prefix}company_wizard_drafts WHERE actor_id=? AND issuer_id IS NULL ORDER BY created_at,draft_key LIMIT 1",[$actor])[0]??null;
        return $row?$this->row($actor,$row['draft_key']):null;
    }
    public function begin($actor,$requestKey): array
    {
        $actor=IssuerPolicy::id($actor);$key=$this->key($requestKey);
        return $this->transaction(function()use($actor,$key):array{
            $this->master($actor,true);
            if($this->rows("SELECT draft_key FROM {$this->prefix}company_wizard_drafts WHERE draft_key=?",[$key])){return $this->row($actor,$key);}
            $pending=$this->pending($actor);if($pending){return $pending;}
            $now=gmdate('Y-m-d H:i:s');
            IssuerStorage::query($this->db,"INSERT INTO {$this->prefix}company_wizard_drafts(draft_key,actor_id,revision,step,payload_json,issuer_id,created_at,updated_at) VALUES(?,?,0,1,'{}',NULL,?,?)",[$key,$actor,$now,$now]);
            return $this->row($actor,$key);
        });
    }
    private function normalize(int $step,array $input): array
    {
        if($step===1){
            if(array_diff(array_keys($input),['legal_name','tax_id','profile'])){throw new DomainException('issuer_wizard_invalid_input');}
            $name=trim(is_string($input['legal_name']??null)?$input['legal_name']:'');
            if($name===''||strlen($name)>191||preg_match('/[\x00-\x1f]/',$name)){throw new DomainException('issuer_invalid_name');}
            $profile=$input['profile']??[];
            if(!is_array($profile)||array_diff(array_keys($profile),CompanySettingsIdentity::PROFILE_OPTIONS)){throw new DomainException('company_settings_invalid_identity_field');}
            foreach($profile as $value){if(!is_string($value)||strlen($value)>2000||preg_match('/[\x00-\x1f]/',$value)){throw new DomainException('company_settings_invalid_identity_field');}}
            return ['legal_name'=>$name,'tax_id'=>IssuerPolicy::taxId($input['tax_id']??null),'profile'=>$profile];
        }
        if($step===2){CompanySettingsFormats::validate($input);return $input;}
        if($step===3){
            if(array_diff(array_keys($input),['staff'])||!is_array($input['staff']??null)||count($input['staff'])>100){throw new DomainException('issuer_wizard_invalid_staff');}
            $result=[];$access=new IssuerResourceAccess($this->db,$this->prefix);
            foreach($input['staff'] as $staff=>$assignment){
                $staff=IssuerPolicy::id($staff);
                if(!is_array($assignment)||array_diff(array_keys($assignment),['default','resources'])||!in_array($assignment['default']??null,['0','1'],true)) {throw new DomainException('issuer_wizard_invalid_staff');}
                if(!$this->rows("SELECT staffid FROM {$this->prefix}staff WHERE staffid=? AND active=1 FOR UPDATE",[$staff])||$access->isInstallationMaster($staff)){throw new DomainException('issuer_wizard_invalid_staff');}
                $resources=$assignment['resources']??[];
                if(!is_array($resources)||count($resources)>100){throw new DomainException('issuer_invalid_permission');}
                foreach($resources as $feature=>&$caps){
                    IssuerResourceAccess::key($feature);
                    if(!isset($this->catalog[$feature])||!is_array($caps)||count($caps)>100){throw new DomainException('issuer_invalid_permission');}
                    foreach($caps as $cap){if(!is_string($cap)||!in_array($cap,$this->catalog[$feature],true)){throw new DomainException('issuer_invalid_permission');}}
                    $caps=array_values(array_unique($caps));sort($caps);
                }unset($caps);ksort($resources);
                $result[$staff]=['default'=>$assignment['default'],'resources'=>$resources];
            }
            ksort($result,SORT_NUMERIC);return ['staff'=>$result];
        }
        if($step===4){
            // No automatic sharing or financial rollout is enabled by onboarding preferences.
            if($input!==['client_visibility'=>'private']){throw new DomainException('issuer_wizard_invalid_privacy');}
            return $input;
        }
        throw new DomainException('issuer_wizard_invalid_step');
    }
    public function advance($actor,$key,$revision,$step,array $input): array
    {
        $actor=IssuerPolicy::id($actor);$key=$this->key($key);$revision=$this->revision($revision);$step=IssuerPolicy::id($step);
        return $this->transaction(function()use($actor,$key,$revision,$step,$input):array{
            $this->master($actor,true);$draft=$this->row($actor,$key);
            if($draft['issuer_id']!==null||(int)$draft['revision']!==$revision){throw new DomainException('issuer_wizard_stale');}
            if($step>4||$step>(int)$draft['step']){throw new DomainException('issuer_wizard_invalid_step');}
            $payload=$draft['payload'];$payload[$step]=$this->normalize($step,$input);
            $json=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
            if(strlen($json)>65536){throw new DomainException('issuer_wizard_invalid_input');}
            IssuerStorage::query($this->db,"UPDATE {$this->prefix}company_wizard_drafts SET payload_json=?,step=?,revision=revision+1,updated_at=? WHERE draft_key=? AND revision=?",[$json,$step+1,gmdate('Y-m-d H:i:s'),$key,$revision]);
            $saved=$this->row($actor,$key);
            if((int)$saved['revision']!==$revision+1||$saved['payload']!==$payload){throw new RuntimeException('issuer_wizard_write_unconfirmed');}
            return $saved;
        });
    }
    public function complete($actor,$key,$revision): int
    {
        $actor=IssuerPolicy::id($actor);$key=$this->key($key);$revision=$this->revision($revision);
        return $this->transaction(function()use($actor,$key,$revision):int{
            $this->master($actor,true);$draft=$this->row($actor,$key);
            if($draft['issuer_id']!==null){return IssuerPolicy::id($draft['issuer_id']);}
            if((int)$draft['revision']!==$revision){throw new DomainException('issuer_wizard_stale');}
            if((int)$draft['step']!==5){throw new DomainException('issuer_wizard_invalid_step');}
            $payload=[];for($step=1;$step<=4;$step++){$payload[$step]=$this->normalize($step,$draft['payload'][$step]??[]);}
            $issuer=(new IssuerRegistry($this->db,$this->prefix))->createInOwnedTransaction($actor,$payload[1]);
            (new CompanySettingsStorage($this->db,$this->prefix))->updateBatchInOwnedTransaction($issuer,$actor,$payload[2]);
            $staffAccess=new IssuerStaffAccess($this->db,$this->prefix,$actor,$this->catalog);
            foreach($payload[3]['staff'] as $staff=>$assignment){$staffAccess->addCompanyInOwnedTransaction($staff,$issuer,$assignment['resources'],$assignment['default']==='1');}
            IssuerStorage::query($this->db,"UPDATE {$this->prefix}company_wizard_drafts SET issuer_id=?,step=6,revision=revision+1,updated_at=? WHERE draft_key=? AND issuer_id IS NULL",[$issuer,gmdate('Y-m-d H:i:s'),$key]);
            if((int)$this->row($actor,$key)['issuer_id']!==$issuer){throw new RuntimeException('issuer_wizard_write_unconfirmed');}
            IssuerStorage::query($this->db,"INSERT INTO {$this->prefix}issuers_audit(actor_id,event,issuer_id,subject_id,created_at) VALUES(?,'company_onboarding_completed',?,NULL,?)",[$actor,$issuer,gmdate('Y-m-d H:i:s')]);
            return $issuer;
        },true);
    }
    public function staffCatalog($actor): array
    {
        $this->master($actor);$access=new IssuerResourceAccess($this->db,$this->prefix);
        return array_values(array_filter($this->rows("SELECT staffid,firstname,lastname FROM {$this->prefix}staff WHERE active=1 ORDER BY firstname,lastname,staffid"),static fn($row)=>!$access->isInstallationMaster($row['staffid'])));
    }
}
