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
        self.assertIn("docker/setup-web.php", dockerfile)
        self.assertIn("docker/provisioner.php", dockerfile)
        self.assertIn("php -l /opt/argws-crm-provisioner/web/index.php", dockerfile)

    def test_web_entrypoint_exposes_only_the_one_time_setup_route_before_install(self):
        entrypoint = read("docker/entrypoint.sh")
        caddy = read("docker/Caddyfile")
        pending = read("docker/Caddyfile.unprovisioned")
        self.assertNotIn("app-config-sample.php", entrypoint)
        self.assertIn("Caddyfile.unprovisioned", entrypoint)
        self.assertIn("@provisioned file", pending)
        self.assertIn("handle @provisioned", pending)
        self.assertIn("@setup path /setup /setup/", pending)
        self.assertIn("/opt/argws-crm-provisioner/web", pending)
        self.assertIn("@setup path /setup /setup/", caddy)
        self.assertIn("@installer path /install /install/ /install/*", caddy)
        self.assertLess(caddy.index("@installer path"), caddy.rindex("\n        php_server\n"))
        self.assertIn("503", pending)
        self.assertNotIn("docker compose exec", pending)
        self.assertIn("@installer path /install /install/ /install/*", pending)

    def test_cli_and_web_provisioners_share_safe_database_guards(self):
        cli = read("docker/provision.php")
        source = read("docker/provisioner.php")
        web = read("docker/setup-web.php")
        self.assertIn("PHP_SAPI !== 'cli'", cli)
        self.assertIn("information_schema.tables", source)
        self.assertIn("PasswordHash", source)
        self.assertIn("INSERT INTO tblstaff", source)
        self.assertIn("MYSQLI_REPORT_STRICT", source)
        self.assertIn("--input-json", source)
        self.assertIn("ARGWS_SETUP_TOKEN", web)
        self.assertIn("hash_equals", web)
        self.assertIn("csrf_token", web)
        self.assertIn("provision_with_input", web)

    def test_compose_passes_private_database_settings_to_runtime(self):
        for path in ("compose.yaml", "deploy/develop/compose.yaml", "deploy/production/compose.yaml"):
            with self.subTest(path=path):
                compose = read(path)
                self.assertIn("ARGWS_DB_HOST", compose)
                self.assertIn("ARGWS_DB_PASSWORD", compose)
                self.assertIn("ARGWS_CONFIG_DIR", compose)
                self.assertIn("ARGWS_SETUP_TOKEN", compose)

    def test_smoke_covers_web_setup_protection_and_persistence(self):
        smoke = read("tests/docker_provision_smoke.sh")
        restart = smoke.index('docker restart "$web"')
        refreshed_port = smoke.index('docker port "$web" 8080/tcp', restart)
        self.assertGreater(refreshed_port, restart)
        self.assertIn('[[ ! "$status" =~ ^[1-5][0-9][0-9]$ ]]', smoke)
        self.assertIn('"$url/setup"', smoke)
        self.assertIn('setup_token=$setup_token', smoke)
        self.assertIn('admin_password_repeat=$admin_password', smoke)
        self.assertIn("admin_count_after_restart", smoke)
        self.assertNotIn("/opt/argws-crm-provisioner/provision.php", smoke)


    def test_setup_token_is_optional_after_setup_and_deployer_migrates_legacy_env(self):
        for path in ("compose.yaml", "deploy/develop/compose.yaml", "deploy/production/compose.yaml"):
            with self.subTest(path=path):
                self.assertIn("ARGWS_SETUP_TOKEN: ${ARGWS_SETUP_TOKEN:-}", read(path))
        deployer = read("tools/argws-crm-deployer/src/main.rs")
        self.assertIn("previous_compose_has_setup_token", deployer)
        self.assertIn("ensure_setup_token(&old, !previous_compose_has_setup_token", deployer)
        self.assertIn("None if !generate_if_missing => Ok(contents.to_string())", deployer)
        self.assertIn("ARGWS_SETUP_TOKEN deve estar vazio ou conter 64", deployer)

    def test_environment_examples_include_one_time_setup_key(self):
        for path in ("container.env.example", "deploy/develop/.env.example", "deploy/production/.env.example"):
            with self.subTest(path=path):
                self.assertIn("ARGWS_SETUP_TOKEN=", read(path))

    def test_environment_examples_use_the_internal_database_service(self):
        for path in ("deploy/develop/.env.example", "deploy/production/.env.example"):
            with self.subTest(path=path):
                self.assertIn("ARGWS_DB_HOST=database", read(path))

    def test_root_compose_keeps_external_database_variables_optional(self):
        compose = read("compose.yaml")
        self.assertIn("ARGWS_DB_HOST:", compose)
        self.assertNotIn("Defina ARGWS_DB_HOST", compose)
        self.assertIn("container.env.example", read("README.md"))
        self.assertIn("/setup", read("deploy/README.md"))
        self.assertIn("ARGWS_SETUP_TOKEN", read("deploy/README.md"))



if __name__ == "__main__":
    unittest.main()
