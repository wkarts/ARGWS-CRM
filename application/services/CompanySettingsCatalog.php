<?php

declare(strict_types=1);
require_once __DIR__.'/CompanySettingsIdentity.php';
require_once __DIR__.'/CompanySettingsFormats.php';
require_once __DIR__.'/CompanyDocumentPdf.php';

/** Only groups consumed by the isolated company flows; never the legacy plugin catalog. */
final class CompanySettingsCatalog
{
    public static function groups(): array
    {
        return ['task_timers'=>'Cronômetros das tarefas','task_operations'=>'Prioridade e acompanhamento de tarefas','task_defaults'=>'Padrões de criação de tarefas','ticket_preferences'=>'Ordem das respostas de suporte','pdf_typography'=>'Tipografia dos PDFs','project_files'=>'Arquivos de projetos','company'=>'Informações da Empresa','email'=>'Servidor SMTP','email_content'=>'Apresentação dos e-mails',
            'documents'=>'Faturas e créditos','sales_documents'=>'Orçamentos e propostas','document_pdf'=>'PDF de faturas, créditos e contratos','sales_pdf'=>'PDF de orçamentos e propostas','localization'=>'Data e hora'];
    }

    public static function fields(string $group): array
    {
        return match($group) {
            'task_timers'=>['timer_started_change_status_in_progress'],
            'task_operations'=>['default_task_priority','new_task_auto_follower_current_member'],
            'task_defaults'=>['default_task_status','new_task_auto_assign_current_member'],
            'ticket_preferences'=>['ticket_replies_order'],
            'pdf_typography'=>['pdf_font','pdf_font_size'],
            'project_files'=>['allow_contact_to_delete_files'],
            'company'=>array_merge(['invoice_company_name','company_vat'],CompanySettingsIdentity::PROFILE_OPTIONS),
            'localization'=>['dateformat','time_format'],
            'document_pdf'=>['pdf_format_invoice','pdf_format_credit_note','pdf_format_contract'],
            'sales_pdf'=>['pdf_format_estimate','pdf_format_proposal'],
            'sales_documents'=>['estimate_prefix','predefined_terms_estimate','proposal_number_prefix','predefined_terms_proposal'],
            'documents'=>['invoice_prefix','predefined_terms_invoice','credit_note_prefix','predefined_terms_credit_note'],
            'email'=>['smtp_host','smtp_port','smtp_encryption','smtp_email','smtp_username','smtp_password'],
            'email_content'=>['email_header','email_signature','email_footer'],
            default=>throw new DomainException('company_settings_group_unavailable'),
        };
    }

    public static function defaultValue(string $group,string $field):string
    {
        if(!in_array($field,self::fields($group),true))throw new DomainException('company_settings_invalid_input');
        if($group==='pdf_typography')return $field==='pdf_font'?'dejavusans':'';
        if($group==='task_timers')return '0';
        if($group==='task_operations')return $field==='default_task_priority'?'2':'0';
        if($group==='task_defaults')return $field==='default_task_status'?'todo':'1';
        if($group==='ticket_preferences')return 'asc';
        if($group==='project_files')return '0';
        if(in_array($group,['sales_pdf','document_pdf'],true))return 'A4-PORTRAIT';
        return $group==='sales_documents'?(['estimate_prefix'=>'ORC-','proposal_number_prefix'=>'PROP-'][$field]??''):'';
    }

    public static function multiline(string $name): bool
    {
        return in_array($name,['predefined_terms_invoice','predefined_terms_credit_note','predefined_terms_estimate','predefined_terms_proposal','email_header','email_signature','email_footer'],true);
    }

