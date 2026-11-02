#!/usr/bin/env bash
# Local pre-push check: the separate CI owners still execute their own gates.
set -euo pipefail
cd "$(dirname "$0")/../.."
python3 scripts/audit-issuer-isolation.py
python3 scripts/inventory-company-boundaries.py --check
php scripts/company-surface-inventory.php --check
census="$(mktemp)"
trap 'rm -f "$census"' EXIT
php scripts/native-experience-inventory.php > "$census"
diff -u --label committed-action-census --label generated-action-census docs/companies/native-experience-action-census.json "$census"
