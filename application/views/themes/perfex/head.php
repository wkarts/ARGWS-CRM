<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<!DOCTYPE html>
<html lang="<?= e($locale); ?>">

<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<title><?= e(!empty($native_portal) ? (($title ?? '') . ' — ' . $native_portal_options['companyname']) : crm_app_page_title($title ?? null)); ?></title>
	<?= !empty($native_portal) ? NativePortalPresentation::css($isRTL === 'true') : compile_theme_css(); ?>
	<script
		src="<?= base_url('assets/plugins/jquery/jquery.min.js'); ?>">
	</script>
	<?php if(empty($native_portal)) app_customers_head(); ?>
    <?php $this->load->view('includes/pwa'); ?>
</head>

<body
	class="customers <?= !empty($native_portal) ? '' : strtolower($this->agent->browser()); ?><?= empty($native_portal) && is_mobile() ? ' mobile' : ''; ?><?= isset($bodyclass) ? ' ' . $bodyclass : ''; ?>"
	<?= $isRTL == 'true' ? 'dir="rtl"' : ''; ?>>

	<?php if(empty($native_portal)) hooks()->do_action('customers_after_body_start'); ?>
