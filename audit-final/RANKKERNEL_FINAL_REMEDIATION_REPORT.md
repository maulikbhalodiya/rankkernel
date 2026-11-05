# RankKernel 0.1.0 — Final Remediation Report (Server Audit Follow-up)

Date: 2026-11-05  
Role: Office OpenCode, lead developer/implementer  
Release branch: `release/wporg-0.1.0`  
Previous source commit: `2afb1185d919f03ad4ed74444466db3b44db0786`  
Final source commit: `fb386a419d6b32394cc342cf740f8e41a75389bb`  
Final ZIP: `rankkernel-0.1.0.zip` — 902,973 bytes, 239 files, SHA-256 `4edb5beb087c1668c6678f4ccc768dd9432cb8ef2aa5fd70420f5e89e113287a`  
Manifest: `manifest.txt` — 239 rows, SHA-256 `1298873705f453e1bcf8430b0f62bdcf5bf2ed0929f0f98dc993be2e1b7de000`

This report closes the findings from `RANKKERNEL_FINAL_INDEPENDENT_AUDIT.md` and `RANKKERNEL_FINAL_AUDIT_FINDINGS.csv` on `audit/wporg-0.1.0-final-delivery`. The Server audit remains historical evidence and was not modified.

## Findings table RK-F-01 through RK-F-14

| ID | Severity | Status after this round | Evidence |
|---|---|---|---|
| RK-F-01 | High | **Prepared, awaiting owner asset-deletion approval** | New exact candidate built and verified from `fb386a4`. Draft `v0.1.0-rc-rel01` still contains the three stale assets; replacement requires owner approval because the old `rankkernel-0.1.0.zip` name must be deleted first. |
| RK-F-02 | Low | **Fixed** | `MetaPayload.php:285,289` now use `nullableBool(...) ?? false`; arrays/objects/malformed strings cannot become true. Tests added. |
| RK-F-03 | Low | **Fixed** | `BreadcrumbsBlock.php` now reads `showHomeItem`, `showCurrentItem` and `showOnHomePage` through `boolAttribute()`; malformed values fall back to the setting/default. Tests added. |
| RK-F-04 | Info | **Fixed** | `ModuleRegistry::label()` throws the raw message; no pre-escaped exception text. Test added. |
| RK-F-05 | Low | **Fixed with documented single-site policy** | `rankkernel_activate( $network_wide )` refuses network activation and network-deactivates before any option/cron/table setup; `readme.txt` documents the block and single-site uninstall scope. Unit test added. |
| RK-F-06 | Info | **Fixed** | `ImportController::release()` now uses the same strict `is_string()` `run_id` guard as `runChunk()`; arrays return 400. Test added. |
| RK-F-07 | Info | **Retained intentionally** | Legacy flat-list schema rows remain supported for backward compatibility. `sanitizeSchema()` now documents that the shape is never rendered as HTML and is JSON-encoded/consumed through known fields. No code behavior change. |
| RK-F-08 | Info | **Verified false positives; no change** | Exact new ZIP: 0 errors / 223 warnings on WP 7.1.4 and WP 6.5.14; same 203 view-scope + 20 prepared-SQL categories. No suppression added. |
| RK-F-09 | Info | **Resolved** | Classified `RANKKERNEL_PRODUCTION_INPUT_MATRIX.csv` (409 rows: 15 PASS, 4 FIXED, 382 ACCEPTED, 8 comment-only, 0 blocked, 0 `not reviewed`) and `RANKKERNEL_REMEDIATION_INPUT_ADDITIONS.csv` (28 rows) are published on `audit/final-fc5c641` and pointed to from `docs/audit-final/README.md` on the release branch. |
| RK-F-10 | Info | **Rejected false positive; no change** | WordPress 7.1.4 is real; `Tested up to: 7.1` remains truthful. |
| RK-F-11 | Info | **Closed; no change** | Breadcrumb KSES test requires the exact plugin active. Documented; exact-SHA CI passes. |
| RK-F-12 | Info | **Verified; strengthened locally** | Local Psalm 5.26.1 taint/security analysis ran successfully: **exit 0, no taint errors**. CI `continue-on-error` remains noted; the local run is now the substantive evidence. |
| RK-F-13 | Info | **Measured locally** | Psalm 5.26.1 `--taint-analysis` exited 0 with “No errors found”. Standard non-taint scan at level 6 has unrelated typing/config noise; the security scan is clean. |
| RK-F-14 | Info | **Closed; no code change** | Exact new ZIP passes WP 7.1.4 and WP 6.5.14 runtime smoke after enabling all modules. |

