# RankKernel — Remediation Summary

Date: 2026-11-05  
Developer: Office OpenCode  
Branch: `release/wporg-0.1.0-final` (existing branch; no new branch, PR, force-push or tag)

## Commits

| | |
|---|---|
| Before remediation | `fc5c6416e9499f3ed93106c1445c9016393d57a8` |
| Production fix commit | `5a5a4260287ebcafba75d82e29c4728d522ae955` |
| Final commit | `2afb1185d919f03ad4ed74444466db3b44db0786` (adds preview regression coverage only) |

Final commit is pushed to `origin/release/wporg-0.1.0-final`. Production files changed only in `5a5a426`; `2afb118` changes one test file.

## Confirmed defects

| Class | Found | Fixed | Unresolved |
|---|---:|---:|---:|
| Input sanitization / validation | 5 | 5 | 0 |
| Output escaping | 0 | 0 | 0 |
| **Total** | **5** | **5** | **0** |

### Input defects

1. **IN-01** Instant Indexing search: raw slashed `$_GET`; search only trimmed. Fixed with `wp_unslash()` at the consumer and `sanitize_text_field()` in `LogFilters`.
2. **IN-02** MetaPayload: `(bool)`/`absint()`/`! empty()` silently coerced nested arrays for robots flags, schema `disabled`, and Open Graph/Twitter `image_id`. Fixed with strict `nullableBool()` and `scalarAbsint()`.
3. **IN-03** REST import JSON body: arrays became `"Array"`/`1` for `kind`, `mode`, `offset`, `limit`, `q`, `excludes`, `run_id`. Fixed with explicit scalar/numeric checks returning 400.
4. **IN-04** SupportRequest: direct array input produced string-cast warnings and an array could satisfy consent. Fixed with `is_scalar` guards.
5. **IN-05** Analysis keyword sanitizer: nested arrays became `"Array"` before REST item validation. Fixed by preserving non-scalars for validation and filtering them in the analyzer.

No high/critical issue was confirmed. Every affected sink already used prepared SQL and contextual output escaping; the fixes close type-validation and early-sanitization gaps.

### The eleven WordPress.org examples

- **Fixed:** #3 `src/Admin/InstantIndexingPage.php:330`.
- **Already safe after individual source review:** #1, #2, #4, #5, #6, #7, #8, #9, #10, #11. Exact evidence is in `RANKKERNEL_INPUT_SANITIZATION_REMEDIATION.md`.

## Input matrix totals

`RANKKERNEL_PRODUCTION_INPUT_MATRIX.csv` retains all 409 original rows.

| Metric | Count |
|---|---:|
| Original occurrences | 409 |
| Executable reads | 401 |
| Comment/docblock-only | 8 |
| Outside production package | 0 |
| PASS | 15 |
| FIXED | 4 |
| ACCEPTED_WITH_JUSTIFICATION | 382 |
| BLOCKED | 0 |
| `not reviewed` | **0** |

The four `FIXED` rows map to IN-01 (one row) and IN-02 (three rows). IN-03, IN-04 and IN-05 are REST/validator/shared-code paths outside the original 16-file superglobal inventory; they are listed in `RANKKERNEL_REMEDIATION_INPUT_ADDITIONS.csv` (20 rows) and covered by the tests below.

## Output matrix totals

`RANKKERNEL_PRODUCTION_OUTPUT_ESCAPING_MATRIX.csv` is unchanged.

| Metric | Count |
|---|---:|
| Output statements | 1,244 |
| PASS_CONTEXTUAL | 1,237 |
| ACCEPTED_WITH_JUSTIFICATION | 7 |
| Confirmed defects | 0 |

## Regression tests added

| Test | Defect |
|---|---|
| `InstantIndexingPageTest::test_search_filter_is_unslashed_and_sanitized` | IN-01 |
| `InstantIndexingLogQueryTest::test_search_term_is_sanitized_before_query` | IN-01 |
| `MetaPayloadTest::test_sanitize_rejects_nested_arrays_for_boolean_and_id_fields` | IN-02 |
| `MetaPayloadTest::test_sanitize_accepts_scalar_boolean_and_id_fields` | IN-02 |
| `ImportControllerTest::test_preview_array_kind_returns_400` | IN-03 |
| `ImportControllerTest::test_run_array_kind_returns_400` | IN-03 |
| `ImportControllerTest::test_run_array_mode_returns_400` | IN-03 |
| `ImportControllerTest::test_run_array_offset_returns_400` | IN-03 |
| `SupportRequestTest::test_array_values_are_rejected_field_by_field` | IN-04 |
| `AnalysisControllerTest::test_nested_keyword_arrays_are_preserved_for_validation` | IN-05 |

## Verification on final commit

| Gate | Command | Result |
|---|---|---|
| PHPUnit | `composer test` | **2385 tests / 9182 assertions — OK** |
| PHPCS/WPCS | `composer lint` | exit 0 |
| PHPStan L6 | `composer stan` | `[OK] No errors` |
| JavaScript tests | `composer test:js` | 352 pass / 0 fail |
| JS syntax | `node --check assets/js/*.js` | clean |
| Composer validate | `composer validate --strict` | valid |
| Composer audit | `composer audit --locked` | no advisories |
| Plugin Check, exact ZIP | WP 7.1.4, Plugin Check 2.1.0 | **0 errors / 223 warnings** |
| WP 7.1.4 admin smoke | exact ZIP | 9 RankKernel screens HTTP 200 |
| Public smoke | exact ZIP | home 200; sitemap 200 XML; robots 200 text; unknown path 404 |
| Debug log | exact ZIP runtime | no plugin warnings/notices/fatals |
| Manifest comparison | ZIP vs installed | 239/239 hashes match |

Plugin Check warnings are unchanged from the prior audit: 203 template-local `NonPrefixedVariableFound` warnings and 20 `DirectDB.UnescapedDBParameter` warnings for prepared custom-table SQL with int-cast IDs. No suppression was added.

## Final package

| Artifact | Value |
|---|---|
| Path | `/home/web-dev-3/Documents/my personal plugin/release-output-2afb118/rankkernel-0.1.0.zip` |
| Size | **902,022 bytes** |
| Files | **239** (single top-level `rankkernel/`) |
| ZIP SHA-256 | **`87d94a568cf3a404f4b84648277e20fc81ed64de8fce5c6064343c9a3c0d8252`** |
| Manifest | `MANIFEST.sha256`, 239 lines |
| Manifest SHA-256 | **`e6ad78e0c97f3f26f34bd2a7de56a8fe10f9778b0aa326f316e54169026b3f87`** |

The prior `fc5c641` ZIP (`0d891a9f…`) is superseded and must not be used.

## Remaining WordPress.org approval blockers

1. Independent Server OpenCode audit of the exact final ZIP `87d94a56…` has not yet been performed.
2. The reviewer's exact 48-line scanner list remains unavailable; the 409-row matrix is a source-level inventory with handler tracing, not a one-to-one reproduction.
3. WordPress 6.5.14 was not available in this environment for a final-ZIP smoke test.
4. No WordPress.org upload, GitHub release, tag or publication has occurred. The owner authorizes submission only after the independent audit.

## Verdict

**READY FOR SERVER INDEPENDENT AUDIT** of commit `2afb118` and ZIP `87d94a56…`. This is not an independent approval.
