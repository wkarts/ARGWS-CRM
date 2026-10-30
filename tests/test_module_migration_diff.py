"""Synthetic module trees and real Git histories for the PR delta gate."""
import importlib.util
import json
import subprocess
import sys
import tempfile
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SPEC = importlib.util.spec_from_file_location("module_migration_diff", ROOT / "scripts/check-module-migration-diff.py")
POLICY = importlib.util.module_from_spec(SPEC)
sys.modules[SPEC.name] = POLICY
SPEC.loader.exec_module(POLICY)


def migration(number):
    return "<?php\nclass Migration_Version_" + str(number) + " extends App_module_migration { public function up() { /* NOOP: version tracking. */ } }\n"


def module(version="1.0.1", numbers=(101,), name="demo"):
    prefix = "modules/" + name + "/"
    files = {
        prefix + name + ".php": "<?php\n/*\nModule Name: Demo\nVersion: " + version + "\n*/\n",
        prefix + "install.php": "<?php\n$m = new App_module_migration('" + name + "'); $m->to_latest();\n",
        prefix + "views/index.php": "<?php echo 'Original';\n",
    }
    files.update({prefix + "migrations/" + str(n) + "_version_" + str(n) + ".php": migration(n) for n in numbers})
    return files


def upgrade(files, version="1.0.2", number=102, name="demo"):
    result = dict(files)
    result["modules/" + name + "/" + name + ".php"] = module(version, (), name)["modules/" + name + "/" + name + ".php"]
    result["modules/" + name + "/migrations/" + str(number) + "_version_" + str(number) + ".php"] = migration(number)
    return result


