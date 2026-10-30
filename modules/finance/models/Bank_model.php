<?php
defined("BASEPATH") or exit("No direct script access allowed");

class Bank_model extends App_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    // Função para listar todos os bancos
    public function get_all_banks()
    {
        return $this->db->get("tblbanks")->result_array();
    }

    // Função para obter um banco específico
    public function get_bank($id)
    {
        return $this->db->where("id", $id)->get("tblbanks")->row();
    }

    // Função para adicionar um novo banco
    public function add_bank($data)
    {
        $this->db->insert("tblbanks", $data);
        return $this->db->insert_id();
    }

    // Função para atualizar um banco
    public function update_bank($id, $data)
    {
        $this->db->where("id", $id);
        return $this->db->update("tblbanks", $data);
    }

    // Função para deletar um banco
    public function delete_bank($id)
    {
        $this->db->where("id", $id);
        return $this->db->delete("tblbanks");
    }
}
