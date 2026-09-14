# Redirects and 404 Monitor Security

What the code actually does for each threat in the binding plan. Every
row is covered by an automated test; the test column names the proving
test.

| Threat | Mitigation | Test |
|---|---|---|
| Open redirect | Destinations validated at save and again at the send boundary; external hosts require the `rankkernel/redirect/allowed_hosts` allowlist | `RedirectsDestinationValidatorTest`, `RedirectsRedirectorTest::test_unsafe_destination_never_sent` |
| Unsafe schemes (`javascript:`, `data:`, `file:`, `vbscript:`) | Scheme allowlist via `wp_allowed_protocols`, rejected at save and at send | `RedirectsDestinationValidatorTest::test_unsafe_schemes_rejected`, send boundary test in `RedirectsRedirectorTest` |
| Malformed URLs | Parsed and normalized through `Normalizer`; rejected on failure | `RedirectsNormalizerTest`, `RedirectsAdminTest` field validation |
| CRLF and control characters in destination | Rejected before storage and before send by `DestinationValidator` | `RedirectsDestinationValidatorTest::test_crlf_rejected`, `::test_control_characters_rejected`, send boundary test in `RedirectsRedirectorTest` |
| Header injection | No user data in header names or values; only the cleaned destination reaches `wp_redirect` | `RedirectsRedirectorTest` |
| XSS | All admin output escaped; stored referer and user agent treated as untrusted and escaped on display | `RedirectsAdminTest`, `MonitorAdminTest` render tests |
| CSRF | Nonce on every mutation, verified server side in `requireAccess()` on both screens | Capability plus nonce tests per action in `RedirectsAdminTest`, `MonitorAdminTest`, `RedirectsMonitorSecurityTest` |
| Unauthorized access | `manage_options` on every page and every mutating action on both screens | Capability failure tests per action in `RedirectsAdminTest`, `MonitorAdminTest`, `RedirectsMonitorSecurityTest` |
| SQL injection | Prepared statements with placeholders everywhere; order columns whitelisted; id lists cast to int before interpolation | `RedirectsRepositoryTest`, `MonitorRepositoryTest` |
| Regex injection | User patterns escaped and wrapped, compile tested at save in the admin form and CSV import | `RedirectsAdminTest`, `RedirectsCsvTest::test_invalid_regex_rejected` |
| ReDoS and catastrophic patterns | Pattern cap 20 rules (`Matcher::MAX_REGEX_RULES`), length cap 200 (`Matcher::MAX_REGEX_LENGTH`), anchored wrapping, regex matched last, fail closed on `preg_last_error` | `RedirectsMatcherTest` |
| Oversized input | Length limits on source, target (2000), and CSV rows (2 MB file, 5000 rows) | `RedirectsAdminTest`, `RedirectsCsvTest::test_row_count_limit_stops_the_run` |
| CSV formula injection | `CsvHandler::sanitize_cell()` on import and `::escape_cell()` on export prefix dangerous leading characters with a single quote | `RedirectsCsvTest::test_formula_prefix_neutralized_on_import`, `::test_export_escapes_formula_cells` |
| Malicious CSV upload | Per row validation, bounded batches, no code execution | `RedirectsCsvTest` |
| Redirect loops | Graph cycle detection at save (DFS, depth 10, nodes 50), no internal chain following at runtime | `RedirectsValidatorTest`, `RedirectsAdminTest::test_add_loop_blocks_save_and_shows_path` |
| Redirect chains | Bounded warning (5 hops), saveable, with a recommended final destination | `RedirectsValidatorTest`, `RedirectsAdminTest::test_add_chain_saves_with_warning` |
| External destinations | Explicit allowlist, documented policy, permitted for legitimate SEO once allowlisted | `RedirectsDestinationValidatorTest` |
| SSRF | No server side fetch of destinations, only browser redirects | By construction in `Redirector` |
| Cache poisoning | Positive caching only, keyed by normalized path hash, invalidated on every write and toggle via a validator bump | `RedirectsCacheTest` |
| 404 flood | Dedupe on `UNIQUE (uri_hash)`, exclusions, per window new URI budget, bounded pruning | `MonitorLoggerTest`, `MonitorFloodGuardTest`, `MonitorPrunerTest` |
| Admin bulk operations | Capability and nonce on bulk and clear paths on both screens | `RedirectsAdminTest`, `MonitorAdminTest`, `RedirectsMonitorSecurityTest` |

Regex safety note: PHP 8.1 has no per call regex timeout. Anchoring
alone does not stop nested quantifier ReDoS. The practical mitigation
is the cap set above (20 rules, 200 characters, last tier) plus
failing closed on engine errors. Compile probes in the admin form and
CSV import swallow only the compile warning inside a guarded handler
that is always restored.
