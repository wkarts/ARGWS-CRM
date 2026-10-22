<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerStaffAccess.php';

/** One native profile/ACL unit. Unreviewed mutable pre-hooks fail before any write. */
final class CompanyNativeStaffTransaction
{
    private static array $active = [];
    public const TABLES = ['staff','staff_permissions','staff_departments','customfields','customfieldsvalues','announcements','dismissed_announcements','departments','roles','issuers_registry','issuers_staff','issuers_staff_defaults','issuers_lock','issuers_audit','issuers_resource_permissions','issuers_installation_identity'];

    public static function active($db): bool { return isset(self::$active[spl_object_id($db)]); }
    private static function rows($db, string $sql, array $params): array { return IssuerStorage::query($db, $sql, $params)->result_array(); }
    public static function normalized(array $rows): array
    {
        return array_map(static function ($row) {
            // Native hourly_rate is DECIMAL(15,2). Compare the exact decimal value,
            // without floats or treating a rounded/truncated value as confirmed.
            if (isset($row['hourly_rate']) && preg_match('/^(-?)([0-9]+)(?:\.([0-9]*))?$/D', (string)$row['hourly_rate'], $parts)) {
                $whole=ltrim($parts[2],'0');$whole=$whole===''?'0':$whole;$fraction=rtrim($parts[3]??'','0');
                $row['hourly_rate']=($parts[1]==='-'&&($whole!=='0'||$fraction!=='')?'-':'').$whole.($fraction!==''?'.'.$fraction:'');
            }
            ksort($row); return array_map(static fn($v) => $v === null ? null : (string)$v, $row);
        }, $rows);
    }
    /** Expected result is supplied by the writer, never inferred from affected_rows(). */
    public static function expect($db, string $key, string $sql, array $params, array $expected): void
    {
        if (!self::active($db)) { return; }
        $expected = self::normalized($expected);
        if (self::normalized(self::rows($db, $sql, $params)) !== $expected) { throw new RuntimeException('issuer_staff_write_unconfirmed'); }
        self::$active[spl_object_id($db)]['witnesses'][$key] = [$sql, $params, $expected];
    }
    public static function profile($db, string $prefix, int $id, array $values): void
    {
        if (!self::active($db)) { return; }
        $row = self::rows($db, 'SELECT * FROM '.$prefix.'staff WHERE staffid=?', [$id])[0] ?? null;
        if (!$row) { throw new RuntimeException('issuer_staff_write_unconfirmed'); }
        $key = 'profile:'.$id;
        $previous = self::$active[spl_object_id($db)]['profiles'][$id] ?? $row;
        $expected = array_replace($previous, $values);
        if (array_key_exists('staff_email_identity', $row) && array_key_exists('email', $values)) {
            $expected['staff_email_identity'] = StaffEmailIdentity::normalize($values['email']);
        }
        self::expect($db, $key, 'SELECT * FROM '.$prefix.'staff WHERE staffid=?', [$id], [$expected]);
        self::$active[spl_object_id($db)]['profiles'][$id] = $expected;
    }
    public static function afterCommit($db, callable $effect): void
    {
        if (self::active($db)) { self::$active[spl_object_id($db)]['effects'][] = $effect; }
        else { $effect(); }
    }
    private static function hooks(string $mode): void
    {
        $hooks = hooks();
        $tag = $mode === 'add' ? 'before_create_staff_member' : ($mode === 'update' ? 'before_update_staff_member' : 'before_staff_update_profile');
        // No installed repository callback subscribes to these three pre-hooks. A third-party
        // callback must declare its transactional participants before being admitted here.
        if (method_exists($hooks, 'has_filter') && ($hooks->has_filter($tag) || $hooks->has_filter('all'))) {
            throw new DomainException('issuer_staff_extension_unreviewed');
        }
    }
    public static function run($db, string $prefix, int $actor, string $mode, $staff, array $prepared, array $resources, callable $writer): array
    {
        if (!in_array($mode, ['add','update','update_profile'], true)) { throw new InvalidArgumentException('Invalid staff writer'); }
        self::hooks($mode);
        $access = new IssuerStaffAccess($db, $prefix, $actor, $resources);
        $effects = [];
        $result = IssuerTransaction::run($db, $prefix, self::TABLES, function () use ($db, $prefix, $actor, $mode, $staff, $prepared, $access, $writer, &$effects): array {
            self::rows($db,'SELECT staffid FROM '.$prefix.'staff WHERE staffid=? FOR UPDATE',[$actor]);
            $personal = !empty($prepared['personal']);
            if ($personal !== ($mode === 'update_profile') || ($personal && (int)$staff !== $actor)) { throw new DomainException('issuer_access_denied'); }
            if ($personal) { $access->validateOwnDefault($prepared['default']); }
            else { $access->prepare($mode === 'add' ? null : $staff, $prepared['memberships'], $prepared['default'], self::grants($prepared['resources'])); }
            $token = spl_object_id($db);
            self::$active[$token] = ['witnesses'=>[], 'profiles'=>[], 'effects'=>[]];
            try {
                if ($mode !== 'add') {
                    $id = IssuerPolicy::id($staff);
                    $row = self::rows($db, 'SELECT * FROM '.$prefix.'staff WHERE staffid=? FOR UPDATE', [$id])[0] ?? null;
                    if (!$row) { throw new DomainException('issuer_access_denied'); }
                    self::$active[$token]['profiles'][$id] = $row;
                }
                $native = $writer();
                if (is_array($native)) { throw new DomainException(isset($native['cant_remove_installation_master']) ? 'issuer_master_protected' : 'issuer_staff_native_rejected'); }
                $id = $mode === 'add' ? IssuerPolicy::id($native) : IssuerPolicy::id($staff);
                if ($personal) { $access->saveOwnDefaultInOwnedTransaction($prepared['default']); }
                else { $access->saveInOwnedTransaction($id, $prepared['memberships'], $prepared['default'], self::grants($prepared['resources'])); }
                if ($personal) { $access->validateOwnDefault($prepared['default']); }
                else { $access->prepare($id, $prepared['memberships'], $prepared['default'], self::grants($prepared['resources'])); }
                foreach (self::$active[$token]['witnesses'] as [$sql, $params, $expected]) {
                    if (self::normalized(self::rows($db, $sql, $params)) !== $expected) { throw new RuntimeException('issuer_staff_write_unconfirmed'); }
                }
                $effects = self::$active[$token]['effects'];
                return ['staff_id'=>$id,'native_result'=>$native];
            } finally { unset(self::$active[$token]); }
        });
        $failed = 0;
        foreach ($effects as $effect) { try { if($effect()===false){$failed++;} } catch (Throwable $error) { $failed++; } }
        return $result + ['post_commit_failures'=>$failed];
    }
    private static function grants(array $resources): array
    {
        return array_map(static fn($features) => array_map(static fn($caps) => array_keys($caps), $features), $resources);
    }
}
