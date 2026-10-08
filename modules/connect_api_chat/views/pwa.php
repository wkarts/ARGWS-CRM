<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#00a884">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<title><?php echo html_escape($title); ?></title>
<link rel="manifest" crossorigin="use-credentials" href="<?php echo html_escape($manifest_url); ?>">
<link rel="icon" href="<?php echo base_url('modules/connect_api_chat/assets/pwa/icon-192.png'); ?>">
<style>html,body{margin:0;height:100%;background:#111b21;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif}body{overflow:hidden}.connect-api-chat-pwa{height:100vh;height:100dvh;padding:0!important}.connect-api-chat-pwa .cac-shell{height:100vh!important;height:100dvh!important;min-height:0!important;border-radius:0!important;border:0!important}.connect-api-chat-pwa .cac-dependency{height:100vh;height:100dvh;display:flex;align-items:center;justify-content:center;padding:20px;box-sizing:border-box}</style>
</head>
<body>
<div class="connect-api-chat-pwa">
<?php $this->load->view('_chat_shell'); ?>
</div>
</body>
</html>
