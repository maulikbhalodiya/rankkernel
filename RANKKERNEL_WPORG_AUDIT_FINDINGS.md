# RankKernel — WordPress.org Audit Findings (working document)

Temporary working document. **Do not ship in the release ZIP.**
Repo: `/home/web-dev-3/Local Sites/wordpress-test-site/app/public/wp-content/plugins/rankkernel`
Integration branch: `wporg/audit-2026-10` (from verified release tree + `main`).
Target: `main` first, then `release/wporg-0.1.0` from the exact verified `main` commit.

Status: `OPEN` · `IN_PROGRESS` · `FIXED_PENDING_REVIEW` · `VERIFIED` · `REJECTED_WITH_REASON`

## A. PR consolidation matrix

| PR | Purpose | Files | Decision | Reason |
|---|---|---|---|---|
| #397 | Remove dead `check_admin_referer()` false-branches | 11 src + tests | MERGE | No behavior change; superset of #409/#406 |
| #408 | Normalizer request-level cache | Normalizer + tests | MERGE | Bounded (500), key = case+home+raw, tests |
| #404 | RedirectsSettings static cache | RedirectsSettings + 9 tests | MERGE | Blog-keyed, invalidated by `set()`/`ensureSchema()` |
| #405 | 404 settings aria-describedby | not-found view + test | MERGE | IDs exist; tests assert |
| #401 | htaccess/llms aria-describedby | 2 views + test | MERGE | IDs exist; tests assert |
| #396 | htaccess/llms/robots aria | 3 views | CLOSE | Duplicate of #401; robots hint folded in |
| #402 | wp_unslash/type guards | 11 files | CLOSE/SUPERSEDE | Apply safe type guards directly; KeyFileServer semantic change rejected |
| #409 | Instant Indexing nonce | 2 files | CLOSE | Subset of #397 |
| #406 | Nonce cleanup 3 pages | 6 files | CLOSE | Subset of #397 |
| #410 | AI disclosure docs | 0 files | CLOSE | Empty diff |
| #407 | AI disclosure docs | 1 file | CLOSE | Docs-only duplicate; disclosure already tracked |
| #403 | readme Tested up to | 1 file | CLOSE | Diff is docs-only; does not match title; 7.1 is correct |
| #400 | Lazy DestinationValidator | 0 files | CLOSE | Empty; lazy init already present (`Redirector::destinationValidator`) |
| #398 | readme Tested up to 6.7 | 2 files | CLOSE | Incorrect downgrade; 7.1 verified current |
| #395 | RedirectsSettings cache | 6 files | CLOSE | Less correct duplicate of #404 |
| #387 | AI docs + workflow token downgrade | 4 files | CLOSE | Superseded; GH_PAT→GITHUB_TOKEN needs owner decision |
| #342 | Bump opencode-ai-reviewer | 2 workflow files | HOLD | Workflow-only; owner decision; not shipped |

## B. Findings

### RK-001 — Direct `<noscript><link rel=stylesheet>` with PHPCS suppression
- Severity: Medium
- Category: WP.org A / enqueued resources
- Files: `src/Admin/Views/sitemap-settings.php`, `src/Admin/Views/schema-settings.php`, `src/Admin/Views/not-found.php`
- Status: VERIFIED
- Problem: inline `<link rel="stylesheet" href="...admin-nojs.css">` inside `<noscript>`, suppressing `WordPress.WP.EnqueuedResources`.
- Root cause: `AdminMenu::enqueueSharedStyles()` already enqueued `admin-nojs.css` everywhere; the inline tags were redundant. Worse, the CSS forced `[hidden]` panels visible even with JS, because it was not scoped to a no-JS state.
- Fix: enqueue `admin-nojs.css` through `wp_enqueue_style`; add a head inline script `rankkernel-js-flag` that adds `rk-js` to `<html>`; scope all no-JS reveal rules under `html:not(.rk-js)`. Removed all 3 inline tags and all 3 ignores.
- Tests: `AdminStylesTest` (handle, head flag, scoped CSS), `AdminMenuTest` (shared enqueue), `SitemapSettingsAdminTest` (no `<noscript><link`), `MonitorAdminTest` (aria IDs).
- Verification: focused 54 tests/168 assertions; full suite 2338/9034; phpcs clean. Pending independent re-read.

