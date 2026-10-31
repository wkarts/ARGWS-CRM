<?php
/** Official Projects::view composes the owned detail without hydrating the global model. */
require __DIR__.'/native_projects_controller.php';
require __DIR__.'/portal_operations_schema_fixture.php';
$controller->session=new class{private array $data=[];function userdata($key){return $this->data[$key]??null;}function set_userdata($key,$value){$this->data[$key]=$value;}};
require_once dirname(__DIR__).'/support/NativeProjectChartFixture.php';
$canonical=NativeCompanyResourceId::encode('company_project',$pa);
$controller->input->verb='GET';$controller->input->query=[];$controller->load->views=[];
CompanyRequestScope::$issuer=$a;CompanyRequestScope::$actor=7;
$controller->view($canonical);
$view=$controller->load->views[0]??null;
essentialCheck(is_array($view)&&$view[0]==='admin/projects/view','Official project detail dispatches the original project view');
$data=$view[1];
essentialCheck(($data['native_project_view']['canonical_id']??null)===$canonical&&isset($data['native_project_overview'],$data['native_project_files']),'Project detail carries only the issuer-owned overview and file projection');
essentialCheck(($data['native_project_view']['context_token']??null)==='synthetic-project-context'&&($data['native_project_view']['csrf_token']??null)==='synthetic-project-csrf','Project detail binds official mutations to context and native CSRF');
essentialCheck(in_array('Cache-Control: no-store',$controller->output->headers,true),'Project detail is never cached across companies');

CompanyRequestScope::$issuer=$b;$reject(fn()=>$controller->view($canonical),403);
CompanyRequestScope::$issuer=$a;$controller->input->verb='POST';$reject(fn()=>$controller->view($canonical),403);
echo "Official project detail: owned overview/files, original view, method and cross-company guards passed.\n";
