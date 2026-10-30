<?php defined('BASEPATH') or exit('No direct script access allowed');
$nativeInvoicePreview = isset($native_invoice_preview_data) || isset($native_payment_form_data); ?>
<div class="table-responsive">
    <table class="table table-hover">
        <thead>
            <tr>
                <th>
                    <span
                        class="bold"><?= _l('payments_table_number_heading'); ?></span>
                </th>
                <th>
                    <span
                        class="bold"><?= _l('payments_table_mode_heading'); ?></span>
                </th>
                <th>
                    <span
                        class="bold"><?= _l('payments_table_date_heading'); ?></span>
                </th>
                <th>
                    <span
                        class="bold"><?= _l('payments_table_amount_heading'); ?></span>
                </th>
                <?php if (empty($nativeInvoicePreview)) { ?><th>
                    <span
                        class="bold"><?= _l('options'); ?></span>
                </th><?php } ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($invoice->payments as $payment) { ?>
            <tr class="payment">
                <td><?= e($payment['paymentid']); ?>
                    <?php if (empty($nativeInvoicePreview)) { ?>
                    <?= icon_btn('payments/pdf/' . $payment['paymentid'], 'fa-regular fa-file-pdf', 'btn-default pull-right'); ?>
                    <?php } ?>
                </td>
                <td><?= e($payment['name']); ?>
                    <?php if (! empty($payment['paymentmethod'])) {
                        echo ' - ' . $payment['paymentmethod'];
                    }
                if ($payment['transactionid']) {
                    echo '<br />' . _l('payments_table_transaction_id', $payment['transactionid']);
                }
                ?>
                </td>
                <td><?= e(_d($payment['date'])); ?>
                </td>
                <td><?= e(app_format_money($payment['amount'], $invoice->currency_name)); ?>
                    <?php if (!empty($nativeInvoicePreview) && (float)($payment['native_reversed_amount'] ?? 0) > 0) { ?>
                    <br><small class="text-muted"><?= e('Valor original: ' . app_format_money($payment['native_original_amount'], $invoice->currency_name)); ?><br><?= e('Estornado: ' . app_format_money($payment['native_reversed_amount'], $invoice->currency_name)); ?></small>
                    <?php } ?>
                    <?php if(!empty($native_invoice_preview_data)&&!empty($payment['native_can_reverse'])){ ?>
                    <details class="mtop10"><summary>Estornar recebimento manual</summary>
                        <?= form_open(admin_url('invoices/delete_payment/'.$payment['paymentid'].'/'.$invoice->id)); ?>
                        <?= form_hidden('revision',$native_payment_revision); ?>
                        <?= form_hidden('request_key',bin2hex(random_bytes(32))); ?>
                        <?= form_hidden('reversal[payment_id]',$payment['paymentid']); ?>
                        <p class="text-muted">Registra a correção local e conserva o recebimento e seu histórico.</p>
                        <label>Valor<input class="form-control" type="number" name="reversal[amount]" min="0.01" max="<?= e($payment['amount']); ?>" step="0.01" required></label>
                        <label>Data<input class="form-control" type="date" name="reversal[date]" value="<?= e(date('Y-m-d')); ?>" min="<?= e($payment['date']); ?>" max="<?= e(date('Y-m-d')); ?>" required></label>
                        <label>Referência<input class="form-control" name="reversal[reference]" maxlength="191" required></label>
                        <label>Motivo<input class="form-control" name="reversal[reason]" maxlength="4000" required></label>
                        <button class="btn btn-danger" type="submit">Registrar estorno</button>
                        <?= form_close(); ?>
                    </details>
                    <?php } ?>
                </td>
                <?php if (empty($nativeInvoicePreview)) { ?>
                <td>
                    <div class="tw-flex tw-items-center tw-space-x-2">
                        <a href="<?= admin_url('payments/payment/' . $payment['paymentid']); ?>"
                            class="tw-text-neutral-500 hover:tw-text-neutral-700 focus:tw-text-neutral-700">
                            <i class="fa-regular fa-pen-to-square fa-lg"></i>
                        </a>
                        <?php if (staff_can('delete', 'payments')) { ?>
                        <a href="<?= admin_url('invoices/delete_payment/' . $payment['paymentid'] . '/' . $payment['invoiceid']); ?>"
                            class="tw-text-neutral-500 hover:tw-text-neutral-700 focus:tw-text-neutral-700 _delete">
                            <i class="fa-regular fa-trash-can"></i>
                        </a>
                        <?php } ?>
                    </div>
                </td>
                <?php } ?>
            </tr>
            <?php } ?>
        </tbody>
    </table>
</div>
