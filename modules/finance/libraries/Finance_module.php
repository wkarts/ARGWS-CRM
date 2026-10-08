<?php
defined("BASEPATH") or exit("No direct script access allowed");

class Finance_module
{
    private $ci;

    public function __construct()
    {
        $this->ci = &get_instance();
        $this->ci->load->database();
    }

    // Obtém as entradas (faturas pagas) com nome do cliente
    public function get_invoices()
    {
        $this->ci->db->select(
            "tblinvoices.date, tblinvoices.id as transacao, tblclients.company as descricao, tblinvoices.total as valor, 'entrada' as tipo"
        );
        $this->ci->db->join(
            "tblclients",
            "tblclients.userid = tblinvoices.clientid",
            "left"
        );
        $this->ci->db->where("tblinvoices.status", 2); // Filtra apenas faturas pagas
        $query = $this->ci->db->get("tblinvoices");
        return $query->result_array();
    }

    // Obtém as saídas (despesas) com o nome da despesa
    public function get_expenses()
    {
        $this->ci->db->select(
            "tblexpenses.date, tblexpenses.id as transacao, tblexpenses.expense_name as descricao, tblexpenses.amount as valor, 'saida' as tipo"
        );
        $query = $this->ci->db->get("tblexpenses");
        return $query->result_array();
    }

    // Combina as transações de entrada e saída, e ordena por data
    public function get_all_transactions()
    {
        $invoices = $this->get_invoices();
        $expenses = $this->get_expenses();
        $transactions = array_merge($invoices, $expenses);

        // Ordena as transações por data
        usort($transactions, function ($a, $b) {
            return strtotime($a["date"]) - strtotime($b["date"]);
        });

        return $transactions;
    }
}
