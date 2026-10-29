<?php

declare(strict_types=1);
require_once __DIR__.'/CompanySettingsStorage.php';
require_once __DIR__.'/NativeCompanySalesSequencePreferences.php';

/**
 * Validated, issuer-owned storage for the fields on the original sales forms.
 * HTTP groups are admitted only after every control has an issuer-owned consumer.
 */
final class NativeCompanySalesSettings
{
    private const DEFAULTS = [
        'estimates' => [
            'estimate_prefix'=>'ORC-', 'next_estimate_number'=>'1', 'estimate_due_after'=>'7',
            'delete_only_on_last_estimate'=>'1', 'estimate_number_decrement_on_delete'=>'1',
            'allow_staff_view_estimates_assigned'=>'1', 'view_estimate_only_logged_in'=>'0',
            'show_sale_agent_on_estimates'=>'1', 'show_project_on_estimate'=>'1',
            'estimate_auto_convert_to_invoice_on_client_accept'=>'1',
            'exclude_estimate_from_client_area_with_draft_status'=>'1', 'estimate_number_format'=>'1',
            'estimates_pipeline_limit'=>'50', 'default_estimates_pipeline_sort'=>'pipeline_order',
            'default_estimates_pipeline_sort_type'=>'asc', 'predefined_clientnote_estimate'=>'',
            'predefined_terms_estimate'=>'',
        ],
        'proposals' => [
            'proposal_number_prefix'=>'PROP-', 'proposal_due_after'=>'7', 'proposals_pipeline_limit'=>'50',
            'default_proposals_pipeline_sort'=>'pipeline_order', 'default_proposals_pipeline_sort_type'=>'asc',
            'show_project_on_proposal'=>'1', 'exclude_proposal_from_client_area_with_draft_status'=>'1',
            'proposal_auto_convert_leads_to_client_on_client_accept'=>'0',
            'proposal_auto_convert_to_invoice_on_client_accept'=>'0', 'allow_staff_view_proposals_assigned'=>'1',
            'proposal_info_format'=>"{proposal_to}<br />\n{address}<br />\n{city} {state}<br />\n{country_code} {zip_code}<br />\n{phone}<br />\n{email}",
            'predefined_terms_proposal'=>'',
        ],
    ];

