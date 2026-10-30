<?php

use app\services\InvoiceIssuerScope;

defined('BASEPATH') or exit('No direct script access allowed');

class Email_schedule_model extends App_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    public function create($rel_id, $rel_type, $data)
    {
        if ($rel_type === 'invoice' && !InvoiceIssuerScope::isLegacyInvoice($this->db, db_prefix() . 'invoices', $rel_id)) { return false; }
        $contacts = $data['contacts'];

        if (is_array($contacts)) {
            $contacts = implode(',', $contacts);
        }

        $this->db->insert(db_prefix() . 'scheduled_emails', [
            'rel_type'     => $rel_type,
            'rel_id'       => $rel_id,
            'scheduled_at' => $data['scheduled_at'],
            'contacts'     => $contacts,
            'cc'           => $data['cc'],
            'attach_pdf'   => $data['attach_pdf'],
            'template'     => $data['template'],
        ]);
    }

    public function update($id, $data)
    {
        $schedule = $this->getById($id);
        if (!$schedule || (isset($data['rel_id']) && (string) $data['rel_id'] !== (string) $schedule->rel_id)
            || (isset($data['rel_type']) && $data['rel_type'] !== $schedule->rel_type)) { return false; }
        unset($data['rel_id'], $data['rel_type']);
        if (is_array($data['contacts'])) {
            $data['contacts'] = implode(',', $data['contacts']);
        }

        $this->db->where('id', $id);
        $this->db->update(db_prefix() . 'scheduled_emails', $data);

        return $this->db->affected_rows() > 0;
    }

    public function getById($id)
    {
        $this->db->where('id', $id);

        $this->apply_legacy_invoice_scope();
        return $this->db->get(db_prefix() . 'scheduled_emails')->row();
    }

    public function get($rel_id, $rel_type)
    {
        $this->db->where('rel_id', $rel_id);
        $this->db->where('rel_type', $rel_type);

        $this->apply_legacy_invoice_scope();
        return $this->db->get(db_prefix() . 'scheduled_emails')->row();
    }
    private function apply_legacy_invoice_scope()
    {
        if (InvoiceIssuerScope::schemaReady($this->db, db_prefix() . 'invoices')) {
            $this->db->where("(rel_type != 'invoice' OR rel_id IN (SELECT id FROM " . db_prefix() . 'invoices WHERE ' . InvoiceIssuerScope::legacySql($this->db, db_prefix() . 'invoices') . '))', null, false);
        }
    }

}
