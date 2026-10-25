"""Source/DDL contracts supplement, never replace PHP/MySQL and A/B runtime tests."""
import re
import unittest
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
class EfiCompanyContracts(unittest.TestCase):
 def test_seed_parity(self):
  schema=(ROOT/'application/services/EfiCompanySchema.php').read_text()
  fields={m[0]:re.findall(r"'([^']+)'=>'([^']+)'",m[1]) for m in re.findall(r"^        '(\w+)'=>\[(.*?)\],$",schema.split('public const KEYS')[0],re.M)}
  keys={m[0]:[(k,re.findall(r"'([^']+)'",v)) for k,v in re.findall(r"'([^']+)'=>\[([^\]]+)\]",m[1])] for m in re.findall(r"^        '(\w+)'=>\[(.*?)\],$",schema.split('public const KEYS')[1].split('public static function tables')[0],re.M)}
  self.assertEqual(set(fields),{'accounts','origins','proofs','receipts'})
  seed=(ROOT/'install/database.sql').read_text()
  for name,cols in fields.items():
   parts=[]
   for col,typ in cols:
    nullable=typ.endswith('?');typ=typ.rstrip('?')
    parts.append('`'+col+'` '+typ.upper()+(' CHARACTER SET utf8mb4 COLLATE utf8mb4_bin' if re.search('char|text',typ) else '')+(' NULL' if nullable else ' NOT NULL')+(' AUTO_INCREMENT' if col=='id' else ''))
   for key,cols in keys[name]:parts.append(('PRIMARY KEY' if key=='PRIMARY' else 'UNIQUE KEY `'+key+'`')+' (`'+'`,`'.join(cols)+'`)')
   expected='CREATE TABLE `tblefi_company_'+name+'` ('+','.join(parts)+') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;'
   self.assertEqual(seed.count(expected),1)
 def test_no_global_fallback(self):
  source=(ROOT/'modules/gerencianet_gateway/services/CompanyAccounts.php').read_text()
  self.assertNotRegex(source,r'\b(get_option|update_option|add_option)\s*\(')
  for token in ['IssuerTransaction::assertOwned','IssuerTransaction::run','certificate_hash','efi_company_account_owned','efi_company_account_revoked']:
   self.assertIn(token,source)
 def test_core_owns_ddl(self):
  migration=(ROOT/'application/migrations/473_version_473.php').read_text()
  self.assertIn("APPPATH . 'services/EfiCompanySchema.php'",migration)
  self.assertIn('CompanySchema::ensure($this->db, db_prefix(), true)',migration)
  self.assertNotIn('CREATE TABLE',(ROOT/'modules/gerencianet_gateway/migrations/515_version_515.php').read_text())
 def test_ui_scope_and_no_secret_echo(self):
  controller=(ROOT/'application/controllers/admin/Company_efi.php').read_text()
  view=(ROOT/'application/views/admin/companies/efi.php').read_text()
  for marker in ['CompanyRequestScope::sealedFor','CompanySettingsRuntime::issuerId','hash_equals($csrf','CompanyCheckout','instruments','Cache-Control: no-store']:
   self.assertIn(marker,controller)
  self.assertIn("'company_efi'",(ROOT/'application/services/CompanyRequestScope.php').read_text())
  self.assertIn("views/admin/companies/layout_head.php",view)
  self.assertIn("views/admin/companies/layout_tail.php",view)
  self.assertNotIn('init_head(',view)
  self.assertNotIn("$profile['credentials_cipher']",view)
  self.assertNotIn("$profile['certificate_cipher']",view)
  self.assertIn('rel="noopener noreferrer"',view)
 def test_reverse_origin_fences(self):
  for file in ['application/services/CompanyInvoices.php','application/services/CompanyCreditWriter.php','application/services/InvoicePaymentWriter.php','modules/asaas/libraries/Asaas/CompanyService.php','modules/banco_brasil/libraries/BancoBrasil/ApiService.php','modules/banco_brasil/libraries/BancoBrasil/Origination.php']:
   source=(ROOT/file).read_text()
   if file=='application/services/CompanyInvoices.php':
    self.assertIn("require_once __DIR__.'/CompanyInvoiceProviderTables.php'",source)
    self.assertGreaterEqual(source.count('CompanyInvoiceProviderTables::current('),4)
    self.assertIn('efi_company_origins',(ROOT/'application/services/CompanyInvoiceProviderTables.php').read_text())
    self.assertIn('company_invoice_provider_review_required',source)
   else:
    self.assertIn('efi_company_origins',source,file)
if __name__=='__main__':unittest.main()
