<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php if(isset($native_portal_proposals)) { ?>
<h4 class="tw-mt-0 tw-font-bold tw-text-lg tw-text-neutral-700 section-heading section-heading-proposals"><?= e(_l('proposals')); ?></h4>
<div class="panel_s"><div class="panel-body"><div class="table-responsive"><table class="table dt-table table-proposals"><thead><tr>
<th><?= e(_l('proposal')); ?></th><th><?= e(_l('proposal_date')); ?></th><th><?= e(_l('proposal_open_till')); ?></th><th><?= e(_l('proposal_total')); ?></th><th><?= e(_l('proposal_status')); ?></th>
</tr></thead><tbody><?php foreach($native_portal_proposals as$proposal){ ?><tr>
<td><a href="<?= e(NativePortalPresentation::url('proposal/'.$proposal['id'].'/'.$proposal['hash'])); ?>"><?= e($proposal['number']); ?></a></td>
<td><?= e($proposal['date']); ?></td><td><?= e((string)($proposal['open_till']??'')); ?></td><td><?= e($proposal['total']); ?></td>
<td><?= e($proposal['state']==='converted'?'Convertida em fatura':'Aceita'); ?></td></tr><?php } ?></tbody></table></div>
<?php if($native_portal_proposals===[]){ ?><p><?= e(_l('no_proposals_found')); ?></p><?php } ?></div></div><?php return; } ?>
<h4 class="tw-mt-0 tw-font-bold tw-text-lg tw-text-neutral-700 section-heading section-heading-proposals">
    <?= _l('proposals'); ?>
</h4>
<div class="panel_s">
    <div class="panel-body">
        <?php get_template_part('proposals_table'); ?>
    </div>
</div>
