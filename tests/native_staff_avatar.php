<?php
define('APPPATH',__DIR__.'/../application/');
require APPPATH.'services/StaffAvatarPresentation.php';
$n=0;foreach([null,(object)['profile_image'=>null],(object)['profile_image'=>'photo.png']]as$data)foreach([false,true]as$exists){$html=StaffAvatarPresentation::render($data,'7',['native-class'],'small',['data-title'=>'A < B'],static fn($p)=>'/'.$p,static fn($p)=>$exists);if(!str_contains($html,'data-title="A &lt; B"')||!str_contains($html,'class="native-class"'))throw new RuntimeException('Native avatar markup lost');$photo=$data&&$data->profile_image!==null&&$exists;if(str_contains($html,'small_photo.png')!==$photo)throw new RuntimeException('Actual file presence ignored');++$n;}
foreach(['../private','..\\private',"x\0.png"]as$name){$called=false;$html=StaffAvatarPresentation::render((object)['profile_image'=>$name],'7',[],'small',[],static fn($p)=>'/'.$p,function()use(&$called){$called=true;return true;});if($called||!str_contains($html,'user-placeholder.jpg'))throw new RuntimeException('Unsafe file lookup');++$n;}
echo "Native staff avatar: $n checks passed.\n";

// Compare the actual old native function with the shared renderer, including exact attribute order.
function e($v){return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function base_url($p){return '/'.$p;}
function get_staff_user_id(){return 7;}
require __DIR__.'/fixtures/native-staff-avatar-original.php';
$dir=sys_get_temp_dir().'/crm-avatar-'.bin2hex(random_bytes(8));mkdir($dir,0700);mkdir($dir.'/uploads/staff_profile_images/7',0700,true);$cwd=getcwd();chdir($dir);
try{
    foreach([null,'missing.png','photo.png']as$file){$GLOBALS['current_user']=(object)['profile_image'=>$file];if($file==='photo.png')file_put_contents('uploads/staff_profile_images/7/small_photo.png','synthetic-image-presence');
        $old=original_staff_profile_image('7',['native-class'],'small',['data-title'=>'A < B']);$new=StaffAvatarPresentation::render($GLOBALS['current_user'],'7',['native-class'],'small',['data-title'=>'A < B'],'base_url','file_exists');if($old!==$new)throw new RuntimeException('Shared renderer changed native avatar bytes');++$n;
    }
}finally{chdir($cwd);@unlink($dir.'/uploads/staff_profile_images/7/small_photo.png');rmdir($dir.'/uploads/staff_profile_images/7');rmdir($dir.'/uploads/staff_profile_images');rmdir($dir.'/uploads');rmdir($dir);}
echo "Native avatar exact historical comparison: $n cumulative checks passed.\n";
