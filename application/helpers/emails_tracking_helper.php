<?php

defined('BASEPATH') or exit('No direct script access allowed');

/** Return existing local email records for history and data-export compatibility. */
function get_tracked_emails($rel_id, $rel_type)
{
    $CI = &get_instance();
    $CI->db->where('rel_id', $rel_id);
    $CI->db->where('rel_type', $rel_type);
    $CI->db->order_by('date', 'desc');
    return $CI->db->get(db_prefix() . 'tracked_mails')->result_array();
}

/** Remove local history only when its related CRM record is removed. */
function delete_tracked_emails($rel_id, $rel_type)
{
    $CI = &get_instance();
    $CI->db->where('rel_id', $rel_id);
    $CI->db->where('rel_type', $rel_type);
    $CI->db->delete(db_prefix() . 'tracked_mails');
}

/** Compatibility hook callback; email-open pixels are no longer injected. */
function email_tracking_inject_in_body($template)
{
    return $template;
}

/** Compatibility callback; no new recipient tracking records are written. */
function add_email_tracking($data)
{
    return false;
}

function get_available_tracking_templates_slugs()
{
    return [];
}
