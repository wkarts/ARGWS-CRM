#!/usr/bin/env python3
"""Run the local validation subset and record unavailable-runtime gates.

This runner never turns a missing runtime into a false pass. It records the
gate as ``blocked`` so the same evidence can be attached to a local checkpoint
and rerun unchanged inside the pinned CI container.
"""

from __future__ import annotations

import datetime as dt
import json
import os
import shutil
import subprocess
import sys
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
EVIDENCE = ROOT / ".continua-ai" / "evidence" / "local-validation-r10"


def command_available(command: str) -> bool:
    return shutil.which(command) is not None


def run_check(name: str, command: list[str], *, required: list[str] | None = None) -> dict:
    required = required or []
    missing = [item for item in required if not command_available(item)]
    if missing:
        return {"name": name, "status": "blocked", "command": command, "missing": missing}
    try:
        result = subprocess.run(
            command,
            cwd=ROOT,
            text=True,
            stdout=subprocess.PIPE,
            stderr=subprocess.STDOUT,
            check=False,
            timeout=900,
        )
    except subprocess.TimeoutExpired as error:
        output = (error.stdout or "")[-8000:]
        return {"name": name, "status": "failed", "command": command, "exit_code": None, "output": output, "timeout": True}
    output = result.stdout[-12000:]
    return {"name": name, "status": "passed" if result.returncode == 0 else "failed", "command": command, "exit_code": result.returncode, "output": output}


def main() -> int:
    EVIDENCE.mkdir(parents=True, exist_ok=True)
    checks = [
        run_check("node-contracts", ["node", "--test", "tests/*.test.cjs"], required=["node"]),
        run_check("ui-inventory", ["python3", "tests/company_ui_surface_inventory.py"], required=["python3"]),
        run_check("focused-ci-contract", ["python3", "tests/ci/test_native_company_focused.py"], required=["python3"]),
        run_check("workflow-ownership", ["python3", "-c", (
            "from pathlib import Path; import re; "
            "hits=[p.name for p in Path('.github/workflows').glob('*.yml') "
            "if re.search(r'node --test tests/native_header_overflow\\.test\\.cjs', p.read_text())]; "
            "assert hits == ['native-header-layout.yml'], hits; print(hits)"
        )], required=["python3"]),
        run_check("php-runtime", ["php", "-v"], required=["php"]),
        run_check("mix-build", ["npm", "run", "build"], required=["npm", "node"] if (ROOT / "node_modules").exists() else ["npm", "node_modules/.bin/mix"]),
        run_check("ruby-yaml-runtime", ["ruby", "-e", "require 'yaml'; YAML.load_file('.github/workflows/native-company-ui.yml')"], required=["ruby"]),
    ]
    environment = {
        "php": shutil.which("php"),
        "docker": shutil.which("docker"),
        "ruby": shutil.which("ruby"),
        "node": shutil.which("node"),
        "npm": shutil.which("npm"),
        "node_modules": (ROOT / "node_modules").is_dir(),
    }
    report = {
        "format": 1,
        "generated_at_utc": dt.datetime.now(dt.timezone.utc).replace(microsecond=0).isoformat().replace("+00:00", "Z"),
        "root": str(ROOT),
        "environment": environment,
        "checks": checks,
        "summary": {
            "passed": sum(check["status"] == "passed" for check in checks),
            "failed": sum(check["status"] == "failed" for check in checks),
            "blocked": sum(check["status"] == "blocked" for check in checks),
            "complete": all(check["status"] == "passed" for check in checks),
        },
    }
    (EVIDENCE / "local-validation.json").write_text(json.dumps(report, ensure_ascii=False, indent=2) + "\n")
    for check in checks:
        suffix = "" if not check.get("missing") else f" (missing: {', '.join(check['missing'])})"
        print(f"{check['status'].upper():7} {check['name']}{suffix}")
    print(f"evidence: {EVIDENCE / 'local-validation.json'}")
    return 1 if report["summary"]["failed"] else 0


if __name__ == "__main__":
    sys.exit(main())
