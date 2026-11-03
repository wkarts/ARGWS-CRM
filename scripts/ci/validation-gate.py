#!/usr/bin/env python3
"""Read-only CI gate: reuse exact-tree PR proof or await one shared fast preflight."""
import json,os,sys,time,datetime,urllib.request,urllib.parse
ACTIVE={'queued','in_progress','pending','waiting','requested'}
def latest(runs,path):
    matching=[r for r in runs if r.get('path','').split('@')[0]==path]
    return max(matching,key=lambda r:r['id'],default=None)
def proof_allowed(pr,run,repo,branch,sha,tree,head_tree,now):
    if not(pr.get('merged') and pr.get('merge_commit_sha')==sha and pr.get('base',{}).get('ref')==branch and pr.get('head',{}).get('repo',{}).get('full_name')==repo and tree==head_tree):return False
    if not run or run.get('event')!='pull_request' or run.get('conclusion')!='success' or run.get('head_sha')!=pr['head']['sha'] or run.get('head_repository',{}).get('full_name')!=repo:return False
    when=datetime.datetime.fromisoformat(run['updated_at'].replace('Z','+00:00'))
    return datetime.timedelta(0)<=now-when<=datetime.timedelta(hours=24)
def preflight_state(jobs):
    required={'node-contracts','Native browser preflight (8.1)','Native browser preflight (8.5)'}
    selected={j['name']:j for j in jobs if j.get('name') in required}
    if any(j.get('status')=='completed' and j.get('conclusion')!='success' for j in selected.values()):return 'failure'
    if set(selected)==required and all(j.get('status')=='completed' and j.get('conclusion')=='success' for j in selected.values()):return 'success'
    return 'pending'
def main():
    repo=os.environ['GITHUB_REPOSITORY'];event=json.load(open(os.environ['GITHUB_EVENT_PATH']));kind=os.environ['GITHUB_EVENT_NAME'];sha=event.get('pull_request',{}).get('head',{}).get('sha',os.environ['GITHUB_SHA']);path=os.environ['VALIDATION_WORKFLOW_PATH'];base='https://api.github.com/repos/'+repo
    def get(route):
        req=urllib.request.Request(base+route,headers={'Authorization':'Bearer '+os.environ['GH_TOKEN'],'Accept':'application/vnd.github+json','X-GitHub-Api-Version':'2022-11-28'})
        with urllib.request.urlopen(req,timeout=25) as response:return json.load(response)
    def output(run,reason):
        with open(os.environ['GITHUB_OUTPUT'],'a')as f:f.write('run='+str(run).lower()+'\n')
        print(reason,flush=True)
        with open(os.environ['GITHUB_STEP_SUMMARY'],'a')as f:f.write(reason+'\n')
    if kind=='push':
        # Missing/ambiguous evidence always runs checks; never fabricates success.
        try:
            tree=get('/git/commits/'+sha)['tree']['sha']
            for candidate in get('/commits/'+sha+'/pulls?per_page=100'):
                pr=get('/pulls/'+str(candidate['number']))
                if not pr.get('merged') or pr.get('head',{}).get('repo',{}).get('full_name')!=repo:continue
                head=pr['head']['sha'];head_tree=get('/git/commits/'+head)['tree']['sha']
                runs=get('/actions/runs?event=pull_request&head_sha='+head+'&per_page=100')['workflow_runs'];run=latest(runs,path)
                if proof_allowed(pr,run,repo,os.environ['GITHUB_REF_NAME'],sha,tree,head_tree,datetime.datetime.now(datetime.timezone.utc)):
                    output(False,'Reused successful exact-tree validation from '+run['html_url']+' for merged PR #'+str(pr['number'])+'. No tests silently discarded.');return
        except Exception as e:print('Proof unavailable; execute full validation:',type(e).__name__)
    if path=='.github/workflows/native-company-experience.yml' or kind not in {'pull_request','push'}:
        output(True,'Execute full validation for this SHA.');return
    started=time.monotonic()
    while time.monotonic()-started<15*60:
        runs=get('/actions/runs?head_sha='+sha+'&per_page=100')['workflow_runs'];run=latest(runs,'.github/workflows/native-company-experience.yml')
        if run:
            jobs=get('/actions/runs/'+str(run['id'])+'/jobs?filter=latest&per_page=100')['jobs']
            state=preflight_state(jobs)
            if state=='failure':raise SystemExit('Shared Node/browser preflight failed: '+run.get('html_url',''))
            if state=='success':
                output(True,'Shared Node and both real browser preflights passed: '+run['html_url']);return
            if run['status']=='completed' and run['conclusion'] not in {'success'}:raise SystemExit('Preflight workflow did not succeed.')
        time.sleep(10)
    raise SystemExit('Fast preflight unavailable after fifteen minutes; heavy suite not started.')
if __name__=='__main__':main()
