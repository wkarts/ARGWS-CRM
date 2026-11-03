#!/usr/bin/env python3
"""Reuse a published tools digest only when every image input is unchanged."""
import hashlib,json,os,pathlib,re
ROOT=pathlib.Path(__file__).resolve().parents[2]
INPUTS=['docker/ci/Dockerfile','docker/ci/package.json','docker/ci/package-lock.json','docker/ci/smoke.sh','docker/ci/smoke.cjs']
def inputs_hash(root=ROOT):
    listing=''.join(hashlib.sha256((root/name).read_bytes()).hexdigest()+'  '+name+'\n' for name in INPUTS)
    return hashlib.sha256(listing.encode()).hexdigest()
def reusable_digest(php,digest_hash,receipts,refresh=False):
    if refresh:return None
    matches=[row for row in receipts if row.get('php')==php and row.get('inputs_sha256')==digest_hash and row.get('platform')=='linux/amd64' and row.get('smoke')=='passed-before-login']
    if len(matches)!=1:return None
    ref=matches[0].get('digest_reference','')
    return ref if re.fullmatch(r'ghcr\.io/wkarts/argws-crm-ci-php@sha256:[a-f0-9]{64}',ref) else None
if __name__=='__main__':
    digest=reusable_digest(os.environ['PHP_MINOR'],inputs_hash(),json.loads((ROOT/'docs/ci/validation-images.json').read_text()),os.environ.get('REFRESH_BASE')=='true')
    with open(os.environ['GITHUB_OUTPUT'],'a') as output:output.write('reuse='+str(bool(digest)).lower()+'\ndigest='+str(digest or '')+'\n')
    print('Reuse verified published inputs; run smoke again.' if digest else 'Image inputs changed or refresh requested; build and run smoke.')
