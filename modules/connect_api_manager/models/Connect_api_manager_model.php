<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Connect_api_manager_model extends App_Model
{
    private $table;

    public function __construct()
    {
        parent::__construct();
        $this->table = db_prefix() . 'argws_connect_manager_instances';
        $this->load->library(CONNECT_API_MANAGER_MODULE . '/ConnectApiManagerSecureStore');
    }

    public function save_instance($name, $token, $integration = 'WHATSAPP-BAILEYS', $state = null, $phoneNumber = null)
    {
        $row = $this->db->where('instance_name', $name)->get($this->table)->row_array();
        $data = [
            'instance_name' => $name,
            'integration'   => $integration,
            'last_state'    => $state,
            'updated_at'    => date('Y-m-d H:i:s'),
        ];

        if ($phoneNumber !== null && (string) $phoneNumber !== '') {
            $data['phone_number'] = preg_replace('/\D+/', '', (string) $phoneNumber);
        }

        if ((string) $token !== '') {
            $data['instance_token'] = $this->connectapimanagersecurestore->encrypt($token);
        }

        if ($row) {
            $this->db->where('id', $row['id'])->update($this->table, $data);
            return (int) $row['id'];
        }

        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert($this->table, $data);

        return (int) $this->db->insert_id();
    }

    public function remove_instance($name)
    {
        return $this->db->where('instance_name', $name)->delete($this->table);
    }

    public function update_runtime_info($name, $phoneNumber = null, $state = null)
    {
        $data = ['updated_at' => date('Y-m-d H:i:s')];
        if ($phoneNumber !== null && (string) $phoneNumber !== '') {
            $data['phone_number'] = preg_replace('/\D+/', '', (string) $phoneNumber);
        }
        if ($state !== null && (string) $state !== '') {
            $data['last_state'] = (string) $state;
        }

        return $this->db->where('instance_name', $name)->update($this->table, $data);
    }

    public function get_instance_number($name)
    {
        $row = $this->db->select('phone_number')->where('instance_name', $name)->get($this->table)->row_array();
        return $row ? preg_replace('/\D+/', '', (string) ($row['phone_number'] ?? '')) : '';
    }

    public function is_local_instance($name)
    {
        return $this->db->where('instance_name', $name)->count_all_results($this->table) > 0;
    }

    public function has_instance_token($name)
    {
        $row = $this->db->select('instance_token')->where('instance_name', $name)->get($this->table)->row_array();
        if (!$row || empty($row['instance_token'])) {
            return false;
        }

        return $this->connectapimanagersecurestore->decrypt($row['instance_token']) !== '';
    }

    public function get_instance_token($name)
    {
        $row = $this->db->select('instance_token')->where('instance_name', $name)->get($this->table)->row_array();
        if (!$row || empty($row['instance_token'])) {
            return '';
        }

        return $this->connectapimanagersecurestore->decrypt($row['instance_token']);
    }

    public function get_local_instance($name)
    {
        $row = $this->db->where('instance_name', $name)->get($this->table)->row_array();
        if (!$row) {
            return null;
        }

        $row['instance_token_plain'] = $this->connectapimanagersecurestore->decrypt($row['instance_token'] ?? '');
        unset($row['instance_token']);

        return $row;
    }

    public function get_connector_instances()
    {
        $rows = $this->db->order_by('instance_name', 'ASC')->get($this->table)->result_array();
        $result = [];

        foreach ($rows as $row) {
            $token = $this->connectapimanagersecurestore->decrypt($row['instance_token'] ?? '');
            if ($token === '') {
                continue;
            }

            $result[] = [
                'instance_name'  => $row['instance_name'],
                'instance_token' => $token,
                'integration'    => $row['integration'],
                'phone_number'   => preg_replace('/\D+/', '', (string) ($row['phone_number'] ?? '')),
                'api_url'        => get_option('connect_api_manager_api_url'),
                'source'         => CONNECT_API_MANAGER_MODULE,
            ];
        }

        return $result;
    }
}
