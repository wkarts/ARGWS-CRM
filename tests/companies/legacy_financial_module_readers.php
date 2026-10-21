<?php
/** Native Finance/Accounting model isolation with real CI3 SQL. */
declare(strict_types=1);
require __DIR__ . '/legacy_financial_module_fixture.php';

$observed = [];
$expected = [];
foreach (['tbl','module454_'] as $prefix) {
    $GLOBALS['moduleReaderPrefix'] = $prefix; $db->set_dbprefix($prefix); $db->reset_query();
    moduleReaderFixture($db, db_prefix());
    $finance = new Finance_model();
    $library = new Finance_module();
    $accounting = new Accounting_model();
    $historyBefore = $db->query('SELECT * FROM ' . db_prefix() . 'acc_account_history ORDER BY id')->result_array();
    $check = static function (string $name, callable $call, $want) use (&$observed, &$expected, $prefix): void {
        $name = $prefix . ':' . $name;
        $observed[$name] = moduleReaderCapture($call); $expected[$name] = $want;
    };
    $check('finance.paid_total', fn() => (float)$finance->calculate_total_revenue(), 100.0);
    $check('finance.expense_total', fn() => (float)$finance->calculate_total_expense(), 160.0);
    $check('finance.forecast_total', fn() => array_sum(array_map('floatval', array_column($finance->get_faturas_previsao(), 'valor'))), 60.0);
    $check('finance.forecast_30_days', fn() => array_sum(array_map('floatval', array_column($finance->get_invoice_forecast_30_days(), 'daily_total'))), 60.0);
    $check('finance.reconciliation_ids', fn() => moduleReaderIds($finance->get_transacoes_para_conciliacao()), [101,106,201,207,208]);
    $check('finance.expense_rows', fn() => count($finance->get_despesas()), 3);
    $check('finance.paid_entries', fn() => count($finance->get_all_invoices_as_entries()), 1);
    $check('finance.library_invoice_ids', fn() => moduleReaderIds($library->get_invoices(), 'transacao'), [101]);
    $check('finance.library_expense_ids', fn() => moduleReaderIds($library->get_expenses(), 'transacao'), [201,207,208]);
    $check('accounting.invoice_select_ids', fn() => moduleReaderIds($accounting->get_data_invoices_for_select()), [101,106]);
    $check('accounting.invoice_count', fn() => $accounting->count_invoice_not_convert_yet(), 2);
    $check('accounting.payment_count', fn() => $accounting->count_payment_not_convert_yet(), 2);
    $check('accounting.expense_count', fn() => (int)$accounting->count_expense_not_convert_yet(), 3);
    $check('accounting.bill_ids', fn() => moduleReaderIds($accounting->get_bills()), [201,207,208]);
    $check('accounting.bill_list_ids', fn() => moduleReaderIds($accounting->get_bill()), [201,207,208]);
    $check('accounting.foreign_direct_bill', fn() => $accounting->get_bill(203) === null, true);
    $check('accounting.legacy_direct_bill', fn() => (int)$accounting->get_bill(201)->id, 201);
    $check('accounting.history_preserved', fn() => $db->query('SELECT * FROM ' . db_prefix() . 'acc_account_history ORDER BY id')->result_array(), $historyBefore);
    $check('accounting.foreign_bill_mapping', fn() => $accounting->get_bill_mapping_details(203, 'debit'), []);
    foreach (['invoice'=>103, 'payment'=>303, 'expense'=>203] as $type=>$id) {
        $db->query('INSERT INTO ' . db_prefix() . 'acc_account_history (id,rel_id,rel_type,debit) VALUES (?,?,?,73)', [10,$id,$type]);
        $method = 'automatic_' . $type . '_conversion';
        $check('accounting.foreign_' . $type . '_conversion', fn() => $accounting->$method($id), false);
        $check('accounting.foreign_' . $type . '_history_not_deleted', fn() => (int)$db->query('SELECT COUNT(*) AS n FROM ' . db_prefix() . 'acc_account_history WHERE id=10')->row()->n, 1);
        $db->query('DELETE FROM ' . db_prefix() . 'acc_account_history WHERE id=10');
    }
    $db->query('INSERT INTO ' . db_prefix() . 'acc_account_history (id,rel_id,rel_type,debit) VALUES (10,103,\'invoice\',73)');
    $check('accounting.foreign_invoice_status', fn() => $accounting->invoice_status_changed(['invoice_id'=>103,'status'=>2]), false);
    $check('accounting.foreign_invoice_paid_not_changed', fn() => (int)$db->query('SELECT paid FROM ' . db_prefix() . 'acc_account_history WHERE id=10')->row()->paid, 0);

    $check('accounting.mapping_private_direct', fn() => $accounting->get_bill_mapping(203), null);
    $check('accounting.mapping_legacy_direct', fn() => (int)$accounting->get_bill_mapping(201)->bill_id, 201);
    $check('accounting.mapping_list', fn() => moduleReaderIds($accounting->get_bill_mapping(), 'bill_id'), [201,207,208]);
    $check('accounting.foreign_bill_payments', fn() => $accounting->get_list_pay_bill(203), []);
    $check('accounting.foreign_bill_approve', fn() => $accounting->bill_appove_payable(203), false);
    $check('accounting.foreign_bill_status', fn() => $accounting->bill_status_change(203, 2), false);
    $check('accounting.foreign_bill_void', fn() => $accounting->void_bill(203, ['reason_for_void'=>'synthetic']), false);
    $check('accounting.foreign_bill_delete', fn() => $accounting->delete_bill(203), false);
    $check('accounting.foreign_bill_delete_file', fn() => $accounting->delete_bill_attachment(203), false);
    $check('accounting.foreign_bill_update', fn() => $accounting->update_bill([], 203), false);
    $check('accounting.bill_update_private_client', fn() => $accounting->update_bill(['clientid'=>103], 201), false);
    $check('accounting.bill_update_private_invoice', fn() => $accounting->update_bill(['invoiceid'=>103], 201), false);
    $check('accounting.bill_create_private_client', fn() => $accounting->add_bill(['clientid'=>103]), false);
    $check('accounting.bill_create_private_invoice', fn() => $accounting->add_bill(['clientid'=>0,'invoiceid'=>103]), false);
    $check('accounting.bill_create_orphan_invoice', fn() => $accounting->add_bill(['clientid'=>0,'invoiceid'=>999]), false);
    $check('accounting.foreign_bill_intact', fn() => (float)$db->query('SELECT amount FROM ' . db_prefix() . 'expenses WHERE id=203')->row()->amount, 30.0);
    // Cleanup tests must use existence, never the reader's legacy visibility predicate.
    $db->query('DELETE FROM ' . db_prefix() . 'acc_account_history');
    foreach ([1=>['invoice',103],2=>['payment',303],3=>['expense',203],4=>['invoice',101],5=>['invoice',999]] as $rowId=>[$type,$id]) {
        $db->query('INSERT INTO ' . db_prefix() . 'acc_account_history (id,rel_id,rel_type,debit) VALUES (?,?,?,73)', [$rowId,$id,$type]);
    }
    $preserved = $db->query('SELECT * FROM ' . db_prefix() . 'acc_account_history WHERE id<5 ORDER BY id')->result_array();
    foreach (['invoice'=>103,'payment'=>303,'expense'=>203] as $type=>$id) {
        $check('accounting.cleanup_exists_' . $type, fn() => $accounting->check_transaction_exists($id,$type), true);
    }
    $check('accounting.cleanup_only_orphan', fn() => $accounting->remove_invalid_entries(), true);
    $check('accounting.cleanup_preserves_all_existing_origins', fn() => $db->query('SELECT * FROM ' . db_prefix() . 'acc_account_history ORDER BY id')->result_array(), $preserved);

    // Simulated partial schema and genuine pre-foundation semantics are separate fixtures.
    moduleReaderFixture($db, db_prefix());
    $db->query('ALTER TABLE ' . db_prefix() . 'invoices DROP COLUMN issuer_id'); $db->data_cache=[];
    $check('partial.invoice_column_missing_finance', fn() => (float)$finance->calculate_total_revenue(), 0.0);
    $check('partial.invoice_column_missing_accounting', fn() => $accounting->count_invoice_not_convert_yet(), 0);
    $check('partial.invoice_column_missing_payments', fn() => $accounting->count_payment_not_convert_yet(), 0);
    $check('partial.linked_expense_origin_ambiguous', fn() => moduleReaderIds($accounting->get_bills()), [201,207]);
    $db->query('DROP TABLE ' . db_prefix() . 'issuers_clients'); $db->data_cache=[];
    $check('partial.owners_missing_finance', fn() => (float)$finance->calculate_total_expense(), 0.0);
    $check('partial.owners_missing_accounting', fn() => $accounting->get_bills(), []);
    $db->query('DROP TABLE ' . db_prefix() . 'issuers_registry'); $db->data_cache=[];
    $check('partial.links_only_still_fenced', fn() => (float)$finance->calculate_total_revenue(), 0.0);
    $db->query('DROP TABLE ' . db_prefix() . 'issuers_client_links'); $db->data_cache=[];
    $check('pre_foundation.native_invoice_behavior', fn() => (float)$finance->calculate_total_revenue(), 1500.0);
    $check('pre_foundation.native_invoice_count', fn() => $accounting->count_invoice_not_convert_yet(), 9);
    $check('pre_foundation.payment_requires_existing_invoice', fn() => $accounting->count_payment_not_convert_yet(), 9);
    $check('pre_foundation.expense_requires_existing_invoice', fn() => (float)$finance->calculate_total_expense(), 390.0);
    moduleReaderFixture($db, db_prefix());
    $db->query('ALTER TABLE ' . db_prefix() . 'expenses DROP COLUMN invoiceid'); $db->data_cache=[];
    $check('partial.expense_origin_column_missing', fn() => (float)$finance->calculate_total_expense(), 0.0);
    $check('partial.expense_origin_column_missing_accounting', fn() => $accounting->get_bills(), []);
}
$failures = [];
foreach ($expected as $name => $value) if ($observed[$name] !== $value) $failures[$name] = ['expected'=>$value,'actual'=>$observed[$name]];
echo json_encode(['runtime'=>PHP_VERSION,'engine'=>$db->query('SELECT VERSION() AS version')->row()->version,
    'checks'=>count($expected),'passed'=>count($expected)-count($failures),'acceptance'=>$failures?'FAIL':'PASS',
    'failures'=>$failures,'observed'=>$observed], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
exit($failures ? 1 : 0);
