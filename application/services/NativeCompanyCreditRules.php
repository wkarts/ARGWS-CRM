<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyInvoiceRules.php';

/** Original credit-note rules; values are bound to a company-owned projection. */
final class NativeCompanyCreditRules
{
    public const TYPES = ['number'=>'NumberRule','reference_no'=>'TextRule','date'=>'DateRule','total'=>'NumberRule','remaining_amount'=>'NumberRule','status'=>'MultiSelectRule','year'=>'MultiSelectRule'];

    public static function catalogue(array $definitions): array
    {
        $out=[];
        foreach ($definitions as $definition) {
            $slug=$definition['slug']??null;
            if (($definition['fieldto']??null)!=='credit_note'||!is_string($slug)||!preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,190}$/D',$slug)||isset(self::TYPES[$slug])||isset($out[$slug])) throw new DomainException('native_credit_filter_invalid');
            if (isset(NativeCompanyInvoiceCustomRules::TYPES[$definition['type']??''])) $out[$slug]=$definition;
        }
        return $out;
    }

    public static function normalizeBuilder(array $builder, string $format='Y-m-d|%Y-%m-%d', array $definitions=[]): array
    {
        if (count($builder)!==2||array_diff(array_keys($builder),['match_type','rules'])||!in_array($builder['match_type']??null,['and','or'],true)||!is_array($builder['rules']??null)||!array_is_list($builder['rules'])||count($builder['rules'])>100) throw new DomainException('native_credit_filter_invalid');
        $custom=self::catalogue($definitions); $rules=[];
        foreach ($builder['rules'] as $rule) {
            if (!is_array($rule)||!is_string($rule['id']??null)) throw new DomainException('native_credit_filter_invalid');
            $id=$rule['id'];
            if (isset($custom[$id])) { $rules[]=NativeCompanyInvoiceCustomRules::normalize($rule,$custom[$id],$format); continue; }
            if (!isset(self::TYPES[$id])||($rule['type']??null)!==self::TYPES[$id]) throw new DomainException('native_credit_filter_invalid');
            if ($id==='reference_no') { $rules[]=NativeCompanyInvoiceCustomRules::normalize($rule,['slug'=>$id,'type'=>'input'],$format); continue; }
            $base=$rule; $base['id']=$id==='remaining_amount'?'total':$id;
            $normalized=NativeCompanyInvoiceRules::normalizeBuilder(['match_type'=>'and','rules'=>[$base]],$format)['rules'][0];
            $normalized['id']=$id;
            if ($id==='status'&&array_diff($normalized['value'],['1','2','3'])) throw new DomainException('native_credit_filter_invalid');
            $rules[]=$normalized;
        }
        return ['match_type'=>$builder['match_type'],'rules'=>$rules];
    }

    public static function compile(array $builder, array $expressions, string $today, string $format='Y-m-d|%Y-%m-%d', array $definitions=[]): array
    {
        $builder=self::normalizeBuilder($builder,$format,$definitions); $parts=[]; $params=[];
        foreach ($builder['rules'] as $rule) {
            $projection=$expressions[$rule['id']]??null;
            if (!$projection) throw new DomainException('native_credit_filter_invalid');
            if (in_array($rule['id'],['status','year'],true)) {
                $parts[]=$projection['sql'].($rule['operator']==='in'?' IN (':' NOT IN (').implode(',',array_fill(0,count($rule['value']),'?')).')';
                $params=array_merge($params,$projection['params'],$rule['value']);
            } else {
                $compiled=NativeCompanyInvoiceCustomRules::compile($rule,$projection,$today);
                $parts[]=$compiled['sql']; $params=array_merge($params,$compiled['params']);
            }
        }
        return ['sql'=>$parts?'('.implode(' '.strtoupper($builder['match_type']).' ',array_map(static fn($part)=>'('.$part.')',$parts)).')':'','params'=>$params,'builder'=>$builder];
    }
}
