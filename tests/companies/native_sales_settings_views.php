<?php
// Render the original two Settings partials with explicit owned data and no global lookups.
require __DIR__.'/sales.php';
require_once APPPATH.'services/NativeCompanySalesSettings.php';
require_once APPPATH.'services/NativeCompanySettingsForms.php';
function get_option($key){throw new LogicException('Native view consulted a global option: '.$key);}
function get_custom_fields($fieldto){throw new LogicException('Native view consulted global custom fields');}
function total_rows(...$unused){throw new LogicException('Native view consulted global table');}
function _l($key,...$unused){return $key;}
function esc($value){return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function render_input($name,$label,$value,...$unused){return '<input name="'.esc($name).'" value="'.esc($value).'">';}
function render_textarea($name,$label,$value,...$unused){return '<textarea name="'.esc($name).'">'.esc($value).'</textarea>';}
function render_yes_no_option($key,$label,$tooltip='',$yes='',$no='',$one='',$zero='',$value=null){if($value===null)throw new LogicException('Native radio used global fallback');echo '<input name="settings['.esc($key).']" value="'.esc($value).'">';}
function clear_textarea_breaks($value){return $value;}
$start=$GLOBALS['essentialChecks'];$forms=new NativeCompanySalesSettings($db,$p);
$estimateA=$forms->page($a,7,'estimates');$estimateB=$forms->page($b,7,'estimates');
$input=$estimateA['values'];$input['estimate_prefix']='A-"<script>'; $input['show_project_on_estimate']='0';$saved=$forms->save($a,7,'estimates',$estimateA['revision'],$input);
$renderEstimates=static function(array $values):string{$native_settings_estimate_values=$values;ob_start();try{include APPPATH.'views/admin/settings/includes/estimates.php';return ob_get_clean();}catch(Throwable $e){ob_end_clean();throw $e;}};
$first=$renderEstimates($saved['values']);$second=$renderEstimates($estimateB['values']);
foreach(NativeCompanySalesSettings::fields('estimates') as $name)essentialCheck(str_contains($first,'name="settings['.$name.']"'),'native estimate view retains field '.$name);
essentialCheck(str_contains($first,'A-&quot;&lt;script&gt;')&&!str_contains($first,'A-"<script>'),'estimate prefix is safely escaped');
essentialCheck(str_contains($first,'name="settings[show_project_on_estimate]" value="0"')&&str_contains($second,'name="settings[show_project_on_estimate]" value="1"'),'same native view renders independent company radio values');
essentialCheck(!str_contains($second,'A-&quot;&lt;script&gt;'),'A values never enter B view');
$proposalA=$forms->page($a,7,'proposals');$proposalB=$forms->page($b,7,'proposals');$value=$proposalA['values'];$value['proposal_number_prefix']='P-A-';$value['predefined_terms_proposal']='<script>text</script>';$savedProposal=$forms->save($a,7,'proposals',$proposalA['revision'],$value);
$renderProposals=static function(array $values,array $fields):string{$native_settings_proposal_values=$values;$native_settings_proposal_field_definitions=$fields;ob_start();try{include APPPATH.'views/admin/settings/includes/proposals.php';return ob_get_clean();}catch(Throwable $e){ob_end_clean();throw $e;}};
$html=$renderProposals($savedProposal['values'],[['name'=>'<script>x</script>','id'=>'owned-1','show_on_client_portal'=>0]]);$other=$renderProposals($proposalB['values'],[]);
foreach(NativeCompanySalesSettings::fields('proposals') as $name)essentialCheck(str_contains($html,'name="settings['.$name.']"'),'native proposal view retains field '.$name);
essentialCheck(str_contains($html,'&lt;script&gt;text&lt;/script&gt;')&&!str_contains($html,'<script>text</script>'),'proposal terms remain literal');
essentialCheck(str_contains($html,'&lt;script&gt;x&lt;/script&gt;')&&!str_contains($html,'<script>x</script>'),'field labels are HTML escaped');
essentialCheck(!str_contains($other,'P-A-')&&!str_contains($other,'owned-1'),'other company view receives neither A settings nor A field definitions');
essentialCheck(!in_array('estimates',NativeCompanySettingsForms::availableGroups(),true)&&!in_array('proposals',NativeCompanySettingsForms::availableGroups(),true),'incomplete downstream settings are not exposed');
echo 'Native sales settings views: '.($GLOBALS['essentialChecks']-$start)." added checks; native HTTP groups remain closed.\n";
