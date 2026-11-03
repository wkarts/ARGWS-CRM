<?php
/** Static inventory. Never includes, evaluates or executes repository source. */
declare(strict_types=1);
function decodePhpLiteral(string $literal): string
{
    $quote=$literal[0]; $s=substr($literal,1,-1); $out=''; $length=strlen($s);
    $double=['n'=>"\n",'r'=>"\r",'t'=>"\t",'v'=>"\v",'e'=>"\x1b",'f'=>"\f",'\\'=>'\\','"'=>'"','$'=>'$'];
    for($i=0;$i<$length;$i++) {
        if($s[$i]!=="\\" || $i+1===$length) {$out.=$s[$i];continue;}
        $next=$s[$i+1];
        if($quote==="'") {
            if($next==="'" || $next==='\\') {$out.=$next;$i++;} else $out.='\\';
        } elseif(isset($double[$next])) {$out.=$double[$next];$i++;}
        elseif($next>='0' && $next<='7') {
            $digits=$next;$i++;
            while(strlen($digits)<3 && $i+1<$length && $s[$i+1]>='0' && $s[$i+1]<='7')$digits.=$s[++$i];
            $out.=chr(octdec($digits));
        } elseif($next==='x' && $i+2<$length && ctype_xdigit($s[$i+2])) {
            $i+=2;$digits=$s[$i];if($i+1<$length && ctype_xdigit($s[$i+1]))$digits.=$s[++$i];$out.=chr(hexdec($digits));
        } else $out.='\\';
    }
    return $out;
}
function staticExpression(array $tokens,array $known): array
{
    $values=[];
    foreach($tokens as $token) {
        if($token['kind']==='T_CONSTANT_ENCAPSED_STRING')$values[]=decodePhpLiteral($token['text']);
        elseif($token['kind']==='T_LNUMBER')$values[]=(int)$token['text'];
        elseif($token['kind']==='T_VARIABLE' && array_key_exists($token['text'],$known))$values[]=$known[$token['text']];
        elseif($token['text']!=='.')return ['resolved'=>false,'value'=>null];
    }
    if(count($values)===1)return ['resolved'=>true,'value'=>$values[0]];
    if($values && count(array_filter($values,'is_string'))===count($values))return ['resolved'=>true,'value'=>implode('',$values)];
    return ['resolved'=>false,'value'=>null];
}
function literalSqlRows(string $sql,string $table): array
{
    $pattern='/INSERT\s+INTO\s+`?'.preg_quote($table,'/').'`?\s*\(([^)]+)\)\s*VALUES\s*/i';
    preg_match_all($pattern,$sql,$matches,PREG_SET_ORDER|PREG_OFFSET_CAPTURE);$rows=[];$len=strlen($sql);
    foreach($matches as $match) {
        $columns=array_map(static fn($v)=>trim(trim($v),'`'),explode(',',$match[1][0]));
        $i=$match[0][1]+strlen($match[0][0]);
        while($i<$len && $sql[$i]==='(') {
            $start=$i++;$values=[];
            while(true) {
                while($i<$len && ctype_space($sql[$i]))$i++;
                if($i>=$len)throw new RuntimeException('Truncated SQL row.');
                if($sql[$i]==="'" || $sql[$i]==='"') {
                    $quote=$sql[$i++];$value='';$closed=false;
                    while($i<$len) {
                        $char=$sql[$i++];
                        if($char==='\\' && $i<$len){$next=$sql[$i++];$value.=['r'=>"\r",'n'=>"\n",'t'=>"\t",'0'=>"\0",'b'=>"\x08",'Z'=>"\x1a"][$next]??$next;}
                        elseif($char===$quote){if($i<$len && $sql[$i]===$quote){$value.=$quote;$i++;}else{$closed=true;break;}}
                        else $value.=$char;
                    }
                    if(!$closed)throw new RuntimeException('Unclosed SQL string.');
                } else {
                    $begin=$i;while($i<$len && $sql[$i]!==',' && $sql[$i]!==')')$i++;$value=trim(substr($sql,$begin,$i-$begin));
                }
                $values[]=$value;while($i<$len && ctype_space($sql[$i]))$i++;
                if($i<$len && $sql[$i]===')'){$i++;break;}
                if($i>=$len || $sql[$i]!==',')throw new RuntimeException('Invalid SQL separator.');$i++;
            }
            if(count($columns)!==count($values))throw new RuntimeException('SQL column mismatch.');
            $rows[]=['row'=>array_combine($columns,$values),'offset'=>$start];
            while($i<$len && ctype_space($sql[$i]))$i++;if($i<$len && $sql[$i]===',')$i++;while($i<$len && ctype_space($sql[$i]))$i++;
        }
    }
    return $rows;
}
function missingPhrases(string $message,array $phrases): array
{
    $missing=[];
    foreach(preg_split('/(<[^>]*>)/u',$message,-1,PREG_SPLIT_DELIM_CAPTURE) as $part) {
        if($part==='' || $part[0]==='<')continue;
        $key=preg_replace('/\{[^{}]+\}/u','%s',$part);$key=preg_replace('/&nbsp;|&#160;/iu',' ',$key);$key=trim(preg_replace('/\s+/u',' ',$key));
        if($key!=='' && preg_match('/\p{L}/u',str_replace('%s','',$key)) && !array_key_exists($key,$phrases))$missing[$key]=true;
    }
    $result=array_keys($missing);sort($result);return $result;
}
function comparison(array $row,array $core,array $modules,array $defaults,array $phrases): array
{
    $slug=$row['slug']??null;$base=$defaults[$slug]??null;
    if(isset($modules[$slug])){$base=[];foreach(['name','subject','message'] as $field)$base[$field]=$modules[$slug]['source_'.$field]??'';}
    $different=[];$fields=[];
    foreach(['name','subject','message'] as $field)if($base!==null && ($row[$field]??null)!==($base[$field]??null))$different[]=$field;
    foreach(['subject','message'] as $field){preg_match_all('/\{[^{}]+\}/u',(string)($row[$field]??''),$matches);$fields[$field]=$matches[0];}
    return ['catalog'=>isset($core[$slug])?'core':(isset($modules[$slug])?'module':null),'different_fields_from_canonical'=>$different,'missing_translation_phrases'=>missingPhrases((string)($row['message']??''),$phrases),'fields'=>$fields];
}
$root=rtrim($argv[1]??dirname(__DIR__),'/');$catalog=$root.'/application/services/email_templates_ptbr';
$readJson=static fn($file)=>json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);
$core=$readJson($catalog.'/templates.json');$modules=$readJson($catalog.'/templates_modules.json');$defaults=[];
foreach($readJson($catalog.'/defaults.json') as $row)$defaults[$row['slug']]=$row;
$phrases=[];foreach(glob($catalog.'/phrases*.json') as $file)$phrases=array_replace($phrases,$readJson($file));
$calls=[];$sqlStrings=[];$files=[];
foreach(['application','modules','install'] as $directory) {
    $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$directory,FilesystemIterator::SKIP_DOTS));
    foreach($iterator as $file) {
        $path=$file->getPathname();$relative=substr($path,strlen($root)+1);
        if($file->isLink() || !$file->isFile() || strtolower($file->getExtension())!=='php' || preg_match('#/(vendor|assets|language)/#',$relative))continue;
        $content=file_get_contents($path);
        if(strpos($content,'create_email_template')!==false || (strpos($content,'emailtemplates')!==false && strpos($relative,'/migrations/')!==false))$files[$relative]=$content;
    }
}
ksort($files);
foreach($files as $relative=>$content) {
    $tokens=[];$line=1;
    foreach(token_get_all($content) as $raw) {
        $text=is_array($raw)?$raw[1]:$raw;$kind=is_array($raw)?token_name($raw[0]):$raw;
        if(!in_array($kind,['T_WHITESPACE','T_COMMENT','T_DOC_COMMENT'],true))$tokens[]=['kind'=>$kind,'text'=>$text,'line'=>$line];$line+=substr_count($text,"\n");
    }
    $known=[];$count=count($tokens);
    foreach($tokens as $i=>$token) {
        if($token['kind']==='T_CONSTANT_ENCAPSED_STRING' && strpos($token['text'],'tblemailtemplates')!==false)$sqlStrings[]=['file'=>$relative,'line'=>$token['line'],'sql'=>decodePhpLiteral($token['text']),'raw_token'=>$token['text']];
        if($token['kind']==='T_VARIABLE' && ($tokens[$i+1]['text']??null)==='=') {
            $end=$i+2;while($end<$count && $tokens[$end]['text']!==';')$end++;
            $value=staticExpression(array_slice($tokens,$i+2,$end-$i-2),$known);
            if($value['resolved'])$known[$token['text']]=$value['value'];else unset($known[$token['text']]);
        }
        if($token['kind']!=='T_STRING' || strtolower($token['text'])!=='create_email_template' || ($tokens[$i-1]['kind']??null)==='T_FUNCTION' || ($tokens[$i+1]['text']??null)!=='(')continue;
        $parts=[];$chunk=[];$depth=0;
        for($j=$i+2;$j<$count;$j++) {
            $next=$tokens[$j];
            if($next['text']===')' && $depth===0){if($chunk)$parts[]=$chunk;break;}
            if($next['text']===',' && $depth===0){$parts[]=$chunk;$chunk=[];continue;}
            if(in_array($next['text'],['(','[','{'],true))$depth++;
            if(in_array($next['text'],[')',']','}'],true))$depth--;$chunk[]=$next;
        }
        $arguments=[];$row=['file'=>$relative,'line'=>$token['line']];$names=['subject','message','type','name','slug','active'];
        foreach($parts as $p=>$part){$value=staticExpression($part,$known);$arguments[]=array_merge(['expression'=>implode('',array_column($part,'text'))],$value);if(isset($names[$p]))$row[$names[$p]]=$value['value'];}
        $row['arguments']=$arguments;$row=array_merge($row,comparison($row,$core,$modules,$defaults,$phrases));$calls[]=$row;
    }
}
$retired=['task-marked-as-finished','task-unmarked-as-finished','task-marked-as-finished-to-contacts'];$historical=[];
foreach($sqlStrings as $source)foreach(literalSqlRows($source['sql'],'tblemailtemplates') as $item) {
    $row=$item['row'];$offset=strpos($source['raw_token'],"'".$row['slug']."'");if($offset===false)throw new RuntimeException('Exact source line unresolved.');
    $row['file']=$source['file'];$row['line']=$source['line']+substr_count(substr($source['raw_token'],0,$offset),"\n");$row['retired_by_migration_197']=in_array($row['slug'],$retired,true);
    $historical[]=array_merge($row,comparison($row,$core,$modules,$defaults,$phrases));
}
$maFile='modules/ma/database/email_template_default.sql';$maSql=file_get_contents($root.'/'.$maFile);$ma=[];
foreach(literalSqlRows($maSql,'tblma_email_templates') as $item){$row=$item['row'];$row['file']=$maFile;$row['line']=substr_count(substr($maSql,0,$item['offset']),"\n")+1;$ma[]=$row;}
$variants=[];$unresolved=0;foreach($calls as $row){$key=$row['slug']??'';$variants[$key][json_encode([$row['name']??null,$row['subject']??null,$row['message']??null])]=true;foreach($row['arguments'] as $argument)if(!$argument['resolved']){$unresolved++;break;}}
$result=['scope'=>'Static source inventory only; no installer, migration, mail sender or production database executed. Repository untouched.','generated_at_utc'=>gmdate('c'),'repository'=>$root,'parser'=>'PHP token_get_all; static literal decoding, preceding literal-variable resolution and SQL literal parser. No eval/include/require of repository source.','counts'=>['core_catalog'=>count($core),'module_catalog'=>count($modules),'create_email_template_calls'=>count($calls),'unique_call_slugs'=>count($variants),'unresolved_calls'=>$unresolved,'unique_call_name_subject_message_variants'=>array_sum(array_map('count',$variants)),'historical_core_sql_rows'=>count($historical),'historical_core_sql_unique_slugs'=>count(array_unique(array_column($historical,'slug'))),'marketing_automation_seed_rows'=>count($ma)],'create_email_template_calls'=>$calls,'historical_core_sql_rows'=>$historical,'marketing_automation_seeds'=>$ma,'retired_core_slugs'=>$retired,'catalog_slug_intersection'=>array_values(array_intersect(array_keys($core),array_keys($modules)))];
echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
