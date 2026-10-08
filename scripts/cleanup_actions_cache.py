#!/usr/bin/env python3
"""Safely expire only stale ARGWS CRM GitHub Actions caches for main/develop."""
import argparse, datetime as dt, json, os, re
from pathlib import Path
from urllib.parse import urlencode
from urllib.request import Request, urlopen

PUBLISH_PATHS = {".github/workflows/container-publish.yml", ".github/workflows/release-packages.yml"}
RETENTION_DAYS = 30
MAX_DELETIONS = 100
HEX_SHA = re.compile(r"^[0-9a-f]{40}$")

def parse_time(value):
    if not value:
        return None
    try:
        parsed = dt.datetime.fromisoformat(value.replace("Z", "+00:00"))
        return parsed if parsed.tzinfo else parsed.replace(tzinfo=dt.timezone.utc)
    except (TypeError, ValueError):
        return None

def plan_caches(caches, expected_ref, now):
    cutoff = now - dt.timedelta(days=RETENTION_DAYS)
    decisions = []
    for cache in caches:
        accessed = parse_time(cache.get("last_accessed_at")) or parse_time(cache.get("created_at"))
        row = {"id": cache.get("id"), "key": cache.get("key"), "ref": cache.get("ref"), "size_in_bytes": cache.get("size_in_bytes", 0)}
        if cache.get("ref") != expected_ref:
            row.update(decision="preserve", reason="different-ref")
        elif not isinstance(cache.get("id"), int) or not cache.get("key"):
            row.update(decision="preserve", reason="incomplete-cache-record")
        elif accessed is None:
            row.update(decision="preserve", reason="unknown-age")
        elif accessed > cutoff:
            row.update(decision="preserve", reason="recently-used")
        else:
            row.update(decision="candidate", reason="older-than-retention-window")
        decisions.append(row)
    return decisions

def trusted_publisher(run, repo, branch, sha):
    return (
        branch in {"main", "develop"} and bool(HEX_SHA.fullmatch(sha or ""))
        and run.get("status") == "completed" and run.get("conclusion") == "success"
        and run.get("path") in PUBLISH_PATHS and run.get("event") in {"push", "workflow_dispatch"}
        and run.get("head_branch") == branch and run.get("head_sha") == sha
        and (run.get("repository") or {}).get("full_name") == repo
        and (run.get("head_repository") or {}).get("full_name") == repo
    )

class GitHub:
    def __init__(self, token, api_url=None):
        self.token = token
        self.api_url = (api_url or os.getenv("GITHUB_API_URL", "https://api.github.com")).rstrip("/")

    def request(self, method, path):
        request = Request(
            self.api_url + path, method=method,
            headers={
                "Accept": "application/vnd.github+json",
                "Authorization": f"Bearer {self.token}",
                "X-GitHub-Api-Version": "2022-11-28",
                "User-Agent": "argws-crm-cache-retention",
            },
        )
        with urlopen(request, timeout=30) as response:
            payload = response.read()
        return json.loads(payload) if payload else {}

    def pages(self, repo, route, collection, params=None):
        output = []
        for page in range(1, 101):
            query = {**(params or {}), "per_page": 100, "page": page}
            payload = self.request("GET", f"/repos/{repo}/{route}?{urlencode(query)}")
            rows = payload.get(collection, [])
            output.extend(rows)
            if len(rows) < 100:
                break
        return output

def find_publisher(api, repo, branch, sha, run_id):
    if run_id is not None:
        run = api.request("GET", f"/repos/{repo}/actions/runs/{run_id}")
        return run if trusted_publisher(run, repo, branch, sha) else None
    runs = api.pages(repo, "actions/runs", "workflow_runs", {"head_sha": sha})
    matches = [run for run in runs if trusted_publisher(run, repo, branch, sha)]
    return max(matches, key=lambda run: run.get("id", 0), default=None)

def branch_contains_publication(api, repo, branch, sha):
    ref = api.request("GET", f"/repos/{repo}/git/ref/heads/{branch}")
    current = (ref.get("object") or {}).get("sha", "")
    if not HEX_SHA.fullmatch(current):
        return False
    result = api.request("GET", f"/repos/{repo}/compare/{sha}...{current}")
    return result.get("status") in {"ahead", "identical"}

