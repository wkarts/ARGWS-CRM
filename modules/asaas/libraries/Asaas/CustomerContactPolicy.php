<?php

declare(strict_types=1);

namespace Asaas;

/** Customer preferences apply at the transport boundary, including legacy callers. */
final class CustomerContactPolicy
{
    public static function isCustomerPath(string $path): bool
    {
        return preg_match('#^(?:v3/)?customers(?:/|$)#', self::resourcePath($path)) === 1;
    }

    /**
     * Omit disallowed contacts rather than sending null/empty values, which could
     * clear existing remote data. Changing settings alone never updates customers.
     * Notification defaults apply on creation; an explicit update only changes the
     * customer-level notification setting when that field was requested.
     *
     * @return array{0: string, 1: array, 2: array|null}
     */
    public static function apply(string $method, string $path, array $query, ?array $payload, bool $sendEmail, bool $sendMobile, bool $disableNotifications): array
    {
        if (!self::isCustomerPath($path)) return [$path, $query, $payload];

        // URL query parameters are transmission too. Merge them with the explicit
        // query before filtering; the explicit query retains precedence.
        $parts = parse_url(ltrim($path, '/'));
        if (isset($parts['query'])) {
            $embedded = [];
            parse_str($parts['query'], $embedded);
            $query = array_replace($embedded, $query);
            $path = $parts['path'];
        }
        $filteredQuery = self::filterContacts($query, $sendEmail, $sendMobile);
        // Removing an identity filter could turn an exact customer lookup into
        // an unrelated listing. Refuse that lookup rather than broaden its match.
        if (strtoupper($method) === 'GET' && $filteredQuery !== $query) {
            throw new \DomainException('asaas_customer_contact_filter_disabled');
        }
        $query = $filteredQuery;
        if ($payload !== null) {
            $payload = self::filterContacts($payload, $sendEmail, $sendMobile);
            $resource = rtrim(self::resourcePath($path), '/');
            $creating = strtoupper($method) === 'POST' && in_array($resource, ['customers', 'v3/customers'], true);
            $updating = in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'], true)
                && preg_match('#^(?:v3/)?customers/[^/]+$#', $resource) === 1;
            if ($creating || ($updating && array_key_exists('notificationDisabled', $payload))) {
                $payload['notificationDisabled'] = $disableNotifications;
            }
        }
        return [$path, $query, $payload];
    }

    private static function filterContacts(array $values, bool $sendEmail, bool $sendMobile): array
    {
        if ($sendEmail && $sendMobile) return $values;
        foreach ($values as $key => $value) {
            // Also recognize bracketed keys accepted by query-string encoders.
            $keyParts = preg_split('/[\[\]]+/', strtolower((string) $key), -1, PREG_SPLIT_NO_EMPTY);
            if ((!$sendEmail && array_intersect($keyParts, ['email', 'additionalemails']))
                || (!$sendMobile && array_intersect($keyParts, ['phone', 'mobilephone', 'phonenumber']))) {
                unset($values[$key]);
            } elseif (is_array($value) || is_object($value)) {
                $values[$key] = self::filterContacts((array) $value, $sendEmail, $sendMobile);
            }
        }
        return $values;
    }

    private static function resourcePath(string $path): string
    {
        // Match AsaasAdapter::normalizePath: leading slashes are removed before
        // interpreting a resource, so //customers is not mistaken for a host.
        $path = rawurldecode((string) (parse_url(ltrim($path, '/'), PHP_URL_PATH) ?? ''));
        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') continue;
            if ($segment === '..') array_pop($segments);
            else $segments[] = $segment;
        }
        return implode('/', $segments);
    }
}
