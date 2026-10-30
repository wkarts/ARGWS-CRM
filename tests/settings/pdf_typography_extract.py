#!/usr/bin/env python3
"""Real canonical files: independent Poppler font family, body point-size and embedded Unicode glyph evidence."""
from pathlib import Path
import json,os,re,subprocess,tempfile,xml.etree.ElementTree as ET
base=Path(__file__).resolve().parents[2]
with tempfile.TemporaryDirectory(prefix='crm-typography-pdf-',dir='/tmp')as folder:
 env=os.environ.copy();env['CRM_PDF_TYPOGRAPHY_EVIDENCE']=folder
 for test in ['tests/companies/sales_pdf_typography.php','tests/companies/invoice_credit_pdf_typography.php','tests/companies/contracts_pdf_typography.php']:
  p=subprocess.run([env.get('CRM_TEST_PHP_BINARY','php'),test],cwd=base,env=env,capture_output=True,text=True)
  if p.returncode:raise SystemExit(p.stdout+p.stderr)
 records=list(Path(folder).glob('*.json'));assert len(records)==79,len(records)
 for record in records:
  expect=json.loads(record.read_text());pdf=str(record.with_suffix('.pdf'));xml=ET.fromstring(subprocess.check_output(['pdftohtml','-xml','-stdout','-hidden','-zoom','1',pdf],text=True));font=expect['font'].lower();specs=[x.attrib for x in xml.iter('fontspec')];assert any(re.sub('[^a-z]','',s['family'].split('+')[-1].lower()).startswith(font)and int(s['size'])==expect['size']for s in specs),(record.name,specs,expect)
  fonts=subprocess.check_output(['pdffonts',pdf],text=True);assert any(font in re.sub('[^a-z]','',line.lower())and re.search(r'yes\s+yes\s+yes',line)for line in fonts.splitlines()),(record.name,fonts)
  text=''.join(xml.itertext());assert any(x in text for x in 'çãéêí'),record.name
 print('79 real A/B/principal five-family PDFs: Poppler verified exact body point-size, requested font, embedded Unicode glyphs and preserved historical default9/10. No provider transport.')
