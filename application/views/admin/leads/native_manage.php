<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper" data-native-leads data-mode="<?= $isKanBan?'kanban':'list'; ?>" data-open-lead="<?= e($leadid); ?>">
    <div class="content" id="vueApp"><div class="row"><div class="col-md-12">
        <div class="leads-overview tw-mb-6<?= $isKanBan?' hide':''; ?>">
            <h4 class="tw-my-0 tw-font-bold tw-text-xl tw-mb-2"><?= _l('leads'); ?></h4>
            <div class="tw-grid tw-gap-2 sm:tw-grid-flow-col sm:tw-auto-cols-max tw-overflow-x-auto">
                <?php foreach($summary as$status){ ?>
                <button type="button" @click="extra.leadsRules = <?= \app\services\utilities\Js::from($table->findRule('status')->setValue([(string)$status['id']])); ?>" class="tw-bg-transparent tw-border tw-border-solid tw-border-neutral-300 tw-shadow-sm tw-py-1 tw-px-2 tw-rounded-lg tw-text-sm hover:tw-bg-neutral-200/60 tw-text-neutral-700 hover:tw-text-neutral-600 focus:tw-text-neutral-600 text-left">
                    <span class="tw-font-semibold tw-mr-1 rtl:tw-ml-1" data-lead-summary="<?= (int)$status['id']; ?>"><?= (int)$status['total']; ?></span><span><?= e($status['name']); ?></span>
                </button><?php } ?>
            </div>
        </div>
        <div class="_buttons tw-mb-2"><div class="tw-flex tw-items-center tw-justify-between tw-space-x-2 rtl:tw-space-x-reverse">
            <div class="tw-flex tw-items-center tw-space-x-1 rtl:tw-space-x-reverse">
                <?php if($capabilities['create']){ ?><a href="#" onclick="init_lead(); return false;" class="btn btn-primary"><i class="fa-regular fa-plus"></i> <?= _l('new_lead'); ?></a><?php } ?>
                <a href="<?= e(admin_url('leads?view='.($isKanBan?'list':'kanban'))); ?>" class="btn btn-default hidden-xs !tw-px-3" data-toggle="tooltip" title="<?= $isKanBan?_l('switch_to_list_view'):_l('leads_switch_to_kanban'); ?>"><i class="fa-solid <?= $isKanBan?'fa-table-list':'fa-grip-vertical'; ?>"></i></a>
                <?php if($capabilities['create']){ ?><a href="<?= e(admin_url('leads?workspace=1#imports')); ?>" class="hidden-xs btn btn-default"><i class="fa-solid fa-upload tw-mr-1"></i><?= _l('import_leads'); ?></a><?php } ?>
                <a href="<?= e(admin_url('leads?workspace=1')); ?>" class="btn btn-default">Caixa interna e configurações</a>
            </div>
            <div><?php if($isKanBan){ ?><div class="leads-search"><input id="native-lead-search" type="search" class="form-control" maxlength="191" placeholder="<?= _l('leads_search'); ?>" aria-label="<?= _l('leads_search'); ?>"></div><?php }else{ ?>
                <div class="tw-inline"><app-filters id="company_leads" view="leads" :rules="extra.leadsRules || <?= \app\services\utilities\Js::from($initial_status===null?[]:$table->findRule('status')->setValue([(string)$initial_status])); ?>" :saved-filters="<?= $table->filtersJs(); ?>" :available-rules="<?= $table->rulesJs(); ?>"></app-filters></div>
            <?php } ?></div>
        </div></div>
        <input type="hidden" id="native-leads-csrf" name="native_leads_csrf" value="<?= e($native_leads_csrf); ?>">
        <div id="native-lead-page-error" class="alert alert-danger hide" role="alert"></div>
        <div class="<?= $isKanBan?'':'panel_s'; ?>"><div class="<?= $isKanBan?'':'panel-body'; ?>"><div class="tab-content">
            <?php if($isKanBan){ ?>
            <div class="active kan-ban-tab tw-mt-4" id="kan-ban-tab" style="overflow:auto;"><div class="kanban-leads-sort"><span class="bold"><?= _l('leads_sort_by'); ?>:</span> <a href="#" data-lead-board-sort="desc"><?= _l('date_created'); ?> <i class="fa fa-sort-amount-desc"></i></a> <a href="#" data-lead-board-sort="asc"><?= _l('date_created'); ?> <i class="fa fa-sort-amount-asc"></i></a></div><div class="row"><div class="container-fluid"><div id="kan-ban" data-native-lead-board></div></div></div></div>
            <?php }else{ ?>
            <div class="row mbot15"><div class="col-md-3"><label for="native-lead-state">Exibir</label><select id="native-lead-state" class="form-control selectpicker"><option value="active">Ativos</option><option value="archived">Arquivados</option><option value="all">Todos</option></select></div></div>
            <div class="panel-table-full">
                <?php $headings=['<span class="hide"> - </span>'];$labels=['number'=>'the_number_sign','name'=>'leads_dt_name','company'=>'lead_company','email'=>'leads_dt_email','phone'=>'leads_dt_phonenumber','lead-value'=>'leads_dt_lead_value','tags'=>'tags','assigned'=>'leads_dt_assigned','status'=>'lead_status','source'=>'lead_source','last-contact'=>'leads_dt_last_contact','created'=>'date_created'];foreach($labels as$id=>$label)$headings[]=['name'=>_l($label),'th_attrs'=>['class'=>'toggleable','id'=>'th-'.$id]];
                render_datatable($headings,'leads',['customizable-table number-index-2'],['id'=>'company_leads','data-last-order-identifier'=>'leads','data-default-order'=>get_table_last_order('leads')]); ?>
            </div><?php } ?>
        </div></div></div>
    </div></div></div>
</div>
<script id="hidden-columns-company_leads" type="application/json">[]</script>
<?php init_tail(); ?>
</body></html>
