<?php
declare(strict_types=1);
require_once __DIR__.'/CompanySettingsCommitGuard.php';

/** The installation company's existing estimate counter; never an owned-company allocator. */
final class NativeCompanyPrimaryEstimateSequence
{
    public function __construct(private $db, private string $p)
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');
    }

    public function tables(): array
    {
        return ['issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions',
            'issuers_installation_identity','issuers_audit','staff','options','estimates'];
    }

    private function authorize(int $issuer, int $actor, string $capability): void
    {
        $acl=new IssuerResourceAccess($this->db,$this->p);
        if($acl->primaryId()!==$issuer || !$acl->can($issuer,$actor,'settings',$capability)) {
            throw new DomainException('native_sales_settings_denied');
        }
    }

    private static function number(string $value): int
    {
        if(!preg_match('/^[1-9][0-9]{0,9}$/D',$value) || (int)$value>2147483647) {
            throw new DomainException('native_sales_sequence_invalid');
        }
        return (int)$value;
    }

    public function readInOwnedTransaction(int $issuer, int $actor): array
    {
        IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());
        $this->authorize($issuer,$actor,'view');
        $rows=IssuerStorage::query($this->db,'SELECT value FROM '.$this->p.'options WHERE name=? FOR UPDATE',['next_estimate_number'])->result_array();
        if(count($rows)>1)throw new RuntimeException('native_sales_sequence_ambiguous');
        $next=$rows?(string)$rows[0]['value']:'1';
        $number=self::number($next);
        $maximum=IssuerStorage::query($this->db,'SELECT MAX(number) maximum FROM '.$this->p.'estimates')->row_array();
        $issued=(int)($maximum['maximum']??0);
        $this->authorize($issuer,$actor,'view');
        return ['next_number'=>$next,'maximum_issued'=>$issued,'revision'=>hash('sha256',json_encode([$issuer,'legacy_estimates',(bool)$rows,$next,$issued],JSON_THROW_ON_ERROR))];
    }

    public function saveInOwnedTransaction(int $issuer,int $actor,string $next,string $revision): array
    {
        $number=self::number($next);
        $this->authorize($issuer,$actor,'edit');
        $before=$this->readInOwnedTransaction($issuer,$actor);
        if(!preg_match('/^[a-f0-9]{64}$/D',$revision)||!hash_equals($before['revision'],$revision)) {
            throw new DomainException('native_sales_sequence_stale');
        }
        if($number<(int)$before['next_number'])throw new DomainException('native_sales_sequence_regression');
        if($number<=$before['maximum_issued'])throw new DomainException('native_sales_sequence_unemittable');
        if($next===$before['next_number'])return $before;
        $guard=new CompanySettingsCommitGuard($this->db,$this->p,$issuer,$actor,'edit');
        $guard->writeOption('update','next_estimate_number',$next);
        $guard->audit('company_settings_update');
        $after=$this->readInOwnedTransaction($issuer,$actor);
        if($after['next_number']!==$next)throw new RuntimeException('native_sales_sequence_write_unconfirmed');
        $guard->confirm();
        return $after;
    }
}
