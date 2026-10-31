<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php if(isset($native_portal_estimate)) { $record=$native_portal_estimate;$legacy=$native_portal_estimate_legacy;if($legacy){$number=$record['formatted_number'];$date=$record['date'];$due=$record['expirydate'];$currency=$record['currency'];$total=$record['total'];$items=$record['items'];$note=$record['clientnote'];$terms=$record['terms'];$custom=[];$pending=(int)$record['status']===2;}else{$display=CompanySalesPreferences::presentation('estimates',$record['number'],$record['context'],'',$record['payload']['date']);$number=$display['formatted_number'];$date=$record['payload']['date'];$due=$record['payload']['due_date'];$currency=$record['context']['currency']['name'];$total=$record['total'];$items=$record['payload']['items'];$note=$record['payload']['note'];$terms=$display['terms'];$custom=$record['custom_fields'];$pending=$record['state']==='open'&&(!isset($record['payload']['due_date'])||$record['payload']['due_date']>=date('Y-m-d'));} ?>
<div class="panel_s"><div class="panel-body"><h3 class="estimate-html-number"><?= e($number); ?></h3><p><?= e((string)($legacy?$record['issuer_name']:$record['context']['identity']['legal_name'])); ?><br><?= e((string)($legacy?$record['client_name']:$record['context']['recipient']['name'])); ?></p><p><?= e((string)$date); ?> · <?= e((string)($due??'')); ?></p><div class="table-responsive"><table class="table"><thead><tr><th><?= e(_l('estimate_table_item_heading')); ?></th><th><?= e(_l('estimate_table_quantity_heading')); ?></th><th><?= e(_l('estimate_table_rate_heading')); ?></th></tr></thead><tbody><?php foreach($items as$item) { if((string)($item['is_optional']??'0')==='1'&&(string)($item['is_selected']??'1')!=='1')continue; ?><tr><td><?= e((string)$item['description']); ?><?php if(($item['long_description']??'')!=='') { ?><br><?= nl2br(e((string)$item['long_description'])); ?><?php } ?></td><td><?= e((string)$item['qty']); ?> <?= e((string)($item['unit']??'')); ?></td><td><?= e((string)$item['rate']); ?></td></tr><?php } ?></tbody></table></div><p><?= e(_l('estimate_total')); ?>: <?= e($currency.' '.$total); ?></p><?php foreach($custom as$field) { ?><p><?= e($field['name']); ?>: <?= nl2br(e($field['value'])); ?></p><?php } ?><?php if($note!=='') { ?><p><?= nl2br(e((string)$note)); ?></p><?php } ?><?php if($terms!=='') { ?><p><?= nl2br(e((string)$terms)); ?></p><?php } ?>
<?php if($pending&&$legacy) { ?><p class="alert alert-warning">A resposta deste orçamento histórico permanece disponível somente para consulta.</p><?php } ?>
<?php if($pending&&!$legacy) { $decisionUrl=NativePortalPresentation::url('estimate/'.$record['id'].'/'.$native_portal_estimate_hash); ?>
<div class="tw-flex tw-flex-wrap tw-gap-2 tw-my-4 native-estimate-actions">
<?= form_open($decisionUrl,['class'=>'native-estimate-decline-form']); ?>
<?= form_hidden('_native_portal_csrf',$native_portal_csrf); ?>
<?= form_hidden('estimate_action','3'); ?>
<?= form_hidden('native_estimate_revision',$record['publication_revision']); ?>
<?= form_hidden('native_request_key',$native_portal_estimate_request_keys['declined']); ?>
<button type="submit" class="btn btn-default"><i class="fa fa-remove" aria-hidden="true"></i> <?= e(_l('clients_decline_estimate')); ?></button>
<?= form_close(); ?>
<?php if(!$record['signature_required']) { ?>
<?= form_open($decisionUrl,['class'=>'native-estimate-accept-form']); ?>
<?= form_hidden('_native_portal_csrf',$native_portal_csrf); ?>
<?= form_hidden('estimate_action','4'); ?>
<?= form_hidden('native_estimate_revision',$record['publication_revision']); ?>
<?= form_hidden('native_request_key',$native_portal_estimate_request_keys['accepted']); ?>
<button type="submit" class="btn btn-success"><i class="fa fa-check" aria-hidden="true"></i> <?= e(_l('clients_accept_estimate')); ?></button>
<?= form_close(); ?>
<?php } ?>
</div>
<?php if($record['signature_required']) { ?>
<?= form_open($decisionUrl,['class'=>'native-estimate-signature-form','id'=>'native-estimate-signature-form']); ?>
<?= form_hidden('_native_portal_csrf',$native_portal_csrf); ?>
<?= form_hidden('estimate_action','4'); ?>
<?= form_hidden('native_estimate_revision',$record['publication_revision']); ?>
<?= form_hidden('native_request_key',$native_portal_estimate_request_keys['accepted']); ?>
<?= form_hidden('signature',''); ?>
<fieldset><legend><?= e(_l('clients_accept_estimate')); ?></legend>
<div class="row"><div class="col-md-6"><label for="native-acceptance-firstname"><?= e(_l('client_firstname')); ?></label><input id="native-acceptance-firstname" class="form-control" name="acceptance_firstname" maxlength="191" required value="<?= e($native_portal_estimate_contact['firstname']); ?>"></div><div class="col-md-6"><label for="native-acceptance-lastname"><?= e(_l('client_lastname')); ?></label><input id="native-acceptance-lastname" class="form-control" name="acceptance_lastname" maxlength="191" required value="<?= e($native_portal_estimate_contact['lastname']); ?>"></div></div>
<div class="row mtop15"><div class="col-md-6"><label for="native-acceptance-email"><?= e(_l('client_email')); ?></label><input id="native-acceptance-email" type="email" class="form-control" name="acceptance_email" maxlength="191" required value="<?= e($native_portal_estimate_contact['email']); ?>"></div><div class="col-md-6"><label for="native-acceptance-title"><?= e(_l('contact_position')); ?></label><input id="native-acceptance-title" class="form-control" name="acceptance_title" maxlength="191"></div></div>
<div class="mtop15"><label for="native-estimate-signature-pad"><?= e(_l('signature')); ?></label><canvas id="native-estimate-signature-pad" width="640" height="180" class="form-control" style="height:180px;touch-action:none;background:#fff"></canvas><p id="native-estimate-signature-error" class="text-danger hide" role="alert">Informe a assinatura para aceitar o orçamento.</p><button type="button" id="native-estimate-signature-clear" class="btn btn-default btn-sm mtop5"><?= e(_l('clear')); ?></button></div>
<button type="submit" class="btn btn-success mtop15"><i class="fa fa-check" aria-hidden="true"></i> <?= e(_l('clients_accept_estimate')); ?></button>
</fieldset><?= form_close(); ?>
<script>
(function(){var form=document.getElementById('native-estimate-signature-form'),canvas=document.getElementById('native-estimate-signature-pad'),clear=document.getElementById('native-estimate-signature-clear'),error=document.getElementById('native-estimate-signature-error');if(!form||!canvas)return;var context=canvas.getContext('2d'),drawing=false,written=false;context.lineWidth=2;context.lineCap='round';context.strokeStyle='#111827';function point(event){var box=canvas.getBoundingClientRect();return{x:(event.clientX-box.left)*(canvas.width/box.width),y:(event.clientY-box.top)*(canvas.height/box.height)}}canvas.addEventListener('pointerdown',function(event){drawing=true;written=true;canvas.setPointerCapture(event.pointerId);var p=point(event);context.beginPath();context.moveTo(p.x,p.y);event.preventDefault()});canvas.addEventListener('pointermove',function(event){if(!drawing)return;var p=point(event);context.lineTo(p.x,p.y);context.stroke();event.preventDefault()});function stop(){drawing=false}canvas.addEventListener('pointerup',stop);canvas.addEventListener('pointercancel',stop);clear.addEventListener('click',function(){context.clearRect(0,0,canvas.width,canvas.height);written=false;form.elements.signature.value='';error.classList.add('hide')});form.addEventListener('submit',function(event){if(!written){event.preventDefault();error.classList.remove('hide');canvas.focus();return}form.elements.signature.value=canvas.toDataURL('image/png')})})();
</script>
<?php } ?>
<?php } ?>
<?php if(!$legacy&&is_array($native_portal_estimate_conversion_retry??null)) { $retry=$native_portal_estimate_conversion_retry; ?>
<div class="alert alert-info">A aceitação foi registrada. Conclua a geração da fatura usando o mesmo comprovante seguro.</div>
<?= form_open(NativePortalPresentation::url('estimate/'.$record['id'].'/'.$native_portal_estimate_hash),['class'=>'native-estimate-conversion-retry-form']); ?>
<?= form_hidden('_native_portal_csrf',$native_portal_csrf); ?>
<?= form_hidden('estimate_action','accepted'); ?>
<?= form_hidden('native_estimate_revision',$retry['revision']); ?>
<?= form_hidden('native_request_key',$retry['request_key']); ?>
<button type="submit" class="btn btn-success"><i class="fa fa-refresh" aria-hidden="true"></i> Concluir geração da fatura</button>
<?= form_close(); ?>
<?php } ?>
<?php if(!$legacy&&is_array($record['signature']??null)) { $signature=$record['signature']; ?><section aria-label="Assinatura"><h4>Assinatura</h4><p><?= e($signature['firstname'].' '.$signature['lastname']); ?><br><?= e($signature['email']); ?><br><?= e($signature['title']); ?><br><?= e($signature['date']); ?><br><?= e($signature['ip']); ?></p><img src="<?= e($signature['image']); ?>" alt="Assinatura" style="max-width:200px;max-height:100px"></section><?php } ?>
<?php foreach($native_portal_estimate_files as$file) { $external=$file['external']??null;$url=$external??(NativePortalPresentation::url('download/file/sales_attachment/'.$file['file_key']).'&estimate_id='.$record['id'].'&estimate_hash='.$native_portal_estimate_hash); ?><p><a href="<?= e($url); ?>"<?= $external!==null?' target="_blank" rel="noopener noreferrer"':''; ?>><?= e($file['filename']); ?></a></p><?php } ?>
<a class="btn btn-default" href="<?= e(NativePortalPresentation::url('clients/estimates')); ?>"><?= e(_l('client_go_to_dashboard')); ?></a><?= form_open(NativePortalPresentation::url('estimate/'.$record['id'].'/'.$native_portal_estimate_hash)); ?><?= form_hidden('_native_portal_csrf',$native_portal_csrf); ?><button type="submit" name="estimatepdf" value="estimatepdf" class="btn btn-default"><?= e(_l('clients_estimate_html_btn_download')); ?></button><?= form_close(); ?></div></div><?php return; } ?>
<div class="mtop15 preview-top-wrapper">
    <div class="row">
        <div class="col-md-3">
            <div class="mbot30">
                <div class="estimate-html-logo">
                    <?= get_dark_company_logo(); ?>
                </div>
            </div>
        </div>
        <div class="clearfix"></div>
    </div>
    <div class="top" data-sticky data-sticky-class="preview-sticky-header">
        <div class="container preview-sticky-container">
            <div class="sm:tw-flex sm:tw-justify-between -tw-mx-4">
                <div class="sm:tw-self-end">
                    <h3 class="bold tw-my-0 estimate-html-number">
                        <span class="sticky-visible hide tw-mb-2">
                            <?= e(format_estimate_number($estimate->id)); ?>
                        </span>
                    </h3>
                    <span class="estimate-html-status">
                        <?= format_estimate_status($estimate->status, '', true); ?>
                    </span>
                </div>

                <div class="tw-flex tw-items-end tw-space-x-2 tw-mt-3 sm:tw-mt-0">
                    <?= form_open($this->uri->uri_string(), ['class' => 'action-button']); ?>
                    <button type="submit" name="estimatepdf" class="btn btn-default action-button download"
                        value="estimatepdf">
                        <i class="fa-regular fa-file-pdf"></i>
                        <?= _l('clients_invoice_html_btn_download'); ?>
                    </button>
                    <?= form_close(); ?>
                    <?php if (is_client_logged_in() && has_contact_permission('estimates')) { ?>
                    <a href="<?= site_url('clients/estimates/'); ?>"
                        class="btn btn-default action-button go-to-portal">
                        <?= _l('client_go_to_dashboard'); ?>
                    </a>
                    <?php } ?>
                    <?php
                    // Is not accepted, declined and expired
                  if ($estimate->status != 4 && $estimate->status != 3 && $estimate->status != 5) {
                      echo form_open($this->uri->uri_string(), ['class' => 'action-button']);
                      echo form_hidden('estimate_action', 3);
                      echo '<button type="submit" data-loading-text="' . _l('wait_text') . '" autocomplete="off" class="btn btn-default action-button decline"><i class="fa fa-remove"></i> ' . _l('clients_decline_estimate') . '</button>';
                      echo form_close();
                  }
