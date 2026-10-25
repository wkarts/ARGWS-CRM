<?php
require_once __DIR__.'/../application/services/NativeProjectRowPresentation.php';
function admin_url($v){return '/crm/admin/'.$v;}
function _l($v){return $v;}
function e($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function render_tags($v){return '<div class="tags">'.e($v).'</div>';}
function _d($v){return 'date:'.$v;}
function staff_profile_image($id,$classes,$size,$attrs){return '<img data-id="'.e($id).'" class="'.e(implode(' ',$classes)).'" title="'.e($attrs['data-title']).'">';}
function get_project_status_by_id($id){return ['name'=>'Status '.$id,'color'=>'#2563eb'];}
function adjust_hex_brightness($v,$amount){return $v;}
$renderers=['url'=>'admin_url','translate'=>'_l','escape'=>'e','tags'=>'render_tags','date'=>'_d','avatar'=>'staff_profile_image','status'=>'get_project_status_by_id','brightness'=>'adjust_hex_brightness'];
$aRow=['id'=>'4294967297','name'=>'Project <A>','clientid'=>10,'company'=>'Client <A>','tags'=>'one,two','start_date'=>'2026-10-25','deadline'=>'2026-12-25','members'=>'Ada,Ben','members_ids'=>'7,8','status'=>'85899345921','cvalue_0'=>'<b>authorized rich value</b>','date_picker_cvalue_1'=>'2026-11-01'];
$customFieldsColumns=['cvalue_0','date_picker_cvalue_1'];$n=0;
foreach([false,true]as$hasPermissionCreate)foreach([false,true]as$hasPermissionEdit)foreach([false,true]as$hasPermissionDelete)foreach(['',10]as$clientid){
 require __DIR__.'/fixtures/native-project-row-original.php';$expected=$row;
 $actual=NativeProjectRowPresentation::render($aRow,$clientid,['create'=>$hasPermissionCreate,'edit'=>$hasPermissionEdit,'delete'=>$hasPermissionDelete],$renderers,$customFieldsColumns);
 if($actual!==$expected)throw new RuntimeException('Native row DOM changed');$n++;
}
$aRow['members']='<script>bad()</script>';$aRow['members_ids']='7';
$row=NativeProjectRowPresentation::render($aRow,'',['create'=>false,'edit'=>false,'delete'=>false],$renderers);
if(str_contains($row[6],'<script>')||!str_contains($row[6],'&lt;script&gt;'))throw new RuntimeException('Member export name not escaped');$n++;
try{NativeProjectRowPresentation::render($aRow,'',['create'=>1,'edit'=>false,'delete'=>false],$renderers);throw new RuntimeException('Non-boolean grant accepted');}catch(InvalidArgumentException$e){$n++;}
$missing=$renderers;unset($missing['status']);try{NativeProjectRowPresentation::render($aRow,'',['create'=>false,'edit'=>false,'delete'=>false],$missing);throw new RuntimeException('Missing projection accepted');}catch(InvalidArgumentException$e){$n++;}
echo "Native project shared row: {$n} checks passed, exact legacy markup across grants/client filter, no global queries.\n";
