import datetime as dt
import unittest
from scripts.cleanup_actions_cache import plan_caches, trusted_publisher

class CacheRetentionTests(unittest.TestCase):
    def setUp(self):
        self.now = dt.datetime(2026, 10, 8, tzinfo=dt.timezone.utc)
        self.ref = "refs/heads/develop"

    def test_only_old_cache_on_exact_branch_is_candidate(self):
        rows = [
            {"id": 1, "key": "old", "ref": self.ref, "created_at": "2026-08-01T00:00:00Z", "last_accessed_at": "2026-08-01T00:00:00Z"},
            {"id": 2, "key": "fresh", "ref": self.ref, "created_at": "2026-10-01T00:00:00Z", "last_accessed_at": "2026-10-01T00:00:00Z"},
            {"id": 3, "key": "other-branch", "ref": "refs/heads/feature/test", "created_at": "2026-07-01T00:00:00Z"},
        ]
        decisions = plan_caches(rows, self.ref, self.now)
        self.assertEqual([row["decision"] for row in decisions], ["candidate", "preserve", "preserve"])
        self.assertEqual(decisions[1]["reason"], "recently-used")
        self.assertEqual(decisions[2]["reason"], "different-ref")

    def test_unknown_age_and_incomplete_records_are_preserved(self):
        rows = [
            {"id": 4, "key": "unknown", "ref": self.ref},
            {"key": "missing-id", "ref": self.ref, "created_at": "2026-01-01T00:00:00Z"},
        ]
        decisions = plan_caches(rows, self.ref, self.now)
        self.assertEqual([row["decision"] for row in decisions], ["preserve", "preserve"])
        self.assertEqual([row["reason"] for row in decisions], ["unknown-age", "incomplete-cache-record"])

    def test_only_successful_same_repo_publish_on_expected_branch_is_trusted(self):
        run = {
            "id": 22, "status": "completed", "conclusion": "success",
            "path": ".github/workflows/container-publish.yml", "event": "push",
            "head_branch": "develop", "head_sha": "a" * 40,
            "repository": {"full_name": "wkarts/ARGWS-CRM"},
            "head_repository": {"full_name": "wkarts/ARGWS-CRM"},
        }
        self.assertTrue(trusted_publisher(run, "wkarts/ARGWS-CRM", "develop", "a" * 40))
        self.assertFalse(trusted_publisher({**run, "event": "pull_request"}, "wkarts/ARGWS-CRM", "develop", "a" * 40))
        self.assertFalse(trusted_publisher({**run, "conclusion": "failure"}, "wkarts/ARGWS-CRM", "develop", "a" * 40))
        self.assertFalse(trusted_publisher({**run, "head_repository": {"full_name": "fork/ARGWS-CRM"}}, "wkarts/ARGWS-CRM", "develop", "a" * 40))
        self.assertFalse(trusted_publisher(run, "wkarts/ARGWS-CRM", "main", "a" * 40))

if __name__ == "__main__":
    unittest.main()
