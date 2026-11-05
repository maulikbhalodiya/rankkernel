# RankKernel — Final Test Report

Source `2afb1185d919f03ad4ed74444466db3b44db0786`  
Final ZIP `rankkernel-0.1.0.zip` — 902,022 bytes, 239 files, SHA-256 `87d94a568cf3a404f4b84648277e20fc81ed64de8fce5c6064343c9a3c0d8252`  
Manifest SHA-256 `e6ad78e0c97f3f26f34bd2a7de56a8fe10f9778b0aa326f316e54169026b3f87`

## Automated gates (final commit)

| Gate | Command | Result |
|---|---|---|
| PHPUnit | `composer test` | **2385 tests / 9182 assertions — OK** |
| PHPCS/WPCS | `composer lint` | exit 0 |
| PHPStan L6 | `composer stan` | `[OK] No errors` |
| JavaScript tests | `composer test:js` | **352 pass / 0 fail** |
| JS syntax | `node --check assets/js/*.js` | clean |
| Composer validation | `composer validate --strict` | valid |
| Composer audit | `composer audit --locked` | no advisories |
| Plugin Check, exact ZIP | WP 7.1.4 + Plugin Check 2.1.0 | **0 errors / 223 warnings** |

PHPUnit count rose from 2375/9150 at `fc5c641` to 2385/9182 at `2afb118`, reflecting ten new regression tests for the five input-remediation groups.

## Plugin Check warning analysis

- **203** `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound`: template-local variables inside included admin views, not globals. Reviewed at each file/line; no suppression added.
- **20** `PluginCheck.Security.DirectDB.UnescapedDBParameter`: custom-table SQL. Values are prepared placeholders, IDs are int-cast, and table identifiers are plugin-controlled/validated. Reviewed at each source location; no suppression added.

No new warning category appeared after the remediation.

## Exact-ZIP WordPress 7.1.4 smoke test

Environment: `/tmp/opencode/wp-final-audit`, WordPress 7.1.4, PHP 8.4.27, `WP_DEBUG=true`, `WP_DEBUG_LOG=true`. The exact ZIP was unpacked over the plugin directory; 239/239 installed file hashes matched the ZIP manifest.

| Check | Result |
|---|---|
| RankKernel dashboard | HTTP 200 |
| Instant Indexing | HTTP 200 |
| Redirects | HTTP 200 |
| 404 Monitor | HTTP 200 |
| Schema Settings | HTTP 200 |
| Sitemap Settings | HTTP 200 |
| General Settings | HTTP 200 |
| Support | HTTP 200 |
| Importer | HTTP 200 |
| Home page | HTTP 200 |
| `/sitemap_index.xml` | HTTP 200, `application/xml; charset=UTF-8` |
| `/robots.txt` | HTTP 200, `text/plain; charset=utf-8` |
| Unknown path | HTTP 404 |
| Admin search with `<b>hello</b> O\'Reilly` | HTTP 200; tags stripped, quote unslashed, escaped in field |
| Admin search `s[]=hello` (array) | HTTP 200; no fatal/notice |
| Redirect chain `rk_chain[]=evil` (array) | HTTP 200; no fatal/notice |
| `wp-content/debug.log` | no plugin warnings, notices or fatals |

## Regression coverage added

- `InstantIndexingPageTest::test_search_filter_is_unslashed_and_sanitized`
- `InstantIndexingLogQueryTest::test_search_term_is_sanitized_before_query`
- `MetaPayloadTest::test_sanitize_rejects_nested_arrays_for_boolean_and_id_fields`
- `MetaPayloadTest::test_sanitize_accepts_scalar_boolean_and_id_fields`
- `ImportControllerTest::test_preview_array_kind_returns_400`
- `ImportControllerTest::test_run_array_kind_returns_400`
- `ImportControllerTest::test_run_array_mode_returns_400`
- `ImportControllerTest::test_run_array_offset_returns_400`
- `SupportRequestTest::test_array_values_are_rejected_field_by_field`
- `AnalysisControllerTest::test_nested_keyword_arrays_are_preserved_for_validation`

## Not run in this environment

- **Psalm / Psalm Security:** not installed in the local vendor tree. The CI Psalm jobs were green on the prior commit `fc5c641`; the new remediation changes have not yet been run through the Psalm CI job.
- **WordPress 6.5.14 exact-ZIP smoke:** not available in this environment. The prior release candidate and breadcrumb KSES integration passed on 6.5.14.
- **LiteSpeed:** unavailable; not tested.
- **Real Apache after the remediation:** not re-run; the changes are PHP input-validation only.

No test result above was fabricated. Plugin Check and the HTTP smoke test were run against the exact ZIP whose checksum is recorded above.