// Is not accepted, declined and expired
if ($estimate->status != 4 && $estimate->status != 3 && $estimate->status != 5) {
    $can_be_accepted = true;
    if ($identity_confirmation_enabled == '0') {
        echo form_open($this->uri->uri_string(), ['class' => 'action-button']);
        echo form_hidden('estimate_action', 4);
        echo '<button type="submit" data-loading-text="' . _l('wait_text') . '" autocomplete="off" class="btn btn-success action-button accept"><i class="fa fa-check"></i> ' . _l('clients_accept_estimate') . '</button>';
        echo form_close();
    } else {
        echo '<button type="button" id="accept_action" class="btn btn-success action-button accept"><i class="fa fa-check"></i> ' . _l('clients_accept_estimate') . '</button>';
    }
} elseif ($estimate->status == 3) {
    if (($estimate->expirydate >= date('Y-m-d') || ! $estimate->expirydate) && $estimate->status != 5) {
        $can_be_accepted = true;
        if ($identity_confirmation_enabled == '0') {
            echo form_open($this->uri->uri_string(), ['class' => 'action-button']);
            echo form_hidden('estimate_action', 4);
            echo '<button type="submit" data-loading-text="' . _l('wait_text') . '" autocomplete="off" class="btn btn-success action-button accept"><i class="fa fa-check"></i> ' . _l('clients_accept_estimate') . '</button>';
            echo form_close();
        } else {
            echo '<button type="button" id="accept_action" class="btn btn-success action-button accept"><i class="fa fa-check"></i> ' . _l('clients_accept_estimate') . '</button>';
        }
    }
}
?>
                </div>
            </div>
        </div>
    </div>
    <div class="clearfix"></div>
    <div class="panel_s tw-mt-6">
        <div class="panel-body">
            <div class="col-md-10 col-md-offset-1">
                <div class="row mtop20">
                    <div class="col-md-6 col-sm-6 transaction-html-info-col-left">
                        <h4 class="bold estimate-html-number">
                            <?= e(format_estimate_number($estimate->id)); ?>
                        </h4>
                        <address class="estimate-html-company-info tw-text-neutral-500 tw-text-normal">
                            <?= format_organization_info(); ?>
                        </address>
                    </div>
                    <div class="col-sm-6 text-right transaction-html-info-col-right">
                        <span class="tw-font-medium tw-text-neutral-600 estimate_to">
                            <?= _l('estimate_to'); ?>
                        </span>
                        <address class="estimate-html-customer-billing-info tw-text-neutral-500 tw-text-normal">
                            <?= format_customer_info($estimate, 'estimate', 'billing'); ?>
                        </address>
                        <!-- shipping details -->
                        <?php if ($estimate->include_shipping == 1 && $estimate->show_shipping_on_estimate == 1) { ?>
                        <span class="tw-font-medium tw-text-neutral-700 estimate_ship_to">
                            <?= _l('ship_to'); ?>
                        </span>
                        <address class="estimate-html-customer-shipping-info tw-text-neutral-500 tw-text-normal">
                            <?= format_customer_info($estimate, 'estimate', 'shipping'); ?>
                        </address>
                        <?php } ?>
                        <p class="estimate-html-date tw-mb-0 tw-text-normal">
                            <span class="tw-font-medium tw-text-neutral-700">
                                <?= _l('estimate_data_date'); ?>:
                            </span>
                            <?= e(_d($estimate->date)); ?>
                        </p>
                        <?php if (! empty($estimate->expirydate)) { ?>
                        <p class="estimate-html-expiry-date tw-mb-0 tw-text-normal">
                            <span class="tw-font-medium tw-text-neutral-700">
                                <?= _l('estimate_data_expiry_date'); ?>:
                            </span>
                            <?= e(_d($estimate->expirydate)); ?>
                        </p>
                        <?php } ?>
                        <?php if (! empty($estimate->reference_no)) { ?>
                        <p class="estimate-html-reference-no tw-mb-0 tw-text-normal">
                            <span
                                class="tw-font-medium tw-text-neutral-700"><?= _l('reference_no'); ?>:</span>
                            <?= e($estimate->reference_no); ?>
                        </p>
                        <?php } ?>
                        <?php if ($estimate->sale_agent && get_option('show_sale_agent_on_estimates') == 1) { ?>
                        <p class="estimate-html-sale-agent tw-mb-0 tw-text-normal">
                            <span
                                class="tw-font-medium tw-text-neutral-700"><?= _l('sale_agent_string'); ?>:</span>
                            <?= e(get_staff_full_name($estimate->sale_agent)); ?>
                        </p>
                        <?php } ?>
                        <?php if ($estimate->project_id && get_option('show_project_on_estimate') == 1) { ?>
                        <p class="estimate-html-project tw-mb-0 tw-text-normal">
                            <span
                                class="tw-font-medium tw-text-neutral-700"><?= _l('project'); ?>:</span>
                            <?= e(get_project_name_by_id($estimate->project_id)); ?>
                        </p>
                        <?php } ?>
                        <?php $pdf_custom_fields = get_custom_fields('estimate', ['show_on_pdf' => 1, 'show_on_client_portal' => 1]);

