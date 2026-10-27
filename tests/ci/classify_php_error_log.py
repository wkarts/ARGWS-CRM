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
    'company_calendar_denied',
)
for line in text.splitlines():
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