### RK-002 — `.htaccess` backup fallback writes beside root file
- Severity: High
- Category: WP.org B / filesystem
- Files: `src/Admin/HtaccessFile.php` (`backupDir()`, `backupPath()`, `save()`), `SettingsPage::saveHtaccess()`, `Views/sections/htaccess.php`
- Status: VERIFIED
- Problem: when `backupDir()` failed, `backupPath()` returned `$this->path() . '.rankkernel-backup-<ts>'`, creating a backup in the WP root (potentially web-accessible and outside allowed storage).
- Fix: `backupPath()` returns `''`; `save()` fails with `reason=backup_unavailable` and does not modify the file; admin notice/banner explains the uploads directory must be writable. `backupDir()` now refuses non-writable parents before `mkdir()`, prefers `wp_mkdir_p()`, verifies the directory is writable, and fails closed if the guard files cannot be written.
- Tests: `HtaccessFileTest::test_save_refuses_when_no_safe_backup_location_exists` (no sibling backup, original unchanged, reason), normal backup/restore tests.
- Verification: 78 tests/301 assertions (HtaccessFile+SettingsPage), no PHP warnings; full suite green.

### RK-003 — Instant Indexing action marker read before nonce verification
- Severity: Low (reviewer-cited; no write before gate)
- Category: WP.org C / nonce
- File: `src/Admin/InstantIndexingPage.php` `postedAction()` 595-600, `maybeHandleSave()` 143-190, `requireAccess()` 675-685
- Status: VERIFIED
- Problem: `$_POST['rankkernel_indexnow_action']` was read to choose the nonce action, then each handler verified its own nonce; the reviewer saw an input processed without a visible nonce check. Every target handler did gate before writing, and `manage_options` was checked.
- Fix: `maybeHandleSave()` now maps the strict action marker to one of six nonces (`nonceActionFor()`), returns early on an unknown marker, and calls `requireAccess()` once **before** dispatch. Per-handler `requireAccess()` calls removed; comments/ignores now say "nonce verified before dispatch".
- Tests: `test_unknown_action_is_ignored_before_any_nonce_check`, `test_dispatch_verifies_the_nonce_for_the_selected_action`, existing capability/nonce failure tests for regenerate/verify/clear/retry.
- Verification: InstantIndexingPageTest 68 tests/330 assertions; phpcs clean; PHPStan clean. Pending independent re-read.

### RK-004 — `twitter_site` passed to sanitizer without scalar guard
- Severity: Medium
- Category: WP.org D / input
- File: `src/Admin/SettingsPage.php:745`
- Status: VERIFIED
- Problem: `MetaPayload::sanitizeTwitterHandle( wp_unslash( $_POST['twitter_site'] ) )` with no `is_string`/`is_scalar` check; array input can reach custom sanitizer (type error/array-to-string).
- Fix: unslash into `$rawTwitter`, then `is_string( $rawTwitter ) ? MetaPayload::sanitizeTwitterHandle( $rawTwitter ) : ''`.
- Tests: `SettingsPageTest::test_array_settings_input_is_ignored_without_warnings` (array title/separator/twitter ignored, stored values survive, valid field still saves); full suite 2343 tests/9043 assertions.

### RK-005 — `focus_keywords` cast to string turns arrays into "Array"
- Severity: Medium
- Category: WP.org D / arrays
- File: `src/Admin/MetadataBox.php:1180-1224` (esp. 1196)
- Status: VERIFIED
- Problem: `(string) wp_unslash( $_POST['rankkernel_meta_focus_keywords'] )` on an array raises array-to-string warning and imports literal "Array".
- Fix: title/description, focus keywords, and canonical now require `is_string()`; non-string input is skipped so existing stored values survive. Robots/OG/Twitter arrays remain `is_array` validated.
- Tests: `MetadataBoxTest::test_array_focus_keywords_are_rejected_without_warning`; existing focus-keyword split/dedupe tests; full suite green.

### RK-006 — `(string) wp_unslash()` on custom separator / settings scalars
- Severity: Low
- Category: WP.org D / input
- File: `src/Admin/SettingsPage.php:700-752`
- Status: VERIFIED
- Problem: unconditional string casts before/without type checks; array input can produce warnings/invalid settings.
- Fix: text fields, separator choice/custom, breadcrumbs fields, llms summary/content, and notices now require `is_string` before unslash/cast; `settings-updated`/`robots_tab`/`section`/`page` use scalar+string checks. Same `settings-updated` guard applied in `SitemapSettingsPage`, `DashboardPage`, `SchemaSettingsPage`; module toggle and support notice guarded too.
- Tests: `SettingsPageTest::test_array_settings_input_is_ignored_without_warnings`; `DashboardPageTest::test_array_module_toggle_is_ignored`; existing sitemap/schema settings suites; full suite green.

