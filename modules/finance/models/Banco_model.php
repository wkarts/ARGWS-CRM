<?php

class Banco_model extends CI_Model
{
    public function get_all_banks()
    {
        return $this->db->get("tblbanks")->result_array();
    }
}
