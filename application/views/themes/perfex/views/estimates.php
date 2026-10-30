<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php if(isset($native_portal_estimates)) { $labels=[1=>'estimate_status_draft',2=>'estimate_status_sent',3=>'estimate_status_declined',4=>'estimate_status_accepted',5=>'estimate_status_expired']; ?>
<h4 class="section-heading section-heading-estimates"><?= e(_l('clients_my_estimates')); ?></h4><div class="panel_s"><div class="panel-body"><div class="table-responsive"><table class="table dt-table table-estimates"><thead><tr><th><?= e(_l('clients_estimate_dt_number')); ?></th><th><?= e(_l('clients_estimate_dt_date')); ?></th><th><?= e(_l('clients_estimate_dt_duedate')); ?></th><th><?= e(_l('clients_estimate_dt_amount')); ?></th><th><?= e(_l('clients_estimate_dt_status')); ?></th></tr></thead><tbody>
<?php foreach($native_portal_estimates as$estimate) { ?><tr><td><a href="<?= e(NativePortalPresentation::url('estimate/'.$estimate['id'].'/'.$estimate['hash'])); ?>"><?= e((string)$estimate['number']); ?></a></td><td><?= e((string)$estimate['date']); ?></td><td><?= e((string)($estimate['expirydate']??'')); ?></td><td><?= e((string)$estimate['total']); ?></td><td><?= e(_l($labels[$estimate['status']]??'estimate_status_unknown')); ?></td></tr><?php } ?>
</tbody></table></div></div></div><?php return; } ?>
<h4 class="tw-mt-0 tw-font-bold tw-text-lg tw-text-neutral-700 section-heading section-heading-estimates">
    <?= _l('clients_my_estimates'); ?>
</h4>
<div class="panel_s">
    <div class="panel-body">
        <?php get_template_part('estimates_stats'); ?>
        <hr />
        <?php get_template_part('estimates_table'); ?>
    </div>
</div>
