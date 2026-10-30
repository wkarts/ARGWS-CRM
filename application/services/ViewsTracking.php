<?php

namespace app\services;

defined('BASEPATH') or exit('No direct script access allowed');

class ViewsTracking
{
    public static function get($rel_type, $rel_id)
    {
        $CI = & get_instance();
        $CI->db->where('rel_id', $rel_id);
        $CI->db->where('rel_type', $rel_type);
        $CI->db->order_by('date', 'DESC');

        return $CI->db->get(db_prefix() . 'views_tracking')->result_array();
    }

    public static function create($rel_type, $rel_id)
    {
        // New page-view telemetry is disabled; local history remains readable via get().
        return false;
    }
}
