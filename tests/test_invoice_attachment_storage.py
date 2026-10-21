import unittest
from pathlib import Path
ROOT = Path(__file__).resolve().parents[1]

class InvoiceAttachmentStorage(unittest.TestCase):
    def test_both_caddy_configs_deny_invoice_static_path_before_php(self):
        for name in ['Caddyfile', 'Caddyfile.unprovisioned']:
            with self.subTest(name=name):
                source = (ROOT / 'docker' / name).read_text()
                rule = '@private_invoice_uploads path /uploads/invoices /uploads/invoices/ /uploads/invoices/*'
                self.assertIn(rule, source)
                self.assertLess(source.index(rule), source.rindex('php_server'))
                self.assertIn('respond @private_invoice_uploads 404', source)
                self.assertNotIn('@private_invoice_uploads path /uploads/*', source)

    def test_apache_and_invoice_views_use_protected_download_path(self):
        self.assertIn('Deny from all', (ROOT / 'uploads/invoices/.htaccess').read_text())
        for name in ['application/views/admin/invoices/invoice_preview_html.php',
                     'application/views/admin/invoices/invoice_send_to_client.php',
                     'application/views/themes/perfex/views/invoicehtml.php']:
            source = (ROOT / name).read_text()
            self.assertIn('download/file/sales_attachment/', source)
            self.assertNotIn('uploads/invoices/', source)

    def test_customer_static_paths_and_previews_are_protected(self):
        for name in ['Caddyfile', 'Caddyfile.unprovisioned']:
            source = (ROOT / 'docker' / name).read_text()
            rule = '@private_customer_uploads path /uploads/clients /uploads/clients/ /uploads/clients/*'
            self.assertIn(rule, source)
            self.assertLess(source.index(rule), source.rindex('php_server'))
            self.assertIn('respond @private_customer_uploads 404', source)
            self.assertNotIn('@private_customer_uploads path /uploads/*', source)
        self.assertIn('Deny from all', (ROOT / 'uploads/clients/.htaccess').read_text())
        for name in ['application/views/admin/clients/groups/attachments.php',
                     'application/views/themes/perfex/views/files.php']:
            source = (ROOT / name).read_text()
            self.assertIn('download/preview_image', source)
            self.assertIn('protected_file_url_by_path', source)
        source = (ROOT / 'application/controllers/Download.php').read_text()
        self.assertIn('shared_customer_files', source)
        self.assertIn('ClientIssuerScope::isLegacyClient', source)

if __name__ == '__main__':
    unittest.main()
