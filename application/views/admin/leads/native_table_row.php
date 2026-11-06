<?php
defined('BASEPATH') or exit('No direct script access allowed');
$canonical=$lead_row['canonical_id'];$href='href="'.e(admin_url('leads/index/'.$canonical)).'" data-native-lead="'.e($canonical).'"';
$name='<a '.$href.' class="tw-font-medium">'.e($lead_row['name']).'</a>';$name.='<div class="row-options"><a '.$href.'>'._l('view').'</a>';
$editable=$capabilities['edit']&&$lead_row['archived']===0&&$lead_row['converted_client_id']===null;if($editable)$name.=' | <a '.$href.' data-lead-edit="1">'._l('edit').'</a>';$name.='</div>';
if($lead_row['archived']===1)$name.='<span class="label label-default">Arquivado</span>';elseif($lead_row['converted_client_id']!==null)$name.='<span class="label label-success">'._l('lead_converted_to_client').'</span>';
$status='<span class="label label-default">'.e($lead_row['status_name']).'</span>';
$tags='';foreach($lead_row['tags']??[]as$tag)$tags.='<span class="label label-tag tag">'.e($tag['name']).'</span> ';
$value=CompanyLeadMetadata::money($lead_row['lead_value']??null,$lead_row['currency']??null);$contact=($lead_row['lastcontact']??null)===null?'':_dt($lead_row['lastcontact']);
$row=['','<a '.$href.' class="tw-font-medium">'.e($lead_row['id']).'</a>',$name,e($lead_row['company']),$lead_row['email']===''?'':'<a href="mailto:'.e($lead_row['email']).'">'.e($lead_row['email']).'</a>',$lead_row['phone']===''?'':'<a href="tel:'.e($lead_row['phone']).'">'.e($lead_row['phone']).'</a>',e($value),$tags,e($lead_row['assigned_name']),$status,e($lead_row['source_name']),e($contact),e(_dt($lead_row['created_at']))];
$row['DT_RowClass']='has-row-options';$row['DT_RowAttr']=['data-native-lead-row'=>$canonical,'data-lead-revision'=>(string)$lead_row['revision']];
