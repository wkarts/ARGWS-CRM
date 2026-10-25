<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<link rel="manifest" href="<?= e(base_url('pwa.php?resource=manifest')); ?>">
<link rel="apple-touch-icon" href="<?= e(base_url('pwa.php?resource=icon-192')); ?>">
<meta name="apple-mobile-web-app-capable" content="yes">
<script defer src="<?= e(base_url('assets/pwa/install.js')); ?>" data-pwa-root="<?= e(base_url()); ?>"></script>
