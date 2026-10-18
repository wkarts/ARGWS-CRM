<?php

declare(strict_types=1);

require_once __DIR__ . '/IssuerPolicy.php';

/** All mutations enter one transaction and serialize on a module-owned InnoDB row. */
final class IssuerRegistry
{
    private $db;
    private string $prefix;
    private string $tables;

    public function __construct($db, string $prefix)
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/D', $prefix)) {
            throw new InvalidArgumentException('Invalid table prefix');
        }
        $this->db = $db;
        $this->prefix = $prefix;
        $this->tables = $prefix . 'issuers_';
    }

    private function rows(string $sql, array $params = []): array
    {
        $result = $this->db->query($sql, $params);
        if ($result === false) {
            throw new RuntimeException('issuer_storage_failed');
        }
        return $result->result_array();
    }

    private function one(string $sql, array $params = []): ?array
    {
        return $this->rows($sql, $params)[0] ?? null;
    }

    private function write(string $sql, array $params = []): void
    {
        if ($this->db->query($sql, $params) === false) {
            throw new RuntimeException('issuer_storage_failed');
        }
    }

    private function transaction(callable $callback)
    {
        if (!$this->db->trans_begin()) {
            throw new RuntimeException('issuer_storage_failed');
        }
        try {
            if (!$this->one("SELECT id FROM {$this->tables}lock WHERE id = 1 FOR UPDATE")) {
                throw new RuntimeException('issuer_storage_failed');
            }
            $result = $callback();
            if ($this->db->trans_status() === false || !$this->db->trans_commit()) {
                throw new RuntimeException('issuer_storage_failed');
            }
            return $result;
        } catch (Throwable $exception) {
            $this->db->trans_rollback();
            throw $exception;
        }
    }

    private function audit(int $actor, string $event, ?int $issuer, ?int $subject = null): void
    {
        $this->write("INSERT INTO {$this->tables}audit (actor_id,event,issuer_id,subject_id,created_at) VALUES (?,?,?,?,?)",
            [$actor, $event, $issuer, $subject, date('Y-m-d H:i:s')]);
    }

    private function staff(int $id): array
    {
        $row = $this->one("SELECT staffid,admin,active FROM {$this->prefix}staff WHERE staffid = ?", [$id]);
        if (!$row || (int) $row['active'] !== 1) {
            throw new DomainException('issuer_access_denied');
        }
        return $row;
    }

    public function requireAdmin($actor): int
    {
        $actor = IssuerPolicy::id($actor);
        if ((int) $this->staff($actor)['admin'] !== 1) {
            throw new DomainException('issuer_access_denied');
        }
        return $actor;
    }

    private function issuer(int $id, bool $active = true): array
    {
        $row = $this->one("SELECT * FROM {$this->tables}registry WHERE id = ?", [$id]);
        if (!$row || ($active && (int) $row['active'] !== 1)) {
            throw new DomainException('issuer_unavailable');
        }
        return $row;
    }

    /** Administrators have no implicit membership or cross-company customer access. */
    public function canAccess($issuer, $actor, string $capability = 'view'): bool
    {
        try {
            $issuer = IssuerPolicy::id($issuer);
            $actor = IssuerPolicy::id($actor);
            if (!in_array($capability, IssuerPolicy::CAPABILITIES, true)) {
                return false;
            }
            $this->staff($actor);
            $this->issuer($issuer);
            $row = $this->one("SELECT * FROM {$this->tables}staff WHERE issuer_id = ? AND staff_id = ?", [$issuer, $actor]);
            return $row && (int) $row['can_view'] === 1 && (int) $row['can_' . $capability] === 1;
        } catch (DomainException $exception) {
            return false;
        }
    }

    private function requireAccess(int $issuer, int $actor, string $capability = 'view'): void
    {
        if (!$this->canAccess($issuer, $actor, $capability)) {
            throw new DomainException('issuer_access_denied');
        }
    }

    public function catalog($actor): array
    {
        $this->requireAdmin($actor);
        return $this->rows("SELECT * FROM {$this->tables}registry ORDER BY legal_name,id");
    }

    public function available($actor): array
    {
        $actor = IssuerPolicy::id($actor);
        $this->staff($actor);
        return $this->rows("SELECT r.* FROM {$this->tables}registry r JOIN {$this->tables}staff s ON s.issuer_id=r.id
            WHERE s.staff_id=? AND s.can_view=1 AND r.active=1 ORDER BY r.legal_name,r.id", [$actor]);
    }

    public function get($issuer, $actor): array
    {
        $issuer = IssuerPolicy::id($issuer);
        $this->requireAccess($issuer, IssuerPolicy::id($actor));
        return $this->issuer($issuer);
    }

    public function saveIssuer($actor, array $input, $issuer = null): int
    {
        $actor = $this->requireAdmin($actor);
        $id = $issuer === null ? null : IssuerPolicy::id($issuer);
        $name = trim(is_string($input['legal_name'] ?? null) ? $input['legal_name'] : '');
        if ($name === '' || strlen($name) > 191 || preg_match('/[\x00-\x1f]/', $name)) {
            throw new DomainException('issuer_invalid_name');
        }
        $tax = IssuerPolicy::taxId($input['tax_id'] ?? '');
        $active = ($input['active'] ?? '1');
        if (!in_array($active, [0, 1, '0', '1'], true)) {
            throw new DomainException('issuer_invalid_status');
        }
        return $this->transaction(function () use ($actor, $id, $name, $tax, $active) {
            $this->requireAdmin($actor);
            $duplicate = $this->one("SELECT id FROM {$this->tables}registry WHERE tax_id=?", [$tax]);
            if ($duplicate && (int) $duplicate['id'] !== $id) {
                throw new DomainException('issuer_tax_conflict');
            }
            if ($id !== null) {
                $this->issuer($id, false);
                $this->write("UPDATE {$this->tables}registry SET legal_name=?,tax_id=?,active=? WHERE id=?", [$name, $tax, (int) $active, $id]);
                if ((int) $active !== 1) {
                    $this->write("DELETE FROM {$this->tables}staff_defaults WHERE issuer_id=?", [$id]);
                }
            } else {
                $this->write("INSERT INTO {$this->tables}registry (legal_name,tax_id,active,settings_json,created_by,created_at) VALUES (?,?,?,?,?,?)",
                    [$name, $tax, (int) $active, '{}', $actor, date('Y-m-d H:i:s')]);
                $id = (int) $this->db->insert_id();
            }
            $this->audit($actor, 'issuer_saved', $id);
            return $id;
        });
    }

    public function members($issuer, $actor): array
    {
        $this->requireAdmin($actor);
        $issuer = IssuerPolicy::id($issuer);
        $this->issuer($issuer, false);
        return $this->rows("SELECT m.*,s.firstname,s.lastname,s.active FROM {$this->tables}staff m
            JOIN {$this->prefix}staff s ON s.staffid=m.staff_id WHERE m.issuer_id=? ORDER BY s.firstname,s.staffid", [$issuer]);
    }

    public function assignMember($issuer, $staff, array $permissions, $actor): void
    {
        $issuer = IssuerPolicy::id($issuer);
        $staff = IssuerPolicy::id($staff);
        $actor = $this->requireAdmin($actor);
        $permissions = IssuerPolicy::permissions($permissions);
        $this->transaction(function () use ($issuer, $staff, $permissions, $actor) {
            $this->requireAdmin($actor);
            $this->issuer($issuer);
            $this->staff($staff);
            $flags = array_map(fn ($capability) => in_array($capability, $permissions, true) ? 1 : 0, IssuerPolicy::CAPABILITIES);
            if ($this->one("SELECT staff_id FROM {$this->tables}staff WHERE issuer_id=? AND staff_id=?", [$issuer, $staff])) {
                $this->write("UPDATE {$this->tables}staff SET can_view=?,can_manage_clients=?,can_share_clients=? WHERE issuer_id=? AND staff_id=?", array_merge($flags, [$issuer, $staff]));
            } else {
                $this->write("INSERT INTO {$this->tables}staff (can_view,can_manage_clients,can_share_clients,issuer_id,staff_id) VALUES (?,?,?,?,?)", array_merge($flags, [$issuer, $staff]));
            }
            if (!in_array('view', $permissions, true)) {
                $this->write("DELETE FROM {$this->tables}staff_defaults WHERE issuer_id=? AND staff_id=?", [$issuer, $staff]);
            }
            $this->audit($actor, 'member_assigned', $issuer, $staff);
        });
    }

    public function removeMember($issuer, $staff, $actor): void
    {
        $issuer = IssuerPolicy::id($issuer);
        $staff = IssuerPolicy::id($staff);
        $actor = $this->requireAdmin($actor);
        $this->transaction(function () use ($issuer, $staff, $actor) {
            $this->requireAdmin($actor);
            $this->write("DELETE FROM {$this->tables}staff_defaults WHERE issuer_id=? AND staff_id=?", [$issuer, $staff]);
            $this->write("DELETE FROM {$this->tables}staff WHERE issuer_id=? AND staff_id=?", [$issuer, $staff]);
            $this->audit($actor, 'member_removed', $issuer, $staff);
        });
    }

    public function defaultForStaff($staff): ?int
    {
        $staff = IssuerPolicy::id($staff);
        $row = $this->one("SELECT issuer_id FROM {$this->tables}staff_defaults WHERE staff_id=?", [$staff]);
        return $row && $this->canAccess($row['issuer_id'], $staff) ? (int) $row['issuer_id'] : null;
    }

    public function setDefault($issuer, $actor): void
    {
        $issuer = IssuerPolicy::id($issuer);
        $actor = IssuerPolicy::id($actor);
        $this->transaction(function () use ($issuer, $actor) {
            $this->requireAccess($issuer, $actor);
            $this->write("DELETE FROM {$this->tables}staff_defaults WHERE staff_id=?", [$actor]);
            $this->write("INSERT INTO {$this->tables}staff_defaults (staff_id,issuer_id) VALUES (?,?)", [$actor, $issuer]);
            $this->audit($actor, 'default_selected', $issuer);
        });
    }

    private function checkedClient(int $client, bool $requireTax): array
    {
        $row = $this->one("SELECT userid,company,vat,active FROM {$this->prefix}clients WHERE userid=? FOR UPDATE", [$client]);
        if (!$row || (int) $row['active'] !== 1) {
            throw new DomainException('issuer_client_unavailable');
        }
        $tax = IssuerPolicy::taxId((string) ($row['vat'] ?? ''), $requireTax);
        if ($tax !== null) {
            // Core VAT fields are not normalized. Recheck every candidate before linking; never auto-merge.
            foreach ($this->rows("SELECT userid,vat FROM {$this->prefix}clients") as $candidate) {
                if ((int) $candidate['userid'] !== $client && preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $candidate['vat'])) === $tax) {
                    throw new DomainException('issuer_client_tax_conflict');
                }
            }
            $other = $this->one("SELECT client_id FROM {$this->tables}clients WHERE tax_id=?", [$tax]);
            if ($other && (int) $other['client_id'] !== $client) {
                throw new DomainException('issuer_client_tax_conflict');
            }
        }
        $row['normalized_tax_id'] = $tax;
        return $row;
    }

    /** Explicit admin adoption of an existing canonical customer; default is owner-only. */
    public function registerClient($issuer, $client, $actor): void
    {
        $issuer = IssuerPolicy::id($issuer);
        $client = IssuerPolicy::id($client);
        $actor = $this->requireAdmin($actor);
        $this->transaction(function () use ($issuer, $client, $actor) {
            $this->requireAdmin($actor);
            $this->requireAccess($issuer, $actor, 'manage_clients');
            $record = $this->one("SELECT * FROM {$this->tables}clients WHERE client_id=?", [$client]);
            if ($record) {
                throw new DomainException('issuer_client_already_registered');
            }
            $row = $this->checkedClient($client, false);
            $this->write("INSERT INTO {$this->tables}clients (client_id,owner_issuer_id,tax_id,created_by,created_at) VALUES (?,?,?,?,?)",
                [$client, $issuer, $row['normalized_tax_id'], $actor, date('Y-m-d H:i:s')]);
            $this->write("INSERT INTO {$this->tables}client_links (client_id,issuer_id,linked_by,created_at) VALUES (?,?,?,?)",
                [$client, $issuer, $actor, date('Y-m-d H:i:s')]);
            $this->audit($actor, 'client_registered_private', $issuer, $client);
        });
    }

    /** Replace additional recipients atomically. The owning issuer can never be removed. */
    public function shareClient($owner, $client, array $targets, $actor): void
    {
        $owner = IssuerPolicy::id($owner);
        $client = IssuerPolicy::id($client);
        $actor = IssuerPolicy::id($actor);
        $targets = array_values(array_unique(array_merge([$owner], array_map([IssuerPolicy::class, 'id'], $targets))));
        $this->transaction(function () use ($owner, $client, $targets, $actor) {
            $this->requireAccess($owner, $actor, 'share_clients');
            $record = $this->one("SELECT * FROM {$this->tables}clients WHERE client_id=?", [$client]);
            if (!$record || (int) $record['owner_issuer_id'] !== $owner) {
                throw new DomainException('issuer_access_denied');
            }
            // The owner controls revocation, including inaccessible/inactive recipients.
            // Only requested recipients grant/retain access and must pass destination ACL.
            foreach ($targets as $target) {
                $this->requireAccess((int) $target, $actor, 'manage_clients');
            }
            if (count($targets) > 1) {
                $row = $this->checkedClient($client, true);
                if ($record['tax_id'] !== $row['normalized_tax_id']) {
                    throw new DomainException('issuer_client_identity_changed');
                }
            }
            $this->write("DELETE FROM {$this->tables}client_links WHERE client_id=?", [$client]);
            foreach ($targets as $target) {
                $this->write("INSERT INTO {$this->tables}client_links (client_id,issuer_id,linked_by,created_at) VALUES (?,?,?,?)",
                    [$client, $target, $actor, date('Y-m-d H:i:s')]);
            }
            $this->audit($actor, count($targets) > 1 ? 'client_shared' : 'client_private', $owner, $client);
        });
    }

    public function clients($issuer, $actor): array
    {
        $issuer = IssuerPolicy::id($issuer);
        $this->requireAccess($issuer, IssuerPolicy::id($actor));
        return $this->rows("SELECT c.userid,c.company,c.vat,r.owner_issuer_id FROM {$this->prefix}clients c
            JOIN {$this->tables}clients r ON r.client_id=c.userid
            JOIN {$this->tables}client_links l ON l.client_id=c.userid
            WHERE l.issuer_id=? AND c.active=1 ORDER BY c.company,c.userid", [$issuer]);
    }

    public function clientTargets($issuer, $client, $actor): array
    {
        $issuer = IssuerPolicy::id($issuer);
        $client = IssuerPolicy::id($client);
        $this->requireAccess($issuer, IssuerPolicy::id($actor), 'share_clients');
        $record = $this->one("SELECT owner_issuer_id FROM {$this->tables}clients WHERE client_id=?", [$client]);
        if (!$record || (int) $record['owner_issuer_id'] !== $issuer) {
            throw new DomainException('issuer_access_denied');
        }
        return array_map('intval', array_column($this->rows("SELECT issuer_id FROM {$this->tables}client_links WHERE client_id=?", [$client]), 'issuer_id'));
    }
}
