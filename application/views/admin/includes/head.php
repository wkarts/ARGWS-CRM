<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
require_once APPPATH.'services/NativeCompanyAdminPresentation.php';
$nativeCompany=NativeCompanyAdminPresentation::current(function_exists('get_instance') ? get_instance() : $this);
$nativeLabel=$nativeCompany ? static fn($key, ...$unused)=>NativeCompanyAdminPresentation::label((string)$key) : static fn($key, ...$args)=>_l($key,...$args);
?>

<?php $isRTL = ($nativeCompany ? ($nativeCompany['rtl'] ? 'true' : 'false') : (is_rtl() ? 'true' : 'false')); ?>

<!DOCTYPE html>
<html lang="<?= e($locale); ?>"
    dir="<?= ($isRTL == 'true') ? 'rtl' : 'ltr' ?>">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    <title>
        <?= e(crm_app_page_title($title ?? null)); ?>
    </title>

    <?php $this->load->view('admin/includes/company_context_script'); ?>
    <?php if($nativeCompany) { foreach(NativeCompanyAdminPresentation::css(function_exists('get_instance') ? get_instance() : $this) as $asset) { ?>
    <link rel="stylesheet" href="<?= e(base_url($asset).'?v='.(defined('ARGWS_VERSION')?ARGWS_VERSION:'core')); ?>">
    <?php } ?><style><?= $nativeCompany['theme']; ?></style><?php } else { echo app_compile_css(); } ?>
    <?php if($nativeCompany) { ?>
    <link rel="icon" href="<?= e($nativeCompany['favicon']); ?>">
    <link rel="apple-touch-icon" href="<?= e($nativeCompany['favicon']); ?>">
    <script>
        window.app=<?= json_encode(NativeCompanyAdminPresentation::appConfig(function_exists('get_instance') ? get_instance() : $this),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR); ?>;
        window.admin_url=<?= json_encode(CompanyRequestScope::adminUrl(function_exists('get_instance') ? get_instance() : $this),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR); ?>;
        window.isRTL=<?= json_encode($isRTL); ?>;
    </script>
    <?php } ?>
    <?php if(!$nativeCompany) { render_admin_js_variables(); ?>

    <script>
        var totalUnreadNotifications = <?= e($current_user->total_unread_notifications); ?> ,
            proposalsTemplates = <?= json_encode(get_proposal_templates()); ?> ,
            contractsTemplates = <?= json_encode(get_contract_templates()); ?> ,
            billingAndShippingFields = ['billing_street', 'billing_city', 'billing_state', 'billing_zip',
                'billing_country',
                'shipping_street', 'shipping_city', 'shipping_state', 'shipping_zip', 'shipping_country'
            ],
            isRTL = '<?= e($isRTL); ?>',
            taskid, taskTrackingStatsData, taskAttachmentDropzone, taskCommentAttachmentDropzone, newsFeedDropzone,
            expensePreviewDropzone, taskTrackingChart, cfh_popover_templates = {},
            _table_api;
    </script>
    <?php app_admin_head(); } ?>
    <?php $this->load->view('includes/pwa'); ?>
</head>

<body <?= $nativeCompany ? 'data-native-mobile="'.($nativeCompany['is_mobile']?'true':'false').'"' : ''; ?> <?= $nativeCompany ? 'class="'.e(NativeCompanyAdminPresentation::bodyClasses(function_exists('get_instance') ? get_instance() : $this,$bodyclass ?? '')).'"' : admin_body_class($bodyclass ?? ''); ?>>
    <?php if(!$nativeCompany) { hooks()->do_action('after_body_start'); } ?>
