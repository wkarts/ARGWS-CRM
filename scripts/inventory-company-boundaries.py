#!/usr/bin/env python3
"""Deterministic, conservative inventory. Matches locate review work, never prove isolation."""
from __future__ import annotations
import argparse, hashlib, json, re
from pathlib import Path

ROOTS = ('application', 'modules', 'assets/js', 'docker', 'install')
EXTENSIONS = {'.php', '.js', '.ts', '.sql', '.yml', '.yaml', '.json', '.sh', '.conf'}
DEPENDENCIES = {'vendor', 'node_modules', 'third_party', 'sdk', 'SDK', 'libraries/vendor'}
PATTERNS = {
    'database': r'->\s*(?:query|prepare|where|or_where|join|from|insert|update|delete|count_all_results|get_where)\s*\(|\b(?:SELECT|INSERT\s+INTO|UPDATE|DELETE\s+FROM)\b',
    'settings': r'\b(?:get_option|update_option|add_option|delete_option|config_item|getenv|defined)\s*\(|\b(?:smtp_|api_key|client_secret|paymentmethod_)',
    'authorization': r'\b(?:staff_can|staff_cant|is_admin|has_permission|canAccess|resource_can|access_denied)\s*\(',
    'identity_context': r'\b(?:issuer_id|company_id|clientid|customer_id|contact_id|staffid|staff_id|tenant_id)\b',
    'session_cache': r'\b(?:session|userdata|set_userdata|cache|redis|memcached|localStorage|sessionStorage)\b',
    'background': r'\b(?:cron|schedule|scheduled|queue|worker|webhook|callback|recurring|recurrence)\b',
    'transport': r'\b(?:curl_|http_client|Guzzle|send_mail|send_email|mail\s*\(|send_invoice|notification|sms)',
    'files': r'\b(?:get_upload_path|uploads/|move_uploaded_file|readfile|file_get_contents|file_put_contents|fopen|unlink|download|attachment)\b',
    'documents_reports': r'\b(?:pdf|tcpdf|export|csv|statement|report|dashboard|chart)\b',
    'extension_points': r'\b(?:hooks\s*\(|add_action|add_filter|register_activation_hook|register_staff_capabilities|load->model|load->helper)\b',
    'routes': r'\b(?:public\s+function|\$route\[|add_route|router\.|fetch\s*\(|ajax\s*\(|axios\.)',
}
REGEX = {key: re.compile(pattern, re.I) for key, pattern in PATTERNS.items()}

def classify(path: str) -> str:
    parts = Path(path).parts
    if any(part in DEPENDENCIES for part in parts): return 'dependency_boundary'
    if '/language/' in path: return 'translation'
    if '/views/' in path or path.startswith('assets/'): return 'presentation'
    if '/migrations/' in path or path.endswith('.sql'): return 'schema_history'
    if path.startswith(('docker/', 'install/')): return 'provisioning'
    if path.startswith('modules/'): return 'module:' + parts[1]
    return 'core'

def inventory(root: Path, reviews: dict | None = None) -> dict:
    reviews = reviews or {}
    files: dict[str, Path] = {}
    for folder in ROOTS:
        for path in (root / folder).rglob('*'):
            if path.is_file() and not path.is_symlink() and path.suffix.lower() in EXTENSIONS:
                files[path.relative_to(root).as_posix()] = path
    entries = []
    for name, path in sorted(files.items()):
        data = path.read_bytes()
        digest = hashlib.sha256(data).hexdigest()
        category = classify(name)
        hits = []
        # Third-party source is an explicit boundary requiring an adapter review, not silently omitted.
        if category != 'dependency_boundary':
            for number, line in enumerate(data.decode('utf-8', errors='replace').splitlines(), 1):
                kinds = [kind for kind, regex in REGEX.items() if regex.search(line)]
                if kinds: hits.append({'line': number, 'kinds': kinds})
        review = reviews.get(name, {})
        accepted = (review.get('sha256') == digest and review.get('decision') in ('scoped', 'global_infrastructure')
                    and bool(review.get('tests')) and bool(review.get('rationale')))
        entries.append({'path': name, 'sha256': digest, 'category': category,
                        'status': review['decision'] if accepted else 'unreviewed',
                        'stale_review': bool(review) and not accepted,
                        'matches': hits[:500], 'matches_truncated': len(hits) > 500,
                        'match_count': len(hits)})
    categories = {}
    for entry in entries: categories[entry['category']] = categories.get(entry['category'], 0) + 1
    unresolved = sum(entry['status'] == 'unreviewed' or entry['matches_truncated'] for entry in entries)
    return {'format': 1, 'purpose': 'Review inventory only; lexical matches do not establish runtime isolation.',
            'operational_ready': bool(entries) and unresolved == 0, 'files': len(entries),
            'unresolved_files': unresolved, 'categories': categories, 'entries': entries}

def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--root', type=Path, default=Path(__file__).resolve().parents[1])
    parser.add_argument('--output', type=Path, default=Path('docs/companies/boundary-inventory.json'))
    parser.add_argument('--reviews', type=Path, default=Path('docs/companies/boundary-reviews.json'))
    parser.add_argument('--check', action='store_true')
    parser.add_argument('--full', action='store_true', help='Write full per-file matches for a CI artifact instead of the versioned summary')
    parser.add_argument('--require-ready', action='store_true')
    args = parser.parse_args()
    reviews_path = args.root / args.reviews
    reviews = json.loads(reviews_path.read_text()) if reviews_path.exists() else {}
    result = inventory(args.root, reviews)
    report = result if args.full else {key: value for key, value in result.items() if key != 'entries'}
    if not args.full:
        report['source_sha256'] = hashlib.sha256(json.dumps([(e['path'], e['sha256'], e['status'], e['stale_review']) for e in result['entries']], separators=(',', ':')).encode()).hexdigest()
        report['detail_generation'] = 'python scripts/inventory-company-boundaries.py --full --output /tmp/company-boundaries-full.json'
    encoded = json.dumps(report, ensure_ascii=False, indent=2) + '\n'
    output = args.root / args.output
    if args.check:
        if not output.exists() or output.read_text() != encoded:
            print('Inventory is stale; regenerate and review changed boundaries.')
            if not args.full:
                print('Expected boundary source_sha256: ' + report['source_sha256'])
            return 1
    else:
        output.parent.mkdir(parents=True, exist_ok=True); output.write_text(encoded)
    print(f"{result['files']} files; {result['unresolved_files']} unresolved; operational_ready={result['operational_ready']}")
    return 1 if args.require_ready and not result['operational_ready'] else 0
if __name__ == '__main__': raise SystemExit(main())