    /** Validate the entire form before encryption, SQL or audit. Returns normalized safe fragments. */
    public static function prepare(string $group,array $settings): array
    {
        if(array_diff(array_keys($settings),self::fields($group)))throw new DomainException('company_settings_invalid_input');
        foreach($settings as $name=>$value) {
            if(!is_string($value)||preg_match('//u',$value)!==1
                ||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$value))throw new DomainException('company_settings_invalid_input');
            $limit=match($name){'invoice_prefix','credit_note_prefix','estimate_prefix','proposal_number_prefix'=>50,'predefined_terms_invoice','predefined_terms_credit_note','predefined_terms_estimate','predefined_terms_proposal'=>10000,
                'email_header','email_signature','email_footer'=>60000,'smtp_password'=>4096,default=>2000};
            if(strlen($value)>$limit)throw new DomainException('company_settings_invalid_input');
            if($group!=='company'&&$name!=='smtp_password'&&!self::multiline($name)&&preg_match('/[\r\n]/',$value))throw new DomainException('company_settings_invalid_input');
        }
        if($group==='project_files'&&(array_keys($settings)!==['allow_contact_to_delete_files']||!in_array($settings['allow_contact_to_delete_files'],['0','1'],true)))throw new DomainException('company_settings_invalid_input');
        if($group==='task_timers'){require_once __DIR__.'/CompanyTaskTimerPreferences.php';CompanyTaskTimerPreferences::prepare($settings);}
        if($group==='task_operations'){require_once __DIR__.'/CompanyTaskOperationPreferences.php';CompanyTaskOperationPreferences::prepare($settings);}
        if($group==='task_defaults'){require_once __DIR__.'/CompanyTaskPreferences.php';CompanyTaskPreferences::prepare($settings);}
        if($group==='ticket_preferences'){require_once __DIR__.'/CompanyTicketPreferences.php';CompanyTicketPreferences::prepare($settings);}
        if($group==='pdf_typography'){require_once __DIR__.'/CompanyPdfTypography.php';CompanyPdfTypography::prepare($settings);}
        if($group==='localization')CompanySettingsFormats::validate($settings);
        if(in_array($group,['sales_pdf','document_pdf'],true))foreach($settings as $value)CompanyDocumentPdf::validate($value);
        if($group==='email') {
            if(isset($settings['smtp_encryption'])&&!in_array($settings['smtp_encryption'],['','ssl','tls'],true))throw new DomainException('company_settings_invalid_input');
            if(($settings['smtp_port']??'')!==''&&(!ctype_digit($settings['smtp_port'])||(int)$settings['smtp_port']<1||(int)$settings['smtp_port']>65535))throw new DomainException('company_settings_invalid_input');
            if(($settings['smtp_email']??'')!==''&&!filter_var($settings['smtp_email'],FILTER_VALIDATE_EMAIL))throw new DomainException('company_settings_invalid_input');
            if(($settings['smtp_host']??'')!==''&&!preg_match('/^(?:[a-zA-Z0-9](?:[a-zA-Z0-9.-]{0,251}[a-zA-Z0-9])?|\[[a-fA-F0-9:]+\])$/D',$settings['smtp_host']))throw new DomainException('company_settings_invalid_input');
        }
        if($group==='email_content') {
            foreach($settings as $name=>$value)$settings[$name]=self::emailHtml($value);
        }
        return $settings;
    }

    /** Independent HTML fragments; no scripts, remote images, forms, stylesheets or hooks. */
    private static function emailHtml(string $html): string
    {
        if(!class_exists('HTMLPurifier'))require_once __DIR__.'/../vendor/ezyang/htmlpurifier/library/HTMLPurifier.auto.php';
        $config=HTMLPurifier_Config::createDefault();
        $config->set('Core.Encoding','UTF-8');
        $config->set('Cache.DefinitionImpl',null);
        $config->set('HTML.Doctype','HTML 4.01 Transitional');
        $config->set('HTML.Allowed','div[style],p[style],span[style],h1[style],h2[style],h3[style],strong,b,em,i,u,br,hr,ul,ol,li,a[href|title],table[style|cellpadding|cellspacing|border],thead,tbody,tr,th[style|colspan|rowspan],td[style|colspan|rowspan]');
        $config->set('CSS.AllowedProperties',['color','background-color','font-family','font-size','font-weight','font-style','text-align','text-decoration','padding','padding-top','padding-right','padding-bottom','padding-left','margin','margin-top','margin-right','margin-bottom','margin-left','border','border-collapse','width']);
        $config->set('URI.AllowedSchemes',['https'=>true,'mailto'=>true]);
        return (new HTMLPurifier($config))->purify($html);
    }
}