def has_active_run(api, repo, branch, current_run_id):
    for status in ("queued", "in_progress", "waiting", "pending", "requested"):
        runs = api.pages(repo, "actions/runs", "workflow_runs", {"branch": branch, "status": status})
        for run in runs:
            if run.get("id") == current_run_id:
                continue
            if run.get("path") == ".github/workflows/actions-cache-retention.yml":
                continue
            if run.get("head_branch") == branch and run.get("status") != "completed":
                return True
    return False

def cleanup(api, repo, branch, sha, source_run_id=None, apply=False, now=None, current_run_id=0):
    report = {"mode": "dry-run", "branch": branch, "verified_sha": sha, "retention_days": RETENTION_DAYS, "deleted": [], "decisions": []}
    if branch not in {"main", "develop"} or not HEX_SHA.fullmatch(sha or ""):
        report.update(mode="blocked", reason="Invalid branch or publication SHA.")
        return report
    publisher = find_publisher(api, repo, branch, sha, source_run_id)
    if publisher is None:
        report.update(mode="blocked", reason="No successful trusted publisher found; no cache was changed.")
        return report
    if not branch_contains_publication(api, repo, branch, sha):
        report.update(mode="blocked", reason="Published commit is no longer in the target branch history.")
        return report
    if has_active_run(api, repo, branch, current_run_id):
        report.update(mode="blocked", reason="A workflow is still active on the target branch.")
        return report
    expected_ref = f"refs/heads/{branch}"
    caches = api.pages(repo, "actions/caches", "actions_caches", {"ref": expected_ref})
    decisions = plan_caches(caches, expected_ref, now or dt.datetime.now(dt.timezone.utc))
    candidates = sorted((row for row in decisions if row["decision"] == "candidate"), key=lambda row: row.get("size_in_bytes", 0), reverse=True)
    report["decisions"] = decisions
    report["mode"] = "apply" if apply else "dry-run"
    report["publisher_run_id"] = publisher.get("id")
    if not apply:
        report["reason"] = "Report only; no cache was changed."
        return report
    for row in candidates[:MAX_DELETIONS]:
        if not branch_contains_publication(api, repo, branch, sha):
            report.update(mode="partial", reason="Branch changed during cleanup; remaining caches were preserved.")
            break
        if has_active_run(api, repo, branch, current_run_id):
            report.update(mode="partial", reason="A workflow started during cleanup; remaining caches were preserved.")
            break
        exact = api.pages(repo, "actions/caches", "actions_caches", {"key": row["key"], "ref": expected_ref})
        current = next((cache for cache in exact if cache.get("id") == row["id"]), None)
        if current is None:
            report["deleted"].append({"id": row["id"], "decision": "preserve", "reason": "already-absent"})
            continue
        refreshed = plan_caches([current], expected_ref, dt.datetime.now(dt.timezone.utc))[0]
        if refreshed["decision"] != "candidate":
            report["deleted"].append({**refreshed, "decision": "preserve"})
            continue
        api.request("DELETE", f"/repos/{repo}/actions/caches/{row['id']}")
        report["deleted"].append({**row, "decision": "deleted"})
    if len(candidates) > MAX_DELETIONS:
        report["remaining_candidates"] = len(candidates) - MAX_DELETIONS
    return report

def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--repository", required=True)
    parser.add_argument("--branch", choices=("main", "develop"), required=True)
    parser.add_argument("--verified-sha", required=True)
    parser.add_argument("--source-run-id", type=int)
    parser.add_argument("--apply", action="store_true")
    parser.add_argument("--output", default="cache-retention-report.json")
    args = parser.parse_args()
    token = os.getenv("GH_TOKEN") or os.getenv("GITHUB_TOKEN")
    if not token:
        raise RuntimeError("GH_TOKEN is required.")
    report = cleanup(
        GitHub(token), args.repository, args.branch, args.verified_sha,
        args.source_run_id, args.apply, current_run_id=int(os.getenv("GITHUB_RUN_ID", "0")),
    )
    destination = Path(args.output)
    destination.parent.mkdir(parents=True, exist_ok=True)
    destination.write_text(json.dumps(report, indent=2) + "\n", encoding="utf-8")
    print(json.dumps({key: report.get(key) for key in ("mode", "branch", "reason", "publisher_run_id", "remaining_candidates")}))
    print(f"Cache candidates: {sum(row['decision'] == 'candidate' for row in report.get('decisions', []))}; deleted: {sum(row.get('decision') == 'deleted' for row in report.get('deleted', []))}.")

if __name__ == "__main__":
    main()
