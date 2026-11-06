<?php

declare(strict_types=1);

/** Original installation settings whose writers have not moved to issuer storage. */
final class NativeSettingsCompatibility
{
    public const PRIMARY_GROUPS = [
        'email', 'invoices',
        'credit_notes', 'subscriptions', 'payment_gateways', 'leads', 'pusher',
        'e_sign', 'cronjob', 'ai', 'update', 'info', 'argws_platform',
        'sms', 'openai', 'einvoice', 'products',
    ];

    public static function usesInstallationForm(bool $primary, $group): bool
    {
        return $primary && is_string($group) && in_array($group, self::PRIMARY_GROUPS, true);
    }

    /** Static links only; the original primary renderer loads actual providers. */
    public static function appendInstallationSections(array $sections,array $activeModules):array
    {
        $entries=[
            ['other','sms','SMS','admin/settings/includes/sms',null],
            ['ai','openai','OpenAI','openai/settings','openai'],
            ['finance','einvoice','Fatura eletrônica','einvoice/settings','einvoice'],
            ['other','products','Produtos','products/settings','products'],
        ];
        foreach($entries as [$section,$id,$name,$view,$module]){
            if($module!==null&&!in_array($module,$activeModules,true))continue;
            if(!isset($sections[$section]))continue;
            if(in_array($id,array_column($sections[$section]['children'],'id'),true))continue;
            $sections[$section]['children'][]=['id'=>$id,'name'=>$name,'view'=>$view,'icon'=>'fa-solid fa-gears','position'=>100];
        }
        return $sections;
    }
}
