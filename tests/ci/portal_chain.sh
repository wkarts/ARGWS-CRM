#!/usr/bin/env bash
# Sourced only after the original serial prefix. Never a fresh/empty-schema shortcut.
portal_evidence="${ISSUERS_BROWSER_ARTIFACTS:-/tmp/issuer-browser-evidence}/portal-chain"
mkdir -p "$portal_evidence"
docker exec "$upgrade" mkdir -p /app/tests/ci
docker cp tests/ci/portal_state.php "$upgrade:/app/tests/ci/portal_state.php"
portal_snapshot() {
    docker exec -e ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci "$upgrade" php /app/tests/ci/portal_state.php > "$portal_evidence/$1.json"
}
portal_snapshot before
portal_started=$SECONDS
: > "$portal_evidence/scenarios.jsonl"
portal_stage() { portal_scenario_started=$SECONDS; }
portal_done() {
    python3 - "$portal_evidence/scenarios.jsonl" "$1" "$((SECONDS - portal_scenario_started))" <<'PY_RECORD'
import json,sys
with open(sys.argv[1], 'a') as output:
    output.write(json.dumps({'scenario':sys.argv[2], 'elapsed_seconds':int(sys.argv[3]), 'result':'PASS'})+'\n')
print('Portal pilot stage passed: '+sys.argv[2]+' elapsed_seconds='+sys.argv[3])
PY_RECORD
}
portal_stage
ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/companies/project_portal_tasks_browser.cjs
portal_done tests/companies/project_portal_tasks_browser.cjs
portal_stage
ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/companies/portal_task_write_browser.cjs
portal_done tests/companies/portal_task_write_browser.cjs

portal_stage
ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/companies/project_milestones_browser.cjs
portal_done tests/companies/project_milestones_browser.cjs

portal_stage
ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/companies/project_timesheets_browser.cjs
portal_done tests/companies/project_timesheets_browser.cjs

portal_stage
ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/companies/project_gantt_browser.cjs
portal_done tests/companies/project_gantt_browser.cjs

portal_stage
ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/companies/project_contact_files_browser.cjs
portal_done tests/companies/project_contact_files_browser.cjs

portal_elapsed=$((SECONDS - portal_started))
portal_snapshot after
python3 tests/ci/portal_evidence.py record "$portal_evidence" "$portal_mode" "$portal_elapsed"
