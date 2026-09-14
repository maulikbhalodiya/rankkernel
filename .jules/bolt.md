# Bolt's Journal - Critical Learnings

## 2025-05-18 - Static Memory Caching for Table Existence Checks
**Learning:** Table existence checks (like `SHOW TABLES LIKE ...`) execute on the hot path (every request for `RedirectTable::exists()` and every 404 for `LogTable::exists()`). Without in-memory static caching, `SHOW TABLES` queries hit the MySQL server on every invocation, adding unnecessary database roundtrips.
**Action:** Use request-level static memory caching in table classes (`RedirectTable`, `LogTable`) to execute `SHOW TABLES` at most once per request, clearing or setting the cache whenever table schemas are modified/ensured.
