<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php $nativePayment=isset($native_payment);if(!$nativePayment)$payment_modes = \app\services\PaymentGatewayCatalog::forSelection($payment_modes, [$payment->paymentmode]); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="tw-max-w-4xl tw-mx-auto">
            <h4 class="tw-mt-0 tw-font-bold tw-text-lg tw-text-neutral-700">
                <?= _l('payment_edit_for_invoice'); ?>
                <a
                    href="<?= admin_url('invoices/list_invoices/' . $payment->invoiceid); ?>">
                    <?= e($nativePayment?$native_payment['invoice']['number']:format_invoice_number($payment->invoice->id)); ?>
                </a>
            </h4>
            <div class="horizontal-scrollable-tabs">
                <div class="scroller arrow-left"><i class="fa fa-angle-left"></i></div>
                <div class="scroller arrow-right"><i class="fa fa-angle-right"></i></div>
                <div class="horizontal-tabs">
                    <ul class="nav nav-tabs nav-tabs-horizontal nav-tabs-segmented tw-mb-3" role="tablist">
                        <li role="presentation" class="active">
                            <a href="#tab_receipt" aria-controls="tab_receipt" role="tab" data-toggle="tab">
                                <?= _l('payment_receipt'); ?>
                            </a>
                        </li>
                        <li role="presentation">
                            <a href="#tab_payment" aria-controls="tab_payment" role="tab" data-toggle="tab">
                                <?= _l('payment'); ?>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="tab-content">
                <div role="tabpanel" class="tab-pane active" id="tab_receipt">
                    <div class="panel_s">
                        <div class="panel-body">
                            <div class="tw-flex tw-justify-end tw-mb-6">
                                <div class="tw-self-start">
                                    <div class="btn-group">
                                        <?php if(!$nativePayment){ ?><a href="#" data-toggle="modal" data-target="#payment_send_to_client"
                                            class="payment-send-to-client btn-with-tooltip btn btn-default">
                                            <i class="fa-regular fa-envelope"></i></span>
                                        </a><?php } ?>

                                        <a href="#" class="btn btn-default dropdown-toggle" data-toggle="dropdown"
                                            aria-haspopup="true" aria-expanded="false">
                                            <i class="fa-regular fa-file-pdf"></i>
                                            <?php if (is_mobile()) {
                                                echo ' PDF';
                                            } ?> <span class="caret"></span>
                                        </a>

                                        <ul class="dropdown-menu dropdown-menu-right">
                                            <li class="hidden-xs">
                                                <a
                                                    href="<?= admin_url('payments/pdf/' . $payment->paymentid . '?output_type=I'); ?>">
                                                    <?= _l('view_pdf'); ?>
                                                </a>
                                            </li>
                                            <li class="hidden-xs">
                                                <a href="<?= admin_url('payments/pdf/' . $payment->paymentid . '?output_type=I'); ?>"
                                                    target="_blank">
                                                    <?= _l('view_pdf_in_new_window'); ?>
                                                </a>
                                            </li>
                                            <li>
                                                <a
                                                    href="<?= admin_url('payments/pdf/' . $payment->paymentid); ?>">
                                                    <?= _l('download'); ?>
                                                </a>
                                            </li>
                                            <li>
                                                <a href="<?= admin_url('payments/pdf/' . $payment->paymentid . '?print=true'); ?>"
                                                    target="_blank">
                                                    <?= _l('print'); ?>
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                    <?php if (!$nativePayment&&staff_can('delete', 'payments')) { ?>
                                    <a href="<?= admin_url('payments/delete/' . $payment->paymentid); ?>"
                                        class="btn btn-danger _delete">
                                        <i class="fa-regular fa-trash-can"></i>
                                    </a>
                                    <?php } ?>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 col-sm-6">
                                    <address class="tw-text-neutral-500">
                                        <?php if($nativePayment){echo e($native_payment['invoice']['identity']['legal_name']).'<br>'.e($native_payment['invoice']['identity']['tax_id']).'<br>'.e(CompanyDocumentBranding::address($native_payment['invoice']['identity']));}else echo format_organization_info(); ?>
                                    </address>
                                </div>
                                <div class="col-sm-6 text-right">
                                    <address class="tw-text-neutral-500">
                                        <?php if($nativePayment){echo e($native_payment['invoice']['recipient']['name']).'<br>'.e($native_payment['invoice']['recipient']['tax_id']);}else echo format_customer_info($payment->invoice, 'payment', 'billing', true); ?>
                                    </address>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <h3 class="text-uppercase tw-font-bold tw-text-neutral-700">
                                        <?= _l('payment_receipt'); ?>
                                    </h3>
                                </div>
                                <div class="col-md-12 tw-mt-4">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <p class="tw-text-neutral-600 tw-font-medium">
                                                <?= _l('payment_date'); ?>
                                                <span
                                                    class="pull-right"><?= e(_d($payment->date)); ?></span>
                                            </p>
                                            <hr class="tw-my-2" />
                                            <p class="tw-text-neutral-600 tw-font-medium">
                                                <?= _l('payment_view_mode'); ?>
                                                <span class="pull-right">
                                                    <?= e($payment->name); ?>
                                                    <?php if (! empty($payment->paymentmethod)) {
                                                        echo ' - ' . e($payment->paymentmethod);
                                                    }