## Code-level changes

### RK-F-02 — MetaPayload pillar/cornerstone

`src/Modules/Metadata/MetaPayload.php`:

```php
$out['flags']['pillar']      = self::nullableBool( $flags['pillar'] ) ?? false;
$out['flags']['cornerstone'] = self::nullableBool( $flags['cornerstone'] ) ?? false;
```

`nullableBool()` accepts real booleans, `0`/`1`, and documented checkbox strings; arrays, objects and unknown strings return `null`, so the field keeps its default `false`.

Regression tests:
- `MetaPayloadTest::test_sanitize_rejects_nested_arrays_for_pillar_and_cornerstone_flags`
- `MetaPayloadTest::test_sanitize_accepts_scalar_pillar_and_cornerstone_flags`

### RK-F-03 — Breadcrumbs block booleans

`src/Modules/Breadcrumbs/blocks/BreadcrumbsBlock.php`:

- Added `boolAttribute( array $attributes, string $key, bool $fallback ): bool`.
- `showHomeItem` and `showCurrentItem` fall back to the stored breadcrumb setting when absent or malformed.
- `showOnHomePage` falls back to `false`.
- Accepted shapes: real booleans, `0`/`1` integers, and `1/0`, `true/false`, `yes/no`, `on/off`, empty string. Anything else uses the fallback.

Regression tests:
- `BreadcrumbsOutputTest::test_block_boolean_attributes_reject_malformed_values`
- `BreadcrumbsOutputTest::test_block_show_on_home_page_rejects_malformed_value`

### RK-F-04 — ModuleRegistry exception boundary

`src/Modules/ModuleRegistry.php` now throws:

```php
throw new \InvalidArgumentException( sprintf( 'Unknown module id: %s', $id ) );
```

No display site exists. The message is raw so a future display site escapes once. The PHPCS exception-not-escaped sniff is documented inline.

Regression test:
- `ModuleRegistryTest::test_label_exception_message_is_not_pre_escaped`

### RK-F-05 — Network activation policy

`rankkernel.php`:

- `rankkernel_activate( $network_wide = false )`.
- When `$network_wide` is true, the plugin is network-deactivated before requirements, options, cron events or tables are touched, then an administrator-facing `wp_die()` explains that single-site activation is required.
- Single-site activation is unchanged.
- `readme.txt` now says network activation is blocked and uninstall is scoped to the current site.

Regression test:
- `PluginTest::test_network_activation_is_refused_before_any_setup`

Uninstall remains intentionally single-site. Because network activation is refused, no partial network data can be seeded. The existing single-site uninstall purge tests remain in place.

### RK-F-06 — Import release run_id

`src/Rest/ImportController.php`:

```php
$runIdRaw = $params['run_id'] ?? null;
$runId    = is_string( $runIdRaw ) ? sanitize_text_field( $runIdRaw ) : '';
```

Arrays/objects now fail the existing run-id pattern with 400, matching `runChunk()`.

Regression test:
- `ImportControllerTest::test_release_with_array_run_id_returns_400`

### RK-F-07 — Legacy schema list shape

No behavior change. `sanitizeSchema()` now documents that legacy flat-list rows are retained deliberately, are never rendered as HTML, and reach the final sink through JSON encoding with HEX flags and known schema-piece fields.

## Evidence publication (RK-F-09)

The release branch now contains `docs/audit-final/README.md`, which points to the current evidence on `audit/final-fc5c641`:

- `RANKKERNEL_FINAL_REMEDIATION_REPORT.md`
- `RANKKERNEL_FINAL_TEST_REPORT.md`
- `RANKKERNEL_RELEASE_WORKFLOW.md`
- `RANKKERNEL_WPORG_HANDOFF.md`
- `RANKKERNEL_PRODUCTION_FILE_MANIFEST.txt`
- `SHA256SUMS`
- `RANKKERNEL_PRODUCTION_SECURITY_AUDIT.md`
- `RANKKERNEL_PRODUCTION_INPUT_MATRIX.csv`
- `RANKKERNEL_PRODUCTION_OUTPUT_ESCAPING_MATRIX.csv`
- `RANKKERNEL_HISTORIC_WPORG_FINDINGS.md`
- `RANKKERNEL_REMEDIATION_INPUT_ADDITIONS.csv`
- `PLUGIN_CHECK_FINAL.json`

`docs/` is excluded by `.distignore`; these files do not ship in the ZIP.

