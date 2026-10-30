<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<link rel="manifest" href="<?= e(base_url('pwa.php?resource=manifest')); ?>">
<link rel="apple-touch-icon" href="<?= e(base_url('assets/pwa/apple-touch-icon-180.png')); ?>">
<meta name="apple-mobile-web-app-capable" content="yes">
<script defer src="<?= e(base_url('assets/pwa/install.js')); ?>" data-pwa-root="<?= e(base_url()); ?>"></script>

