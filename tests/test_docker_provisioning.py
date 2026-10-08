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

    def test_persistent_storage_uses_relative_env_root_in_all_compose_stacks(self):
        targets = {
            "/var/lib/argws-crm/config", "/app/uploads", "/app/temp",
            "/app/application/cache", "/app/application/logs",
            "/app/modules/accounting/uploads", "/app/modules/finance/uploads",
            "/app/modules/fleet/uploads", "/app/modules/hr_payroll/uploads",
            "/app/modules/hr_profile/uploads", "/app/modules/invoices_builder/uploads",
            "/app/modules/ma/uploads", "/app/modules/products/uploads",
            "/app/modules/purchase/uploads", "/app/modules/service_management/uploads",
            "/app/modules/si_custom_theme/uploads", "/app/modules/timesheets/uploads",
            "/data", "/config",
        }
        for path in ("compose.yaml", "deploy/develop/compose.yaml", "deploy/production/compose.yaml"):
            with self.subTest(path=path):
                compose = read(path)
                self.assertNotIn("\nvolumes:\n", compose)
                self.assertIn('"${ARGWS_STORAGE_ROOT:-./storage}', compose)
                self.assertIn("service_completed_successfully", compose)
                self.assertIn('mkdir -p "/storage/$directory"', compose)
                self.assertIn('chown 33:33 "/storage/$directory"', compose)
                self.assertNotIn('"/storage/$directory"', compose)
                mounts = [
                    line.strip().split('"')[1]
                    for line in compose.splitlines()
                    if line.strip().startswith('- "') and "${ARGWS_STORAGE_ROOT:-./storage}" in line
                ]
                self.assertTrue(mounts)
                for mount in mounts:
                    source, target = mount.rsplit(":", 1)
                    self.assertTrue(source == "${ARGWS_STORAGE_ROOT:-./storage}" or source.startswith("${ARGWS_STORAGE_ROOT:-./storage}/"))
                    self.assertTrue(target.startswith("/"))
                mounted_targets = {mount.rsplit(":", 1)[1] for mount in mounts}
                self.assertTrue(targets.issubset(mounted_targets))
                if path != "compose.yaml":
                    self.assertIn("/var/lib/mysql", mounted_targets)

        for path in ("container.env.example", "deploy/develop/.env.example", "deploy/production/.env.example"):
            self.assertIn("ARGWS_STORAGE_ROOT=./storage", read(path))

    def test_named_volume_migration_is_non_destructive_and_shell_valid(self):
        migration = read("deploy/migrate-storage.sh")
        self.assertIn('down --remove-orphans', migration)
        self.assertIn('cp -an /legacy/. /target/', migration)
        self.assertIn('COMPOSE_PROJECT_NAME', migration)
        self.assertNotIn('down -v', migration)
        self.assertNotIn('docker volume rm', migration)
        self.assertNotIn('--rm-volume', migration)
        result = __import__("subprocess").run(["sh", "-n", "deploy/migrate-storage.sh"], cwd=ROOT, capture_output=True, text=True)
        self.assertEqual(result.returncode, 0, result.stderr)
        core = read("tools/argws-crm-deployer/src/core.rs")
        self.assertIn('include_str!("../../../deploy/migrate-storage.sh")', core)
        self.assertIn("ensure_storage_root(&contents)", core)
        self.assertIn("valid_storage_root(&storage_root)", core)
    def test_named_volume_migration_copies_data_and_keeps_the_source(self):
        import os
        import subprocess
        import tempfile

        with tempfile.TemporaryDirectory() as temporary:
            root = Path(temporary)
            stack = root / "stack"
            volumes = root / "volumes"
            docker_bin = root / "bin"
            stack.mkdir()
            volumes.mkdir()
            docker_bin.mkdir()
            (stack / "compose.yaml").write_text("services: {}\n", encoding="utf-8")
            (stack / ".env").write_text(
                "COMPOSE_PROJECT_NAME=testcrm\nARGWS_STORAGE_ROOT=./storage\nARGWS_VERSION=3.6.0\n",
                encoding="utf-8",
            )
            legacy = volumes / "testcrm_uploads"
            (legacy / "nested").mkdir(parents=True)
            (legacy / "nested" / "customer.txt").write_text("customer data", encoding="utf-8")
            fake_docker = docker_bin / "docker"
            fake_docker.write_text(
                """#!/bin/sh
set -eu
case "$1" in
  compose|image) exit 0 ;;
  volume) [ -d "$FAKE_DOCKER_VOLUMES/$3" ] ;;
  run)
    shift
    legacy=
    target=
    while [ "$#" -gt 0 ]; do
      if [ "$1" = "--volume" ]; then
        spec=$2
        shift 2
        case "$spec" in
          *:/legacy:ro) legacy=$(printf '%s' "$spec" | sed 's|:/legacy:ro$||') ;;
          *:/target) target=$(printf '%s' "$spec" | sed 's|:/target$||') ;;
        esac
      else
        shift
      fi
    done
    mkdir -p "$target"
    cp -an "$FAKE_DOCKER_VOLUMES/$legacy/." "$target/"
    ;;
  *) exit 2 ;;
esac
""",
                encoding="utf-8",
            )
            fake_docker.chmod(0o755)
            env = os.environ.copy()
            env["PATH"] = str(docker_bin) + os.pathsep + env["PATH"]
            env["FAKE_DOCKER_VOLUMES"] = str(volumes)
            result = subprocess.run(
                ["sh", str(ROOT / "deploy/migrate-storage.sh"), str(stack)],
                env=env,
                capture_output=True,
                text=True,
            )
            self.assertEqual(result.returncode, 0, result.stderr)
            copied = stack / "storage/uploads/nested/customer.txt"
            self.assertEqual(copied.read_text(encoding="utf-8"), "customer data")
            self.assertEqual((legacy / "nested/customer.txt").read_text(encoding="utf-8"), "customer data")

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
        self.assertIn('docker compose --project-directory "$storage_compose_dir"', smoke)
        self.assertIn("storage-init OK", smoke)
        self.assertNotIn("/opt/argws-crm-provisioner/provision.php", smoke)


    def test_web_provisioning_has_no_short_password_policy_or_php_request_timeout(self):
        source = read("docker/provisioner.php")
        web = read("docker/setup-web.php")
        smoke = read("tests/docker_provision_smoke.sh")
        self.assertIn("set_time_limit(0)", source)
        self.assertIn("$input['admin_password'] === ''", source)
        self.assertNotIn("strlen($input['admin_password']) < 12", source)
        self.assertNotIn('minlength="12"', web)
        self.assertNotIn("Use pelo menos 12 caracteres.", web)
        self.assertIn('admin_password="abc123"', smoke)
        self.assertIn("--max-time 180", smoke)


    def test_setup_reports_partial_schema_without_deleting_or_overwriting_database(self):
        web = read("docker/setup-web.php")
        source = read("docker/provisioner.php")
        self.assertIn("schema parcial", web)
        self.assertIn("O banco já contém tabelas", web)
        self.assertIn("O banco não está vazio", source)
        self.assertIn("nenhum", source.lower())


    def test_setup_token_is_optional_after_setup_and_deployer_migrates_legacy_env(self):
        for path in ("compose.yaml", "deploy/develop/compose.yaml", "deploy/production/compose.yaml"):
            with self.subTest(path=path):
                self.assertIn("ARGWS_SETUP_TOKEN: ${ARGWS_SETUP_TOKEN:-}", read(path))
        core = read("tools/argws-crm-deployer/src/core.rs")
        self.assertIn("previous_compose_has_setup_token", core)
        self.assertIn("ensure_setup_token(&old, !previous_compose_has_setup_token", core)
        self.assertIn("None if !generate_if_missing => Ok(contents.to_string())", core)
        self.assertIn("ARGWS_SETUP_TOKEN deve estar vazio ou conter 64", core)

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