class ModuleMigrationDelta(unittest.TestCase):
    def errors(self, before, after):
        return POLICY.validate_changes(POLICY.MemoryTree(before), POLICY.MemoryTree(after))[0]

    def test_efi_exact_audited_adoption_not_generic_normalization(self):
        path = "modules/gerencianet_gateway/gerencianet_gateway.php"
        source = (ROOT / "tests/fixtures/efi/legacy-entrypoint.php.txt").read_text()
        before = {path: source}
        after = {path: source.replace("Version: 5.1.4 sdk final", "Version: 5.1.5"),
                 "modules/gerencianet_gateway/migrations/515_version_515.php": migration(515)}
        self.assertEqual([], self.errors(before, after))
        self.assertTrue(self.errors(dict(before, **{"modules/gerencianet_gateway/migrations/514_version_514.php": migration(514)}), dict(after, **{"modules/gerencianet_gateway/migrations/514_version_514.php": migration(514)})))
        for bad in [source + "\n", source.replace("sdk final", "sdk f"), source.replace("sdk final", "other")]:
            self.assertTrue(self.errors({path: bad}, after))
        self.assertTrue(self.errors(before, {path: after[path].replace("5.1.5", "5.1.6"),
            "modules/gerencianet_gateway/migrations/516_version_516.php": migration(516)}))
        self.assertTrue(self.errors(before, {path: after[path]}))
        self.assertTrue(self.errors(before, dict(after, **{"modules/gerencianet_gateway/migrations/514_version_514.php": migration(514)})))

    def test_new_101_native_module_passes(self):
        self.assertEqual([], self.errors({}, module()))

    def test_new_module_with_contiguous_release_history_passes(self):
        self.assertEqual([], self.errors({}, module("1.0.3", (101, 102, 103))))

    def test_new_initial_011_and_100_rejected(self):
        for version, number in (("0.1.1", 11), ("1.0.0", 100)):
            with self.subTest(version=version):
                self.assertTrue(self.errors({}, module(version, (number,))))

    def test_new_module_missing_first_101_rejected(self):
        self.assertTrue(self.errors({}, module("1.0.2", (102,))))

    def test_unchanged_legacy_ignored(self):
        files = module("5.1.4 sdk final", ())
        files["modules/demo/install.php"] = "<?php $db->query('CREATE TABLE demo(id int)');"
        self.assertEqual([], self.errors(files, files))

    def test_old_100_without_migrations_accepts_101_no_backfill(self):
        before = module("1.0.0", ())
        self.assertEqual([], self.errors(before, upgrade(before, "1.0.1", 101)))

    def test_api_historical_gap_not_repaired(self):
        before = module("2.0.1", (101, 102, 103, 200, 201))
        self.assertEqual([], self.errors(before, upgrade(before, "2.0.2", 202)))

    def test_asaas_historical_start_110_not_repaired(self):
        before = module("1.4.5", range(110, 146))
        self.assertEqual([], self.errors(before, upgrade(before, "1.4.6", 146)))

    def test_legacy_install_untouched_is_not_retrofit(self):
        before = module()
        before["modules/demo/install.php"] = "<?php $db->query('CREATE TABLE legacy(id int)');"
        self.assertEqual([], self.errors(before, upgrade(before)))

    def test_functional_paths_require_bump_even_in_docs_or_dist(self):
        for path in ("controllers/Api.php", "views/index.php", "language/portuguese_br/demo_lang.php", "assets/main.js", "dist/main.min.js", "assets/main.css", "docs/runtime.php", "docs/example.js", "docs/view.html", "vendor/sdk/client.php", "build/config.json", "settings.yaml", "views/template.md"):
            with self.subTest(path=path):
                before = module()
                after = dict(before, **{"modules/demo/" + path: "changed"})
                self.assertTrue(self.errors(before, after))

    def test_deleted_functional_file_requires_bump(self):
        before = module()
        after = dict(before)
        del after["modules/demo/views/index.php"]
        self.assertTrue(self.errors(before, after))
        self.assertEqual([], self.errors(before, upgrade(after)))

    def test_docs_only_ignored(self):
        before = module()
        after = dict(before)
        for path in ("README.md", "CHANGELOG.md", "LICENSE", "docs/manual.md", "docs/upgrade.txt"):
            after["modules/demo/" + path] = "Documentação."
        errors, functional, ignored = POLICY.validate_changes(POLICY.MemoryTree(before), POLICY.MemoryTree(after))
        self.assertEqual([], errors)
        self.assertEqual({}, functional)
        self.assertEqual(5, len(ignored))

    def test_docs_plus_functional_still_requires_bump(self):
        before = module()
        after = dict(before, **{"modules/demo/README.md": "Docs", "modules/demo/views/index.php": "New"})
        self.assertTrue(self.errors(before, after))

    def test_noop_missing_new_file_rejected(self):
        before = module()
        after = upgrade(before)
        del after["modules/demo/migrations/102_version_102.php"]
        self.assertTrue(self.errors(before, after))

    def test_migration_without_header_bump_rejected(self):
        before = module()
        after = dict(before, **{"modules/demo/migrations/102_version_102.php": migration(102)})
        self.assertTrue(self.errors(before, after))

    def test_same_or_lower_version_rejected(self):
        for version in ("1.0.1", "1.0.0", "0.9.9"):
            with self.subTest(version=version):
                self.assertTrue(self.errors(module(), upgrade(module(), version, 102)))

    def test_historical_migration_edit_delete_rename_rejected(self):
        before = module()
        path = "modules/demo/migrations/101_version_101.php"
        for operation in ("edit", "delete", "rename"):
            with self.subTest(operation=operation):
                after = upgrade(before)
                if operation == "edit":
                    after[path] += "// Small correction is still forbidden."
                else:
                    old = after.pop(path)
                    if operation == "rename":
                        after["modules/demo/migrations/101_renamed.php"] = old
                self.assertTrue(any("immutable" in e for e in self.errors(before, after)))

    def test_preexisting_future_file_cannot_satisfy_new_release(self):
        before = module("1.0.1", (101, 102))
        self.assertTrue(self.errors(before, upgrade(before)))

    def test_new_steps_must_be_contiguous_above_baseline(self):
        self.assertTrue(self.errors(module(), upgrade(module(), "1.0.3", 103)))

    def test_minor_carry_109_to_110_matches_framework(self):
        before = module("1.0.9", range(101, 110))
        self.assertEqual([], self.errors(before, upgrade(before, "1.1.0", 110)))

    def test_major_carry_199_to_200_matches_framework(self):
        before = module("1.9.9", (199,))
        self.assertEqual([], self.errors(before, upgrade(before, "2.0.0", 200)))

    def test_patch_over_nine_major_and_colliding_versions_rejected(self):
        self.assertEqual("1110", "1.1.10".replace(".", ""))
        self.assertEqual("1110", "1.11.0".replace(".", ""))
        for version in ("1.0.10", "1.1.10", "1.11.0", "10.0.0", "1.0.2-beta", "01.0.2"):
            with self.subTest(version=version):
                self.assertTrue(any("unambiguous" in e for e in self.errors(module(), upgrade(module(), version))))

    def test_noncanonical_changed_baseline_fails_explicitly(self):
        before = module("5.1.4 sdk final", ())
        self.assertTrue(any("unambiguous" in e for e in self.errors(before, upgrade(before, "5.1.5", 515))))

    def test_duplicate_header_rejected(self):
        after = module()
        after["modules/demo/demo.php"] += "\nVersion: 1.0.1\n"
        self.assertTrue(self.errors({}, after))

    def test_wrong_migration_filename_class_base_or_up_rejected(self):
        path = "modules/demo/migrations/101_version_101.php"
        for wrong in ("wrong_class", "core_base", "private_up", "missing_up", "comment_only", "string_only"):
            with self.subTest(wrong=wrong):
                after = module()
                if wrong == "wrong_class": after[path] = migration(102)
                if wrong == "core_base": after[path] = migration(101).replace("App_module_migration", "CI_Migration")
                if wrong == "private_up": after[path] = migration(101).replace("public", "private")
                if wrong == "missing_up": after[path] = migration(101).replace("up()", "down()")
                if wrong == "comment_only": after[path] = "<?php /* " + migration(101).replace("/* NOOP: version tracking. */", "") + " */"
                if wrong == "string_only": after[path] = "<?php $x = 'class Migration_Version_101 extends App_module_migration { public function up() {} }';"
                self.assertTrue(self.errors({}, after))
        after = module()
        after["modules/demo/migrations/101_wrong.php"] = after.pop(path)
        self.assertTrue(self.errors({}, after))

    def test_new_module_requires_native_install_without_ddl(self):
        for install in (None, "<?php // new App_module_migration('demo')->to_latest();", "<?php $x = 'new App_module_migration( )->to_latest()';", "<?php new CI_Migration();", "<?php $m = new App_module_migration('demo'); $m->to_latest(); $db->query('CREATE TABLE test (id INT)');", "<?php $m = new App_module_migration('demo'); $m->to_latest(); $dbforge->create_table('test');"):
            with self.subTest(install=install):
                after = module()
                if install is None: del after["modules/demo/install.php"]
                else: after["modules/demo/install.php"] = install
                self.assertTrue(self.errors({}, after))

    def test_new_module_install_comment_mentions_ddl_without_executing(self):
        after = module()
        after["modules/demo/install.php"] += "\n// CREATE TABLE belongs in the migration.\n"
        self.assertEqual([], self.errors({}, after))

    def test_no_global_core_version_or_other_module_requirement(self):
        before = {**module(), **module("8.9.0 legacy", (), "untouched"), "application/config/version.php": "3.8.1"}
        after = upgrade(before)
        after["application/config/version.php"] = "3.8.2"
        self.assertEqual([], self.errors(before, after))

    def test_module_removal_and_rename_rejected(self):
        self.assertTrue(self.errors(module(), {}))
        self.assertTrue(self.errors(module(), module(name="renamed")))

    def test_symlink_cannot_hide_as_docs_or_policy_input(self):
        before = POLICY.MemoryTree(module())
        after = POLICY.MemoryTree(upgrade(module()))
        after.entries["modules/demo/README.md"] = POLICY.Entry("ignored", "120000")
        self.assertTrue(POLICY.validate_changes(before, after)[0])

    def test_module_root_submodule_cannot_be_skipped(self):
        before, after = POLICY.MemoryTree({}), POLICY.MemoryTree({})
        after.entries["modules/external"] = POLICY.Entry("a" * 40, "commit")
        self.assertTrue(POLICY.validate_changes(before, after)[0])

    def test_historical_migration_mode_change_is_immutable(self):
        before = POLICY.MemoryTree(module())
        after = POLICY.MemoryTree(upgrade(module()))
        path = "modules/demo/migrations/101_version_101.php"
        after.entries[path] = POLICY.Entry(after.entries[path].oid, "100755")
        self.assertTrue(any("immutable" in error for error in POLICY.validate_changes(before, after)[0]))

    def test_native_executor_mapping_contract(self):
        code = (ROOT / "application/libraries/App_module_migration.php").read_text()
        self.assertIn("str_replace('.', '', $module['headers']['version'])", code)
        self.assertIn("/^\\d{3}_(\\w+)$/", code)
        self.assertIn("wordwrap($migration, 1, '.', true)", code)

    def test_workflow_uses_current_pr_shas_and_retarget_event(self):
        workflow = (ROOT / ".github/workflows/module-migration-policy.yml").read_text()
        self.assertIn("pull_request:", workflow)
        self.assertNotIn("pull_request_target", workflow)
        self.assertIn("edited", workflow)
        self.assertIn("fetch-depth: 0", workflow)
        self.assertIn("persist-credentials: false", workflow)
        self.assertIn("contents: read", workflow)
        self.assertIn("ref: ${{ github.sha }}", workflow)
        self.assertNotIn("ref: ${{ github.event.pull_request.head.sha }}", workflow)
        self.assertIn("PR_BASE_SHA: ${{ github.event.pull_request.base.sha }}", workflow)
        self.assertIn("PR_HEAD_SHA: ${{ github.event.pull_request.head.sha }}", workflow)
        self.assertIn(' --base "$PR_BASE_SHA" --head "$PR_HEAD_SHA"', workflow)
        self.assertNotIn("paths:", workflow)

    def source_map_fixture(self):
        files = module()
        files["modules/demo/assets/source.js"] = "const value = 1;\n"
        files["modules/demo/assets/bundle.js"] = "const value=1;\n"
        payload = {"version": 3, "file": "bundle.js", "sources": ["source.js"], "sourcesContent": [files["modules/demo/assets/source.js"]], "mappings": "AAAA"}
        return files, payload

    def test_proven_generated_source_map_only_does_not_force_bump(self):
        before, payload = self.source_map_fixture()
        after = dict(before, **{"modules/demo/assets/bundle.js.map": json.dumps(payload)})
        self.assertEqual([], self.errors(before, after))
        self.assertEqual([], self.errors(after, before))

    def test_generated_name_alone_does_not_skip_runtime(self):
        before, payload = self.source_map_fixture()
        for key, value in (("sourcesContent", ["not the source"]), ("file", "missing.js"), ("sources", ["../../../../outside.js"]), ("sources", ["https://example.invalid/main.js"]), ("sourceRoot", "webpack://"), ("version", 4)):
            with self.subTest(key=key):
                after = dict(before, **{"modules/demo/assets/bundle.js.map": json.dumps(dict(payload, **{key: value}))})
                self.assertTrue(self.errors(before, after))

    def test_source_or_bundle_change_with_map_still_functional(self):
        before, payload = self.source_map_fixture()
        for path in ("source.js", "bundle.js"):
            with self.subTest(path=path):
                after = dict(before, **{"modules/demo/assets/bundle.js.map": json.dumps(payload), "modules/demo/assets/" + path: "changed"})
                self.assertTrue(self.errors(before, after))


