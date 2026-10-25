#!/usr/bin/env python3
"""Explicit fail-fast focused checks; never providers, deployment or automatic retries."""
import argparse,json,os,pathlib,re,subprocess,sys,time
ROOT=pathlib.Path(__file__).resolve().parents[2]
MANIFEST=ROOT/'tests/ci/native-company-focused.json'
def commands(phase):
    manifest=json.loads(MANIFEST.read_text())
    if manifest.get('format')!=1 or phase not in ('sqlite','node','mysql','browser'): raise ValueError('Invalid focused phase')
    result=manifest[phase]
    if not isinstance(result,list) or not result: raise ValueError('Missing focused checks')
    seen=set()
    for command in result:
        if not isinstance(command,list) or len(command) not in (2,3) or command[0] not in ('php','node'): raise ValueError('Invalid command')
        if len(command)==3 and command[:2]!=['node','--test']: raise ValueError('Unsupported arguments')
        path=command[-1]
        if not isinstance(path,str) or not re.fullmatch(r'tests/[A-Za-z0-9_./-]+\.(php|cjs)',path) or '..' in pathlib.PurePosixPath(path).parts: raise ValueError('Invalid test path')
        if not (ROOT/path).is_file() or (ROOT/path).is_symlink() or tuple(command) in seen: raise ValueError('Missing or duplicate check')
        seen.add(tuple(command))
    return result

def main():
    parser=argparse.ArgumentParser();parser.add_argument('phase',choices=['sqlite','node','mysql','browser']);parser.add_argument('--check',action='store_true');args=parser.parse_args()
    selected=commands(args.phase)
    if args.check: print(f'{args.phase}: {len(selected)} explicit checks validated');return 0
    if args.phase=='mysql' and not re.fullmatch(r'mysql:host=mysql;dbname=issuer_native_module_audit;charset=utf8mb4',os.environ.get('ISSUERS_TEST_DSN','')): raise ValueError('Dedicated synthetic MySQL required')
    if args.phase=='sqlite' and os.environ.get('ISSUERS_TEST_DSN'): raise ValueError('SQLite phase must not inherit database credentials')
    out=pathlib.Path(os.environ.get('NATIVE_COMPANY_EVIDENCE','/tmp/native-company-focused'));out.mkdir(parents=True,exist_ok=True)
    records=[]
    for index,command in enumerate(selected):
        execution=['php','-d','display_errors=stderr','-d','log_errors=0',command[-1]] if command[0]=='php' else command
        start=time.monotonic();print(f'START {args.phase} {index+1}/{len(selected)} {command[-1]}',flush=True)
        try:
            result=subprocess.run(execution,cwd=ROOT,stdout=subprocess.PIPE,stderr=subprocess.STDOUT,text=True,timeout=180)
            output=result.stdout;code=result.returncode
        except subprocess.TimeoutExpired as error:
            output=(error.stdout or b'').decode(errors='replace') if isinstance(error.stdout,bytes) else (error.stdout or '');code=124
        diagnostics=bool(re.search(r'(?m)^(?:PHP )?(?:Warning|Deprecated|Notice|Fatal error|Parse error):',output))
        if code==0 and diagnostics: code=1
        (out/f'{args.phase}-{index+1:03d}.log').write_text(output)
        sys.stdout.write(output);records.append({'command':command,'exit':code,'php_diagnostics':diagnostics,'seconds':round(time.monotonic()-start,3)})
        (out/f'{args.phase}.json').write_text(json.dumps({'sha':os.environ.get('GITHUB_SHA'),'phase':args.phase,'records':records,'complete':len(records)==len(selected),'success':len(records)==len(selected) and all(r['exit']==0 for r in records)},indent=2)+'\n')
        print(f'END {args.phase} {index+1}/{len(selected)} exit={code}',flush=True)
        if code: return code
    return 0
if __name__=='__main__':sys.exit(main())
