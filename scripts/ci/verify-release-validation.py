#!/usr/bin/env python3
"""Publication waits for real validation; exact-tree PR evidence avoids duplicate suites."""
import importlib.util,pathlib,os,json,time,datetime,urllib.request
spec=importlib.util.spec_from_file_location('gate',pathlib.Path(__file__).with_name('validation-gate.py'));gate=importlib.util.module_from_spec(spec);spec.loader.exec_module(gate)
REQUIRED=['.github/workflows/'+p+'.yml' for p in ['native-company-experience','issuer-foundation','banco-brasil','deployment-integrity']]
def outcome(runs):
    current=[gate.latest(runs,p) for p in REQUIRED]
    if any(r and r.get('conclusion') in {'failure','cancelled','timed_out','action_required','startup_failure'} for r in current):return 'failure'
    if all(r and r.get('conclusion')=='success' for r in current):return 'success'
    return 'pending'
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
                print('Publication validated by identical merged tree and successful PR workflows:')
                for p in REQUIRED:print(gate.latest(runs,p)['html_url'])
                return 0
    except Exception as error:print('PR evidence unavailable; await current commit validation:',type(error).__name__)
    started=time.monotonic()
    while time.monotonic()-started<90*60:
        runs=get('/actions/runs?event=push&head_sha='+sha+'&per_page=100')['workflow_runs'];state=outcome(runs)
        if state=='success':print('All required validations passed on the publication SHA.');return 0
        if state=='failure':raise SystemExit('Publication blocked: a required validation failed or was cancelled. Fix and validate the commit first.')
        time.sleep(20)
    raise SystemExit('Publication blocked: required validation evidence is incomplete.')
if __name__=='__main__':raise SystemExit(main())
