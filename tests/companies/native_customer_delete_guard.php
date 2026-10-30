<?php
/** Bounded native deletion never falls through to the global deletion cascade. */
require __DIR__.'/native_clients_controller.php';
$beforeA=$adapterA->details($nativeA);
$beforeB=$adapterB->details($nativeB);
foreach ([41,82] as $issuer) {
    CompanyRequestScope::$issuer=$issuer;
    foreach (['GET','POST'] as $verb) {
        $controller->input->method=$verb;
        $controller->input->post=['_native_company_csrf'=>'synthetic-native-csrf'];
        foreach ([$nativeA,$nativeB] as $customer) {
            $owned=($issuer===41&&$customer===$nativeA)||($issuer===82&&$customer===$nativeB);
            try {
                $controller->delete($customer);
                throw new RuntimeException('native deletion accepted an incomplete request');
            } catch (NativeClientHttpError $error) {
                $expected=$verb==='GET'?422:($owned?409:403);
                essentialCheck($error->getCode()===$expected,'native deletion requires POST, ownership and an explicit current revision');
            }
        }
    }
}
essentialCheck($adapterA->details($nativeA)===$beforeA,'company A customer remains unchanged');
essentialCheck($adapterB->details($nativeB)===$beforeB,'company B customer remains unchanged');
echo "Native customer bounded deletion guard passed without reaching the legacy cascade.\n";
