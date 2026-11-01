'use strict';
// Static link check: shared native assets must not call functions retained only
// in the legacy page bootstrap. No browser, provider or database is contacted.
const fs=require('node:fs'),path=require('node:path'),acorn=require('acorn'),scope=require('eslint-scope');
function parse(source){return acorn.parse(source,{ecmaVersion:2022,ranges:true,sourceType:'script'});}
function globals(ast){return scope.analyze(ast,{ecmaVersion:2022,sourceType:'script',optimistic:true}).globalScope;}
function audit(sources,legacy,declared=[]){
 const legacyAst=parse(legacy),candidates=new Set([...globals(legacyAst).variables.map(v=>v.name),...declared]);
 const available=new Set(),references=[];
 for(const [file,source] of Object.entries(sources)){
  const g=globals(parse(source));for(const v of g.variables)available.add(v.name);
  for(const r of g.through)if(candidates.has(r.identifier.name))references.push({file,name:r.identifier.name,line:source.slice(0,r.identifier.start).split('\n').length});
 }
 return references.filter(r=>!available.has(r.name));
}
function repository(root){
 const presentation=fs.readFileSync(path.join(root,'application/services/NativeCompanyAdminPresentation.php'),'utf8');
 const paths=[...new Set([...presentation.matchAll(/'(assets\/[^']+\.js)'/g)].map(m=>m[1]))];
 const sources=Object.fromEntries(paths.map(p=>[p,fs.readFileSync(path.join(root,p),'utf8')]));
 const manifest=JSON.parse(fs.readFileSync(path.join(root,'tests/fixtures/native-ui-tools-extraction.json')));
 return audit(sources,fs.readFileSync(path.join(root,'assets/js/main.js'),'utf8'),Object.keys(manifest.functions));
}
module.exports={audit,repository};
if(require.main===module){const errors=repository(path.resolve(__dirname,'../..'));if(errors.length){for(const r of errors)console.error(`${r.file}:${r.line}: native asset references missing shared declaration ${r.name}`);process.exitCode=1;}else console.log('Native JS dependency link check passed; no database or network was used.');}
