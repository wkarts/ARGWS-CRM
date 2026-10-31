#!/usr/bin/env python3
"""Cancel only same-repository PR validation runs, never push/release/deploy runs."""
import json, os, time, urllib.request, urllib.error
ACTIVE={'queued','in_progress','pending','waiting','requested'}
BAD={'failure','timed_out','action_required','startup_failure'}
def same_pr(run, pr, repository):
    return (run.get('event')=='pull_request' and run.get('head_repository',{}).get('full_name')==repository
            and any(p.get('number')==pr['number'] for p in run.get('pull_requests',[])))
def cancellation_plan(runs,pr,repository,own,failed=False):
    return [r['id'] for r in runs if r['id']!=own and same_pr(r,pr,repository)
            and r['status'] in ACTIVE and (r['head_sha']!=pr['head']['sha'] or failed)]
class Api:
    def __init__(self,repo,token): self.base='https://api.github.com/repos/'+repo; self.token=token
    def call(self,path,method='GET'):
        req=urllib.request.Request(self.base+path,method=method,headers={'Authorization':'Bearer '+self.token,'Accept':'application/vnd.github+json','X-GitHub-Api-Version':'2022-11-28'},data=b'' if method=='POST' else None)
        try:
            with urllib.request.urlopen(req,timeout=30) as response:
                data=response.read();return json.loads(data) if data else {}
        except urllib.error.HTTPError as error:
            # A run can finish between list and cancel; no other errors are hidden.
            if method=='POST' and error.code==409:return {}
            raise
    def runs(self,branch,head=None,status=None):
        from urllib.parse import quote
        rows=[]
        for page in range(1,11):
            query='/actions/runs?event=pull_request&branch='+quote(branch,safe='')+'&per_page=100&page='+str(page)
            if head:query+='&head_sha='+head
            if status:query+='&status='+status
            batch=self.call(query)['workflow_runs'];rows+=batch
            if len(batch)<100:break
        return rows

def main():
    repo=os.environ['GITHUB_REPOSITORY'];own=int(os.environ['GITHUB_RUN_ID'])
    event=json.load(open(os.environ['GITHUB_EVENT_PATH']));pr=event['pull_request'];number=pr['number'];expected=pr['head']['sha']
    if pr['head']['repo']['full_name']!=repo:raise SystemExit('Fork PR: no cancellation authority')
    api=Api(repo,os.environ['GH_TOKEN']);started=time.monotonic();canceled=set();stable_since=None;cursor=0;first=True
    while time.monotonic()-started<45*60:
        latest=api.call('/pulls/'+str(number))
        if latest['state']!='open' or latest['head']['sha']!=expected:
            print('PR closed or superseded; stop without touching newer validation.');return 0
        runs=api.runs(pr['head']['ref'],head=expected)
        if first:
            for status in sorted(ACTIVE):runs+=api.runs(pr['head']['ref'],status=status)
            runs=list({r['id']:r for r in runs}.values());first=False
        current=[r for r in runs if r['id']!=own and same_pr(r,pr,repo) and r['head_sha']==expected]
        failures=[r for r in current if r.get('conclusion') in BAD]
        # Detect the first failed job before other jobs in that workflow finish.
        busy=[r for r in current if r['status']=='in_progress']
        # Bound polling to fit the repository token's REST rate budget.
        selected=[busy[(cursor+i)%len(busy)] for i in range(min(4,len(busy)))] if busy else []
        cursor+=len(selected)
        for run in selected:
            if run['status']=='in_progress':
                page=1
                while True:
                    jobs=api.call('/actions/runs/'+str(run['id'])+'/jobs?filter=latest&per_page=100&page='+str(page))['jobs']
                    failures += [j for j in jobs if j.get('conclusion') in BAD]
                    if len(jobs)<100:break
                    page+=1
        for run_id in cancellation_plan(runs,pr,repo,own,bool(failures)):
            if run_id in canceled:continue
            # Recheck the PR head immediately before each mutation.
            if api.call('/pulls/'+str(number))['head']['sha']!=expected:return 0
            api.call('/actions/runs/'+str(run_id)+'/cancel','POST');canceled.add(run_id);print('Cancellation requested:',run_id,flush=True)
        if failures:
            print('First failing validation(s):',json.dumps([{'name':r['name'],'url':r.get('html_url'),'conclusion':r['conclusion']} for r in failures]),flush=True)
            print('Logs remain available. A corrective commit starts a fresh round.');return 1
        if current and all(r['status']=='completed' for r in current):
            if stable_since is None:stable_since=time.monotonic()
            if time.monotonic()-stable_since>=60:
                if any(r.get('conclusion') not in {'success','skipped','neutral'} for r in current):return 1
                print('All observed PR validations completed successfully.');return 0
        else:stable_since=None
        time.sleep(30)
    raise SystemExit('Validation still pending after 45 minutes; no success claimed.')
if __name__=='__main__':raise SystemExit(main())
