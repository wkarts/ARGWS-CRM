import unittest
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]


def read(path):
    return (ROOT / path).read_text(encoding="utf-8")


class DockerProvisioningContractTest(unittest.TestCase):
    def test_runtime_image_excludes_web_installer(self):
        dockerfile = read("Dockerfile")
        ignore = read(".dockerignore")
        self.assertIn("rm -rf /out/app/install", dockerfile)
        self.assertIn("install/database.sql", dockerfile)
        self.assertIn("!install/database.sql", ignore)
        self.assertNotIn("find application modules install", dockerfile)

    def test_web_entrypoint_is_blocked_until_cli_setup(self):
        entrypoint = read("docker/entrypoint.sh")
        caddy = read("docker/Caddyfile")
        pending = read("docker/Caddyfile.unprovisioned")
        self.assertNotIn("app-config-sample.php", entrypoint)
        self.assertIn("Caddyfile.unprovisioned", entrypoint)
        self.assertIn("@installer path /install /install/*", caddy)
        self.assertIn("503", pending)
        self.assertIn("provision.php", pending)

    def test_cli_provisioner_guards_existing_databases_and_hashes_admin(self):
        source = read("docker/provision.php")
        self.assertIn("PHP_SAPI !== 'cli'", source)
        self.assertIn("information_schema.tables", source)
        self.assertIn("PasswordHash", source)
        self.assertIn("INSERT INTO tblstaff", source)
        self.assertIn("MYSQLI_REPORT_STRICT", source)
        self.assertIn("--input-json", source)

    def test_compose_passes_private_database_settings_to_runtime(self):
        for path in ("compose.yaml", "deploy/develop/compose.yaml", "deploy/production/compose.yaml"):
            with self.subTest(path=path):
                compose = read(path)
                self.assertIn("ARGWS_DB_HOST", compose)
                self.assertIn("ARGWS_DB_PASSWORD", compose)
                self.assertIn("ARGWS_CONFIG_DIR", compose)


if __name__ == "__main__":
    unittest.main()
