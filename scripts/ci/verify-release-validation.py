#!/usr/bin/env python3
"""Publication waits for real validation; exact-tree PR evidence avoids duplicate suites."""
import importlib.util,pathlib,os,json,time,datetime,urllib.request
spec=importlib.util.spec_from_file_location('gate',pathlib.Path(__file__).with_name('validation-gate.py'));gate=importlib.util.module_from_spec(spec);spec.loader.exec_module(gate)
REQUIRED=['.github/workflows/'+p+'.yml' for p in ['native-company-experience','issuer-foundation','banco-brasil','deployment-integrity']]
MISSING_RUN_TIMEOUT=5*60
VALIDATION_TIMEOUT=90*60
POLL_SECONDS=20
def outcome(runs):
    current=[gate.latest(runs,p) for p in REQUIRED]
    if any(r and r.get('status')=='completed' and r.get('conclusion')!='success' for r in current):return 'failure'
    if all(r and r.get('status')=='completed' and r.get('conclusion')=='success' for r in current):return 'success'
    return 'pending'
def wait_for_validation(get,sha,*,clock=time.monotonic,sleep=time.sleep,report=None):
    """Bound missing-run discovery separately from real queued/running validation."""
    if report is None:report=lambda message:print(message,flush=True)
    started=clock();last_report=None;last_report_at=started
    while True:
        runs=get('/actions/runs?event=push&head_sha='+sha+'&per_page=100')['workflow_runs']
        current={p:gate.latest(runs,p) for p in REQUIRED}
        missing=[p for p,r in current.items() if r is None]
        details='\n'.join(p+': '+('missing (no push run on this SHA)' if r is None else
            str(r.get('status'))+'/'+str(r.get('conclusion') or 'pending')+' '+r.get('html_url','')) for p,r in current.items())
        elapsed=clock()-started
        if details!=last_report or clock()-last_report_at>=60:
            report('Release validation SHA '+sha+'; elapsed '+str(int(elapsed))+'s:\n'+details)
            last_report=details;last_report_at=clock()
        state=outcome(runs)
        if state=='success':
            report('All required validations passed on the publication SHA.');return 0
        if state=='failure':
            raise SystemExit('Publication blocked: required validation did not succeed.\n'+details)
        if missing and elapsed>=MISSING_RUN_TIMEOUT:
            raise SystemExit('Publication blocked: required workflows were not triggered within five minutes: '+', '.join(missing)+'. Check branch/path filters; absence is not approval.')
        if elapsed>=VALIDATION_TIMEOUT:
            raise SystemExit('Publication blocked: required validation evidence is incomplete.\n'+details)
        sleep(POLL_SECONDS)
def main():
    repo=os.environ['GITHUB_REPOSITORY'];sha=os.environ['GITHUB_SHA'];base='https://api.github.com/repos/'+repo
    def get(route):
        req=urllib.request.Request(base+route,headers={'Authorization':'Bearer '+os.environ['GH_TOKEN'],'Accept':'application/vnd.github+json','X-GitHub-Api-Version':'2022-11-28'})
        with urllib.request.urlopen(req,timeout=25)as response:return json.load(response)
    try:
        tree=get('/git/commits/'+sha)['tree']['sha']
        for candidate in get('/commits/'+sha+'/pulls?per_page=100'):
            pr=get('/pulls/'+str(candidate['number']))
            if not pr.get('merged') or pr.get('head',{}).get('repo',{}).get('full_name')!=repo:continue
            head=pr['head']['sha'];head_tree=get('/git/commits/'+head)['tree']['sha'];runs=get('/actions/runs?event=pull_request&head_sha='+head+'&per_page=100')['workflow_runs']
            if all(gate.proof_allowed(pr,gate.latest(runs,p),repo,os.environ['GITHUB_REF_NAME'],sha,tree,head_tree,datetime.datetime.now(datetime.timezone.utc))for p in REQUIRED):
                print('Publication validated by identical merged tree and successful PR workflows:',flush=True)
                for p in REQUIRED:print(gate.latest(runs,p)['html_url'],flush=True)
                return 0
    except Exception as error:print('PR evidence unavailable; await current commit validation:',type(error).__name__,flush=True)
    return wait_for_validation(get,sha)
if __name__=='__main__':raise SystemExit(main())
