#!/usr/bin/env python3
"""Validate PDF file MediaBox via independent Poppler, all four native formats in A/B/principal."""
from pathlib import Path
import json,os,re,shutil,subprocess,tempfile
base=Path(__file__).resolve().parents[2]
if not shutil.which('pdfinfo'):raise SystemExit('pdfinfo required for independent real PDF dimensions proof')
with tempfile.TemporaryDirectory(prefix='crm-sales-pdf-',dir='/tmp') as folder:
 env=os.environ.copy();env['CRM_SALES_PDF_FORMAT_EVIDENCE']=folder
 result=subprocess.run([env.get('CRM_TEST_PHP_BINARY','php'),'tests/companies/sales_pdf_preferences.php'],cwd=base,env=env,capture_output=True,text=True)
 if result.returncode:raise SystemExit(result.stdout+result.stderr)
 records=list(Path(folder).glob('*.json'));assert len(records)==24,len(records)
 for record in records:
  expect=json.loads(record.read_text());info=subprocess.check_output(['pdfinfo',str(record.with_suffix('.pdf'))],text=True)
  m=re.search(r'Page size:\s+([\d.]+) x ([\d.]+) pts',info);assert m,(record.name,info)
  assert abs(float(m[1])*25.4/72-expect['width'])<.05 and abs(float(m[2])*25.4/72-expect['height'])<.05,(record.name,info)
 print('24 actual A/B/principal quote/proposal PDF files: all native sizes and orientations independently verified with Poppler.')