### RK-007 — RedirectsPage GET casts need explicit type guards
- Severity: Low
- Category: WP.org D / input
- File: `src/Admin/RedirectsPage.php:818, 2045, 2096` (plus other GET reads)
- Status: VERIFIED
- Problem: `(string) wp_unslash($_GET[...])` patterns before allowlists; PHP arrays could reach string functions.
- Fix: all 41 HTTP reads across `RedirectsPage` and `NotFoundPage` now require `is_string()` (scalar flags) or `is_array()` (bulk ID/exclusion arrays) before unslash; array fields reverted to `is_array` after an over-broad regex pass. Redundant `is_scalar()` fallbacks removed where PHPStan proved the branch unreachable.
- Tests: existing RedirectsPage/NotFoundPage suites cover filters and row actions; full suite green; PHPStan L6 clean. Dedicated array-input tests to be added in the second audit pass.

### RK-008 — Schema import `$_FILES` workflow needs full validation proof
- Severity: High (pending audit)
- Category: WP.org D / uploads
- File: `src/Admin/SchemaMetabox.php:900-1100`
- Status: VERIFIED
- Problem: reviewer flagged `$_FILES['rankkernel_schema_import']`; current code checks structure, error, tmp_name string, `is_uploaded_file`, readability, name string. Must verify size, extension/MIME, JSON structure, depth, and safe parse.
- Fix: size is read from the verified temp path after `clearstatcache()` and must be `> 0` and `<= 1 MiB` (`MAX_IMPORT_BYTES`); the read contents are re-checked against the same cap; `json_decode(..., true, 64)` bounds nesting depth. MIME/extension still cross-checked via `wp_check_filetype_and_ext()` with a temporary `application/json` allow filter; the temp file is never moved or executed; schema is sanitized by `MetaPayload::sanitize()`.
- Tests: `SchemaMetaboxTest::test_save_import_oversized_file_fails_closed` (1,048,577 bytes), `test_save_import_deeply_nested_json_fails_closed` (70 levels), plus existing missing-file, upload-error, non-JSON, valid-JSON and nonce/capability coverage. Focused: 45 tests/143 assertions OK; phpcs clean.

### RK-009 — `$_SERVER` handling (Logger, Redirector)
- Severity: Low (pending)
- Category: WP.org D / server vars
- Files: `src/Modules/Monitor/Logger.php:262, 579-602`; `src/Modules/Redirects/Redirector.php:207`
- Status: VERIFIED
- Verified: string checks, unslash, control-char stripping, referer query redaction, 255-char truncation, allowlisted keys; host comparison for IndexNow uses site host. `REQUEST_URI` in Logger and Redirector now requires `is_string` before unslash; `SERVER_SOFTWARE`/`REMOTE_ADDR` guarded. `REQUEST_METHOD` comparisons use `?? ''` and never cast. All other `$_SERVER` reads in `src/` are the request-method checks or the guarded fields above.
- Remaining: none identified in this pass; re-check during second audit.
- Tests: existing Logger/Redirector suites cover missing keys, control characters, redaction; full suite green.

### RK-010 — Importer historical risks
- Severity: High (pending audit)
- Category: Importer correctness
- Files: `src/Modules/Importer/*`, `src/Rest/ImportController.php`, `assets/js/importer-admin.js`
- Status: VERIFIED
- Verified: exclusions use the same identity strings the preview emits; runner re-derives rows server side and validates exclusion/choice/rule identities against that set (unknown values throw 400). Posts/terms count excluded rows in `skipped` and set `done` when the fetched slice is smaller than the limit. Redirects re-derive the full preview, skip existing matches, and are idempotent. Sitewide choices are validated against re-derived candidates. Bulk rules must match a re-derived `bulk:{kind}:{type}` identity and enumerate ids in bounded slices. Source metadata is never written. Run lock is claimed once and now released on 400/500/Throwable.
- Fix (UI): confirmation now states, for any kind whose preview is capped, `preview shows X of N; import will process all eligible rows`; progress uses applied+skipped+failed rather than chunks, so multiple bulk rules reach 100%.
- Tests: `tests/js/importer-admin.test.js` 33 tests pass (including progress monotonicity/100% and partial failure); `ImportControllerTest` 29 tests/121 assertions; earlier live browser run applied 1 post, retry skipped 1, preserved Yoast meta, wrote RankKernel meta.

