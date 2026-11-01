'use strict';
const assert=require('node:assert/strict');
const path=require('node:path');
const {execFileSync}=require('node:child_process');
const implementation=path.resolve(__dirname,'../../application/services/NativeCompanyResourceId.php');
function type(kind){assert(['estimates','proposals'].includes(kind),'explicit sales kind required');return kind==='estimates'?'estimate':'proposal';}
function codec(mode,kind,id){
 const resource=type(kind);assert.match(String(id),/^[1-9]\d*$/);
 const code='require $argv[1]; $type=$argv[3]; if($argv[2]==="encode"){echo NativeCompanyResourceId::encode("company_".$type,$argv[4]);}else{$r=NativeCompanyResourceId::decode($type,$argv[4]);if($r["source_kind"]!=="company_".$type){throw new RuntimeException("Expected owned sales document");}echo $r["source_id"];}';
 return execFileSync('php',['-r',code,'--',implementation,mode,resource,String(id)],{encoding:'utf8',stdio:['ignore','pipe','pipe']}).trim();
}
module.exports={salesSourceId:(kind,id)=>codec('decode',kind,id),salesPublicId:(kind,id)=>codec('encode',kind,id)};
