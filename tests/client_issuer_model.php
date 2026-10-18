<?php
require __DIR__ . '/support/client_issuer_test_support.php';
check(array_column($model->get(),'userid')===[1],'directory legacy only');
check($model->get(2)===null&&$model->get(3)===null,'private IDs hidden');
check($model->get(1)->company==='Legacy','legacy preserved');
check(array_column($model->get('', 'q_clients.userid=2 OR q_clients.userid=1'),'userid')===[1],'OR cannot escape ownership');
check(array_column($model->get_contacts(),'id')===[10],'contacts list scoped');
check($model->get_contacts(2)===[],'private contacts not enumerable');
check($model->get_contact(20)===null,'contact ID cannot bypass');
check($model->get_contact_by_email('x@example.test')===null,'contact email cannot bypass');
check($model->get_contact_by_email('l@example.test')->id===10,'custom table prefix preserved');
check($model->get_admins(2)===[],'customer admin assignment does not authorize issuer');
echo "Client model ownership: $n checks passed (real model / SQLite)\n";
