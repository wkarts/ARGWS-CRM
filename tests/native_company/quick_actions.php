<?php
declare(strict_types=1);
require_once __DIR__.'/../../application/services/NativeAdminQuickActionsCatalog.php';
function _l($key){return 'translated:'.$key;}function _d($date){return 'fixture-date';}function get_option($key){if($key!=='access_tickets_to_none_staff_members')throw new RuntimeException('Unexpected option');return 0;}function is_staff_member(){return $GLOBALS['fixtureStaff'];}
// Compare against the frozen, published native method, not a reconstructed expectation.
$method=file_get_contents(__DIR__.'/quick-actions-native-method.txt');
eval('class QuickNativeFixture { public $app; '.str_replace('private function','public function',$method).' }');
foreach([true,false] as $staff){$GLOBALS['fixtureStaff']=$staff;$legacy=new QuickNativeFixture();$legacy->app=new class {public array $entries=[];function add_quick_actions_link($entry){$this->entries[]=$entry;}};$legacy->init_quick_actions_links();$actual=NativeAdminQuickActionsCatalog::entries(!$staff,'fixture-date');foreach($actual as &$entry){$entry['name']=_l($entry['label']);unset($entry['label']);}unset($entry);if($actual!==$legacy->app->entries){foreach($actual as $i=>$entry){$x=$entry;$y=$legacy->app->entries[$i];ksort($x);ksort($y);if($x!==$y)throw new RuntimeException('Native quick-action descriptor mismatch '.$i);}}if(count($actual)!==15)throw new RuntimeException('Native quick actions omitted');}
echo "Native quick actions: all 15 descriptors, order, URLs, handlers, permissions and both ticket modes preserved\n";
