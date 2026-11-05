# RankKernel — Input Sanitization and Validation Remediation

Date: 2026-11-05  
Developer: Office OpenCode  
Release branch: `release/wporg-0.1.0-final`  
Base commit: `fc5c6416e9499f3ed93106c1445c9016393d57a8`  
Final commit: `2afb1185d919f03ad4ed74444466db3b44db0786`  
Final ZIP: `rankkernel-0.1.0.zip` — 902,022 bytes, 239 files, SHA-256 `87d94a568cf3a404f4b84648277e20fc81ed64de8fce5c6064343c9a3c0d8252`

## Result

| Metric | Count |
|---|---:|
| Confirmed input defect groups | 5 |
| Fixed | 5 |
| Unresolved | 0 |
| Original matrix occurrences reclassified | 409 |
| Original rows now `FIXED` | 4 |
| Original rows already safe (`PASS` / accepted) | 397 |
| Comment/docblock-only rows | 8 |
| `not reviewed` rows | 0 |

No high or critical input vulnerability was confirmed. All five defects were type-validation gaps that could silently coerce malformed arrays or skip early sanitization. SQL was prepared and output escaped at every affected sink, so none was directly exploitable for SQL injection or XSS; they are genuine contract violations of **sanitize early, validate always**.

## IN-01 — Instant Indexing search was neither unslashed nor sanitized early

**Severity:** Low / WordPress.org compliance  
**Locations:** `src/Admin/InstantIndexingPage.php:330`, `src/Modules/InstantIndexing/LogFilters.php:121-128`  
**WordPress.org example:** #3 (`LogQuery::fromInput( $_GET, ... )`)

**What was unsafe**

`InstantIndexingPage` passed the raw slashed `$_GET` array to `LogQuery::fromInput()`. `LogFilters::fromInput()` only trimmed and length-capped the free-text search term. SQL used a prepared placeholder and the search field used `esc_attr()`, so this was not SQL injection or XSS, but the input was not unslashed at the documented consumer boundary and was not sanitized early as required.

**Change**

- `InstantIndexingPage` now passes `wp_unslash( $_GET )`.
- `LogFilters::fromInput()` applies `sanitize_text_field()` to the search term after the length cap. Allowlisted `source` and `status` values and the bounded numeric page/per-page values are unchanged.

**Regression tests**

- `tests/Unit/InstantIndexingPageTest.php::test_search_filter_is_unslashed_and_sanitized` — submits `<b>hello</b> O\'Reilly`, asserts tags are gone, the quote is unslashed, and the rendered search field is escaped.
- `tests/Unit/InstantIndexingLogQueryTest.php::test_search_term_is_sanitized_before_query` — asserts `<b>hello</b>` normalizes to `hello`.
- Existing search, pagination, REST log, and view tests continue to pass.

**Commit:** `5a5a426` (production fix)

## IN-02 — MetaPayload coerced nested arrays into booleans and attachment IDs

**Severity:** Low / data-integrity validation  
**Locations:** `src/Modules/Metadata/MetaPayload.php:178-199` (robots flags), `:232` and `:260` (Open Graph / Twitter image IDs), `:466` (schema `disabled`)  
**Reached from:** `src/Admin/MetadataBox.php:1233`, `:1239`, `:1246`

**What was unsafe**

- `(bool) $robots['index']` and the other flags treated a nested array as `true`.
- `! empty( $raw['disabled'] )` did the same for the schema disabled flag.
- `absint( $og['image_id'] )` and `absint( $tw['image_id'] )` on a nested array become `1`, silently attaching an unrelated attachment ID.
- These are malformed-input paths, not attacker-controlled XSS/SQL sinks, but they violate the requirement not to silently accept unexpected nested values.

**Change**

- Added `MetaPayload::nullableBool()`: accepts real booleans, `0`/`1` integers, and documented checkbox strings (`1/0`, `true/false`, `yes/no`, `on/off`); all other shapes return `null`, so the documented default is kept.
- Added `MetaPayload::scalarAbsint()`: returns `0` for non-scalar/non-numeric values; valid scalars keep `absint()` behavior (including `-42` → `42`).
- Applied both helpers to the robots flags, schema `disabled`, and Open Graph/Twitter `image_id`.

**Regression tests**

