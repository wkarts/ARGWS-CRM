<?php defined('BASEPATH') or exit('No direct script access allowed'); require_once APPPATH.'helpers/pwa_helper.php'; $pwaRevision=native_pwa_revision(); $pwaBase=base_url('pwa.php'); ?>
<link rel="manifest" href="<?= e($pwaBase.'?resource=manifest&v='.$pwaRevision); ?>">
<meta name="apple-mobile-web-app-capable" content="yes">
<script defer src="<?= e(base_url('assets/pwa/install.js')); ?>" data-pwa-root="<?= e(base_url()); ?>" data-pwa-revision="<?= e($pwaRevision); ?>"></script>
