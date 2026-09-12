# Performance Claims

Honest claims for the Redirects and 404 Monitor modules, each mapped
to the test that proves it. No claim is made without a proving test.

| Path | Queries | Writes | Notes | Proven by |
|---|---|---|---|---|
| Module disabled | 0 | 0 | no class, no hooks | `RedirectsModuleTest::test_disabled_module_boots_zero_hooks`, `MonitorModuleTest::test_disabled_module_boots_zero_hooks`, `RedirectsMonitorGatingTest` |
| Cache hit | 0 rule queries | 0 before response | object cache or transient hit plus the in memory map | `RedirectsRedirectorTest::test_cache_hit_runs_zero_rule_queries` |
| Cold miss, exact | 1 indexed lookup | 0 before response | `UNIQUE (match_type, source_hash)`, then the cache warms | `RedirectsRedirectorTest::test_cold_miss_exact_runs_one_indexed_lookup` |
| Pattern match | 1 cached pattern list read | 0 before response | bounded cached list, at most 500 rows, regex capped at 20 rules | `RedirectsPatternBoundTest`, `RedirectsMatcherTest` |
| Redirect request | as above | 1 coalesced counter update at shutdown | never before the response | `RedirectsHitCounterTest::test_records_coalesce_until_flush`, `::test_each_rule_gets_one_update` |
| 404 dedupe hit | 1 lookup plus 1 update | 1 bounded update | no insert | `MonitorRepositoryTest::test_record_inserts_then_increments_without_duplicate_row`, `MonitorLoggerTest::test_repeat_404_increments_same_row_across_requests` |
| 404 insert | 1 lookup plus 1 insert | 1 insert plus 1 bounded prune at shutdown | pruning at shutdown, bounded | `MonitorLoggerTest::test_insert_schedules_shutdown_prune`, `MonitorPrunerTest` |
| 404 prune | bounded deletes only | bounded deletes only | age batch 500, count batch excess plus 20 percent margin capped at 500 | `MonitorPrunerTest`, `MonitorRepositoryTest::test_delete_oldest_over_removes_oldest_first_bounded` |
| Admin request | normal admin queries | per action | no frontend hooks on admin screens | `RedirectsAdminTest`, `MonitorAdminTest` |

The headline claim: zero cost when the module is off, cache first when
on, at most one targeted query on a cold miss, one coalesced counter
write at shutdown, bounded pruning.

What is not claimed: exact counter durability (an in flight increment
is lost on a hard kill, acceptable for statistics and documented in
`HitCounter`), sub query admin lists (pagination is bounded to 100
rows per page), or multisite aggregation (v1 is per site).