### RK-011 — 230 security PHPCS ignores need case-by-case review
- Severity: Medium
- Category: Code quality / compliance
- Files: 16 files (SettingsPage 54, RedirectsPage 40, NotFoundPage 24, SchemaMetabox 21, SitemapSettingsPage 20, MetadataBox 17, SchemaSettingsPage 16, SupportPage 12, InstantIndexingPage 8, SupportDelivery 3, Logger 3, DashboardPage 3, HtaccessFile 2, Redirector 1, KeyFileServer 1, UserProfileField 1)
- Status: CLOSED — REVIEWED, JUSTIFIED
- Result: every remaining `WordPress.Security.*` ignore marks a request-marker read, a value read only after the nonce gate, or a server field validated immediately below. No blanket suppression was added. The three `WordPress.WP.EnqueuedResources` ignores were removed by RK-001; the count is now zero.

### RK-012 — EscapeOutput suppressions review
- Severity: Low
- Category: Output escaping
- Files: SchemaMetabox 1105; Views/schema-metabox 56/59/70; Views/redirects 564; Views/redirects-list 263/272; Plugin 278; LlmsRouter 109; SchemaModule 281; Sitemaps/Router 259/316; XslStylesheet 55; Breadcrumbs/template-tags 30; KeyFileServer 141; ImportRunner 190; CsvHandler 467/484
- Status: VERIFIED
- Result: each suppression covers a non-HTML context or an already-escaped fragment (JSON/XML/CSV/Markdown bodies, a JSON download, hardcoded attribute fragments, pre-escaped list renderers, a never-echoed exception message, an escaped key-file body). No unescaped user data reaches an HTML context.

### RK-013 — Direct asset tags elsewhere
- Severity: Low
- Category: Enqueued resources
- Files: `AdminStyles.php:134` font preload; `SchemaModule.php:282` JSON-LD; `HeadRenderer.php:321` canonical
- Status: CLOSED — NOT APPLICABLE (legitimate frontend/head output, not admin asset enqueue).

### RK-014 — readme `Tested up to`
- Severity: Low
- Category: WP.org metadata
- File: `readme.txt`
- Status: VERIFIED — 7.1 is current and installable; #398/#403 are incorrect/no-op. Keep 7.1.
- Tests: PluginTest asserts 7.1.

### RK-015 — AI assistance disclosure / external services
- Severity: Low
- Category: WP.org disclosure
- Files: `docs/ai-disclosure.md`, `readme.txt`
- Status: VERIFIED
- Fix: `readme.txt` now ships an `AI Assistance Disclosure` section naming Claude, GitHub Copilot and Jules, the areas assisted, and the developer verification/responsibility statement. `docs/ai-disclosure.md` remains the fuller repository record. External-service disclosure already names the support inbox and IndexNow, their triggers, payloads, timeout and redirect policy.
- Remaining: confirm the shipped readme renders correctly on WordPress.org after upload.

### RK-016 — Import lock stranded after a rejected or unexpected run
- Severity: Medium
- Category: Reliability / concurrency
- File: `src/Rest/ImportController.php::runChunk()`
- Status: VERIFIED
- Problem: `InvalidArgumentException` and `ImportRunException` returned 400/500 without releasing the global lock, so a rejected rule blocked every retry for the 120 s lease. Unexpected throwables escaped the REST boundary.
- Fix: every catch releases the owner-scoped lock before returning, and a `\Throwable` catch returns a generic 500 without exposing the exception message.
- Tests: `ImportControllerTest::test_run_maps_runner_exceptions_to_400` now proves a rejected run frees the lock; `test_run_maps_unexpected_throwable_to_generic_500` proves no internal message leaks and the lock is freed. 29 tests/121 assertions.