## Branch consolidation

- Active release branch: `release/wporg-0.1.0` at `fb386a4`.
- `main`: `fb386a4`, identical to the active release branch.
- Old release/remediation branches verified as ancestors of `fb386a4`, then deleted locally and remotely:
  - `release/wporg-0.1.0-final`
  - `release/wporg-0.1.0-integration`
  - `release/wporg-0.1.0-review-fixes`
  - `release-candidate/integrate-0.1.0`
  - `transfer/release-finalize-0.1.0`
  - `wporg/remediation-2026-11`
  - `wporg/remediation-2026-10`
  - `wporg/audit-2026-10`
  - `wporg/final-verification`
  - `wporg/review-fixes-consolidated`
- Preserved evidence branches: `audit/final-fc5c641` (current reports) and `audit/wporg-0.1.0-final-delivery` (Server's independent audit).
- No force-push or history rewrite was used.

## Local release storage

`Rankkernel/release-output/` now contains exactly the current and last candidate:

```text
rankkernel-0.1.0.zip        current, fb386a4, 4edb5beb…
rankkernel-0.1.0-last.zip   last, 2afb118, 87d94a56…
manifest.txt                239 rows
SHA256SUMS
BUILD_INFO.md
SHA256SUMS-github           prepared for draft asset replacement
RELEASE_NOTES_DRAFT.md      prepared, not published
```

Older release folders, extracted copies, root ZIPs, frozen baseline and `rankkernel-old/` were removed. The release-output SHAs verify with `sha256sum -c`.

## Old and new package identity

| | Previous | Final |
|---|---|---|
| Source commit | `2afb118` | `fb386a4` |
| ZIP | `rankkernel-0.1.0.zip` | `rankkernel-0.1.0.zip` |
| Size | 902,022 bytes | **902,973 bytes** |
| SHA-256 | `87d94a568cf3a404f4b84648277e20fc81ed64de8fce5c6064343c9a3c0d8252` | **`4edb5beb087c1668c6678f4ccc768dd9432cb8ef2aa5fd70420f5e89e113287a`** |
| Files | 239 | **239** |
| Manifest | 239 rows | **239 rows, SHA-256 `1298873705f453e1bcf8430b0f62bdcf5bf2ed0929f0f98dc993be2e1b7de000`** |

The six changed packaged files are exactly: `rankkernel.php`, `readme.txt`, `src/Modules/Breadcrumbs/blocks/BreadcrumbsBlock.php`, `src/Modules/Metadata/MetaPayload.php`, `src/Modules/ModuleRegistry.php`, `src/Rest/ImportController.php`.

## Verification

| Gate | Result |
|---|---|
| PHPUnit | **2392 tests / 9197 assertions — OK** |
| Focused audit tests | All RK-F-02/03/04/05/06 tests pass |
| PHPCS/WPCS | exit 0 |
| PHPStan L6 | `[OK] No errors` |
| Psalm Security (local, Psalm 5.26.1) | `--taint-analysis` exit 0, no taint errors |
| JavaScript | 352 pass / 0 fail; `node --check` clean |
| Composer validate / audit | valid / no advisories |
| Plugin Check WP 7.1.4, exact ZIP | 0 errors / 223 warnings |
| Plugin Check WP 6.5.14, exact ZIP | 0 errors / 223 warnings |
| WP 7.1.4 smoke | 9 admin screens 200; home 200; sitemap 200 XML; robots 200; unknown path 404; debug log clean |
| WP 6.5.14 smoke | 9 admin screens 200; home 200; sitemap 200 XML; robots 200; unknown path 404; debug log clean |
| Source/package comparison | 239/239 packaged files match `fb386a4`; 0 extra, 0 mismatch |

## Remaining limitations

- Network activation is verified by unit test with `$network_wide = true`; no full multisite network shutdown/uninstall runtime was available. The guard runs before any setup, so partial network state is avoided.
- The three stale GitHub draft assets remain until the owner approves the exact deletion list. No draft asset was deleted or replaced.
- The draft release was not retargeted or published, because its current assets still mismatch the new candidate.
- No WordPress.org upload, submission, tag, GitHub release publication, or independent approval is claimed.

## Release status

**READY FOR SERVER INDEPENDENT AUDIT** of commit `fb386a4` and ZIP `4edb5beb…`, subject to the owner approving replacement of the stale draft assets.

The previous independent audit approved `2afb118` with non-blocking follow-ups. This report does not claim approval of `fb386a4`.
