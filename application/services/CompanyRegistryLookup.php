<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerPolicy.php';

/** Read-only adapters to the same third-party providers used by masks-cnpj-cep. */
final class CompanyRegistryLookup
{
    public static function request(string $kind, $value): array
    {
        if(!is_string($value)){throw new DomainException('Informe um documento válido.');}
        if($kind==='cnpj'){
            $id=IssuerPolicy::taxId($value);
            if(!preg_match('/^[0-9]{14}$/D',$id)){throw new DomainException('A consulta aceita CNPJ numérico. CPF e CNPJ alfanumérico podem ser cadastrados manualmente.');}
            return ['url'=>'https://www.receitaws.com.br/v1/cnpj/'.$id,'id'=>$id];
        }
        if($kind==='cep' && preg_match('/^[0-9]{5}-?[0-9]{3}$/D',trim($value))){
            $id=str_replace('-','',trim($value));return ['url'=>'https://viacep.com.br/ws/'.$id.'/json/','id'=>$id];
        }
        throw new DomainException('Informe um CEP com 8 dígitos.');
    }
    public static function lookup(string $kind,$value,?callable $transport=null): array
    {
        $request=self::request($kind,$value);
        $body=$transport?$transport($request['url']):self::fetch($request['url']);
        if(!is_string($body)||strlen($body)>131072){throw new RuntimeException('lookup_unavailable');}
        $data=json_decode($body,true);
        if(!is_array($data)||($data['status']??'')==='ERROR'||!empty($data['erro'])){throw new RuntimeException('lookup_unavailable');}
        if($kind==='cnpj'){
            if(IssuerPolicy::taxId($data['cnpj']??'')!==$request['id']){throw new RuntimeException('lookup_mismatch');}
            $map=['nome'=>'legal_name','fantasia'=>'company_trade_name','logradouro'=>'invoice_company_address','numero'=>'company_address_number','complemento'=>'company_address_complement','bairro'=>'company_address_district','municipio'=>'invoice_company_city','uf'=>'company_state','cep'=>'invoice_company_postal_code','telefone'=>'invoice_company_phonenumber'];
        }else{
            if(preg_replace('/\D/','',(string)($data['cep']??''))!==$request['id']){throw new RuntimeException('lookup_mismatch');}
            $map=['logradouro'=>'invoice_company_address','complemento'=>'company_address_complement','bairro'=>'company_address_district','localidade'=>'invoice_company_city','uf'=>'company_state','cep'=>'invoice_company_postal_code'];
        }
        $fields=[];
        foreach($map as $source=>$target){$v=$data[$source]??null;if(is_string($v)&&trim($v)!==''&&strlen($v)<=($target==='legal_name'?191:2000)&&!preg_match('/[\x00-\x1f]/',$v)){$fields[$target]=trim($v);}}
        $fields['invoice_company_country_code']='BR';
        return ['fields'=>$fields,'provider'=>$kind==='cnpj'?'ReceitaWS':'ViaCEP'];
    }
    private static function fetch(string $url): string
    {
        if(!function_exists('curl_init')){throw new RuntimeException('lookup_unavailable');}
        $body='';$curl=curl_init($url);
        curl_setopt_array($curl,[CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>8,
            CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,
            CURLOPT_HTTPHEADER=>['Accept: application/json'],CURLOPT_USERAGENT=>'ARGWS-CRM company lookup',
            CURLOPT_WRITEFUNCTION=>static function($handle,$chunk)use(&$body){if(strlen($body)+strlen($chunk)>131072){return 0;}$body.=$chunk;return strlen($chunk);}]);
        $ok=curl_exec($curl);$status=curl_getinfo($curl,CURLINFO_HTTP_CODE);curl_close($curl);
        if($ok===false||$status!==200){throw new RuntimeException('lookup_unavailable');}return $body;
    }
}