foreach ($pdf_custom_fields as $field) {
    $value = get_custom_field_value($estimate->id, $field['id'], 'estimate');
    if ($value == '') {
        continue;
    } ?>
                        <p class="tw-mb-0 tw-text-normal">
                            <span class="tw-font-medium tw-text-neutral-700">
                                <?= e($field['name']); ?>:
                            </span>
                            <?= $value; ?>
                        </p>
                        <?php } ?>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <div class="table-responsive">
                            <?php $items = get_items_table_data($estimate, 'estimate'); ?>
                            <?= $items->table(); ?>
                        </div>
                    </div>
                    <div class="col-md-6 col-md-offset-6">
                        <table class="table text-right tw-text-normal">
                            <tbody>
                                <tr id="subtotal">
                                    <td>
                                        <span class="bold tw-text-neutral-700">
                                            <?= _l('estimate_subtotal'); ?>
                                        </span>
                                    </td>
                                    <td class="subtotal">
                                        <?= e(app_format_money($estimate->subtotal, $estimate->currency_name)); ?>
                                    </td>
                                </tr>
                                <?php if (is_sale_discount_applied($estimate)) { ?>
                                <tr>
                                    <td>
                                        <span
                                            class="bold tw-text-neutral-700"><?= _l('estimate_discount'); ?>
                                            <?php if (is_sale_discount($estimate, 'percent')) { ?>
                                            (<?= e(app_format_number($estimate->discount_percent, true)); ?>%)
                                            <?php } ?>
                                        </span>
                                    </td>
                                    <td class="discount">
                                        <?= e('-' . app_format_money($estimate->discount_total, $estimate->currency_name)); ?>
                                    </td>
                                </tr>
                                <?php } ?>
                                <?php
        foreach ($items->taxes() as $tax) {
            echo '<tr class="tax-area"><td class="bold !tw-text-neutral-700">' . e($tax['taxname']) . ' (' . e(app_format_number($tax['taxrate'])) . '%)</td><td>' . e(app_format_money($tax['total_tax'], $estimate->currency_name)) . '</td></tr>';
        }
