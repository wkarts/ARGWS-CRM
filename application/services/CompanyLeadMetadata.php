<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyInvoiceAmounts.php';
require_once __DIR__.'/NativeAvailableLanguages.php';
require_once __DIR__.'/NativeCompanyResourceId.php';
require_once __DIR__.'/CompanySettingsFormats.php';

/** Optional original Lead fields. Absent native_fields leaves the historical request body unchanged. */
final class CompanyLeadMetadata
{
    public const VERSION=1;
    // The lengths follow the real 4.0.2 lead columns, not invented client/address columns.
    public const TEXT=['title'=>100,'website'=>150,'address'=>100,'city'=>100,'state'=>50,'zip'=>15,'default_language'=>40];

    private static function invalid():never { throw new DomainException('company_lead_metadata_invalid'); }
    private static function text($value,int $max,bool $multiline=false,bool $trim=true):string
    {
        if(!is_string($value)||preg_match('//u',$value)!==1||mb_strlen($value)>$max
            ||preg_match($multiline?'/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/':'/[\x00-\x1f\x7f]/',$value))self::invalid();
        return$trim?trim($value):$value;
    }
    private static function integer($value,int $minimum=0):int
    {
        if((!is_int($value)&&!is_string($value))||!preg_match('/^(?:0|[1-9][0-9]{0,9})$/D',(string)$value)
            ||(int)$value<$minimum||(int)$value>2147483647)self::invalid();
        return(int)$value;
    }
    public static function decimal($value):?string
    {
        if($value===null||$value==='')return null;
        try{$minor=CompanyInvoiceAmounts::minor($value,true);if(abs($minor)>CompanyInvoiceAmounts::MAX)self::invalid();return CompanyInvoiceAmounts::decimal($minor);}
        catch(DomainException $error){self::invalid();}
    }
    public static function datetime($value):?string
    {
        if($value===null||$value==='')return null;
        if(!is_string($value)||!preg_match('/^[1-9][0-9]{3}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}$/D',$value))self::invalid();
        $date=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$value,new DateTimeZone('UTC'));
        if(!$date||$date->format('Y-m-d H:i:s')!==$value)self::invalid();
        return$value;
    }
    public static function tags($values):array
    {
        if(!is_array($values)||!array_is_list($values)||count($values)>100)self::invalid();$out=[];$seen=[];
        foreach($values as$value){$label=self::text($value,100);$key=mb_strtolower($label,'UTF-8');if($label===''||isset($seen[$key]))self::invalid();$seen[$key]=true;$out[]=$label;}
        return$out;
    }
    public static function customFields($values):array
    {
        if(!is_array($values)||count($values)>256)self::invalid();$out=[];
        foreach($values as$id=>$value){
            try{$canonical=NativeCompanyResourceId::decode('custom_field',$id)['canonical_id'];}catch(Throwable $error){self::invalid();}
            if(is_array($value)){if(!array_is_list($value)||count($value)>256)self::invalid();$out[$canonical]=[];foreach($value as$part)$out[$canonical][]=self::text($part,65535,true,false);}
            else $out[$canonical]=self::text($value,65535,true,false);
        }
        ksort($out,SORT_STRING);if(strlen(json_encode($out,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE))>262144)self::invalid();return$out;
    }
    public static function normalize($input):array
    {
        $allowed=array_merge(['version','form_revision','formats','country','lead_value','lastcontact','contacted_today','tags','custom_fields'],array_keys(self::TEXT));
        if(!is_array($input)||array_diff(array_keys($input),$allowed)||!in_array($input['version']??null,[self::VERSION,(string)self::VERSION],true)
            ||!is_string($input['form_revision']??null)||!preg_match('/^[a-f0-9]{64}$/D',$input['form_revision']))self::invalid();
        $formats=$input['formats']??null;if(!is_array($formats))self::invalid();try{CompanySettingsFormats::validate($formats);}catch(DomainException $error){self::invalid();}
        $out=['version'=>self::VERSION,'form_revision'=>$input['form_revision'],'formats'=>['dateformat'=>$formats['dateformat'],'time_format'=>$formats['time_format']]];foreach(self::TEXT as$field=>$length)$out[$field]=self::text($input[$field]??'',$length,$field==='address');
        if($out['default_language']!==''&&!in_array($out['default_language'],NativeAvailableLanguages::all(),true))self::invalid();
        $out['country']=self::integer(($input['country']??'')===''?0:$input['country']);$out['lead_value']=self::decimal($input['lead_value']??null);
        $out['lastcontact']=self::datetime($input['lastcontact']??null);$out['contacted_today']=self::integer($input['contacted_today']??0);
        if($out['contacted_today']>1||($out['contacted_today']===1&&$out['lastcontact']!==null))self::invalid();
        $out['tags']=self::tags($input['tags']??[]);$out['custom_fields']=self::customFields($input['custom_fields']??[]);
        return$out;
    }
    /** Capture public currency reference bytes, without changing the global catalogue or converting amounts. */
    public static function currency(array $row):array
    {
        $out=['id'=>self::integer($row['id']??null,1)];
        foreach(['name'=>100,'symbol'=>10,'decimal_separator'=>5,'thousand_separator'=>5,'placement'=>10]as$field=>$length)$out[$field]=self::text($row[$field]??null,$length,false,false);
        if(trim($out['name'])===''||$out['decimal_separator']===''||!in_array($out['placement'],['before','after'],true))self::invalid();return$out;
    }
    /** Resolve the clock only inside the first successful create work; request replay does not call this again. */
    public static function values(array $input,?array $currency,string $now):array
    {
        $input=self::normalize($input);$now=self::datetime($now);if($now===null)self::invalid();
        $out=array_intersect_key($input,self::TEXT);$out['country']=$input['country'];$out['lead_value']=$input['lead_value'];
        if($out['lead_value']!==null&&$currency===null)self::invalid();
        $out['currency']=$out['lead_value']===null?null:self::currency($currency);
        $out['lastcontact']=$input['contacted_today']===1?$now:$input['lastcontact'];return$out;
    }
    public static function hash(array $value):string { return hash('sha256',json_encode($value,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)); }
    public static function columns(array $values):array
    {
        $out=array_diff_key($values,['currency'=>true]);$out['currency_json']=$values['currency']===null?null:json_encode(self::currency($values['currency']),JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
        return$out;
    }
    /** Stored DECIMAL strings remain exact and the currency snapshot cannot silently fall back to today's catalogue. */
    public static function fromRow(array $row):array
    {
        try{
            $input=['version'=>self::VERSION,'form_revision'=>str_repeat('0',64),'formats'=>['dateformat'=>'Y-m-d|%Y-%m-%d','time_format'=>'24']]+array_intersect_key($row,self::TEXT);
            foreach(['country','lead_value','lastcontact']as$field){if(!array_key_exists($field,$row))self::invalid();$input[$field]=$row[$field];}
            if(!array_key_exists('currency_json',$row)||!isset($row['updated_at']))self::invalid();
            $currency=$row['currency_json']===null?null:json_decode($row['currency_json'],true,16,JSON_THROW_ON_ERROR);
            if($currency!==null&&(!is_array($currency)||self::currency($currency)!==$currency))self::invalid();
            if(($input['lead_value']===null)!==($currency===null))self::invalid();
            $values=self::values($input,$currency,$row['updated_at']);$columns=self::columns($values);
            foreach($columns as$field=>$expected)if(!array_key_exists($field,$row)||($expected===null?$row[$field]!==null:(string)$expected!==(string)$row[$field]))self::invalid();
            self::integer($row['lead_id']??null,1);self::integer($row['issuer_id']??null,1);self::integer($row['revision']??null,1);
            return$values;
        }catch(Throwable $error){throw new DomainException('company_lead_metadata_corrupt');}
    }
    public static function money(?string $amount,?array $currency):string
    {
        if($amount===null)return'';if($currency===null)self::invalid();$currency=self::currency($currency);$amount=self::decimal($amount);
        $negative=str_starts_with($amount,'-');[$integer,$fraction]=explode('.',ltrim($amount,'-'));
        $groups=[];while(strlen($integer)>3){array_unshift($groups,substr($integer,-3));$integer=substr($integer,0,-3);}array_unshift($groups,$integer);
        $number=($negative?'-':'').implode($currency['thousand_separator'],$groups).$currency['decimal_separator'].$fraction;
        return$currency['placement']==='before'?$currency['symbol'].$number:$number.$currency['symbol'];
    }
    /** Plain display only; the original form may store a bare domain and never makes it executable. */
    public static function websiteHref(string $value):?string
    {
        $value=self::text($value,self::TEXT['website']);if($value===''||preg_match('/[\x00-\x20\x7f]/',$value))return null;
        if(!preg_match('~^https?://~i',$value))return null;
        return filter_var($value,FILTER_VALIDATE_URL)!==false?$value:null;
    }
    /** Parse persisted link markup inertly; callers escape both returned values and discard every original attribute. */
    public static function linkValue(string $value):array
    {
        $document=new DOMDocument();$previous=libxml_use_internal_errors(true);
        try{$document->loadHTML('<?xml encoding="utf-8" ?><div>'.$value.'</div>',LIBXML_NONET);$anchor=$document->getElementsByTagName('a')->item(0);}
        finally{libxml_clear_errors();libxml_use_internal_errors($previous);}
        if(!$anchor)return['text'=>$value,'href'=>null];$href=$anchor->getAttribute('href');
        if(preg_match('/[\x00-\x20\x7f]/',$href)||!preg_match('~^https?://~i',$href)||filter_var($href,FILTER_VALIDATE_URL)===false)$href=null;
        return['text'=>$anchor->textContent,'href'=>$href];
    }
}
