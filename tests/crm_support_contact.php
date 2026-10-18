<?php
define('BASEPATH', __DIR__);
$options=[];
function get_option($key){return $GLOBALS['options'][$key]??'';}
function html_escape($s){return htmlspecialchars($s,ENT_QUOTES,'UTF-8');}
require __DIR__.'/../application/helpers/themes_helper.php';
$n=0;function check($ok,$label){global $n;$n++;if(!$ok)throw new RuntimeException($label);}
foreach(['CRM_SUPPORT_WHATSAPP','CRM_SUPPORT_EMAIL','CRM_SUPPORT_SITE_URL'] as $name)putenv($name.'=');
check(support_contact_destination()===[] && support_contact_link()==='', 'absent configuration hidden');
$options=['support_contact_channel'=>'website','support_site_url'=>'https://support.example.test/help'];
check(support_contact_destination()['href']==='https://support.example.test/help','configured site');
check(strpos(support_contact_link(),'noopener noreferrer nofollow')!==false,'safe external link');
foreach(['javascript:alert(1)','http://support.example.test','https://user:pass@example.test','//example.test'] as $url){$options['support_site_url']=$url;check(support_contact_destination()===[],'unsafe URL hidden');}
$options=['support_contact_channel'=>'email','support_email'=>'support@example.test'];check(support_contact_destination()['href']==='mailto:support@example.test','email supported');
$options['support_email']="bad\r\nBcc:bad@example.test";check(support_contact_destination()===[],'email header injection denied');
$options=['support_contact_channel'=>'whatsapp','support_whatsapp'=>'(11) 99999-8888'];check(support_contact_destination()['href']==='https://wa.me/5511999998888','existing phone normalization');
$options=['support_contact_channel'=>'website'];putenv('CRM_SUPPORT_SITE_URL=https://env.example.test');check(support_contact_destination()['href']==='https://env.example.test','existing ENV fallback');
$options['support_site_url']='https://local.example.test';check(support_contact_destination()['href']==='https://local.example.test','local config priority');
$menu=file_get_contents(__DIR__.'/../application/views/admin/includes/setup_menu.php');check(strpos($menu,'ARGWS_RELEASES_URL')===false && strpos($menu,'support_contact_destination()')!==false,'setup help uses support config');
check(strpos(file_get_contents(__DIR__.'/../application/config/constants.php'),'https://github.com/wkarts/argws-crm/releases')!==false,'technical releases retained');
foreach (['application/views/admin/settings/includes/update.php','install/finish.php'] as $file) {
 $html=file_get_contents(__DIR__.'/../'.$file);
 check(strpos($html,'github.com')===false && strpos($html,'ARGWS_RELEASES_URL')===false,'no visible repository link: '.$file);
}
echo "CRM support contact: $n checks passed\n";
