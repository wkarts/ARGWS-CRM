<?php
/** Source-only census: no application bootstrap, models, credentials or database. */
$root=dirname(__DIR__);$rows=[];
$owners=['Clients'=>'customers','Dashboard'=>'work','Projects'=>'work','Tasks'=>'work','Tickets'=>'work','Invoices'=>'invoices','Todo'=>'auxiliary','Announcements'=>'auxiliary','Taxes'=>'auxiliary','Invoice_items'=>'auxiliary','Misc'=>'auxiliary/presentation/central','Staff'=>'work/central'];
$files=glob($root.'/application/controllers/admin/*.php');sort($files);
foreach($files as$file){$tokens=token_get_all(file_get_contents($file));$class=null;
 foreach($tokens as$i=>$token){if(!is_array($token)||$token[0]!==T_CLASS)continue;$j=$i+1;while(isset($tokens[$j])&&is_array($tokens[$j])&&in_array($tokens[$j][0],[T_WHITESPACE,T_COMMENT,T_DOC_COMMENT],true))$j++;if(isset($tokens[$j])&&is_array($tokens[$j])&&$tokens[$j][0]===T_STRING){$class=$tokens[$j][1];break;}}
 // Explicit inert tombstones retained by a workspace synchronizer are not
 // controllers. A class declaration is required before a route enters census.
 if($class===null)continue;$parallel=str_starts_with($class,'Company_')&&$class!=='Company_selection';$methods=[];
 foreach($tokens as$i=>$token){if(!is_array($token)||$token[0]!==T_FUNCTION)continue;$j=$i+1;while(isset($tokens[$j])&&((is_array($tokens[$j])&&in_array($tokens[$j][0],[T_WHITESPACE,T_COMMENT,T_DOC_COMMENT],true))||$tokens[$j]==='&'))$j++;if(!isset($tokens[$j])||!is_array($tokens[$j])||$tokens[$j][0]!==T_STRING)continue;
 $visibility='public';for($k=$i-1;$k>=0;$k--){$previous=$tokens[$k];if(!is_array($previous)&&in_array($previous,[';','{','}'],true))break;if(is_array($previous)&&in_array($previous[0],[T_PRIVATE,T_PROTECTED,T_PUBLIC],true)){$visibility=strtolower($previous[1]);break;}}
 if($visibility!=='public'||str_starts_with($tokens[$j][1],'__'))continue;
 $methods[]=['method'=>$tokens[$j][1],'line'=>$token[2],'classification'=>$parallel?'parallel_to_remove':'review_required'];
 }
 $rows[]=['path'=>substr($file,strlen($root)+1),'controller'=>$class,'owner'=>$owners[$class]??'central','parallel'=>$parallel,'methods'=>$methods];
}
echo json_encode(['format'=>1,'scope'=>'Native admin source census, not proof of admission, completeness, or runtime coverage','controllers'=>$rows],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;
