<?php

defined('BASEPATH') or exit('No direct script access allowed');
require_once APPPATH . 'services/issuers/IssuerRegistry.php';

class Issuers_model extends App_Model
{
    public function registry(): IssuerRegistry
    {
        return new IssuerRegistry($this->db, db_prefix());
    }

    public function get($issuerId): array
    {
        return $this->registry()->get($issuerId, get_staff_user_id());
    }

    public function can_access($issuerId, $staffId, string $capability = 'view'): bool
    {
        return $this->registry()->canAccess($issuerId, $staffId, $capability);
    }

    public function available($staffId): array
    {
        return $this->registry()->available($staffId);
    }

    public function default_for_staff($staffId): ?int
    {
        return $this->registry()->defaultForStaff($staffId);
    }

    public function assert_operational_scope($issuerId): void
    {
        IssuerPolicy::assertOperationalScope($issuerId);
    }
}
