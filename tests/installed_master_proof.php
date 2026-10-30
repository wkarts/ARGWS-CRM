<?php
/** Execute the exact inline fixture gates without MySQL or application bootstrap. */
declare(strict_types=1);
$files = ['hr_http_fixture','hr_tokens_http_fixture','hr_cli_http_fixture','leads_http_fixture','purchases_http_fixture','task_recurring_http_fixture'];
$original = getenv('ISSUERS_BROWSER_DB_NAME');
$checks = 0;
function runMasterProof(string $code, array $identity, array $rows, string $database): void {
    putenv('ISSUERS_BROWSER_DB_NAME='.$database);
    $db = new class($rows) {
        public function __construct(private array $rows) {}
        public function query(string $sql): object {
            if ($sql !== "SELECT actor_id,subject_id,issuer_id,metadata_json FROM tblissuers_audit WHERE event='original_admin_attested'") throw new LogicException('Unexpected SQL in read-only proof');
            return new class($this->rows) {
                public function __construct(private array $rows) {}
                public function result_array(): array { return $this->rows; }
            };
        }
    };
    eval($code); // Extracted only from these six versioned, local test fixtures.
}
try {
    foreach ($files as $file) {
        $source = file_get_contents(__DIR__.'/companies/'.$file.'.php');
        if (!preg_match('~// BEGIN exact upgraded-fixture owner proof.*?\n(.*?)// END exact upgraded-fixture owner proof\.~s', $source, $match)) throw new RuntimeException('Missing fixture gate: '.$file);
        $identity = ['master_staff_id'=>17,'primary_issuer_id'=>1,'source'=>'owner_attestation'];
        $metadata = ['proof'=>'installation_owner_attestation','confirmation_reference'=>'test:explicit-owner-confirmation','primary_issuer_id'=>1];
        $audit = ['actor_id'=>0,'subject_id'=>17,'issuer_id'=>null,'metadata_json'=>json_encode($metadata)];
        runMasterProof($match[1],$identity,[$audit],'argws_upgrade_ci'); ++$checks;
        $cases = [[array_replace($identity,['primary_issuer_id'=>2]),[array_replace($audit,['metadata_json'=>json_encode(array_replace($metadata,['primary_issuer_id'=>2]))])],'argws_upgrade_ci'],[$identity,[array_replace($audit,['actor_id'=>null])],'argws_upgrade_ci'],[$identity,[],'argws_upgrade_ci'],[$identity,[$audit,$audit],'argws_upgrade_ci'],[$identity,[$audit],'argws_fresh_ci'],[array_replace($identity,['master_staff_id'=>42]),[$audit],'argws_upgrade_ci'],[array_replace($identity,['primary_issuer_id'=>2]),[$audit],'argws_upgrade_ci']];
        foreach (['actor_id'=>17,'subject_id'=>42,'issuer_id'=>1,'metadata_json'=>'{}'] as $field=>$value) $cases[]=[$identity,[array_replace($audit,[$field=>$value])],'argws_upgrade_ci'];
        foreach (['proof'=>'installer','confirmation_reference'=>'other','primary_issuer_id'=>2] as $field=>$value) $cases[]=[$identity,[array_replace($audit,['metadata_json'=>json_encode(array_replace($metadata,[$field=>$value]))])],'argws_upgrade_ci'];
        foreach ($cases as $args) {
            $rejected=false;
            try { runMasterProof($match[1],...$args); } catch (RuntimeException $e) { if($e->getMessage()!=='Exact upgraded fixture owner attestation and audit required')throw $e; $rejected=true; }
            if(!$rejected)throw new RuntimeException('Invalid attestation accepted: '.$file);
            ++$checks;
        }
    }
    echo "Installed master fixture audit: {$checks} checks passed.\n";
} finally {
    putenv($original === false ? 'ISSUERS_BROWSER_DB_NAME' : 'ISSUERS_BROWSER_DB_NAME='.$original);
}
