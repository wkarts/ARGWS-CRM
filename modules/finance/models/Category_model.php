<?php
defined("BASEPATH") or exit("No direct script access allowed");

class Category_model extends App_Model
{
    public function __construct()
    {
        parent::__construct();
    }


    public function get_category($id)
    {
        return $this->db->where("id", $id)->get("tblcategories")->row();
    }



    public function delete_category($id)
    {
        $this->db->where("id", $id)->delete("tblcategories");
        return $this->db->affected_rows();
    }



public function get_all_categories()
{
    $this->db->select("id, name, type, father_category");
    return $this->db->get("tblcategories")->result_array();
}

public function add_category($data)
{
    $this->db->insert("tblcategories", $data);
    return $this->db->insert_id();
}

public function update_category($id, $data)
{
    $this->db->where("id", $id)->update("tblcategories", $data);
    return $this->db->affected_rows();
}


}
