"""Real local HTTP + actual PHP controller/HMVC/SQLite; outer framework and provider are explicit probes.
The full CRM negative HTTP regression is asaas_checkout_legacy_scope_http.cjs.
"""
from pathlib import Path
import json,os,socket,subprocess,time,urllib.request,urllib.error
repo=Path(__file__).resolve().parents[1]
with socket.socket() as listener:listener.bind(('127.0.0.1',0));port=listener.getsockname()[1]
env=dict(os.environ,ASAAS_CHECKOUT_CONTROLLER_HTTP_FIXTURE='1');php=env.get('CRM_TEST_PHP_BINARY','php')
server=subprocess.Popen([php,'-S',f'127.0.0.1:{port}','-t',str(repo),str(repo/'tests/asaas_checkout_legacy_scope.php')],cwd=repo,env=env,stdout=subprocess.DEVNULL,stderr=subprocess.PIPE)
class NoRedirect(urllib.request.HTTPRedirectHandler):
 def redirect_request(self,*args,**kwargs):return None
opener=urllib.request.build_opener(NoRedirect());checks=0
try:
 for _ in range(100):
  if server.poll() is not None:raise RuntimeError('isolated controller HTTP fixture failed to start')
  try:
   with socket.create_connection(('127.0.0.1',port),timeout=.1):break
  except OSError:time.sleep(.02)
 else:raise RuntimeError('isolated controller HTTP fixture startup timeout')
 for action in ['index','boleto','cartao','qrcode']:
  for hash_ in ['company_a_hash','company_b_hash','invalid_zero_owner','unknown_hash']:
   try:response=opener.open(f'http://127.0.0.1:{port}/asaas/checkout/{action}/{hash_}',timeout=3)
   except urllib.error.HTTPError as error:response=error
   data=json.load(response)
   assert response.status==404 and data['status']==404,(action,hash_,data)
   assert data['invoice_id'] is None and data['effects']==[] and data['gateway_loads']==[] and data['gateway_calls']==0 and data['hydrated_ids']==[] and data['financial_reads']==[],data
   checks+=2
 for action in ['index','boleto','cartao','qrcode']:
  try:response=opener.open(f'http://127.0.0.1:{port}/asaas/checkout/{action}/legacy_hash',timeout=3)
  except urllib.error.HTTPError as error:response=error
  data=json.load(response);assert response.status==(302 if action=='boleto' else 200),data
  assert data['hydrated_ids']==[1] and data['gateway_loads']==['asaas/asaas_gateway'],data
  if action!='boleto':assert data['invoice_id']==1 and any(effect.startswith('view:asaas/') for effect in data['effects']),data
  checks+=2
 print(f'Asaas checkout actual HTTP/controller/HMVC/SQLite: {checks} checks passed; outer/provider probes; no external network.')
finally:
 server.terminate()
 try:server.communicate(timeout=5)
 except subprocess.TimeoutExpired:server.kill();server.communicate()
