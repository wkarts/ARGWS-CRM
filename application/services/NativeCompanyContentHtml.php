<?php
declare(strict_types=1);
/** Same bundled purifier, without tenant-unsafe hooks or ambient option hydration. */
final class NativeCompanyContentHtml
{
    public static function purify(string $html):string
    {
        if(strlen($html)>65535||preg_match('//u',$html)!==1||str_contains($html,"\0"))throw new DomainException('issuer_content_invalid');
        if(!class_exists('HTMLPurifier'))require_once __DIR__.'/../vendor/ezyang/htmlpurifier/library/HTMLPurifier.auto.php';
        $c=HTMLPurifier_Config::createDefault();$c->set('Core.Encoding','UTF-8');$c->set('Cache.DefinitionImpl',null);
        $c->set('HTML.Allowed','p[style],div[style],span[style],h1,h2,h3,h4,h5,h6,strong,b,em,i,u,s,sub,sup,br,hr,ul,ol,li,blockquote,pre,code,a[href|title|target],img[src|alt|width|height],table[style|cellpadding|cellspacing|border],thead,tbody,tr,th[colspan|rowspan],td[colspan|rowspan]');
        $c->set('Attr.AllowedFrameTargets',['_blank']);$c->set('URI.AllowedSchemes',['https'=>true,'mailto'=>true]);
        $c->set('CSS.AllowedProperties',['color','background-color','font-family','font-size','font-weight','font-style','text-align','text-decoration','padding','margin','border','border-collapse','width']);
        $clean=(new HTMLPurifier($c))->purify($html);
        if(strlen($clean)>65535)throw new DomainException('issuer_content_invalid');return $clean;
    }
}
