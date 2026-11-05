<?php
/** Actual pre-hydration boundary for restored routes; synthetic CI I/O and real SQLite rows. */
require __DIR__.'/native_context.php';

$before = $GLOBALS['essentialChecks'];
$routes = [
    ['contracts', 'index', 'GET'], ['contracts', 'index', 'HEAD'], ['contracts', 'table', 'POST'],
    ['estimates', 'get_estimate_data_ajax', 'GET'], ['estimates', 'get_estimate_data_ajax', 'HEAD'],
    ['estimates', 'get_notes', 'GET'], ['estimates', 'add_note', 'POST'], ['estimates', 'get_estimates_total', 'POST'],
    ['proposals', 'get_proposal_data_ajax', 'GET'], ['proposals', 'get_proposal_data_ajax', 'HEAD'],
    ['proposals', 'get_notes', 'GET'], ['proposals', 'add_note', 'POST'],
    ['credit_notes', 'table', 'POST'], ['credit_notes', 'get_credit_note_data_ajax', 'GET'],
    ['credit_notes', 'credit_note', 'GET'], ['credit_notes', 'credit_note', 'POST'], ['credit_notes', 'pdf', 'HEAD'],
    ['payments', 'index', 'GET'], ['payments', 'table', 'POST'],
    ['invoices', 'recurring_preview', 'GET'], ['invoices', 'recurring_preview', 'HEAD'],
];

foreach ([41, 54] as $issuer) {
    $session = new ContextSession();
    $session->data = ['staff_user_id'=>7, 'staff_logged_in'=>true];
    $context = new CompanyContext($db, $p, $session, 7);
    $context->resolve();
    if ($issuer !== 41) { $context->select($issuer, $context->csrf(), $context->token()); }
    $context = new CompanyContext($db, $p, $session, 7);
    $context->resolve();
    $token = $context->token();
    $csrf = $context->nativeCsrf();
    foreach ($routes as [$controller, $method, $verb]) {
        $ci = contextEnvironment($f, $session, $controller, $method, $verb);
        if ($verb === 'POST') { $_POST = ['_company_context'=>$token, '_native_company_csrf'=>$csrf]; }
        CompanyRequestScope::beforeHydration($ci);
        essentialCheck(CompanyRequestScope::sealedFor($ci) && CompanyRequestScope::nativeFor($ci)
            && CompanyRequestScope::context()->issuerId() === $issuer,
            $controller.'/'.$method.' is sealed to company '.$issuer.' before model hydration ('.$verb.')');
        essentialCheck($ci->load->trace === [] && !isset($_POST['_company_context'], $_POST['_native_company_csrf']),
            'Route dispatch neither hydrates a global model nor passes boundary tokens to a model');
        CompanyRequestScope::finish();
    }
    foreach ([['contracts','index','POST'], ['contracts','table','GET'], ['estimates','get_estimate_data_ajax','POST'],
        ['estimates','add_note','GET'], ['proposals','get_proposal_data_ajax','POST'], ['proposals','add_note','GET'],
        ['credit_notes','table','GET'], ['credit_notes','get_credit_note_data_ajax','POST'], ['credit_notes','pdf','POST']] as [$controller,$method,$verb]) {
        $ci = contextEnvironment($f, $session, $controller, $method, $verb);
        if ($verb === 'POST') { $_POST = ['_company_context'=>$token, '_native_company_csrf'=>$csrf]; }
        expectStatus(fn()=>CompanyRequestScope::beforeHydration($ci), 405);
    }
    $ci = contextEnvironment($f, $session, 'proposals', 'add_note', 'POST');
    $_POST = ['_company_context'=>$token];
    expectStatus(fn()=>CompanyRequestScope::beforeHydration($ci), 403);
    foreach ([['expenses', 'get_customer_change_data', 'GET', ['source'=>'company']],
        ['expenses', 'get_customer_change_data', 'HEAD', ['source'=>'company']],
        ['invoices', 'table', 'POST', ['recurring'=>'1']],
        ['invoices', 'table', 'POST', ['recurring'=>'invalid']]] as [$controller,$method,$verb,$query]) {
        $ci = contextEnvironment($f, $session, $controller, $method, $verb);
        $ci->input->query = $query;
        $ci->uri = new class { public function rsegment($n) { return $n === 3 ? '1' : null; } };
        if ($verb === 'POST') { $_POST = ['_company_context'=>$token, '_native_company_csrf'=>$csrf]; }
        CompanyRequestScope::beforeHydration($ci);
        essentialCheck(CompanyRequestScope::sealedFor($ci) && CompanyRequestScope::nativeFor($ci)
            && $ci->load->trace === [], 'Owned expense picker/recurrence table never enters a legacy model before its own validation');
        CompanyRequestScope::finish();
    }
    foreach ([['expenses','get_customer_change_data','POST',['source'=>'company']],
        ['invoices','table','GET',['recurring'=>'1']], ['invoices','recurring_preview','POST',[]]] as [$controller,$method,$verb,$query]) {
        $ci = contextEnvironment($f, $session, $controller, $method, $verb);
        $ci->input->query = $query;
        if ($verb === 'POST') { $_POST = ['_company_context'=>$token, '_native_company_csrf'=>$csrf]; }
        expectStatus(fn()=>CompanyRequestScope::beforeHydration($ci), 405);
    }
}

// Historical primary receipts deliberately retain their existing reader; a named
// invoice can never take that path merely by using a colliding numeric payment ID.
if (!IssuerStorage::fieldExists($db, 'issuer_id', $p.'invoices')) {
    $db->query('ALTER TABLE '.$p.'invoices ADD COLUMN issuer_id INT NULL');
}
if (!$db->table_exists($p.'invoicepaymentrecords')) {
    $db->query('CREATE TABLE '.$p.'invoicepaymentrecords(id INT PRIMARY KEY, invoiceid INT NOT NULL)');
}
$db->query('INSERT INTO '.$p.'invoices(id, clientid, total, paymentmode, issuer_id) VALUES(43,1,1000,?,54)', ['synthetic']);
$db->query('INSERT INTO '.$p.'invoicepaymentrecords(id, invoiceid) VALUES(901,42),(902,43)');
$session = new ContextSession();
$session->data = ['staff_user_id'=>7, 'staff_logged_in'=>true];
foreach (['payment','pdf'] as $method) {
    foreach ([901=>false, 902=>true] as $id=>$native) {
        $ci = contextEnvironment($f, $session, 'payments', $method, 'GET');
        $ci->uri = new class($id) { public function __construct(private int $id) {} public function rsegment($n) { return $n === 3 ? (string)$this->id : null; } };
        CompanyRequestScope::beforeHydration($ci);
        essentialCheck(CompanyRequestScope::nativeFor($ci) === $native,
            'Primary '.$method.' selects historical or owned adapter from persisted invoice provenance');
        CompanyRequestScope::finish();
    }
}
echo 'Revision 8 route admission: '.($GLOBALS['essentialChecks']-$before).' additional checks passed; actual pre-hydration/CSRF boundary, no production HTTP claim.'.PHP_EOL;
