<?php defined('BASEPATH') or exit('No direct script access allowed');
$esc=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$type=$kind==='estimates'?'estimate':'proposal';$id=$detail['id'];
$url=fn($q='')=>CompanyRequestScope::adminUrl($this,$native_sales_controller).($q===''?'':'?'.ltrim($q,'?&'));
$hidden=function($action)use($esc,$company_sales_csrf){echo '<input type="hidden" name="action" value="'.$esc($action).'"><input type="hidden" name="company_sales_csrf" value="'.$esc($company_sales_csrf).'"><input type="hidden" name="request_key" value="'.bin2hex(random_bytes(32)).'">';};
require_once APPPATH.'services/NativeCompanySalesTable.php';
require_once APPPATH.'services/NativeCompanyContentHtml.php';
$amounts=$detail['origin']==='legacy'?$detail['stored_amounts']:CompanyInvoiceLines::calculate($detail['payload']['items'],array_intersect_key($detail['payload'],array_flip(['discount_mode','discount_value','discount_type','adjustment','calculation_contract'])));
$notesAvailable=$this->db->table_exists(db_prefix().'company_notes');
?>
<div class="col-md-12 no-padding" data-native-sales-preview="<?= $esc($id); ?>">
    <div class="panel_s"><div class="panel-body">
        <?php if($error){ ?><div class="alert alert-danger" role="alert"><?= $esc($error); ?></div><?php } ?>
        <div class="horizontal-scrollable-tabs preview-tabs-top panel-full-width-tabs">
            <div class="scroller arrow-left"><i class="fa fa-angle-left"></i></div>
            <div class="scroller arrow-right"><i class="fa fa-angle-right"></i></div>
            <div class="horizontal-tabs"><ul class="nav nav-tabs nav-tabs-horizontal mbot15" role="tablist">
                <li class="active" role="presentation"><a href="#tab_<?= $type; ?>" role="tab" data-toggle="tab"><?= _l($type); ?></a></li>
                <?php if($notesAvailable){ ?><li role="presentation"><a href="#tab_notes" role="tab" data-toggle="tab" data-native-sales-notes="<?= $esc($id); ?>"><?= _l($type.'_notes'); ?></a></li><?php } ?>
                <li role="presentation"><a href="#tab_activity" role="tab" data-toggle="tab"><?= _l($type.'_view_activity_tooltip'); ?></a></li>
                <li role="presentation"><a href="#tab_attachments" role="tab" data-toggle="tab"><?= _l('attachments'); ?></a></li>
                <li role="presentation"><a href="#tab_context" role="tab" data-toggle="tab"><?= _l('settings'); ?></a></li>
                <li role="presentation" class="tab-separator toggle_view"><a href="#" onclick="small_table_full_view(); return false;" title="<?= _l('toggle_full_view'); ?>"><i class="fa fa-expand"></i></a></li>
            </ul></div>
        </div>
        <div class="row mtop20"><div class="col-md-3"><span class="label label-default mtop5 inline-block"><?= $esc(NativeCompanySalesTable::statusLabel($kind,(int)$detail['native_status'])); ?></span></div>
            <div class="col-md-9"><div class="pull-right _buttons">
                <?php if($can_edit&&($detail['origin']==='owned'?$detail['state']==='open':$detail['editable'])){ ?><a class="btn btn-default btn-with-tooltip sm:!tw-px-3" href="<?= $esc(CompanyRequestScope::adminUrl($this,$kind.'/'.$type.'/'.$id)); ?>" title="<?= _l('edit'); ?>"><i class="fa-regular fa-pen-to-square"></i></a><?php } ?>
                <a class="btn btn-default btn-with-tooltip sm:!tw-px-3" href="<?= $esc($url('document='.$id.'&pdf=1'.($detail['origin']==='legacy'?'&source=legacy':''))); ?>" title="<?= _l('view_pdf'); ?>"><i class="fa-regular fa-file-pdf"></i></a>
                <?php if($detail['origin']==='owned'&&$detail['state']==='open'&&($can_edit||$can_delete)){ ?><div class="btn-group"><button class="btn btn-default dropdown-toggle" type="button" data-toggle="dropdown"><?= _l('more'); ?> <span class="caret"></span></button><ul class="dropdown-menu dropdown-menu-right">
                    <?php foreach(['accepted'=>'Registrar aceite','declined'=>'Registrar recusa','cancelled'=>'Cancelar documento'] as $action=>$label){if(($action==='cancelled'&&!$can_delete)||($action!=='cancelled'&&!$can_edit))continue; ?><li><?= form_open($url());$hidden($action); ?><input type="hidden" name="document_id" value="<?= $esc($id); ?>"><input type="hidden" name="revision" value="<?= $esc($detail['revision_token']); ?>"><button type="submit" class="btn btn-link"><?= $esc($label); ?></button><?= form_close(); ?></li><?php } ?>
                </ul></div><?php } ?>
                <?php if($detail['origin']==='owned'&&in_array($detail['state'],['accepted','converting'],true)&&$can_edit&&$can_convert){ ?>
                    <div class="tw-inline-block"><?= form_open($url());$hidden('convert'); ?><input type="hidden" name="document_id" value="<?= $esc($id); ?>"><input type="hidden" name="revision" value="<?= $esc($detail['revision_token']); ?>"><button type="submit" class="btn btn-success"><?= _l('estimate_convert_to_invoice'); ?></button><?= form_close(); ?></div>
                <?php } ?>
            </div></div>
        </div>
        <div class="clearfix"></div><hr class="hr-panel-separator">
        <div class="tab-content">
            <div role="tabpanel" class="tab-pane active" id="tab_<?= $type; ?>">
                <div class="row"><div class="col-md-6"><h4 class="tw-font-semibold"><?= $esc($detail['formatted_number']); ?></h4><address><?= $esc($detail['context']['identity']['legal_name']); ?><br><?= nl2br($esc($detail['context']['identity']['address']??'')); ?></address></div>
                    <div class="col-md-6 text-right"><span class="bold"><?= _l($type==='estimate'?'estimate_to':'proposal_to'); ?></span><address><?= $esc($detail['payload']['native_proposal']['proposal_to']??$detail['context']['recipient']['name']); ?></address>
                        <p><span class="bold"><?= _l($type==='estimate'?'estimate_data_date':'proposal_date'); ?>:</span> <?= $esc(_d($detail['payload']['date'])); ?></p>
                        <?php if($detail['payload']['due_date']){ ?><p><span class="bold"><?= _l($type==='estimate'?'estimate_data_expiry_date':'proposal_open_till'); ?>:</span> <?= $esc(_d($detail['payload']['due_date'])); ?></p><?php } ?>
                        <?php if(!empty($detail['reference_no'])){ ?><p><span class="bold"><?= _l('reference_no'); ?>:</span> <?= $esc($detail['reference_no']); ?></p><?php } ?>
                    </div></div>
                <?php if($kind==='proposals'){ $proposalContent=$detail['legacy_content']??($detail['payload']['native_proposal']['content']??''); ?><h4><?= $esc($detail['subject']); ?></h4><?php if($proposalContent!==''){ ?><div class="proposal-content"><?= NativeCompanyContentHtml::purify(str_replace('{proposal_items}','',$proposalContent)); ?></div><?php } } ?>
                <div class="table-responsive"><table class="table items items-preview estimate-items-preview"><thead><tr><th>#</th><th><?= _l('estimate_table_item_heading'); ?></th><th class="text-right"><?= _l('estimate_table_quantity_heading'); ?></th><th class="text-right"><?= _l('estimate_table_rate_heading'); ?></th><th class="text-right"><?= _l('estimate_table_tax_heading'); ?></th></tr></thead><tbody>
                    <?php foreach($detail['payload']['items'] as $position=>$item){ ?><tr><td><?= $position+1; ?></td><td><strong><?= $esc($item['description']); ?></strong><?php if(($item['long_description']??'')!==''){ ?><br><?= nl2br($esc($item['long_description'])); ?><?php }if(($item['is_optional']??0)===1){ ?><br><span class="text-muted"><?= ($item['is_selected']??1)===1?'Opcional escolhido':'Opcional não escolhido'; ?></span><?php } ?></td><td class="text-right"><?= $esc($item['qty'].' '.($item['unit']??'')); ?></td><td class="text-right"><?= $esc(app_format_money($item['rate'],$detail['context']['currency']['name'])); ?></td><td class="text-right"><?php foreach($item['taxes'] as $tax){ ?><?= $esc($tax['name'].' '.$tax['rate'].'%'); ?><br><?php } ?></td></tr><?php } ?>
                </tbody></table></div>
                <div class="row"><div class="col-md-5 col-md-offset-7"><table class="table text-right"><tbody>
                    <?php foreach(['subtotal'=>'estimate_subtotal','discount_total'=>'estimate_discount','total_tax'=>'estimates_total_tax','adjustment'=>'estimate_adjustment','total'=>'estimate_total'] as $field=>$label){$value=$field==='total_tax'?($amounts['total_tax']??$amounts['tax']??'0.00'):($amounts[$field]??'0.00'); ?><tr><td><span class="bold"><?= _l($label); ?></span></td><td><?= $esc(app_format_money($value,$detail['context']['currency']['name'])); ?></td></tr><?php } ?>
                </tbody></table></div></div>
                <?php if($detail['payload']['note']!==''){ ?><h4><?= _l('estimate_add_edit_client_note'); ?></h4><p><?= nl2br($esc($detail['payload']['note'])); ?></p><?php } ?>
                <?php if($detail['terms']!==''){ ?><h4><?= _l('terms_and_conditions'); ?></h4><p><?= nl2br($esc($detail['terms'])); ?></p><?php } ?>
                <?php if((int)$detail['invoice_id']>0){ ?><a href="<?= $esc(CompanyRequestScope::adminUrl($this,'invoices/list_invoices/'.$detail['invoice_id'])); ?>"><?= _l('estimate_invoiced'); ?></a><?php } ?>
            </div>
            <?php if($notesAvailable){ ?><div role="tabpanel" class="tab-pane" id="tab_notes">
                <?php if($can_edit){ ?><?= form_open(CompanyRequestScope::adminUrl($this,$kind.'/add_note/'.$id),['data-native-sales-note-form'=>'1']); ?><?= render_textarea('description','note_description'); ?><button type="submit" class="btn btn-primary"><?= _l('add_note'); ?></button><?= form_close(); ?><?php } ?><div id="sales_notes_area"></div>
            </div><?php } ?>
            <div role="tabpanel" class="tab-pane" id="tab_activity">
                <?php require APPPATH.'views/admin/includes/native_sales/legacy_fields.php'; ?>
                <?php if($detail['origin']==='owned'){ ?><p><?= $esc($detail['created_at']); ?> · Documento emitido · Revisão <?= $esc($detail['revision_number']); ?></p><?php } ?>
            </div>
            <div role="tabpanel" class="tab-pane" id="tab_attachments"><?php require APPPATH.'views/admin/includes/native_sales/extras.php'; ?></div>
            <div role="tabpanel" class="tab-pane" id="tab_context">
                <?php require APPPATH.'views/admin/includes/native_sales/mutations.php'; ?>
                <?php if($portal_permissions){ ?><h4>Acesso no portal por contato</h4><?php foreach($portal_permissions as $contact=>$permission){ ?><?= form_open($url());$hidden('portal_permission'); ?><input type="hidden" name="document_id" value="<?= $esc($id); ?>"><input type="hidden" name="contact_id" value="<?= (int)$contact; ?>"><input type="hidden" name="revision" value="<?= $esc($permission['revision']); ?>"><label><input type="checkbox" name="allowed" value="1" <?= $permission['allowed']?'checked':''; ?> <?= $can_contact_edit?'':'disabled'; ?>> <?= $esc($permission['name']); ?></label><?php if($can_contact_edit){ ?><button type="submit" class="btn btn-default">Salvar acesso no portal</button><?php } ?><?= form_close(); ?><?php } } ?>
            </div>
        </div>
    </div></div>
</div>
