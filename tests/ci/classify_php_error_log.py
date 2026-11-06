#!/usr/bin/env python3
"""Classify disposable CI PHP logs without copying exception messages or values."""
import pathlib
import re
import sys

text = sys.stdin.buffer.read(1048576).decode('utf-8', 'replace')
root = pathlib.Path.cwd().resolve()
# Codes below are fixed identifiers, not arbitrary values supplied by the request.
codes = (
    'issuer_mail_transport_write_unconfirmed', 'issuer_portal_mail_write_unconfirmed',
    'company_settings_write_unconfirmed', 'company_settings_invalid_input',
    'native_settings_invalid', 'native_mail_settings_invalid', 'native_mail_settings_stale',
    'company_calendar_denied', 'native_portal_secure_origin_required',
)
# Application-owned diagnostics contain no exception text or request arguments.
# Only repository PHP basenames are emitted; unknown names are not copied.
source_names = None
bounded = re.compile(r'(?:^| --> )(native_asaas_operation_failure|native_sales_form_failure(?: kind=(?:estimates|proposals))) class=(engine|runtime) file=([A-Za-z0-9_.-]{1,255}) line=([1-9][0-9]{0,6})$')
for line in text.splitlines():
    fixed = bounded.search(line)
    if fixed:
        if source_names is None:
            source_names = {path.name for folder in ('application', 'modules', 'system')
                            for path in (root / folder).rglob('*.php') if path.is_file()}
        location = fixed[3] + ':' + fixed[4] if fixed[3] in source_names else 'unavailable'
        print('kind=' + fixed[2] + ' location=' + location + ' code=' + fixed[1])
        continue
    if line.startswith('PHP=') and re.fullmatch(r'PHP=[0-9.]+ memory_limit=[0-9A-Za-z-]+', line):
        print(line)
        continue
    if 'Severity:' not in line and 'Exception:' not in line:
        continue
    kind = 'exception' if 'Exception:' in line else 'php_error'
    match = re.search(r'(/app/((?:application|system|modules)/[A-Za-z0-9_./-]+\.php))\s+(\d+)\s*$', line)
    location = 'unavailable'
    if match:
        candidate = (root / match.group(2)).resolve()
        if candidate.is_relative_to(root) and candidate.is_file():
            location = match.group(2) + ':' + match.group(3)
    found = [code for code in codes if re.search(r'(?<![A-Za-z0-9_])' + re.escape(code) + r'(?![A-Za-z0-9_])', line)]
    print('kind=' + kind + ' location=' + location + ' code=' + (','.join(found) or 'unclassified'))
