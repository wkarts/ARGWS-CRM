#!/usr/bin/env python3
"""Extract actual native TCPDF text from the synthetic integration test, never a DOM/PDF mock."""
from pathlib import Path
import json, os, shutil, subprocess, tempfile
base=Path(__file__).resolve().parents[2]
extractor=shutil.which('pdftotext')
if not extractor:
    raise SystemExit('pdftotext is required for this explicit PDF extraction proof')
with tempfile.TemporaryDirectory(prefix='crm-sales-preferences-',dir='/tmp') as folder:
    env=os.environ.copy();env['CRM_SALES_PREFERENCES_PDF_EVIDENCE']=folder
    result=subprocess.run([env.get('CRM_TEST_PHP_BINARY','php'),'tests/companies/sales_preferences.php'],cwd=base,env=env,capture_output=True,text=True)
    if result.returncode:raise SystemExit(result.stdout+result.stderr)
    records=list(Path(folder).glob('*.json'));assert len(records)==4,'A/B quote and proposal PDFs must all be produced'
    for record in records:
        expected=json.loads(record.read_text());pdf=record.with_suffix('.pdf');text=subprocess.check_output([extractor,'-layout',str(pdf),'-'],text=True)
        assert expected['number'] in text,(record.name,'own prefix absent from actual PDF')
        assert expected['terms'] in text,(record.name,'own terms absent from actual PDF')
        assert expected['forbidden_terms'] not in text,(record.name,'invoice conditions replaced sales conditions')
    print('Sales preferences PDF: four actual A/B quote/proposal PDFs extracted; own numbers/terms and invoice-term separation passed. Synthetic PDO; no external transport.')
