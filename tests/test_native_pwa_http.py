"""Real PHP HTTP endpoint tests; no CRM DB, session, or credentials required."""
import json, os, pathlib, shutil, socket, subprocess, tempfile, time, unittest, urllib.request, urllib.error
ROOT = pathlib.Path(__file__).resolve().parents[1]
@unittest.skipUnless(shutil.which('php'), 'PHP runtime unavailable')
class PwaHttp(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.tmp = tempfile.TemporaryDirectory()
        os.symlink(ROOT, pathlib.Path(cls.tmp.name) / 'crm')
        os.symlink(ROOT / 'pwa.php', pathlib.Path(cls.tmp.name) / 'pwa.php')
        with socket.socket() as s:
            s.bind(('127.0.0.1',0)); cls.port = s.getsockname()[1]
        cls.server = subprocess.Popen(['php','-S',f'127.0.0.1:{cls.port}','-t',cls.tmp.name],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
        cls.url = f'http://127.0.0.1:{cls.port}/crm/pwa.php'
        for _ in range(100):
            try: urllib.request.urlopen(cls.url).close(); break
            except OSError: time.sleep(.05)
        else: raise RuntimeError('PHP server failed')
    @classmethod
    def tearDownClass(cls):
        cls.server.terminate(); cls.server.wait(); cls.tmp.cleanup()
    def test_manifest_headers_scope_icons(self):
        with urllib.request.urlopen(self.url) as r:
            self.assertIn('application/manifest+json',r.headers['Content-Type'])
            self.assertIn('no-store',r.headers['Cache-Control'])
            self.assertNotIn('Set-Cookie',r.headers)
            m=json.load(r)
        self.assertEqual(m['scope'],'/crm/'); self.assertEqual(m['id'],'/crm/')
        self.assertEqual(m['start_url'],'/crm/admin'); self.assertEqual(m['display'],'standalone')
        self.assertEqual([i['sizes'] for i in m['icons']],['192x192','512x512','512x512'])
        self.assertEqual(m['icons'][2]['purpose'],'maskable')
        for icon in m['icons']:
            with urllib.request.urlopen(f'http://127.0.0.1:{self.port}'+icon['src']) as r:
                self.assertIn('image/png',r.headers['Content-Type']); self.assertTrue(r.read().startswith(b'\x89PNG'))
    def test_root_scope(self):
        with urllib.request.urlopen(f'http://127.0.0.1:{self.port}/pwa.php') as r:
            m=json.load(r)
        self.assertEqual(m['scope'],'/'); self.assertEqual(m['start_url'],'/admin')
    def test_worker_headers_and_methods(self):
        with urllib.request.urlopen(self.url+'?resource=worker') as r:
            self.assertIn('application/javascript',r.headers['Content-Type'])
            self.assertEqual(r.headers['Service-Worker-Allowed'],'/crm/')
            self.assertIn('no-store',r.headers['Cache-Control']); self.assertIn(b'no-store',r.read())
        for request,status in [(urllib.request.Request(self.url,method='POST'),405),(self.url+'?resource=unknown',404)]:
            with self.assertRaises(urllib.error.HTTPError) as e: urllib.request.urlopen(request)
            self.assertEqual(e.exception.code,status)
        with urllib.request.urlopen(urllib.request.Request(self.url,method='HEAD')) as r: self.assertEqual(r.read(),b'')
if __name__ == '__main__': unittest.main()
