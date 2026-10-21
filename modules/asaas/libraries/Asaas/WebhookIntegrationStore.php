<?php
declare(strict_types=1);
namespace Asaas;
require_once __DIR__ . "/WebhookTransactionGuard.php";
require_once __DIR__ . '/WebhookSecurity.php';
require_once __DIR__ . '/WebhookTokenCipher.php';
require_once __DIR__ . '/WebhookTokenSchema.php';
require_once __DIR__ . '/ModuleReadiness.php';
final class WebhookIntegrationStore
{
    private $db;
    private string $table;
    private WebhookTokenCipher $cipher;
    public function __construct($db, ?WebhookTokenCipher $cipher = null) { $this->db=$db; $this->table=db_prefix().'asaas_webhook_integrations'; $this->cipher=$cipher ?? new WebhookTokenCipher(); }
    private function requireAdmin(): void
    {
        if (!is_admin() || (class_exists('CompanySettingsRuntime', false) && \CompanySettingsRuntime::active() && !\CompanySettingsRuntime::isPrimary())) {
            throw new \DomainException('webhook_admin_access_denied');
        }
    }
    public function ready(): bool
    {
        return $this->db->table_exists($this->table)
            && $this->db->table_exists(db_prefix().'asaas_settlement_outbox')
            && $this->db->table_exists(db_prefix().'asaas_webhook_admin_audit')
            && $this->db->field_exists('scope_key', db_prefix().'asaas_webhook_events');
    }
    public function byPublicId(string $publicId): ?array
    {
        if (!$this->ready() || !preg_match('/^[a-f0-9]{32}$/D', $publicId)) return null;
        return $this->db->get_where($this->table,['public_id'=>$publicId])->row_array() ?: null;
    }
    public function byId(int $id): ?array
    {
        if (!$this->ready() || $id<1) return null;
        return $this->db->get_where($this->table,['id'=>$id])->row_array() ?: null;
    }
    public function createVerified(string $label, array $scope): array
    {
         $this->requireAdmin();
        ModuleReadiness::require154($this->db, db_prefix());
        if (!$this->ready()) throw new \RuntimeException('webhook_migration_required');
        WebhookTokenSchema::assertReady($this->db, $this->table);
        if (trim($label)==='' || strlen($label)>100
            || ($scope['issuer_id']??null)!==null || ($scope['provider']??'')!=='asaas'
            || !in_array($scope['environment']??'', ['sandbox','production'], true)
            || !preg_match('/^[a-zA-Z0-9_-]{1,128}$/D', $scope['account_id']??'')) {
            throw new \InvalidArgumentException('invalid_webhook_integration');
        }
        $token=bin2hex(random_bytes(32));
        $row=['public_id'=>bin2hex(random_bytes(16)), 'label'=>trim($label), 'issuer_id'=>null,
            'provider'=>'asaas','account_id'=>$scope['account_id'],'environment'=>$scope['environment'],
            'token_hash'=>hash('sha256',$token),'enabled'=>1,'created_by'=>get_staff_user_id()?:null,
            'created_at'=>date('Y-m-d H:i:s'),'rotated_at'=>null,'last_valid_at'=>null,
            'last_rejected_at'=>null,'rejection_count'=>0];
        $row['token_cipher']=$this->cipher->seal($token,$row,'legacy');
        return $this->adminTransaction(function () use ($row,$token) {
            if (!$this->db->insert($this->table,$row)) throw new \RuntimeException('webhook_integration_write_failed');
            $saved=$this->byPublicId($row['public_id']);
            if (!$saved || empty($saved['id']) || ($saved['token_cipher']??null)!==$row['token_cipher'] || !hash_equals($saved['token_hash'],hash('sha256',$token))) throw new \RuntimeException('webhook_integration_unconfirmed');
            $this->audit((int)$saved['id'],'created');
            return $this->sanitize($saved)+['one_time_token'=>$token];
        });
    }
    public function rotateToken(int $id): array
    {
        $this->requireAdmin();
        ModuleReadiness::require154($this->db, db_prefix());
        WebhookTokenSchema::assertReady($this->db, $this->table);
        $token=bin2hex(random_bytes(32));
        return $this->adminTransaction(function () use ($id,$token) {
            $row=$this->lockedAdminRow($id);
            $cipher=$this->cipher->seal($token,$row,'legacy');
            if (!$this->db->where('id',$id)->update($this->table,['token_hash'=>hash('sha256',$token),'token_cipher'=>$cipher,'rotated_at'=>date('Y-m-d H:i:s'),'rotated_by'=>get_staff_user_id()?:null])
                || $this->db->affected_rows()!==1) throw new \RuntimeException('webhook_rotation_failed');
            $this->audit($id,'token_rotated');
            return ['integration_id'=>$id,'one_time_token'=>$token];
        });
    }
    private function lockedAdminRow(int $id, bool $requireEnabled = true): array
    {
        $this->requireAdmin();
        $row=$this->db->query('SELECT * FROM '.$this->db->protect_identifiers($this->table,true).' WHERE id=? FOR UPDATE',[$id])->row_array();
        if (!$row || $row['issuer_id']!==null || ($row['provider']??'')!=='asaas' || ($requireEnabled && (int)$row['enabled']!==1)) throw new \DomainException('webhook_admin_access_denied');
        return $row;
    }
    public function revealToken(int $id): array
    {
        $this->requireAdmin();
        ModuleReadiness::require154($this->db, db_prefix());
        return $this->adminTransaction(function () use ($id) {
            $row=$this->lockedAdminRow($id);
            $token=$this->cipher->open($row['token_cipher']??null,$row['token_hash'],$row,'legacy');
            $this->audit($id,'token_revealed');
            return ['integration_id'=>$id,'one_time_token'=>$token,'revealed'=>true];
        });
    }
    public function disable(int $id): void
    {
        $this->requireAdmin();
        $this->adminTransaction(function () use ($id) {
            $row=$this->lockedAdminRow($id,false);
            if ((int)$row['enabled']===0) return; // Replay is a no-op, without duplicate audit.
            if ((int)$row['enabled']!==1) throw new \RuntimeException('webhook_disable_failed');
            if (!$this->db->where('id',$id)->update($this->table,['enabled'=>0,'disabled_at'=>date('Y-m-d H:i:s'),'disabled_by'=>get_staff_user_id()?:null]) || $this->db->affected_rows()!==1) throw new \RuntimeException('webhook_disable_failed');
            $this->audit($id,'disabled');
        });
    }
    public function audit(int $integrationId,string $action,?int $eventRowId=null): void
    {
        if (!in_array($action,['created','token_rotated','token_revealed','disabled','event_reviewed'],true)
            || !get_staff_user_id() || !$this->db->insert(db_prefix().'asaas_webhook_admin_audit',[
                'integration_id'=>$integrationId,'event_row_id'=>$eventRowId,'actor_id'=>(int)get_staff_user_id(),
                'action'=>$action,'created_at'=>date('Y-m-d H:i:s'),
            ])) throw new \RuntimeException('webhook_admin_audit_failed');
    }
    private function adminTransaction(callable $fn)
    {
        $debug=$this->db->db_debug;$this->db->db_debug=false;
        $saveQueries=property_exists($this->db,'save_queries')?$this->db->save_queries:null;
        if ($saveQueries!==null) $this->db->save_queries=false;
        $owned=false;
        try {
            WebhookTransactionGuard::prepare($this->db);
            foreach ([$this->table,db_prefix().'asaas_webhook_admin_audit'] as $table) {
                $result=$this->db->query('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',[$table]);
                if (!$result || strtoupper((string)($result->row_array()['ENGINE']??''))!=='INNODB') throw new \RuntimeException('transactional_audit_required');
            }
            WebhookTransactionGuard::prepare($this->db);
            if (!$this->db->trans_begin()) throw new \RuntimeException('admin_transaction_unavailable');
            $owned=true;
            $result=$fn();
            if (!$this->db->trans_status() || !$this->db->trans_commit()) throw new \RuntimeException('admin_write_unconfirmed');
            $owned=false;
            return $result;
        } catch (\Throwable $error) { if ($owned) $this->db->trans_rollback();throw $error; }
        finally { $this->db->db_debug=$debug; if ($saveQueries!==null) $this->db->save_queries=$saveQueries; }
    }
    public function listForAdmin(): array
    {
        $this->requireAdmin();
        if (!$this->ready()) return [];
        return array_map([$this,'sanitize'],$this->db->where('issuer_id',null)->where('provider','asaas')->order_by('id','DESC')->limit(250)->get($this->table)->result_array());
    }
    public function recentEvents(int $id,int $limit=25): array
    {
        if (!$this->ready()) return [];
        $rows=$this->db->select('id,event_id,event_type,process_status,verified_status,received_at,processed_at,attempt_count,error_message,reviewed_by,reviewed_at')
            ->where('integration_id',$id)->order_by('id','DESC')->limit(max(1,min(100,$limit)))
            ->get(db_prefix().'asaas_webhook_events')->result_array();
        return array_map(static fn($r)=>array_intersect_key($r,array_flip(['id','event_id','event_type','process_status','verified_status','received_at','processed_at','attempt_count','error_message','reviewed_by','reviewed_at'])),$rows);
    }
    public function reviewQueue(int $limit=50): array
    {
        if (!$this->ready()) return [];
        $rows=$this->db->query('SELECT id,event_id,event_type,asaas_payment_id,process_status,received_at,integration_id FROM '
            .$this->db->protect_identifiers(db_prefix().'asaas_webhook_events',true)
            ." WHERE process_status IN ('review_required','failed') ORDER BY id DESC LIMIT ".max(1,min($limit,100)))->result_array();
        return array_map(static fn($r)=>array_intersect_key($r,array_flip(['id','event_id','event_type','asaas_payment_id','process_status','received_at','integration_id'])),$rows);
    }
    public function pendingAutomations(int $limit=50): array
    {
        if (!$this->ready() || !$this->db->table_exists(db_prefix().'asaas_settlement_outbox')) return [];
        return $this->db->where('state','pending')->order_by('id','DESC')->limit(max(1,min($limit,100)))
            ->get(db_prefix().'asaas_settlement_outbox')->result_array();
    }
    public function recordAccepted(int $id): void
    {
        if (!$this->db->where('id',$id)->update($this->table,['last_valid_at'=>date('Y-m-d H:i:s')])) throw new \RuntimeException('webhook_audit_failed');
    }
    public function recordRejected(int $id): void
    {
        // One fixed-size counter; unauthenticated requests never create payload rows.
        $this->db->query('UPDATE '.$this->db->protect_identifiers($this->table,true)
            .' SET rejection_count = LEAST(rejection_count + 1, 2147483647), last_rejected_at = ? WHERE id = ?', [date('Y-m-d H:i:s'),$id]);
    }
    private function sanitize(array $row): array { $row['token_recoverable']=is_string($row['token_cipher']??null)&&$row['token_cipher']!==''; unset($row['token_hash'],$row['token_cipher']); return $row; }
}