- `tests/Unit/MetaPayloadTest.php::test_sanitize_rejects_nested_arrays_for_boolean_and_id_fields` — nested arrays keep `index=true`, `follow=true`, all other flags false, IDs `0`, disabled false.
- `tests/Unit/MetaPayloadTest.php::test_sanitize_accepts_scalar_boolean_and_id_fields` — `'0'`, `'1'`, `1`, `'42'`, and `7` normalize correctly.
- Existing `image_id` absint, REST schema, robots budget, and string-field array rejection tests continue to pass.

**Commit:** `5a5a426` (production fix)

## IN-03 — REST import body parameters accepted arrays through string/int casts

**Severity:** Low / request-shape validation  
**Locations:** `src/Rest/ImportController.php` — `preview()` (kind, offset, limit, q), `runChunk()` (kind, offset, limit, excludes, run_id), `modeFor()` (mode)

**What was unsafe**

`bodyParams()` reads `get_json_params()` directly, bypassing the registered REST arg schema for JSON bodies. The controller then used `(string)` and `(int)` casts:

- an array `kind` or `mode` became the literal string `"Array"` instead of a 400;
- an array `offset` or `limit` became `1`;
- nested `excludes` entries became `"Array"`;
- an array `run_id` became `"Array"`, failing only incidentally on the regex.

**Change**

- `modeFor()` returns `WP_Error` 400 for a non-string `mode`.
- `preview()` and `runChunk()` require a string `kind`, string `q`, and numeric `offset`/`limit`, returning `WP_Error` 400 for malformed shapes.
- `excludes` keeps only scalar entries and sanitizes each with `sanitize_text_field()`.
- `run_id` is accepted only when it is a string; malformed shapes fail the existing run-id pattern with 400.

**Regression tests**

- `tests/Unit/ImportControllerTest.php::test_preview_array_kind_returns_400`
- `tests/Unit/ImportControllerTest.php::test_run_array_kind_returns_400`
- `tests/Unit/ImportControllerTest.php::test_run_array_mode_returns_400`
- `tests/Unit/ImportControllerTest.php::test_run_array_offset_returns_400`
- Existing preview/run/release, limit clamp, excludes, run-id, and permission tests continue to pass.

**Commits:** `5a5a426` (production fix), `2afb118` (preview regression coverage)

## IN-04 — SupportRequest validated arrays with string casts

**Severity:** Low / shared validator contract  
**Locations:** `src/Support/SupportRequest.php:156, 164, 184, 204, 218`

**What was unsafe**

`SupportRequest::validate()` cast `category`, `subject`, `message`, and `email` with `(string)`. A direct caller passing an array produced an `Array to string conversion` warning and the literal `"Array"`. `empty( $input['consent'] )` also treated a non-empty array as consent. `SupportPage` already sanitizes every field to a string before calling the validator, so the production form was not directly affected; the public validator contract was still wrong.

**Change**

- Each field is accepted only when `is_scalar()`; otherwise it is treated as empty and receives the normal field error.
- Consent requires a scalar non-empty value other than `'0'`.
- Diagnostics requires a scalar non-empty value other than `'0'`; an array yields `'0'`.

**Regression test**

- `tests/Unit/SupportRequestTest.php::test_array_values_are_rejected_field_by_field` — arrays produce category/subject/message/email/consent errors and diagnostics `'0'`, with no type warnings.
- Existing complete, category, length, email, consent, and diagnostics tests continue to pass.

**Commit:** `5a5a426`

## IN-05 — Analysis keyword sanitizer cast nested arrays before validation

**Severity:** Low / REST validation bypass  
**Location:** `src/Rest/AnalysisController.php:116-127` and `:163-168`

**What was unsafe**

`sanitizeKeywords()` ran `sanitize_text_field( (string) $keyword )` on every element. A nested array became the string `"Array"` during sanitization, so the later REST item-type validation saw a valid string and could not reject the malformed element. The analyzer also used `array_map( 'strval', ... )` on the already-sanitized list.

**Change**

- `sanitizeKeywords()` sanitizes scalar elements and leaves non-scalars untouched, so `rest_validate_request_arg` rejects them with 400.
- `analyze()` filters non-scalars before `strval` as defense in depth.

**Regression test**

- `tests/Unit/AnalysisControllerTest.php::test_nested_keyword_arrays_are_preserved_for_validation` — scalar `red apples` survives, nested array is preserved for validation instead of becoming `"Array"`.
- Existing hostile-keyword, scalar-list, route-args, permission, and analyzer tests continue to pass.

**Commit:** `5a5a426`

## The eleven WordPress.org examples — individual result

