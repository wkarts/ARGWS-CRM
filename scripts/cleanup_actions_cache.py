#!/usr/bin/env python3
"""Clean only GitHub Actions caches when no other repository workflow is active."""
import argparse, json, os
from pathlib import Path
from urllib.parse import urlencode
from urllib.request import Request, urlopen

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
        output, seen, total = [], set(), None
        page = 1
        while True:
            query = {**(params or {}), "per_page": 100, "page": page}
            payload = self.request("GET", f"/repos/{repo}/{route}?{urlencode(query)}")
            rows, count = payload.get(collection), payload.get("total_count")
            if not isinstance(rows, list) or type(count) is not int or count < 0:
                raise RuntimeError("Incomplete API collection")
            if total is not None and count != total:
                raise RuntimeError("Collection changed during pagination")
            total = count
            for row in rows:
                identity = row.get("id")
                if type(identity) is not int or identity in seen:
                    raise RuntimeError("Missing or repeated API identity")
                seen.add(identity)
            output.extend(rows)
            if len(output) == total:
                return output
            if not rows or len(output) > total or len(rows) < 100:
                raise RuntimeError("Truncated API collection")
            page += 1

def active_runs(api, repo, current_run_id):
    # Do not infer safety from branch names: PRs can restore default-branch caches.
    runs = api.pages(repo, "actions/runs", "workflow_runs")
    for run in runs:
        if type(run.get("id")) is not int or not isinstance(run.get("status"), str):
            raise RuntimeError("Incomplete workflow run")
    return [run["id"] for run in runs
            if run["id"] != current_run_id and run["status"] != "completed"]


def cleanup_idle(api, repo, apply=False, current_run_id=0):
    report = {"mode": "dry-run", "deleted": [], "decisions": [], "delete_attempt": None,
              "bytes_before": None, "bytes_after": None, "bytes_deleted": 0,
              "limitation": "Run checks are conservative snapshots, not an atomic cache lock."}
    try:
        busy = active_runs(api, repo, current_run_id)
        if busy:
            report.update(mode="blocked", reason="other-workflows-active", active_run_ids=busy)
            return report
        caches = api.pages(repo, "actions/caches", "actions_caches")
        def validate(rows):
            for row in rows:
                if (type(row.get("id")) is not int or row["id"] <= 0
                        or type(row.get("size_in_bytes")) is not int or row["size_in_bytes"] < 0
                        or not isinstance(row.get("key"), str) or not row["key"]
                        or not isinstance(row.get("ref"), str) or not row["ref"]):
                    raise RuntimeError("Incomplete cache record")
            return sum(row["size_in_bytes"] for row in rows)
        report["bytes_before"] = validate(caches)
        report["mode"] = "apply" if apply else "dry-run"
        report["decisions"] = [{**row, "decision": "candidate", "reason": "repository-idle"}
                               for row in caches]
        if apply:
            for row in report["decisions"]:
                # Refresh the exact cache, then recheck every run immediately before DELETE.
                exact = api.pages(repo, "actions/caches", "actions_caches",
                                  {"key": row["key"], "ref": row["ref"]})
                validate(exact)
                current = next((item for item in exact if item["id"] == row["id"]), None)
                if current is None:
                    row.update(decision="preserve", reason="already-absent")
                    continue
                if any(current.get(k) != row.get(k) for k in
                       ("key", "ref", "size_in_bytes", "last_accessed_at", "created_at")):
                    row.update(decision="preserve", reason="cache-changed")
                    continue
                busy = active_runs(api, repo, current_run_id)
                if busy:
                    report.update(mode="partial", reason="workflow-started", active_run_ids=busy)
                    break
                report["delete_attempt"] = {"id": row["id"], "outcome": "uncertain"}
                api.request("DELETE", f"/repos/{repo}/actions/caches/{row['id']}")
                report["delete_attempt"]["outcome"] = "confirmed"
                row.update(decision="deleted", reason="deletion-confirmed")
                report["deleted"].append(dict(row))
                report["bytes_deleted"] += row["size_in_bytes"]
        report["bytes_after"] = validate(api.pages(repo, "actions/caches", "actions_caches"))
    except Exception as error:
        # No retries after an uncertain DELETE; preserve confirmed receipts and fail closed.
        report.update(mode="partial" if report["deleted"] else "blocked",
                      reason="api-error-or-incomplete-snapshot", error_type=type(error).__name__)
    return report


def main():
    parser = argparse.ArgumentParser(description="Clean Actions caches only while the repository is idle.")
    parser.add_argument("--repository", required=True)
    parser.add_argument("--apply", action="store_true")
    parser.add_argument("--output", default="cache-retention-report.json")
    args = parser.parse_args()
    token = os.getenv("GH_TOKEN") or os.getenv("GITHUB_TOKEN")
    if not token:
        raise RuntimeError("GH_TOKEN is required.")
    report = cleanup_idle(GitHub(token), args.repository, args.apply,
                          current_run_id=int(os.getenv("GITHUB_RUN_ID", "0")))
    destination = Path(args.output)
    destination.parent.mkdir(parents=True, exist_ok=True)
    destination.write_text(json.dumps(report, indent=2) + "\n", encoding="utf-8")
    print(json.dumps(report))
    if report.get("reason") == "api-error-or-incomplete-snapshot":
        raise SystemExit(1)

if __name__ == "__main__":
    main()
