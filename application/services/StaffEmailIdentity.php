<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerStorage.php';

/** A staff login is installation-wide; company memberships never select its owner. */
final class StaffEmailIdentity
{
    public const COLUMN = 'staff_email_identity';
    public const INDEX = 'uq_staff_email_identity';
    public const COLLATION = 'utf8mb4_unicode_ci';

    public static function table(string $prefix): string
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/D', $prefix)) {
            throw new InvalidArgumentException('Invalid table prefix');
        }
        return $prefix.'staff';
    }

    /** Trim ASCII SPACE only, just like SQL TRIM; other ASCII whitespace is invalid. */
    public static function normalize($email): string
    {
        if (!is_string($email) || strlen($email) > 100 || preg_match('/[^\x20-\x7E]/', $email)) {
            throw new DomainException('issuer_staff_email_invalid');
        }
        $email = strtolower(trim($email, ' '));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new DomainException('issuer_staff_email_invalid');
        }
        return $email;
    }

    /** Keep existing API row/list shapes; the generated key is storage metadata only. */
    public static function publicData($data)
    {
        if (is_object($data)) {
            $copy = clone $data;
            unset($copy->{self::COLUMN});
            return $copy;
        }
        if (!is_array($data)) { return $data; }
        $row = array_key_exists('staffid', $data);
        unset($data[self::COLUMN]);
        if (!$row) {
            foreach ($data as $key => $value) {
                if (is_array($value) || is_object($value)) { $data[$key] = self::publicData($value); }
            }
        }
        return $data;
    }

    public static function expression(string $column = 'email'): string
    {
        if (!in_array($column, ['email', self::COLUMN], true)) {
            throw new InvalidArgumentException('Invalid identity column');
        }
        return 'LOWER(TRIM(`'.$column.'`)) COLLATE '.self::COLLATION;
    }

    public static function assertAvailable($db, string $prefix, $email, ?int $except = null): string
    {
        $email = self::normalize($email);
        $sql = 'SELECT staffid FROM `'.self::table($prefix).'` WHERE '.self::expression().'=?';
        $params = [$email];
        if ($except !== null) {
            if ($except < 1) { throw new DomainException('issuer_staff_email_invalid'); }
            $sql .= ' AND staffid<>?';
            $params[] = $except;
        }
        if (IssuerStorage::query($db, $sql.' LIMIT 1', $params)->result_array()) {
            throw new DomainException('issuer_staff_email_conflict');
        }
        return $email;
    }

    /** Also usable before upgrade: ambiguous legacy identities fail closed. */
    public static function unique($db, string $prefix, $email): ?object
    {
        try { $email = self::normalize($email); }
        catch (DomainException $error) { return null; }
        $rows = IssuerStorage::query($db, 'SELECT * FROM `'.self::table($prefix).'` WHERE '.self::expression().'=? ORDER BY staffid LIMIT 2', [$email])->result_array();
        return count($rows) === 1 ? (object) $rows[0] : null;
    }
}
