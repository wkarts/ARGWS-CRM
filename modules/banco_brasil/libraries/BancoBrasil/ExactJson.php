<?php

declare(strict_types=1);
namespace BancoBrasil;
/** JSON grammar is checked natively; numeric values retain their original decimal lexemes. Duplicate keys fail. */
final class ExactJson
{
    private int $offset=0;
    private function __construct(private string $json){}
    public static function decode(string $json):array
    {
        if(strlen($json)>1024*1024)throw new \DomainException('bb_api_response_invalid');json_decode($json,true,64,JSON_THROW_ON_ERROR);
        $p=new self($json);$value=$p->value(0);$p->space();if($p->offset!==strlen($json)||!is_array($value))throw new \DomainException('bb_api_response_invalid');return $value;
    }
    private function space():void{while(isset($this->json[$this->offset])&&str_contains(" \t\r\n",$this->json[$this->offset]))$this->offset++;}
    private function string():string
    {
        $start=$this->offset++;
        while(isset($this->json[$this->offset])){if($this->json[$this->offset]==='\\'){$this->offset+=2;continue;}if($this->json[$this->offset++]==='"')return json_decode(substr($this->json,$start,$this->offset-$start),true,64,JSON_THROW_ON_ERROR);}
        throw new \DomainException('bb_api_response_invalid');
    }
    private function value(int $depth)
    {
        if($depth>64)throw new \DomainException('bb_api_response_invalid');$this->space();$c=$this->json[$this->offset]??'';
        if($c==='"')return $this->string();
        if($c==='{'||$c==='['){
            $object=$c==='{';$end=$object?'}':']';$this->offset++;$this->space();$items=[];
            if(($this->json[$this->offset]??null)===$end){$this->offset++;return $items;}
            while(true){
                $this->space();if($object){if(($this->json[$this->offset]??'')!=='"')throw new \DomainException('bb_api_response_invalid');$key=$this->string();if(array_key_exists($key,$items))throw new \DomainException('bb_api_duplicate_key');$this->space();if(($this->json[$this->offset++]??null)!==':')throw new \DomainException('bb_api_response_invalid');$items[$key]=$this->value($depth+1);}else{$items[]=$this->value($depth+1);}
                $this->space();$next=$this->json[$this->offset++]??null;if($next===$end)return $items;if($next!==',')throw new \DomainException('bb_api_response_invalid');
            }
        }
        foreach(['true'=>true,'false'=>false,'null'=>null] as $literal=>$value)if(substr($this->json,$this->offset,strlen($literal))===$literal){$this->offset+=strlen($literal);return $value;}
        if(preg_match('/\G-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?(?:[eE][+-]?[0-9]+)?/A',$this->json,$match,0,$this->offset)){$this->offset+=strlen($match[0]);return $match[0];}
        throw new \DomainException('bb_api_response_invalid');
    }
}
