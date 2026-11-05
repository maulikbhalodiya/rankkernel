# RankKernel — Final Test Report

Final source: `fb386a419d6b32394cc342cf740f8e41a75389bb`  
Release branch: `release/wporg-0.1.0`  
Final ZIP: `rankkernel-0.1.0.zip` — 902,973 bytes, 239 files  
ZIP SHA-256: `4edb5beb087c1668c6678f4ccc768dd9432cb8ef2aa5fd70420f5e89e113287a`  
Manifest: `manifest.txt` — 239 rows, SHA-256 `1298873705f453e1bcf8430b0f62bdcf5bf2ed0929f0f98dc993be2e1b7de000`

## Source history

| | Commit | ZIP SHA-256 | ZIP size | Result |
|---|---|---|---:|---|
| Previous independently audited candidate | `2afb118` | `87d94a56…` | 902,022 | Approved with non-blocking follow-ups |
| Final remediation candidate | `fb386a4` | `4edb5beb…` | 902,973 | **This report** |

The previous `2afb118` results are historical and were not reused for this candidate.

## Automated gates (final commit)

| Gate | Command | Result |
|---|---|---|
| PHPUnit | `composer test` | **2392 tests / 9197 assertions — OK** |
| Focused audit tests | PHPUnit filters for RK-F-02/03/04/05/06 | pass |
| PHPCS/WPCS | `composer lint` | exit 0 |
| PHPStan L6 | `composer stan` | `[OK] No errors` |
| Psalm Security (local) | Psalm 5.26.1 `--taint-analysis` | **exit 0, no taint errors** |
| JavaScript tests | `composer test:js` | 352 pass / 0 fail |
| JS syntax | `node --check assets/js/*.js` | clean |
| Composer validation | `composer validate --strict` | valid |
| Composer audit | `composer audit --locked` | no advisories |
| Plugin Check, exact ZIP, WP 7.1.4 | Plugin Check 2.1.0 | **0 errors / 223 warnings** |
| Plugin Check, exact ZIP, WP 6.5.14 | Plugin Check 2.1.0 | **0 errors / 223 warnings** |

### Psalm note

The CI job still uses `continue-on-error: true`, so the green CI check alone is not treated as proof. A local Psalm 5.26.1 security scan was run against `src/` with the WordPress stubs and `--taint-analysis`; it exited **0** with **no taint errors**. Standard non-taint Psalm at level 6 reports unrelated typing/config noise, so only the security result is claimed as clean.

## New regression tests

| Test | Finding |
|---|---|
| `MetaPayloadTest::test_sanitize_rejects_nested_arrays_for_pillar_and_cornerstone_flags` | RK-F-02 |
| `MetaPayloadTest::test_sanitize_accepts_scalar_pillar_and_cornerstone_flags` | RK-F-02 |
| `BreadcrumbsOutputTest::test_block_boolean_attributes_reject_malformed_values` | RK-F-03 |
| `BreadcrumbsOutputTest::test_block_show_on_home_page_rejects_malformed_value` | RK-F-03 |
| `ModuleRegistryTest::test_label_exception_message_is_not_pre_escaped` | RK-F-04 |
| `PluginTest::test_network_activation_is_refused_before_any_setup` | RK-F-05 |
| `ImportControllerTest::test_release_with_array_run_id_returns_400` | RK-F-06 |

## Plugin Check warning breakdown

Identical on both WordPress versions:

- **203** `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound` in `src/Admin/Views/**`. All view files are included inside methods; no `global` declarations. False positives.
- **20** `PluginCheck.Security.DirectDB.UnescapedDBParameter`. All locations use prepared placeholders, fixed DDL, a prepared UPDATE, or a regex-gated prefix-swept table name. Reviewed at each flagged line; false positives/safe.
- No new warning category was introduced by the remediation.

## Runtime smoke tests — exact ZIP

Environment setup: exact ZIP extracted into the plugin directory; all 10 modules enabled; pretty permalinks flushed; `WP_DEBUG` and `WP_DEBUG_LOG` enabled.

| Check | WP 7.1.4 | WP 6.5.14 |
|---|---|---|
| Home | 200 | 200 |
| Sitemap index | 200 XML | 200 XML |
| robots.txt | 200 text | 200 text |
| Unknown path | 404 | 404 |
| RankKernel dashboard | 200 | 200 |
| Instant Indexing | 200 | 200 |
| Redirects | 200 | 200 |
| 404 Monitor | 200 | 200 |
| Schema Settings | 200 | 200 |
| Sitemap Settings | 200 | 200 |
| General Settings | 200 | 200 |
| Support | 200 | 200 |
| Importer | 200 | 200 |
| Plugin debug log | no plugin warnings/notices/fatals | no plugin warnings/notices/fatals |

## Exact-package verification

- Source comparison: all **239/239** packaged files match `git show fb386a4:<path>`; 0 missing, 0 extra, 0 hash mismatches.
- ZIP structure: 239 files, one top-level `rankkernel/`, no symlinks/traversal.
- Plugin header/readme: version `0.1.0`, Stable tag `0.1.0`, Tested up to `7.1`, Requires PHP `8.2`.
- The six changed files versus `2afb118` are exactly: `rankkernel.php`, `readme.txt`, `src/Modules/Breadcrumbs/blocks/BreadcrumbsBlock.php`, `src/Modules/Metadata/MetaPayload.php`, `src/Modules/ModuleRegistry.php`, `src/Rest/ImportController.php`.

## Not run / limitations

- Full multisite network activation and network-wide uninstall were not available as a runtime environment. The activation guard is covered by `PluginTest::test_network_activation_is_refused_before_any_setup`; uninstall remains intentionally single-site and is documented in `readme.txt`.
- Real Apache/LiteSpeed were not re-tested in this pass; the changes are PHP validation/lifecycle only.
- No independent approval of `fb386a4` exists yet.
