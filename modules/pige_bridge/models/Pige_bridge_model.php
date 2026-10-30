<?php

defined('BASEPATH') or exit('No direct script access allowed');
class Pige_bridge_model extends App_Model
{
    /** One atomic unique-key insert. No invoices/payments are touched, even on retries. */
    public function accept_request(string $connectionKey, array $event, string $rawBody): array
    {
        $scope = $event['scope'];
        $row = [
            'connection_key' => $connectionKey,
            'event_id' => $event['event_id'],
            'request_id' => 'req_' . bin2hex(random_bytes(16)),
            'payload_sha256' => hash('sha256', $rawBody),
            'scope_sha256' => hash('sha256', implode("\n", [$scope['school_id'], $scope['issuer_id'], $scope['account_id'], $scope['environment']])),
            'school_id' => $scope['school_id'],
            'issuer_id' => $scope['issuer_id'],
            'account_id' => $scope['account_id'],
            'environment' => $scope['environment'],
            'charge_id' => $event['data']['charge_id'],
            'amount_cents' => $event['data']['amount_cents'],
            'due_date' => $event['data']['due_date'],
            'created_at' => gmdate('Y-m-d H:i:s'),
        ];
        $table = db_prefix() . 'pige_bridge_requests';
        $sql = $this->db->insert_string($table, $row) . ' ON DUPLICATE KEY UPDATE id=id';
        if (!$this->db->query($sql)) {
            throw new RuntimeException('receipt_storage_unavailable');
        }
        $stored = $this->db->where('connection_key', $connectionKey)->where('event_id', $event['event_id'])->get($table)->row_array();
        if (!$stored) {
            $existing = $this->db->where('scope_sha256', $row['scope_sha256'])->where('charge_id', $row['charge_id'])->get($table)->row_array();
            if ($existing) {
                throw new DomainException('idempotency_conflict');
            }
            throw new RuntimeException('receipt_storage_unavailable');
        }
        if (!hash_equals($stored['payload_sha256'], $row['payload_sha256'])) {
            throw new DomainException('idempotency_conflict');
        }
        return $stored;
    }
}
