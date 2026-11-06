<?php
defined('BASEPATH') or exit('No direct script access allowed');
// Same columns and classes as the original contracts table. Actions keep typed identities.
$id=$contract_row['id'];$url=admin_url('contracts/contract/'.$id);
$subject='<a href="'.e($url).'" class="tw-truncate tw-max-w-xs tw-block tw-w-full tw-font-medium" title="'.e($contract_row['subject']).'">'.e($contract_row['subject']).'</a>';
if((int)$contract_row['trash']===1)$subject.='<span class="label label-danger pull-right">'._l('contract_trash').'</span>';
$subject.='<div class="row-options"><a href="'.e($url).'">'._l('view').'</a>';
if($can_edit&&$contract_row['status']!=='deleted')$subject.=' | <a href="'.e($url).'">'._l('edit').'</a>';
// The owned deletion command requires POST, revision and the module CSRF. Keep the action at its existing reviewed detail form.
if($can_delete&&$contract_row['status']!=='deleted')$subject.=$contract_row['source_kind']==='contract'?' | <a class="_delete" href="'.e(admin_url('contracts/delete/'.$id)).'">'._l('delete').'</a>':' | <a href="'.e($url).'#contract-actions">'._l('delete').'</a>';
$subject.='</div>';
$client=e($contract_row['company']);
if($can_view_customers&&(int)$contract_row['client_visible']===1)$client='<a href="'.e(admin_url('clients/client/'.$contract_row['client_id'])).'">'.$client.'</a>';
$value=$contract_row['contract_value']===null?'':'<span class="tw-font-medium">'.e(app_format_money($contract_row['contract_value'],$contract_row['currency'])).'</span>';
$project=$contract_row['project_name']!==null&&$contract_row['project_name']!==''?'<a href="'.e(admin_url('projects/view/'.$contract_row['project_id'])).'">'.e($contract_row['project_name']).'</a>':'';
$signature=(int)$contract_row['marked_as_signed']===1?'<span class="text-success tw-font-medium">'._l('marked_as_signed').'</span>':((int)$contract_row['signed']===1?'<span class="text-success tw-font-medium">'._l('is_signed').'</span>':'<span class="text-muted">'._l('is_not_signed').'</span>');
$row=[$id,$subject,$client,e($contract_row['type_name']),$value,e(_d($contract_row['datestart'])),e(_d($contract_row['dateend'])),$project,$signature];
foreach($custom_fields as$field){$value=$contract_row['custom_values'][(string)$field['id']]??'';$row[]=e($field['type']==='date_picker_time'?_dt($value):($field['type']==='date_picker'?_d($value):$value));}
$row['DT_RowClass']='has-row-options has-border-left';
if($contract_row['dateend']!==null&&$contract_row['dateend']<date('Y-m-d')&&!(int)$contract_row['signed']&&!(int)$contract_row['marked_as_signed'])$row['DT_RowClass'].=' row-border-danger';
