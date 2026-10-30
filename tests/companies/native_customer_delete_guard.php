<?php
/** The native controller must not fall through to the global deletion cascade. */
require __DIR__.'/native_clients_controller.php';
$beforeA=$adapterA->details($nativeA);
$beforeB=$adapterB->details($nativeB);
foreach ([41,82] as $issuer) {
    CompanyRequestScope::$issuer=$issuer;
    foreach (['GET','POST'] as $verb) {
        $controller->input->method=$verb;
        $controller->input->post=['_native_company_csrf'=>'synthetic-native-csrf'];
        foreach ([$nativeA,$nativeB] as $customer) {
            try {
                $controller->delete($customer);
                throw new RuntimeException('native deletion unexpectedly admitted');
            } catch (NativeClientHttpError $error) {
                essentialCheck($error->getCode()===404,'native deletion remains closed for either company and verb');
            }
        }
    }
}
essentialCheck($adapterA->details($nativeA)===$beforeA,'company A customer remains unchanged');
essentialCheck($adapterB->details($nativeB)===$beforeB,'company B customer remains unchanged');
echo "Native customer legacy cascade guard passed.\n";
