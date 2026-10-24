import argparse
import importlib.util
from pathlib import Path
import subprocess
import unittest
from unittest.mock import patch

spec = importlib.util.spec_from_file_location("diagnose", Path(__file__).resolve().parents[1] / "scripts/diagnose-runtime.py")
diagnose = importlib.util.module_from_spec(spec)
spec.loader.exec_module(diagnose)


class RuntimeDiagnosticsTests(unittest.TestCase):
    def test_default_is_docker_only_with_safe_fields(self):
        with patch.object(diagnose, "run", return_value={"available": True, "value": "0"}) as run:
            report = diagnose.collect("crm-web")
        commands = [c.args[0] for c in run.call_args_list]
        self.assertEqual(len(commands), 9)
        self.assertNotIn("anonymous_local_http", report)
        self.assertTrue(all(c[:2] in (["docker", "inspect"], ["docker", "stats"]) for c in commands))
        self.assertTrue(all("--format" in c for c in commands))
        self.assertFalse(any(".Config.Env" in str(c) or "Health.Log" in str(c) for c in commands))

    def test_http_is_one_bounded_loopback_request_without_redirects(self):
        with patch.object(diagnose, "run", return_value={"available": True}) as run:
            diagnose.collect("crm-web", 8089)
        commands = [c.args[0] for c in run.call_args_list if c.args[0][0] == "curl"]
        self.assertEqual(len(commands), 1)
        command = commands[0]
        self.assertEqual(command[-1], "http://127.0.0.1:8089/admin/")
        self.assertEqual(command[1], "--disable")
        self.assertIn("--max-time", command)
        self.assertIn("/dev/null", command)
        self.assertNotIn("--location", command)

    def test_errors_never_include_stderr_or_stdout(self):
        with patch.object(diagnose.subprocess, "run", return_value=subprocess.CompletedProcess([], 1, "secret", "secret")):
            self.assertEqual(diagnose.run(["docker"]), {"available": False, "exit_code": 1})

    def test_missing_and_timeout_are_explicit(self):
        for error, reason in [(FileNotFoundError(), "command_missing"), (subprocess.TimeoutExpired([], 1), "probe_timeout")]:
            with patch.object(diagnose.subprocess, "run", side_effect=error):
                self.assertEqual(diagnose.run(["docker"])["reason"], reason)

    def test_cli_rejects_options_paths_and_invalid_ports(self):
        for name in ["--all", "x/y", "x; echo secret", ""]:
            with self.assertRaises(argparse.ArgumentTypeError):
                diagnose.container_name(name)
        for port in ["0", "65536", "x"]:
            with self.assertRaises(argparse.ArgumentTypeError):
                diagnose.port_number(port)
        self.assertEqual(diagnose.port_number("8089"), 8089)


if __name__ == "__main__":
    unittest.main()
