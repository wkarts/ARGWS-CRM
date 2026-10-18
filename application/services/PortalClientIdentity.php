<?php
namespace app\services;
defined('BASEPATH') or exit('No direct script access allowed');

/** Read-only identity of the authenticated contact. Never an issuer/financial grant. */
final class PortalClientIdentity
{
    private const CONTACT_FIELDS = ['id','userid','is_primary','firstname','lastname','email','phonenumber','title','active','profile_image','direction','email_verified_at','invoice_emails','estimate_emails','credit_note_emails','contract_emails','task_emails','project_emails','ticket_emails'];
    private const CLIENT_FIELDS = ['userid','company','vat','phonenumber','country','city','zip','state','address','website','active','billing_street','billing_city','billing_state','billing_zip','billing_country','shipping_street','shipping_city','shipping_state','shipping_zip','shipping_country','default_language','default_currency','show_primary_contact','registration_confirmed'];

    public static function fromAuthenticatedSession($db, string $prefix, $session): ?array
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $prefix)) {
            throw new \InvalidArgumentException('portal_identity_prefix');
        }
        if ($session->userdata('client_logged_in') !== true) return null;
        $contact = $session->userdata('contact_user_id');
        $client = $session->userdata('client_user_id');
        foreach ([$contact, $client] as $id) {
            if ((!is_int($id) && !is_string($id)) || !preg_match('/^[1-9][0-9]*$/D', (string) $id)) return null;
        }
        // Both identifiers are taken only from the established server-side login.
        // A moved/deactivated contact or customer invalidates the previous identity.
        $where = ' FROM '.$prefix.'contacts AS p INNER JOIN '.$prefix.'clients AS c ON c.userid=p.userid'
            .' WHERE p.id=? AND p.userid=? AND p.active=1 AND c.active=1';
        $contactQuery = $db->query('SELECT '.self::columns('p', self::CONTACT_FIELDS).$where, [$contact, $client]);
        if ($contactQuery === false) throw new \RuntimeException('portal_identity_storage_failed');
        $contactRow = $contactQuery->row();
        if (!$contactRow) return null;
        $clientQuery = $db->query('SELECT '.self::columns('c', self::CLIENT_FIELDS).$where, [$contact, $client]);
        if ($clientQuery === false) throw new \RuntimeException('portal_identity_storage_failed');
        $clientRow = $clientQuery->row();
        if (!$clientRow) return null;
        // Never expose auth hashes, reset/verification keys, provider IDs or future columns.
        return ['contact' => $contactRow, 'client' => $clientRow];
    }

    /** Revalidate a contact already authenticated by a password/cookie provider. No lookup by email or company grant. */
    public static function activeContact($db, string $prefix, $contact): ?object
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $prefix)) throw new \InvalidArgumentException('portal_identity_prefix');
        if ((!is_int($contact) && !is_string($contact)) || !preg_match('/^[1-9][0-9]*$/D', (string)$contact)) return null;
        $query=$db->query('SELECT p.id,p.userid FROM '.$prefix.'contacts p JOIN '.$prefix.'clients c ON c.userid=p.userid WHERE p.id=? AND p.active=1 AND c.active=1', [$contact]);
        if ($query===false) throw new \RuntimeException('portal_identity_storage_failed');
        return $query->row();
    }

    /** Resolve a known contact's mail origin from ownership, never a selected staff company or sharing destination.
     * This is routing metadata only; callers must separately verify the recovery request/token.
     */
    public static function mailOrigin($db, string $prefix, $contact): ?array
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $prefix)) throw new \InvalidArgumentException('portal_identity_prefix');
        if ((!is_int($contact) && !is_string($contact)) || !preg_match('/^[1-9][0-9]*$/D',(string)$contact)) return null;
        foreach (['issuers_clients','issuers_installation_identity','issuers_registry'] as $table) {
            if (!$db->table_exists($prefix.$table)) throw new \DomainException('portal_mail_origin_schema_required');
        }
        $issuer='CASE WHEN o.client_id IS NULL THEN i.primary_issuer_id ELSE o.owner_issuer_id END';
        // One statement binds active contact, parent, ownership and company to the same read.
        $query=$db->query('SELECT p.id contact_id,p.userid client_id,'.$issuer.' issuer_id,o.client_id owner_client,r.active issuer_active'
            .' FROM '.$prefix.'contacts p JOIN '.$prefix.'clients c ON c.userid=p.userid'
            .' LEFT JOIN '.$prefix.'issuers_clients o ON o.client_id=c.userid'
            .' LEFT JOIN '.$prefix.'issuers_installation_identity i ON i.id=1'
            .' LEFT JOIN '.$prefix.'issuers_registry r ON r.id=('.$issuer.')'
            .' WHERE p.id=? AND p.active=1 AND c.active=1',[$contact]);
        if ($query===false) throw new \RuntimeException('portal_identity_storage_failed');
        $rows=$query->result_array();
        if (!$rows) return null;
        if (count($rows)!==1) throw new \DomainException('portal_mail_origin_ambiguous');
        $row=$rows[0];$issuer=$row['issuer_id'];
        if ((!is_string($issuer)&&!is_int($issuer)) || !preg_match('/^[1-9][0-9]*$/D',(string)$issuer) || (int)$row['issuer_active']!==1) throw new \DomainException('portal_mail_origin_unavailable');
        return ['contact_id'=>(int)$row['contact_id'],'client_id'=>(int)$row['client_id'],'issuer_id'=>(int)$issuer,'legacy'=>$row['owner_client']===null];
    }

    private static function columns(string $alias, array $fields): string
    {
        return implode(',', array_map(static fn($field) => $alias.'.'.$field, $fields));
    }
}
