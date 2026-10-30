<?php
require __DIR__.'/staff_roles_endpoints.php';require_once APPPATH.'services/NativeCompanyStaffEndpoints.php';
$page=$s->page(41,7);$id=$s->save(41,7,null,$page['revision'],'Original selector',['tasks'=>['view']]);$wire=NativeCompanyResourceId::encode('company_staff_role',$id);
essentialCheck(NativeCompanyStaffEndpoints::roleChanged($ci,$wire)===['tasks'=>['view']],'same staff role_changed returns native permissions shape');
CompanyRequestScope::$issuer=82;settingThrows(fn()=>NativeCompanyStaffEndpoints::roleChanged($ci,$wire),'native_staff_role_denied','foreign scoped role denied');CompanyRequestScope::$issuer=41;
settingThrows(fn()=>NativeCompanyStaffEndpoints::roleChanged($ci,(string)$id),'native_staff_role_denied','global role cannot alias scoped role');
echo "Staff role_changed: three SQLite source/tenant/shape checks; context boundary double.\n";
