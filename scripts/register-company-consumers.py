#!/usr/bin/env python3
"""Finite, conservative symbol register; lexical candidates are not proof of isolation."""
import argparse, hashlib, importlib.util, json, re
from collections import Counter, defaultdict
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location('boundaries',ROOT/'scripts/inventory-company-boundaries.py')
base=importlib.util.module_from_spec(spec);spec.loader.exec_module(base)
FUNCTION=re.compile(r'(?m)^[ \t]*(?:(?:final|public|private|protected|static|abstract)\s+)*function\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(')
def category(path):
    if path.startswith('modules/'):return 'modules'
    if any(x in path for x in ['Company','Issuer','issuers','settings','Settings']):return 'company_core_and_settings'
    if any(x in path for x in ['Estimates','Proposals','Credit_notes','Contract','estimates','proposals','credit_note','contract']):return 'sales_documents'
    if any(x in path for x in ['authentication','Authentication','Clients','clients/','Contact','contact','Portal','portal','multiple_companies']):return 'customer_portal'
    if any(x in path.lower() for x in ['mail','cron','queue','webhook','callback']):return 'jobs_and_transport'
    if any(x in path.lower() for x in ['report','pdf','download','upload','dashboard','export']):return 'files_and_reports'
    return 'other_core'
def main():
    parser=argparse.ArgumentParser();parser.add_argument('--full-output',type=Path);args=parser.parse_args()
    evidence=json.loads((ROOT/'docs/companies/consumer-evidence.json').read_text())
    entries=[]
    for folder in ['application','modules']:
      for file in sorted((ROOT/folder).rglob('*.php')):
        path=file.relative_to(ROOT).as_posix()
        if any(part in base.DEPENDENCIES for part in file.relative_to(ROOT).parts) or '/language/' in path or '/migrations/' in path:continue
        data=file.read_bytes();source=data.decode('utf-8',errors='replace');functions=list(FUNCTION.finditer(source))
        names=Counter(m.group(1) for m in functions)
        chunks=[('top_level',0,functions[0].start() if functions else len(source))]
        chunks += [(m.group(1),m.start(),functions[i+1].start() if i+1<len(functions) else len(source)) for i,m in enumerate(functions)]
        for symbol,start,end in chunks:
          risk=[key for key,pattern in base.REGEX.items() if key!='routes' and pattern.search(source[start:end])]
          key=path+'::'+symbol
          if names.get(symbol,0)>1:key+='@line'+str(source.count('\n',0,start)+1)
          if not risk and key not in evidence:continue
          proof=evidence.get(key,{})
          if proof and proof.get('source_sha256') != hashlib.sha256(data).hexdigest():proof={}
          entries.append({'consumer':key,'category':category(path),'line':source.count('\n',0,start)+1,'source_sha256':hashlib.sha256(data).hexdigest(),'risk_candidates':risk,'state':'boundary_tested' if proof else 'pending','evidence':proof,'full_operation_ready':False})
    missing=sorted(set(evidence)-{e['consumer'] for e in entries})
    if missing:raise ValueError('Unknown evidence consumers: '+repr(missing))
    totals=defaultdict(Counter)
    for e in entries:totals[e['category']][e['state']]+=1
    result={'format':1,'scope':'Line-anchored named PHP function candidates and top-level blocks in first-party application/modules, excluding translations and migration history. Lexical candidates may include false positives. Anonymous closures belong to their enclosing segment. Not an exhaustive runtime call graph.','meaning':'boundary_tested means only the outcomes in evidence were exercised with synthetic local fixtures. It never means the whole consumer is isolated or secondary-company operation is ready. All full_operation_ready values remain false pending cross-consumer, MySQL, concurrency and HTTP proof.','source_identity':'Exact per-file SHA-256 and source_manifest_sha256 below; no historical commit is asserted for a regenerated working tree.','counts':{k:dict(v) for k,v in sorted(totals.items())},'consumers':entries}
    if args.full_output:args.full_output.write_text(json.dumps(result,ensure_ascii=False,indent=2)+'\n')
    result['source_manifest_sha256']=hashlib.sha256(json.dumps([(e['consumer'],e['source_sha256']) for e in entries],separators=(',',':')).encode()).hexdigest()
    result['total_consumers']=len(entries)
    result['consumers']=[e for e in entries if e['state']=='boundary_tested']
    result['full_register_command']='python3 scripts/register-company-consumers.py --full-output /tmp/company-consumers-full.json'
    target=ROOT/'docs/companies/consumer-register.json';target.write_text(json.dumps(result,ensure_ascii=False,indent=2)+'\n');print(json.dumps(result['counts'],ensure_ascii=False));print('total_consumers',len(entries))
if __name__=='__main__':main()
