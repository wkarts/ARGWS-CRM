#!/usr/bin/env python3
"""Conservative inventory, not a SQL parser or a proof of multi-issuer authorization.

New/changed invoice/customer boundaries require review. A passing inventory check
means the inventory is current; it never opens the operational rollout gate.
"""
from __future__ import annotations
import argparse
import hashlib
import json
from pathlib import Path
import re
import sys

EXCLUDED_PARTS = {'vendor', 'third_party', 'assets', 'language', 'migrations', 'node_modules'}
EXCLUDED_NAMES = {'install.php', 'uninstall.php'}
SIGNALS = {
    'invoice_table': re.compile(r"(?:['\"]invoices(?:['\".\s])|db_prefix\(\).{0,10}invoices)", re.I),
    'invoice_model': re.compile(r'\binvoices_model\b', re.I),
    'payments': re.compile(r'\binvoicepaymentrecords\b', re.I),
    'customer_privacy': re.compile(r"(?:\bclients_model\b|\bClientIssuerScope\b|\bPortalClientIdentity\b|db_prefix\(\).{0,10}clients|['\"]clients['\"])", re.I),
    'documents_numbering': re.compile(r'\binvoice_pdf\b|\bformat_invoice_number\b|invoice_company_|next_invoice_number', re.I),
}
ALLOWED_STATES = {'unreviewed', 'partial', 'closed_legacy', 'closed_scoped', 'not_applicable'}


def discover(root: Path) -> dict:
    found = {}
    for directory in ('application', 'modules'):
        base = root / directory
        if not base.exists():
            continue
        for path in sorted(base.rglob('*.php')):
            relative = path.relative_to(root)
            if any(part in EXCLUDED_PARTS for part in relative.parts) or path.name in EXCLUDED_NAMES:
                continue
            source = path.read_text(encoding='utf-8', errors='replace')
            matches = []
            for number, line in enumerate(source.splitlines(), 1):
                categories = [name for name, pattern in SIGNALS.items() if pattern.search(line)]
                if categories:
                    matches.append({'line': number, 'categories': categories})
            if matches:
                found[relative.as_posix()] = {
                    'sha256': hashlib.sha256(source.encode()).hexdigest(),
                    'categories': sorted({category for match in matches for category in match['categories']}),
                    'signal_lines': [match['line'] for match in matches],
                    'state': 'unreviewed',
                    'evidence': '',
                }
    return found


def reconcile(current: dict, previous: dict) -> dict:
    for path, record in current.items():
        old = previous.get(path, {})
        # Never carry a coverage assertion across a changed source file automatically.
        if old.get('sha256') == record['sha256']:
            record['state'] = old.get('state', 'unreviewed')
            record['evidence'] = old.get('evidence', '')
    return current


def inventory_errors(current: dict, saved: dict) -> list[str]:
    errors = []
    for path in sorted(set(current) | set(saved)):
        if path not in saved:
            errors.append(f'Uninventoried boundary: {path}')
        elif path not in current:
            errors.append(f'Removed boundary needs review: {path}')
        elif current[path]['sha256'] != saved[path].get('sha256'):
            errors.append(f'Changed boundary needs review: {path}')
        if path in saved and saved[path].get('state') not in ALLOWED_STATES:
            errors.append(f'Unknown review state: {path}')
        if path in saved and saved[path].get('state') in {'closed_legacy', 'closed_scoped', 'not_applicable'} and not saved[path].get('evidence'):
            errors.append(f'Review evidence missing: {path}')
    return errors


def ready_errors(manifest: dict) -> list[str]:
    errors = []
    if manifest.get('operational_rollout') != 'blocked':
        errors.append('This inventory version supports blocked rollout only; no automatic activation is permitted.')
    for path, record in manifest.get('files', {}).items():
        if record.get('state') not in {'closed_legacy', 'closed_scoped', 'not_applicable'}:
            errors.append(f'Uncovered boundary: {path}')
    errors.append('Operational gate remains blocked. Inventory alone does not prove authorization, document isolation or provider safety.')
    return errors


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--root', type=Path, default=Path(__file__).resolve().parents[1])
    parser.add_argument('--manifest', type=Path, default=Path('docs/issuer-isolation-inventory.json'))
    parser.add_argument('--write', action='store_true', help='Write current inventory; changed files revert to unreviewed.')
    parser.add_argument('--assert-ready', action='store_true', help='Fail closed while any boundary/operational review remains pending.')
    parser.add_argument('--details', action='store_true')
    args = parser.parse_args()
    manifest_path = args.manifest if args.manifest.is_absolute() else args.root / args.manifest
    previous = json.loads(manifest_path.read_text()) if manifest_path.exists() else {'files': {}}
    current = discover(args.root)
    if args.write:
        manifest = {
            'version': 1,
            'operational_rollout': 'blocked',
            'limitations': ['Conservative lexical inventory, not complete call-graph or SQL authorization proof.',
                           'Vendor/assets/language/historical migrations and install scripts excluded from runtime inventory.',
                           'Dynamic tables, aliases and extensions require additional manual/runtime coverage.'],
            'files': reconcile(current, previous.get('files', {})),
        }
        manifest_path.parent.mkdir(parents=True, exist_ok=True)
        manifest_path.write_text(json.dumps(manifest, indent=2, ensure_ascii=False) + '\n')
        previous = manifest
    errors = inventory_errors(current, previous.get('files', {}))
    if args.assert_ready:
        errors.extend(ready_errors(previous))
    counts = {}
    for entry in previous.get('files', {}).values():
        state = entry.get('state', 'unreviewed')
        counts[state] = counts.get(state, 0) + 1
    print(json.dumps({'files': len(current), 'states': counts, 'operational_rollout': 'blocked', 'errors': len(errors)}, ensure_ascii=False))
    if args.details:
        for path, entry in current.items():
            print(f"{path}:{','.join(map(str, entry['signal_lines']))} [{','.join(entry['categories'])}]")
    for error in errors:
        print(error, file=sys.stderr)
    return 1 if errors else 0

if __name__ == '__main__':
    raise SystemExit(main())
