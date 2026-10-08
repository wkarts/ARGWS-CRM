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
        self.assertIn("route {", caddy)
        self.assertIn("@installer path /install /install/ /install/*", caddy)
        self.assertLess(caddy.index("@installer path"), caddy.rindex("\n        php_server\n"))
        self.assertIn("503", pending)
        self.assertIn("provision.php", pending)
        self.assertIn("@installer path /install /install/ /install/*", pending)

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

    def test_smoke_refreshes_ephemeral_port_and_rejects_missing_http_status(self):
        smoke = read("tests/docker_provision_smoke.sh")
        restart = smoke.index('docker restart "$web"')
        refreshed_port = smoke.index('docker port "$web" 8080/tcp', restart)
        self.assertGreater(refreshed_port, restart)
        self.assertIn('[[ ! "$status" =~ ^[1-5][0-9][0-9]$ ]]', smoke)

    def test_root_compose_keeps_external_database_variables_optional(self):
        compose = read("compose.yaml")
        self.assertIn("ARGWS_DB_HOST:", compose)
        self.assertNotIn("Defina ARGWS_DB_HOST", compose)
        self.assertIn("container.env.example", read("README.md"))



if __name__ == "__main__":
    unittest.main()
