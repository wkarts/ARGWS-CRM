<?php
defined("BASEPATH") or exit("No direct script access allowed");

class Conciliacao_model extends App_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    // Adiciona uma transação temporária à tabela de conciliações pendentes
    public function add_temp_transaction($data)
    {
        $this->db->insert("tblconciliacoes_pendentes", $data);
    }

     // Verifica se a transação já foi conciliada usando o ofx_id
     public function is_ofx_id_conciliado($ofx_id)
     {
         return $this->db
             ->where("ofx_id", $ofx_id)
             ->count_all_results("tblconciliacoes") > 0;
     }

    // Obtém todas as conciliações não conciliadas (conciliado = 0)
    public function get_all_conciliacoes()
    {
        return $this->db
            ->get_where("tblconciliacoes", ["conciliado" => 0])
            ->result_array();
    }

      // Verifica se uma transação com a mesma data, descrição e valor já foi conciliada
      public function is_transaction_conciliated($data, $descricao, $valor)
      {
          $this->db->where('data', $data);
          $this->db->where('descricao', $descricao);
          $this->db->where('valor', $valor);
          $this->db->where('conciliado', 1); // Somente registros já conciliados
  
          return $this->db->count_all_results("tblconciliacoes") > 0;
      }

      public function is_conciliado($data, $descricao, $valor)
{
    $this->db->where('data', $data);
    $this->db->where('descricao', $descricao);
    $this->db->where('valor', $valor);
    $this->db->where('conciliado', 1);
    return $this->db->count_all_results('tblconciliacoes') > 0;
}
    

    // Atualiza o status de uma transação para conciliado
    public function conciliar_transacao($id)
    {
        $this->db->where("id", $id);
        $this->db->update("tblconciliacoes", ["conciliado" => 1]);
    }

    // Obtém uma transação pendente pelo ID
    public function get_transacao_pendente($id)
    {
        $this->db->where("id", $id);
        $query = $this->db->get("tblconciliacoes_pendentes"); // Verifique o nome exato da tabela pendente
        return $query->row(); // Retorna a transação como um objeto
    }

   // Adiciona uma transação à tabela de conciliações apenas se ainda não estiver conciliada
   public function add_conciliacao($data)
   {
       return $this->db->insert("tblconciliacoes", $data);
   }

    // Remove uma transação pendente após conciliação
    public function remove_transacao_pendente($id)
    {
        $this->db->delete("tblconciliacoes_pendentes", ["id" => $id]);
    }

    // Obtém todas as transações pendentes de conciliação
    public function get_transacoes_pendentes()
    {
        return $this->db->get("tblconciliacoes_pendentes")->result_array();
    }

    // Verifica se uma transação específica já foi conciliada para evitar duplicidade
    public function is_transacao_conciliada($transacao_id)
    {
        return $this->db
            ->where("transacao_id", $transacao_id)
            ->count_all_results("tblconciliacoes") > 0;
    }
}