| # | Location | Result | Evidence |
|---:|---|---|---|
| 1 | `src/Admin/UserProfileField.php:90` | **ALREADY SAFE** | `is_string()` guard; `wp_unslash()`; `MetaPayload::sanitizeTwitterHandle()`; capability + nonce; array field ignored. |
| 2 | `src/Admin/SchemaMetabox.php:687` | **ALREADY SAFE** | `is_string()` guard; 1 MiB byte cap; `json_decode(..., 64)`; JSON error check; `MetaPayload::sanitize()`. |
| 3 | `src/Admin/InstantIndexingPage.php:330` | **FIXED (IN-01)** | `wp_unslash( $_GET )`; search sanitized in `LogFilters`; two regression tests. |
| 4 | `src/Admin/SchemaMetabox.php:944` | **ALREADY SAFE** | Upload error, temp path, `is_uploaded_file()`, readability, actual size, MIME/extension probe, byte cap, depth-64 JSON decode, `MetaPayload::sanitize()`. |
| 5 | `src/Admin/NotFoundPage.php:616` | **ALREADY SAFE** | `is_string()` + `sanitize_key()`; scalar-only entry IDs cast to int; `delete` allowlist; capability + nonce. |
| 6 | `src/Admin/RedirectsPage.php:732` | **ALREADY SAFE** | `is_string()` + `sanitize_text_field()`; display-only chain; escaped output. |
| 7 | `src/Admin/SettingsPage.php:470` | **ALREADY SAFE** | Policy map shape checked; `CrawlerPolicy::sanitizeMap()` allowlists each crawler value; override string only. |
| 8 | `src/Admin/MetadataBox.php:1213` | **ALREADY SAFE** | `is_string()`; `esc_url_raw()` through `MetaPayload::sanitize()`. |
| 9 | `src/Admin/MetadataBox.php:1196` | **ALREADY SAFE** | `is_string()` guard means arrays are ignored, never cast; scalar keywords split and sanitized. No heuristic cast-to-string. |
| 10 | `src/Modules/Monitor/Logger.php:262` | **ALREADY SAFE** | `is_string()`; null/control-character strip; host-aware normalization; query redaction; escaped admin output. |
| 11 | `src/Modules/Redirects/Redirector.php:207` | **ALREADY SAFE** | `is_string()`; null/control-character strip; `Normalizer`; `DestinationValidator`; `wp_safe_redirect()`. |

## Additional input surfaces reviewed

REST controllers (`AnalysisController`, `ImportController`, `LogController`, `ModulesController`, `SettingsController`), the redirects AJAX list, settings handlers, metadata/schema metaboxes, shortcode and block attributes, CSV upload/import, support screenshot upload, importers, cron/hook payloads, and values read from the database were re-checked.

- `ModulesController::toggleModule` and `SettingsController::updateSettings` already reject array booleans because `filter_var( ..., FILTER_NULL_ON_FAILURE )` returns `null` for arrays and the controller returns 400.
- `RedirectsPage::listFilters()` already guards every display filter with `is_string`/`is_numeric` and allowlists.
- `NotFoundPage::postedExclusions()` and `SitemapSettingsPage::collectPartial()` already use `is_array` + `is_scalar` guards.
- Support screenshot and schema/CSV upload paths already validate upload error, temp path, actual size, MIME/extension, and JSON/CSV structure.

No further confirmed input defect was found.

## Limitations

- The reviewer's exact 48-line scanner list remains unavailable. The 409-row matrix is a complete source inventory with per-file handler tracing, not 409 independent runtime executions, and is not claimed to map one-to-one to those 48 incidences.
- The new REST/validator fixes are covered by unit tests, not by a live REST request against the exact ZIP. The exact ZIP was loaded under WordPress 7.1.4 and all affected admin screens returned 200 with no plugin debug-log entries.
- WordPress 6.5.14 was not available in this environment for the final ZIP smoke test.

## Verification for this report

| Gate | Result |
|---|---|
| PHPUnit | 2385 tests / 9182 assertions — OK |
| PHPCS/WPCS | exit 0 |
| PHPStan L6 | `[OK] No errors` |
| JavaScript tests | 352 pass / 0 fail |
| Composer validate / audit | valid / no advisories |
| Plugin Check, exact ZIP | 0 errors / 223 warnings (same 203 + 20 documented categories) |
| WP 7.1.4 admin smoke, exact ZIP | 9 RankKernel screens HTTP 200; no plugin warnings/notices/fatals |
| Public smoke, exact ZIP | home 200, sitemap 200 XML, robots 200 text, unknown path 404 |
| Installed-file vs manifest | 239/239 hashes match |
