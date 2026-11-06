<?php
// Render the production view with the existing isolated financial fixtures.
ob_start();
require dirname(__DIR__).'/companies/asaas_checkout_views.php';
ob_end_clean();
$data['checkout_ready']=true;
echo $harness->render($data);
