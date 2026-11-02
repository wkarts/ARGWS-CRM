#!/usr/bin/env python3
"""Validate complete ownership before selecting serial invocations for isolated stacks."""
import argparse,collections,json,pathlib,re,shlex
ROOT=pathlib.Path(__file__).resolve().parents[2]
MANIFEST=ROOT/'tests/ci/standalone-browser-plan.json'
SOURCE=ROOT/'tests/issuers_standalone_http_smoke.sh'
def validate(plan,source):
 actual=[shlex.split(line) for line in re.findall(r'^node (.+)$',source,re.M)]
 rows=plan['invocations'];total=len(rows)
 if plan.get('format')!=1 or [r['argv'] for r in rows]!=actual:raise ValueError('Browser invocation manifest differs from actual shell; update coverage explicitly')
 common=plan['common'];owners=[i for g in plan['groups'].values() for i in g['owned']]
 if len(set(common))!=len(common) or sorted(common+owners)!=list(range(total)):raise ValueError('Every invocation must have exactly one owner or common fixture role')
 for name,g in plan['groups'].items():
  values=g['owned']+g['prerequisites']
  if not g['owned'] or len(set(values))!=len(values) or any(type(i)!=int or i not in range(total) for i in values):raise ValueError('Invalid/duplicate group assignment: '+name)
  if set(values)&set(common):raise ValueError('Common fixtures cannot also be group owned')
 for r in rows:
  if r['database'] not in [None,'argws_legacy_ci','argws_upgrade_ci'] or not all(isinstance(a,str) and a for a in r['argv']):raise ValueError('Invalid invocation identity')
 for owner in plan['shell_owners'].values():
  if owner not in plan['groups']:raise ValueError('Shell block owner missing')
 return plan

def selected(plan,group):
 if group=='all':return set(range(len(plan['invocations'])))
 if group not in plan['groups']:raise ValueError('Unknown browser group: '+group)
 g=plan['groups'][group]
 return set(plan['common']+g['owned']+g['prerequisites'])

def main():
 parser=argparse.ArgumentParser();parser.add_argument('--check',action='store_true');parser.add_argument('--group',default='all');args=parser.parse_args()
 plan=validate(json.loads(MANIFEST.read_text()),SOURCE.read_text());indices=selected(plan,args.group)
 if args.check:print(str(len(plan['invocations']))+' browser invocations; '+str(len(plan['groups']))+' isolated groups; complete ownership verified');return
 for i,row in enumerate(plan['invocations']):print(str(i)+'\t'+str(int(i in indices))+'\t'+(row['database'] or '-')+'\t'+' '.join(row['argv']))
if __name__=='__main__':main()
