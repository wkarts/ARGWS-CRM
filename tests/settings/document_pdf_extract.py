#!/usr/bin/env python3
"""Independent Poppler dimensions for own invoice/credit/contract PDFs, including after changing settings."""
from pathlib import Path
import json,os,re,shutil,subprocess,tempfile
base=Path(__file__).resolve().parents[2]
if not shutil.which('pdfinfo'):raise SystemExit('pdfinfo required for independent PDF dimensions')
with tempfile.TemporaryDirectory(prefix='crm-document-pdf-',dir='/tmp') as folder:
 env=os.environ.copy();env['CRM_DOCUMENT_PDF_EVIDENCE']=folder
 for test in ['tests/companies/invoice_credit_pdf_preferences.php','tests/companies/contracts_pdf_preferences.php']:
  p=subprocess.run([env.get('CRM_TEST_PHP_BINARY','php'),test],cwd=base,env=env,capture_output=True,text=True)
  if p.returncode:raise SystemExit(p.stdout+p.stderr)
 records=list(Path(folder).glob('*.json'));assert len(records)==72,len(records)
 for record in records:
  expect=json.loads(record.read_text());info=subprocess.check_output(['pdfinfo',str(record.with_suffix('.pdf'))],text=True);m=re.search(r'Page size:\s+([\d.]+) x ([\d.]+) pts',info);assert m,(record.name,info)
  assert abs(float(m[1])*25.4/72-expect['width'])<.05 and abs(float(m[2])*25.4/72-expect['height'])<.05,(record.name,info)
 print('72 real A/B/principal invoice/credit/contract PDF files: four native formats and unchanged dimensions after settings edits verified by Poppler.')
