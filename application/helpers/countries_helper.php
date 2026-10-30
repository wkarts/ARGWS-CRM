<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Get all countries stored in database
 *
 * @return array
 */
function get_all_countries()
{
    $countries = get_instance()->db->order_by('short_name', 'asc')->get(db_prefix() . 'countries')->result_array();
    if(class_exists('CompanyRequestScope',false)&&CompanyRequestScope::nativeFor(get_instance()))return $countries;
    return hooks()->apply_filters('all_countries', $countries);
}
/**
 * Get country row from database based on passed country id
 *
 * @param mixed $id
 *
 * @return object
 */
function get_country($id)
{
    $CI = &get_instance();
    if(class_exists('CompanyRequestScope',false)&&CompanyRequestScope::nativeFor($CI)){
        if(!is_scalar($id)||!preg_match('/^[1-9][0-9]{0,9}$/D',(string)$id))return null;
        return $CI->db->where('country_id',$id)->get(db_prefix().'countries')->row();
    }

    $country = $CI->app_object_cache->get('db-country-' . $id);

    if (! $country) {
        $CI->db->where('country_id', $id);
        $country = $CI->db->get(db_prefix() . 'countries')->row();
        $CI->app_object_cache->add('db-country-' . $id, $country);
    }

    return hooks()->apply_filters('get_country', $country);
}
/**
 * Get country short name by passed id
 *
 * @param mixed $id county id
 *
 * @return mixed
 */
function get_country_short_name($id)
{
    $country = get_country($id);
    if ($country) {
        return $country->iso2;
    }

    return '';
}
/**
 * Get country name by passed id
 *
 * @param mixed $id county id
 *
 * @return mixed
 */
function get_country_name($id)
{
    $country = get_country($id);
    if ($country) {
        return $country->short_name;
    }

    return '';
}