?>
                                                </span>
                                            </p>
                                            <?php if (! empty($payment->transactionid)) { ?>
                                            <hr class="tw-my-2" />
                                            <p class="tw-text-neutral-600 tw-font-medium">
                                                <?= _l('payment_transaction_id'); ?>:
                                                <span
                                                    class="pull-right"><?= e($payment->transactionid); ?></span>
                                            </p>
                                            <?php } ?>
                                        </div>
                                        <div class="clearfix"></div>
                                        <div class="col-md-6">
                                            <div
                                                class="tw-py-3 tw-px-4 tw-rounded-lg tw-bg-neutral-100 tw-flex tw-flex-col tw-my-4">
                                                <span class="tw-font-medium">
                                                    <?= _l('payment_total_amount'); ?>
                                                </span>
                                                <span class="tw-font-bold">
                                                    <?= e(app_format_money($payment->amount, $payment->invoice->currency_name)); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-12 tw-mt-4">
                                    <h4 class="tw-font-semibold tw-text-base tw-text-neutral-800">
                                        <?= _l('payment_for_string'); ?>
                                    </h4>
                                    <div class="table-responsive">
                                        <table class="table table-bordered !tw-mt-0">
                                            <thead>
                                                <tr>
                                                    <th><?= _l('payment_table_invoice_number'); ?>
                                                    </th>
                                                    <th><?= _l('payment_table_invoice_date'); ?>
                                                    </th>
                                                    <th><?= _l('payment_table_invoice_amount_total'); ?>
                                                    </th>
                                                    <th><?= _l('payment_table_payment_amount_total'); ?>
                                                    </th>
                                                    <?php if ($payment->invoice->status != 2
        && $payment->invoice->status != 5) { ?>
                                                    <th><span
                                                            class="text-danger"><?= _l('invoice_amount_due'); ?></span>
                                                    </th>
                                                    <?php } ?>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td class="tw-font-medium">
                                                        <?= e($nativePayment?$native_payment['invoice']['number']:format_invoice_number($payment->invoice->id)); ?>
                                                    </td>
                                                    <td><?= e(_d($payment->invoice->date)); ?>
                                                    </td>
                                                    <td><?= e(app_format_money($payment->invoice->total, $payment->invoice->currency_name)); ?>
                                                    </td>
                                                    <td><?= e(app_format_money($payment->amount, $payment->invoice->currency_name)); ?>
                                                    </td>
                                                    <?php if ($payment->invoice->status != 2
            && $payment->invoice->status != 5) { ?>
                                                    <td class="text-danger">
                                                        <?= e(app_format_money(($nativePayment?$native_payment['balance']['receivable']:get_invoice_total_left_to_pay($payment->invoice->id, $payment->invoice->total)), $payment->invoice->currency_name)); ?>
                                                    </td>
                                                    <?php } ?>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div role="tabpanel" class="tab-pane" id="tab_payment">
                    <div class="panel_s">
                        <div class="panel-body">
                            <?= form_open($this->uri->uri_string()); ?>

                            <?php if($nativePayment){ ?>
                            <p>Valor original: <?= e($native_payment['invoice']['currency'].' '.$native_payment['payment']['original_amount']); ?> · Estornado: <?= e($native_payment['payment']['reversed_amount']); ?> · Efetivo: <?= e($payment->amount); ?></p>
                            <p>Data: <?= e($payment->date); ?> · Modo: <?= e($payment->name); ?> · Referência: <?= e($payment->transactionid); ?></p>
                            <p>Para corrigir o lançamento financeiro, registre o estorno autorizado e o novo recebimento. Ambos permanecem no histórico.</p>
                            <?= form_hidden('revision',$native_payment['revision']); ?><?= form_hidden('request_key',bin2hex(random_bytes(32))); ?>
                            <?= render_textarea('note','note',$payment->note,['rows'=>7]); ?>
                            <?php }else{ ?>
                            <?= render_input('amount', 'payment_edit_amount_received', $payment->amount, 'number'); ?>
                            <?= render_date_input('date', 'payment_edit_date', _d($payment->date)); ?>
                            <?= render_select('paymentmode', $payment_modes, ['id', 'name'], 'payment_mode', $payment->paymentmode); ?>
                            <i class="fa-regular fa-circle-question pull-left tw-mt-0.5 tw-mr-1" data-toggle="tooltip"
                                data-title="<?= _l('payment_method_info'); ?>"></i>
                            <?= render_input('paymentmethod', 'payment_method', $payment->paymentmethod); ?>
                            <?= render_input('transactionid', 'payment_transaction_id', $payment->transactionid); ?>
                            <?= render_textarea('note', 'note', $payment->note, ['rows' => 7]); ?>

                            <?php hooks()->do_action('before_admin_edit_payment_form_submit', $payment); } ?>

                            <div class="text-right">
                                <button type="submit"
                                    class="btn btn-primary" <?= $nativePayment&&!$native_payment_caps['edit']?'disabled':''; ?>><?= _l('submit'); ?></button>
                            </div>
                            <?= form_close(); ?>
                            <?php if($nativePayment&&$native_payment_caps['delete']&&$native_payment['payment']['can_reverse']){ ?>
                            <hr><h4>Estornar pagamento manual</h4>
                            <?= form_open(admin_url('payments/delete/'.$payment->paymentid),['id'=>'payment-reversal-form']); ?>
                            <?= form_hidden('revision',$native_payment['financial_revision']); ?><?= form_hidden('request_key',bin2hex(random_bytes(32))); ?><?= form_hidden('reversal[payment_id]',$payment->paymentid); ?>
                            <?= render_input('reversal[amount]','Valor',$native_payment['payment']['native_amount'],'text'); ?><?= render_input('reversal[date]','Data',gmdate('Y-m-d'),'date'); ?>
                            <?= render_input('reversal[reference]','Referência'); ?><?= render_textarea('reversal[reason]','Motivo'); ?>
                            <button type="submit" class="btn btn-danger">Confirmar estorno</button><?= form_close(); ?>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php if(!$nativePayment)$this->load->view('admin/payments/send_to_client'); ?>
<?php init_tail(); ?>
<script>
    $(function() {
        appValidateForm($('form'), <?= $nativePayment?'{}':"{amount:'required',date:'required'}"; ?>);
    });
</script>
</body>

</html>