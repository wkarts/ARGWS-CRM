<?php

declare(strict_types=1);

require_once __DIR__ . '/NativeCompanyTasksRead.php';
require_once __DIR__ . '/NativeCompanyResourceReferences.php';

/** Scoped identities for the original task index; not a table or a route adapter. */
final class NativeCompanyTaskIndexRead
{
    public function __construct(private $db, private string $prefix)
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/D', $prefix)) throw new InvalidArgumentException('Invalid table prefix');
    }

    private function rows(string $query, array $parameters): array
    {
        return IssuerStorage::query($this->db, $query, $parameters)->result_array();
    }

    /**
     * Names are hydrated only after the existing source and relationship guards.
     * The returned records cannot authorize a subsequent view or mutation: each
     * consumer must resolve the canonical identity again at its own boundary.
     */
    public function read($issuer, $actor): array
    {
        return $this->capture($issuer, $actor, true);
    }

    /** Internal snapshot; caller must compare a second capture before emitting it. */
    public function captureForComparison($issuer, $actor): array
    {
        return $this->capture($issuer, $actor, false);
    }

    private function capture($issuer, $actor, bool $verify): array
    {
        $issuer = IssuerPolicy::id($issuer);
        $actor = IssuerPolicy::id($actor);
        $acl = new IssuerResourceAccess($this->db, $this->prefix);
        if (!$acl->can($issuer, $actor, 'tasks', 'view')) throw new DomainException('native_company_task_denied');
        $primary = $acl->primaryId();
        $projectsVisible = $acl->can($issuer, $actor, 'projects', 'view');
        $sources = [
            ['company_standalone_task', 'company_standalone_tasks', ' WHERE issuer_id=?', [$issuer]],
        ];
        if ($projectsVisible) $sources[] = ['company_task', 'company_tasks', ' WHERE issuer_id=?', [$issuer]];
        if ($primary === $issuer) $sources[] = ['task', 'tasks', '', []];

        $reader = new NativeCompanyTasksRead($this->db, $this->prefix);
        $sets = [];
        $result = [];
        foreach ($sources as [$kind, $table, $where, $args]) {
            $query = 'SELECT id FROM ' . $this->prefix . $table . $where . ' ORDER BY id LIMIT 10001';
            $ids = $this->rows($query, $args);
            if (count($ids) > 10000) throw new DomainException('native_task_index_limit');
            $sets[] = [$query, $args, $ids];
            foreach ($ids as $row) {
                $canonical = NativeCompanyResourceId::encode($kind, $row['id']);
                try {
                    $result[] = $reader->relationOption($issuer, $actor, $canonical);
                } catch (DomainException $error) {
                    // Individual historical relationships or private objects may
                    // legitimately be outside this company. All other errors are
                    // propagated; a revoked membership must never become an empty list.
                    if (!in_array($error->getMessage(), ['native_resource_reference_denied', 'native_ticket_denied', 'native_ticket_department_denied'], true)
                        || !$acl->can($issuer, $actor, 'tasks', 'view')) throw $error;
                }
            }
        }
        if (count($result) > 10000) throw new DomainException('native_task_index_limit');
        if ($verify) {
            foreach ($sets as [$query, $args, $ids]) {
                if ($this->rows($query, $args) !== $ids) throw new DomainException('native_task_index_changed');
            }
            foreach ($result as $item) {
                if ($reader->relationOption($issuer, $actor, $item['id']) !== $item) throw new DomainException('native_task_index_changed');
            }
        }
        if (!$acl->can($issuer, $actor, 'tasks', 'view') || $acl->primaryId() !== $primary
            || $acl->can($issuer, $actor, 'projects', 'view') !== $projectsVisible) {
            throw new DomainException('native_task_index_changed');
        }
        return $result;
    }
}
