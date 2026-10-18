<?php
namespace app\services;
defined('BASEPATH') or exit('No direct script access allowed');

/** Explicit client ownership predicates; never an ambient selected-company grant. */
final class ClientIssuerScope
{
    private static function identifier(string $name): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $name)) throw new \InvalidArgumentException('client_scope_identifier');
        return $name;
    }
    public static function legacySql($db, string $prefix, string $alias, string $idColumn = 'userid'): string
    {
        self::identifier($prefix); self::identifier($alias); self::identifier($idColumn);
        $owners = $prefix . 'issuers_clients';
        // Consult persisted ownership even when module bootstrap is unavailable.
        if (!$db->table_exists($owners)) {
            // A partially missing module schema must never reopen previously private customers.
            return $db->table_exists($prefix . 'issuers_client_links') || $db->table_exists($prefix . 'issuers_registry') ? '1=0' : '1=1';
        }
        return 'NOT EXISTS (SELECT 1 FROM ' . $owners . ' AS client_owner_scope WHERE client_owner_scope.client_id = ' . $alias . '.' . $idColumn . ')';
    }
    /** Generic legacy helpers accept only simple known table expressions, not inferred joins. */
    public static function legacyTableSql($db,string $prefix,string $table): ?string
    {
        self::identifier($prefix);
        $pattern='/^`?('.preg_quote($prefix,'/').'(?:clients|contacts|tasks|projects|expenses|tickets|contracts|estimates|creditnotes|proposals|emailtemplates))`?(?:\\s+(?:AS\\s+)?`?([A-Za-z_][A-Za-z0-9_]*)`?)?$/iD';
        if(!preg_match($pattern,trim($table),$match))return null;
        if (strtolower($match[1]) === strtolower($prefix.'emailtemplates')) { require_once __DIR__.'/CompanyEmailTemplates.php'; return \CompanyEmailTemplates::scopeSql($db,$prefix,$match[2]??$match[1],\CompanyEmailTemplates::currentIssuer()); }
        if (strtolower($match[1]) === strtolower($prefix.'tasks')) return TaskClientBoundary::legacySql($db,$prefix,$match[2]??$match[1]);
        foreach (['projects','expenses','tickets','contracts'] as $related) if (strtolower($match[1]) === strtolower($prefix.$related)) return CustomerRelationBoundary::sql($db,$prefix,$related,$match[2]??$match[1]);
        foreach (['estimates','creditnotes','proposals'] as $document) if (strtolower($match[1]) === strtolower($prefix.$document)) return self::legacyDocumentSql($db,$prefix,$document,$match[2]??$match[1]);
        return self::legacySql($db,$prefix,$match[2]??$match[1]);
    }
    public static function applyLegacy($db, string $prefix, string $alias, string $idColumn = 'userid'): void
    {
        $db->where(self::legacySql($db, $prefix, $alias, $idColumn), null, false);
    }
    public static function isLegacyClient($db, string $prefix, $id): bool
    {
        if ((!is_string($id) && !is_int($id)) || !preg_match('/^[1-9][0-9]*$/D', (string)$id)) return false;
        self::identifier($prefix);
        $query = $db->query('SELECT userid FROM ' . $prefix . 'clients WHERE userid=? AND ' . self::legacySql($db,$prefix,$prefix.'clients'), [$id]);
        return $query !== false && $query->row() !== null;
    }
    /** Legacy writers cannot attach a private customer, including via extension hooks. */
    public static function allowsLegacyWrite($db, string $prefix, array $data, string $field = 'clientid', bool $required = false): bool
    {
        if (!array_key_exists($field, $data)) return !$required;
        $id = $data[$field];
        if ((!is_string($id) && !is_int($id)) || !preg_match('/^[1-9][0-9]*$/D', (string) $id)) return false;
        self::identifier($prefix);
        // Without any issuer schema, preserve the core's own legacy reference validation.
        if (!$db->table_exists($prefix . 'issuers_clients') && !$db->table_exists($prefix . 'issuers_client_links') && !$db->table_exists($prefix . 'issuers_registry')) return true;
        return self::isLegacyClient($db, $prefix, $id);
    }
    public static function allowsLegacyProposalWrite($db, string $prefix, array $data): bool
    {
        $type = $data['rel_type'] ?? null;
        if ($type === null || $type === '' || $type === 'lead') return true;
        // Reject noncanonical spellings: case-insensitive MySQL collations may otherwise
        // treat an unvalidated relation as customer in downstream queries.
        return $type === 'customer' && self::allowsLegacyWrite($db, $prefix, $data, 'rel_id', true);
    }
    /** A hook may omit rel_type while replacing rel_id; validate the effective stored relation. */
    public static function allowsLegacyProposalUpdate($db, string $prefix, array $data, $id): bool
    {
        self::identifier($prefix);
        if ((!is_string($id) && !is_int($id)) || !preg_match('/^[1-9][0-9]*$/D', (string) $id)) return false;
        $query = $db->query('SELECT rel_id,rel_type FROM ' . $prefix . 'proposals WHERE id=?', [$id]);
        if ($query === false || !($row = $query->row())) return false;
        return self::allowsLegacyProposalWrite($db, $prefix, array_replace((array) $row, $data));
    }
    /** SQL boundary for legacy sales-document readers and aggregate helpers. */
    public static function legacyDocumentSql($db, string $prefix, string $type, string $alias): string
    {
        $columns = ['estimates'=>'clientid', 'creditnotes'=>'clientid', 'contracts'=>'client', 'proposals'=>'rel_id'];
        if (!isset($columns[$type])) throw new \InvalidArgumentException('client_document_unknown');
        $sql = self::legacySql($db, $prefix, $alias, $columns[$type]);
        return $type === 'proposals' ? "($alias.rel_type IS NULL OR LOWER($alias.rel_type) <> 'customer' OR ($sql))" : $sql;
    }
    /** Raw lookup precedes model hydration and conversion/copy effects. */
    public static function isLegacyDocumentClient($db, string $prefix, string $type, $id): bool
    {
        self::identifier($prefix);
        $columns = ['invoices'=>'clientid', 'creditnotes'=>'clientid', 'estimates'=>'clientid', 'contracts'=>'client', 'proposals'=>'rel_id'];
        if (!isset($columns[$type]) || (!is_string($id) && !is_int($id)) || !preg_match('/^[1-9][0-9]*$/D', (string) $id)) return false;
        $column = $columns[$type];
        $query = $db->query('SELECT ' . $column . ($type === 'proposals' ? ',rel_type' : '') . ' FROM ' . $prefix . $type . ' WHERE id=?', [$id]);
        if ($query === false || !($row = $query->row())) return false;
        return $type === 'proposals'
            ? self::allowsLegacyProposalWrite($db, $prefix, (array) $row)
            : self::allowsLegacyWrite($db, $prefix, (array) $row, $column, true);
    }
    public static function isLegacyContact($db, string $prefix, $id): bool
    {
        if ((!is_string($id) && !is_int($id)) || !preg_match('/^[1-9][0-9]*$/D', (string)$id)) return false;
        self::identifier($prefix);
        $query = $db->query('SELECT id FROM ' . $prefix . 'contacts WHERE id=? AND '
            . self::legacySql($db, $prefix, $prefix . 'contacts'), [$id]);
        return $query !== false && $query->row() !== null;
    }
    /** Phase-two rollout is blocked until portal/session and remaining direct readers are scoped. */
    public static function assertAdoptionReady(): void
    {
        throw new \DomainException('issuer_client_privacy_pending');
    }

    /** Minimal identity for an already authorized legacy invoice; never a customer-directory grant. */
    public static function legacyInvoiceIdentity($db, string $prefix, int $invoice): ?object
    {
        self::identifier($prefix);
        if ($invoice < 1) return null;
        $sql='SELECT c.userid,c.company,c.vat FROM '.$prefix.'invoices AS i'
            .' INNER JOIN '.$prefix.'clients AS c ON c.userid=i.clientid WHERE i.id=? AND '
            .InvoiceIssuerScope::legacySql($db,$prefix.'invoices','i');
        $result=$db->query($sql,[$invoice]);
        if ($result===false) throw new \RuntimeException('client_scope_storage_failed');
        $client=$result->row();
        if (!$client) return null;
        if (trim((string)$client->company)==='') {
            $contact=$db->query('SELECT firstname,lastname FROM '.$prefix.'contacts WHERE userid=? AND is_primary=1 ORDER BY id LIMIT 1',[$client->userid]);
            if ($contact===false) throw new \RuntimeException('client_scope_storage_failed');
            $name=$contact->row();
            $client->company=$name ? trim($name->firstname.' '.$name->lastname) : '';
        }
        $client->issuer_financial_identity_only=true;
        return $client;
    }
    /** Scoped reads require live ACL both before and after the query (revocation is never cached). */
    public static function forIssuer($db, string $prefix, int $issuer, int $actor, callable $authorize, ?int $client = null): array
    {
        self::identifier($prefix);
        if ($issuer < 1 || $actor < 1 || ($client !== null && $client < 1)
            || $authorize($issuer,$actor,'view') !== true) throw new \DomainException('client_scope_denied');
        if (!$db->table_exists($prefix.'issuers_clients') || !$db->table_exists($prefix.'issuers_client_links')) throw new \DomainException('client_scope_unavailable');
        $sql='SELECT c.userid,c.company,c.vat,o.owner_issuer_id FROM '.$prefix.'clients AS c INNER JOIN '.$prefix.'issuers_clients AS o ON o.client_id=c.userid'
            .' WHERE (o.owner_issuer_id=? OR EXISTS (SELECT 1 FROM '.$prefix.'issuers_client_links AS l WHERE l.client_id=c.userid AND l.issuer_id=?))';
        $bindings=[$issuer,$issuer];
        if ($client!==null){$sql.=' AND c.userid=?';$bindings[]=$client;}
        $sql.=' ORDER BY c.userid LIMIT 101';
        $query=$db->query($sql,$bindings);
        if ($query===false) throw new \RuntimeException('client_scope_storage_failed');
        if ($authorize($issuer,$actor,'view') !== true) throw new \DomainException('client_scope_denied');
        return $query->result_array();
    }
}
