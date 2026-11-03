<?php
declare(strict_types=1);
require_once __DIR__.'/CompanySettingsStorage.php';
require_once __DIR__.'/CompanyPdfTypography.php';
require_once __DIR__.'/CompanyDocumentPdf.php';
require_once __DIR__.'/CompanyAssets.php';
/** One original Settings/PDF form for both former format groups and the actual snapshot typography. */
final class NativeCompanyPdfSettings
{
    public const FORMATS=['pdf_format_invoice','pdf_format_estimate','pdf_format_proposal','pdf_format_credit_note','pdf_format_contract','pdf_format_payment','pdf_format_statement'];
    public function __construct(private $db,private string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    public static function fields():array{return array_merge(CompanyPdfTypography::FIELDS,self::FORMATS,array_keys(CompanyPdfLayout::DEFAULTS));}
    public static function validate(array $values,bool $complete=false):void
    {
        if(array_diff(array_keys($values),self::fields())||($complete&&array_diff(self::fields(),array_keys($values))))throw new DomainException('native_settings_invalid');
        foreach(self::FORMATS as$key)if(array_key_exists($key,$values))CompanyDocumentPdf::validate($values[$key]);
        CompanyPdfLayout::validateSettings(array_intersect_key($values,CompanyPdfLayout::DEFAULTS));
        if(array_key_exists('pdf_font',$values)||array_key_exists('pdf_font_size',$values)){
            if(!array_key_exists('pdf_font',$values)||!array_key_exists('pdf_font_size',$values))throw new DomainException('native_settings_invalid');
            CompanyPdfTypography::prepare(['pdf_font'=>$values['pdf_font'],'pdf_font_size'=>$values['pdf_font_size']]);
        }
    }
    private function tables():array{return array_merge((new CompanyPdfTypography($this->db,$this->p))->tables(),['options']);}
    private function auth(int $issuer,int $actor,string $cap):void{if(!(new IssuerResourceAccess($this->db,$this->p))->can($issuer,$actor,'settings',$cap))throw new DomainException('native_settings_denied');}
    private function capture(int $issuer,int $actor):array
    {
        $this->auth($issuer,$actor,'view');$store=new CompanySettingsStorage($this->db,$this->p);$type=(new CompanyPdfTypography($this->db,$this->p))->readInOwnedTransaction($issuer,$actor);$values=['pdf_font'=>$type['pdf_font'],'pdf_font_size'=>$type['pdf_font_size']];
        foreach(self::FORMATS as$field)$values[$field]=$store->exists($issuer,$field)?$store->get($issuer,$field):'A4-PORTRAIT';foreach(CompanyPdfLayout::DEFAULTS as$field=>$default)$values[$field]=$store->exists($issuer,$field)?$store->get($issuer,$field):$default;self::validate($values,true);$assets=(new CompanyAssets($this->db,$this->p))->view($issuer,$actor);
        return['values'=>$values,'assets'=>$assets,'custom_fields'=>[],'typography_revision'=>$type['revision'],'revision'=>hash('sha256',json_encode([$issuer,$values,$assets,$type['revision']],JSON_THROW_ON_ERROR))];
    }
    public function page(int $issuer,int $actor):array{$this->auth($issuer,$actor,'view');return IssuerTransaction::run($this->db,$this->p,$this->tables(),fn()=>$this->capture($issuer,$actor));}
    public function save(int $issuer,int $actor,string $revision,array $values,array $uploads=[]):array
    {
        $this->auth($issuer,$actor,'edit');self::validate($values);if(!preg_match('/^[a-f0-9]{64}$/D',$revision)||array_diff(array_keys($uploads),['signature']))throw new DomainException('native_settings_invalid');
        return IssuerTransaction::run($this->db,$this->p,$this->tables(),function()use($issuer,$actor,$revision,$values,$uploads){
            $this->auth($issuer,$actor,'edit');(new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($issuer,$actor);$before=$this->capture($issuer,$actor);if(!hash_equals($before['revision'],$revision))throw new DomainException('native_settings_stale');
            $formats=array_intersect_key($values,array_flip(array_merge(self::FORMATS,array_keys(CompanyPdfLayout::DEFAULTS))));if($formats)(new CompanySettingsStorage($this->db,$this->p))->updateNativeSettingsInOwnedTransaction($issuer,$actor,'pdf',$formats);
            if(isset($values['pdf_font']))(new CompanyPdfTypography($this->db,$this->p))->saveInOwnedTransaction($issuer,$actor,$before['typography_revision'],['pdf_font'=>$values['pdf_font'],'pdf_font_size'=>$values['pdf_font_size']]);
            if($uploads)(new CompanyAssets($this->db,$this->p))->saveBatchInOwnedTransaction($issuer,$actor,$uploads,$before['assets']['revision']);
            $this->auth($issuer,$actor,'edit');return$this->capture($issuer,$actor);
        });
    }
}