    public function __construct(private $db, private string $prefix)
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/D', $prefix)) { throw new InvalidArgumentException('Invalid prefix'); }
    }

    public static function defaults(string $group): array
    {
        return self::DEFAULTS[$group] ?? throw new DomainException('native_sales_settings_invalid');
    }
    public static function fields(string $group): array { return array_keys(self::defaults($group)); }

    /** Complete wire format, except that storage never stores the sequence itself. */
    public static function validate(string $group, array $values, bool $withSequence=true): void
    {
        $allowed = self::fields($group);
        if (!$withSequence) { $allowed = array_values(array_diff($allowed, ['next_estimate_number'])); }
        if (array_diff(array_keys($values), $allowed) || array_diff($allowed, array_keys($values))) {
            throw new DomainException('native_sales_settings_invalid');
        }
        foreach ($values as $key=>$value) {
            if (!is_string($value) || strlen($value)>10000 || preg_match('//u', $value)!==1
                || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $value)) {
                throw new DomainException('native_sales_settings_invalid');
            }
            if (in_array($key, ['predefined_clientnote_estimate','predefined_terms_estimate',
                'predefined_terms_proposal','proposal_info_format'], true)) { continue; }
            if (in_array($key, ['estimate_prefix','proposal_number_prefix'], true)) {
                if (strlen($value)>50 || preg_match('/[\r\n]/', $value)) {
                    throw new DomainException('native_sales_settings_invalid');
                }
                continue;
            }
            if ($key === 'next_estimate_number') {
                if (!preg_match('/^[1-9][0-9]{0,19}$/D', $value)
                    || (strlen($value) === 20 && strcmp($value, '18446744073709551614') > 0)) {
                    throw new DomainException('native_sales_settings_invalid');
                }
                continue;
            }
            if (in_array($key, ['estimate_due_after','proposal_due_after','estimates_pipeline_limit','proposals_pipeline_limit'], true)) {
                if (!preg_match('/^(?:0|[1-9][0-9]{0,3})$/D', $value)) {
                    throw new DomainException('native_sales_settings_invalid');
                }
                continue;
            }
            if ($key === 'estimate_number_format') {
                if (!in_array($value, ['1','2','3','4'], true)) { throw new DomainException('native_sales_settings_invalid'); }
                continue;
            }
            if (in_array($key, ['default_estimates_pipeline_sort_type','default_proposals_pipeline_sort_type'], true)) {
                if (!in_array($value, ['asc','desc'], true)) { throw new DomainException('native_sales_settings_invalid'); }
                continue;
            }
            if ($key === 'default_estimates_pipeline_sort') {
                if (!in_array($value, ['datecreated','date','pipeline_order','expirydate'], true)) { throw new DomainException('native_sales_settings_invalid'); }
                continue;
            }
            if ($key === 'default_proposals_pipeline_sort') {
                if (!in_array($value, ['datecreated','date','pipeline_order','open_till'], true)) { throw new DomainException('native_sales_settings_invalid'); }
                continue;
            }
            if (!in_array($value, ['0','1'], true)) { throw new DomainException('native_sales_settings_invalid'); }
        }
    }

    private function authorize(int $issuer, int $actor, string $capability): void
    {
        $acl = new IssuerResourceAccess($this->db, $this->prefix);
        // Primary sales options remain on the historical Settings storage.
        if ($acl->primaryId() !== null && $issuer === $acl->primaryId()) {
            throw new DomainException('native_sales_settings_denied');
        }
        if (!$acl->can($issuer,$actor,'settings',$capability)) {
            throw new DomainException('native_sales_settings_denied');
        }
    }
    private function tables(string $group): array
    {
        self::defaults($group);
        return (new NativeCompanySalesSequencePreferences($this->db, $this->prefix))->tables();
    }
    private function capture(int $issuer, int $actor, string $group): array
    {
        $this->authorize($issuer,$actor,'view');
        $store = new CompanySettingsStorage($this->db,$this->prefix);
        $values = [];
        foreach (self::defaults($group) as $key=>$default) {
            $values[$key] = $store->exists($issuer,$key) ? $store->get($issuer,$key) : $default;
        }
        $sequence = null;
        if ($group==='estimates') {
            $sequence = (new NativeCompanySalesSequencePreferences($this->db,$this->prefix))
                ->readInOwnedTransaction($issuer,$actor,'estimates');
            $values['next_estimate_number'] = $sequence['next_number'];
        }
        self::validate($group,$values);
        $this->authorize($issuer,$actor,'view');
        return ['values'=>$values,'sequence'=>$sequence,
            'revision'=>hash('sha256',json_encode([$issuer,$group,$values,$sequence],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR))];
    }
    public function page(int $issuer, int $actor, string $group): array
    {
        $this->authorize($issuer,$actor,'view');
        return IssuerTransaction::run($this->db,$this->prefix,$this->tables($group),fn()=>$this->capture($issuer,$actor,$group));
    }
    public function save(int $issuer, int $actor, string $group, string $revision, array $values): array
    {
        $this->authorize($issuer,$actor,'edit');
        self::validate($group,$values);
        if (!preg_match('/^[a-f0-9]{64}$/D',$revision)) { throw new DomainException('native_sales_settings_invalid'); }
        return IssuerTransaction::run($this->db,$this->prefix,$this->tables($group),function()use($issuer,$actor,$group,$revision,$values){
            $this->authorize($issuer,$actor,'edit');
            (new IssuerResourceAccess($this->db,$this->prefix))->lockFinancialIdentity($issuer,$actor);
            $current=$this->capture($issuer,$actor,$group);
            if (!hash_equals($current['revision'],$revision)) { throw new DomainException('native_sales_settings_stale'); }
            $stored=$values;
            if ($group==='estimates') {
                (new NativeCompanySalesSequencePreferences($this->db,$this->prefix))->saveInOwnedTransaction(
                    $issuer,$actor,'estimates',$values['next_estimate_number'],$current['sequence']['revision']);
                unset($stored['next_estimate_number']);
            }
            (new CompanySettingsStorage($this->db,$this->prefix))->updateNativeSettingsInOwnedTransaction($issuer,$actor,$group,$stored);
            $this->authorize($issuer,$actor,'edit');
            return $this->capture($issuer,$actor,$group);
        });
    }
}