### RK-017 — Support screenshot trusted the client-reported size
- Severity: Medium
- Category: WP.org D / uploads
- File: `src/Support/SupportDelivery.php::validateScreenshot()`
- Status: VERIFIED
- Problem: the 2 MB cap used `$_FILES['size']`, which the client controls; a request could claim a small size for a larger file. The MIME checker also fell back to the browser-supplied type when it identified nothing.
- Fix: name/type/tmp are string-checked; the MIME checker must return both an extension and a type; the temporary file is measured with `clearstatcache()` + `filesize()` and must be non-empty and within the cap before acceptance. `wp_handle_upload()` still re-checks type and size when storing.
- Tests: `SupportDeliveryTest::test_a_lying_reported_size_is_refused_by_the_actual_filesize`, existing oversized/non-image/non-uploaded/valid tests. 29 tests/52 assertions.

### RK-018 — IndexNow single events survive deactivation
- Severity: Low
- Category: Lifecycle / cron
- File: `rankkernel.php::rankkernel_deactivate()`
- Status: VERIFIED
- Fix: deactivation now clears `rankkernel_daily_prune` and `rankkernel_indexnow_submit`; data is still left intact.
- Tests: `PluginTest::test_deactivation_clears_daily_prune_event` asserts both hook names.

### RK-019 — SQL and database safety review
- Severity: High (audit area)
- Category: Security / SQL
- Files: `src/Modules/Monitor/*`, `src/Modules/Redirects/*`, `src/Modules/InstantIndexing/*`, `src/Modules/Importer/ImportLock.php`, `src/Modules/Sitemaps/Provider/*`, `uninstall.php`
- Status: VERIFIED
- Result: values travel through `$wpdb->prepare()` with placeholders; `IN (...)` lists are built only from `(int)`-cast, positive, de-duplicated ids; `ORDER BY` columns come from strict allowlists; table identifiers come from `$wpdb->prefix`/constants, and uninstall validates every prefix-matched table name against `^[A-Za-z0-9_]+$` before `DROP`. No user-controlled identifier or unescaped value reached SQL text.

### RK-020 — Redirect security and correctness review
- Severity: High (audit area)
- Category: Security / redirects
- Files: `src/Modules/Redirects/DestinationValidator.php`, `Normalizer.php`, `Matcher.php`, `Redirector.php`, `CsvHandler.php`, `src/Admin/RedirectsPage.php`
- Status: VERIFIED
- Result: destinations reject control characters, backslashes and unsafe schemes; same-host URLs fold to relative paths; external hosts require the `rankkernel/redirect/allowed_hosts` allowlist; `wp_safe_redirect()` sends the validated value. Regex rules are length/pattern-capped, compile with a reduced backtrack limit, and known catastrophic shapes are rejected before save/import. CSV import is capped at 2 MB and 5,000 rows, validates the header, and neutralises spreadsheet formula prefixes. Every create/edit/delete/bulk/import/export handler checks `manage_options` + its own nonce.

### RK-021 — Nonce and capability sweep
- Severity: High (audit area)
- Category: Security / CSRF
- Status: VERIFIED
- Result: every state-changing admin path routes through a handler that calls `requireAccess()` (capability + `check_admin_referer`) before reading or writing: Dashboard toggle, General/Schema/Sitemap/Support saves, Redirects form/bulk/settings/import/export/row actions, 404 clear/bulk/settings/row delete, Schema and Metadata metabox saves/exports, profile field, and Instant Indexing dispatch (nonce chosen from the action before any handler runs). REST import endpoints require `manage_options`; cookie nonces are enforced by core. The remaining `WordPress.Security.*` ignores are marker reads or values read only after the nonce gate, each with an inline reason; no missing check was found. `WordPress.WP.EnqueuedResources` suppressions are now zero.

### RK-022 — Secrets and external HTTP
- Severity: High (audit area)
- Category: Security / privacy
- Status: VERIFIED
- Result: the IndexNow key is stored in site options, shown only on a `manage_options` screen, and sent only to the fixed `https://api.indexnow.org/indexnow` endpoint with `redirection => 0`, a 10 s timeout, and a URL list filtered to the site host. The key check fetches only the site's own key location. Support screenshots go through `wp_handle_upload()` into uploads and are deleted after the mail attempt. No telemetry, tracking, or third-party script was found.

