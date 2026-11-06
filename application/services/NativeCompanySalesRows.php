<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanySalesTable.php';
require_once __DIR__.'/NativeCompanyResourceReferences.php';
require_once __DIR__.'/TagPresentation.php';

/** Original native column order, numbers and row actions, using already-authorized source documents. */
final class NativeCompanySalesRows
{
    public function __construct(private $db,private string $p){}
    private static function escape($value):string { return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
    public function data(int $issuer,int $actor,string $kind,array $document,array $definitions=[]):array
    {
        $e=[self::class,'escape'];$type=NativeCompanySalesDirectory::type($kind);$id=$document['id'];
        $acl=new IssuerResourceAccess($this->db,$this->p);
        if(!$acl->can($issuer,$actor,$kind,'view'))throw new DomainException('company_sales_denied');
        $view=admin_url($kind.'/list_'.$kind.'/'.$id);$open=' data-native-sales-id="'.$e($id).'"';
        $link='<a href="'.$e($view).'"'.$open.' class="tw-font-medium">'.$e($document['formatted_number']).'</a>';
        $link.='<div class="row-options"><a href="'.$e($view).'"'.$open.'>'._l('view').'</a>';
        if($acl->can($issuer,$actor,$kind,'edit')&&($document['origin']==='owned'?$document['state']==='open':$document['editable']))
            $link.=' | <a href="'.$e(admin_url($kind.'/'.$type.'/'.$id)).'">'._l('edit').'</a>';
        $link.='</div>';
        $money=$document['origin']==='legacy'?$document['stored_amounts']:CompanyInvoiceLines::calculate($document['payload']['items'],
            array_intersect_key($document['payload'],array_flip(['discount_mode','discount_value','discount_type','adjustment','calculation_contract'])));
        $currency=$document['context']['currency']['name'];$amount='<span class="tw-font-medium">'.$e(app_format_money($document['total'],$currency)).'</span>';
        if($document['invoice_id'])$amount.='<br /><span class="text-success tw-text-sm tw-font-medium">'._l('estimate_invoiced').'</span>';
        $customer=$e($document['context']['recipient']['name']);$clientId=(int)$document['payload']['client_id'];
        if($clientId>0&&$acl->can($issuer,$actor,'customers','view')){
            try{$ref=NativeCompanyResourceReferences::resolve($this->db,$this->p,$issuer,$actor,'client',$clientId);$customer='<a href="'.$e(admin_url($ref['url'])).'">'.$customer.'</a>';}
            catch(DomainException $denied){if(!str_contains($denied->getMessage(),'denied'))throw $denied;}
        }
        $project='';$projectId=$document['origin']==='legacy'?($document['project_id']??0):
            (isset($document['payload']['native_project']['source_id'])?NativeCompanyResourceId::encode('company_project',$document['payload']['native_project']['source_id']):0);
        if($projectId&&$acl->can($issuer,$actor,'projects','view')){
            try{$ref=NativeCompanyResourceReferences::resolve($this->db,$this->p,$issuer,$actor,'project',$projectId);$project='<a href="'.$e(admin_url($ref['url'])).'">'.$e($ref['name']).'</a>';}
            catch(DomainException $denied){if(!str_contains($denied->getMessage(),'denied'))throw $denied;}
        }
        $tags='';
        if($this->db->table_exists($this->p.'company_work_tag_links')){
            require_once __DIR__.'/NativeCompanyResourceTags.php';
            $tags=TagPresentation::render((new NativeCompanyResourceTags($this->db,$this->p))->read($issuer,$actor,$type,$id)['tags']);
        }
        $status=(int)$document['native_status'];$color=($kind==='estimates'?[1=>'default',2=>'info',3=>'danger',4=>'success',5=>'warning']:
            [1=>'default',2=>'danger',3=>'success',4=>'info',5=>'info',6=>'default'])[$status];
        $statusHtml='<span class="label label-'.$color.' s-status '.$type.'-status-'.$status.'">'.$e(NativeCompanySalesTable::statusLabel($kind,$status)).'</span>';
        $date=$document['payload']['date'];$due=$document['payload']['due_date'];
        $row=$kind==='estimates'?[
            $link,$amount,'<span class="tw-font-medium">'.$e(app_format_money($money['total_tax']??$money['tax'],$currency)).'</span>',
            $e(substr($date,0,4)),$customer,$project,$tags,$e(_d($date)),$e(_d($due)),$e($document['reference_no']??''),$statusHtml,
        ]:[
            $link,'<a href="'.$e($view).'"'.$open.'>'.$e($document['subject']).'</a>',$customer,$amount,$e(_d($date)),$e(_d($due)),
            $project,$tags,$e(_d($document['created_at'])),$statusHtml,
        ];
        if($definitions){
            require_once __DIR__.'/NativeCompanyCustomFields.php';
            $values=(new NativeCompanyCustomFields($this->db,$this->p))->values($issuer,$actor,$type,$id);
            foreach($definitions as $definition)if((int)$definition['show_on_table']===1){
                $value=$values[(string)$definition['id']]??'';
                $row[]=$e(in_array($definition['type'],['date_picker','date_picker_time'],true)?_d($value):$value);
            }
        }
        if(!$acl->can($issuer,$actor,$kind,'view'))throw new DomainException('company_sales_denied');
        return $row;
    }
}
