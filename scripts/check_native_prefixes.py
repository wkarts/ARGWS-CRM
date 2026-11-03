#!/usr/bin/env python3
"""Fail closed when a first-party basename recreates the removed company_ namespace."""
from __future__ import annotations
import json
import subprocess
import sys
from pathlib import Path

EXCLUDED_PARTS = {'.git', 'node_modules', 'vendor', 'cache', 'logs', '__pycache__'}
EXCLUDED_PREFIXES = ('application/cache/', 'application/logs/', 'uploads/')

def files(root: Path):
    # Prefer the Git index so a staged rename/delete is checked exactly as it
    # will be delivered, while synthetic unit-test roots still use the filesystem.
    try:
        tracked = subprocess.check_output(['git', 'ls-files', '-z'], cwd=root, text=False)
    except (OSError, subprocess.CalledProcessError):
        tracked = b''
    if tracked:
        for raw in tracked.split(b'\0'):
            if not raw:
                continue
            path = root / raw.decode()
            rel = path.relative_to(root).as_posix()
            if any(part in EXCLUDED_PARTS for part in path.relative_to(root).parts) or rel.startswith(EXCLUDED_PREFIXES):
                continue
            yield path
        return
    for path in root.rglob('*'):
        if not path.is_file():
            continue
        rel = path.relative_to(root).as_posix()
        if any(part in EXCLUDED_PARTS for part in path.relative_to(root).parts) or rel.startswith(EXCLUDED_PREFIXES):
            continue
        yield path

def violations(root: Path):
    return sorted((p.relative_to(root).as_posix() for p in files(root)
                   if p.name.casefold().startswith('company_')))

def main() -> int:
    root = Path(__file__).resolve().parents[1]
    found = violations(root)
    payload = {'root': str(root), 'count': len(found), 'files': found}
    print(json.dumps(payload, ensure_ascii=False, indent=2))
    return 1 if found else 0

if __name__ == '__main__':
    raise SystemExit(main())