?>
                                <?php if ((int) $estimate->adjustment != 0) { ?>
                                <tr>
                                    <td>
                                        <span class="bold tw-text-neutral-700">
                                            <?= _l('estimate_adjustment'); ?>
                                        </span>
                                    </td>
                                    <td class="adjustment">
                                        <?= e(app_format_money($estimate->adjustment, $estimate->currency_name)); ?>
                                    </td>
                                </tr>
                                <?php } ?>
                                <tr>
                                    <td>
                                        <span class="bold tw-text-neutral-700">
                                            <?= _l('estimate_total'); ?>
                                        </span>
                                    </td>
                                    <td class="total">
                                        <?= e(app_format_money($estimate->total, $estimate->currency_name)); ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <?php hooks()->do_action('after_total_summary_estimatehtml', $estimate); ?>
                    <?php
               if (get_option('total_to_words_enabled') == 1) { ?>
                    <div class="col-md-12 text-center estimate-html-total-to-words">
                        <p class="tw-font-medium">
                            <?= _l('num_word'); ?>:<span
                                class="tw-text-neutral-500">
                                <?= $this->numberword->convert($estimate->total, $estimate->currency_name); ?>
                            </span>
                        </p>
                    </div>
                    <?php } ?>
                    <?php if (count($estimate->attachments) > 0 && $estimate->visible_attachments_to_customer_found == true) { ?>
                    <div class="clearfix"></div>
                    <div class="estimate-html-files">
                        <div class="col-md-12">
                            <hr />
                            <p class="bold mbot15 font-medium">
                                <?= _l('estimate_files'); ?>
                            </p>
                        </div>
                        <?php foreach ($estimate->attachments as $attachment) {
                            // Do not show hidden attachments to customer
                            if ($attachment['visible_to_customer'] == 0) {
                                continue;
                            }
                            $attachment_url = site_url('download/file/sales_attachment/' . $attachment['attachment_key']);
                            if (! empty($attachment['external'])) {
                                $attachment_url = $attachment['external_link'];
                            } ?>
                        <div class="col-md-12 mbot15">
                            <div class="pull-left"><i
                                    class="<?= get_mime_class($attachment['filetype']); ?>"></i>
                            </div>
                            <a
                                href="<?= e($attachment_url); ?>"><?= e($attachment['file_name']); ?></a>
                        </div>
                        <?php
                        } ?>
                    </div>
                    <?php } ?>
                    <?php if (! empty($estimate->clientnote)) { ?>
                    <div class="col-md-12 estimate-html-note">
                        <p class="tw-mb-2.5 tw-font-medium">
                            <b><?= _l('estimate_note'); ?></b>
                        </p>
                        <div class="tw-text-neutral-500">
                            <?= process_text_content_for_display($estimate->clientnote); ?>
                        </div>
                    </div>
                    <?php } ?>
                    <?php if (! empty($estimate->terms)) { ?>
                    <div class="col-md-12 estimate-html-terms-and-conditions">
                        <hr />
                        <p class="tw-mb-2.5 tw-font-medium">
                            <b><?= _l('terms_and_conditions'); ?></b>
                        </p>
                        <div class="tw-text-neutral-500">
                            <?= process_text_content_for_display($estimate->terms); ?>
                        </div>
                    </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
    <?php
   if ($identity_confirmation_enabled == '1' && $can_be_accepted) {
       get_template_part('identity_confirmation_form', ['formData' => form_hidden('estimate_action', 4)]);
   }
?>
    <script>
        $(function() {
            new Sticky('[data-sticky]');
            $('.select-item').on('change', function() {
                const $el = $(this);
                const isChecked = $el.prop('checked') ? 1 : 0;
                const itemId = $el.closest('tr').data('item-id');

                $.post(window.location.href, {
                    item_id: itemId,
                    choosen: isChecked,
                    action: 'estimate_item_selection_changed'
                }).done(function() {
                    window.location.reload();
                });
            });
        })
    </script>
