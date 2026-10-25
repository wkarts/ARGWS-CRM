"""Static coupling guards only; runtime assertions live in auxiliary_contract.php."""
import pathlib
import unittest
ROOT = pathlib.Path(__file__).resolve().parents[2]
class NativeAuxiliaryContract(unittest.TestCase):
    def read(self,p): return (ROOT/p).read_text()
    def test_no_global_storage_or_provider_fallback(self):
        for file in ('NativeCompanyAuxiliary.php','NativeCompanyCatalog.php'):
            source=self.read('application/services/'+file)
            for forbidden in ('get_option(', 'get_instance(', 'hooks()', '$_SESSION', '$_POST', 'curl_exec(', 'send_mail_template('):
                self.assertNotIn(forbidden,source)
            self.assertIn('IssuerTransaction::run',source)
            self.assertIn('lockFinancialIdentity',source)
    def test_native_controllers_branch_before_legacy_model(self):
        for controller,model in [('Todo','todo_model'),('Announcements','announcements_model'),('Misc','misc_model'),('Taxes','taxes_model')]:
            source=self.read('application/controllers/admin/'+controller+'.php')
            self.assertLess(source.index('CompanyRequestScope::nativeFor'),source.index("$this->load->model('"+model+"')"))
            self.assertIn('CompanyRequestScope::assertNativeMutation($this)',source)
    def test_schema_does_not_adopt_legacy_rows(self):
        source=self.read('application/services/NativeCompanyAuxiliarySchema.php')
        self.assertNotIn('ALTER TABLE',source)
        self.assertNotIn('INSERT INTO',source)
        for table in ('company_todos','company_announcements','company_announcement_dismissals','company_reminders','company_notes','company_items','company_taxes','company_item_groups'):
            self.assertIn("'"+table+"'",source)
        self.assertIn("'rel_id' => ['bigint unsigned'",source)
        self.assertIn('REFERENTIAL_CONSTRAINTS',source)
    def test_no_fake_runtime_success(self):
        source=self.read('tests/native_company/auxiliary_contract.php')
        for token in ('essentialCheck','settingThrows','company_auxiliary_stale','issuer_outer_transaction_not_allowed','company_catalog_denied','INSERT INTO'):
            self.assertIn(token,source)
if __name__=='__main__': unittest.main()
