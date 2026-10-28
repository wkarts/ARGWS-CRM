<?php

declare(strict_types=1);

require_once __DIR__.'/CompanyStaff.php';

/** Company-membership operations exposed by the original Staff controller. */
final class NativeCompanyStaffMembershipEndpoints
{
    private static function context($ci): array
    {
        if (!CompanyRequestScope::nativeFor($ci)) {
            throw new DomainException('issuer_staff_denied');
        }
        $context = CompanyRequestScope::context();
        if ((int) get_staff_user_id() !== $context->staffId()) {
            throw new DomainException('issuer_staff_denied');
        }

        return [$context->issuerId(), $context->staffId()];
    }

    public static function linkForm($ci): array
    {
        [$issuer, $actor] = self::context($ci);
        $acl = new IssuerResourceAccess($ci->db, db_prefix());
        if (!$acl->can($issuer, $actor, 'staff', 'view') || !$acl->can($issuer, $actor, 'staff', 'create')) {
            return ['native_staff_can_link' => false];
        }

        $catalog = CompanyResourceCatalog::definitions();
        $assignable = [];
        foreach ($catalog as $feature => $definition) {
            foreach ($definition['capabilities'] as $capability => $label) {
                if ($acl->can($issuer, $actor, $feature, $capability)) {
                    $assignable[$feature][] = $capability;
                }
            }
        }

        // Recheck after building the presentation data so a revoked actor never
        // receives a form that could be submitted from a stale page.
        if (!$acl->can($issuer, $actor, 'staff', 'view') || !$acl->can($issuer, $actor, 'staff', 'create')) {
            throw new DomainException('issuer_staff_denied');
        }

        return [
            'native_staff_can_link' => true,
            'native_staff_link_catalog' => $catalog,
            'native_staff_link_assignable' => $assignable,
            'native_staff_link_request_key' => bin2hex(random_bytes(32)),
        ];
    }

    public static function link($ci): int
    {
        [$issuer, $actor] = self::context($ci);
        CompanyRequestScope::assertNativeMutation($ci);
        $input = $ci->input->post(null, false);
        if (!is_array($input)) {
            throw new DomainException('issuer_staff_invalid');
        }
        if (isset($ci->security)) {
            unset($input[$ci->security->get_csrf_token_name()]);
        }
        if (array_diff(array_keys($input), ['native_staff_operation', 'existing_email', 'permissions', 'make_default', 'request_key'])) {
            throw new DomainException('issuer_staff_invalid');
        }
        if (($input['native_staff_operation'] ?? null) !== 'link'
            || !is_string($input['existing_email'] ?? null)
            || !is_array($input['permissions'] ?? [])
            || !is_string($input['request_key'] ?? null)
            || !preg_match('/^[a-f0-9]{64}$/D', $input['request_key'])) {
            throw new DomainException('issuer_staff_invalid');
        }
        $default = isset($input['make_default']) && in_array($input['make_default'], ['1', 'on'], true);
        if (isset($input['make_default']) && !in_array($input['make_default'], ['0', '1', 'on'], true)) {
            throw new DomainException('issuer_staff_invalid');
        }

        return (new CompanyStaff($ci->db, db_prefix()))->link(
            $issuer,
            $actor,
            $input['existing_email'],
            $input['permissions'] ?? [],
            $default,
            $input['request_key']
        );
    }

    public static function revoke($ci): int
    {
        [$issuer, $actor] = self::context($ci);
        CompanyRequestScope::assertNativeMutation($ci);
        $input = $ci->input->post(null, false);
        if (!is_array($input)) {
            throw new DomainException('issuer_staff_invalid');
        }
        if (isset($ci->security)) {
            unset($input[$ci->security->get_csrf_token_name()]);
        }
        if (array_diff(array_keys($input), ['id', 'revision', 'request_key'])
            || !is_string($input['revision'] ?? null)
            || !preg_match('/^[a-f0-9]{64}$/D', $input['revision'])
            || !is_string($input['request_key'] ?? null)
            || !preg_match('/^[a-f0-9]{64}$/D', $input['request_key'])) {
            throw new DomainException('issuer_staff_invalid');
        }

        $staff = IssuerPolicy::id($input['id'] ?? null);
        return (new CompanyStaff($ci->db, db_prefix()))->revoke(
            $issuer,
            $actor,
            $staff,
            $input['revision'],
            $input['request_key']
        );
    }
}
