<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php if (!empty($native_portal_home)) {
    $home=$native_portal_home;
    $invoiceNames=[1=>'invoice_status_unpaid',2=>'invoice_status_paid',3=>'invoice_status_not_paid_completely',4=>'invoice_status_overdue'];
    $months=[];for($month=1;$month<=12;$month++)$months[]=_l(date('F',mktime(0,0,0,$month,1)));
    $chartConfig=['data'=>$home['invoice_chart'],'months'=>$months,'labels'=>array_map('_l',$invoiceNames)];
?>
<div class="row"><div class="col-md-12 section-client-dashboard">
    <h3 id="greeting" class="tw-font-semibold tw-mt-0"><?= e($home['identity']['firstname']); ?></h3>
    <?php if ($home['permissions']['invoices']) { ?>
    <div class="tw-mb-3"><h3 class="invoices-quick-info-heading tw-text-neutral-700 tw-font-medium tw-text-lg tw-mt-7 tw-mb-0"><?= e(_l('clients_quick_invoice_info')); ?></h3></div>
    <div class="panel_s"><div class="panel-body"><div class="row text-left invoice-quick-info invoices-stats">
        <?php foreach($invoiceNames as $status=>$label) { $count=$home['invoice_counts'][$status];$total=$home['invoice_total'];$percent=$total>0?round($count*100/$total,2):0; ?>
        <div class="col-md-3"><div class="row"><div class="col-md-8 stats-status"><a href="<?= e(NativePortalPresentation::url('clients/invoices/'.$status)); ?>"><?= e(_l($label)); ?></a></div><div class="col-md-4 text-right stats-numbers"><?= (int)$count; ?> / <?= (int)$total; ?></div><div class="col-md-12 tw-mt-1.5"><div class="progress"><div class="progress-bar progress-bar-<?= $status===2?'success':($status===1?'danger':'warning'); ?>" role="progressbar" aria-valuenow="<?= e((string)$percent); ?>" aria-valuemin="0" aria-valuemax="100" style="width:<?= e((string)$percent); ?>%"></div></div></div></div></div>
        <?php } ?>
    </div><hr><div class="row"><div class="col-md-3">
        <?php if($home['invoice_years']) { ?><label for="payments_year"><?= e(_l('year')); ?></label><select class="form-control" id="payments_year" name="payments_years"><?php foreach($home['invoice_years'] as $year){ ?><option value="<?= e((string)$year); ?>"<?= (string)$year===date('Y')?' selected':''; ?>><?= e((string)$year); ?></option><?php } ?></select><?php } ?>
        <?php if($home['invoice_currencies']) { ?><label for="currency"><?= e(_l('currency')); ?></label><select class="form-control" id="currency" name="currency"><?php foreach($home['invoice_currencies'] as $currency){ ?><option value="<?= (int)$currency['id']; ?>"><?= e($currency['symbol'].' - '.$currency['name']); ?></option><?php } ?></select><?php } ?>
    </div><div class="col-md-12"><div class="relative" style="max-height:400px"><canvas id="client-home-chart" height="400" class="animated fadeIn"></canvas></div></div></div></div></div>
    <script id="native-portal-home-chart" type="application/json"><?= json_encode($chartConfig,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR); ?></script>
    <script src="<?= e(base_url('assets/plugins/Chart.js/Chart.min.js')); ?>"></script><script src="<?= e(base_url('assets/js/companies/native-portal-home.js')); ?>"></script>
    <?php } ?>
</div></div>
<?php return; } ?>
<div class="row">
    <div class="col-md-12 section-client-dashboard">
        <h3 id="greeting" class="tw-font-semibold tw-mt-0"></h3>
        <?php if (has_contact_permission('projects')) { ?>
        <h3 class="projects-summary-heading tw-text-neutral-700 tw-font-medium tw-text-lg tw-mt-7">
            <?= _l('projects_summary'); ?>
        </h3>
        <?php get_template_part('projects/project_summary'); ?>
        <?php } ?>
        <?php hooks()->do_action('client_area_after_project_overview'); ?>
        <?php
            if (has_contact_permission('invoices')) { ?>
        <div class="tw-mb-3">
            <h3 class="invoices-quick-info-heading tw-text-neutral-700 tw-font-medium tw-text-lg tw-mt-7 tw-mb-0">
                <?= _l('clients_quick_invoice_info'); ?>
            </h3>
            <?php if (has_contact_permission('invoices')) { ?>
            <a href="<?= site_url('clients/statement'); ?>"
                class="tw-text-sm">
                <?= _l('view_account_statement'); ?>
            </a>
            <?php } ?>
        </div>
        <div class="panel_s">
            <div class="panel-body">
                <?php get_template_part('invoices_stats'); ?>
                <hr />
                <div class="row">
                    <div class="col-md-3">
                        <?php if (count($payments_years) > 0) { ?>
                        <div class="form-group">
                            <select
                                data-none-selected-text="<?= _l('dropdown_non_selected_tex'); ?>"
                                class="form-control" id="payments_year" name="payments_years" data-width="100%"
                                onchange="total_income_bar_report();"
                                data-none-selected-text="<?= _l('dropdown_non_selected_tex'); ?>">
                                <?php foreach ($payments_years as $year) { ?>
                                <option
                                    value="<?= e($year['year']); ?>"
                                    <?php if ($year['year'] == date('Y')) {
                                        echo 'selected';
                                    } ?>>
                                    <?= e($year['year']); ?>
                                </option>
                                <?php } ?>
                            </select>
                        </div>
                        <?php } ?>
                        <?php if (is_client_using_multiple_currencies()) { ?>
                        <div id="currency" class="form-group mtop15" data-toggle="tooltip"
                            title="<?= _l('clients_home_currency_select_tooltip'); ?>">
                            <select
                                data-none-selected-text="<?= _l('dropdown_non_selected_tex'); ?>"
                                class="form-control" name="currency">
                                <?php foreach ($currencies as $currency) {
                                    $selected = '';
                                    if ($currency['isdefault'] == 1) {
                                        $selected = 'selected';
                                    } ?>
                                <option
                                    value="<?= e($currency['id']); ?>"
                                    <?= e($selected); ?>>
                                    <?= e($currency['symbol']); ?>
                                    -
                                    <?= e($currency['name']); ?>
                                </option>
                                <?php
                                } ?>
                            </select>
                        </div>
                        <?php } ?>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <div class="relative" style="max-height:400px;">
                            <canvas id="client-home-chart" height="400" class="animated fadeIn"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php } ?>
        <?php hooks()->do_action('client_area_dashboard_end'); ?>
    </div>
    <script>
        var greetDate = new Date();
        var hrsGreet = greetDate.getHours();

        var greet;
        if (hrsGreet < 12)
            greet = "<?= _l('good_morning'); ?>";
        else if (hrsGreet >= 12 && hrsGreet <= 17)
            greet = "<?= _l('good_afternoon'); ?>";
        else if (hrsGreet >= 17 && hrsGreet <= 24)
            greet = "<?= _l('good_evening'); ?>";

        if (greet) {
            document.getElementById('greeting').innerHTML =
                '<b>' + greet + ' <?= e($contact->firstname); ?>!</b>';
        }
    </script>
