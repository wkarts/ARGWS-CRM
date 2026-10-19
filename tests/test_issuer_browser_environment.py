"""Exercise the real smoke command blocks with a clean Bash environment."""
import json
import os
from pathlib import Path
import re
import subprocess
import tempfile
import unittest


class BrowserEnvironmentTest(unittest.TestCase):
    def test_every_browser_receives_its_disposable_environment(self):
        script = Path(__file__).with_name("issuers_standalone_http_smoke.sh")
        blocks, pending = [], []
        for line in script.read_text().splitlines():
            pending.append(line)
            if line.endswith("\\"):
                continue
            block = "\n".join(pending)
            if re.fullmatch(r"node tests/[A-Za-z0-9_/]+\.cjs", line):
                blocks.append((line.split()[1], block))
            pending = []
        self.assertGreater(len(blocks), 2)
        keys = ["URL", "PASSWORD", "DB_CONTAINER", "DB_NAME", "DB_USER", "DB_PASSWORD", "APP_CONTAINER"]
        environment = {k: v for k, v in os.environ.items()
                       if not k.startswith(("ISSUERS_BROWSER_", "ISSUER_SMOKE_"))}
        with tempfile.TemporaryDirectory() as directory:
            node = Path(directory) / "node"
            node.write_text("#!/usr/bin/env python3\nimport json,os\n"
                            "print(json.dumps({k:os.environ.get('ISSUERS_BROWSER_'+k) for k in "
                            + repr(keys) + "}))\n")
            node.chmod(0o755)
            environment["PATH"] = directory + os.pathsep + environment["PATH"]
            setup = """set -eu
url=http://127.0.0.1:48111
upgrade_url=http://127.0.0.1:48222
upgrade=argws-crm-upgrade-999
ISSUER_SMOKE_DATABASE=argws-crm-provision-db-999
ISSUER_SMOKE_DB_USER=synthetic_user
ISSUER_SMOKE_DB_PASSWORD=synthetic_password
"""
            for index, (path, block) in enumerate(blocks):
                with self.subTest(command=path, position=index):
                    result = subprocess.run(["bash", "-c", setup + block], env=environment,
                                            text=True, capture_output=True, check=True)
                    actual = json.loads(result.stdout)
                    self.assertEqual(actual["URL"], "http://127.0.0.1:" + ("48111" if index == 0 else "48222"))
                    self.assertEqual(actual["PASSWORD"], "synthetic-browser-17")
                    self.assertEqual(actual["DB_CONTAINER"], "argws-crm-provision-db-999")
                    self.assertEqual(actual["DB_NAME"], "argws_legacy_ci" if index == 0 else "argws_upgrade_ci")
                    self.assertEqual(actual["DB_USER"], "synthetic_user")
                    self.assertEqual(actual["DB_PASSWORD"], "synthetic_password")
                    if path != "tests/issuers_browser_smoke.cjs":
                        self.assertEqual(actual["APP_CONTAINER"], "argws-crm-upgrade-999")


if __name__ == "__main__":
    unittest.main()
