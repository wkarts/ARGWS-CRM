<?php

defined('BASEPATH') or exit('No direct script access allowed');
$route['pige_bridge/api/v1/requests'] = 'bridge/requests';

$route['pige_bridge/api/v1/diagnostics'] = 'bridge/diagnostics';

$route['pige_bridge/api/v2/invoices'] = 'bridge/invoices';
$route['pige_bridge/api/v2/invoices/status'] = 'bridge/invoice_status';

$route['pige_bridge/api/v3/charges'] = 'bridge/charges';
$route['pige_bridge/api/v3/charges/status'] = 'bridge/charge_status';
$route['pige_bridge/api/v3/charges/sync'] = 'bridge/charge_sync';
$route['pige_bridge/api/v3/charges/instruments'] = 'bridge/charge_instruments';
$route['pige_bridge/api/v3/charges/reconcile'] = 'bridge/charge_reconcile';