### RK-023 — EscapeOutput suppressions review
- Severity: Low
- Category: Output escaping
- Status: VERIFIED
- Result: each suppression covers a non-HTML context or an already-escaped fragment: JSON/XML/CSV/Markdown response bodies, a JSON download, hardcoded attribute fragments, pre-escaped list renderers, a never-echoed exception message, and an escaped key-file body. No unescaped user data reaches an HTML context.

### RK-024 — Support form cast arrays to strings
- Severity: Medium
- Category: WP.org D / input
- File: `src/Admin/SupportPage.php:95, 124-127`; `src/Admin/SettingsPage.php:445/449`
- Status: VERIFIED
- Problem: `(string) ( $_POST['category'] ?? '' )` and the honeypot/partial flags could turn an array into "Array", producing a warning and a misleading value.
- Fix: each field now requires `is_string()` before unslash/sanitize; non-strings are treated as empty, so required-field validation rejects them without a warning.
- Tests: `SupportPageTest::test_array_text_fields_are_ignored_without_warnings`; Settings tests; 74 tests/265 assertions for the focused pair.

### RK-025 — `$wpdb->prepare()` called with no placeholder on the unfiltered 404 list
- Severity: Low
- Category: Debug notice / SQL hygiene
- File: `src/Modules/Monitor/MonitorRepository.php::filteredWhere()`
- Status: VERIFIED
- Problem: with no search or `min_hits`, the WHERE clause was `WHERE 1=1` with no parameters, but `paginate()` and `countFiltered()` still passed it to `$wpdb->prepare()`. WordPress raised `_doing_it_wrong` notices in `debug.log` on every unfiltered 404 list load.
- Fix: the base clause is now `WHERE 1 = %d` with parameter `1`, so every prepared statement has a placeholder. No behavior change.
- Tests: `MonitorRepositoryTest` 14 tests/68 assertions; browser reload of the 404 screen with `WP_DEBUG` produced no `debug.log` entries.

## C. Verification log
- Automated gates on `wporg/audit-2026-10` @ `4b33419`: PHPUnit `OK (2348 tests, 9059 assertions)`, `composer lint` exit 0, PHPStan L6 `[OK] No errors`, JS `352 pass / 0 fail`, `node --check` clean, `composer validate --strict` valid, `composer audit --locked` no advisories.
- Plugin Check on the packaged candidate (239 files, 896,750 bytes, SHA-256 `768e0179d2bd46bfe2e374d52a5503399c1bdf5ee3c2201cc0103607715cd01a`): **0 errors, 238 warnings**. Warnings: 218 `PrefixAllGlobals.NonPrefixedVariableFound` (template-local variables) and 20 `DirectDB.UnescapedDBParameter` (custom tables with trusted identifiers and int-cast id lists). Both classes were manually reviewed; none is a security defect.
- Clean WordPress 7.1.3 + PHP 8.2, `WP_DEBUG`/`WP_DEBUG_LOG` on, exact candidate ZIP installed:
  - All nine RankKernel admin pages load with 0 console errors; `debug.log` stayed empty after the Monitor prepare fix.
  - Importer: module off by default, toggle persists; Detect shows no warnings; Preview ready; confirmation exact; Run applied 1 post, preserved `_yoast_wpseo_title`/`_yoast_wpseo_metadesc`, wrote `_rankkernel_meta_data` and `_rankkernel_noindex`; retry idempotent; zero console errors.
  - General settings save persisted across reload and was restored; IndexNow key generated, key file served exact key, auto-submit settings persisted.
  - Redirect `/audit-old-page` -> `/` stored as active 301 and served a live 301; invalid nonce row delete returned “link has expired” without deleting.
  - 404 monitor logged a real 404 after pretty permalinks were enabled; page rendered the row.
  - Frontend post: title/description/canonical/robots/OG/Twitter/JSON-LD all present; RankKernel sitemap index, child sitemap and XSL return 200 with Yoast inactive.
  - Aria: 33 `aria-describedby` references across nine pages, 0 missing target IDs.
  - 390px viewport: no horizontal overflow on eight pages; importer has a 1px sub-pixel delta.
  - Unauthenticated admin page: 302 to login; unauthenticated REST import: 403; unknown key file: 404.
  - Deactivate/reactivate: data preserved (1 redirect, 2 log rows, module list), crons cleared on deactivation, daily prune rescheduled on activation.
- Remaining before release: final ZIP built from the exact verified `main`-derived release commit, Plugin Check/hash re-recorded, and the ZIP reinstalled once for a final smoke test.
