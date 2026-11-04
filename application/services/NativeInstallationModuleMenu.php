<?php

declare(strict_types=1);

/** Preserve installed module navigation without invoking module hooks/providers. */
final class NativeInstallationModuleMenu
{
    public static function append($ci,array &$menus,array &$children):void
    {
        $context=CompanyRequestScope::context();
        if(!$context->isPrimary())return;
        $acl=new IssuerResourceAccess($ci->db,db_prefix());
        if(!$acl->administrative($context->issuerId(),$context->staffId()))return;
        $active=array_fill_keys(array_column(IssuerStorage::query($ci->db,
            'SELECT module_name FROM '.db_prefix().'modules WHERE active=1')->result_array(),'module_name'),true);
        foreach(require __DIR__.'/NativeInstallationModuleMenuCatalog.php' as $entry){
            if(!isset($active[$entry['module']])||$entry['module']==='banco_brasil')continue;
            $type=$entry['kind'];$parent=$entry['parent'];$href=$entry['href']??'#';
            if(str_starts_with($href,'@admin/'))$href=CompanyRequestScope::adminUrl($ci,substr($href,7));
            elseif(str_starts_with($href,'@base/'))$href=base_url(substr($href,6));
            else $href='#';
            $item=['slug'=>$entry['child']?$entry['slug']:$parent,'name'=>$entry['name'],
                'href'=>$href,'icon'=>$entry['icon']??'','position'=>$entry['position']??50,'children'=>[]];
            if($entry['child'])$children[$type][$parent][]=$item;
            else {
                if($href==='#'||!empty($entry['collapse']))$item['collapse']=true;
                $menus[$type][$parent]=$item;
            }
        }
    }
}
