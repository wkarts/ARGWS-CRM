<?php defined('BASEPATH') or exit('No direct script access allowed');foreach($columns as$column){$status=$column['stage']; ?>
<ul class="kan-ban-col" data-col-status-id="<?= (int)$status['id']; ?>" data-total="<?= (int)$column['total']; ?>"><li class="kan-ban-col-wrapper"><div class="border-right panel_s">
    <div class="panel-heading tw-bg-neutral-700 tw-text-white" data-status-id="<?= (int)$status['id']; ?>"><i class="fa fa-reorder"></i><span class="heading tw-ml-1"><?= e($status['name']); ?></span> - <small><?= (int)$column['total']; ?> <?= _l('leads'); ?></small><?php if($capabilities['create']&&(int)$status['active']===1){ ?><a href="#" class="pull-right color-white" data-new-lead-status="<?= (int)$status['id']; ?>" title="<?= _l('new_lead'); ?>"><i class="fa fa-plus"></i></a><?php } ?></div>
    <div class="kan-ban-content-wrapper"><div class="kan-ban-content"><ul class="status leads-status sortable" data-lead-status-id="<?= (int)$status['id']; ?>" data-status-active="<?= (int)$status['active']; ?>">
    <?php foreach($column['rows']as$lead_row){require __DIR__.'/native_board_card.php';} ?>
    <li class="text-center not-sortable kanban-empty mtop30<?= $column['rows']?' hide':''; ?>"><h4><i class="fa-solid fa-circle-notch"></i><br><br><?= _l('no_leads_found'); ?></h4></li>
    <li class="text-center not-sortable kanban-load-more<?= $column['next']===null?' hide':''; ?>"><a href="#" class="btn btn-default btn-block" data-lead-load-status="<?= (int)$status['id']; ?>" data-offset="<?= (int)($column['next']??0); ?>"><?= _l('load_more'); ?></a></li>
    </ul></div></div>
</div></li></ul>
<?php } ?>
