<?php
/** Customer menu visibility uses actual membership, ACL and owned assignments. */
require __DIR__.'/native_clients_controller.php';
require_once APPPATH.'services/NativeCompanyAdminPresentation.php';
$acl=new IssuerResourceAccess($db,$p);
essentialCheck(NativeCompanyAdminPresentation::customersMenuAvailable($db,$p,41,12,$acl),'customer view permission admits menu');
$db->query("DELETE FROM {$p}issuers_resource_permissions WHERE issuer_id=41 AND staff_id=12 AND feature='customers' AND capability='view'");
essentialCheck(NativeCompanyAdminPresentation::customersMenuAvailable($db,$p,41,12,$acl),'customer create-only permission admits menu');
$db->query("DELETE FROM {$p}issuers_resource_permissions WHERE issuer_id=41 AND staff_id=12 AND feature='customers' AND capability='create'");
essentialCheck(!NativeCompanyAdminPresentation::customersMenuAvailable($db,$p,41,12,$acl),'no customer view, create or assignment hides menu');
$db->query("INSERT INTO {$p}company_customer_admins(issuer_id,client_id,staff_id,date_assigned) VALUES(41,?,12,?)",[$nativeA,'2026-10-27 00:00:00']);
essentialCheck(NativeCompanyAdminPresentation::customersMenuAvailable($db,$p,41,12,$acl),'owned customer assignment admits menu without global view');
$db->query("UPDATE {$p}issuers_staff SET can_view=0 WHERE issuer_id=41 AND staff_id=12");
essentialCheck(!NativeCompanyAdminPresentation::customersMenuAvailable($db,$p,41,12,$acl),'revoked membership closes assigned customer menu');
echo "Native customer menu uses scoped ACL and assignments.\n";
