<?php

declare(strict_types=1);
/** Configuration stays in the native Settings route; module commands retain their own ACL/CSRF. */
final class NativeCompanyBancoBrasilSettings
{
    public static function render($ci):void
    {
        if(!CompanyRequestScope::sealedFor($ci)){show_error('Contexto indisponível.',503);return;}
        $scope=CompanyRequestScope::context();$issuer=$scope->issuerId();$actor=$scope->staffId();$prefix=db_prefix();
        $acl=new IssuerResourceAccess($ci->db,$prefix);
        if(!$acl->can($issuer,$actor,'settings','view')||!$acl->can($issuer,$actor,'banco_brasil','view')){show_error('Configuração indisponível nesta empresa.',403);return;}
        $module=IssuerStorage::query($ci->db,'SELECT active FROM '.$prefix.'modules WHERE module_name=?',['banco_brasil'])->row_array();
        if((int)($module['active']??0)!==1){show_error('Módulo Banco do Brasil não está ativo.',409);return;}
        $root=dirname(__DIR__,2).'/modules/banco_brasil/';
        require_once $root.'libraries/BancoBrasil/ModuleStore.php';require_once $root.'libraries/BancoBrasil/ChannelPreferences.php';require_once $root.'libraries/BancoBrasil/ApiService.php';require_once __DIR__.'/issuers/IssuerRegistry.php';
        $ci->output->set_header('Cache-Control: no-store');
        $csrf=$ci->session->userdata('bb_admin_csrf');if(!is_string($csrf)||!preg_match('/^[a-f0-9]{64}$/D',$csrf)){$csrf=bin2hex(random_bytes(32));$ci->session->set_userdata('bb_admin_csrf',$csrf);}
        $data=['title'=>'Banco do Brasil — Configurações','csrf'=>$csrf,'notice'=>$ci->session->userdata('bb_notice'),'selected'=>null,'connections'=>[],'layouts'=>\BancoBrasil\LayoutCatalog::all(),'layout_bindings'=>[],'channel_preferences'=>null,'channel_preferences_error'=>false,'api_profiles'=>[],'api_ready'=>false,'origination_ready'=>false,'storage_error'=>false,'can_manage'=>$acl->can($issuer,$actor,'banco_brasil','manage'),'bb_module_views'=>$root.'views/'];
        foreach(['api_charges','api_discount','api_receipt_window','api_assign_discount']as$cap)$data['can_'.$cap]=$acl->can($issuer,$actor,'banco_brasil',$cap);
        try{
            $data['selected']=(array)\BancoBrasil\Transaction::quiet($ci->db,fn()=>(new IssuerRegistry($ci->db,$prefix))->get($issuer,$actor));
            if(!$data['selected']||(int)$data['selected']['id']!==$issuer)throw new DomainException('bb_settings_denied');
            $store=new \BancoBrasil\ModuleStore($ci->db,$prefix);$data['connections']=$store->connections($issuer);$data['layout_bindings']=$store->layoutBindings($issuer);
            try{$data['channel_preferences']=(new \BancoBrasil\ChannelPreferences($ci->db,$prefix))->page($issuer,$actor);}catch(Throwable $e){$data['channel_preferences_error']=true;}
            try{$overview=(new \BancoBrasil\ApiService($ci->db,$prefix))->overview($issuer,$actor);$data['api_profiles']=$overview['profiles'];$data['api_ready']=true;}catch(Throwable $e){$data['api_ready']=false;}
            try{$data['origination_ready']=(new \BancoBrasil\Origination($ci->db,$prefix))->ready();}catch(Throwable $e){$data['origination_ready']=false;}
        }catch(Throwable $e){$data['storage_error']=true;$data['can_manage']=false;$data['connections']=$data['layout_bindings']=[];}
        if(strtoupper($ci->input->method(true))==='HEAD'){$ci->output->set_output('');return;}
        $ci->load->view('admin/settings/native_banco_brasil',$data);
    }
}
