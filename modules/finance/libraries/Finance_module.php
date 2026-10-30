<?php
defined("BASEPATH") or exit("No direct script access allowed");
require_once __DIR__ . '/FinanceLegacyScope.php';

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
        $this->ci->db->from(db_prefix() . 'invoices AS finance_invoice');
        $this->ci->db->select(
            "finance_invoice.date, finance_invoice.id as transacao, finance_client.company as descricao, finance_invoice.total as valor, 'entrada' as tipo"
        );
        $this->ci->db->join(
            db_prefix() . 'clients AS finance_client',
            'finance_client.userid = finance_invoice.clientid',
            "left"
        );
        $this->ci->db->where('finance_invoice.status', 2); // Filtra apenas faturas pagas
        FinanceLegacyScope::invoices($this->ci->db, db_prefix(), 'finance_invoice');
        $query = $this->ci->db->get();
        return $query->result_array();
    }

    // Obtém as saídas (despesas) com o nome da despesa
    public function get_expenses()
    {
        $this->ci->db->from(db_prefix() . 'expenses AS finance_expense');
        $this->ci->db->select(
            "finance_expense.date, finance_expense.id as transacao, finance_expense.expense_name as descricao, finance_expense.amount as valor, 'saida' as tipo"
        );
        FinanceLegacyScope::expenses($this->ci->db, db_prefix(), 'finance_expense');
        $query = $this->ci->db->get();
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
