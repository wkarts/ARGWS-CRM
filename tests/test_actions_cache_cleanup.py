import unittest
from scripts.cleanup_actions_cache import GitHub, cleanup_idle

REPO = 'wkarts/ARGWS-CRM'

class FakeAPI:
    def __init__(self, busy_at=None, failure=None):
        self.caches = [{'id': i, 'key': key, 'ref': ref, 'size_in_bytes': i * 1024,
                        'last_accessed_at': '2026-10-29T00:00:00Z'}
                       for i, key, ref in [(1, 'crm-ci-php-8.1', 'refs/heads/main'),
                                           (2, 'pip', 'refs/pull/52/merge'),
                                           (3, 'release', 'refs/heads/develop')]]
        self.calls, self.run_reads, self.busy_at, self.failure = [], 0, busy_at, failure

    def pages(self, repo, route, collection, params=None):
        self.calls.append(('GET', route, params))
        if route == 'actions/runs':
            self.run_reads += 1
            if self.failure == 'runs': raise RuntimeError('unavailable')
            return [{'id': 999, 'status': 'in_progress'},
                    {'id': 17, 'status': 'waiting' if self.run_reads == self.busy_at else 'completed'}]
        if self.failure == 'caches': raise RuntimeError('incomplete')
        return [dict(c) for c in self.caches if not params or c['key'] == params['key']]

    def request(self, method, path):
        self.calls.append((method, path))
        if self.failure == 'delete': raise TimeoutError('uncertain')
        cache_id = int(path.rsplit('/', 1)[1])
        self.caches = [c for c in self.caches if c['id'] != cache_id]
        return {}

class CacheCleanupTests(unittest.TestCase):
    def test_dry_run_never_deletes_even_recent_all_scope_candidates(self):
        api = FakeAPI()
        report = cleanup_idle(api, REPO, current_run_id=999)
        self.assertEqual(report['mode'], 'dry-run')
        self.assertEqual(len(report['decisions']), 3)
        self.assertEqual(report['bytes_before'], report['bytes_after'])
        self.assertFalse(any(c[0] == 'DELETE' for c in api.calls))

    def test_idle_cleans_all_scopes_without_age_prefix_or_batch_limit(self):
        api = FakeAPI()
        api.caches *= 1
        report = cleanup_idle(api, REPO, apply=True, current_run_id=999)
        self.assertEqual([r['id'] for r in report['deleted']], [1, 2, 3])
        self.assertEqual(report['bytes_before'], 6144)
        self.assertEqual(report['bytes_deleted'], 6144)
        self.assertEqual(report['bytes_after'], 0)
        self.assertEqual(api.run_reads, 4)

    def test_any_other_run_blocks_before_cache_access(self):
        api = FakeAPI(busy_at=1)
        report = cleanup_idle(api, REPO, apply=True, current_run_id=999)
        self.assertEqual(report['reason'], 'other-workflows-active')
        self.assertEqual(len(api.calls), 1)

    def test_new_run_stops_before_next_delete(self):
        api = FakeAPI(busy_at=3)
        report = cleanup_idle(api, REPO, apply=True, current_run_id=999)
        self.assertEqual(report['mode'], 'partial')
        self.assertEqual([r['id'] for r in report['deleted']], [1])
        self.assertEqual(report['bytes_after'], 5120)

    def test_api_failures_never_retry_or_claim_success(self):
        for failure in ['runs', 'caches', 'delete']:
            with self.subTest(failure=failure):
                api = FakeAPI(failure=failure)
                report = cleanup_idle(api, REPO, apply=True, current_run_id=999)
                self.assertEqual(report['reason'], 'api-error-or-incomplete-snapshot')
                self.assertEqual(report['deleted'], [])
                self.assertLessEqual(sum(c[0] == 'DELETE' for c in api.calls), 1)
                self.assertIsNone(report['bytes_after'])

    def test_new_or_unknown_status_blocks(self):
        api = FakeAPI()
        original = api.pages
        def pages(repo, route, collection, params=None):
            return [{'id': 9, 'status': 'new_pending_state'}] if route == 'actions/runs' else original(repo, route, collection, params)
        api.pages = pages
        self.assertEqual(cleanup_idle(api, REPO, True, 999)['mode'], 'blocked')

    def test_changed_cache_is_preserved(self):
        api = FakeAPI()
        original = api.pages
        def pages(repo, route, collection, params=None):
            rows = original(repo, route, collection, params)
            if params:
                for row in rows: row['last_accessed_at'] = '2026-10-29T01:00:00Z'
            return rows
        api.pages = pages
        report = cleanup_idle(api, REPO, True, 999)
        self.assertEqual(report['deleted'], [])
        self.assertTrue(all(r['reason'] == 'cache-changed' for r in report['decisions']))

class PaginationTests(unittest.TestCase):
    def api(self, payloads):
        api = GitHub('synthetic-unused')
        pages = iter(payloads)
        api.request = lambda *args: next(pages)
        return api

    def test_full_pagination_more_than_one_hundred_pages(self):
        payloads = [{'total_count': 10101, 'rows': [{'id': n} for n in range(i*100, min((i+1)*100, 10101))]} for i in range(102)]
        self.assertEqual(len(self.api(payloads).pages(REPO, 'unused', 'rows')), 10101)

    def test_missing_truncated_duplicate_and_changing_collections_fail_closed(self):
        cases = [ [{}], [{'total_count': 2, 'rows': [{'id': 1}]}],
                  [{'total_count': 2, 'rows': [{'id': 1}, {'id': 1}]}],
                  [{'total_count': 101, 'rows': [{'id': n} for n in range(100)]}, {'total_count': 102, 'rows': [{'id': 100}]}] ]
        for payloads in cases:
            with self.subTest(payloads=payloads), self.assertRaises(RuntimeError):
                self.api(payloads).pages(REPO, 'unused', 'rows')

if __name__ == '__main__': unittest.main()
