<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php if(isset($native_portal_tickets)&&is_array($native_portal_tickets)){
    $portalUrl=static fn(string$path)=>NativePortalPresentation::url($path);$states=['open'=>'Aberto','resolved'=>'Resolvido','closed'=>'Encerrado']; ?>
<section id="native-portal-ticket-list">
    <div class="tw-flex tw-items-center tw-justify-between tw-mb-3"><h4 class="tw-my-0 tw-font-bold tw-text-lg"><?= e(_l('clients_tickets_heading')); ?></h4><a class="btn btn-primary" href="<?= e($portalUrl('clients/open_ticket')); ?>"><?= e(_l('clients_ticket_open_subject')); ?></a></div>
    <div class="tw-flex tw-gap-2 tw-mb-4"><a class="btn btn-default" href="<?= e($portalUrl('clients/tickets')); ?>">Todos</a><?php foreach($states as$key=>$label){ ?><a class="btn btn-default" href="<?= e($portalUrl('clients/tickets/'.$key)); ?>"><?= e($label); ?></a><?php } ?></div>
    <div class="panel_s"><div class="panel-body"><div class="table-responsive"><table class="table"><thead><tr><th>Assunto</th><th>Status</th><th>Prioridade</th><th>Aberto em</th></tr></thead><tbody>
    <?php foreach($native_portal_tickets['items']as$ticket){ ?><tr><td><a href="<?= e($portalUrl('clients/ticket/'.$ticket['canonical_id'])); ?>"><?= e($ticket['name']); ?></a></td><td><?= e($states[$ticket['status']]??$ticket['status']); ?></td><td><?= e($ticket['priority']); ?></td><td><?= e($ticket['created_at']); ?></td></tr><?php } ?>
    <?php if(!$native_portal_tickets['items']){ ?><tr><td colspan="4"><?= e(_l('no_records_found')); ?></td></tr><?php } ?></tbody></table></div>
    <?php if($native_portal_tickets['next']!==null){ ?><a class="btn btn-default" href="<?= e($portalUrl('clients/tickets').'&after='.(int)$native_portal_tickets['next']); ?>">Próxima página</a><?php } ?></div></div>
</section>
<?php return; } ?>

<h4 class="tw-mt-0 tw-font-bold tw-text-lg tw-text-neutral-700 tickets-summary-heading">
    <?= _l('tickets_summary'); ?>
</h4>

<dl class="tw-grid tw-grid-cols-1 md:tw-grid-cols-5 tw-gap-2 sm:tw-gap-4 tw-mt-2 tw-mb-10">
    <?php foreach (get_clients_area_tickets_summary($ticket_statuses) as $status) { ?>
    <a href="<?= e($status['url']); ?>"
        class="tw-border tw-border-solid tw-border-neutral-200 tw-rounded-md hover:tw-bg-neutral-100 <?= in_array($status['ticketstatusid'], $list_statuses) ? 'tw-bg-white' : 'tw-bg-neutral-50 '; ?>">
        <div class="tw-px-4 tw-py-5 sm:tw-px-4 sm:tw-py-2">
            <dt class="tw-font-medium"
                style="color:<?= e($status['statuscolor']); ?>">
                <?= e($status['translated_name']); ?>
            </dt>
            <dd class="tw-mt-1 tw-flex tw-items-baseline tw-justify-between md:tw-block lg:tw-flex">
                <div class="tw-flex tw-items-baseline tw-text-base tw-font-semibold tw-text-primary-600">
                    <?= e($status['total_tickets']) ?>
                </div>
            </dd>
        </div>
    </a>
    <?php } ?>
</dl>

<div class="tw-flex tw-items-center tw-justify-between tw-mb-3">
    <h4 class="tw-my-0 tw-font-bold tw-text-lg tw-text-neutral-700 section-heading section-heading-tickets">
        <?= _l('clients_tickets_heading'); ?>
    </h4>
    <a href="<?= site_url('clients/open_ticket'); ?>"
        class="btn btn-primary new-ticket">
        <i class="fa-regular fa-plus tw-mr-1"></i>
        <?= _l('clients_ticket_open_subject'); ?>
    </a>
</div>

<div class="panel_s">
    <div class="panel-body">
        <?php get_template_part('tickets_table'); ?>
    </div>
</div>