class GitBaselineIntegration(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.root = Path(self.temp.name)
        self.command("init", "-q")
        self.command("config", "user.name", "Synthetic Policy Test")
        self.command("config", "user.email", "policy-test@example.invalid")
        self.write(module())
        self.initial = self.commit("initial")

    def tearDown(self):
        self.temp.cleanup()

    def command(self, *args):
        return subprocess.run(["git", "-C", str(self.root), *args], capture_output=True, check=True).stdout.decode().strip()

    def write(self, files):
        for path, contents in files.items():
            file = self.root / path
            file.parent.mkdir(parents=True, exist_ok=True)
            file.write_text(contents)

    def commit(self, message):
        self.command("add", ".")
        self.command("commit", "-qm", message)
        return self.command("rev-parse", "HEAD")

    def test_real_git_delta_and_uncommitted_files_ignored(self):
        self.write(upgrade(module()))
        head = self.commit("upgrade")
        self.write({"modules/demo/demo.php": "broken working tree"})
        baseline, errors, functional, ignored = POLICY.compare_repository(self.root, self.initial, head)
        self.assertEqual(self.initial, baseline)
        self.assertEqual([], errors)
        self.assertEqual(["demo"], list(functional))

    def test_merge_base_not_tip_prevents_importing_unrelated_base_changes(self):
        self.command("checkout", "-qb", "pr")
        self.write(upgrade(module()))
        head = self.commit("pr upgrade")
        self.command("checkout", "-qb", "base", self.initial)
        self.write(module("bad legacy", (), "unrelated"))
        base = self.commit("base advanced")
        baseline, errors, functional, ignored = POLICY.compare_repository(self.root, base, head)
        self.assertEqual(self.initial, baseline)
        self.assertEqual([], errors)
        self.assertEqual(["demo"], list(functional))

    def test_retarget_current_base_changes_required_delta(self):
        self.write(upgrade(module()))
        advanced = self.commit("previous approved release")
        self.write({"modules/demo/views/index.php": "functional correction without bump"})
        head = self.commit("new correction")
        self.assertEqual([], POLICY.compare_repository(self.root, self.initial, head)[1])
        self.assertTrue(POLICY.compare_repository(self.root, advanced, head)[1])

    def composed_checkout(self, pr_files, base_files):
        self.command("checkout", "-qb", "older-pr")
        self.write(pr_files)
        head = self.commit("PR predating policy adoption")
        self.assertFalse((self.root / "scripts/check-module-migration-diff.py").exists())
        self.assertFalse((self.root / "tests/test_module_migration_diff.py").exists())
        self.command("checkout", "-qb", "base-with-policy", self.initial)
        self.write(base_files)
        self.write({path: (ROOT / path).read_text() for path in (
            "scripts/check-module-migration-diff.py", "tests/test_module_migration_diff.py")})
        base = self.commit("base adopts policy independently")
        self.command("checkout", "older-pr")
        self.command("merge", "--no-ff", "-m", "synthetic PR merge", "base-with-policy")
        self.assertTrue((self.root / "tests/test_module_migration_diff.py").is_file())
        self.assertNotIn(self.command("rev-parse", "HEAD"), (base, head))
        result = subprocess.run([
            sys.executable, str(self.root / "scripts/check-module-migration-diff.py"),
            "--base", base, "--head", head,
        ], capture_output=True, text=True, cwd=self.root)
        self.assertIn("Module migration baseline: " + self.initial, result.stdout)
        return result, base, head

    def test_older_pr_uses_gate_from_composed_tree_without_importing_base_modules(self):
        result, _, _ = self.composed_checkout(
            upgrade(module()), module("bad legacy", (), "unrelated"))
        self.assertEqual(0, result.returncode, result.stderr)
        self.assertIn('Functional modules: ["demo"]', result.stdout)

    def test_base_bump_cannot_hide_unbumped_pr_in_composed_checkout(self):
        result, base, head = self.composed_checkout(
            {"modules/demo/views/index.php": "functional correction without bump"},
            upgrade(module()))
        self.assertEqual(1, result.returncode)
        self.assertIn("FAIL", result.stderr)
        # Comparing the composed tree instead would wrongly borrow the base's bump.
        composed = self.command("rev-parse", "HEAD")
        self.assertEqual([], POLICY.compare_repository(self.root, self.initial, composed)[1])
        self.assertTrue(POLICY.compare_repository(self.root, base, head)[1])

    def test_invalid_or_missing_shas_fail_closed(self):
        for sha in ("HEAD", "--help", "0" * 40, "a" * 40, self.initial[:7]):
            with self.subTest(sha=sha), self.assertRaises(ValueError):
                POLICY.compare_repository(self.root, sha, self.initial)


if __name__ == "__main__":
    unittest.main()
